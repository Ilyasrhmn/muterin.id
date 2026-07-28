<?php

namespace Database\Seeders;

use App\Models\CommunityPin;
use App\Models\CommunityPinConfirmation;
use App\Models\CommunityPinFavorite;
use App\Models\Motorcycle;
use App\Models\PlaceList;
use App\Models\User;
use Illuminate\Database\Seeder;

class JuryDemoSeeder extends Seeder
{
    /**
     * Presentation-ready demo data: cleans out the ad-hoc test junk left on
     * the presenter's real account (ilyasnurrohman14@gmail.com) and adds a
     * handful of realistically-named community users so the Peta Komunitas
     * feature has more than one contributor when shown to the jury.
     *
     * Re-runnable: new demo users are matched by email (firstOrCreate), and
     * their old pins/motors are wiped before reseeding.
     */
    public function run(): void
    {
        $presenter = User::where('email', 'ilyasnurrohman14@gmail.com')->first();
        if ($presenter) {
            $this->cleanPresenterMotorcycles($presenter);
            $this->cleanPresenterPins($presenter);
        }

        $users = $this->seedCommunityUsers();
        $this->cleanCommunityUserPins($users);

        $allPinOwners = $presenter ? [$presenter, ...$users] : $users;
        $pins = $this->seedCommunityPins($allPinOwners);
        $this->seedEngagement($pins, $allPinOwners);

        if ($presenter) {
            $this->seedPresenterPins($presenter);
            $this->seedPresenterPlaces($presenter);
        }
    }

    // --- Presenter cleanup -------------------------------------------------

    private function cleanPresenterMotorcycles(User $presenter): void
    {
        // NMAX Kantor's 3rd fuel log has a nonsense odometer jump (5200 ->
        // 13000 on 3L, left over from earlier ad-hoc testing) -- replace it
        // with a believable continuation and bring current_odometer_km back
        // in line with it.
        $nmax = $presenter->motorcycles()->where('nickname', 'NMAX Kantor')->first();
        if ($nmax) {
            $nmax->fuelLogs()->where('odometer_km', 13000)->delete();
            $nmax->odometerReadings()->where('reading_km', 13000)->delete();

            $nmax->fuelLogs()->create([
                'filled_at' => '2026-07-20',
                'odometer_km' => 6100,
                'liters' => 4.1,
                'total_cost' => 64000,
                'is_full_tank' => true,
            ]);
            $nmax->odometerReadings()->create([
                'reading_km' => 6100,
                'recorded_at' => '2026-07-20',
                'source' => 'fuel',
            ]);
            $nmax->update(['current_odometer_km' => 6400]);
        }

        // Supra Bapak only had 1 full-tank fill (not enough to compute
        // km/l) and no maintenance/expense history -- flesh it out to the
        // same depth as the other two motorcycles.
        $supra = $presenter->motorcycles()->where('nickname', 'Supra Bapak')->first();
        if ($supra && $supra->fuelLogs()->count() < 2) {
            $supra->fuelLogs()->create([
                'filled_at' => '2026-07-24',
                'odometer_km' => 12010,
                'liters' => 1.6,
                'total_cost' => 16000,
                'is_full_tank' => false,
            ]);
            $supra->fuelLogs()->create([
                'filled_at' => '2026-08-01',
                'odometer_km' => 12130,
                'liters' => 4.8,
                'total_cost' => 48000,
                'is_full_tank' => true,
            ]);
            $supra->update(['current_odometer_km' => 12130]);

            $servisRutin = $supra->maintenanceItems()->where('name', 'Servis Rutin')->first();
            if ($servisRutin && $servisRutin->logs()->count() === 0) {
                $servisRutin->logs()->create([
                    'serviced_at_odometer_km' => 12000,
                    'cost' => 95000,
                    'serviced_at' => '2026-07-15',
                    'note' => 'Servis rutin + ganti oli',
                ]);
                $servisRutin->update(['last_service_odometer_km' => 12000]);
            }

            $supra->otherExpenses()->firstOrCreate(
                ['category' => 'cuci_motor', 'expense_date' => '2026-07-26'],
                ['amount' => 20000],
            );

            $supra->odometerReadings()->firstOrCreate(
                ['reading_km' => 12130, 'recorded_at' => '2026-08-01'],
                ['source' => 'fuel'],
            );
        }
    }

