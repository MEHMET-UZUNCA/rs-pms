@extends('layouts.app')
@section('title', $report ? __('pms.reports.r.'.$key) : __('pms.reports.title'))
@section('content')
<div class="flex flex-1 min-h-0 gap-2">
    <aside class="win w-64 shrink-0">
        <div class="win-title"><span>{{ __('pms.reports.title') }}</span></div>
        <div class="p-2 border-b border-line"><input type="search" class="input w-full" placeholder="{{ __('pms.reports.search') }}" data-report-search @if (! $report) data-autofocus @endif></div>
        <form method="GET" class="px-2 pb-2 border-b border-line flex items-end gap-1" data-archive-form>
            @if ($key)<input type="hidden" name="r" value="{{ $key }}">@endif
            <label class="flex flex-col flex-1"><span class="field-label">{{ __('pms.reports.archive') }}</span>
                <input name="arsiv" value="{{ $archive ? $archive['date']?->format('d.m.Y') : '' }}" list="archive-dates" class="input" placeholder="{{ __('pms.reports.archive_hint') }}" autocomplete="off"></label>
            <datalist id="archive-dates">@foreach ($archiveDates as $d)<option value="{{ \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') }}"></option>@endforeach</datalist>
            <button class="btn">{{ __('pms.reports.open') }}</button>
            @if ($archive)<a href="{{ route('reports.index', array_filter(['r' => $key])) }}" class="btn" title="{{ __('pms.reports.live') }}">×</a>@endif
        </form>
        <nav class="flex-1 min-h-0 overflow-auto py-1" data-report-tree>
            @foreach ($tree as $group => $items)
                @php $items = $archive ? collect($items)->only($archive['keys']) : $items; @endphp
                @if (collect($items)->isEmpty()) @continue @endif
                <div class="px-3 pt-2 pb-1 text-[11px] uppercase tracking-wide text-muted" data-report-group>{{ __('pms.reports.groups.'.$group) }}</div>
                @foreach ($items as $k => $r)
                    <a href="{{ route('reports.index', array_filter(['r' => $k, 'arsiv' => $archive ? $archive['date']?->format('d.m.Y') : null, 'goster' => $archive ? 1 : null])) }}" data-report-item @class(['block px-4 py-1 text-[13px] hover:bg-brand-50', 'bg-brand-100 font-semibold' => $k === $key])>{{ __('pms.reports.r.'.$k) }}</a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <div class="win flex-1 min-w-0">
        <div class="win-title"><span>{{ $report ? __('pms.reports.r.'.$key) : __('pms.reports.title') }}</span>
            @if ($result)<span class="font-normal text-brand-100 text-[12px]">{{ __('pms.common.records', ['count' => count($result['rows'])]) }}</span>@endif</div>
        @if ($archive)
            <div class="px-3 py-2 border-b border-line bg-amber-50 text-[13px]" data-archive-banner>
                @if ($archive['found']){{ __('pms.reports.archive_banner', ['date' => $archive['date']->format('d.m.Y'), 'time' => \Illuminate\Support\Carbon::parse($archive['saved_at'])->format('d.m.Y H:i')]) }}
                @else<span class="text-danger">{{ __('pms.reports.archive_missing', ['date' => $archive['date']?->format('d.m.Y') ?? request('arsiv')]) }}</span>@endif
            </div>
        @endif
        @if (! $report)
            <p class="p-4 text-muted">{{ __('pms.reports.pick') }}</p>
        @elseif ($archive)
            <div class="flex gap-2 px-3 py-2 border-b border-line">
                <a class="btn" target="_blank" href="{{ route('reports.index', ['r' => $key, 'arsiv' => $archive['date']?->format('d.m.Y'), 'format' => 'print']) }}" data-hotkey="alt+p">{{ __('pms.res.print') }} <span class="kbd">Alt+P</span></a>
                <a class="btn" href="{{ route('reports.index', ['r' => $key, 'arsiv' => $archive['date']?->format('d.m.Y'), 'format' => 'csv']) }}" data-hotkey="alt+x">Excel (CSV) <span class="kbd">Alt+X</span></a>
            </div>
            <div class="flex-1 min-h-0 overflow-auto">
                @if ($result)@include('reports._table', ['class' => 'grid-table min-w-full'])@else<p class="p-4 text-muted">{{ __('pms.reports.archive_no_report') }}</p>@endif
            </div>
        @else
            <form method="GET" class="flex flex-wrap items-end gap-2 px-3 py-2 border-b border-line" data-report-form>
                <input type="hidden" name="r" value="{{ $key }}">
                @if (in_array('date', $report['filters'], true))
                    <label class="flex flex-col"><span class="field-label">{{ __('pms.common.date') }}</span><input name="bas" value="{{ $filters['from']->format('d.m.Y') }}" class="input w-28" autocomplete="off" data-autofocus></label>
                @endif
                @if (in_array('range', $report['filters'], true))
                    <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.from') }}</span><input name="bas" value="{{ $filters['from']->format('d.m.Y') }}" class="input w-28" autocomplete="off" data-autofocus></label>
                    <label class="flex flex-col"><span class="field-label">{{ __('pms.ooo.to') }}</span><input name="bit" value="{{ $filters['to']->format('d.m.Y') }}" class="input w-28" autocomplete="off"></label>
                @endif
                @if (in_array('room_type', $report['filters'], true))
                    <label class="flex flex-col"><span class="field-label">{{ __('pms.reports.col.room_type') }}</span>
                        <select name="tip" class="input w-40"><option value="">{{ __('pms.audit_log.all') }}</option>@foreach ($roomTypes as $t)<option value="{{ $t->id }}" @selected($filters['room_type'] === (int) $t->id)>{{ $t->code }} — {{ $t->name }}</option>@endforeach</select></label>
                @endif
                @if (in_array('user', $report['filters'], true))
                    <label class="flex flex-col"><span class="field-label">{{ __('pms.moves.user') }}</span>
                        <select name="kullanici" class="input w-36"><option value="">{{ __('pms.audit_log.all') }}</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected($filters['user'] === (int) $u->id)>{{ $u->username }}</option>@endforeach</select></label>
                @endif
                @if (in_array('unassigned', $report['filters'], true))
                    <label class="flex items-center gap-1 h-8"><input type="checkbox" name="atanmamis" value="1" @checked($filters['unassigned'])> {{ __('pms.reports.unassigned') }}</label>
                @endif
                @if (in_array('clean', $report['filters'], true))
                    <label class="flex items-center gap-1 h-8"><input type="checkbox" name="temiz" value="1" @checked($filters['clean'])> {{ __('pms.reports.clean_only') }}</label>
                @endif
                <button class="btn btn-primary" name="goster" value="1" data-hotkey="f10">{{ __('pms.reports.show') }} <span class="kbd !text-brand-100 !bg-transparent !border-brand-200">F10</span></button>
                <button class="btn" name="format" value="print" formtarget="_blank" data-hotkey="alt+p">{{ __('pms.res.print') }} <span class="kbd">Alt+P</span></button>
                <button class="btn" name="format" value="csv" data-hotkey="alt+x">Excel (CSV) <span class="kbd">Alt+X</span></button>
            </form>
            <div class="flex-1 min-h-0 overflow-auto">
                @if ($result)
                    @include('reports._table', ['class' => 'grid-table min-w-full'])
                @else
                    <p class="p-4 text-muted">{{ __('pms.reports.hint') }}</p>
                @endif
            </div>
        @endif
        <div class="flex px-3 py-2 border-t border-line"><span class="text-[12px] text-muted">{{ __('pms.reports.footer', ['currency' => $base]) }}</span><a href="{{ route('home') }}" class="btn ml-auto" data-hotkey="escape">{{ __('pms.common.close') }} <span class="kbd">Esc</span></a></div>
    </div>
</div>
<script>
    // Rapor ağacında arama: ada göre süzer, boş kalan grubun başlığını gizler; Enter ilk raporu açar
    (() => {
        const input = document.querySelector('[data-report-search]');
        input?.addEventListener('input', () => {
            const q = input.value.toLocaleLowerCase();
            document.querySelectorAll('[data-report-item]').forEach((a) => { a.hidden = q !== '' && ! a.textContent.toLocaleLowerCase().includes(q); });
            document.querySelectorAll('[data-report-group]').forEach((g) => {
                let n = g.nextElementSibling, any = false;
                while (n && n.matches('[data-report-item]')) { any ||= ! n.hidden; n = n.nextElementSibling; }
                g.hidden = ! any;
            });
        });
        input?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); document.querySelector('[data-report-item]:not([hidden])')?.click(); } });
    })();
</script>
@endsection
