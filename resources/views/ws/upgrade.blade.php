<x-layouts.workspace title="Upgrade your plan">
    <div class="card card-pad mx-auto max-w-xl text-center">
        <div class="mx-auto w-fit rounded-2xl bg-amber-100 p-4 text-amber-800"><x-icon name="lock" class="h-8 w-8" /></div>
        <h1 class="mt-4 text-2xl font-extrabold">{{ $module }} is not in your plan</h1>
        <p class="mt-2 text-muted">Upgrade your plan to unlock this module for your team.</p>
        @if (auth()->user()->hasPerm('tenant_settings.view'))<a href="{{ route('ws.settings.plan') }}" class="btn-primary btn-pill mt-6">See plans</a>@endif
    </div>
</x-layouts.workspace>
