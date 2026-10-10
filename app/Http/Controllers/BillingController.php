<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function checkout(string $plan, MidtransService $midtrans)
    {
        abort_unless(in_array($plan, ['monthly', 'annual'], true), 404);

        return redirect()->route('dashboard', ['upgrade' => 1, 'plan' => $plan]);
    }

    public function index(Request $request, MidtransService $midtrans)
    {
        return redirect()->route('dashboard', ['upgrade' => 1, 'billing' => 1]);
    }

    public function summary(Request $request, MidtransService $midtrans)
    {
        $query = Payment::where('user_id', $request->user()->id)->where('environment', $midtrans->environment());
        $active = (clone $query)->where('status', 'paid')->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderByDesc('ends_at')->first();

        $payments = $query->orderByDesc('id')->paginate(10);

        return response()->json([
            'active' => $active ? ['plan' => $active->plan, 'ends_at' => $active->ends_at->format('d M Y')] : null,
            'payments' => $payments->map(fn (Payment $payment) => [
                'order_id' => $payment->order_id, 'plan' => $payment->plan, 'amount' => $payment->amount, 'status' => $payment->status,
                'created_at' => $payment->created_at->format('d M Y H:i'),
                'starts_at' => $payment->starts_at?->format('d M Y'), 'ends_at' => $payment->ends_at?->format('d M Y'),
                'resumable' => $payment->status === 'pending' && filled($payment->snap_token),
                'refresh_url' => route('billing.refresh', $payment), 'resume_url' => route('billing.resume', $payment),
            ])->values(),
            'pagination' => ['current' => $payments->currentPage(), 'last' => $payments->lastPage(), 'total' => $payments->total()],
        ]);
    }

    public function pay(Request $request, MidtransService $midtrans)
    {
        $data = $request->validate(['plan' => 'required|in:monthly,annual']);
        if (! $midtrans->configured()) {
            return response()->json(['message' => 'Pembayaran belum tersedia saat ini. Silakan coba lagi nanti.'], 503);
        }
        $payment = Payment::create(['user_id' => $request->user()->id, 'order_id' => 'MUTERIN-'.Str::uuid(),
            'plan' => $data['plan'], 'amount' => (int) config("subscription.{$data['plan']}_price"), 'environment' => $midtrans->environment(), 'status' => 'pending']);
        try {
            $token = $midtrans->token($payment);
            $payment->update(['snap_token' => $token]);

            return response()->json(['token' => $token, 'refresh_url' => route('billing.refresh', $payment)]);
        } catch (RequestException|ConnectionException|\RuntimeException $e) {
            $payment->update(['status' => 'failed']);

            return response()->json(['message' => 'Checkout belum bisa dibuka. Coba lagi sebentar.'], 502);
        }
    }

    public function refresh(Request $request, Payment $payment, MidtransService $midtrans)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        try {
            $payment = $midtrans->refresh($payment);

            return response()->json(['status' => $payment->status]);
        } catch (RequestException|ConnectionException $e) {
            return response()->json(['message' => 'Status belum bisa diperiksa. Coba lagi sebentar.'], 502);
        }
    }

    public function resume(Request $request, Payment $payment, MidtransService $midtrans)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless($midtrans->configured(), 503, 'Pembayaran belum dikonfigurasi.');
        abort_unless($payment->environment === $midtrans->environment(), 409);
        abort_unless($payment->status === 'pending' && filled($payment->snap_token), 409, 'Pembayaran ini tidak dapat dilanjutkan. Periksa statusnya.');

        return response()->json(['token' => $payment->snap_token, 'refresh_url' => route('billing.refresh', $payment)]);
    }

    public function notification(Request $request, MidtransService $midtrans)
    {
        try {
            $midtrans->apply($request->all());
        } catch (RequestException|ConnectionException $e) {
            return response()->json(['message' => 'Verifikasi provider belum tersedia. Kirim ulang notifikasi.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
