@php
    // E-posta istemcileri (Outlook dahil) için tablo düzeni ve satır içi stil; görseller e-postaya gömülür.
    $t = fn (string $key, array $replace = []) => __('letter.'.$key, $replace, $lang);
    $img = fn (string $file) => isset($message) ? $message->embed(public_path('img/letter/'.$file)) : asset('img/letter/'.$file);
    $gold = '#b3a486';
    $serif = "'Times New Roman', Times, serif";
    $century = "'Century Schoolbook', 'Century', 'Times New Roman', serif";
    $gothic = "'Century Gothic', 'URW Gothic', Arial, sans-serif";
    $rows = [
        ['guest_name', $guest],
        ['reservation_number', $r->confirmation_number],
        ['check_in_date', $r->arrival_date?->format('d.m.Y')],
        ['check_out_date', $r->departure_date?->format('d.m.Y')],
        ['length_of_stay', trans_choice('letter.nights', $r->nights, [], $lang)],
        ['room_type', $r->roomType?->name],
        ['number_of_guests', $guests],
        ['room_rate', $rate],
        ['booked_by', $bookedBy],
        ['special_requests', $r->guest_comments],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t('title_1') }} {{ $t('title_2') }}</title>
</head>
<body style="margin:0; padding:0; background:#eeeeee;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eeeeee;">
<tr><td align="center" style="padding:16px 8px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px; max-width:100%; background:#ffffff; font-family:{{ $serif }}; font-size:15px; line-height:1.3; color:#000000;">
    <tr>
        <td style="background:#f4f3ea; padding:0; width:258px;" valign="top"><img src="{{ $img('header-left.png') }}" width="258" alt="Rocks Hotel &amp; Casino" style="display:block; width:258px; height:auto; border:0;"></td>
        <td style="background:#f4f3ea; padding:0 28px 0 0; text-align:right;" valign="middle">
            <div style="font-family:{{ $gothic }}; font-size:30px; font-weight:bold; line-height:1; color:{{ $gold }};">{{ $t('title_1') }}</div>
            <div style="font-family:'Calibri', Arial, sans-serif; font-size:17px; color:#1f1f1f; margin-top:2px;">{{ $t('title_2') }}</div>
        </td>
    </tr>
    <tr><td colspan="2" style="padding:24px 28px 0; text-align:center; line-height:1.15;">
        <div style="font-size:17px; font-weight:bold; color:{{ $gold }};">{{ $t('hotel_line') }}</div>
        <div style="font-size:13px;"><b style="color:{{ $gold }};">Tel:</b> +90 392 650 0400 <b style="color:{{ $gold }};">Fax:</b> +90 392 650 0404</div>
        <div style="font-size:13px;"><b style="color:{{ $gold }};">E-Mail:</b> info@rockshotel.com <b style="color:{{ $gold }};">Homepage:</b> www.rockshotel.com</div>
    </td></tr>
    <tr><td colspan="2" style="padding:18px 28px 0;">
        <p style="margin:0;">{{ $t('dear', ['name' => $guest]) }}<br>{{ $t('intro') }}</p>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px 0 0 14px; font-size:15px;">
            @foreach ($rows as [$key, $value])
                <tr>
                    <td style="width:178px; padding:1px 0; font-family:{{ $century }}; font-weight:bold;" valign="top">{{ $t($key) }}@if ($key === 'room_rate' && $t('per_night') !== '') <i>{{ $t('per_night') }}</i>@endif</td>
                    <td style="width:10px; padding:1px 0; font-weight:bold;" valign="top">:</td>
                    <td style="padding:1px 0;" valign="top">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
        <p style="margin:16px 0 0;">{{ $t('guarantee_1') }}</p>
        <p style="margin:16px 0 0;">{{ $t('guarantee_2') }}</p>
        <p style="margin:16px 0 0; text-align:center; font-family:{{ $century }}; color:{{ $gold }};">{{ $t('times') }}</p>
        <p style="margin:16px 0 0;">{{ $t('relations') }}<br>
            &#128222; +90 392 650 04 00 &nbsp;&nbsp;|&nbsp;&nbsp; &#9993; <a href="mailto:guestrelations@rockshotel.com" style="color:#000000; text-decoration:none;">guestrelations@rockshotel.com</a></p>
        <p style="margin:16px 0 0; font-family:{{ $century }}; font-weight:bold; color:{{ $gold }};">{{ $t('thanks') }}</p>
        <p style="margin:16px 0 0; text-align:right;">{{ $t('regards') }}<br>{{ $t('department') }}<br>{{ $t('signature') }}</p>
        <p style="margin:8px 0 24px; text-align:right;"><img src="{{ $img('signature.jpg') }}" width="180" alt="" style="width:180px; height:auto; border:0;"></p>
    </td></tr>
    <tr><td colspan="2" style="background:#f4f3ea; padding:7px 28px; font-family:{{ $gothic }}; font-size:12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:{{ $gothic }}; font-size:12px;"><tr>
            <td><img src="{{ $img('phone.png') }}" width="12" alt="" style="vertical-align:middle; border:0;"> H · 0392 650 04 00 &nbsp;C · 0392 650 04 44</td>
            <td style="text-align:right;"><img src="{{ $img('location.png') }}" width="9" alt="" style="vertical-align:middle; border:0;"> 20 Temmuz Kordonboyu Cad. Girne / K.K.T.C.</td>
        </tr></table>
    </td></tr>
    <tr><td colspan="2" style="background:#231f20; padding:8px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:{{ $gothic }}; font-size:12px; color:#ffffff;"><tr>
            <td><img src="{{ $img('web.png') }}" width="12" alt="" style="vertical-align:middle; border:0;"> <a href="https://www.rockshotel.com" style="color:#ffffff; text-decoration:none;">www.rockshotel.com</a></td>
            <td style="text-align:right;"><img src="{{ $img('instagram.png') }}" width="12" alt="" style="vertical-align:middle; border:0;"> <img src="{{ $img('facebook.png') }}" width="12" alt="" style="vertical-align:middle; border:0;"> rockshotel</td>
        </tr></table>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
