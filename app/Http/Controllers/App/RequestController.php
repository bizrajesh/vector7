<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Purchase / call-back requests from the website and customer portal. */
class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::in(['new', 'contacted', 'closed'])]]);

        return view('app.requests.index', [
            'requests' => PurchaseRequest::query()->with(['plot', 'layout', 'booking', 'handler'])
                ->where('status', $request->input('status', 'new'))
                ->latest()->paginate(25)->withQueryString(),
            'counts' => PurchaseRequest::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['contacted', 'closed'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $purchaseRequest->status = $data['status'];
        $purchaseRequest->notes = $data['notes'] ?? $purchaseRequest->notes;
        $purchaseRequest->handled_by = $request->user()->id;
        $purchaseRequest->handled_at = now();
        $purchaseRequest->save();

        return $this->done('Request updated.');
    }
}
