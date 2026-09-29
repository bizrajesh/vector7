<x-layouts.app :title="$def['title']">
    <x-page-header title="Settings" :subtitle="$def['title']" />
    @include('app.settings._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="table min-w-[560px]">
                <thead><tr>@foreach ($def['fields'] as $label)<th class="first:pl-4">{{ $label }}</th>@endforeach<th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($def['fields'] as $field => $label)
                            <td class="first:pl-4">
                                @php $value = $row->{$field}; @endphp
                                @if ($field === 'pan') {{ $value ? substr($value, 0, 2).'XXXXX'.substr($value, -3) : '—' }}
                                @elseif (is_bool($value)) {{ $value ? 'Yes' : 'No' }}
                                @elseif ($value instanceof \Carbon\CarbonInterface) {{ $value->format('j M Y') }}
                                @else {{ $value ?? '—' }} @endif
                            </td>
                        @endforeach
                        <td class="text-right">
                            <form method="POST" action="{{ route('app.settings.masters.destroy', [$def['type'], $row->id]) }}" data-confirm="Remove this entry?">@csrf @method('DELETE')
                                <button class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-700" aria-label="Remove"><x-icon name="trash" class="h-4 w-4" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($def['fields']) + 1 }}" class="pl-4 text-ink-muted">Nothing added yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('app.settings.masters.store', $def['type']) }}" class="card-pad flex flex-col gap-3">
            @csrf
            <h2 class="section-title">Add</h2>
            @foreach ($def['fields'] as $field => $label)
                @if ($field === 'unit')
                    <x-select name="unit" :label="$label" :options="['sqft' => 'Square feet', 'rft' => 'Running feet', 'nos' => 'Numbers', 'lumpsum' => 'Lump sum']" />
                @elseif ($field === 'deduct_from_sellable')
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="deduct_from_sellable" value="0"><input type="checkbox" name="deduct_from_sellable" value="1" class="check"> {{ $label }}</label>
                @elseif ($field === 'holiday_date')
                    <x-field name="holiday_date" type="date" :label="$label" required />
                @else
                    <x-field :name="$field" :label="$label" :type="in_array($field, ['unit_cost', 'commission_pct']) ? 'number' : ($field === 'email' ? 'email' : 'text')" step="0.01" />
                @endif
            @endforeach
            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
