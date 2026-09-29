<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Layout;
use App\Models\LedgerCategory;
use App\Models\LedgerEntry;
use App\Services\FileVault;
use App\Services\LedgerService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Accounting (section 7.2). Sales users see sales income only. */
class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        return view('app.ledger.index', [
            'entries' => (clone $query)->with(['layout', 'category', 'stage'])->latest('entry_date')->latest('id')->paginate(40)->withQueryString(),
            'totals' => [
                'in' => (float) (clone $query)->where('direction', 'in')->sum('amount'),
                'out' => (float) (clone $query)->where('direction', 'out')->sum('amount'),
            ],
            'layouts' => Layout::query()->orderBy('name')->get(['id', 'name']),
            'categories' => LedgerCategory::query()->orderBy('name')->get(),
            'salesOnly' => ! $request->user()->hasPermission('ledger.view'),
        ]);
    }

    public function store(Request $request, LedgerService $ledger, FileVault $vault): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'layout_id' => ['nullable', Rule::exists('layouts', 'id')->where('tenant_id', $tenantId)],
            'ledger_category_id' => ['required', Rule::exists('ledger_categories', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(['investment', 'expense', 'sales_income', 'commission', 'refund', 'distribution', 'other'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'party' => ['nullable', 'string', 'max:150'],
            'mode' => ['nullable', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:'.implode(',', config('vector7.uploads.mimes'))],
        ]);

        $data['direction'] = LedgerCategory::query()->findOrFail($data['ledger_category_id'])->direction;
        $data['attachment_path'] = $request->hasFile('attachment') ? $vault->store($request->file('attachment'), 'ledger') : null;
        $ledger->post($data);

        return $this->done('Entry posted.');
    }

    public function reverse(Request $request, LedgerEntry $entry, LedgerService $ledger): RedirectResponse
    {
        abort_if($entry->reversal_of !== null, 422, 'A reversal cannot be reversed.');
        abort_unless($entry->source_type === 'manual', 422, 'System entries are corrected from their source (payment, share allocation).');
        $ledger->reverse($entry, $request->validate(['reason' => ['required', 'string', 'max:200']])['reason']);

        return $this->done('Reversal posted.');
    }

    /** CSV export; cells starting with = + - @ are neutralised to prevent spreadsheet formula injection. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with(['layout', 'category'])->orderBy('entry_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Date', 'Project', 'Category', 'Type', 'Direction', 'Amount', 'Party', 'Mode', 'Reference', 'Description']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, array_map([self::class, 'safeCell'], [
                        $e->entry_date->toDateString(), $e->layout?->name, $e->category?->name, $e->type, $e->direction,
                        $e->amount, $e->party, $e->mode, $e->reference_no, $e->description,
                    ]));
                }
            });
            fclose($out);
        }, 'vector7-ledger-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'layout_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(['investment', 'expense', 'sales_income', 'commission', 'refund', 'distribution', 'other'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = LedgerEntry::query()
            ->when($request->layout_id, fn ($q, $id) => $q->where('layout_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->from, fn ($q, $d) => $q->where('entry_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('entry_date', '<=', $d));

        // Sales role: sales income only (permission matrix, section 3).
        if (! $request->user()->hasPermission('ledger.view')) {
            abort_unless($request->user()->hasPermission('ledger.view.sales'), 403);
            $query->where('type', 'sales_income');
        }

        return $query;
    }
}
