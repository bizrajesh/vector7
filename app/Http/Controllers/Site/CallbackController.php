<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\OnlineBookingService;
use App\Services\PublicCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Buy this plot / call me back" from the public plot map — handled by the sales team. */
class CallbackController extends Controller
{
    public function store(Request $request, string $tenant, string $code, PublicCatalog $catalog, TenantContext $context): RedirectResponse
    {
        [$tenantModel, $layout] = $catalog->find($tenant, $code);

        if (filled($request->input('website'))) {
            return back()->with('status', 'Thank you. Our sales team will call you shortly.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'plot_no' => ['nullable', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $context->run($tenantModel, function () use ($data, $layout) {
            $plot = ! empty($data['plot_no']) ? $layout->plots()->where('plot_no', $data['plot_no'])->first() : null;
            app(OnlineBookingService::class)->requestPurchase([
                'layout_id' => $layout->id, 'plot_id' => $plot?->id,
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
                'message' => $data['message'] ?? null, 'type' => $plot ? 'purchase' : 'callback', 'source' => 'website',
            ]);
        });

        return back()->with('status', 'Thank you. Our sales team will call you shortly to take you through the purchase.');
    }
}
