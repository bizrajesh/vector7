<?php

namespace App\Services;

use App\Models\Layout;
use App\Models\LedgerEntry;
use App\Models\Sale;
use App\Models\Shareholder;
use App\Models\ShareAllocation;
use App\Models\ShareHolding;
use App\Models\ShareIssuance;
use App\Models\SharePayout;
use App\Models\SharePool;
use App\Models\ShareValueEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shares (section 7.1). Allocation by Admin only; no trading.
 * Share value = (contributed capital + Σ realised profit of sold plots) ÷ total shares.
 * Only plot sales (and the one-off closing true-up) change the value.
 */
class ShareService
{
    public function __construct(private readonly LedgerService $ledger, private readonly Settings $settings) {}

    public function poolFor(Layout $layout): SharePool
    {
        $pool = SharePool::query()->where('layout_id', $layout->id)->first();
        if ($pool) {
            return $pool;
        }

        $pool = new SharePool(['face_value' => (float) $this->settings->get('share_face_value', 1000)]);
        $pool->layout_id = $layout->id;
        $pool->current_share_value = $pool->face_value;
        $pool->save();

        return $pool;
    }

    public function lockBaseline(Layout $layout, float $costPerSqft): void
    {
        $pool = $this->poolFor($layout);
        $pool->baseline_cost_per_sqft = $costPerSqft;
        if ((float) $pool->total_shares === 0.0) {
            $pool->current_share_value = $pool->face_value;
        }
        $pool->save();
    }

