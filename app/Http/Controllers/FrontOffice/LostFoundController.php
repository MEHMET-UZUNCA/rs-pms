<?php

namespace App\Http\Controllers\FrontOffice;

use App\Domain\Core\Models\Property;
use App\Domain\Housekeeping\Models\LostFoundItem;
use App\Domain\Housekeeping\Services\LostFoundService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Front Desk → Lost & Found (Fidelio "Lost & Found Items"): solda kayıp, sağda bulunan eşyalar; her listenin süzgeci (tarih, grup,
 * eşya, oda, ortak alan, kişi, eşleşenler / kapananlar dahil); seçili eşyanın hareket geçmişi, eşleştirme, teslim, imha, not.
 */
class LostFoundController extends Controller
{
    public const KINDS = ['lost', 'found'];

    public function __construct(private readonly LostFoundService $items) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $lists = [];
        foreach (self::KINDS as $kind) {
            $f = fn (string $key) => $request->query($kind[0].'_'.$key);
            $bd = $p->current_business_date->toImmutable();
            $from = $f('from') ? BusinessDateInput::parse((string) $f('from'), $bd) : null;
            $to = $f('to') ? BusinessDateInput::parse((string) $f('to'), $bd) : null;
            $text = trim((string) $f('item'));
            $lists[$kind] = LostFoundItem::where('property_id', $p->id)->where('kind', $kind)
                ->with(['room:id,number'])
                ->when(! $request->boolean($kind[0].'_matched'), fn ($q) => $q->where('status', '!=', 'matched'))
                ->when(! $request->boolean($kind[0].'_resolved'), fn ($q) => $q->whereNotIn('status', ['returned', 'disposed']))
                ->when($from, fn ($q) => $q->where('found_at', '>=', $from->startOfDay()))
                ->when($to, fn ($q) => $q->where('found_at', '<=', $to->endOfDay()))
                ->when($f('group'), fn ($q, $g) => $q->where('group_id', $g))
                ->when($f('place'), fn ($q, $g) => $q->where('public_place_id', $g))
                ->when($f('room'), fn ($q, $r) => $q->whereHas('room', fn ($w) => $w->where('number', $r)))
                ->when($f('person'), fn ($q, $n) => $q->where('person_name', 'like', "%{$n}%"))
                ->when($text !== '', fn ($q) => $q->where(fn ($w) => $w->where('description', 'like', "%{$text}%")->orWhere('location', 'like', "%{$text}%")
                    ->orWhere('note', 'like', "%{$text}%")->orWhere('number', $text)))
                ->orderByDesc('found_at')->orderByDesc('id')->limit(300)->get();
        }

