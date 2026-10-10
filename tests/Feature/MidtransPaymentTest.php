<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    private array $providerPayload = [];

    private int $providerHttpStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => 'test-server', 'services.midtrans.client_key' => 'test-client', 'services.midtrans.production' => false]);
        Http::preventStrayRequests();
        Http::fake(['api.sandbox.midtrans.com/*' => fn () => Http::response($this->providerPayload, $this->providerHttpStatus)]);
    }

    public function test_checkout_requires_login_and_rejects_free_plan(): void
    {
        $this->get(route('billing.checkout', ['plan' => 'monthly']))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('billing.checkout', ['plan' => 'free']))->assertNotFound();
    }

    public function test_server_price_is_used_and_token_does_not_activate_subscription(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'test-snap-token'], 201)]);
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('billing.pay'), ['plan' => 'monthly', 'amount' => 1]);
        $response->assertOk()->assertJsonPath('token', 'test-snap-token');
        $payment = Payment::first();
        $this->assertSame((int) config('subscription.monthly_price'), $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->ends_at);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === (int) config('subscription.monthly_price'));
        $this->assertStringNotContainsString('test-server', $response->getContent());
    }

    public function test_duplicate_settlement_only_grants_one_period_and_old_pending_does_not_revert_paid(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment);
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $payment->refresh();
        $end = $payment->ends_at->toIso8601String();
        $this->assertSame('paid', $payment->status);
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->assertSame($end, $payment->fresh()->ends_at->toIso8601String());
        $this->postJson(route('midtrans.notification'), $this->payload($payment, 'pending'))->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_invalid_signature_and_wrong_amount_cannot_activate_payment(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment);
        $payload['signature_key'] = 'forged';
        $this->postJson(route('midtrans.notification'), $payload)->assertForbidden();
        $payload = $this->payload($payment);
        $payload['gross_amount'] = '1.00';
        $payload['signature_key'] = hash('sha512', $payment->order_id.'200'.'1.00'.'test-server');
        $this->postJson(route('midtrans.notification'), $payload)->assertUnprocessable();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_challenged_card_is_not_paid(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment, 'capture');
        $payload['fraud_status'] = 'challenge';
        $this->providerPayload = $payload;
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_status_refresh_is_owner_only_and_verified_against_midtrans(): void
    {
        $payment = $this->payment();
        $this->providerPayload = $this->payload($payment);
        $this->actingAs(User::factory()->create())->postJson(route('billing.refresh', $payment))->assertForbidden();
        $this->actingAs($payment->user)->postJson(route('billing.refresh', $payment))->assertOk()->assertJsonPath('status', 'paid');
        $this->assertNotNull($payment->fresh()->ends_at);
    }

    public function test_provider_failure_leaves_failed_order_and_returns_safe_error(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['error_messages' => ['secret debug']], 500)]);
        $this->actingAs(User::factory()->create())->postJson(route('billing.pay'), ['plan' => 'annual'])
            ->assertStatus(502)->assertJsonPath('message', 'Checkout belum bisa dibuka. Coba lagi sebentar.');
        $this->assertSame('failed', Payment::first()->status);
    }

    private function payment(): Payment
    {
        return Payment::create(['user_id' => User::factory()->create()->id, 'order_id' => 'MUTERIN-'.fake()->uuid(), 'plan' => 'monthly', 'amount' => config('subscription.monthly_price'), 'environment' => 'sandbox', 'status' => 'pending']);
    }

    public function test_missing_keys_show_setup_state_and_do_not_create_orders(): void
    {
        config(['services.midtrans.server_key' => null]);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard', ['upgrade' => 1, 'plan' => 'annual']))
            ->assertOk()->assertSee('Pembayaran belum tersedia saat ini.');
        $this->postJson(route('billing.pay'), ['plan' => 'annual'])->assertStatus(503);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_calendar_renewal_appends_after_existing_period_without_overflow(): void
    {
        $this->travelTo(Carbon::parse('2026-01-31 12:00:00'));
        $first = $this->payment();
        $this->postJson(route('midtrans.notification'), $this->payload($first))->assertOk();
        $this->assertSame('2026-02-28', $first->fresh()->ends_at->toDateString());
        $second = Payment::create(['user_id' => $first->user_id, 'order_id' => 'MUTERIN-renewal', 'plan' => 'annual', 'amount' => config('subscription.annual_price'), 'environment' => 'sandbox', 'status' => 'pending']);
        $this->postJson(route('midtrans.notification'), $this->payload($second))->assertOk();
        $this->assertSame($first->fresh()->ends_at->toIso8601String(), $second->fresh()->starts_at->toIso8601String());
        $this->assertSame('2027-02-28', $second->fresh()->ends_at->toDateString());
    }

    public function test_refund_cannot_be_reversed_by_late_settlement(): void
    {
        $payment = $this->payment();
        $this->postJson(route('midtrans.notification'), $this->payload($payment))->assertOk();
        $this->postJson(route('midtrans.notification'), $this->payload($payment, 'refund'))->assertOk();
        $this->postJson(route('midtrans.notification'), $this->payload($payment))->assertOk();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->actingAs($payment->user)->getJson(route('billing.summary'))->assertOk()->assertJsonPath('active', null);
    }

    public function test_provider_status_for_a_different_order_is_rejected(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment);
        $payload['order_id'] = 'different-order';
        $this->providerPayload = $payload;
        $this->actingAs($payment->user)->postJson(route('billing.refresh', $payment))->assertUnprocessable();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_history_is_private_and_environment_isolated(): void
    {
        $payment = $this->payment();
        $this->actingAs(User::factory()->create())->getJson(route('billing.summary'))->assertOk()->assertDontSee($payment->order_id);
        $this->actingAs($payment->user)->getJson(route('billing.summary'))->assertOk()->assertSee($payment->order_id);
        config(['services.midtrans.production' => true]);
        $this->getJson(route('billing.summary'))->assertOk()->assertDontSee($payment->order_id);
        $this->postJson(route('billing.refresh', $payment))->assertStatus(409);
    }

    public function test_registration_preserves_the_selected_checkout(): void
    {
        $this->get(route('billing.checkout', ['plan' => 'annual']))->assertRedirect(route('login'));
        $this->post(route('register'), ['name' => 'Pembeli Paket', 'email' => 'pembeli@example.test', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertRedirect(route('billing.checkout', ['plan' => 'annual']));
    }

    public function test_pending_order_can_be_resumed_only_by_its_owner_without_new_order(): void
    {
        $payment = $this->payment();
        $payment->update(['snap_token' => 'private-snap-token']);
        $this->actingAs(User::factory()->create())->postJson(route('billing.resume', $payment))->assertForbidden();
        $this->actingAs($payment->user)->postJson(route('billing.resume', $payment))->assertOk()->assertJsonPath('token', 'private-snap-token');
        $this->getJson(route('billing.summary'))->assertOk()->assertJsonPath('payments.0.resumable', true)->assertDontSee('private-snap-token');
        $payment->update(['status' => 'paid']);
        $this->postJson(route('billing.resume', $payment))->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
    }

    private function payload(Payment $payment, string $status = 'settlement'): array
    {
        $amount = number_format($payment->amount, 2, '.', '');

        $payload = ['order_id' => $payment->order_id, 'status_code' => '200', 'gross_amount' => $amount, 'transaction_status' => $status, 'fraud_status' => 'accept', 'currency' => 'IDR', 'signature_key' => hash('sha512', $payment->order_id.'200'.$amount.'test-server')];
        $this->providerPayload = $payload;

        return $payload;
    }

    public function test_replayed_signature_with_altered_status_cannot_grant_subscription(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment, 'pending');
        $payload['transaction_status'] = 'settlement';
        $this->postJson(route('midtrans.notification'), $payload)->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->ends_at);
    }

    public function test_unavailable_status_api_requests_webhook_retry_without_granting_period(): void
    {
        $payment = $this->payment();
        $payload = $this->payload($payment);
        $this->providerHttpStatus = 503;
        $this->postJson(route('midtrans.notification'), $payload)->assertStatus(503);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_production_uses_live_endpoints_and_tracks_production_period(): void
    {
        config(['services.midtrans.production' => true]);
        Http::fake([
            'app.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'production-token'], 201),
            'api.midtrans.com/*' => fn () => Http::response($this->providerPayload),
        ]);
        $this->actingAs(User::factory()->create())->postJson(route('billing.pay'), ['plan' => 'annual'])
            ->assertOk()->assertJsonPath('token', 'production-token');
        $payment = Payment::first();
        $this->assertSame('production', $payment->environment);
        $this->providerPayload = $this->payload($payment);
        $this->postJson(route('billing.refresh', $payment))->assertOk()->assertJsonPath('status', 'paid');
        Http::assertSent(fn ($request) => $request->url() === 'https://app.midtrans.com/snap/v1/transactions');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.midtrans.com/v2/'.$payment->order_id.'/status');
        $this->assertNotNull($payment->fresh()->ends_at);
    }
}
