@extends('layouts.admin')

@section('title', trans_choice('general.customers', 2))

@section('new_button')
    @can('create-sales-customers')
        <a href="{{ route('customers.create') }}" class="btn btn-success btn-sm">{{ trans('general.add_new') }}</a>
        <a href="{{ route('import.create', ['group' => 'sales', 'type' => 'customers']) }}" class="btn btn-white btn-sm">{{ trans('import.import') }}</a>
    @endcan
    <a href="{{ route('customers.export', request()->input()) }}" class="btn btn-white btn-sm">{{ trans('general.export') }}</a>
@endsection

@section('content')
    @if ($customers->count() || request()->get('search', false))
        <div class="card">
            <div class="card-header border-bottom-0" :class="[{'bg-gradient-primary': bulk_action.show}]">
                {!! Form::open([
                    'method' => 'GET',
                    'route' => 'customers.index',
                    'role' => 'form',
                    'class' => 'mb-0'
                ]) !!}
                    <div class="align-items-center" v-if="!bulk_action.show">
                        <x-search-string model="App\Models\Common\Contact" />
                    </div>

                    {{ Form::bulkActionRowGroup('general.customers', $bulk_actions, ['group' => 'sales', 'type' => 'customers']) }}
                {!! Form::close() !!}
            </div>

            <div class="table-responsive">
                <table class="table table-flush table-hover">
                    <thead class="thead-light">
                        <tr class="row table-head-line">
                            <th class="col-sm-2 col-md-1 col-lg-1 col-xl-1 d-none d-sm-block">{{ Form::bulkActionAllGroup() }}</th>
                            <th class="col-md-2 col-lg-1 col-xl-1 d-none d-md-block">@sortablelink('name', trans('general.name'), ['filter' => 'active, visible'], ['class' => 'col-aka', 'rel' => 'nofollow'])</th>
                            <th class="col-xs-4 col-sm-4 col-md-4 col-lg-2 col-xl-2 text-left">@sortablelink('telegram_id', trans('general.telegram'))</th>
                            <th class="col-xs-4 col-sm-4 col-md-3 col-lg-2 col-xl-2 text-left">@sortablelink('utm', trans('general.utm'))</th>
                            <th class="col-lg-2 col-xl-2 d-none d-lg-block text-left">@sortablelink('unpaid', trans('general.unpaid'))</th>
                            <th class="col-lg-2 col-xl-2 d-none d-lg-block text-left">@sortablelink('enabled', trans('general.enabled'))</th>
                            <th class="col-lg-1 col-xl-1 d-none d-lg-block text-right">{{ trans('general.actions') }}</th>
                            <th class="col-lg-1 col-xl-1 d-none d-lg-block text-center"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($customers as $item)
                            <tr class="row align-items-center border-top-1">
                                <td class="col-sm-2 col-md-1 col-lg-1 col-xl-1 d-none d-sm-block">
                                    {{ Form::bulkActionGroup($item->id, $item->name) }}
                                </td>
                                <td class="col-md-2 col-lg-1 col-xl-1 d-none d-md-block">
                                    <a class="col-aka" href="{{ route('customers.show', $item->id) }}">{{ $item->name }}</a>
                                </td>
                                <td class="col-xs-4 col-sm-4 col-md-4 col-lg-2 col-xl-2 long-texts text-left">
                                    <el-tooltip content="{{ !empty($item->telegram_chat_id) ? $item->telegram_chat_id : trans('general.na') }}"
                                        effect="dark"
                                        placement="top">
                                        <span>{{ !empty($item->telegram_id) ? $item->telegram_id : trans('general.na') }}</span>
                                    </el-tooltip>
                                </td>
                                <td class="col-lg-2 col-xl-2 d-none d-lg-block text-left">
                                    <span>{{ !empty($item->utm) ? $item->utm : trans('general.na') }}</span>
                                </td>
                                <td class="col-lg-2 col-xl-2 d-none d-lg-block text-left">
                                    @money($item->unpaid, setting('default.currency'), true)
                                </td>
                                <td class="col-lg-2 col-xl-2 d-none d-lg-block text-left">
                                    @if (user()->can('update-sales-customers'))
                                        {{ Form::enabledGroup($item->id, $item->name, $item->enabled) }}
                                    @else
                                        @if ($item->enabled)
                                            <badge rounded type="success" class="mw-60 d-inline-block">{{ trans('general.yes') }}</badge>
                                        @else
                                            <badge rounded type="danger" class="mw-60 d-inline-block">{{ trans('general.no') }}</badge>
                                        @endif
                                    @endif
                                </td>
                                <td class="col-xs-4 col-sm-2 col-md-2 col-lg-1 col-xl-1 text-right">
                                    <div class="dropdown">
                                        <a class="btn btn-neutral btn-sm text-light items-align-center py-2" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fa fa-ellipsis-h text-muted"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right dropdown-menu-arrow">
                                            <a class="dropdown-item" href="{{ route('customers.show', $item->id) }}">{{ trans('general.show') }}</a>
                                            <a class="dropdown-item" href="{{ route('customers.edit', $item->id) }}">{{ trans('general.edit') }}</a>

                                            <div class="dropdown-divider"></div>
                                            @can('create-sales-customers')
                                                <a class="dropdown-item" href="{{ route('customers.duplicate', $item->id) }}">{{ trans('general.duplicate') }}</a>

                                                <div class="dropdown-divider"></div>
                                            @endcan
                                            @can('delete-sales-customers')
                                                {!! Form::deleteLink($item, 'customers.destroy') !!}
                                            @endcan
                                        </div>
                                    </div>
                                </td>
                                <td class="col-lg-1 col-xl-1 d-none d-lg-block text-center"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer table-action">
                <div class="row">
                    @include('partials.admin.pagination', ['items' => $customers])
                </div>
            </div>
        </div>
    @else
        <x-empty-page group="sales" page="customers" />
    @endif
@endsection

@push('scripts_start')
    <script src="{{ asset('public/js/sales/customers.js?v=' . version('short')) }}"></script>
@endpush
