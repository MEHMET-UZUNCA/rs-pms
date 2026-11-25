@extends('layouts.app')
@section('title', __('pms.common.home'))
@section('content')
<div class="win flex-1">
    <div class="win-title">{{ __('pms.common.home') }}</div>
    <div class="p-4 text-muted">
        <p>{{ __('pms.common.available_screens') }}</p>
        <ul class="list-disc ml-5 mt-2 text-ink">
            @can('permission', 'reservations.view')<li><a class="text-brand-600 hover:underline" href="{{ route('reservations.navigator') }}">{{ __('pms.menu.reservation') }} → {{ __('pms.menu.reservation_navigator') }}</a></li>@endcan
            @can('permission', 'setup.outlets')<li><a class="text-brand-600 hover:underline" href="{{ route('setup.outlets.index') }}">{{ __('pms.menu.setup') }} → {{ __('pms.menu.outlets') }}</a></li>@endcan
            @can('permission', 'setup.transaction_codes')<li><a class="text-brand-600 hover:underline" href="{{ route('setup.transaction-codes.index') }}">{{ __('pms.menu.setup') }} → {{ __('pms.menu.department_codes') }}</a></li>@endcan
        </ul>
    </div>
</div>
@endsection
