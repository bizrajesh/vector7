<x-layouts.workspace :title="$lead->name">
    @php($canEdit = auth()->user()->hasPerm('leads.update'))
    <x-page-header :title="$lead->name" :subtitle="$lead->lead_code.' · '.$lead->sourceLabel().' · created '.\App\Support\Format::datetime($lead->created_at)" :back="route('ws.leads.index')">
        <span class="badge-{{ $lead->stage?->color ?? 'gray' }}">{{ $lead->stage?->name }}</span>
        <span class="{{ ['hot' => 'badge-red', 'warm' => 'badge-amber', 'cold' => 'badge-blue'][$lead->priority] ?? 'badge-gray' }}">{{ \App\Models\Lead::PRIORITIES[$lead->priority] ?? '' }}</span>
    </x-page-header>

    @if (session('ai_result'))
        @php($ai = session('ai_result'))
        <div class="card card-pad mb-6 ring-2 ring-teal-100">
            <p class="flex items-center gap-2 font-bold"><x-icon name="sparkles" class="h-4 w-4 text-teal-700" /> {{ ['summary' => 'AI summary', 'next' => 'Suggested next step', 'draft' => 'Draft message'][$ai['action']] }}</p>
            <div id="ai-text" class="mt-2 whitespace-pre-line text-sm">{{ $ai['text'] }}</div>
            @if ($ai['action'] === 'draft')
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn-light btn-sm" data-copy="ai-text"><x-icon name="copy" class="h-4 w-4" /> Copy</button>
                    @if ($ai['channel'] === 'whatsapp' && $lead->mobile)<a class="btn-teal btn-sm" href="{{ route('ws.leads.whatsapp', [$lead, 'text' => $ai['text']]) }}" target="_blank" rel="noopener">Open in WhatsApp</a>@endif
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Quick actions --}}
            <div class="card card-pad">
                <div class="flex flex-wrap gap-2">
                    @if ($canEdit && $lead->mobile)
                        <a href="{{ route('ws.leads.call', $lead) }}" class="btn-primary"><x-icon name="phone" class="h-4 w-4" /> Call {{ $lead->mobile }}</a>
                        <details class="relative" data-dropdown>
                            <summary class="btn-teal list-none cursor-pointer"><x-icon name="chat" class="h-4 w-4" /> WhatsApp</summary>
                            <div class="absolute left-0 z-20 mt-2 w-72 space-y-1 rounded-xl bg-white p-3 shadow-lift ring-1 ring-navy-50">
                                <a href="{{ route('ws.leads.whatsapp', $lead) }}" target="_blank" rel="noopener" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-page">Open chat (no template)</a>
                                @foreach ($templates as $t)
                                    <a href="{{ route('ws.leads.whatsapp', [$lead, 'template' => $t->id]) }}" target="_blank" rel="noopener" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-page">{{ $t->name }}</a>
                                @endforeach
                                <p class="px-2 pt-1 text-xs text-muted">Opening WhatsApp is recorded on the timeline.</p>
                            </div>
                        </details>
                    @endif
                    @if ($canEdit && $lead->email)
                        <details class="relative" data-dropdown>
                            <summary class="btn-light list-none cursor-pointer"><x-icon name="mail" class="h-4 w-4" /> Email</summary>
                            <form method="POST" action="{{ route('ws.leads.email', $lead) }}" class="absolute left-0 z-20 mt-2 grid w-[min(92vw,26rem)] gap-2 rounded-xl bg-white p-4 shadow-lift ring-1 ring-navy-50">
                                @csrf
                                <x-field name="subject" label="Subject" required id="em_subject" />
                                <x-textarea name="body" label="Message" rows="5" required id="em_body" />
                                <button class="btn-primary btn-sm">Send email</button>
                            </form>
                        </details>
                    @endif
                    @if ($canEdit)
                        <details class="relative" data-dropdown>
                            <summary class="btn-light list-none cursor-pointer"><x-icon name="calendar" class="h-4 w-4" /> Schedule</summary>
                            <form method="POST" action="{{ route('ws.leads.followup', $lead) }}" class="absolute left-0 z-20 mt-2 grid w-[min(92vw,22rem)] gap-2 rounded-xl bg-white p-4 shadow-lift ring-1 ring-navy-50">
                                @csrf
                                <x-select name="kind" label="What" :options="\App\Models\LeadFollowup::KINDS" id="fu_kind" />
                                <x-field name="due_at" type="datetime-local" label="When" :value="now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i')" required id="fu_due" />
                                <x-select name="assigned_to" label="Who" :options="$staff" :value="$lead->assigned_to ?? auth()->id()" id="fu_who" />
                                <x-field name="notes" label="Notes" id="fu_notes" />
                                <button class="btn-primary btn-sm">Schedule</button>
                            </form>
                        </details>
                        <details class="relative" data-dropdown>
                            <summary class="btn-light list-none cursor-pointer"><x-icon name="edit" class="h-4 w-4" /> Log / note</summary>
                            <form method="POST" action="{{ route('ws.leads.activity', $lead) }}" class="absolute left-0 z-20 mt-2 grid w-[min(92vw,22rem)] gap-2 rounded-xl bg-white p-4 shadow-lift ring-1 ring-navy-50">
                                @csrf
                                <x-select name="type" label="Type" :options="['note' => 'Note', 'call' => 'Call made', 'whatsapp' => 'WhatsApp sent', 'email' => 'Email sent']" id="ac_type" />
                                <x-textarea name="body" label="Details" rows="3" required id="ac_body" />
                                <button class="btn-primary btn-sm">Add</button>
                            </form>
                        </details>
                    @endif
                    @if ($canEdit && auth()->user()->hasPerm('bookings.create') && $bookablePlots->isNotEmpty())
                        @foreach ($bookablePlots as $i)
                            <a href="{{ route('ws.leads.convert', [$lead, 'plot' => $i->plot_id]) }}" class="btn-primary"><x-icon name="cart" class="h-4 w-4" /> Book Plot {{ $i->plot->plot_no }}</a>
                        @endforeach
                    @endif
                </div>
                @if ($canEdit && $ai)
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-navy-50 pt-3 text-sm">
                        <span class="flex items-center gap-1 font-semibold text-teal-800"><x-icon name="sparkles" class="h-4 w-4" /> AI help:</span>
                        <form method="POST" action="{{ route('ws.leads.ai', [$lead, 'summary']) }}">@csrf<button class="btn-ghost btn-sm">Summarise this lead</button></form>
                        <form method="POST" action="{{ route('ws.leads.ai', [$lead, 'next']) }}">@csrf<button class="btn-ghost btn-sm">Suggest next step</button></form>
                        <form method="POST" action="{{ route('ws.leads.ai', [$lead, 'draft']) }}" class="flex items-center gap-1">@csrf
                            <select name="channel" class="input py-1 text-xs" aria-label="Channel"><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select>
                            <select name="language" class="input py-1 text-xs" aria-label="Language"><option value="en">English</option><option value="ta">தமிழ்</option></select>
                            <button class="btn-ghost btn-sm">Draft follow-up</button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Follow-ups --}}
            @php($open = $lead->followups->whereNull('done_at'))
            @if ($open->isNotEmpty())
                <div class="card">
                    <h2 class="section-title p-4">Upcoming</h2>
                    <ul class="divide-y divide-navy-50">
                        @foreach ($open as $f)
                            <li class="flex flex-wrap items-center justify-between gap-2 p-4">
                                <div>
                                    <p class="font-semibold {{ $f->due_at->isPast() ? 'text-red-700' : '' }}">{{ \App\Models\LeadFollowup::KINDS[$f->kind] }} · {{ \App\Support\Format::datetime($f->due_at) }}</p>
                                    <p class="text-xs text-muted">{{ $f->assignee?->name ?? 'Unassigned' }}{{ $f->notes ? ' · '.$f->notes : '' }}</p>
                                </div>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('ws.leads.followup.done', [$lead, $f]) }}" class="flex gap-2">@csrf
                                        <input name="outcome" class="input py-1 text-sm" placeholder="Outcome (optional)" aria-label="Outcome">
                                        <button class="btn-light btn-sm"><x-icon name="check" class="h-4 w-4" /> Done</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Timeline --}}
            <div class="card">
                <h2 class="section-title p-4">Activity timeline</h2>
                <ol class="space-y-0 px-4 pb-4">
                    @foreach ($lead->activities as $a)
                        <li class="relative border-l-2 border-navy-50 pb-4 pl-4">
                            <span class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full {{ in_array($a->type, ['call', 'whatsapp', 'email']) ? 'bg-teal' : (in_array($a->type, ['stage', 'assigned']) ? 'bg-navy' : 'bg-amber-500') }}" aria-hidden="true"></span>
                            <p class="text-sm font-semibold">{{ $a->label() }} <span class="font-normal text-muted">· {{ \App\Support\Format::datetime($a->created_at) }} · {{ $a->actor_name ?? 'System' }}</span></p>
                            @if ($a->body)<p class="mt-0.5 whitespace-pre-line text-sm">{{ $a->body }}</p>@endif
                            @if (! empty($a->meta['interest']))<p class="text-xs text-muted">{{ $a->meta['interest'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Stage --}}
            @if ($canEdit)
                <form method="POST" action="{{ route('ws.leads.stage', $lead) }}" class="card card-pad space-y-3" id="stage-form">
                    @csrf
                    <h2 class="section-title">Stage</h2>
                    <x-select name="stage_id" label="Move to" :options="$stages->pluck('name', 'id')" :value="request('lost') ? $stages->firstWhere('is_lost', true)?->id : $lead->lead_stage_id" />
                    <div data-show-when="stage_id={{ $stages->firstWhere('is_lost', true)?->id }}" class="space-y-3">
                        <x-select name="lost_reason" label="Lost reason" :options="\App\Models\Lead::LOST_REASONS" placeholder="Choose…" :value="$lead->lost_reason" />
                        <x-field name="lost_notes" label="Notes" :value="$lead->lost_notes" />
                    </div>
                    <button class="btn-primary">Update stage</button>
                </form>
                <form method="POST" action="{{ route('ws.leads.assign', $lead) }}" class="card card-pad space-y-3">
                    @csrf
                    <h2 class="section-title">Assigned to</h2>
                    @if (auth()->user()->role?->base_role === 'tenant_sales')
                        <p class="text-sm">{{ $lead->assignee?->name ?? 'Unassigned' }}</p>
                        @unless ($lead->assigned_to)<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><button class="btn-light btn-sm">Take this lead</button>@endunless
                    @else
                        <x-select name="assigned_to" :options="$staff" :value="$lead->assigned_to" placeholder="Unassigned" aria-label="Assigned to" />
                        <button class="btn-light btn-sm">Save</button>
                    @endif
                </form>
            @endif

            {{-- Details --}}
            <form method="POST" action="{{ route('ws.leads.update', $lead) }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="section-title">Details</h2>
                <x-field name="name" label="Name" :value="$lead->name" required />
                <x-field name="mobile" label="Mobile" :value="$lead->mobile" inputmode="numeric" maxlength="10" />
                <x-field name="email" type="email" label="Email" :value="$lead->email" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="budget" type="number" label="Budget (₹)" :value="$lead->budget ? (int) $lead->budget : null" min="0" />
                    <x-select name="priority" label="Priority" :options="\App\Models\Lead::PRIORITIES" :value="$lead->priority" />
                </div>
                <x-field name="preferred_location" label="Preferred location" :value="$lead->preferred_location" />
                <x-field name="tags" label="Tags (comma separated)" :value="implode(', ', $lead->tags ?? [])" />
                <x-textarea name="notes" label="Requirement notes" :value="$lead->notes" rows="3" />
                <p class="text-xs text-muted">Consent to contact: <strong>{{ $lead->consent ? 'Yes' : 'Not recorded' }}</strong>@if ($lead->customer) · Registered buyer @endif</p>
                @if ($canEdit)<button class="btn-primary btn-sm">Save details</button>@endif
            </form>

            {{-- Interests --}}
            <div class="card card-pad">
                <h2 class="section-title">Interested in</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($lead->interests as $i)
                        <li>
                            @if ($i->listing)<a href="{{ route('ws.listings.show', $i->listing) }}">{{ $i->label() }}</a>
                            @elseif ($i->project)<a href="{{ route('ws.launch.show', $i->project) }}">{{ $i->label() }}</a>@if ($i->plot) <x-status :status="$i->plot->status" class="ml-1" />@endif
                            @else {{ $i->label() }} @endif
                        </li>
                    @empty
                        <li class="text-muted">Nothing recorded yet.</li>
                    @endforelse
                </ul>
                @if ($canEdit)
                    <form method="POST" action="{{ route('ws.leads.interest', $lead) }}" class="mt-3 flex gap-2">@csrf
                        <x-select name="interest" :options="$interestOptions" placeholder="Add listing / layout…" aria-label="Add interest" class="flex-1" />
                        <button class="btn-light btn-sm">Add</button>
                    </form>
                @endif
            </div>

            @can('leads.delete')
                <x-confirm :action="route('ws.leads.destroy', $lead)" method="DELETE" message="Delete this lead and its timeline?" class="btn-ghost btn-sm text-red-700">Delete lead</x-confirm>
            @endcan
        </div>
    </div>
</x-layouts.workspace>
