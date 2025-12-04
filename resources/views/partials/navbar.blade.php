@php
    $u = auth()->user();
@endphp

<nav class="navbar navbar-atai navbar-expand-lg bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        {{-- Brand (left) --}}
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2" style="height:32px;">
            <span class="brand-word">ATAI</span>
        </a>

        {{-- Mobile Toggler --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ataiNav"
                aria-controls="ataiNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Links --}}
        <div class="collapse navbar-collapse" id="ataiNav">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                @hasanyrole('sales_eastern|sales_central|sales_western|gm|admin')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.index') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('projects.index') }}">
                        Quotation KPI
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('inquiries.index') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('inquiries.index') }}">
                        Quotation Log
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.kpi') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('salesorders.manager.kpi') }}">
                        Sales Order Log KPI
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.index') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('salesorders.manager.index') }}">
                        Sales Order Log
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('estimation.index') }}">
                        Estimation
                    </a>
                </li>
                @endhasanyrole
                @hasanyrole('sales_eastern|sales_central|sales_western')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('forecast.*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('forecast.create') }}">
                        Forecast
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('weekly.*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('weekly.create') }}">
                        Weekly Reports
                    </a>
                </li>
                @endhasanyrole


                @hasanyrole('estimator')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimator.projects.*') ? 'active' : '' }}"
                       href="{{ route('estimation.reports.index') }}">
                        <i class="bi bi-clipboard-check me-1"></i>
                        Estimation
                    </a>
                </li>
                @endhasanyrole

                @hasanyrole('project_coordinator_eastern|project_coordinator_western')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('coordinator.index') ? 'active' : '' }}"
                       href="{{ route('coordinator.index') }}">
                        <i class="bi bi-clipboard-check me-1"></i>
                        Project Coordinator
                    </a>
                </li>
                @endhasanyrole


                @hasanyrole('sales_eastern|sales_central|sales_western|admin|gm')
                <li class="nav-item">
                    <a href="{{ route('bnc.index') }}"
                       class="nav-link {{ request()->routeIs('bnc.index') ? 'active' : '' }}">
                        <i class="bi bi-building-check me-1"></i>
                        BNC Projects
                    </a>
                </li>
                {{-- GM/Admin only --}}
                @hasanyrole('gm|admin')
{{--                <li class="nav-item">--}}
{{--                    <a class="nav-link {{ request()->routeIs('salesorders.index') ? 'active fw-semibold text-primary' : '' }}"--}}
{{--                       href="{{ route('salesorders.index') }}">--}}
{{--                        Sales Summary--}}
{{--                    </a>--}}
{{--                </li>--}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.area*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('performance.area') }}">
                        Area Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('performance.salesman') }}">
                        Salesman Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.product*') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('performance.product') }}">
                        Product Summary
                    </a>
                </li>





                @endhasanyrole
{{--                <li class="nav-item">--}}
{{--                    <a class="nav-link {{ request()->routeIs('accounts.summary') ? 'active fw-semibold text-primary' : '' }}"--}}
{{--                       href="{{ route('powerbi.jump') }}">--}}
{{--                        Accounts Summary--}}
{{--                    </a>--}}
{{--                </li>--}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active fw-semibold text-primary' : '' }}"
                       href="{{ route('powerbi.jump') }}">
                        KPI'S  Dashboard
                    </a>
                </li>
                @endhasanyrole
            </ul>

            {{-- Right Section --}}
            <div class="navbar-right d-flex align-items-center">
                <div class="navbar-text me-3">
                    Logged in as <strong>{{ $u->name ?? '' }}</strong>
                    @if(!empty($u->region))
                         <small>{{ ucfirst($u->region) }}</small>
                    @endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                    <button class="btn btn-sm btn-outline-danger" type="submit">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
