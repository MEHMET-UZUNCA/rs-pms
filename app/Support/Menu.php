<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Fidelio Suite8 ana menüleri (docs/07-arayuz.md §7.1). Hazır olan ekranlar bağlantı (yetkiye göre), diğerleri soluk görünür.
 * Her madde: [dil anahtarı (pms.menu.*), adres ya da null, (isteğe bağlı) kısayol].
 */
class Menu
{
    /** @return array<string, list<array{0: string, 1: ?string, 2?: string}>> */
    public static function items(): array
    {
        $can = fn (string $permission) => Gate::allows('permission', $permission);

        return [
            'reservation' => [
                ['new_reservation', $can('reservations.edit') ? route('reservations.create') : null],
                ['update_reservation', $can('reservations.view') ? route('reservations.navigator') : null],
                ['block_reservation', null], ['group_reservation', null],
                ['financial_accounts', $can('reservations.view') ? route('reservations.navigator', ['f' => 'fa']) : null],
                ['quick_reservation', null],
            ],
            'front_office' => [
                ['arrivals', $can('reservations.view') ? route('reservations.navigator', ['f' => 'gelecek']) : null],
                ['in_house', $can('reservations.view') ? route('reservations.navigator', ['f' => 'iceride']) : null],
                ['messages', null],
                ['new_financial_account', $can('cashiering.accounts') ? route('accounts.create') : null],
                ['queue_rooms', null], ['lost_found', null],
                ['room_rack', $can('reservations.view') ? route('front-office.room-rack') : null],
            ],
            'cashiering' => [['billing', $can('cashiering.billing') ? route('reservations.navigator', ['f' => 'iceride']) : null], ['posting', null], ['passer_by', null], ['close_cashier', null]],
            'housekeeping' => [
                ['room_status', $can('housekeeping.view') ? route('housekeeping.index') : null],
                ['out_of_order', null], ['room_history', null], ['house_status', null],
            ],
            'profiles' => [['profile_navigator', null]],
            'quick_keys' => array_merge(array_map(fn ($k) => [$k['label'], $k['url'], $k['display']], QuickKeys::global()), [['all_shortcuts', route('shortcuts'), 'F1']]),
            'miscellaneous' => [['night_audit', $can('night_audit.view') ? route('night-audit.index') : null], ['reports', null]],
            'setup' => [
                ['outlets', $can('setup.outlets') ? route('setup.outlets.index') : null],
                ['department_codes', $can('setup.transaction_codes') ? route('setup.transaction-codes.index') : null],
                ['taxes', null],
                ['reservation_options', $can('setup.options') ? route('setup.options.reservation') : null],
                ['users', $can('setup.users') ? route('setup.users.index') : null],
                ['roles', $can('setup.users') ? route('setup.roles.index') : null],
            ],
        ];
    }
}
