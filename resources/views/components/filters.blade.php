@props(['action' => null])
<form method="GET" action="{{ $action ?? url()->current() }}" class="card card-pad mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 items-end" role="search">
    {{ $slot }}
    <div class="flex gap-2">
        <button class="btn-primary" type="submit">{!! \App\Support\Icons::svg('filter', 'h-4 w-4') !!} Filter</button>
        <a href="{{ $action ?? url()->current() }}" class="btn-ghost">Reset</a>
    </div>
</form>