    private function cleanPresenterPins(User $presenter): void
    {
        $oldPinIds = $presenter->communityPins()->pluck('id');
        CommunityPinConfirmation::whereIn('community_pin_id', $oldPinIds)->delete();
        CommunityPinFavorite::whereIn('community_pin_id', $oldPinIds)->delete();
        $presenter->communityPins()->delete();
    }

    private function seedPresenterPins(User $presenter): void
    {
        CommunityPin::create([
            'user_id' => $presenter->id,
            'category' => 'momen',
            'lat' => -7.8146,
            'lng' => 110.3628,
            'title' => 'Sunset di Alun-Alun Kidul',
            'description' => 'Spot enak buat nongkrong sore, ramai pas weekend tapi worth it.',
            'time_context' => 'kapanpun',
            'is_anonymous' => false,
        ]);
        CommunityPin::create([
            'user_id' => $presenter->id,
            'category' => 'rusak',
            'lat' => -7.7925,
            'lng' => 110.3679,
            'title' => 'Jalan bergelombang dekat Jembatan Kewek',
            'description' => 'Ada beberapa lubang, hati-hati kalau malam soalnya lampu jalan minim.',
            'time_context' => 'malam',
            'is_anonymous' => false,
        ]);
    }

    /**
     * "Titik Saya" (saved places) is thin -- 2 spots with no description,
     * only the 3 default lists plus one the presenter made themselves. Adds
     * two more custom lists (different icon + color each, to show off
     * marker customization) and fills every list with realistic Yogyakarta
     * spots. Existing lists/places are untouched, only added to.
     */
    private function seedPresenterPlaces(User $presenter): void
    {
        PlaceList::ensureDefaultsFor($presenter);

        $kuliner = $presenter->placeLists()->firstOrCreate(
            ['name' => 'Kuliner Favorit'],
            ['icon' => 'fa-utensils', 'color' => '#DC2626', 'is_default' => false],
        );
        $nongkrong = $presenter->placeLists()->firstOrCreate(
            ['name' => 'Spot Nongkrong'],
            ['icon' => 'fa-mug-hot', 'color' => '#7C3AED', 'is_default' => false],
        );

        $byName = fn (string $name) => $presenter->placeLists()->where('name', $name)->first();

        $spec = [
            ['list' => 'Favorit', 'title' => 'Rumah Orang Tua di Kotagede', 'lat' => -7.8258, 'lng' => 110.3968, 'description' => 'Mampir tiap akhir pekan buat makan siang bareng.'],
            ['list' => 'Mau ke sana', 'title' => 'Candi Prambanan', 'lat' => -7.7520, 'lng' => 110.4915, 'description' => 'Belum pernah ke sini pas sunset, katanya bagus.'],
            ['list' => 'Mau ke sana', 'title' => 'Pantai Parangtritis', 'lat' => -8.0257, 'lng' => 110.3312, 'description' => 'Rencana motoran akhir bulan kalau cuaca cerah.'],
            ['list' => 'Mau ke sana', 'title' => 'Kaliurang', 'lat' => -7.5993, 'lng' => 110.4247, 'description' => 'Udara sejuk, cocok buat kabur dari panas kota.'],
            ['list' => 'Bengkel Langganan', 'title' => 'Bengkel Pak Slamet - Jl. Magelang Km 5', 'lat' => -7.7550, 'lng' => 110.3600, 'description' => 'Servis CVT paling teliti, harga bersahabat.'],
            ['list' => 'Bengkel Langganan', 'title' => 'AHASS Sudirman', 'lat' => -7.7828, 'lng' => 110.3672, 'description' => 'Servis resmi kalau lagi masa garansi.'],
            ['list' => 'Simpan', 'title' => 'Stasiun Tugu', 'lat' => -7.7930, 'lng' => 110.3630, 'description' => null],
            ['list' => 'Kuliner Favorit', 'title' => 'Gudeg Yu Djum Wijilan', 'lat' => -7.8030, 'lng' => 110.3690, 'description' => 'Gudeg kering favorit, buka dari pagi.'],
            ['list' => 'Kuliner Favorit', 'title' => 'Angkringan Lik Man Tugu', 'lat' => -7.7830, 'lng' => 110.3690, 'description' => 'Kopi joss + sate usus, buka malam.'],
            ['list' => 'Kuliner Favorit', 'title' => 'Bakmi Kadin', 'lat' => -7.7970, 'lng' => 110.3720, 'description' => null],
            ['list' => 'Spot Nongkrong', 'title' => 'Kopi Klotok Kaliurang', 'lat' => -7.6100, 'lng' => 110.4250, 'description' => 'Rame banget kalau weekend, dateng pagi biar gak antre.'],
            ['list' => 'Spot Nongkrong', 'title' => 'Legend Coffee Colombo', 'lat' => -7.7690, 'lng' => 110.3850, 'description' => 'Deket kampus, enak buat kerja santai.'],
        ];

        foreach ($spec as $s) {
            $list = $byName($s['list']);
            if (! $list) {
                continue;
            }
            $list->places()->firstOrCreate(
                ['title' => $s['title']],
                ['user_id' => $presenter->id, 'lat' => $s['lat'], 'lng' => $s['lng'], 'description' => $s['description']],
            );
        }
    }