    public function allocate(SharePool $pool, Shareholder $holder, array $data): ShareIssuance
    {
        return DB::transaction(function () use ($pool, $holder, $data) {
            $pool = SharePool::query()->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            abort_if($pool->status === 'closed', 422, 'This share pool is closed.');

            $hasSales = $pool->events()->exists();
            if ($hasSales && trim((string) ($data['reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['reason' => 'A reason is required for allocations after sales have started.']);
            }

            $price = $hasSales ? (float) $pool->current_share_value : (float) $pool->face_value;
            $amount = round((float) $data['contribution_amount'], 2);
            $shares = round($amount / $price, 4);

            $issuance = new ShareIssuance;
            $issuance->forceFill([
                'share_pool_id' => $pool->id,
                'shareholder_id' => $holder->id,
                'contribution_type' => $data['contribution_type'],
                'contribution_amount' => $amount,
                'issue_price' => $price,
                'shares' => $shares,
                'issued_on' => $data['issued_on'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ])->save();

            $this->applyToPool($pool, $shares, $amount);

            $this->ledger->post([
                'layout_id' => $pool->layout_id, 'direction' => 'in', 'type' => 'investment',
                'category' => 'Partner contribution', 'amount' => $amount, 'party' => $holder->name,
                'description' => ucfirst($data['contribution_type']).' contribution for '.number_format($shares, 4).' shares',
            ], $issuance);

            return $issuance;
        });
    }

    public function reverseIssuance(ShareIssuance $original, string $reason): ShareIssuance
    {
        return DB::transaction(function () use ($original, $reason) {
            abort_if(ShareIssuance::query()->where('reversal_of', $original->id)->exists(), 422, 'Already reversed.');
            abort_if($original->reversal_of !== null, 422, 'A reversal cannot be reversed.');
            $pool = SharePool::query()->whereKey($original->share_pool_id)->lockForUpdate()->firstOrFail();

            $reversal = new ShareIssuance;
            $reversal->forceFill([
                'share_pool_id' => $pool->id,
                'shareholder_id' => $original->shareholder_id,
                'contribution_type' => $original->contribution_type,
                'contribution_amount' => -$original->contribution_amount,
                'issue_price' => $original->issue_price,
                'shares' => -$original->shares,
                'issued_on' => now()->toDateString(),
                'reason' => 'Reversal: '.$reason,
                'reversal_of' => $original->id,
                'created_by' => auth()->id(),
            ])->save();

            $this->applyToPool($pool, -(float) $original->shares, -(float) $original->contribution_amount);

            $ledgerOriginal = LedgerEntry::query()->where('source_type', 'ShareIssuance')->where('source_id', $original->id)->first();
            if ($ledgerOriginal) {
                $this->ledger->reverse($ledgerOriginal, $reason);
            }

            return $reversal;
        });
    }

    /** Called once per sale when it reaches the recognition point (Sold by default). Idempotent. */
    public function recogniseSale(Sale $sale): ?ShareValueEvent
    {
        $plot = $sale->plot;
        $pool = SharePool::query()->where('layout_id', $plot->layout_id)->lockForUpdate()->first();

        if (! $pool || (float) $pool->total_shares <= 0 || ShareValueEvent::query()->where('sale_id', $sale->id)->exists()) {
            return null;
        }

        $baseline = (float) ($pool->baseline_cost_per_sqft ?? 0);
        $profit = round((float) $sale->sale_value - (float) $plot->size_sqft * $baseline - (float) $sale->commission_amount, 2);

        return $this->recordEvent($pool, 'sale', $profit, (float) $sale->sale_value, $sale);
    }

    /** One-off closing adjustment: actual expenses vs the locked baseline estimate. */
    public function trueUp(Layout $layout): ?ShareValueEvent
    {
        $pool = SharePool::query()->where('layout_id', $layout->id)->lockForUpdate()->first();
        $estimate = $layout->estimates()->whereNotNull('locked_at')->orderBy('version')->first();
        if (! $pool || ! $estimate || (float) $pool->total_shares <= 0) {
            return null;
        }

        $actual = (float) LedgerEntry::query()->where('layout_id', $layout->id)->where('type', 'expense')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE -amount END), 0) AS total")->value('total');
        $baselineCost = (float) $estimate->total_cost - (float) $estimate->land_cost;
        $variance = round($baselineCost - $actual, 2);

        return $variance == 0.0 ? null : $this->recordEvent($pool, 'true_up', $variance, 0, null);
    }

    public function payout(SharePool $pool, Shareholder $holder, array $data): SharePayout
    {
        return DB::transaction(function () use ($pool, $holder, $data) {
            $balance = $this->balanceFor($pool, $holder);
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Payout cannot exceed the balance of '.number_format($balance, 2).'.']);
            }

            $payout = new SharePayout($data);
            $payout->share_pool_id = $pool->id;
            $payout->shareholder_id = $holder->id;
            $payout->created_by = auth()->id();
            $payout->save();

            $this->ledger->post([
                'layout_id' => $pool->layout_id, 'direction' => 'out', 'type' => 'distribution',
                'category' => 'Shareholder payouts', 'amount' => $amount, 'party' => $holder->name,
                'mode' => $data['mode'], 'reference_no' => $data['reference_no'] ?? null,
                'entry_date' => $data['paid_on'], 'description' => 'Shareholder payout',
            ], $payout);

            return $payout;
        });
    }

    /** Current holdings computed from the append-only issuance ledger. */
    public function holdings(SharePool $pool): Collection
    {
        $rows = ShareIssuance::query()->where('share_pool_id', $pool->id)
            ->selectRaw('shareholder_id, SUM(shares) AS shares, SUM(contribution_amount) AS contributed')
            ->groupBy('shareholder_id')->havingRaw('SUM(shares) > 0')->with('shareholder')->get();

        $total = (float) $rows->sum('shares');

        return $rows->map(fn ($row) => (object) [
            'shareholder' => $row->shareholder,
            'shareholder_id' => $row->shareholder_id,
            'shares' => (float) $row->shares,
            'contributed' => (float) $row->contributed,
            'pct' => $total > 0 ? (float) $row->shares / $total * 100 : 0,
            'value' => (float) $row->shares * (float) $pool->current_share_value,
            'allocated' => (float) ShareAllocation::query()->where('shareholder_id', $row->shareholder_id)
                ->whereHas('event', fn ($q) => $q->where('share_pool_id', $pool->id))->sum('allocated_profit'),
            'paid' => (float) SharePayout::query()->where('share_pool_id', $pool->id)->where('shareholder_id', $row->shareholder_id)->sum('amount'),
        ]);
    }

    public function balanceFor(SharePool $pool, Shareholder $holder): float
    {
        $allocated = (float) ShareAllocation::query()->where('shareholder_id', $holder->id)
            ->whereHas('event', fn ($q) => $q->where('share_pool_id', $pool->id))->sum('allocated_profit');
        $paid = (float) SharePayout::query()->where('share_pool_id', $pool->id)->where('shareholder_id', $holder->id)->sum('amount');

        return round($allocated - $paid, 2);
    }

    private function recordEvent(SharePool $pool, string $type, float $profit, float $saleValue, ?Sale $sale): ShareValueEvent
    {
        $holdings = $this->holdings($pool);
        $totalShares = (float) $pool->total_shares;

        $pool->cumulative_profit = round((float) $pool->cumulative_profit + $profit, 2);
        $pool->current_share_value = round(((float) $pool->total_capital + (float) $pool->cumulative_profit) / $totalShares, 4);
        $pool->save();

        $event = new ShareValueEvent;
        $event->forceFill([
            'share_pool_id' => $pool->id,
            'plot_id' => $sale?->plot_id,
            'sale_id' => $sale?->id,
            'event_type' => $type,
            'sale_value' => $saleValue,
            'realised_profit' => $profit,
            'profit_per_share' => round($profit / $totalShares, 6),
            'share_value_after' => $pool->current_share_value,
            'occurred_at' => now(),
            'created_by' => auth()->id(),
        ])->save();

        // Allocations sum exactly to the realised profit: the last holder takes the rounding remainder.
        $remainingProfit = $profit;
        $remainingProceeds = $saleValue;
        $last = $holdings->count() - 1;
        foreach ($holdings->values() as $i => $holding) {
            $share = $holding->shares / $totalShares;
            $allocatedProfit = $i === $last ? round($remainingProfit, 2) : round($profit * $share, 2);
            $allocatedProceeds = $i === $last ? round($remainingProceeds, 2) : round($saleValue * $share, 2);
            $remainingProfit -= $allocatedProfit;
            $remainingProceeds -= $allocatedProceeds;

            $allocation = new ShareAllocation;
            $allocation->forceFill([
                'share_value_event_id' => $event->id,
                'shareholder_id' => $holding->shareholder_id,
                'shares_held' => $holding->shares,
                'holding_pct' => round($share * 100, 6),
                'allocated_profit' => $allocatedProfit,
                'allocated_proceeds' => $allocatedProceeds,
            ])->save();
        }

        return $event;
    }

    private function applyToPool(SharePool $pool, float $shares, float $amount): void
    {
        $pool->total_shares = round((float) $pool->total_shares + $shares, 4);
        $pool->total_capital = round((float) $pool->total_capital + $amount, 2);
        $pool->current_share_value = (float) $pool->total_shares > 0
            ? round(((float) $pool->total_capital + (float) $pool->cumulative_profit) / (float) $pool->total_shares, 4)
            : $pool->face_value;
        $pool->save();

        foreach ($this->holdings($pool) as $holding) {
            $snapshot = new ShareHolding;
            $snapshot->forceFill([
                'share_pool_id' => $pool->id,
                'shareholder_id' => $holding->shareholder_id,
                'shares' => $holding->shares,
                'holding_pct' => round($holding->pct, 6),
                'as_of' => now(),
            ])->save();
        }
    }
}
