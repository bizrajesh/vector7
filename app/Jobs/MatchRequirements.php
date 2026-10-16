<?php

namespace App\Jobs;

use App\Models\CustomerRequirement;
use App\Models\Plot;
use App\Models\SavedSearch;
use App\Services\Notify;
use App\Services\PropertySearch;
use App\Services\RequirementSearch;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Email customers when something new matches what they asked for (once per item):
 *  - saved plot requirements (older "Find a plot" feature) — plots of a newly launched layout;
 *  - saved marketplace searches (spec 17.7) — a newly published listing or a newly launched layout.
 */
class MatchRequirements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $projectId = null, public ?int $listingId = null) {}

    public function handle(): void
    {
        app(Tenancy::class)->withoutScope(function () {
            if (! $this->listingId) {
                $this->requirements();
            }
            $this->savedSearches();
        });
    }

    private function requirements(): void
    {
        foreach (CustomerRequirement::where('is_active', true)->with('customer')->get() as $req) {
            if (! $req->customer?->is_active) {
                continue;
            }
            $plots = RequirementSearch::matches($req->filters ?? [], 200)
                ->when($this->projectId, fn ($c) => $c->where('project_id', $this->projectId));
            $already = DB::table('customer_requirement_matches')->where('customer_requirement_id', $req->id)->pluck('plot_id')->flip();
            $new = $plots->reject(fn (Plot $p) => isset($already[$p->id]))->take(5);
            foreach ($new as $p) {
                Notify::send('requirement_match', [$req->customer->email], [
                    'name' => $req->customer->name, 'query' => $req->query_text, 'plot_no' => $p->plot_no, 'project' => $p->project->name,
                    'location' => $p->project->location, 'size' => Format::num($p->size_sqft), 'facing' => $p->facing,
                    'price' => Format::inr($p->currentPrice()), 'link' => $p->publicUrl(),
                ]);
                DB::table('customer_requirement_matches')->insertOrIgnore(['customer_requirement_id' => $req->id, 'plot_id' => $p->id, 'notified_at' => now()]);
            }
            if ($new->isNotEmpty()) {
                $req->update(['last_notified_at' => now()]);
            }
        }
    }

    private function savedSearches(): void
    {
        foreach (SavedSearch::where('alerts', true)->with('customer')->get() as $s) {
            if (! $s->customer?->is_active) {
                continue;
            }
            $items = PropertySearch::search(PropertySearch::clean($s->filters ?? []), 'newest', false, 100)['items']
                ->when($this->listingId, fn ($c) => $c->where('key', 'L:'.$this->listingId))
                ->when($this->projectId, fn ($c) => $c->where('key', 'P:'.$this->projectId));
            $already = DB::table('saved_search_matches')->where('saved_search_id', $s->id)->pluck('item_key')->flip();
            $new = $items->reject(fn ($i) => isset($already[$i['key']]))->take(5)->values();
            if ($new->isEmpty()) {
                continue;
            }
            $list = $new->map(fn ($i) => '• '.$i['title'].' — '.$i['location'].' — '.($i['price_from'] ? 'from ' : '').Format::inr($i['price']).$i['price_suffix']."\n  ".$i['url'])->implode("\n");
            Notify::send('saved_search_match', [$s->customer->email], [
                'name' => $s->customer->name, 'query' => $s->query_text ?: $s->label(), 'list' => $list,
                'link' => route('market.search', PropertySearch::toQuery(PropertySearch::clean($s->filters ?? []))),
            ]);
            foreach ($new as $i) {
                DB::table('saved_search_matches')->insertOrIgnore(['saved_search_id' => $s->id, 'item_key' => $i['key'], 'notified_at' => now()]);
            }
            $s->update(['last_notified_at' => now()]);
        }
    }
}