    // --- New community users ------------------------------------------------

    /** @return list<User> */
    private function seedCommunityUsers(): array
    {
        $roster = [
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@warga-jogja.demo', 'nickname' => 'Vario Budi', 'plat' => 'AB 4521 XY', 'brand' => 'Honda', 'model' => 'Vario 125', 'year' => 2021],
            ['name' => 'Siti Nur Aini', 'email' => 'siti.nuraini@warga-jogja.demo', 'nickname' => 'Mio Siti', 'plat' => 'AB 2290 ZL', 'brand' => 'Yamaha', 'model' => 'Mio M3', 'year' => 2020],
            ['name' => 'Agus Prasetyo', 'email' => 'agus.prasetyo@warga-jogja.demo', 'nickname' => 'Supra Agus', 'plat' => 'AB 1187 QW', 'brand' => 'Honda', 'model' => 'Supra X125', 'year' => 2019],
            ['name' => 'Dewi Kusuma Wardani', 'email' => 'dewi.kusuma@warga-jogja.demo', 'nickname' => 'Fino Dewi', 'plat' => 'AB 5643 RT', 'brand' => 'Yamaha', 'model' => 'Fino 125', 'year' => 2022],
            ['name' => 'Rizky Ramadhan', 'email' => 'rizky.ramadhan@warga-jogja.demo', 'nickname' => 'PCX Rizky', 'plat' => 'AB 3390 KM', 'brand' => 'Honda', 'model' => 'PCX 160', 'year' => 2023],
            ['name' => 'Fitria Anggraeni', 'email' => 'fitria.anggraeni@warga-jogja.demo', 'nickname' => 'Aerox Fitria', 'plat' => 'AB 7712 UV', 'brand' => 'Yamaha', 'model' => 'Aerox 155', 'year' => 2022],
        ];

        $users = [];
        foreach ($roster as $r) {
            $user = User::firstOrCreate(
                ['email' => $r['email']],
                ['name' => $r['name'], 'password' => bcrypt('password123'), 'email_verified_at' => now()],
            );

            $user->motorcycles()->delete();
            $motor = Motorcycle::create([
                'user_id' => $user->id,
                'nickname' => $r['nickname'],
                'plat_nomor' => $r['plat'],
                'brand' => $r['brand'],
                'model' => $r['model'],
                'year' => $r['year'],
                'initial_odometer_km' => 1000,
                'current_odometer_km' => 7400,
                'is_active' => true,
            ]);
            $this->seedMotorcycleData($motor);

            $users[] = $user;
        }

        return $users;
    }

