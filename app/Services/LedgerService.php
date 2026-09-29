<?php

namespace App\Services;

use App\Models\LedgerCategory;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Single entry point for money movements (section 7.2). Entries are append-only.
 */
class LedgerService
{
    public function post(array $data, ?Model $source = null): LedgerEntry
    {
        $entry = new LedgerEntry;
        $entry->forceFill([
            'layout_id' => $data['layout_id'] ?? null,
            'project_stage_id' => $data['project_stage_id'] ?? null,
            'ledger_category_id' => $data['ledger_category_id'] ?? $this->categoryId($data['category'] ?? null),
            'direction' => $data['direction'],
            'type' => $data['type'],
            'amount' => round((float) $data['amount'], 2),
            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
            'party' => $data['party'] ?? null,
            'mode' => $data['mode'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'description' => $data['description'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'source_type' => $source ? class_basename($source) : ($data['source_type'] ?? 'manual'),
            'source_id' => $source?->getKey(),
            'created_by' => auth()->id(),
        ])->save();

        return $entry;
    }

    public function reverse(LedgerEntry $original, string $reason): LedgerEntry
    {
        abort_if(LedgerEntry::query()->where('reversal_of', $original->id)->exists(), 422, 'Entry already reversed.');

        $entry = new LedgerEntry;
        $entry->forceFill([
            'layout_id' => $original->layout_id,
            'project_stage_id' => $original->project_stage_id,
            'ledger_category_id' => $original->ledger_category_id,
            'direction' => $original->direction === 'in' ? 'out' : 'in',
            'type' => $original->type,
            'amount' => $original->amount,
            'entry_date' => now()->toDateString(),
            'party' => $original->party,
            'description' => 'Reversal: '.$reason,
            'source_type' => 'reversal',
            'reversal_of' => $original->id,
            'created_by' => auth()->id(),
        ])->save();

        return $entry;
    }

    private function categoryId(?string $name): ?int
    {
        return $name ? LedgerCategory::query()->where('name', $name)->value('id') : null;
    }
}
