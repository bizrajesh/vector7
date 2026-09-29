<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\ChecklistTemplate;
use App\Models\Plot;
use App\Models\Registration;
use App\Models\RegistrationChecklistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration (section 6.5): ROR → Ongoing-Reg → Sold, with checklist and Ack.
 */
class RegistrationService
{
    public function __construct(
        private readonly PlotStatusMachine $machine,
        private readonly Notifier $notifier,
        private readonly Settings $settings,
        private readonly ShareService $shares,
    ) {}

    public function initiate(Plot $plot, array $data): Registration
    {
        return DB::transaction(function () use ($plot, $data) {
            $plot = Plot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            abort_unless($plot->status === PlotStatus::Ror, 422, 'Only ROR plots can start registration.');

            $sale = $plot->activeSale;
            abort_unless($sale && $sale->status === 'paid' && $sale->balance() <= 0, 422, 'The sale must be fully paid.');

            $registration = new Registration($data);
            $registration->plot_id = $plot->id;
            $registration->sale_id = $sale->id;
            $registration->status = 'ongoing';
            $registration->created_by = auth()->id();
            $registration->save();

            $template = ChecklistTemplate::query()->where('purpose', 'registration')->with('items')->first();
            foreach ($template?->items ?? [] as $item) {
                $row = new RegistrationChecklistItem($item->only(['label', 'is_required', 'needs_upload', 'needs_date', 'sort_order']));
                $row->registration_id = $registration->id;
                $row->save();
            }

            $this->machine->transition($plot, PlotStatus::OngoingReg, 'Registration initiated');

            return $registration;
        });
    }

    public function updateItem(RegistrationChecklistItem $item, array $data, ?string $filePath): void
    {
        $item->fill(array_intersect_key($data, array_flip(['value', 'date_value'])));
        if ($filePath) {
            $item->file_path = $filePath;
        }
        $done = (bool) ($data['is_done'] ?? false);
        if ($done && $item->needs_upload && ! $item->file_path) {
            throw ValidationException::withMessages(['file' => "Upload a file for “{$item->label}” before marking it done."]);
        }
        $item->is_done = $done;
        $item->verified_by = $done ? auth()->id() : null;
        $item->verified_at = $done ? now() : null;
        $item->save();
    }

    public function complete(Registration $registration, array $data, ?string $deedPath): void
    {
        abort_unless($registration->status === 'ongoing', 422, 'Registration already completed.');

        $pending = $registration->items()->where('is_required', true)->where('is_done', false)->pluck('label');
        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages(['checklist' => 'Complete required items: '.$pending->implode(', ')]);
        }

        DB::transaction(function () use ($registration, $data, $deedPath) {
            $sale = $registration->sale()->lockForUpdate()->first();
            abort_unless($sale->balance() <= 0, 422, 'Dues are pending on this sale.');

            $registration->registration_date = $data['registration_date'];
            $registration->document_no = $data['document_no'];
            $registration->sub_registrar_office_id = $data['sub_registrar_office_id'] ?? $registration->sub_registrar_office_id;
            $registration->deed_path = $deedPath ?? $registration->deed_path;
            $registration->status = 'completed';
            $registration->completed_at = now();
            $registration->save();

            $sale->status = 'registered';
            $sale->save();

            $plot = Plot::query()->whereKey($registration->plot_id)->lockForUpdate()->firstOrFail();
            $this->machine->transition($plot, PlotStatus::Sold, 'Registered, document '.$registration->document_no);

            if ($this->settings->get('share_recognition', 'sold') === 'sold') {
                $this->shares->recogniseSale($sale);
            }

            $this->notifier->event('registration.completed', "Plot {$plot->plot_no} registered (doc {$registration->document_no}).", $plot->layout);
            $this->notifier->customer($sale->customer, 'registration.completed', "Congratulations! Registration of plot {$plot->plot_no} is complete.");
        });
    }
}
