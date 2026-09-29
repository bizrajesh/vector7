<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Models\DocumentWriter;
use App\Models\Facility;
use App\Models\Holiday;
use App\Models\SubRegistrarOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Simple master lists (facilities, brokers, document writers, SROs, holidays).
 * The {type} segment is matched against a fixed allow-list — never used to build a class or table name.
 */
class MasterDataController extends Controller
{
    private function definition(string $type): array
    {
        $types = [
            'facilities' => [
                'model' => Facility::class, 'title' => 'Facilities & features',
                'fields' => ['name' => 'Name', 'unit' => 'Unit', 'unit_cost' => 'Unit cost (₹)', 'deduct_from_sellable' => 'Deduct area from sellable'],
                'rules' => ['name' => ['required', 'string', 'max:120'], 'unit' => ['required', Rule::in(['sqft', 'rft', 'nos', 'lumpsum'])], 'unit_cost' => ['required', 'numeric', 'min:0', 'max:100000000'], 'deduct_from_sellable' => ['boolean']],
            ],
            'brokers' => [
                'model' => Broker::class, 'title' => 'Brokers',
                'fields' => ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'pan' => 'PAN', 'commission_pct' => 'Commission % (blank = default)'],
                'rules' => ['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'], 'email' => ['nullable', 'email', 'max:190'], 'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'], 'commission_pct' => ['nullable', 'numeric', 'min:0', 'max:20']],
            ],
            'document-writers' => [
                'model' => DocumentWriter::class, 'title' => 'Document writers',
                'fields' => ['name' => 'Name', 'licence_no' => 'Licence no', 'office' => 'Office', 'phone' => 'Phone', 'email' => 'Email'],
                'rules' => ['name' => ['required', 'string', 'max:120'], 'licence_no' => ['nullable', 'string', 'max:60'], 'office' => ['nullable', 'string', 'max:190'], 'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'], 'email' => ['nullable', 'email', 'max:190']],
            ],
            'sub-registrar-offices' => [
                'model' => SubRegistrarOffice::class, 'title' => 'Sub-Registrar offices',
                'fields' => ['name' => 'Name', 'district' => 'District'],
                'rules' => ['name' => ['required', 'string', 'max:120'], 'district' => ['nullable', 'string', 'max:80']],
            ],
            'holidays' => [
                'model' => Holiday::class, 'title' => 'Holidays (excluded from working days)',
                'fields' => ['holiday_date' => 'Date', 'name' => 'Name'],
                'rules' => ['holiday_date' => ['required', 'date'], 'name' => ['required', 'string', 'max:120']],
            ],
        ];

        abort_unless(isset($types[$type]), 404);

        return $types[$type] + ['type' => $type];
    }

    public function index(string $type): View
    {
        $def = $this->definition($type);

        return view('app.settings.masters', ['def' => $def, 'rows' => $def['model']::query()->latest('id')->paginate(50)]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $def = $this->definition($type);
        $def['model']::create($this->normalise($request->validate($def['rules']), $def));

        return $this->done('Saved.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $def = $this->definition($type);
        $row = $def['model']::query()->findOrFail($id);
        $row->update($this->normalise($request->validate($def['rules']), $def));

        return $this->done('Updated.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $def = $this->definition($type);
        $def['model']::query()->findOrFail($id)->delete();

        return $this->done('Removed.');
    }

    private function normalise(array $data, array $def): array
    {
        if (array_key_exists('deduct_from_sellable', $def['rules'])) {
            $data['deduct_from_sellable'] = (bool) ($data['deduct_from_sellable'] ?? false);
        }

        return $data;
    }
}
