@extends('layouts.app')
@section('title', 'Başlangıç')
@section('content')
<div class="win flex-1">
    <div class="win-title">Başlangıç</div>
    <div class="p-4 text-muted">
        <p>Menü çubuğundan bir ekran seçin. Hazır olan ekranlar:</p>
        <ul class="list-disc ml-5 mt-2 text-ink">
            @can('permission', 'setup.outlets')<li><a class="text-brand-600 hover:underline" href="{{ route('setup.outlets.index') }}">Ayarlar → Outlet'ler</a></li>@endcan
            @can('permission', 'setup.transaction_codes')<li><a class="text-brand-600 hover:underline" href="{{ route('setup.transaction-codes.index') }}">Ayarlar → Departman Kodları</a></li>@endcan
        </ul>
    </div>
</div>
@endsection
