<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Working-day calendar: Saturday and Sunday are non-working; each tenant can add holidays.
 */
class WorkingDays
{
    private array $holidays = [];

    public function __construct(private ?int $tenantId)
    {
        if ($tenantId) {
            $this->holidays = Holiday::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip()->all();
        }
    }

    public static function for(?int $tenantId): self
    {
        return new self($tenantId);
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! isset($this->holidays[$date->toDateString()]);
    }

    /** Add N working days. 0 → the same day (or the next working day if it is a holiday). */
    public function add(CarbonInterface $from, int $days): Carbon
    {
        $date = Carbon::parse($from)->startOfDay();
        if ($days === 0) {
            while (! $this->isWorkingDay($date)) {
                $date->addDay();
            }

            return $date;
        }
        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if ($this->isWorkingDay($date)) {
                $added++;
            }
        }

        return $date;
    }

    /** Working days strictly after $from up to and including $to. */
    public function between(CarbonInterface $from, CarbonInterface $to): int
    {
        $d = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $n = 0;
        while ($d->lt($end)) {
            $d->addDay();
            if ($this->isWorkingDay($d)) {
                $n++;
            }
        }

        return $n;
    }

    /** The previous working day before $date. */
    public function previous(CarbonInterface $date): Carbon
    {
        $d = Carbon::parse($date)->startOfDay()->subDay();
        while (! $this->isWorkingDay($d)) {
            $d->subDay();
        }

        return $d;
    }
}
