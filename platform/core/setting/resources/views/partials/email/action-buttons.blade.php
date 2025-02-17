@extends('core/setting::forms.partials.action')

@section('content')
    <x-core::button
        type="button"
        color="info"
        data-bb-toggle="test-email-send"
        :data-saving="trans('core/setting::setting.saving')"
    >
        {{ trans('core/setting::setting.test_send_mail') }}
    </x-core::button>
@stop
