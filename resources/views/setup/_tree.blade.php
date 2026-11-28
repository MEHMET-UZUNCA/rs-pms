{{-- Fidelio ayar ağacı (Setup → Configuration). Hazır olan ekranlar bağlantıdır. --}}
<nav class="tree text-[13px]" aria-label="{{ __('pms.common.setup_title') }}">
    <div class="px-2 pt-1 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.cashiering') }}</div>
    <a href="{{ route('setup.outlets.index') }}" @class(['is-current' => request()->routeIs('setup.outlets.*')])>{{ __('pms.menu.outlets') }}</a>
    <a href="{{ route('setup.transaction-codes.index') }}" @class(['is-current' => request()->routeIs('setup.transaction-codes.*')])>{{ __('pms.menu.department_codes') }}</a>
    @can('permission', 'setup.transaction_codes')
        <a href="{{ route('setup.groups.index') }}" @class(['is-current' => request()->routeIs('setup.groups.*')])>{{ __('pms.setup.department_code_groups') }}</a>
        <a href="{{ route('setup.taxes.index') }}" @class(['is-current' => request()->routeIs('setup.taxes.*')])>{{ __('pms.menu.taxes') }}</a>
    @else<span class="is-soon">{{ __('pms.setup.department_code_groups') }}</span><span class="is-soon">{{ __('pms.menu.taxes') }}</span>@endcan
    @can('permission', 'setup.exchange_rates')<a href="{{ route('setup.exchange.index') }}" @class(['is-current' => request()->routeIs('setup.exchange.*')])>{{ __('pms.exchange.title') }}</a>@endcan
    @can('permission', 'setup.transaction_codes')<a href="{{ route('setup.payment-types.index') }}" @class(['is-current' => request()->routeIs('setup.payment-types.*')])>{{ __('pms.setup.payment_types') }}</a>@else<span class="is-soon">{{ __('pms.setup.payment_types') }}</span>@endcan
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.reservation') }}</div>
    @can('permission', 'setup.options')<a href="{{ route('setup.options.reservation') }}" @class(['is-current' => request()->routeIs('setup.options.*')])>{{ __('pms.options.title') }}</a>@endcan
    @can('permission', 'setup.options')<a href="{{ route('setup.items.index') }}" @class(['is-current' => request()->routeIs('setup.items.*')])>{{ __('pms.items.title') }}</a>@endcan
    @can('permission', 'setup.rooms')
        <a href="{{ route('setup.room-types.index') }}" @class(['is-current' => request()->routeIs('setup.room-types.*')])>{{ __('pms.setup.room_types') }}</a>
        <a href="{{ route('setup.rooms.index') }}" @class(['is-current' => request()->routeIs('setup.rooms.*')])>{{ __('pms.rooms_setup.title') }}</a>
    @else<span class="is-soon">{{ __('pms.setup.room_types') }}</span>@endcan
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.rate_management') }}</div>
    @can('permission', 'setup.rates')<a href="{{ route('setup.rates.index') }}" @class(['is-current' => request()->routeIs('setup.rates.*')])>{{ __('pms.rates.title') }}</a>@else<span class="is-soon">{{ __('pms.rates.title') }}</span>@endcan
    @can('permission', 'setup.rates')
        <a href="{{ route('setup.categories.index') }}" @class(['is-current' => request()->routeIs('setup.categories.*')])>{{ __('pms.setup.rate_categories') }}</a>
        <a href="{{ route('setup.seasons.index') }}" @class(['is-current' => request()->routeIs('setup.seasons.*')])>{{ __('pms.setup.seasons') }}</a>
        <a href="{{ route('setup.packages.index') }}" @class(['is-current' => request()->routeIs('setup.packages.*')])>{{ __('pms.setup.packages') }}</a>
    @else<span class="is-soon">{{ __('pms.setup.rate_categories') }}</span><span class="is-soon">{{ __('pms.setup.seasons') }}</span><span class="is-soon">{{ __('pms.setup.packages') }}</span>@endcan
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.system') }}</div>
    @can('permission', 'setup.options')<a href="{{ route('setup.login-screen') }}" @class(['is-current' => request()->routeIs('setup.login-screen*')])>{{ __('pms.loginscreen.title') }}</a>@endcan
    @can('permission', 'setup.users')
        <a href="{{ route('setup.users.index') }}" @class(['is-current' => request()->routeIs('setup.users.*')])>{{ __('pms.users.title') }}</a>
        <a href="{{ route('setup.roles.index') }}" @class(['is-current' => request()->routeIs('setup.roles.*')])>{{ __('pms.roles.title') }}</a>
    @else
        <span class="is-soon">{{ __('pms.setup.users_roles') }}</span>
    @endcan
</nav>
