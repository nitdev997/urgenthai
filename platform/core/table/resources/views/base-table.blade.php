@php
    /** @var Botble\Table\Abstracts\TableAbstract $table */
@endphp

<div class="table-wrapper">
    @if ($table->hasFilters())
        <x-core::card
            class="mb-3 table-configuration-wrap"
            @style(['display: none' => !$table->isFiltering(), 'display: block' => $table->isFiltering()])
        >
            <x-core::card.body>
                <x-core::button
                    type="button"
                    icon="ti ti-x"
                    :icon-only="true"
                    class="btn-show-table-options rounded-pill"
                    size="sm"
                />

                {!! $table->renderFilter() !!}
            </x-core::card.body>
        </x-core::card>
    @endif

    <x-core::card @class([
        'has-actions' => $table->hasBulkActions(),
        'has-filter' => $table->hasFilters(),
    ])>
        <x-core::card.header>
            <div class="btn-list">
                @if ($table->hasBulkActions())
                    <x-core::dropdown
                        type="button"
                        :label="trans('core/table::table.bulk_actions')"
                    >
                        @foreach ($table->getBulkActions() as $action)
                            {!! $action !!}
                        @endforeach
                    </x-core::dropdown>
                @endif

                @if ($table->hasFilters())
                    <x-core::button
                        type="button"
                        class="btn-show-table-options"
                    >
                        {{ trans('core/table::table.filters') }}
                    </x-core::button>
                @endif

                <div class="table-search-input">
                    <label><input type="search" class="form-control input-sm" placeholder="{{ trans('core/table::table.search') }}"></label>
                </div>
            </div>
        </x-core::card.header>

        <div class="card-table">
            <div @class([
                'table-responsive',
                'table-has-actions' => $table->hasBulkActions(),
                'table-has-filter' => $table->hasFilters(),
            ])>
                @section('main-table')
                    {!! $dataTable->table(compact('id', 'class'), false) !!}
                @show
            </div>
        </div>
    </x-core::card>
</div>

@push('footer')
    @include('core/table::modal')

    {!! $dataTable->scripts() !!}
@endpush
