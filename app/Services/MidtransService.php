<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MidtransService
{
    public function environment(): string
    {
        return config('services.midtrans.production') ? 'production' : 'sandbox';
    }

    public function configured(): bool
    {
        return filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
    }

    public function token(Payment $payment): string
    {
        $host = $this->environment() === 'production' ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
        $response = Http::withBasicAuth(config('services.midtrans.server_key'), '')
            ->acceptJson()->timeout(15)->post("https://{$host}/snap/v1/transactions", [
                'transaction_details' => ['order_id' => $payment->order_id, 'gross_amount' => $payment->amount],
                'item_details' => [['id' => $payment->plan, 'price' => $payment->amount, 'quantity' => 1, 'name' => 'Muterin Pro '.($payment->plan === 'annual' ? 'Tahunan' : 'Bulanan')]],
                'customer_details' => ['first_name' => $payment->user->name, 'email' => $payment->user->email],
                'callbacks' => ['finish' => route('billing.index')],
            ])->throw();
        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Midtrans did not return a token.');
        }

        return $token;
    }

    public function refresh(Payment $payment): Payment
    {
        abort_unless($this->configured(), 503, 'Pembayaran belum dikonfigurasi.');
        abort_unless($payment->environment === $this->environment(), 409, 'Lingkungan transaksi berbeda.');
        $host = $this->environment() === 'production' ? 'api.midtrans.com' : 'api.sandbox.midtrans.com';
        $payload = Http::withBasicAuth(config('services.midtrans.server_key'), '')
            ->acceptJson()->timeout(15)->get("https://{$host}/v2/{$payment->order_id}/status")->throw()->json();
        abort_unless(($payload['order_id'] ?? null) === $payment->order_id, 422);

        return $this->apply($payload, true);
    }

    public function apply(array $payload, bool $fromStatusApi = false): Payment
    {
        abort_unless(filled(config('services.midtrans.server_key')), 503);
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $key) {
            abort_unless(isset($payload[$key]) && is_string($payload[$key]), 422);
        }
        $signature = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key'));
        abort_unless(hash_equals($signature, $payload['signature_key']), 403);
        $payment = Payment::where('order_id', $payload['order_id'])->firstOrFail();
        abort_unless($payment->environment === $this->environment(), 409);
        abort_unless(preg_match('/^\d+(?:\.00)?$/', $payload['gross_amount']) && (float) $payload['gross_amount'] === (float) $payment->amount, 422);
        abort_unless(($payload['currency'] ?? 'IDR') === 'IDR', 422);

        // Midtrans's signature does not bind transaction_status or fraud_status.
        // Re-fetch them so a replayed, altered notification cannot grant a package.
        if (! $fromStatusApi) {
            return $this->refresh($payment);
        }

        return DB::transaction(function () use ($payment, $payload) {
            // Serialize renewals for the same owner before allocating the next period.
            User::whereKey($payment->user_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $provider = $payload['transaction_status'];
            $accepted = ! isset($payload['fraud_status']) || $payload['fraud_status'] === 'accept';
            $paid = in_array($provider, ['settlement', 'capture'], true) && $accepted;
            if ($payment->status === 'refunded') {
                return $payment;
            }
            if ($paid && $payment->paid_at === null) {
                $latest = Payment::where('user_id', $payment->user_id)->where('environment', $payment->environment)
                    ->where('status', 'paid')->max('ends_at');
                $start = $latest && now()->lt($latest) ? Carbon::parse($latest) : now();
                $payment->fill(['status' => 'paid', 'paid_at' => now(), 'starts_at' => $start,
                    'ends_at' => $payment->plan === 'annual' ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow()]);
            } elseif ($provider === 'refund') {
                $payment->status = 'refunded';
            } elseif ($payment->paid_at === null) {
                $payment->status = match ($provider) {
                    'deny', 'failure' => 'failed', 'expire' => 'expired', 'cancel' => 'cancelled', default => 'pending',
                };
            } else {
                return $payment;
            }
            $payment->provider_status = $provider;
            $payment->payment_type = is_string($payload['payment_type'] ?? null) ? $payload['payment_type'] : $payment->payment_type;
            $payment->save();

            return $payment;
        });
    }
}
