{{-- Limit / Used / Left over / % used per plan limit --}}
<div class="table-wrap">
    <table class="tbl">
        <thead><tr><th>Limit</th><th class="num">Plan limit</th><th class="num">Used</th><th class="num">Left over</th><th class="w-1/3">% used</th></tr></thead>
        <tbody>
        @foreach ($usage as $key => $u)
            <tr>
                <td class="font-semibold">{{ $u['label'] }}</td>
                <td class="num">{{ $u['display']['limit'] }}</td>
                <td class="num">{{ $u['display']['used'] }}</td>
                <td class="num">{{ $u['display']['left'] }}</td>
                <td>
                    @if ($u['unlimited'])<span class="text-xs text-muted">Unlimited</span>
                    @else
                        <div class="flex items-center gap-2"><x-progress :pct="$u['pct']" class="flex-1" :label="$u['label'].' used'" /><span class="w-12 text-right text-xs font-semibold tabular-nums">{{ $u['pct'] }}%</span></div>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
