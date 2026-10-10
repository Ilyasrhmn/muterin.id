<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function reminder(User $user): UserNotification
    {
        return UserNotification::create([
            'user_id' => $user->id, 'event_key' => 'test:'.$user->id,
            'severity' => 'red', 'message' => 'Waktunya servis', 'url' => route('bbm.index'),
        ]);
    }

    public function test_click_marks_notification_read_and_redirects_to_destination(): void
    {
        $user = User::factory()->create();
        $notification = $this->reminder($user);
        $this->actingAs($user)->post(route('notifications.read', $notification))->assertRedirect(route('bbm.index'));
        $readAt = $notification->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->travel(1)->hours();
        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertEquals($readAt, $notification->fresh()->read_at);
    }

    public function test_other_users_notification_cannot_be_read(): void
    {
        $notification = $this->reminder(User::factory()->create());
        $this->actingAs(User::factory()->create())->post(route('notifications.read', $notification))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_read_all_only_updates_current_users_active_notifications(): void
    {
        $user = User::factory()->create();
        $own = $this->reminder($user);
        $other = $this->reminder(User::factory()->create());
        $this->actingAs($user)->from(route('dashboard'))->post(route('notifications.read-all'))->assertRedirect(route('dashboard'));
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    public function test_refresh_preserves_read_state_and_service_cycle_creates_new_reminder(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'Beat', 'initial_odometer_km' => 0, 'current_odometer_km' => 3000]);
        $center = app(NotificationCenterService::class);
        $notification = $center->forUser($user)->firstWhere('severity', 'red');
        $this->assertNotNull($notification);
        $notification->update(['read_at' => now()]);
        $center->forUser($user);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertEquals(1, UserNotification::where('user_id', $user->id)->where('event_key', $notification->event_key)->count());

        $motor->maintenanceItems()->update(['last_service_odometer_km' => 3000]);
        $center->forUser($user);
        $this->assertFalse($notification->fresh()->active);
        $motor->update(['current_odometer_km' => 6000]);
        $new = $center->forUser($user)->firstWhere('severity', 'red');
        $this->assertNotNull($new);
        $this->assertNotEquals($notification->id, $new->id);
        $this->assertNull($new->read_at);
    }

    public function test_bell_is_available_outside_dashboard_without_marking_read(): void
    {
        $user = User::factory()->create();
        Motorcycle::create(['user_id' => $user->id, 'nickname' => 'Beat', 'initial_odometer_km' => 0, 'current_odometer_km' => 3000]);
        $this->actingAs($user)->get(route('bbm.index'))->assertOk()->assertSee('notification-panel', false)->assertSee('Belum dibaca');
        $this->assertTrue(UserNotification::where('user_id', $user->id)->whereNull('read_at')->exists());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Pusat Perhatian');
    }
}
