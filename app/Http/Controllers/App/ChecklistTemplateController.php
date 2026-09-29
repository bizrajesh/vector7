<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChecklistTemplateController extends Controller
{
    public function index(): View
    {
        return view('app.settings.checklists.index', ['templates' => ChecklistTemplate::query()->withCount('items')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = ChecklistTemplate::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'purpose' => ['required', Rule::in(['registration', 'general'])],
        ]));

        return $this->done('Checklist created.', 'app.settings.checklists.show', $template);
    }

    public function show(ChecklistTemplate $checklist): View
    {
        return view('app.settings.checklists.show', ['template' => $checklist->load('items')]);
    }

    public function storeItem(Request $request, ChecklistTemplate $checklist): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:190'],
            'is_required' => ['boolean'], 'needs_upload' => ['boolean'], 'needs_date' => ['boolean'],
        ]);

        $item = new ChecklistTemplateItem([
            'label' => $data['label'],
            'is_required' => $request->boolean('is_required'),
            'needs_upload' => $request->boolean('needs_upload'),
            'needs_date' => $request->boolean('needs_date'),
            'sort_order' => (int) $checklist->items()->max('sort_order') + 1,
        ]);
        $item->checklist_template_id = $checklist->id;
        $item->save();

        return $this->done('Item added.');
    }

    public function destroyItem(ChecklistTemplate $checklist, ChecklistTemplateItem $item): RedirectResponse
    {
        $item->delete();

        return $this->done('Item removed.');
    }
}
