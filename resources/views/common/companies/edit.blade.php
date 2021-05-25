@php
/**
 * @var \App\Models\Common\Company $company
 */
@endphp
@extends('layouts.admin')

@section('title', trans('general.title.edit', ['type' => trans_choice('general.companies', 1)]))

@section('content')
    <div class="card">
        {!! Form::model($company, [
            'id' => 'company',
            'method' => 'PATCH',
            'route' => ['companies.update', $company->id],
            '@submit.prevent' => 'onSubmit',
            '@keydown' => 'form.errors.clear($event.target.name)',
            'files' => true,
            'role' => 'form',
            'class' => 'form-loading-button',
            'novalidate' => true
        ]) !!}

            <div class="card-body">
                <div class="row">
                    {{ Form::textGroup('name', trans('general.name'), 'font') }}

                    {{ Form::emailGroup('email', trans('general.email'), 'envelope') }}

                    {{ Form::selectGroup('currency', trans_choice('general.currencies', 1), 'exchange-alt', $currencies, $company->currency ?? 'USD') }}

                    {{-- Form::textGroup('tax_number', trans('general.tax_number'), 'percent', [], setting('company.tax_number')) --}}

                    {{ Form::numberGroup('telegram_channel_id', 'Telegram private channel id (paywall for add/kick users)', 'paper-plane', ['required' => 'required']) }}

                    {{ Form::textareaGroup('telegram_additional_public_channels', "Telegram public channels id (catch user's invite links)", 'paper-plane', implode(PHP_EOL, $company->getAvailableChannels(false))) }}

                    {{ Form::textGroup('telegram_observer_token', 'Telegram observer token', 'paper-plane', ['required' => 'required', 'placeholder' => 'https://api.telegram.org/bot<token>/']) }}

                    {{ Form::fileGroup('logo', trans('companies.logo'), '', ['dropzone-class' => 'form-file'], $company->company_logo) }}

                    {{ Form::radioGroup('enabled', trans('general.enabled'), $company->enabled) }}

                    {{ Form::radioGroup('install_webhook', 'Reinstall telegram webhook', false) }}

                </div>
            </div>

            @can('update-common-companies')
                <div class="card-footer">
                    <div class="row save-buttons">
                        {{ Form::saveButtons('companies.index') }}
                    </div>
                </div>
            @endcan
        {!! Form::close() !!}
    </div>
@endsection

@push('scripts_start')
    <script src="{{ asset('public/js/common/companies.js?v=' . version('short')) }}"></script>
@endpush
