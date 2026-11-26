@php
    $t = fn (string $key, array $replace = []) => __('letter.'.$key, $replace, $lang);
    $img = fn (string $file) => asset('img/letter/'.$file);
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
    <title>{{ $t('title_1') }} {{ $t('title_2') }} — {{ $r->confirmation_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { background: #e5e5e5; color: #000; font-family: "Times New Roman", Times, "Liberation Serif", serif; font-size: 12pt; line-height: 1.1; }
        .page { position: relative; width: 210mm; height: 297mm; margin: 12px auto; background: #fff; overflow: hidden; }
        .header { position: absolute; left: 0; top: 0; width: 100%; height: 113.7pt; display: block; }
        .title { position: absolute; right: 27pt; top: 27pt; text-align: right; }
        .title b { display: block; font-family: "Century Gothic", "URW Gothic", "Avant Garde", "Futura", sans-serif; font-size: 24pt; line-height: 1; color: #b3a486; }
        .title span { display: block; margin-top: 2pt; font-family: "Calibri", "Carlito", "Arial Narrow", sans-serif; font-size: 14pt; color: #1f1f1f; }
        .content { position: absolute; left: 21.24pt; right: 21.24pt; top: 133pt; }
        .hotel { text-align: center; line-height: 1.1; }
        .hotel h1 { margin: 0; font-size: 14pt; font-weight: bold; color: #b3a486; }
        .hotel p { margin: 0; font-size: 11pt; }
        .hotel em { font-style: normal; font-weight: bold; color: #b3a486; }
        .dear { margin: 12pt 0 0; }
        .intro { margin: 0; }
        table.fields { margin: 12pt 0 0 10.8pt; border-collapse: collapse; }
        table.fields td { padding: 0; height: 14.2pt; vertical-align: top; }
        html[lang="tr"] table.fields td { height: 21.6pt; }
        table.fields td.l { width: 134.8pt; font-family: "Century Schoolbook", "New Century Schoolbook", "TeX Gyre Schola", "Century", serif; font-weight: 600; }
        table.fields td.l i { font-weight: 600; }
        table.fields td.c { width: 6pt; font-weight: bold; }
        .para { margin: 13pt 0 0; }
        .para + .para { margin-top: 13pt; }
        .times { margin: 13pt 0 0; text-align: center; color: #b3a486; font-family: "Century Schoolbook", "New Century Schoolbook", "TeX Gyre Schola", "Century", serif; }
        .contact { margin: 4pt 0 0; display: flex; align-items: center; gap: 6pt; }
        .contact .ico { font-family: "Segoe UI Emoji", "Noto Color Emoji", sans-serif; font-size: 10pt; }
        .contact .sep { margin: 0 8pt; }
        .thanks { margin: 13pt 0 0; color: #b3a486; font-family: "Century Schoolbook", "New Century Schoolbook", "TeX Gyre Schola", "Century", serif; font-weight: 600; }
        .regards { margin: 13pt 0 0; text-align: right; }
        .sign { position: absolute; left: 439pt; top: 641pt; width: 137pt; }
        .foot1 { position: absolute; left: 0; right: 0; top: 797pt; height: 22pt; background: #f4f3ea; display: flex; align-items: center; justify-content: space-between; padding: 0 39pt; }
        .foot2 { position: absolute; left: 0; right: 0; top: 819pt; bottom: 0; background: #231f20; color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 39pt; }
        .foot1, .foot2 { font-family: "Century Gothic", "URW Gothic", "Avant Garde", "Futura", sans-serif; font-size: 9pt; }
        .foot1 span, .foot2 span { display: inline-flex; align-items: center; gap: 5pt; }
        .foot1 img, .foot2 img { height: 10pt; width: auto; }
        .bar { position: sticky; top: 0; z-index: 1; display: flex; gap: 6px; justify-content: center; padding: 8px; background: #2b2b2b; font: 13px system-ui, sans-serif; }
        .bar a, .bar button { padding: 4px 12px; border: 1px solid #777; border-radius: 3px; background: #444; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
        .bar a.on { background: #b3a486; border-color: #b3a486; color: #000; }
        .bar kbd { margin-left: 6px; opacity: .7; font-size: 11px; }
        .bar { flex-wrap: wrap; align-items: center; }
        .bar form { display: flex; gap: 6px; align-items: center; margin-left: 12px; }
        .bar input { width: 240px; max-width: 60vw; padding: 4px 8px; border: 1px solid #777; border-radius: 3px; font: inherit; }
        .bar button:disabled { opacity: .4; cursor: not-allowed; }
        .bar .note { color: #ccc; font-size: 12px; }
        .flash { max-width: 210mm; margin: 8px auto 0; padding: 6px 10px; border-radius: 3px; font: 13px system-ui, sans-serif; }
        .flash.ok { background: #e3f1e5; color: #1e6b2d; } .flash.err { background: #fbe4e4; color: #9b1c1c; }
        @media print {
            body { background: #fff; }
            .bar, .flash { display: none; }
            .page { margin: 0; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<nav class="bar" aria-label="{{ __('pms.res.letter') }}">
    @foreach (\App\Http\Controllers\Reservations\ConfirmationLetterController::LANGUAGES as $code)
        <a href="{{ route('reservations.letter', [$r, 'dil' => $code]) }}" @class(['on' => $code === $lang])>{{ strtoupper($code) }}</a>
    @endforeach
    <button type="button" onclick="window.print()">{{ __('pms.res.print') }} <kbd>Ctrl+P</kbd></button>
    <a href="{{ route('reservations.navigator', ['secili' => $r->id, 'no' => $r->confirmation_number]) }}" data-close>{{ __('pms.common.close') }} <kbd>Esc</kbd></a>
    @can('permission', 'reservations.edit')
        <form method="POST" action="{{ route('reservations.letter-send', $r) }}" data-send>
            @csrf
            <input type="hidden" name="dil" value="{{ $lang }}">
            <input type="email" name="email" value="{{ old('email', $email) }}" placeholder="{{ __('pms.res.letter_email') }}" required>
            <button @disabled(! $mailReady) title="{{ $mailReady ? '' : __('pms.res.letter_no_smtp') }}">{{ __('pms.res.letter_send') }}</button>
        </form>
    @endcan
    @if ($sent)
        <span class="note">{{ __('pms.res.letter_last', ['date' => \Illuminate\Support\Carbon::parse($sent->sent_at)->format('d.m.Y H:i'), 'email' => $sent->email, 'user' => $sent->username]) }}</span>
    @endif
</nav>
@if (session('status'))<div class="flash ok" role="status">{{ session('status') }}</div>@endif
@if (session('error'))<div class="flash err" role="alert">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="flash err" role="alert">{{ $errors->first() }}</div>@endif

<main class="page">
    <img class="header" src="{{ $img('header.png') }}" alt="Rocks Hotel &amp; Casino">
    <div class="title"><b>{{ $t('title_1') }}</b><span>{{ $t('title_2') }}</span></div>

    <div class="content">
        <div class="hotel">
            <h1>{{ $t('hotel_line') }}</h1>
            <p><em>Tel:</em> +90 392 650 0400 <em>Fax:</em> +90 392 650 0404</p>
            <p><em>E-Mail:</em> info@rockshotel.com <em>Homepage:</em> www.rockshotel.com</p>
        </div>

        <p class="dear">{{ $t('dear', ['name' => $guest]) }}</p>
        <p class="intro">{{ $t('intro') }}</p>

        <table class="fields">
            @foreach ($rows as [$key, $value])
                <tr>
                    <td class="l">{{ $t($key) }}@if ($key === 'room_rate' && $t('per_night') !== '') <i>{{ $t('per_night') }}</i>@endif</td>
                    <td class="c">:</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p class="para">{{ $t('guarantee_1') }}</p>
        <p class="para">{{ $t('guarantee_2') }}</p>

        <p class="times">{{ $t('times') }}</p>

        <p class="para">{{ $t('relations') }}</p>
        <p class="contact"><span class="ico">📞</span> +90 392 650 04 00 <span class="sep">|</span> <span class="ico">✉️</span> guestrelations@rockshotel.com</p>

        <p class="thanks">{{ $t('thanks') }}</p>

        <p class="regards">{{ $t('regards') }}<br>{{ $t('department') }}<br>{{ $t('signature') }}</p>
    </div>

    <img class="sign" src="{{ $img('signature.jpg') }}" alt="">

    <div class="foot1">
        <span><img src="{{ $img('phone.png') }}" alt="">H · 0392 650 04 00 &nbsp;C · 0392 650 04 44</span>
        <span><img src="{{ $img('location.png') }}" alt="">20 Temmuz Kordonboyu Cad. Girne / K.K.T.C.</span>
    </div>
    <div class="foot2">
        <span><img src="{{ $img('web.png') }}" alt="">www.rockshotel.com</span>
        <span><img src="{{ $img('instagram.png') }}" alt=""><img src="{{ $img('facebook.png') }}" alt="">rockshotel</span>
    </div>
</main>

<script>
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { document.querySelector('[data-close]').click(); }
    });
</script>
</body>
</html>
