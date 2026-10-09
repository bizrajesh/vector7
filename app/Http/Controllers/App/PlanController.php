<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Subscription plans with limits (storage, projects, users per role, modules, AI credits). */
class PlanController extends Controller
{
    public function index()
    {
        return view('app.plans.index', ['plans' => Plan::with('limits')->withCount(['limits'])->orderBy('sort')->get(),
            'counts' => Subscription::selectRaw('plan_id, COUNT(*) c')->whereIn('status', ['trial', 'active', 'past_due'])->groupBy('plan_id')->pluck('c', 'plan_id')]);
    }

    public function create()
    {
        return view('app.plans.form', ['plan' => new Plan(['billing_cycle' => 'monthly', 'trial_days' => 14, 'is_active' => true, 'modules' => array_keys(config('permissions.plan_modules'))])]);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $plan = Plan::create($data['plan'] + ['slug' => Str::slug($data['plan']['name']).'-'.Str::lower(Str::random(4))]);
            $this->saveLimits($plan, $data['limits']);
        });

        return redirect()->route('app.plans.index')->with('ok', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        return view('app.plans.form', ['plan' => $plan->load('limits')]);
    }

    public function update(Request $request, Plan $plan)
    {
        DB::transaction(function () use ($request, $plan) {
            $data = $this->validated($request);
            $plan->update($data['plan']);
            $this->saveLimits($plan, $data['limits']);
        });

        return redirect()->route('app.plans.index')->with('ok', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        if (Subscription::where('plan_id', $plan->id)->exists()) {
            return back()->with('error', 'This plan has subscriptions. Mark it inactive instead.');
        }
        $plan->delete();

        return back()->with('ok', 'Plan deleted.');
    }

    private function validated(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:60',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'trial_days' => 'required|integer|min:0|max:365',
            'modules' => 'array',
            'modules.*' => 'in:'.implode(',', array_keys(config('permissions.plan_modules'))),
            'sort' => 'nullable|integer|min:0',
        ];
        foreach (array_keys(PlanLimiter::LIMIT_LABELS) as $k) {
            $rules["limits.$k"] = 'required|integer|min:-1';
        }
        $v = $request->validate($rules, ['limits.*.min' => 'Use -1 for unlimited.']);
        if ($request->boolean('is_trial_default')) {
            Plan::query()->update(['is_trial_default' => false]);
        }

        return [
            'plan' => [
                'name' => $v['name'], 'description' => $v['description'] ?? null, 'price' => $v['price'], 'billing_cycle' => $v['billing_cycle'],
                'trial_days' => $v['trial_days'], 'modules' => array_values($v['modules'] ?? []), 'sort' => $v['sort'] ?? 0,
                'is_active' => $request->boolean('is_active'), 'is_trial_default' => $request->boolean('is_trial_default'),
            ],
            'limits' => $v['limits'],
        ];
    }

    private function saveLimits(Plan $plan, array $limits): void
    {
        foreach ($limits as $key => $value) {
            $plan->limits()->updateOrCreate(['key' => $key], ['value' => (int) $value]);
        }
    }
}
