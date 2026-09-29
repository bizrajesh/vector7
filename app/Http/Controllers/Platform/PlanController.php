<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    private const FEATURES = ['whatsapp', 'dss', 'public_listings', 'google_drive', 'shareholder_portal'];

    public function index(): View
    {
        return view('platform.plans.index', ['plans' => Plan::query()->withCount('tenants')->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('platform.plans.form', ['plan' => new Plan(['trial_days' => 14, 'status' => 'active', 'features' => []]), 'features' => self::FEATURES]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return $this->done('Plan created.', 'platform.plans.index');
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.form', ['plan' => $plan, 'features' => self::FEATURES]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return $this->done('Plan updated.', 'platform.plans.index');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')->ignore($plan)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:40'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'max_users' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_layouts' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_plots' => ['required', 'integer', 'min:1', 'max:10000000'],
            'max_storage_mb' => ['required', 'integer', 'min:100', 'max:10000000'],
            'status' => ['required', Rule::in(['active', 'hidden', 'archived'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'features' => ['array'],
            'features.*' => [Rule::in(self::FEATURES)],
        ]);

        $data['features'] = collect(self::FEATURES)->mapWithKeys(fn ($f) => [$f => in_array($f, $data['features'] ?? [], true)])->all();

        return $data;
    }
}