    private function seedMotorcycleData(Motorcycle $motor): void
    {
        $motor->fuelLogs()->createMany([
            ['filled_at' => '2026-06-01', 'odometer_km' => 4200, 'liters' => 3.9, 'total_cost' => 61000, 'is_full_tank' => true],
            ['filled_at' => '2026-06-25', 'odometer_km' => 5100, 'liters' => 4.1, 'total_cost' => 64000, 'is_full_tank' => true],
            ['filled_at' => '2026-07-18', 'odometer_km' => 6300, 'liters' => 4.3, 'total_cost' => 67000, 'is_full_tank' => true],
        ]);

        $oli = $motor->maintenanceItems()->where('name', 'Oli Mesin')->first();
        $oli?->logs()->create(['serviced_at_odometer_km' => 5100, 'cost' => 50000, 'serviced_at' => '2026-06-25', 'note' => 'Ganti oli rutin']);
        $oli?->update(['last_service_odometer_km' => 5100]);

        $servis = $motor->maintenanceItems()->where('name', 'Servis Rutin')->first();
        $servis?->logs()->create(['serviced_at_odometer_km' => 4200, 'cost' => 130000, 'serviced_at' => '2026-06-01', 'note' => 'Servis rutin CVT & rem']);
        $servis?->update(['last_service_odometer_km' => 4200]);

        $motor->otherExpenses()->create([
            'category' => 'cuci_motor',
            'amount' => 20000,
            'expense_date' => '2026-07-19',
        ]);
    }

    // --- Community pins -----------------------------------------------------

    /**
     * Wipe the 6 demo users' pins from a previous run before reseeding --
     * without this, re-running the seeder duplicates every community pin
     * (confirmations/favorites cascade-delete with the pin, per the FKs on
     * both tables, so this alone is enough to fully reset engagement too).
     *
     * @param  list<User>  $users
     */
    private function cleanCommunityUserPins(array $users): void
    {
        CommunityPin::whereIn('user_id', collect($users)->pluck('id'))->delete();
    }

    /**
     * @param  list<User>  $owners  index 0 = presenter (skipped here, seeded separately), 1.. = new users
     * @return list<CommunityPin>
     */
    private function seedCommunityPins(array $owners): array
    {
        $byEmail = collect($owners)->keyBy('email');
        $u = fn(string $email) => $byEmail->get($email);

        $spec = [
            ['email' => 'budi.santoso@warga-jogja.demo', 'category' => 'sepi', 'lat' => -7.9021, 'lng' => 110.5872, 'title' => 'Jalan Wonosari km 12 sepi banget malam', 'description' => 'Jarang ada motor lewat setelah jam 9 malam, sinyal HP juga kadang ilang.', 'time_context' => 'malam', 'anon' => false],
            ['email' => 'budi.santoso@warga-jogja.demo', 'category' => 'rawan', 'lat' => -7.8378, 'lng' => 110.4692, 'title' => 'Rawan begal deket SPBU Piyungan', 'description' => 'Beberapa temen kena todong pas motoran malam lewat sini, lebih baik rame-rame.', 'time_context' => 'malam', 'anon' => true],
            ['email' => 'siti.nuraini@warga-jogja.demo', 'category' => 'momen', 'lat' => -7.8858, 'lng' => 110.5350, 'title' => 'Sunrise di Bukit Bintang Patuk', 'description' => 'View kota Jogja dari atas keren banget pas subuh, worth it bangun pagi.', 'time_context' => 'siang', 'anon' => false],
            ['email' => 'siti.nuraini@warga-jogja.demo', 'category' => 'banjir', 'lat' => -7.8266, 'lng' => 110.3521, 'title' => 'Genangan tinggi di Ring Road Selatan pas hujan deras', 'description' => 'Air bisa sampai setengah ban kalau hujan lebih dari 1 jam.', 'time_context' => 'kapanpun', 'anon' => false],
            ['email' => 'agus.prasetyo@warga-jogja.demo', 'category' => 'gelap', 'lat' => -7.8590, 'lng' => 110.3968, 'title' => 'Jalan Imogiri Timur minim penerangan', 'description' => 'Lampu jalan banyak yang mati, bawa senter tambahan kalau lewat malam.', 'time_context' => 'malam', 'anon' => false],
            ['email' => 'agus.prasetyo@warga-jogja.demo', 'category' => 'rusak', 'lat' => -7.8489, 'lng' => 110.3958, 'title' => 'Aspal ambles dekat Pasar Kotagede', 'description' => 'Ada bagian jalan turun mendadak, sering bikin kaget pengendara.', 'time_context' => 'kapanpun', 'anon' => false],
            ['email' => 'dewi.kusuma@warga-jogja.demo', 'category' => 'momen', 'lat' => -7.7828, 'lng' => 110.3672, 'title' => 'Ngopi santai di kawasan Tugu Jogja', 'description' => 'Banyak spot kopi kekinian buat istirahat sebentar habis muter-muter kota.', 'time_context' => 'siang', 'anon' => false],
            ['email' => 'dewi.kusuma@warga-jogja.demo', 'category' => 'sepi', 'lat' => -7.7683, 'lng' => 110.3766, 'title' => 'Jalan kampus UGM belakang pas libur semester', 'description' => 'Sepi banget kalau lagi liburan, enak buat latihan motoran pelan.', 'time_context' => 'kapanpun', 'anon' => false],
            ['email' => 'rizky.ramadhan@warga-jogja.demo', 'category' => 'rawan', 'lat' => -7.7735, 'lng' => 110.4188, 'title' => 'Hati-hati preman di simpang Janti', 'description' => 'Sering ada yang minta uang parkir liar, mending lewat jalur alternatif kalau malam.', 'time_context' => 'malam', 'anon' => true],
            ['email' => 'rizky.ramadhan@warga-jogja.demo', 'category' => 'gelap', 'lat' => -7.6321, 'lng' => 110.4187, 'title' => 'Jalan Kaliurang km 17 gelap total', 'description' => 'Nyaris gak ada lampu jalan, kondisi jalan juga menyempit.', 'time_context' => 'malam', 'anon' => false],
            ['email' => 'fitria.anggraeni@warga-jogja.demo', 'category' => 'banjir', 'lat' => -7.7469, 'lng' => 110.3892, 'title' => 'Underpass Kentungan langganan banjir', 'description' => 'Kalau hujan lebat, air cepat naik dan macet parah.', 'time_context' => 'kapanpun', 'anon' => false],
            ['email' => 'fitria.anggraeni@warga-jogja.demo', 'category' => 'momen', 'lat' => -7.6142, 'lng' => 110.4425, 'title' => 'Spot foto sawah di Pentingsari Cangkringan', 'description' => 'Udara sejuk, cocok buat riding santai pagi hari.', 'time_context' => 'siang', 'anon' => false],
        ];

        return collect($spec)->map(function ($s) use ($u) {
            return CommunityPin::create([
                'user_id' => $u($s['email'])->id,
                'category' => $s['category'],
                'lat' => $s['lat'],
                'lng' => $s['lng'],
                'title' => $s['title'],
                'description' => $s['description'],
                'time_context' => $s['time_context'],
                'is_anonymous' => $s['anon'],
            ]);
        })->all();
    }

