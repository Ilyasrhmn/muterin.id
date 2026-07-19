<?php

namespace Tests\Unit;

use App\Models\Motorcycle;
use App\Models\User;
use App\Services\VehicleDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleDocumentService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new VehicleDocumentService();
    }

    public function test_only_filled_documents_are_returned(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'stnk_due_date' => now()->addDays(10),
        ]);

        $items = $this->svc->forMotorcycle($motor);

        $this->assertCount(1, $items);
        $this->assertEquals('Pajak STNK', $items[0]['label']);
    }

    public function test_color_bands(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create([
            'user_id' => $user->id, 'nickname' => 'A',
            'stnk_due_date' => now()->subDays(3),       // overdue -> red
            'plat_due_date' => now()->addDays(15),       // soon -> yellow
            'insurance_due_date' => now()->addDays(90),  // far -> green
        ]);

        $items = collect($this->svc->forMotorcycle($motor))->keyBy('label');

        $this->assertEquals('red', $items['Pajak STNK']['color']);
        $this->assertEquals('yellow', $items['Ganti Plat (STNK 5 Tahun)']['color']);
        $this->assertEquals('green', $items['Asuransi']['color']);
    }

    public function test_no_documents_returns_empty_array(): void
    {
        $user = User::factory()->create();
        $motor = Motorcycle::create(['user_id' => $user->id, 'nickname' => 'A']);

        $this->assertEmpty($this->svc->forMotorcycle($motor));
    }
}
