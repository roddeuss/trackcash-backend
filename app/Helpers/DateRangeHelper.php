<?php
// app/Helpers/DateRangeHelper.php

namespace App\Helpers;

use Carbon\Carbon;

class DateRangeHelper
{
    /**
     * Ambil start & end date berdasarkan range (day, week, month, year).
     */
    public static function getDateRange(string $range): array
    {
        $now = Carbon::now();

        switch ($range) {
            case 'day':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    /**
     * Ambil start, end, step, dan format label untuk grafik.
     */
    public static function getPeriodSetup(string $range): array
    {
        [$start, $end] = self::getDateRange($range);

        switch ($range) {
            case 'day':
                return [$start, $end, '1 day', 'd-m-Y H:i:s']; // kalau mau jam bisa '1 hour'
            case 'week':
                return [$start, $end, '1 day', 'd-m-Y'];
            case 'month':
                return [$start, $end, '1 day', 'd-m-Y'];
            case 'year':
                return [$start, $end, '1 month', 'm-Y'];
            default:
                return [$start, $end, '1 day', 'd-m-Y'];
        }
    }
}
