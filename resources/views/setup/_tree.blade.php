{{-- Fidelio ayar ağacı (Setup → Configuration). Hazır olan ekranlar bağlantıdır. --}}
<nav class="tree text-[13px]" aria-label="{{ __('pms.common.setup_title') }}">
    <div class="px-2 pt-1 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.cashiering') }}</div>
    <a href="{{ route('setup.outlets.index') }}" @class(['is-current' => request()->routeIs('setup.outlets.*')])>{{ __('pms.menu.outlets') }}</a>
    <a href="{{ route('setup.transaction-codes.index') }}" @class(['is-current' => request()->routeIs('setup.transaction-codes.*')])>{{ __('pms.menu.department_codes') }}</a>
    <span class="is-soon">{{ __('pms.setup.department_code_groups') }}</span>
    <span class="is-soon">{{ __('pms.menu.taxes') }}</span>
    @can('permission', 'setup.exchange_rates')<a href="{{ route('setup.exchange.index') }}" @class(['is-current' => request()->routeIs('setup.exchange.*')])>{{ __('pms.exchange.title') }}</a>@endcan
    <span class="is-soon">{{ __('pms.setup.payment_types') }}</span>
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.reservation') }}</div>
    @can('permission', 'setup.options')<a href="{{ route('setup.options.reservation') }}" @class(['is-current' => request()->routeIs('setup.options.*')])>{{ __('pms.options.title') }}</a>@endcan
    @can('permission', 'setup.options')<a href="{{ route('setup.items.index') }}" @class(['is-current' => request()->routeIs('setup.items.*')])>{{ __('pms.items.title') }}</a>@endcan
    <span class="is-soon">{{ __('pms.setup.room_types') }}</span>
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.rate_management') }}</div>
    @can('permission', 'setup.rates')<a href="{{ route('setup.rates.index') }}" @class(['is-current' => request()->routeIs('setup.rates.*')])>{{ __('pms.rates.title') }}</a>@else<span class="is-soon">{{ __('pms.rates.title') }}</span>@endcan
    <span class="is-soon">{{ __('pms.setup.rate_categories') }}</span>
    <span class="is-soon">{{ __('pms.setup.seasons') }}</span>
    <span class="is-soon">{{ __('pms.setup.packages') }}</span>
    <div class="px-2 pt-2 pb-0.5 text-[11px] uppercase tracking-wide text-muted">{{ __('pms.setup.system') }}</div>
    @can('permission', 'setup.users')
        <a href="{{ route('setup.users.index') }}" @class(['is-current' => request()->routeIs('setup.users.*')])>{{ __('pms.users.title') }}</a>
        <a href="{{ route('setup.roles.index') }}" @class(['is-current' => request()->routeIs('setup.roles.*')])>{{ __('pms.roles.title') }}</a>
    @else
        <span class="is-soon">{{ __('pms.setup.users_roles') }}</span>
    @endcan
</nav>
