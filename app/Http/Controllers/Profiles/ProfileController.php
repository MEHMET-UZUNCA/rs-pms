<?php

namespace App\Http\Controllers\Profiles;

use App\Domain\Core\Models\Property;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Profiles\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Support\BusinessDateInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer Relation → Profiles (Fidelio Profile Navigator): tür sekmeleri, ad / şehir / telefon / e-posta ile arama, seçili profilin
 * özeti ve konaklama geçmişi; profil kartı (yeni / düzenle).
 */
class ProfileController extends Controller
{
    public const LIMIT = 200;

    public function __construct(private readonly ProfileService $profiles) {}

    public function index(Request $request): View
    {
        $p = app(Property::class);
        $type = in_array($request->query('tur'), ProfileService::TYPES, true) ? $request->query('tur') : 'individual';
        $name = trim((string) $request->query('ad', ''));
        $city = trim((string) $request->query('sehir', ''));
        $contact = trim((string) $request->query('iletisim', ''));
        $showInactive = $request->boolean('pasif');
        $like = fn (string $v) => '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';

        $rows = Profile::query()->where('property_id', $p->id)->where('type', $type)->whereNull('merged_into_id')
            ->when(! $showInactive, fn ($q) => $q->whereIn('status', ['active', 'blacklisted']))
            ->when($name !== '', fn ($q) => $q->where(fn ($w) => $w->where('display_name', 'like', $like($name))->orWhere('first_name', 'like', $like($name))
                ->orWhere('tax_number', $name)->orWhere('iata_code', $name)))
            ->when($city !== '', fn ($q) => $q->whereHas('addresses', fn ($a) => $a->where('city', 'like', $like($city))))
            ->when($contact !== '', function ($q) use ($contact, $like) {
                $normalized = str_contains($contact, '@') ? mb_strtolower($contact) : preg_replace('/[^\d+]/', '', $contact);
                $q->whereHas('communications', fn ($c) => $c->where(fn ($w) => $w->where('value', 'like', $like($contact))
                    ->when($normalized !== '', fn ($n) => $n->orWhere('value_normalized', 'like', $like($normalized)))));
            })
            ->with(['addresses' => fn ($a) => $a->orderByDesc('is_primary'), 'communications'])
            ->orderBy('display_name')->limit(self::LIMIT)->get();
        $stays = DB::table('pms_reservations')->where('property_id', $p->id)->whereIn('profile_id', $rows->pluck('id'))->where('status', 'checked_out')
            ->groupBy('profile_id')->selectRaw('profile_id, COUNT(*) AS stays, MAX(departure_date) AS last_stay')->get()->keyBy('profile_id');

        return view('profiles.index', [
            'rows' => $rows, 'stays' => $stays, 'type' => $type, 'name' => $name, 'city' => $city, 'contact' => $contact, 'showInactive' => $showInactive,
            'counts' => DB::table('pms_profiles')->where('property_id', $p->id)->whereNull('merged_into_id')->whereNull('deleted_at')
                ->when(! $showInactive, fn ($q) => $q->whereIn('status', ['active', 'blacklisted']))->groupBy('type')->selectRaw('type, COUNT(*) AS n')->pluck('n', 'type'),
            'selected' => (int) $request->query('secili', session('selected', 0)), 'limit' => self::LIMIT,
        ]);
    }

    /** Seçili profilin özeti ve konaklama geçmişi (alt panel). */
    public function summary(Profile $profile): View
    {
        $this->own($profile);
        $column = match ($profile->type) { 'company' => 'company_profile_id', 'travel_agent' => 'agent_profile_id', 'source' => 'source_profile_id', 'group' => 'group_profile_id', default => 'profile_id' };

        return view('profiles._summary', [
            'profile' => $profile->load(['addresses', 'communications']),
            'history' => DB::table('pms_reservations as r')->join('pms_profiles as g', 'g.id', '=', 'r.profile_id')->leftJoin('pms_rooms as rm', 'rm.id', '=', 'r.room_id')
                ->leftJoin('pms_rate_codes as rc', 'rc.id', '=', 'r.rate_code_id')->where('r.'.$column, $profile->id)
                ->orderByDesc('r.arrival_date')->limit(30)
                ->get(['r.id', 'r.confirmation_number', 'r.arrival_date', 'r.departure_date', 'r.nights', 'r.status', 'r.total_room_amount', 'g.display_name', 'rm.number as room', 'rc.code as rate_code']),
            'country' => fn (?string $code) => $code ? DB::table('pms_countries')->where('code', $code)->value(app()->getLocale() === 'tr' ? 'name_tr' : 'name_en') : null,
        ]);
    }

