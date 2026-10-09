<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequirement;
use App\Services\RequirementSearch;
use Illuminate\Http\Request;

/** Post a requirement in plain words → filters → matching plots; signed-in customers can save it for email alerts. */
class RequirementController extends Controller
{
    public function search(Request $request)
    {
        $text = trim((string) $request->input('q', ''));
        $filters = [];
        $plots = collect();
        if ($text !== '') {
            $request->validate(['q' => 'string|max:500']);
            $filters = RequirementSearch::parse($text);
            $plots = RequirementSearch::matches($filters);
        }

        return view('market.requirement', [
            'q' => $text,
            'filters' => $filters,
            'chips' => RequirementSearch::describe($filters),
            'plots' => $plots,
            'seo' => ['title' => 'Find a plot that fits your needs', 'description' => 'Describe the plot you want — location, budget, size, facing — and see matching approved plots instantly.', 'canonical' => route('market.requirement'), 'noindex' => $text !== ''],
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|max:500', 'filters' => 'required|string|max:2000']);
        $filters = json_decode($data['filters'], true);
        abort_unless(is_array($filters), 422);
        $filters = array_intersect_key($filters, array_flip(RequirementSearch::KEYS));
        $request->user('customer')->requirements()->create(['query_text' => $data['q'], 'filters' => $filters, 'is_active' => true]);

        return redirect()->route('account.requirements')->with('ok', 'Requirement saved. We will email you when a matching plot is launched.');
    }

    public function destroy(Request $request, CustomerRequirement $requirement)
    {
        abort_unless($requirement->customer_id === $request->user('customer')->id, 404);
        $requirement->delete();

        return back()->with('ok', 'Requirement removed.');
    }
}