        return view('front-office.lost-found.index', $this->lookups() + [
            'lists' => $lists, 'filters' => $request->query(),
            'selectedLost' => (int) ($request->query('l_secili') ?? session('selected_lost', 0)),
            'selectedFound' => (int) ($request->query('f_secili') ?? session('selected_found', 0)),
        ]);
    }

    /** Seçili eşyanın bilgileri ve hareket geçmişi (alt panel). */
    public function show(LostFoundItem $item): View
    {
        $this->own($item);

        return view('front-office.lost-found._detail', ['item' => $item->load(['room:id,number', 'match:id,number,description']),
            'actions' => DB::table('pms_lost_found_actions as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->where('a.lost_found_id', $item->id)
                ->orderByDesc('a.id')->get(['a.action', 'a.note', 'a.created_at', 'u.username'])] + $this->lookups());
    }

    public function create(string $kind): View
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        return view('front-office.lost-found.form', $this->lookups() + ['item' => new LostFoundItem(['kind' => $kind, 'found_at' => now()])]);
    }

    public function edit(LostFoundItem $item): View
    {
        $this->own($item);

        return view('front-office.lost-found.form', $this->lookups() + ['item' => $item->load('room:id,number')]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $item = $this->items->create(app(Property::class)->id, $kind, $this->validated($request), $request->user()->id);

        return redirect()->route('lost-found.index')->with('status', __('pms.lf.saved', ['number' => $item->number]))->with('selected_'.$kind, $item->id);
    }

    public function update(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->own($item);

        return $this->run(fn () => $this->items->update($item, $this->validated($request), $request->user()->id), $item, __('pms.lf.saved', ['number' => $item->number]));
    }

    public function destroy(LostFoundItem $item): RedirectResponse
    {
        $this->own($item);

        return $this->run(fn () => $this->items->delete($item), $item, __('pms.common.deleted', ['name' => $item->number]));
    }

    public function match(Request $request): RedirectResponse
    {
        $data = $request->validate(['lost_id' => ['required', 'integer'], 'found_id' => ['required', 'integer']]);
        $lost = LostFoundItem::findOrFail($data['lost_id']);
        $found = LostFoundItem::findOrFail($data['found_id']);
        $this->own($lost);
        $this->own($found);

        return $this->run(fn () => $this->items->match($lost, $found, $request->user()->id), $lost, __('pms.lf.matched', ['lost' => $lost->number, 'found' => $found->number]), $found);
    }

    public function unmatch(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->own($item);

        return $this->run(fn () => $this->items->unmatch($item, $request->user()->id), $item, __('pms.lf.unmatched'));
    }

    public function resolve(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->own($item);
        $data = $request->validate(['how' => ['required', Rule::in(['returned', 'disposed'])], 'to' => ['nullable', 'required_if:how,returned', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500']], [], ['to' => __('pms.lf.returned_to')]);

        return $this->run(fn () => $this->items->resolve($item, $data['how'], $data['to'] ?? null, $data['note'] ?? null, $request->user()->id),
            $item, __('pms.lf.resolved_'.$data['how']));
    }

    public function note(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->own($item);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->items->note($item, $data['note'], $request->user()->id);

        return back()->with('status', __('pms.lf.note_added'))->with('selected_'.$item->kind, $item->id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $p = app(Property::class);
        $own = fn (string $category) => Rule::exists('pms_reason_codes', 'id')->where('property_id', $p->id)->where('category', $category);
        $raw = trim((string) $request->input('found_date'));
        $date = $raw === '' ? null : BusinessDateInput::parse($raw, $p->current_business_date->toImmutable());
        $request->merge(['found_date' => $date?->toDateString() ?? ($raw === '' ? null : 'x')]);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'group_id' => ['nullable', 'integer', $own('lost_found_group')],
            'public_place_id' => ['nullable', 'integer', $own('public_place')],
            'room' => ['nullable', 'string', 'max:10', Rule::exists('pms_rooms', 'number')->where('property_id', $p->id)],
            'location' => ['nullable', 'string', 'max:150'],
            'found_date' => ['required', 'date'], 'found_time' => ['nullable', 'date_format:H:i'],
            'person_name' => ['nullable', 'string', 'max:150'], 'contact' => ['nullable', 'string', 'max:150'],
            'storage_location' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:500'],
        ], [], ['description' => __('pms.lf.item'), 'room' => __('pms.lf.room'), 'found_date' => __('pms.lf.when')]);

        $data['room_id'] = ($data['room'] ?? null) ? DB::table('pms_rooms')->where('property_id', $p->id)->where('number', $data['room'])->value('id') : null;
        $data['found_at'] = $data['found_date'].' '.($data['found_time'] ?? now()->format('H:i')).':00';
        unset($data['room'], $data['found_date'], $data['found_time']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        $p = app(Property::class)->id;
        $list = fn (string $category) => DB::table('pms_reason_codes')->where('property_id', $p)->where('category', $category)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return ['groups' => $list('lost_found_group'), 'places' => $list('public_place')];
    }

    private function run(\Closure $action, LostFoundItem $item, string $message, ?LostFoundItem $other = null): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage())->with('selected_'.$item->kind, $item->id);
        }
        $redirect = redirect()->route('lost-found.index')->with('status', $message)->with('selected_'.$item->kind, $item->id);

        return $other ? $redirect->with('selected_'.$other->kind, $other->id) : $redirect;
    }

    private function own(LostFoundItem $item): void
    {
        abort_unless((int) $item->property_id === (int) app(Property::class)->id, 404);
    }
}
