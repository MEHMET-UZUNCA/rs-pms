<?php

namespace App\Support;

use App\Http\Controllers\ReportController;
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
                ['block_reservation', $can('reservations.view') ? route('blocks.index') : null],
                ['group_reservation', $can('reservations.edit') ? route('blocks.create', ['tur' => 'group']) : null],
                ['financial_accounts', $can('reservations.view') ? route('reservations.navigator', ['f' => 'fa']) : null],
                ['availability', $can('reservations.view') ? route('availability.index') : null],
                ['overbooking', $can('reservations.view') ? route('overbooking.index') : null],
                ['quick_reservation', null],
                ['meal_plan', $can('reservations.view') ? route('meal-plan.forecast') : null],
            ],
            'front_office' => [
                ['arrivals', $can('reservations.view') ? route('reservations.navigator', ['f' => 'gelecek']) : null],
                ['in_house', $can('reservations.view') ? route('reservations.navigator', ['f' => 'iceride']) : null],
                ['new_financial_account', $can('cashiering.accounts') ? route('accounts.create') : null],
                ['queue_rooms', $can('reservations.view') ? route('queue.index') : null],
                ['lost_found', $can('front_office.lost_found') ? route('lost-found.index') : null],
                ['room_rack', $can('reservations.view') ? route('front-office.room-rack') : null],
                ['room_moves', $can('reservations.view') ? route('room-moves.index') : null],
                ['guest_requests', $can('front_office.requests') ? route('requests.index') : null],
            ],
            'cashiering' => [['billing', $can('cashiering.billing') ? route('reservations.navigator', ['f' => 'iceride']) : null], ['posting', null], ['passer_by', null], ['close_cashier', $can('cashiering.post') ? route('cashier.index') : null]],
            'housekeeping' => [
                ['room_status', $can('housekeeping.view') ? route('housekeeping.index') : null],
                ['out_of_order', $can('housekeeping.view') ? route('rooms.out-of-order') : null],
                ['room_history', $can('housekeeping.view') || $can('reservations.view') ? route('rooms.history') : null],
                ['hk_mobile', $can('housekeeping.view') ? route('housekeeping.mobile') : null],
                ['house_status', $can('reservations.view') || $can('housekeeping.view') ? route('front-office.house-status') : null],
            ],
            'profiles' => [['profile_navigator', $can('profiles.view') ? route('profiles.index') : null]],
            'quick_keys' => array_merge(array_map(fn ($k) => [$k['label'], $k['url'], $k['display']], QuickKeys::global()), [['all_shortcuts', route('shortcuts'), 'F1']]),
            'miscellaneous' => [['night_audit', $can('night_audit.view') ? route('night-audit.index') : null], ['reports', ReportController::canSee() ? route('reports.index') : null, 'Ctrl+Shift+R']],
            'setup' => [
                ['outlets', $can('setup.outlets') ? route('setup.outlets.index') : null],
                ['department_codes', $can('setup.transaction_codes') ? route('setup.transaction-codes.index') : null],
                ['taxes', $can('setup.transaction_codes') ? route('setup.taxes.index') : null],
                ['exchange_rates', $can('setup.exchange_rates') ? route('setup.exchange.index') : null],
                ['rate_codes', $can('setup.rates') ? route('setup.rates.index') : null],
                ['reservation_options', $can('setup.options') ? route('setup.options.reservation') : null],
                ['items', $can('setup.options') ? route('setup.items.index') : null],
                ['room_types', $can('setup.rooms') ? route('setup.room-types.index') : null],
                ['rooms_setup', $can('setup.rooms') ? route('setup.rooms.index') : null],
                ['users', $can('setup.users') ? route('setup.users.index') : null],
                ['roles', $can('setup.users') ? route('setup.roles.index') : null],
                ['audit_log', $can('setup.audit') ? route('setup.audit') : null],
                ['login_screen', $can('setup.options') ? route('setup.login-screen') : null],
            ],
        ];
    }
}
