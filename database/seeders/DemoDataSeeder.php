<?php

namespace Database\Seeders;

use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Demo account with two motorcycles, realistic service history, and a
     * few recorded trips — for showing the app with data instead of empty
     * states.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@amicta.test'],
            ['name' => 'Ilyas', 'password' => bcrypt('password123')]
        );

        // Wipe any previous demo motorcycles so the seeder is re-runnable.
        $user->motorcycles()->delete();

        $this->seedMotor1($user);
        $this->seedMotor2($user);
    }

    private function seedMotor1(User $user): void
    {
        $motor = Motorcycle::create([
            'user_id' => $user->id,
            'nickname' => 'Beat Ilyas',
            'plat_nomor' => 'B 3421 XYZ',
            'brand' => 'Honda',
            'model' => 'BeAT Street',
            'year' => 2022,
            'initial_odometer_km' => 500,
            'current_odometer_km' => 12450,
            'is_active' => true,
        ]);

        $this->serviceItem($motor, 'Oli Mesin', [
            ['2026-04-15', 7300, 45000, 'Oli mesin + filter oli'],
            ['2026-06-02', 9800, 50000, 'Ganti oli rutin'],
        ]);
        $this->serviceItem($motor, 'Ban', [
            ['2026-03-01', 2000, 350000, 'Ganti ban depan & belakang'],
        ]);
        $this->serviceItem($motor, 'Aki', [
            ['2026-02-20', 500, 180000, 'Ganti aki baru (GS Astra)'],
        ]);
        $this->serviceItem($motor, 'Servis Rutin', [
            ['2026-01-10', 5200, 120000, 'Servis CVT & rem'],
            ['2026-05-20', 9000, 150000, 'Servis rutin + tune up'],
        ]);

        $this->trip($motor, '2026-07-10 07:30:00', 8.4, 1320, [[-6.200, 106.800], [-6.215, 106.812], [-6.223, 106.821]]);
        $this->trip($motor, '2026-07-14 08:00:00', 12.1, 1680, [[-6.223, 106.821], [-6.234, 106.833], [-6.241, 106.845]]);
        $this->trip($motor, '2026-07-17 17:15:00', 6.7, 960, [[-6.241, 106.845], [-6.232, 106.836], [-6.223, 106.821]]);
    }

    private function seedMotor2(User $user): void
    {
        $motor = Motorcycle::create([
            'user_id' => $user->id,
            'nickname' => 'NMAX Kantor',
            'plat_nomor' => 'B 5678 ABC',
            'brand' => 'Yamaha',
            'model' => 'NMAX 155',
            'year' => 2023,
            'initial_odometer_km' => 0,
            'current_odometer_km' => 6200,
            'is_active' => false,
        ]);

        $this->serviceItem($motor, 'Oli Mesin', [
            ['2026-06-10', 5800, 55000, 'Oli mesin rutin'],
        ]);
        $this->serviceItem($motor, 'Ban', [
            ['2026-01-05', 100, 400000, 'Ban baru bawaan servis besar'],
        ]);
        // Aki belum pernah diservis — tetap dari checkpoint awal (0 km).
        $this->serviceItem($motor, 'Servis Rutin', [
            ['2026-04-01', 3600, 140000, 'Servis rutin CVT'],
        ]);

        $this->trip($motor, '2026-07-12 09:00:00', 5.2, 780, [[-6.175, 106.827], [-6.182, 106.835]]);
        $this->trip($motor, '2026-07-16 09:10:00', 5.0, 750, [[-6.182, 106.835], [-6.175, 106.827]]);
    }

    /**
     * Create maintenance log entries for one item, in order, then move the
     * item's checkpoint to the last entry — exactly what
     * MaintenanceController::complete() does for a real "tandai selesai".
     */
    private function serviceItem(Motorcycle $motor, string $itemName, array $logs): void
    {
        $item = $motor->maintenanceItems()->where('name', $itemName)->firstOrFail();

        foreach ($logs as [$date, $km, $cost, $note]) {
            $item->logs()->create([
                'serviced_at_odometer_km' => $km,
                'cost' => $cost,
                'serviced_at' => $date,
                'note' => $note,
            ]);
        }

        $lastKm = end($logs)[1];
        $item->update(['last_service_odometer_km' => $lastKm]);
    }

    private function trip(Motorcycle $motor, string $endedAt, float $distanceKm, int $durationSeconds, array $path): void
    {
        $motor->trips()->create([
            'distance_km' => $distanceKm,
            'duration_seconds' => $durationSeconds,
            'path_json' => $path,
            'started_at' => Carbon::parse($endedAt)->subSeconds($durationSeconds),
            'ended_at' => $endedAt,
        ]);
    }
}
