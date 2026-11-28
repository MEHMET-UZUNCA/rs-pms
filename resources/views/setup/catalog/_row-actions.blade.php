{{-- Satır düğmeleri: kaydet (ya da yeni), sil. $row null ise yeni satır. --}}
@if ($row)
    <form method="POST" action="{{ $save }}" id="{{ $form }}" class="inline">@csrf @method('PUT')<button class="btn !h-7">{{ __('pms.common.ok') }}</button></form>
    @if ($delete)<form method="POST" action="{{ $delete }}" class="inline" data-confirm="{{ __('pms.catalog.confirm_delete', ['code' => $row->code]) }}">@csrf @method('DELETE')<button class="btn !h-7">{{ __('pms.common.delete') }}</button></form>@endif
@else
    <form method="POST" action="{{ $save }}" id="{{ $form }}">@csrf<button class="btn btn-primary !h-7">{{ __('pms.common.new') }}</button></form>
@endif
