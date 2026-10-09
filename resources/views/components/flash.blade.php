<div class="space-y-3" aria-live="polite">
    @if (session('ok'))<div class="flash-ok" role="status">{!! \App\Support\Icons::svg('check', 'h-5 w-5 shrink-0') !!}<span>{{ session('ok') }}</span></div>@endif
    @if (session('error'))
        <div class="flash-err" role="alert">{!! \App\Support\Icons::svg('alert', 'h-5 w-5 shrink-0') !!}<span>{{ session('error') }}
            @if (session('upgrade') && auth('web')->user()?->tenant_id && auth('web')->user()->hasPerm('tenant_settings.view')) <a href="{{ route('ws.settings.plan') }}" class="font-bold underline">Upgrade your plan</a>@endif</span></div>
    @endif
    @if (session('warn'))<div class="flash-warn" role="status">{!! \App\Support\Icons::svg('info', 'h-5 w-5 shrink-0') !!}<span>{{ session('warn') }}</span></div>@endif
    @if ($errors->any() && ! session('error'))
        <div class="flash-err" role="alert">{!! \App\Support\Icons::svg('alert', 'h-5 w-5 shrink-0') !!}<span>Please fix the highlighted fields{{ $errors->count() > 1 ? ' ('.$errors->count().' errors)' : '' }}.</span></div>
    @endif
</div>
