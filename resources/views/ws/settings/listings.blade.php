{{-- Tenant Settings → Listings & leads (spec 17.11) --}}
<div class="grid gap-6 xl:grid-cols-2">
    <form method="POST" action="{{ route('ws.settings.listings') }}" class="card card-pad grid gap-4 sm:grid-cols-2">
        @csrf @method('PUT')
        <div class="sm:col-span-2">
            <h2 class="section-title">Default listing contact</h2>
            <p class="text-sm text-muted">Pre-filled on every new listing and quick-launch layout; you can change it per listing. Buyers only see buttons for the modes you tick.</p>
        </div>
        <x-field name="contact_name" label="Contact person" :value="$settings->contact_name" required />
        <x-field name="contact_mobile" label="Mobile" :value="$settings->contact_mobile" required inputmode="numeric" maxlength="10" />
        <x-field name="contact_whatsapp" label="WhatsApp number" :value="$settings->contact_whatsapp" hint="Leave blank to use the mobile number." inputmode="numeric" maxlength="10" />
        <x-field name="contact_email" type="email" label="Email" :value="$settings->contact_email" />
        <fieldset class="sm:col-span-2">
            <legend class="label">Preferred modes of contact</legend>
            <div class="flex flex-wrap gap-4">
                <input type="hidden" name="allow_call" value="0"><x-checkbox name="allow_call" label="Call" :checked="$settings->allow_call" />
                <input type="hidden" name="allow_whatsapp" value="0"><x-checkbox name="allow_whatsapp" label="WhatsApp message" :checked="$settings->allow_whatsapp" />
                <input type="hidden" name="allow_email" value="0"><x-checkbox name="allow_email" label="Email (enquiry form)" :checked="$settings->allow_email" />
            </div>
            @error('allow_call')<p class="error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="sm:col-span-2 border-t border-navy-50 pt-4">
            <h2 class="section-title">Leads</h2>
        </div>
        <div class="sm:col-span-2">
            <input type="hidden" name="lead_auto_assign" value="0">
            <x-checkbox name="lead_auto_assign" label="Assign new leads automatically (round-robin among active Sales users)" :checked="$settings->lead_auto_assign"
                :hint="$salesUsers->isEmpty() ? 'No active Sales users yet — leads stay unassigned.' : 'Sales users: '.$salesUsers->implode(', ')" />
        </div>
        <div class="sm:col-span-2 border-t border-navy-50 pt-4"><h2 class="section-title">Images & assistant</h2></div>
        <input type="hidden" name="image_watermark" value="0"><x-checkbox name="image_watermark" label="Add a small “vector7” watermark to uploaded photos" :checked="$settings->image_watermark" />
        <input type="hidden" name="workspace_bot_enabled" value="0"><x-checkbox name="workspace_bot_enabled" label="Workspace assistant (AI chat) on for this workspace" :checked="$settings->workspace_bot_enabled" />
        @unless ($ro)<div class="sm:col-span-2"><button class="btn-primary">Save</button></div>@endunless
    </form>

    <div class="space-y-6">
        <form method="POST" action="{{ route('ws.settings.stages') }}" class="card card-pad">
            @csrf @method('PUT')
            <h2 class="section-title">Lead pipeline stages</h2>
            <p class="text-sm text-muted">Rename or reorder the stages shown on the leads board. New, Won and Lost always exist.</p>
            <div class="table-wrap mt-3"><table class="tbl">
                <thead><tr><th class="w-20">Order</th><th>Stage</th><th class="num">Leads</th><th></th></tr></thead>
                <tbody>
                @foreach ($stages as $st)
                    <tr>
                        <td><input class="input w-16" type="number" name="stages[{{ $st->id }}][sort]" value="{{ $st->sort }}" min="0" max="99" aria-label="Order of {{ $st->name }}" @disabled($ro)></td>
                        <td><input class="input" name="stages[{{ $st->id }}][name]" value="{{ $st->name }}" maxlength="40" aria-label="Name of stage {{ $st->key }}" @disabled($ro)>
                            @if ($st->is_won)<span class="badge-teal mt-1">Won</span>@elseif ($st->is_lost)<span class="badge-gray mt-1">Lost</span>@endif</td>
                        <td class="num">{{ $st->leads_count }}</td>
                        <td class="text-right">@if (! $ro && ! $st->is_won && ! $st->is_lost && $st->key !== 'new' && ! $st->leads_count)<button type="submit" form="del-stage-{{ $st->id }}" class="btn-ghost btn-sm">Remove</button>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @unless ($ro)
                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <x-field name="new_name" label="Add a stage (before Won)" placeholder="e.g. Loan processing" />
                    <button class="btn-primary">Save stages</button>
                </div>
            @endunless
        </form>
        @foreach ($stages as $st)
            <form id="del-stage-{{ $st->id }}" method="POST" action="{{ route('ws.settings.stages.destroy', $st) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach

        <div class="card card-pad">
            <h2 class="section-title">WhatsApp message templates</h2>
            <p class="text-sm text-muted">Used by the WhatsApp quick action on a lead. Placeholders: <code>{name}</code> <code>{listing}</code> <code>{user}</code> <code>{promoter}</code>.</p>
            <ul class="mt-3 space-y-3">
                @foreach ($templates as $t)
                    <li>
                        <form method="POST" action="{{ route('ws.settings.templates.update', $t) }}" class="space-y-2 rounded-xl bg-page p-3">
                            @csrf @method('PUT')
                            <input class="input" name="name" value="{{ $t->name }}" maxlength="60" aria-label="Template name" @disabled($ro)>
                            <textarea class="input" name="body" rows="2" maxlength="1000" aria-label="Template text" @disabled($ro)>{{ $t->body }}</textarea>
                            @unless ($ro)<div class="flex gap-2"><button class="btn-light btn-sm">Save</button><button type="submit" form="del-tpl-{{ $t->id }}" class="btn-ghost btn-sm text-red-700">Delete</button></div>@endunless
                        </form>
                        <form id="del-tpl-{{ $t->id }}" method="POST" action="{{ route('ws.settings.templates.destroy', $t) }}" class="hidden">@csrf @method('DELETE')</form>
                    </li>
                @endforeach
            </ul>
            @unless ($ro)
                <form method="POST" action="{{ route('ws.settings.templates.store') }}" class="mt-4 space-y-2 border-t border-navy-50 pt-4">
                    @csrf
                    <x-field name="name" label="New template name" id="tpl_new_name" required />
                    <x-textarea name="body" label="Message" id="tpl_new_body" rows="2" required />
                    <button class="btn-primary btn-sm">Add template</button>
                </form>
            @endunless
        </div>
    </div>
</div>
