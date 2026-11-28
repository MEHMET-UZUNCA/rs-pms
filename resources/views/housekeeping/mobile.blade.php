{{-- Kat hizmetleri — telefon ekranı: büyük dokunma alanları, menü yok. Oda kartına dokununca durum düğmeleri ve arıza bildirimi açılır. --}}
@php
    $canStatus = Gate::allows('permission', 'housekeeping.status');
    $canReport = Gate::allows('permission', 'front_office.requests');
    $tone = fn (string $hk) => match ($hk) {
        'dirty' => 'border-l-danger bg-danger/5', 'pickup' => 'border-l-amber-500 bg-amber-50',
        'inspected' => 'border-l-brand-600 bg-brand-50', default => 'border-l-ok bg-ok/5',
    };
    $tabs = ['kirli' => __('pms.hkm.tabs.dirty'), 'dep' => __('pms.hkm.tabs.dep'), 'arr' => __('pms.hkm.tabs.arr'), 'ins' => __('pms.hkm.tabs.ins'), 'hepsi' => __('pms.hkm.tabs.all')];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#14284b">
    <title>{{ __('pms.hkm.title') }} — {{ $currentProperty->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-surface text-[15px]">
<header class="sticky top-0 z-10 bg-brand-900 text-white">
    <div class="flex items-center gap-3 px-4 h-12">
        <b class="text-[16px]">{{ __('pms.hkm.title') }}</b>
        <span class="text-brand-100 text-[13px]">{{ $businessDate->format('d.m.Y') }}</span>
        <a href="{{ route('housekeeping.index') }}" class="ml-auto text-[13px] text-brand-100 underline">{{ __('pms.hkm.desktop') }}</a>
    </div>
    <nav class="flex gap-1 overflow-x-auto px-3 pb-2" aria-label="Filters">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('housekeeping.mobile', array_filter(['f' => $key, 'kat' => $floor ?: null])) }}"
               @class(['shrink-0 rounded-full px-3 h-8 inline-flex items-center text-[14px]', 'bg-white text-brand-900 font-semibold' => $tab === $key, 'bg-brand-700 text-white' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </nav>
</header>

<main class="p-3 flex flex-col gap-2 max-w-xl mx-auto">
    @if (session('status'))<p class="rounded bg-ok/10 text-ok px-3 py-2" role="status">{{ session('status') }}</p>@endif
    @if (session('error'))<p class="rounded bg-danger/10 text-danger px-3 py-2" role="alert">{{ session('error') }}</p>@endif

    <form method="GET" class="flex gap-2 items-center">
        <input type="hidden" name="f" value="{{ $tab }}">
        <label for="m-floor" class="text-muted text-[13px]">{{ __('pms.hkscreen.floor') }}</label>
        <select id="m-floor" name="kat" class="input h-10 flex-1" onchange="this.form.submit()">
            <option value="">{{ __('pms.hkm.all_floors') }}</option>
            @foreach ($floors as $f)<option value="{{ $f->id }}" @selected($floor === (int) $f->id)>{{ $f->name }}</option>@endforeach
        </select>
        <span class="text-muted text-[13px] whitespace-nowrap">{{ __('pms.hkm.rooms', ['count' => $rooms->count()]) }}</span>
    </form>

    @forelse ($rooms as $r)
        <details class="rounded-lg border border-line border-l-[6px] {{ $tone($r->hk_status) }} bg-white" data-mobile-room="{{ $r->number }}">
            <summary class="flex items-center gap-3 px-3 py-3 cursor-pointer list-none">
                <b class="text-[22px] tabular-nums w-16">{{ $r->number }}</b>
                <span class="flex flex-col leading-tight">
                    <span class="font-semibold">{{ __('pms.hk.hk_status.'.$r->hk_status) }}@if ($r->hk_flag) · <span class="text-amber-700">{{ $r->hk_flag }}</span>@endif</span>
                    <span class="text-[13px] text-muted">{{ $r->type }} · {{ __('pms.history.fo.'.$r->fo_status) }}@if ($r->adults) · {{ $r->adults }}@if ($r->children)+{{ $r->children }}@endif {{ __('pms.hkm.pax') }}@endif</span>
                </span>
                <span class="ml-auto flex flex-col items-end gap-1 text-[12px]">
                    @if ($r->departing)<span class="rounded bg-danger text-white px-2">{{ __('pms.hkm.due_out') }}</span>@endif
                    @if ($r->arrival)<span class="rounded bg-brand-600 text-white px-2">{{ __('pms.hkm.arrival') }}</span>@endif
                    @if ($r->outage)<span class="rounded bg-ink text-white px-2">{{ strtoupper($r->outage->type) }}</span>@endif
                </span>
            </summary>
            <div class="px-3 pb-3 flex flex-col gap-2 border-t border-line pt-2">
                @if ($canStatus)
                    <form method="POST" action="{{ route('housekeeping.status') }}" class="grid grid-cols-2 gap-2">
                        @csrf <input type="hidden" name="rooms[]" value="{{ $r->id }}">
                        @foreach ($codes as $c)
                            <button name="code" value="{{ $c->code }}" @class(['btn h-11 justify-center text-[15px]', 'btn-primary' => in_array($c->code, ['CL', 'INS'], true)])>{{ $c->name }}</button>
                        @endforeach
                        @if ($r->hk_flag)<button name="code" value="CLEAR" class="btn h-11 justify-center">{{ __('pms.hkscreen.clear_flag') }}</button>@endif
                    </form>
                @endif
                @if ($canReport)
                    <form method="POST" action="{{ route('requests.store') }}" class="flex flex-col gap-2">
                        @csrf
                        <input type="hidden" name="room" value="{{ $r->number }}"><input type="hidden" name="kind" value="request"><input type="hidden" name="department" value="technical">
                        <input type="hidden" name="priority" value="normal"><input type="hidden" name="due_time" value="{{ now()->addHours(2)->format('H:i') }}">
                        <input type="hidden" name="return" value="mobile"><input type="hidden" name="kat" value="{{ $floor ?: '' }}">
                        <label class="text-[13px] text-muted" for="issue-{{ $r->id }}">{{ __('pms.hkm.report') }}</label>
                        <textarea id="issue-{{ $r->id }}" name="message" rows="2" class="input !h-auto py-2" placeholder="{{ __('pms.hkm.report_hint') }}" required maxlength="1000"></textarea>
                        <button class="btn h-11 justify-center">{{ __('pms.hkm.send') }}</button>
                    </form>
                @endif
            </div>
        </details>
    @empty
        <p class="text-center text-muted py-10">{{ __('pms.hkm.empty') }}</p>
    @endforelse
</main>
</body>
</html>
