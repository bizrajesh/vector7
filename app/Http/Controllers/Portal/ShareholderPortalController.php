<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ShareAllocation;
use App\Models\SharePool;
use App\Services\ShareService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only shareholder view. Every query is scoped to the logged-in user's own
 * shareholder record (OWASP A01: no IDOR — no shareholder id is taken from the request).
 */
class ShareholderPortalController extends Controller
{
    public function home(Request $request, ShareService $shares): View
    {
        $holder = $request->user()->shareholder;
        abort_unless($holder, 403, 'Your login is not linked to a shareholder record.');

        $pools = SharePool::query()->whereHas('issuances', fn ($q) => $q->where('shareholder_id', $holder->id))->with('layout')->get();
        $request->validate(['pool' => ['nullable', 'integer']]);
        $pool = $pools->firstWhere('id', (int) $request->query('pool')) ?? $pools->first();

        $mine = $pool ? $shares->holdings($pool)->firstWhere('shareholder_id', $holder->id) : null;

        return view('portal.shareholder', [
            'holder' => $holder,
            'pools' => $pools,
            'pool' => $pool,
            'mine' => $mine,
            'events' => $pool ? $pool->events()->get(['id', 'occurred_at', 'share_value_after', 'event_type']) : collect(),
            'allocations' => $pool ? ShareAllocation::query()->where('shareholder_id', $holder->id)
                ->whereHas('event', fn ($q) => $q->where('share_pool_id', $pool->id))
                ->with('event.plot')->latest('id')->limit(5)->get() : collect(),
            'projected' => $pool ? $this->projectedValue($pool) : null,
        ]);
    }

    public function allocations(Request $request): View
    {
        $holder = $request->user()->shareholder;
        abort_unless($holder, 403);

        return view('portal.shareholder-allocations', [
            'allocations' => ShareAllocation::query()->where('shareholder_id', $holder->id)->with('event.plot.layout')->latest('id')->paginate(25),
            'payouts' => $holder->payouts()->with('pool.layout')->latest('paid_on')->get(),
        ]);
    }

    /** Share value if every unsold plot sells at list price (informational). */
    private function projectedValue(SharePool $pool): ?float
    {
        if ((float) $pool->total_shares <= 0) {
            return null;
        }
        $unsold = $pool->layout->plots()->whereNotIn('status', ['sold'])->get(['size_sqft', 'cost']);
        $profit = $unsold->sum(fn ($p) => (float) $p->cost - (float) $p->size_sqft * (float) $pool->baseline_cost_per_sqft);

        return round(((float) $pool->total_capital + (float) $pool->cumulative_profit + $profit) / (float) $pool->total_shares, 2);
    }
}
