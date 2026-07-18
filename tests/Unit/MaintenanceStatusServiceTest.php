<?php

namespace Tests\Unit;

use App\Services\MaintenanceStatusService;
use PHPUnit\Framework\TestCase;

class MaintenanceStatusServiceTest extends TestCase
{
    private MaintenanceStatusService $svc;

    protected function setUp(): void
    {
        $this->svc = new MaintenanceStatusService();
    }

    public function test_percent_calculation(): void
    {
        $this->assertEquals(50.0, $this->svc->percent(1250, 2500));
    }

    public function test_color_boundaries(): void
    {
        $this->assertEquals('green', $this->svc->color(79.9));
        $this->assertEquals('yellow', $this->svc->color(80.0));
        $this->assertEquals('yellow', $this->svc->color(100.0));
        $this->assertEquals('red', $this->svc->color(100.1));
    }

    public function test_zero_interval_is_safe_not_divide_by_zero(): void
    {
        $this->assertEquals(0.0, $this->svc->percent(500, 0));
    }
}
