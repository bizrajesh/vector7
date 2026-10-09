<?php

namespace App\Jobs;

use App\Models\CustomerRequirement;
use App\Models\Plot;
use App\Services\Notify;
use App\Services\RequirementSearch;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Email customers when a newly launched plot matches one of their saved requirements (once per plot). */
class MatchRequirements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $projectId = null) {}

    public function handle(): void
    {
        app(Tenancy::class)->withoutScope(function () {
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
        });
    }
}
