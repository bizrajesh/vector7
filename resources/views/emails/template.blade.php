<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>vector7</title></head>
<body style="margin:0;padding:0;background:#EEF1F4;font-family:'Plus Jakarta Sans',Segoe UI,Arial,sans-serif;color:#0B1B33;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF1F4;padding:24px 12px;">
<tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
        <tr><td style="padding:24px 28px;border-bottom:1px solid #E8EBF0;">
            @if ($footer && $footer['logo'] && is_file($footer['logo']))
                <img src="{{ $message->embed($footer['logo']) }}" alt="{{ $footer['name'] }}" height="44" style="height:44px;width:auto;">
            @else
                <img src="{{ $message->embed(public_path('images/brand/logo-light.png')) }}" alt="vector7" height="44" style="height:44px;width:auto;">
            @endif
        </td></tr>
        <tr><td style="padding:28px;font-size:15px;line-height:1.6;color:#0B1B33;">{!! $html !!}</td></tr>
        <tr><td style="padding:20px 28px;background:#0B1B33;color:#C9D0DB;font-size:12px;line-height:1.6;">
            @if ($footer)
                <strong style="color:#ffffff;">{{ $footer['name'] }}</strong><br>
                {{ $footer['address'] }}<br>
                @if ($footer['contact']) Phone {{ $footer['contact'] }} · @endif {{ $footer['email'] }}<br>
                <span style="color:#8ACBC5;">Sent via vector7 · Realty Manage Portal</span>
            @else
                <strong style="color:#ffffff;">{{ \App\Services\AppSettings::get('org.name') }}</strong> · <span style="color:#8ACBC5;">REALTY MANAGE PORTAL</span><br>
                {{ \App\Services\AppSettings::get('org.address') }} · {{ \App\Services\AppSettings::get('org.support_email') }}
            @endif
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
