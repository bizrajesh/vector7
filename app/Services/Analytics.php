<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\Booking;
use App\Models\Layout;
use App\Models\LedgerEntry;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\SaleInstalment;
use App\Models\SharePool;
use Illuminate\Support\Carbon;

/**
 * DSS figures (section 8.1) computed live from ledger and plot tables.
 */
class Analytics
{
    public function adminDashboard(?int $layoutId = null, string $period = 'month'): array
    {
        [$from, $to] = $this->range($period);

        $sales = Sale::query()->whereIn('status', ['ongoing', 'paid', 'registered'])->whereBetween('sale_date', [$from, $to])
            ->when($layoutId, fn ($q) => $q->whereHas('plot', fn ($p) => $p->where('layout_id', $layoutId)));

        $due = SaleInstalment::query()->whereIn('status', ['due', 'partial'])->whereHas('sale', fn ($q) => $q->where('status', 'ongoing'));

        $statusCounts = Plot::query()->when($layoutId, fn ($q) => $q->where('layout_id', $layoutId))
            ->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status')->all();

        return [
            'sales_value' => (float) (clone $sales)->sum('sale_value'),
            'sales_count' => (clone $sales)->count(),
            'collections_due' => (float) (clone $due)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS d')->value('d'),
            'overdue_count' => (clone $due)->where('due_date', '<', now()->toDateString())->count(),
            'plots_total' => array_sum($statusCounts),
            'plots_sold' => (int) ($statusCounts[PlotStatus::Sold->value] ?? 0),
            'status_counts' => collect(PlotStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($statusCounts[$s->value] ?? 0)])->all(),
            'pool' => SharePool::query()->where('total_shares', '>', 0)->with('layout')->orderByDesc('updated_at')->first(),
            'layouts' => Layout::query()->withCount(['plots', 'plots as sold_count' => fn ($q) => $q->where('status', 'sold')])
                ->with(['stages:id,layout_id,status,is_mandatory,budget_cost', 'latestEstimate'])->latest()->limit(6)->get(),
            'due_this_week' => SaleInstalment::query()->whereIn('status', ['due', 'partial'])
                ->whereHas('sale', fn ($q) => $q->where('status', 'ongoing'))
                ->where('due_date', '<=', now()->addDays(7)->toDateString())
                ->with('sale.customer', 'sale.plot')->orderBy('due_date')->limit(8)->get(),
            'expiring_bookings' => Booking::query()->where('status', 'active')->where('expires_at', '<=', now()->addDays(5))
                ->with('plot', 'customer')->orderBy('expires_at')->limit(6)->get(),
            'cashflow' => $this->cashflow(6),
        ];
    }

    public function layoutActualCost(Layout $layout): float
    {
        return (float) LedgerEntry::query()->where('layout_id', $layout->id)->where('type', 'expense')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE -amount END), 0) AS t")->value('t');
    }

    /** Monthly in/out for the last N months. */
    public function cashflow(int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = LedgerEntry::query()->where('entry_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(entry_date, '%Y-%m') AS ym, direction, SUM(amount) AS total")
            ->groupBy('ym', 'direction')->get();

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $out[$key] = [
                'label' => Carbon::createFromFormat('Y-m', $key)->format('M'),
                'in' => (float) $rows->where('ym', $key)->where('direction', 'in')->sum('total'),
                'out' => (float) $rows->where('ym', $key)->where('direction', 'out')->sum('total'),
            ];
        }

        return array_values($out);
    }

    private function range(string $period): array
    {
        return match ($period) {
            'quarter' => [now()->startOfQuarter()->toDateString(), now()->endOfQuarter()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }
}
