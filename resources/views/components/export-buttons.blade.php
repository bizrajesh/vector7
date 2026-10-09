@props(['excel' => true, 'pdf' => false])
@php($q = request()->query())
@if ($excel)<a class="btn-light" href="{{ request()->url().'?'.http_build_query(array_merge($q, ['export' => 'xlsx'])) }}">{!! \App\Support\Icons::svg('download', 'h-4 w-4') !!} Excel</a>@endif
@if ($pdf)<a class="btn-light" href="{{ request()->url().'?'.http_build_query(array_merge($q, ['export' => 'pdf'])) }}">{!! \App\Support\Icons::svg('printer', 'h-4 w-4') !!} PDF</a>@endif
