<?php

namespace App\Http\Controllers\App;

use App\Enums\LayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Layout;
use App\Models\LayoutDocument;
use App\Models\LayoutFacility;
use App\Models\LayoutOwner;
use App\Models\LayoutSurveyNumber;
use App\Models\NotificationGroup;
use App\Models\StageGroup;
use App\Models\User;
use App\Services\Analytics;
use App\Services\EstimateService;
use App\Services\FileVault;
use App\Services\LayoutService;
use App\Services\PlanLimits;
use App\Services\Settings;
use App\Services\ShareService;
use App\Services\StageService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layout Projects (section 5): wizard data, stages, estimate, submit / launch / close.
 */
class LayoutController extends Controller
{
    public function __construct(private readonly FileVault $vault) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(LayoutStatus::class)]]);

        return view('app.layouts.index', [
            'layouts' => Layout::query()->withCount(['plots', 'plots as sold_count' => fn ($q) => $q->where('status', 'sold')])
                ->with(['stages:id,layout_id,status,is_mandatory', 'latestEstimate'])
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Settings $settings): View
    {
        return view('app.layouts.form', ['layout' => new Layout(['sellable_pct' => $settings->get('sellable_pct'), 'std_plot_sqft' => 1200, 'contingency_pct' => 0])]);
    }

    public function store(Request $request, PlanLimits $limits): RedirectResponse
    {
        $limits->ensureCanAddLayout();

        $layout = new Layout($this->validated($request));
        $layout->status = LayoutStatus::Draft;
        $layout->created_by = $request->user()->id;
        $layout->save();

        return $this->done('Layout created. Add owners, survey numbers and a stage group next.', 'app.layouts.show', $layout);
    }

    public function show(Request $request, Layout $layout, EstimateService $estimates, Analytics $analytics, ShareService $shares): View
    {
        $request->validate(['tab' => ['nullable', Rule::in(['overview', 'land', 'stages', 'facilities', 'estimate', 'shares'])]]);

        $layout->load(['owners', 'surveyNumbers', 'documents', 'facilities.facility', 'stages.tasks', 'stages.dependsOn:project_stages.id,project_stages.name', 'stages.owner:id,name', 'notificationGroups', 'estimates', 'sharePool']);

        return view('app.layouts.show', [
            'layout' => $layout,
            'tab' => $request->input('tab', 'overview'),
            'estimate' => $estimates->compute($layout),
            'actualCost' => $analytics->layoutActualCost($layout),
            'stageGroups' => StageGroup::query()->where('is_active', true)->orderBy('name')->get(),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'notificationGroups' => NotificationGroup::query()->orderBy('name')->get(),
            'owners' => User::query()->inCurrentTenant()->where('status', 'active')->whereIn('role', ['admin', 'sales'])->orderBy('name')->get(['id', 'name']),
            'holdings' => $layout->sharePool ? $shares->holdings($layout->sharePool) : collect(),
            'plotCounts' => $layout->plots()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function edit(Layout $layout): View
    {
        abort_unless($layout->isEditable(), 422, 'Only draft projects can be edited.');

        return view('app.layouts.form', ['layout' => $layout]);
    }

    public function update(Request $request, Layout $layout): RedirectResponse
    {
        abort_unless($layout->isEditable(), 422, 'Only draft projects can be edited.');
        $layout->update($this->validated($request, $layout));

        return $this->done('Layout updated.', 'app.layouts.show', $layout);
    }

    public function storeOwner(Request $request, Layout $layout): RedirectResponse
    {
        $this->editable($layout);
        $owner = new LayoutOwner($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'father_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'aadhaar' => ['nullable', 'regex:/^[0-9]{4}\s?[0-9]{4}\s?[0-9]{4}$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'share_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]));
        $owner->layout_id = $layout->id;
        $owner->save();

        return $this->tab($layout, 'land', 'Owner added.');
    }

    public function destroyOwner(Layout $layout, LayoutOwner $owner): RedirectResponse
    {
        $this->editable($layout);
        $owner->delete();

        return $this->tab($layout, 'land', 'Owner removed.');
    }

    public function storeSurvey(Request $request, Layout $layout): RedirectResponse
    {
        $this->editable($layout);
        $survey = new LayoutSurveyNumber($request->validate([
            'survey_no' => ['required', 'string', 'max:40'],
            'sub_division' => ['nullable', 'string', 'max:40'],
            'extent_sqft' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'guideline_value_sqft' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]));
        $survey->layout_id = $layout->id;
        $survey->save();

        return $this->tab($layout, 'land', 'Survey number added.');
    }

    public function destroySurvey(Layout $layout, LayoutSurveyNumber $survey): RedirectResponse
    {
        $this->editable($layout);
        $survey->delete();

        return $this->tab($layout, 'land', 'Survey number removed.');
    }

    public function storeDocument(Request $request, Layout $layout): RedirectResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(['Patta', 'Chitta', 'EC', 'Parent document', 'FMB sketch', 'Owner ID', 'Approval', 'Other'])],
            'doc_no' => ['nullable', 'string', 'max:80'],
            'doc_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['obtained', 'pending'])],
            'file' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:'.implode(',', config('vector7.uploads.mimes'))],
        ]);

        $document = new LayoutDocument(Arr::except($data, 'file'));
        $document->layout_id = $layout->id;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $document->file_path = $this->vault->store($file, 'layout-documents');
            $document->original_name = mb_substr($file->getClientOriginalName(), 0, 190);
            $document->mime = $file->getMimeType();
            $document->size_bytes = $file->getSize();
        }
        $document->uploaded_by = $request->user()->id;
        $document->save();

        return $this->tab($layout, 'land', 'Document saved.');
    }

    public function downloadDocument(Layout $layout, LayoutDocument $document): StreamedResponse
    {
        return $this->vault->download($document->file_path, $document->original_name ?? 'document');
    }

    public function destroyDocument(Layout $layout, LayoutDocument $document): RedirectResponse
    {
        $this->editable($layout);
        $this->vault->delete($document->file_path);
        $document->delete();

        return $this->tab($layout, 'land', 'Document removed.');
    }

    public function storeFacility(Request $request, Layout $layout): RedirectResponse
    {
        $this->editable($layout);
        $data = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'qty' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $facility = Facility::query()->findOrFail($data['facility_id']);
        $unitCost = (float) ($data['unit_cost'] ?? $facility->unit_cost);

        $row = LayoutFacility::query()->where('layout_id', $layout->id)->where('facility_id', $facility->id)->first() ?? new LayoutFacility;
        $row->layout_id = $layout->id;
        $row->fill([
            'facility_id' => $facility->id,
            'qty' => $data['qty'],
            'unit_cost' => $unitCost,
            'total' => round($data['qty'] * $unitCost, 2),
            'area_sqft' => $facility->deduct_from_sellable && $facility->unit === 'sqft' ? $data['qty'] : 0,
        ])->save();

        return $this->tab($layout, 'facilities', 'Facility saved.');
    }

    public function destroyFacility(Layout $layout, LayoutFacility $facility): RedirectResponse
    {
        $this->editable($layout);
        $facility->delete();

        return $this->tab($layout, 'facilities', 'Facility removed.');
    }

    public function applyStageGroup(Request $request, Layout $layout, StageService $stages): RedirectResponse
    {
        $data = $request->validate(['stage_group_id' => ['required', Rule::exists('stage_groups', 'id')->where('tenant_id', app(TenantContext::class)->id())]]);
        $stages->instantiate($layout, StageGroup::query()->findOrFail($data['stage_group_id']));

        return $this->tab($layout, 'stages', 'Stages copied from the group. Adjust budgets, owners and links as needed.');
    }

    public function syncNotificationGroups(Request $request, Layout $layout): RedirectResponse
    {
        $data = $request->validate([
            'groups' => ['array'],
            'groups.*' => [Rule::exists('notification_groups', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);
        $layout->notificationGroups()->sync(array_fill_keys($data['groups'] ?? [], ['tenant_id' => $layout->tenant_id]));

        return $this->tab($layout, 'overview', 'Notification groups updated.');
    }

    /** Show / hide a launched project on the public website (plan feature "public_listings"). */
    public function publish(Request $request, Layout $layout, PlanLimits $limits): RedirectResponse
    {
        abort_unless($limits->feature('public_listings'), 403, 'Public project pages are not included in your plan.');
        $data = $request->validate([
            'is_public' => ['required', 'boolean'],
            'public_summary' => ['nullable', 'string', 'max:500'],
        ]);
        $layout->update($data);

        return $this->tab($layout, 'overview', $data['is_public'] ? 'Project is visible on the website once launched.' : 'Project hidden from the website.');
    }

    public function submit(Layout $layout, LayoutService $service): RedirectResponse
    {
        $service->submit($layout);

        return $this->tab($layout, 'estimate', 'Project submitted. The estimate is locked as baseline v1.');
    }

    public function launch(Layout $layout, LayoutService $service): RedirectResponse
    {
        $service->launch($layout);

        return $this->done('Project launched. Plots are now open for booking.', 'app.plots.index', $layout);
    }

    public function close(Request $request, Layout $layout, LayoutService $service): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        $service->close($layout);

        return $this->done('Project closed and the final share true-up recorded.', 'app.layouts.show', $layout);
    }

    private function validated(Request $request, ?Layout $layout = null): array
    {
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('layouts', 'code')->where('tenant_id', app(TenantContext::class)->id())->ignore($layout)],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:190'],
            'village' => ['nullable', 'string', 'max:80'],
            'taluk' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'total_sqft' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'sellable_pct' => ['required', 'numeric', 'min:1', 'max:100'],
            'std_plot_sqft' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'default_rate_sqft' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'land_cost' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'contingency_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'planned_launch_date' => ['nullable', 'date'],
        ]);
    }

    private function editable(Layout $layout): void
    {
        abort_unless($layout->isEditable(), 422, 'This project is submitted; land details and facilities are locked.');
    }

    private function tab(Layout $layout, string $tab, string $message): RedirectResponse
    {
        return redirect()->route('app.layouts.show', ['layout' => $layout, 'tab' => $tab])->with('status', $message);
    }
}
