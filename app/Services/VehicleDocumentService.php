<?php

namespace App\Services;

use App\Models\Motorcycle;
use Illuminate\Support\Carbon;

class VehicleDocumentService
{
    private const DOCUMENTS = [
        'stnk_due_date' => 'Pajak STNK',
        'plat_due_date' => 'Ganti Plat (STNK 5 Tahun)',
        'insurance_due_date' => 'Asuransi',
    ];

    /**
     * ponytail: 30-day "soon" threshold mirrors AttentionService's
     * maintenance-item threshold — tuning knob, not a fixed law.
     */
    public function forMotorcycle(Motorcycle $motorcycle): array
    {
        $items = [];

        foreach (self::DOCUMENTS as $field => $label) {
            $dueDate = $motorcycle->{$field};
            if (!$dueDate) {
                continue;
            }

            $daysLeft = (int) Carbon::today()->diffInDays($dueDate, false);

            $items[] = [
                'label' => $label,
                'due_date' => $dueDate,
                'days_left' => $daysLeft,
                'color' => $daysLeft < 0 ? 'red' : ($daysLeft <= 30 ? 'yellow' : 'green'),
            ];
        }

        return $items;
    }
}