    public function create(string $type): View
    {
        abort_unless(in_array($type, ProfileService::TYPES, true), 404);

        return $this->form(new Profile(['type' => $type, 'status' => 'active', 'store_private_data' => true]));
    }

    public function edit(Profile $profile): View
    {
        $this->own($profile);

        return $this->form($profile->load(['addresses', 'communications']));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, ProfileService::TYPES, true), 404);

        return $this->save($request, $type, null);
    }

    public function update(Request $request, Profile $profile): RedirectResponse
    {
        $this->own($profile);

        return $this->save($request, $profile->type, $profile);
    }

    private function save(Request $request, string $type, ?Profile $profile): RedirectResponse
    {
        $p = app(Property::class);
        $raw = trim((string) $request->input('birth_date'));
        $request->merge(['birth_date' => $raw === '' ? null : (BusinessDateInput::parse($raw, $p->current_business_date->toImmutable())?->toDateString() ?? 'x'),
            'commission_pct' => str_replace(',', '.', (string) $request->input('commission_pct')) ?: null]);
        $own = fn (string $table) => Rule::exists($table, 'id')->where('property_id', $p->id);
        $individual = $type === 'individual';
        $data = $request->validate([
            'last_name' => [$individual ? 'required' : 'nullable', 'string', 'max:100'], 'first_name' => ['nullable', 'string', 'max:100'],
            'company_name' => [$individual ? 'nullable' : 'required', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:20'], 'salutation' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'birth_date' => ['nullable', 'date', 'before:today'], 'occupation' => ['nullable', 'string', 'max:100'],
            'nationality_code' => ['nullable', Rule::exists('pms_countries', 'code')], 'language_code' => ['nullable', 'string', 'size:2'],
            'vip_level_id' => ['nullable', 'integer', $own('pms_vip_levels')],
            'tax_office' => ['nullable', 'string', 'max:100'], 'tax_number' => ['nullable', 'string', 'max:30'], 'iata_code' => ['nullable', 'string', 'max:20'],
            'commission_pct' => ['nullable', 'numeric', 'between:0,100'],
            'default_market_segment_id' => ['nullable', 'integer', $own('pms_market_segments')], 'default_source_code_id' => ['nullable', 'integer', $own('pms_source_codes')],
            'default_rate_code_id' => ['nullable', 'integer', $own('pms_rate_codes')],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])], 'blacklist_reason' => ['nullable', 'required_if:status,blacklisted', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'], 'mobile' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'],
            'address.line1' => ['nullable', 'string', 'max:200'], 'address.line2' => ['nullable', 'string', 'max:200'], 'address.postal_code' => ['nullable', 'string', 'max:15'],
            'address.city' => ['nullable', 'string', 'max:100'], 'address.state' => ['nullable', 'string', 'max:60'], 'address.country_code' => ['nullable', Rule::exists('pms_countries', 'code')],
        ], [], [
            'last_name' => __('pms.prof.last_name'), 'company_name' => __('pms.prof.company_name'), 'birth_date' => __('pms.prof.birth_date'), 'email' => __('pms.users.email'),
            'blacklist_reason' => __('pms.prof.blacklist_reason'),
        ]);
        foreach (['marketing_consent', 'no_mailing', 'no_emailing', 'store_private_data'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['type'] = $type;

        $saved = $this->profiles->save($p->id, $data, $profile, $request->user()->id);

        return redirect()->route('profiles.index', ['tur' => $type, 'ad' => $saved->display_name])->with('status', __('pms.common.saved', ['name' => $saved->display_name]))->with('selected', $saved->id);
    }

    private function form(Profile $profile): View
    {
        $p = app(Property::class)->id;
        $list = fn (string $table, string $order = 'code') => DB::table($table)->where('property_id', $p)->whereNull('deleted_at')->orderBy($order)->get(['id', 'code', 'name']);

        return view('profiles.form', [
            'profile' => $profile, 'address' => $profile->exists ? ($profile->addresses->firstWhere('is_primary', true) ?? $profile->addresses->first()) : null,
            'comm' => fn (string $type) => $profile->exists ? $profile->communications->where('type', $type)->sortByDesc('is_primary')->first()?->value : null,
            'countries' => DB::table('pms_countries')->orderBy(app()->getLocale() === 'tr' ? 'name_tr' : 'name_en')->get(['code', 'name_en', 'name_tr']),
            'vips' => DB::table('pms_vip_levels')->where('property_id', $p)->orderBy('code')->get(['id', 'code', 'name']),
            'segments' => $list('pms_market_segments', 'sort'), 'sources' => $list('pms_source_codes', 'sort'), 'rates' => $list('pms_rate_codes'),
        ]);
    }

    private function own(Profile $profile): void
    {
        abort_unless((int) $profile->property_id === (int) app(Property::class)->id, 404);
    }
}
