@php
    $u = auth()->user();

    $quotationKpiActive = request()->routeIs('projects.index');
    $quotationLogActive = request()->routeIs('projects.inquiries_log');
    $quotationActive = $quotationKpiActive || $quotationLogActive;

    $salesKpiActive = request()->routeIs('salesorders.manager.kpi');
    $salesLogActive = request()->routeIs('salesorders.manager.index');
    $salesActive = $salesKpiActive || $salesLogActive;

    $forecastActive = request()->routeIs('forecast.*');
    $weeklyActive = request()->routeIs('weekly.*');
    $estimationActive = request()->routeIs('estimation.*');
    $bncActive = request()->routeIs('bnc.index');
    $summaryActive = request()->routeIs('performance.salesman*');
    $powerbiActive = request()->routeIs('powerbi.jump');
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

        {{-- Links (single row, no dropdowns) --}}
        <div class="collapse navbar-collapse" id="ataiNav">
            <ul class="nav nav-pills navbar-pills mx-lg-auto mb-2 mb-lg-0">
                @hasanyrole('sales_eastern|sales_central|sales_western|gm|admin')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $quotationActive ? 'active' : '' }}" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-text me-1"></i> Quotation
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ $quotationKpiActive ? 'active' : '' }}"
                               href="{{ route('projects.index') }}">
                                <i class="bi bi-file-earmark-text me-1"></i> Quotation KPI
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ $quotationLogActive ? 'active' : '' }}"
                               href="{{ route('projects.inquiries_log') }}">
                                <i class="bi bi-journal-text me-1"></i> Quotation Log
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $salesActive ? 'active' : '' }}" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-receipt-cutoff me-1"></i> Sales Orders
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ $salesKpiActive ? 'active' : '' }}"
                               href="{{ route('salesorders.manager.kpi') }}">
                                <i class="bi bi-receipt-cutoff me-1"></i> Sales Order KPI
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ $salesLogActive ? 'active' : '' }}"
                               href="{{ route('salesorders.manager.index') }}">
                                <i class="bi bi-receipt me-1"></i> Sales Order Log
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ $estimationActive ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">
                        <i class="bi bi-calculator me-1"></i> Estimation
                    </a>
                </li>
                @endhasanyrole

                @hasanyrole('sales_eastern|sales_central|sales_western')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $forecastActive ? 'active' : '' }}" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bar-chart-line me-1"></i> Forecast
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('forecast.create') ? 'active' : '' }}"
                               href="{{ route('forecast.create') }}">
                                <i class="bi bi-bar-chart-line me-1"></i> New Forecast
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('forecast.list') ? 'active' : '' }}"
                               href="{{ route('forecast.list') }}">
                                <i class="bi bi-list-check me-1"></i> Forecast List
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $weeklyActive ? 'active' : '' }}" href="#"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-calendar-week me-1"></i> Weekly
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('weekly.create') ? 'active' : '' }}"
                               href="{{ route('weekly.create') }}">
                                <i class="bi bi-calendar-plus me-1"></i> New Weekly Report
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('weekly.list') ? 'active' : '' }}"
                               href="{{ route('weekly.list') }}">
                                <i class="bi bi-list-ul me-1"></i> Weekly Report List
                            </a>
                        </li>
                    </ul>
                </li>
                @endhasanyrole

                @hasanyrole('project_coordinator_eastern|project_coordinator_western')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('coordinator.index') ? 'active' : '' }}"
                       href="{{ route('coordinator.index') }}">
                        <i class="bi bi-clipboard-check me-1"></i> Project Coordinator
                    </a>
                </li>
                @endhasanyrole

                @hasanyrole('sales_eastern|sales_central|sales_western|admin|gm')
                <li class="nav-item">
                    <a class="nav-link {{ $bncActive ? 'active' : '' }}"
                       href="{{ route('bnc.index') }}">
                        <i class="bi bi-building-check me-1"></i> BNC Projects
                    </a>
                </li>
                @endhasanyrole

                @hasanyrole('gm|admin')
                <li class="nav-item">
                    <a class="nav-link {{ $summaryActive ? 'active' : '' }}"
                       href="{{ route('performance.salesman') }}">
                        <i class="bi bi-speedometer2 me-1"></i> Summary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $powerbiActive ? 'active' : '' }}"
                       href="{{ route('powerbi.jump') }}">
                        <i class="bi bi-pie-chart me-1"></i> KPI Dashboard
                    </a>
                </li>
                @endhasanyrole
            </ul>

            {{-- Right Section --}}
            <div class="navbar-right d-flex align-items-center">
                <div class="navbar-text me-3">
                    Logged in as <strong>{{ trim($u->name) }}</strong>
                    @if($u->region)
                        <small>{{ strtoupper($u->region) }}</small>
                    @endif
                </div>

                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="btn btn-sm btn-outline-danger atai-logout-btn">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</nav>
