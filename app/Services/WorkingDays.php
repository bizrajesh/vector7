<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working-day arithmetic: Sundays and tenant holidays are skipped.
 */
class WorkingDays
{
    public function add(CarbonInterface $from, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::parse($from)->startOfDay();
        if ($days === 0) {
            return $date;
        }

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$date, $date->addDays($days * 2 + 14)])
            ->pluck('holiday_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $added = 0;
        while ($added < $days) {
            $date = $date->addDay();
            if ($date->isSunday() || in_array($date->toDateString(), $holidays, true)) {
                continue;
            }
            $added++;
        }

        return $date;
    }
}
