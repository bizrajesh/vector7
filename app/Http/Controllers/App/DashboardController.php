<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Layout;
use App\Models\PurchaseRequest;
use App\Models\Sale;
use App\Models\SaleInstalment;
use App\Services\Analytics;
use App\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Analytics $analytics, PlanLimits $limits): View
    {
        $request->validate(['period' => ['nullable', Rule::in(['month', 'quarter', 'year'])]]);
        $user = $request->user();

        if ($user->hasRole('sales')) {
            return view('app.dashboard-sales', [
                'dueToday' => SaleInstalment::query()->whereIn('status', ['due', 'partial'])
                    ->where('due_date', '<=', now()->toDateString())
                    ->whereHas('sale', fn ($q) => $q->where('status', 'ongoing'))
                    ->with('sale.customer', 'sale.plot')->orderBy('due_date')->limit(10)->get(),
                'expiring' => Booking::query()->whereIn('status', ['pending', 'active'])->where('expires_at', '<=', now()->addDays(5))
                    ->with('plot.layout', 'customer')->orderBy('expires_at')->limit(6)->get(),
                'mySales' => Sale::query()->where('created_by', $user->id)->where('sale_date', '>=', now()->startOfMonth()->toDateString())
                    ->whereIn('status', ['ongoing', 'paid', 'registered']),
                'layouts' => Layout::query()->where('status', 'launched')->get(['id', 'name', 'code']),
                'websiteHolds' => Booking::query()->where('status', 'pending')->with('plot', 'customer')->orderBy('expires_at')->limit(10)->get(),
                'newRequests' => PurchaseRequest::query()->where('status', 'new')->count(),
            ]);
        }

        return view('app.dashboard', [
            'd' => $analytics->adminDashboard(null, $request->input('period', 'month')),
            'period' => $request->input('period', 'month'),
            'usage' => $limits->usage(),
            'websiteHolds' => Booking::query()->where('status', 'pending')->count(),
            'newRequests' => PurchaseRequest::query()->where('status', 'new')->count(),
        ]);
    }
}
