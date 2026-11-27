<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Fidelio "Quick Keys" menüsü: her ekrandan çalışan genel kısayollar. Yalnız otelin kullandığı ve RS PMS'te olan (ya da yapılacak)
 * ekranlar listelenir; Fidelio'daki diğerleri (Calendar, Goals, Floor Plan, Lock Station, Telephone Book …) alınmadı.
 * Ekranlara özel kısayollar (Alt+N, F10 …) screens() listesinde; ikisi birlikte Quick Keys → All Shortcuts ekranında gösterilir.
 */
class QuickKeys
{
    /**
     * @return list<array{label: string, hotkey: string, display: string, url: ?string}>
     *                                                                                   url null: ekran henüz yok (menüde soluk görünür, kısayol çalışmaz) ya da kullanıcının yetkisi yok.
     */
    public static function global(): array
    {
        $can = fn (string $permission) => Gate::allows('permission', $permission);
        $keys = [
            ['arrivals', 'ctrl+a', 'Ctrl+A', $can('reservations.view') ? route('reservations.navigator', ['f' => 'gelecek']) : null],
            ['billing', 'ctrl+b', 'Ctrl+B', $can('cashiering.billing') ? route('reservations.navigator', ['f' => 'iceride']) : null],
            ['house_status', 'ctrl+h', 'Ctrl+H', $can('reservations.view') || $can('housekeeping.view') ? route('front-office.house-status') : null],
            ['in_house', 'ctrl+i', 'Ctrl+I', $can('reservations.view') ? route('reservations.navigator', ['f' => 'iceride']) : null],
            ['new_reservation', 'ctrl+n alt+shift+n', 'Ctrl+N', $can('reservations.edit') ? route('reservations.create') : null],
            ['profiles', 'ctrl+p', 'Ctrl+P', $can('profiles.view') ? route('profiles.index') : null],
            ['reports', 'ctrl+shift+r', 'Ctrl+Shift+R', null],
            ['room_rack', 'ctrl+l', 'Ctrl+L', $can('reservations.view') ? route('front-office.room-rack') : null],
            ['update_reservation', 'ctrl+u', 'Ctrl+U', $can('reservations.view') ? route('reservations.navigator') : null],
        ];

        return array_map(fn ($k) => ['label' => $k[0], 'hotkey' => $k[1], 'display' => $k[2], 'url' => $k[3]], $keys);
    }

    /**
     * Ekranlara özel kısayollar (ekran adı => [tuş, açıklama dil anahtarı]).
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public static function screens(): array
    {
        $list = [['Alt+N', 'pms.common.new'], ['Alt+E / Enter', 'pms.common.edit'], ['Alt+C', 'pms.common.copy'], ['Alt+D / Delete', 'pms.common.delete'],
            ['Alt+U / Alt+O', 'pms.keys.move'], ['↑ / ↓', 'pms.keys.select_row'], ['Esc', 'pms.common.close']];

        return [
            'pms.res.navigator' => [['F3', 'pms.common.search'], ['Alt+1 … Alt+0', 'pms.keys.filters'], ['Ctrl+N', 'pms.common.new'], ['Enter', 'pms.common.edit'],
                ['Alt+G', 'pms.res.check_in'], ['Alt+B', 'pms.res.folio'], ['Alt+L', 'pms.res.letter'], ['Esc', 'pms.common.close']],
            'pms.keys.reservation_card' => [['Ctrl+1 … Ctrl+0', 'pms.keys.tabs'], ['Alt+L', 'pms.res.letter'], ['F10 / Ctrl+Enter', 'pms.card.save'], ['Esc', 'pms.common.close']],
            'pms.menu.billing' => [['Alt+1 … Alt+8', 'pms.keys.windows'], ['Alt+P', 'pms.billing.post'], ['Alt+Y', 'pms.billing.payment'], ['Alt+R', 'pms.billing.correction'],
                ['Alt+T', 'pms.billing.transfer'], ['Alt+O', 'pms.billing.check_out'], ['Alt+A', 'pms.fa.edit'], ['Esc', 'pms.common.close']],
            'pms.menu.room_rack' => [['Alt+← / Alt+→', 'pms.keys.period'], ['Alt+Home', 'pms.common.business_date'], ['Esc', 'pms.common.close']],
            'pms.hkscreen.title' => [['Alt+1 … Alt+8', 'pms.keys.filters'], ['Esc', 'pms.common.close']],
            'pms.audit.title' => [['F10', 'pms.audit.run'], ['Esc', 'pms.common.close']],
            'pms.keys.setup_lists' => $list,
            'pms.keys.forms' => [['F10 / Ctrl+Enter', 'pms.common.ok'], ['Esc', 'pms.common.cancel']],
        ];
    }
}
