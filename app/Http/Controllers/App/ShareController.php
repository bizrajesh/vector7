<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Layout;
use App\Models\ShareIssuance;
use App\Models\Shareholder;
use App\Models\SharePool;
use App\Services\ShareService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manage Shares (section 7.1). Admin allocates; there are deliberately no
 * buy / sell / transfer endpoints anywhere in the application.
 */
class ShareController extends Controller
{
    public function __construct(private readonly ShareService $shares) {}

    public function index(): View
    {
        return view('app.shares.index', [
            'pools' => SharePool::query()->with('layout')->withCount('events')->get(),
            'layoutsWithoutPool' => Layout::query()->whereDoesntHave('sharePool')->where('status', '!=', 'closed')->get(['id', 'name', 'code']),
        ]);
    }

    public function show(SharePool $pool): View
    {
        return view('app.shares.show', [
            'pool' => $pool->load('layout'),
            'holdings' => $this->shares->holdings($pool),
            'events' => $pool->events()->with('plot')->latest('occurred_at')->limit(50)->get(),
            'issuances' => $pool->issuances()->with('shareholder')->latest('id')->limit(50)->get(),
            'payouts' => $pool->payouts()->with('shareholder')->latest('paid_on')->limit(50)->get(),
            'shareholders' => Shareholder::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function createPool(Layout $layout): RedirectResponse
    {
        $pool = $this->shares->poolFor($layout);

        return $this->done('Share pool created.', 'app.shares.show', $pool);
    }

    public function allocate(Request $request, SharePool $pool): RedirectResponse
    {
        $data = $request->validate([
            'shareholder_id' => ['required', Rule::exists('shareholders', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'contribution_type' => ['required', Rule::in(['cash', 'land', 'reinvest'])],
            'contribution_amount' => ['required', 'numeric', 'min:1', 'max:100000000000'],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $issuance = $this->shares->allocate($pool, Shareholder::query()->findOrFail($data['shareholder_id']), $data);

        return $this->done(number_format((float) $issuance->shares, 4).' shares allocated at ₹'.number_format((float) $issuance->issue_price, 2).' per share.');
    }

    public function reverse(Request $request, ShareIssuance $issuance): RedirectResponse
    {
        $this->shares->reverseIssuance($issuance, $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']])['reason']);

        return $this->done('Allocation reversed. The shareholder can see the reversal.');
    }

    public function payout(Request $request, SharePool $pool): RedirectResponse
    {
        $data = $request->validate([
            'shareholder_id' => ['required', Rule::exists('shareholders', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'mode' => ['required', Rule::in(['cash', 'upi', 'neft', 'cheque'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
        ]);

        $holder = Shareholder::query()->findOrFail($data['shareholder_id']);
        unset($data['shareholder_id']);
        $this->shares->payout($pool, $holder, $data);

        return $this->done('Payout recorded.');
    }

    public function shareholders(): View
    {
        return view('app.shares.shareholders', ['shareholders' => Shareholder::query()->withCount('issuances')->orderBy('name')->paginate(25)]);
    }

    public function storeShareholder(Request $request): RedirectResponse
    {
        Shareholder::create($request->validate($this->shareholderRules()));

        return $this->done('Shareholder added. Create a Shareholder login for them in Users & Roles.');
    }

    public function updateShareholder(Request $request, Shareholder $shareholder): RedirectResponse
    {
        $data = $request->validate($this->shareholderRules());
        // Blank sensitive fields mean "keep the stored value".
        foreach (['pan', 'bank_details'] as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }
        $shareholder->update($data);

        return $this->done('Shareholder updated.');
    }

    private function shareholderRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'bank_details' => ['nullable', 'string', 'max:255'],
        ];
    }
}
