{{-- Vars: $k (limit key), $v (value) --}}{{ $v < 0 ? 'Unlimited' : ($k === 'storage_mb' ? \App\Support\Format::bytes($v * 1048576) : \App\Support\Format::num($v)) }}
