<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Section 4.1 general settings. */
class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('app.settings.edit', ['s' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'sellable_pct' => ['required', 'numeric', 'min:1', 'max:100'],
            'broker_commission_pct' => ['required', 'numeric', 'min:0', 'max:20'],
            'budget_alert_pct' => ['required', 'numeric', 'min:1', 'max:200'],
            'booking_validity_days' => ['required', 'integer', 'min:1', 'max:90'],
            'online_hold_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'sale_window_working_days' => ['required', 'integer', 'min:1', 'max:120'],
            'share_face_value' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'share_recognition' => ['required', Rule::in(['sold', 'ror'])],
            'storage' => ['required', Rule::in(['local', 'google_drive'])],
            'drive_folder_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{10,100}$/'],
            'instalments' => ['required', 'array', 'min:1', 'max:6'],
            'instalments.*.pct' => ['required', 'numeric', 'min:1', 'max:100'],
            'instalments.*.due_working_day' => ['required', 'integer', 'min:0', 'max:120'],
        ], [], ['drive_folder_id' => 'Google Drive folder ID']);

        $total = collect($data['instalments'])->sum('pct');
        if (abs($total - 100) > 0.001) {
            return back()->withInput()->withErrors(['instalments' => 'Instalment percentages must add up to 100.']);
        }

        $data['instalments'] = collect($data['instalments'])->map(fn ($i) => ['pct' => (float) $i['pct'], 'due_working_day' => (int) $i['due_working_day']])->values()->all();
        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        return $this->done('Settings saved.');
    }
}
