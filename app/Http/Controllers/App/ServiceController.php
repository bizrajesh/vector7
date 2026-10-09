<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Services the platform offers (legal opinion, survey, registration help, loans, marketing). Requests arrive as tickets. */
class ServiceController extends Controller
{
    public function index()
    {
        return view('app.services.index', [
            'services' => Service::orderBy('sort')->orderBy('name')->get(),
            'requests' => ServiceRequest::with(['service', 'tenant:id,name', 'customer:id,name', 'user:id,name', 'ticket:id,number,status'])->latest('id')->take(30)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        Service::create($data);

        return back()->with('ok', 'Service added.');
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validated($request, $service);
        if ($data['name'] !== $service->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $service->id);
        }
        $service->update($data);

        return back()->with('ok', 'Service updated.');
    }

    public function destroy(Service $service)
    {
        if (ServiceRequest::where('service_id', $service->id)->exists()) {
            $service->update(['is_active' => false]);

            return back()->with('ok', 'This service has requests, so it was deactivated instead of deleted.');
        }
        $service->delete();

        return back()->with('ok', 'Service deleted.');
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service?->id)],
            'summary' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'price_on_request' => 'boolean',
            'available_to' => 'required|in:tenant,customer,both',
            'is_active' => 'boolean',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
        $data['price_on_request'] = $request->boolean('price_on_request');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] = (int) ($data['sort'] ?? 0);
        if ($data['price_on_request']) {
            $data['price'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 2;
        while (Service::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
