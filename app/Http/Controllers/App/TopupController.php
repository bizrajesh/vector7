<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantTopup;
use App\Models\TopupPack;
use App\Services\TopupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Top-up packs the App team designs (extra users, projects, plots, storage, listings) and top-ups granted to tenants. */
class TopupController extends Controller
{
    public function packs()
    {
        return view('app.plans.topups', [
            'packs' => TopupPack::orderBy('sort')->orderBy('id')->get(),
            'recent' => TenantTopup::with(['tenantRel:id,name,code', 'invoice'])->latest('id')->take(30)->get(),
            'sold' => SubscriptionInvoice::where('kind', 'topup')->where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
        ]);
    }

    public function storePack(Request $request)
    {
        TopupPack::create($this->validated($request));

        return back()->with('ok', 'Top-up pack added.');
    }

    public function updatePack(Request $request, TopupPack $pack)
    {
        $pack->update($this->validated($request));

        return back()->with('ok', 'Top-up pack saved.');
    }

    public function destroyPack(TopupPack $pack)
    {
        if (TenantTopup::where('topup_pack_id', $pack->id)->exists()) {
            $pack->update(['is_active' => false]);

            return back()->with('ok', 'This pack has been bought before, so it was switched off instead of deleted.');
        }
        $pack->delete();

        return back()->with('ok', 'Top-up pack deleted.');
    }

    /** Free extra allowance for one tenant. */
    public function grant(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(TopupPack::KEYS))],
            'units' => 'required|integer|min:1|max:100000000',
            'ends_on' => 'nullable|date|after_or_equal:today',
            'note' => 'nullable|string|max:255',
        ]);
        $units = (int) $data['units'];
        if ($data['key'] === 'storage_mb' && $request->input('storage_unit', 'GB') === 'GB') {
            $units *= 1024;
        }
        $topup = TopupService::grant($tenant, $data['key'], $units, isset($data['ends_on']) ? Carbon::parse($data['ends_on']) : null, $data['note'] ?? null, $request->user());

        return back()->with('ok', 'Granted '.$topup->label().'.');
    }

    public function cancel(TenantTopup $topup)
    {
        TopupService::cancel($topup);

        return back()->with('ok', 'Top-up cancelled.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'key' => ['required', Rule::in(array_keys(TopupPack::KEYS))],
            'name' => 'required|string|max:80',
            'units' => 'required|integer|min:1|max:10000000',
            'price' => 'required|numeric|min:0|max:10000000',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);

        return $v + ['is_active' => $request->boolean('is_active'), 'sort' => $v['sort'] ?? 0];
    }
}
