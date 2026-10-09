<x-layouts.workspace title="Set up your workspace">
    <x-page-header title="Set up your workspace" :subtitle="'Tenant ID '.$tenant->code.' · four short steps'" />
    @include('partials.import-errors')
    @php($steps = [1 => 'Organisation profile', 2 => 'Pre-configuration template', 3 => 'Validate & import', 4 => 'Invite users'])
    <ol class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($steps as $n => $label)
            <li class="card flex items-center gap-3 p-4 {{ $step === $n ? 'ring-2 ring-teal' : '' }}">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $step > $n ? 'bg-teal-700 text-white' : ($step === $n ? 'bg-navy text-white' : 'bg-page text-muted') }}">{!! $step > $n ? \App\Support\Icons::svg('check', 'h-4 w-4') : $n !!}</span>
                <span class="text-sm font-semibold">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('ws.setup.profile') }}" class="card card-pad grid gap-3 sm:grid-cols-2">
            @csrf
            <h2 class="section-title sm:col-span-2">1. Organisation profile</h2>
            <x-field name="name" label="Name or company name" :value="$tenant->name" required class="sm:col-span-2" />
            <x-field name="address_line1" label="Address line 1" :value="$tenant->address_line1" required class="sm:col-span-2" />
            <x-field name="address_line2" label="Address line 2" :value="$tenant->address_line2" />
            <x-field name="village" label="Village" :value="$tenant->village" />
            <x-field name="city" label="City / town" :value="$tenant->city" required />
            <x-field name="district" label="District" :value="$tenant->district" required />
            <x-select name="state" label="State" :options="array_combine(\App\Services\TemplateImporter::STATES, \App\Services\TemplateImporter::STATES)" :value="$tenant->state" />
            <x-field name="pin" label="PIN code" :value="$tenant->pin" required inputmode="numeric" maxlength="6" />
            <x-field name="contact" label="Contact number" :value="$tenant->contact" required inputmode="numeric" maxlength="10" />
            <x-field name="support_email" type="email" label="Support email" :value="$tenant->support_email" />
            <div class="sm:col-span-2"><button class="btn-primary">Save profile</button></div>
        </form>

        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="section-title">2–3. Load your masters</h2>
                <p class="mt-1 text-sm text-muted">Masters are your approval stages, sub-tasks, facility costs, document checklist, instalment plan and notification groups. Download the template, fill the yellow cells, then upload it. Everything is checked first — nothing is imported if any row has an error.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('ws.template.download') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Download template</a>
                    <x-confirm :action="route('ws.setup.defaults')" message="Copy the vector7 default masters (Tamil Nadu 2026 rates) into your workspace? This replaces any masters you already have." class="btn-teal">Use App defaults</x-confirm>
                </div>
                <form method="POST" action="{{ route('ws.setup.template') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-field name="file" type="file" label="Upload filled template (.xlsx)" accept=".xlsx" required class="flex-1" />
                    <button class="btn-primary"><x-icon name="upload" class="h-4 w-4" /> Validate & import</button>
                </form>
                @if ($hasMasters)<p class="mt-3 text-sm font-semibold text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 inline') !!} Masters loaded.</p>@endif
            </div>
            <div class="card card-pad">
                <h2 class="section-title">4. Invite your team</h2>
                <p class="mt-1 text-sm text-muted">Add managers, sales and accounts staff. They receive a set-password link, or you can generate a password and share it.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('ws.iam.create') }}" class="btn-light"><x-icon name="plus" class="h-4 w-4" /> Add a user</a>
                    <form method="POST" action="{{ route('ws.setup.finish') }}">@csrf<button class="btn-primary">Finish setup</button></form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.workspace>
