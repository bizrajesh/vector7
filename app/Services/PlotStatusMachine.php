<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\Plot;
use App\Models\PlotStatusHistory;
use Illuminate\Validation\ValidationException;

/**
 * The only code path allowed to change a plot's status (OWASP A04 insecure design:
 * business rules enforced server-side). Callers must hold a row lock (lockForUpdate)
 * inside a DB transaction.
 */
class PlotStatusMachine
{
    public function transition(Plot $plot, PlotStatus $to, string $reason): void
    {
        $from = $plot->status;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Plot {$plot->plot_no} cannot move from {$from->label()} to {$to->label()}.",
            ]);
        }

        $plot->status = $to;
        $plot->save();

        PlotStatusHistory::create([
            'plot_id' => $plot->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => mb_substr($reason, 0, 255),
            'user_id' => auth()->id(),
        ]);
    }
}
