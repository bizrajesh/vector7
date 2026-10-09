<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class BrokerController extends Controller
{
    public function index()
    {
        return view('ws.brokers', ['brokers' => Broker::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Broker::create($this->validated($request) + ['tenant_id' => app(Tenancy::class)->id()]);

        return back()->with('ok', 'Broker added.');
    }

    public function update(Request $request, Broker $broker)
    {
        $broker->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('ok', 'Broker updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'email' => 'nullable|email',
            'commission_pct' => 'nullable|numeric|min:0|max:100',
        ]);
    }
}
