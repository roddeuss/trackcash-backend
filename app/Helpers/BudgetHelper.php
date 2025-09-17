<?php

namespace App\Helpers;

use App\Models\Budget;
use Carbon\Carbon;

class BudgetHelper
{
    /**
     * Tentukan jendela waktu budget (start/end) berdasarkan period.
     * - monthly/weekly/yearly: gunakan periode berjalan (current period)
     * - custom: pakai start_date & end_date di record
     */
    public static function getBudgetWindow(Budget $b): array
    {
        $now = Carbon::now();

        switch ($b->period) {
            case 'weekly':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];

            case 'yearly':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

            case 'custom':
                $start = $b->start_date ? Carbon::parse($b->start_date)->startOfDay() : $now->copy()->startOfDay();
                $end   = $b->end_date   ? Carbon::parse($b->end_date)->endOfDay()   : $now->copy()->endOfDay();
                return [$start, $end];

            case 'monthly':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }
}
