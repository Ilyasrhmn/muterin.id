<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDialogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.production' => false]);
    }

    public function test_old_pages_open_dialog_in_existing_application_instead_of_separate_pages(): void
    {
        $this->get(route('subscription'))->assertRedirect(route('home', ['upgrade' => 1]));
        $this->actingAs(User::factory()->create());
        $this->get(route('billing.checkout', ['plan' => 'annual']))->assertRedirect(route('dashboard', ['upgrade' => 1, 'plan' => 'annual']));
        $this->get(route('billing.index'))->assertRedirect(route('dashboard', ['upgrade' => 1, 'billing' => 1]));
        $this->get(route('dashboard'))->assertOk()->assertSee('subscription-dialog-title')->assertSee('Upgrade paket')->assertDontSee('>Langganan</a>', false);
    }

    public function test_billing_summary_requires_auth_and_never_exposes_other_owners_or_tokens(): void
    {
        $this->getJson(route('billing.summary'))->assertUnauthorized();
        $owner = User::factory()->create();
        Payment::create(['user_id' => $owner->id, 'order_id' => 'OWNER-ORDER', 'plan' => 'monthly', 'amount' => 19000, 'environment' => 'sandbox', 'status' => 'pending', 'snap_token' => 'secret-snap-token']);
        $this->actingAs(User::factory()->create())->getJson(route('billing.summary'))->assertOk()->assertJsonCount(0, 'payments');
        $this->actingAs($owner)->getJson(route('billing.summary'))->assertOk()->assertJsonPath('payments.0.order_id', 'OWNER-ORDER')->assertJsonPath('payments.0.resumable', true)->assertDontSee('secret-snap-token');
        config(['services.midtrans.production' => true]);
        $this->getJson(route('billing.summary'))->assertOk()->assertJsonCount(0, 'payments');
    }

    public function test_summary_paginates_and_excludes_future_periods_from_active_subscription(): void
    {
        $owner = User::factory()->create();
        for ($i = 0; $i < 11; $i++) {
            Payment::create(['user_id' => $owner->id, 'order_id' => 'PENDING-'.$i, 'plan' => 'monthly', 'amount' => 19000, 'environment' => 'sandbox', 'status' => 'pending']);
        }
        Payment::create(['user_id' => $owner->id, 'order_id' => 'FUTURE', 'plan' => 'annual', 'amount' => 190000, 'environment' => 'sandbox', 'status' => 'paid', 'paid_at' => now(), 'starts_at' => now()->addMonth(), 'ends_at' => now()->addYear()]);
        $this->actingAs($owner)->getJson(route('billing.summary'))->assertOk()->assertJsonCount(10, 'payments')->assertJsonPath('pagination.last', 2)->assertJsonPath('active', null);
        $this->getJson(route('billing.summary', ['page' => 2]))->assertOk()->assertJsonCount(2, 'payments');
    }
}