    /**
     * Cross-link confirmations/favorites so counts look organic instead of
     * all-zero -- deterministic pattern based on pin index, not random, so
     * the seeder is reproducible.
     *
     * @param  list<CommunityPin>  $pins
     * @param  list<User>  $owners
     */
    private function seedEngagement(array $pins, array $owners): void
    {
        $voterCount = count($owners);

        foreach ($pins as $i => $pin) {
            for ($offset = 1; $offset <= 3; $offset++) {
                $voter = $owners[($i + $offset) % $voterCount];
                if ($voter->id === $pin->user_id) {
                    continue;
                }
                // Every 4th confirmation disputes the pin, rest confirm it --
                // keeps most pins net-positive while a couple show real
                // "sudah tidak berlaku" pushback.
                $stillThere = ($i + $offset) % 4 !== 0;
                CommunityPinConfirmation::create([
                    'community_pin_id' => $pin->id,
                    'user_id' => $voter->id,
                    'still_there' => $stillThere,
                ]);
            }

            if ($i % 2 === 0) {
                $favoriter = $owners[($i + 2) % $voterCount];
                if ($favoriter->id !== $pin->user_id) {
                    CommunityPinFavorite::create(['community_pin_id' => $pin->id, 'user_id' => $favoriter->id]);
                }
            }

            $still = $pin->confirmations()->where('still_there', true)->count();
            $gone = $pin->confirmations()->where('still_there', false)->count();
            $pin->update(['confirm_count' => $still - $gone, 'still_count' => $still, 'gone_count' => $gone]);
        }
    }
}
