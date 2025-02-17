@extends(BaseHelper::getAdminMasterLayoutTemplate())

@push('header-action')
    @if (count($widgets) > 0)
        <!-- <x-core::button
            color="primary"
            :outlined="true"
            class="manage-widget"
            data-bs-toggle="modal"
            data-bs-target="#widgets-management-modal"
            icon="ti ti-layout-dashboard"
        >
            {{ trans('core/dashboard::dashboard.manage_widgets') }}
        </x-core::button> -->
    @endif
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            @if (config('core.base.general.enable_system_updater') && Auth::user()->isSuperUser())
                <v-check-for-updates
                    check-update-url="{{ route('system.check-update') }}"
                    v-slot="{ hasNewVersion, message }"
                    v-cloak
                >
                    <x-core::alert
                        v-if="hasNewVersion"
                        type="warning"
                    >
                        @{{ message }}, please go to <a
                            href="{{ route('system.updater') }}"
                            class="text-warning fw-bold"
                        >System Updater</a> to upgrade to the latest version!
                    </x-core::alert>
                </v-check-for-updates>
            @endif
        </div>

        <div class="col-12">
            {!! apply_filters(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, null) !!}
        </div>

        <div class="col-12">
            <div class="row row-cards">
                @foreach ($statWidgets as $widget)
                    {!! $widget !!}
                @endforeach
                <div class="col dashboard-widget-item col-12 col-md-6 col-lg-3">
                    <a class="text-white d-block rounded position-relative overflow-hidden text-decoration-none" href="{{env('APP_URL')}}/admin/drivers" style="background-color: rgb(50, 197, 210);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="details px-4 py-3 d-flex flex-column justify-content-between">
                                <div class="desc fw-medium">Drivers</div><div class="number fw-bolder">
                                    @php 
                                        $driversCount = \Botble\Driver\Models\Driver::count();
                                    @endphp
                                    <span data-counter="counterup" data-value="{{ $driversCount }}">0</span>
                                </div>
                            </div>
                            <div class="visual ps-1 position-absolute end-0">
                                <i class="icon fas fa-users me-n2" style="opacity: 0.1; --bb-icon-size: 80px;"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
 
    <div class="mb-3 col-12">
        {!! apply_filters(DASHBOARD_FILTER_TOP_BLOCKS, null) !!}
    </div>

    <div class="col-12">
        <div
            id="list_widgets"
            class="row row-cards"
            data-bb-toggle="widgets-list"
        >
            @foreach ($userWidgets as $widget)
                {!! $widget !!}
            @endforeach
        </div>
    </div>
@endsection

@push('footer')
    @include('core/dashboard::partials.modals', compact('widgets'))
@endpush
