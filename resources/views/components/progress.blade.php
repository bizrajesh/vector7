@props(['pct' => 0, 'level' => null, 'label' => null])
@php($pct = max(0, min(100, (float) $pct)))
@php($level = $level ?? \App\Services\PlanLimiter::level($pct))
<div {{ $attributes }}>
    <div class="bar {{ $level === 'teal' ? '' : $level }}" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" @if ($label) aria-label="{{ $label }}" @endif><span data-w="{{ $pct }}"></span></div>
</div>
