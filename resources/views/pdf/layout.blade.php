<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $docTitle ?? 'vector7' }}</title>
<style>
    @page { margin: 28px 32px 48px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #0B1B33; line-height: 1.45; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 16px 0 6px 0; color: #0B1B33; border-bottom: 2px solid #0F8F84; padding-bottom: 3px; }
    h3 { font-size: 11px; margin: 10px 0 4px 0; }
    .muted { color: #5B6677; }
    .small { font-size: 9px; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .teal { color: #0B746B; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0B1B33; color: #fff; text-align: left; padding: 5px 6px; font-size: 9.5px; }
    td { padding: 5px 6px; border-bottom: 1px solid #E8EBF0; vertical-align: top; }
    .kv td { border: none; padding: 2px 6px 2px 0; }
    .kv td.k { color: #5B6677; width: 34%; }
    .box { border: 1px solid #C9D0DB; border-radius: 6px; padding: 8px 10px; margin-top: 8px; }
    .total td { font-weight: bold; border-top: 2px solid #0B1B33; }
    .header { border-bottom: 3px solid #0B1B33; padding-bottom: 8px; margin-bottom: 12px; }
    .header td { border: none; padding: 0; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #E7F5F3; color: #095C55; font-weight: bold; font-size: 9px; }
    .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #5B6677; border-top: 1px solid #E8EBF0; padding-top: 4px; }
    .sign td { border: none; padding-top: 42px; }
    .disclaimer { font-size: 8.5px; color: #5B6677; margin-top: 12px; border-top: 1px dashed #C9D0DB; padding-top: 6px; }
</style>
</head>
<body>
@php
    $brandLogo = public_path('images/brand/logo-light.png');
    $tenantLogo = isset($tenant) && $tenant?->logo_path && is_file(storage_path('app/private/'.$tenant->logo_path)) ? storage_path('app/private/'.$tenant->logo_path) : null;
@endphp
<table class="header"><tr>
    <td style="width:60%">
        @if ($tenantLogo)<img src="{{ $tenantLogo }}" style="height:46px">@else<img src="{{ $brandLogo }}" style="height:40px">@endif
        @isset($tenant)
            <div class="bold" style="margin-top:4px">{{ $tenant->name }}</div>
            <div class="muted small">{{ $tenant->addressLine() }}<br>{{ $tenant->contact }} · {{ $tenant->support_email ?: $tenant->email }}</div>
        @else
            <div class="muted small">{{ \App\Services\AppSettings::get('org.name') }} · {{ \App\Services\AppSettings::get('org.address') }} · {{ \App\Services\AppSettings::get('org.support_email') }}</div>
        @endisset
    </td>
    <td class="right" style="width:40%">
        <h1>{{ $docTitle ?? '' }}</h1>
        @isset($docSub)<div class="muted">{{ $docSub }}</div>@endisset
        <div class="muted small">Printed {{ now()->format('d-m-Y h:i A') }}</div>
    </td>
</tr></table>
@yield('content')
<div class="footer">{{ isset($tenant) ? $tenant->name.' · ' : '' }}Generated with vector7 · Realty Manage Portal</div>
</body>
</html>
