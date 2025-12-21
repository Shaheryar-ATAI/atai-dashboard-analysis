@extends('layouts.app')

@section('title', 'Project Coordinator — ATAI')

@push('head')
    {{-- Extra styling for coordinator page --}}
    <style>
        .coordinator-kpi-card {
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        }

        .coordinator-kpi-value {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .coordinator-toggle .btn {
            min-width: 190px;
        }

        .table-actions {
            white-space: nowrap;
        }

        #tblCoordinatorProjects td:last-child,
        #tblCoordinatorSalesOrders td:last-child {
            text-align: right;
        }

        .upload-block {
            border-radius: 1rem;
            border-style: dashed;
        }

        .modal-lg-coordinator {
            max-width: 900px;
        }

        .coordinator-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #9ca3af;
        }

        /* Modal layering + look */
        .modal-atai.show {
            z-index: 2000 !important;
            pointer-events: auto !important;
        }

        .modal-atai.show .modal-dialog,
        .modal-atai .modal-content {
            background-color: #111827;
            color: #f9fafb;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }

        .modal-backdrop.show {
            z-index: 1990 !important;
            pointer-events: none !important;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .toast,
        .toast-container {
            z-index: 2010 !important;
        }

        /* Filter bar styles (region + month + dates + excel) */
        .coord-filter-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            margin-bottom: 0.15rem; /* was .25rem */
        }

        .coord-chip-group .coord-chip {
            border-radius: 999px;
            padding: 0.25rem 0.9rem;
            border: 1px solid #4b5563;
            background: #020617;
            color: #e5e7eb;
            font-size: 0.8rem;
            margin-right: .35rem;
            cursor: pointer;
            transition: all .15s ease;
        }

        .coord-chip:hover {
            background: #0f172a;
        }

        .coord-chip.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, .5);
        }

        .coord-filter-note {
            font-size: 0.8rem;
        }

        .btnDeleteCoordinator i.bi-trash {
            color: #ff4b4b !important;
            font-size: 1.1rem !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        {{-- HEADER --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">Project Coordinator Panel</h4>
                <small class="text-muted">
                    Region scope:
                    @if(in_array($userRegion, ['western']))
                        Madinah Factory
                    @else
                        Jubail Factory
                    @endif
                </small>
            </div>
        </div>

        {{-- 🔽 FILTER BAR (Region / Month / Dates / Excel) 🔽 --}}
        @php
            // Options coming directly from controller
            $salesmen = $salesmenFilterOptions ?? [];

            // Enforce uppercase codes
            $salesmen = array_map('strtoupper', $salesmen);

            // Detect Western coordinator (Niyaz)
            $isWesternCoordinator = strtolower($userRegion) === 'western';

            // If Western coordinator → only Ahmed + Abdo
            if ($isWesternCoordinator) {
                $salesmen = array_values(array_intersect($salesmen, ['AHMED', 'ABDO']));
            }

                // Coordinator “type”


            // Factories: Eastern can see both, Western locked to Madinah
            $factories = $isWesternCoordinator ? ['Madinah'] : ['Jubail', 'Madinah'];

            // Default selection
            $defaultFactory = $isWesternCoordinator ? 'Madinah' : 'Jubail';






        @endphp

                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-end justify-content-between gap-3">
                        <div class="d-flex flex-wrap align-items-end gap-4">

                            {{-- REGION CHIPS --}}
                            {{-- REGION CHIPS --}}
                            <div>
                                <div class="coord-filter-label">Factory Location</div>
                                <div class="coord-chip-group">
                                    @foreach($factories as $f)
                                        <button type="button"
                                                class="coord-chip {{ $f === $defaultFactory ? 'active' : '' }}"
                                                data-factory="{{ $f }}">
                                            {{ $f }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <div class="coord-filter-label">Region</div>
                                <div class="coord-chip-group">
                                    {{-- Always show "All" (for testing), default active only for non-Western --}}
                                    <button type="button"
                                            class="coord-chip {{ $isWesternCoordinator ? '' : 'active' }}"
                                            data-region="all">
                                        All
                                    </button>

                                    @foreach($regionsScope as $r)
                                        @php
                                            $label    = ucfirst($r);               // Eastern / Central / Western
                                            // For Niyaz we still start with "Western" active by default
                                           $isActive = $isWesternCoordinator && strtolower($label) === 'western';
                                @endphp
                                <button type="button"
                                        class="coord-chip {{ $isActive ? 'active' : '' }}"
                                        data-region="{{ $label }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- SALESMAN CHIPS --}}
                    {{-- SALESMAN CHIPS --}}
                    <div>
                        <div class="coord-filter-label">Salesman</div>
                        <div class="coord-chip-group">
                            {{-- Show "All" only for NON-Western coordinators --}}
                            @if (! $isWesternCoordinator)
                                <button type="button" class="coord-chip active" data-salesman="all">All</button>
                            @endif

                            @foreach($salesmen as $s)
                                @php
                                    $upper = strtoupper($s);              // SOHAIB / TARIQ / ...
                                    $label = ucwords(strtolower($s));     // Sohaib / Tariq / ...
                                    // Default active chip for Western (no "All" button)
                                    $isSmActive = $isWesternCoordinator && $loop->first;
                                @endphp
                                <button type="button"
                                        class="coord-chip {{ $isSmActive ? 'active' : '' }}"
                                        data-salesman="{{ $upper }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    {{-- MONTH SELECT --}}
                    <div>
                        <div class="coord-filter-label">Month</div>
                        <select id="coord_month" class="form-select form-select-sm" style="min-width: 140px;">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $monthName = \Carbon\Carbon::create()->month($m)->format('F');
                                @endphp
                                <option value="{{ $m }}">{{ $monthName }}</option>
                            @endfor
                        </select>
                    </div>

                    {{-- DATE RANGE --}}
                    <div class="col-auto">
                        <div class="coord-filter-label">From</div>
                        <input type="date" id="coord_from" class="form-control form-control-sm" style="width: 160px;">
                    </div>

                    <div class="col-auto">
                        <div class="coord-filter-label">To</div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="date" id="coord_to" class="form-control form-control-sm" style="width: 160px;">
                            <button type="button" id="coord_reset_filters" class="btn btn-outline-secondary btn-sm">
                                Reset Filters
                            </button>
                        </div>
                    </div>

                </div>

                {{-- Right: Excel note + button --}}
                <div class="text-end">
                    <small class="text-muted d-block mb-1">
                        Excel download is for <strong>Sales Order Log</strong> with the filters above applied.
                    </small>
                    <div class="d-flex gap-2 justify-content-end">
                        <button id="coord_download_excel_month"
                                type="button"
                                class="btn btn-outline-success btn-sm">
                            Download Selected Month
                        </button>
                        <button id="coord_download_excel_year"
                                type="button"
                                class="btn btn-outline-primary btn-sm">
                            Download Full Year
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="row g-3 mb-3">
            {{-- TOTAL PROJECTS --}}
            <div class="col-md-4">
                <div class="card bg-dark text-light coordinator-kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small text-secondary">Total Projects</div>
                            <div class="coordinator-kpi-value" id="kpiProjectsCount">
                                {{ number_format($kpiProjectsCount) }}
                            </div>
                        </div>
                        <div class="display-6">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SALES ORDERS COUNT --}}
            <div class="col-md-4">
                <div class="card bg-dark text-light coordinator-kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small text-secondary">Sales Orders Count</div>
                            <div class="coordinator-kpi-value" id="kpiSalesOrdersCount">
                                {{ number_format($kpiSalesOrdersCount) }}
                            </div>
                        </div>
                        <div class="display-6">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SALES ORDERS VALUE (SAR) --}}
            <div class="col-md-4">
                <div class="card bg-dark text-light coordinator-kpi-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small text-secondary">Sales Orders Value (SAR)</div>
                            <div class="coordinator-kpi-value">
                                SAR <span id="kpiSalesOrdersValue">{{ number_format($kpiSalesOrdersValue, 0) }}</span>
                            </div>
                        </div>
                        <div class="display-6">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHART --}}
        <div class="row g-3 mb-3">
            <div class="col-lg-12">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Regional Summary (Quotation vs PO Value)</span>
                    </div>
                    <div class="card-body">
                        <div id="coordinatorRegionStacked" style="height: 360px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TOGGLE BUTTONS --}}
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center coordinator-toggle">
                <div class="btn-group" role="group" aria-label="Coordinator table toggle">
                    <button id="btnShowProjects" class="btn btn-primary btn-sm active">
                        <i class="bi bi-list-task me-1"></i> Inquiries (Projects)
                    </button>
                    <button id="btnShowSalesOrders" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-receipt-cutoff me-1"></i> Sales Order Log
                    </button>
                </div>
                <small class="text-muted">
                    Use the view button to open full details and coordinator fields.
                </small>
            </div>
        </div>

        {{-- TABLES --}}
        <div class="card">
            <div class="card-body">
                {{-- Projects table --}}
                <div id="wrapProjectsTable">
                    <table id="tblCoordinatorProjects"
                           class="table table-sm table-striped table-hover align-middle w-100">
                        <thead>
                        <tr>
                            <th>Quotation No</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Salesman</th>
                            <th>Area</th>
                            <th>ATAI Products</th>
                            <th>Quotation Date</th>
                            <th>Status</th>

                            <th style="width: 1%;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($projects as $p)
                            <tr>
                                <td>{{ $p->quotation_no ?? '-' }}</td>
                                <td>{{ $p->project ?? '-' }}</td>
                                <td>{{ $p->client ?? '-' }}</td>
                                <td>{{ $p->salesman ?? '-' }}</td>
                                <td>{{ $p->area ?? '-' }}</td>
                                <td>{{ $p->atai_products ?? '-' }}</td>
                                <td>{{ optional($p->quotation_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ strtoupper(trim($p->status ?? '')) ?: 'BIDDING' }}</td>
                                <td class="table-actions">
                                    <div class="btn-group btn-group-sm" role="group">
                                        {{-- View button --}}
                                        <button
                                            type="button"
                                            class="btn btn-outline-light btnViewCoordinator"
                                            data-source="project"
                                            data-id="{{ $p->id }}"
                                            data-project="{{ $p->project }}"
                                            data-client="{{ $p->client }}"
                                            data-salesman="{{ $p->salesman }}"
                                            data-location="{{ $p->location }}"
                                            data-area="{{ $p->area }}"
                                            data-quotation-no="{{ $p->quotation_no }}"
                                            data-quotation-date="{{ optional($p->quotation_date)->format('Y-m-d') }}"
                                            data-date-received="{{ optional($p->date_received)->format('Y-m-d') }}"
                                            data-products="{{ $p->atai_products }}"
                                            data-price="{{ $p->quotation_value }}"
                                            data-status="{{ strtoupper(trim($p->status ?? 'BIDDING')) }}"
                                        >
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        {{-- Delete button (clear for naive user) --}}
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btnDeleteCoordinator"
                                            data-source="project"
                                            data-id="{{ $p->id }}"
                                            data-label="{{ $p->quotation_no ?? 'this inquiry' }}"
                                        >
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

{{--                --}}{{-- Sales Orders table --}}
{{--                <div id="wrapSalesOrdersTable" class="d-none">--}}
{{--                    <table id="tblCoordinatorSalesOrders"--}}
{{--                           class="table table-sm table-striped table-hover align-middle w-100">--}}
{{--                        <thead>--}}
{{--                        <tr>--}}
{{--                            <th>PO No</th>--}}
{{--                            <th>Quotation No</th>--}}
{{--                            <th>Job No</th>--}}
{{--                            <th>Project</th>--}}
{{--                            <th>Client</th>--}}
{{--                            <th>Salesman</th>--}}
{{--                            <th>Area</th>--}}
{{--                            <th>ATAI Products</th>--}}
{{--                            <th>PO Date</th>--}}
{{--                            <th>PO Value (SAR)</th>--}}
{{--                            <th>Value with VAT (SAR)</th>--}}
{{--                            <th>Created By</th>--}}
{{--                            <th style="width: 1%;">Action</th>--}}
{{--                        </tr>--}}
{{--                        </thead>--}}
{{--                        <tbody>--}}
{{--                        @foreach($salesOrders as $so)--}}
{{--                            <tr>--}}
{{--                                <td>{{ $so->po_no ?? '-' }}</td>--}}
{{--                                <td>{{ $so->quotation_no ?? '-' }}</td>--}}
{{--                                <td>{{ $so->job_no ?? '-' }}</td>--}}
{{--                                <td>{{ $so->project ?? '-' }}</td>--}}
{{--                                <td>{{ $so->client ?? '-' }}</td>--}}
{{--                                <td>{{ $so->salesman ?? '-' }}</td>--}}
{{--                                <td>{{ $so->area ?? '-' }}</td>--}}
{{--                                <td>{{ $so->atai_products ?? '-' }}</td>--}}
{{--                                <td>{{ optional($so->po_date)->format('Y-m-d') ?? '-' }}</td>--}}
{{--                                <td>{{ number_format($so->total_po_value ?? 0, 0) }}</td>--}}
{{--                                <td>{{ number_format($so->value_with_vat ?? (($so->total_po_value ?? 0) * 1.15), 0) }}</td>--}}
{{--                                <td>{{ optional($so->creator)->name ?? '-' }}</td>--}}

{{--                                <td class="table-actions">--}}
{{--                                    <div class="btn-group btn-group-sm" role="group">--}}
{{--                                        --}}{{-- View button --}}
{{--                                        <button--}}
{{--                                            type="button"--}}
{{--                                            class="btn btn-outline-light btnViewCoordinator"--}}
{{--                                            data-source="salesorder"--}}
{{--                                            data-id="{{ $so->id }}"--}}
{{--                                            data-project="{{ $so->project }}"--}}
{{--                                            data-client="{{ $so->client }}"--}}
{{--                                            data-salesman="{{ $so->salesman }}"--}}
{{--                                            data-location="{{ $so->location }}"--}}
{{--                                            data-area="{{ $so->area }}"--}}
{{--                                            data-quotation-no="{{ $so->quotation_no }}"--}}
{{--                                            data-quotation-date="{{ optional($so->quotation_date)->format('Y-m-d') }}"--}}
{{--                                            data-date-received="{{ optional($so->date_received)->format('Y-m-d') }}"--}}
{{--                                            data-products="{{ $so->atai_products }}"--}}
{{--                                            data-price="{{ $so->quotation_value }}"--}}
{{--                                            data-status="{{ strtoupper(trim($so->status ?? '')) }}"--}}
{{--                                            data-oaa="{{ $so->oaa }}"--}}
{{--                                            data-job-no="{{ $so->job_no }}"--}}
{{--                                            data-payment-terms="{{ $so->payment_terms }}"--}}
{{--                                            data-remarks="{{ $so->remarks }}"--}}
{{--                                            data-po-no="{{ $so->po_no }}"--}}
{{--                                            data-po-date="{{ optional($so->po_date)->format('Y-m-d') }}"--}}
{{--                                            data-po-value="{{ $so->total_po_value }}"--}}
{{--                                        >--}}
{{--                                            <i class="bi bi-eye"></i> View--}}
{{--                                        </button>--}}

{{--                                        --}}{{-- Delete button --}}
{{--                                        <button--}}
{{--                                            type="button"--}}
{{--                                            class="btn btn-outline-danger btnDeleteCoordinator"--}}
{{--                                            data-source="salesorder"--}}
{{--                                            data-id="{{ $so->id }}"--}}
{{--                                            data-label="{{ $so->po_no ?? $so->quotation_no ?? 'this sales order' }}"--}}
{{--                                        >--}}
{{--                                            <i class="bi bi-trash"></i> Delete--}}
{{--                                        </button>--}}
{{--                                    </div>--}}
{{--                                </td>--}}
{{--                            </tr>--}}
{{--                        @endforeach--}}
{{--                        </tbody>--}}
{{--                    </table>--}}
{{--                </div>--}}

                {{-- Sales Orders table --}}
                <div id="wrapSalesOrdersTable" class="d-none">
                    <table id="tblCoordinatorSalesOrders"
                           class="table table-sm table-striped table-hover align-middle w-100">
                        <thead>
                        <tr>
                            <th>Client</th>                  {{-- 0 --}}
                            <th>PO No</th>                   {{-- 1 --}}
                            <th>PO Value (SAR)</th>          {{-- 2 --}}
                            <th>Quotation No(s)</th>         {{-- 3 --}}
                            <th>Project</th>                 {{-- 4 --}}
                            <th>Job No</th>                  {{-- 5 --}}
                            <th>PO Date</th>                 {{-- 6 (Date Rec) --}}
                            <th>Salesman</th>                {{-- 7 --}}
                            <th>Area</th>                    {{-- 8 --}}
                            <th>ATAI Products</th>           {{-- 9 --}}
                            <th>Value with VAT (SAR)</th>    {{-- 10 --}}

                            <th style="width: 1%;">Action</th> {{-- 12 --}}
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($salesOrders as $so)
                            <tr>
                                <td>{{ $so->client ?? '-' }}</td>                           {{-- 0 --}}
                                <td>{{ $so->po_no ?? '-' }}</td>                            {{-- 1 --}}
                                <td>{{ number_format($so->total_po_value ?? 0, 0) }}</td>   {{-- 2 --}}
                                <td>{{ $so->quotation_no ?? '-' }}</td>                     {{-- 3 --}}
                                <td>{{ $so->project ?? '-' }}</td>                          {{-- 4 --}}
                                <td>{{ $so->job_no ?? '-' }}</td>                           {{-- 5 --}}
                                <td>{{ \Illuminate\Support\Carbon::parse($so->po_date)->format('Y-m-d') }}</td> {{-- 6 --}}
                                <td>{{ $so->salesman ?? '-' }}</td>                         {{-- 7 --}}
                                <td>{{ $so->area ?? '-' }}</td>                             {{-- 8 --}}
                                <td>{{ $so->atai_products ?? '-' }}</td>                    {{-- 9 --}}
                                <td>{{ number_format($so->value_with_vat ?? 0, 0) }}</td>   {{-- 10 --}}


                                <td class="table-actions">                                   {{-- 12 --}}
                                    <div class="btn-group btn-group-sm" role="group">
                                        {{-- View button --}}
                                        <button
                                            type="button"
                                            class="btn btn-outline-light btnViewCoordinator"
                                            data-source="salesorder"
                                            data-id="{{ $so->id }}" {{-- MIN(id) of group --}}
                                            data-project="{{ $so->project }}"
                                            data-client="{{ $so->client }}"
                                            data-salesman="{{ $so->salesman }}"
                                            data-location="" {{-- optional --}}
                                            data-area="{{ $so->area }}"
                                            data-quotation-no="{{ $so->quotation_no }}" {{-- combined list --}}
                                            data-quotation-date="{{ $so->po_date }}"
                                            data-date-received="{{ $so->po_date }}"
                                            data-products="{{ $so->atai_products }}"
                                            data-price="{{ $so->total_po_value }}"
                                            data-status=""
                                            data-oaa=""
                                            data-job-no="{{ $so->job_no }}"
                                            data-payment-terms=""
                                            data-remarks=""
                                            data-po-no="{{ $so->po_no }}"
                                            data-po-date="{{ $so->po_date }}"
                                            data-po-value="{{ $so->total_po_value }}"
                                        >
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        {{-- Delete --}}
                                        <button
                                            type="button"
                                            class="btn btn-outline-danger btnDeleteCoordinator"
                                            data-source="salesorder"
                                            data-id="{{ $so->id }}"
                                            data-label="{{ $so->po_no ?? $so->quotation_no ?? 'this sales order' }}"
                                        >
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>




            </div>
        </div>
    </div>

    {{-- MODAL --}}
    <div class="modal fade modal-atai" id="coordinatorModal" tabindex="-1" aria-labelledby="coordinatorModalLabel"
         data-bs-backdrop="static" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-lg-coordinator modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Project Details &amp; Coordinator Update
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="coordinatorForm" enctype="multipart/form-data">
                        <input type="hidden" name="source" id="coord_source">
                        <input type="hidden" name="record_id" id="coord_record_id">

                        <div class="row g-3">
                            {{-- LEFT COLUMN: details --}}
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <div class="coordinator-label">Project</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_project"
                                           name="project">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Client</div>
                                    <textarea class="form-control form-control-sm"
                                              id="coord_client"
                                              name="client"
                                              rows="2"></textarea>
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Salesperson</div>

                                    <select name="salesman"
                                            id="coord_salesman" {{-- keep this id for JS --}}
                                            class="form-select form-select-sm"
                                            required>
                                        <option value="">Select...</option>
                                        @foreach($salesmen as $sm)
                                            @php
                                                $value = strtoupper(trim($sm));           // SOHAIB, TARIQ, ...
                                                $label = ucwords(strtolower($sm));        // Sohaib, Tariq, ...
                                            @endphp
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Location</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_location"
                                           name="location">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Area</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_area"
                                           name="area">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Quotation No</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_quotation_no"
                                           name="quotation_no">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Quotation Date</div>
                                    <input type="date"
                                           class="form-control form-control-sm"
                                           id="coord_quotation_date"
                                           name="quotation_date">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Date Received</div>
                                    <input type="date"
                                           class="form-control form-control-sm"
                                           id="coord_date_received"
                                           name="date_received">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">ATAI Products</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_products"
                                           name="atai_products">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Price</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_price"
                                           name="quotation_value">
                                </div>

                                <div class="mb-2">
                                    <div class="coordinator-label">Status</div>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="coord_status"
                                           readonly> {{-- keep status readonly; OAA dropdown controls Status --}}
                                </div>

                            </div>

                            {{-- RIGHT COLUMN: editable coordinator fields --}}
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <div class="coordinator-label">Job No</div>
                                    <input type="text" name="job_no" id="coord_job_no"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO No</div>
                                    <input type="text" name="po_no" id="coord_po_no"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO Date</div>
                                    <input type="date" name="po_date" id="coord_po_date"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO Value (SAR)</div>
                                    <input type="number" step="0.01" name="po_value" id="coord_po_value"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="mb-2" id="coord_multi_block" style="display: none;">
                                    <div class="coordinator-label">Multiple Quotations</div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="coord_multi_enabled">
                                        <label class="form-check-label small" for="coord_multi_enabled">
                                            This PO covers multiple quotations (search and select).
                                        </label>
                                    </div>

                                    {{-- Search box --}}
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text"
                                               id="coord_multi_search"
                                               class="form-control"
                                               placeholder="Type quotation no and press Enter"
                                               disabled>
                                        <button type="button"
                                                class="btn btn-outline-light"
                                                id="coord_multi_search_btn"
                                                disabled>
                                            Search
                                        </button>
                                    </div>

                                    {{-- Search results (checkbox list) --}}
                                    <div id="coord_multi_container"
                                         class="border rounded p-2 small"
                                         style="max-height: 200px; overflow-y: auto;">
                                        <div class="text-muted">Enable multiple quotations and search above.</div>
                                    </div>

                                    {{-- Selected summary --}}
                                    <div class="mt-2 small">
                                        <strong>Selected extra quotations:</strong>
                                        <div id="coord_multi_selected_list" class="mt-1 text-info">
                                            (none)
                                        </div>
                                        <div class="mt-1">
                                            <strong>Total selected quotation value:</strong>
                                            SAR <span id="coord_multi_total_qv">0</span><br>
                                            <span class="text-muted">
                PO value will be split proportionally based on quotation values
                (main quotation + selected ones).
            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Payment Terms</div>
                                    <select name="payment_terms" id="coord_payment_terms"
                                            class="form-select form-select-sm">
                                        <option value="">Select payment terms</option>
                                        <option value="Advance">Advance</option>
                                        <option value="30 Days">30 Days</option>
                                        <option value="60 Days">60 Days</option>
                                        <option value="90 Days">90 Days</option>
                                        <option value="As per contract">As per contract</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">OAA</div>
                                    <select name="oaa" id="coord_oaa" class="form-select form-select-sm">
                                        <option value="">Select OAA status</option>
                                        <option value="Acceptance">Acceptance</option>
                                        <option value="Pre-acceptance">Pre-acceptance</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Waiting">Waiting</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Remarks</div>
                                    <textarea name="remarks" id="coord_remarks" rows="4"
                                              class="form-control form-control-sm"></textarea>
                                </div>
                            </div>
                        </div>
                        {{-- Existing documents list --}}
                        <div class="card h-100 mt-3">
                            <div class="card-header">Existing Documents</div>
                            <div class="card-body">
                                <ul id="coord_attachments_list" class="list-unstyled small mb-0">
                                    <li class="text-muted">No documents loaded.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Optional: upload section inside modal --}}
                        <div class="card h-100 mt-3">
                            <div class="card-header">Upload Documents</div>
                            <div class="card-body">
                                <div class="p-3 upload-block border border-2 text-center">
                                    <p class="mb-2">Upload related files (PO, Job Card, Email, etc.).</p>
                                    <p class="text-muted small mb-3">
                                        Multiple files allowed (max 4–5 files recommended).
                                    </p>
                                    <input type="file" class="form-control mb-2" name="attachments[]" multiple>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>


                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="button" class="btn btn-primary" id="btnCoordinatorSave">

                        Po Received
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>

    <script>
        (function () {
            const fmtSAR = value => {
                if (value === null || value === undefined) return '0';
                return new Intl.NumberFormat('en-SA', {
                    style: 'currency',
                    currency: 'SAR',
                    maximumFractionDigits: 0
                }).format(Number(value || 0));
            };

            // ----- Salesman alias map -----
            const SALESMAN_ALIASES = {
                'SOHAIB': ['SOHAIB', 'SOAHIB'],
                'TAREQ':  ['TARIQ', 'TAREQ'],
                'JAMAL':  ['JAMAL'],
                'ABDO':   ['ABDO'],
                'AHMED':  ['AHMED'],
            };

            const salesmanChips = document.querySelectorAll('.coord-chip[data-salesman]');
            const regionChips   = document.querySelectorAll('.coord-chip[data-region]');
            const factoryChips  = document.querySelectorAll('.coord-chip[data-factory]');

            const FACTORY_SALESMEN = {
                'Jubail':  ['SOHAIB', 'TAREQ', 'JAMAL'],
                'Madinah': ['AHMED', 'ABDO'],
            };

            // PHP -> JS: user region
            const USER_REGION = '{{ strtolower($userRegion) }}';

            // Default factory based on coordinator region (same logic as Blade defaults)
            let filterFactory = (USER_REGION === 'western') ? 'Madinah' : 'Jubail';

            // Default salesman: western -> first allowed in its factory; others -> all
            let filterSalesman = (USER_REGION === 'western')
                ? '{{ strtoupper($salesmen[0] ?? 'AHMED') }}'
                : 'all';

            // Default region: western -> Western; others -> all
            let filterRegion   = (USER_REGION === 'western') ? 'Western' : 'all';

            let filterMonth    = '';
            let filterFrom     = null;
            let filterTo       = null;

            // ----- Multi-quotation DOM + state -----
            const multiBlock        = document.getElementById('coord_multi_block');
            const multiEnabled      = document.getElementById('coord_multi_enabled');
            const multiContainer    = document.getElementById('coord_multi_container');
            const multiTotalQvSpan  = document.getElementById('coord_multi_total_qv');
            const multiSearchInput  = document.getElementById('coord_multi_search');
            const multiSearchBtn    = document.getElementById('coord_multi_search_btn');
            const multiSelectedList = document.getElementById('coord_multi_selected_list');

            let MULTI_SELECTED_IDS = new Set();   // extra project ids
            let MULTI_QV_BY_ID     = {};          // id -> quotation_value
            let MAIN_QUOTATION_VALUE = 0;

            // ----- Filter DOM -----
            const monthSelect      = document.getElementById('coord_month');
            const fromInput        = document.getElementById('coord_from');
            const toInput          = document.getElementById('coord_to');
            const btnResetFilters  = document.getElementById('coord_reset_filters');

            const elKpiProjects    = document.getElementById('kpiProjectsCount');
            const elKpiSoCount     = document.getElementById('kpiSalesOrdersCount');
            const elKpiSoValueNum  = document.getElementById('kpiSalesOrdersValue');

            const wrapProjects     = document.getElementById('wrapProjectsTable');
            const wrapSalesOrder   = document.getElementById('wrapSalesOrdersTable');
            const btnProj          = document.getElementById('btnShowProjects');
            const btnSO            = document.getElementById('btnShowSalesOrders');

            const coordModalEl     = document.getElementById('coordinatorModal');
            const coordModal       = coordModalEl ? new bootstrap.Modal(coordModalEl) : null;

            const btnSave           = document.getElementById('btnCoordinatorSave');
            const attachmentsListEl = document.getElementById('coord_attachments_list');

            // ---------- Helpers ----------
            function getAllowedSalesmenForFactory(factoryName) {
                return FACTORY_SALESMEN[factoryName] || [];
            }

            function hasAllSalesmanChip() {
                return !!document.querySelector('.coord-chip[data-salesman="all"]');
            }

            // When Salesman=All, export must respect factory scope
            function getSalesmenForExport() {
                const allowed = getAllowedSalesmenForFactory(filterFactory);

                if (filterSalesman && filterSalesman !== 'all') {
                    return allowed.includes(filterSalesman) ? [filterSalesman] : [];
                }
                return allowed; // "all" within factory scope
            }

            function setActiveChipBy(selector, datasetKey, valueUpperOrRaw) {
                const chips = document.querySelectorAll(selector);
                chips.forEach(c => c.classList.remove('active'));

                const target = Array.from(chips).find(c => {
                    const v = (c.dataset[datasetKey] || '').toString();
                    return v.toUpperCase() === valueUpperOrRaw.toString().toUpperCase();
                });

                if (target) target.classList.add('active');
            }

            // ---------- DataTables ----------
            const dtProjects = new DataTable('#tblCoordinatorProjects', {
                pageLength: 25,
                order: [[6, 'desc']] // quotation_date
            });

            const dtSalesOrders = new DataTable('#tblCoordinatorSalesOrders', {
                pageLength: 25,
                order: [[6, 'desc']] // PO Date (index 6)
            });

            // ---------- Custom global filter for BOTH tables ----------
            $.fn.dataTable.ext.search.push(function (settings, data) {
                const tableId = settings.nTable.id;

                if (tableId !== 'tblCoordinatorProjects' && tableId !== 'tblCoordinatorSalesOrders') {
                    return true;
                }

                let areaStr, salesmanStr, dateStr;

                if (tableId === 'tblCoordinatorProjects') {
                    areaStr     = (data[4] || '').trim();
                    salesmanStr = (data[3] || '').trim();
                    dateStr     = data[6] || '';
                } else {
                    areaStr     = (data[8] || '').trim();
                    salesmanStr = (data[7] || '').trim();
                    dateStr     = data[6] || '';
                }

                // ✅ FACTORY filter FIRST (based on salesman membership in factory list)
                const cellSmUpper = (salesmanStr || '').toUpperCase();
                const allowed = getAllowedSalesmenForFactory(filterFactory);

                // If filterFactory set but no allowed list, don't block
                if (allowed.length) {
                    if (!allowed.includes(cellSmUpper)) return false;
                }

                // REGION filter
                if (filterRegion !== 'all') {
                    const cellRegion = (areaStr || '').toUpperCase();
                    const wanted     = filterRegion.toUpperCase();
                    if (cellRegion !== wanted) return false;
                }

                // SALESMAN filter (aliases)
                if (filterSalesman !== 'all') {
                    const cellUpper = (salesmanStr || '').toUpperCase();
                    const aliases   = SALESMAN_ALIASES[filterSalesman] || [filterSalesman];
                    if (!aliases.includes(cellUpper)) return false;
                }

                // DATE filter
                if (!dateStr || dateStr === '-') {
                    return !(filterMonth || filterFrom || filterTo);
                }

                const rowDate = new Date(dateStr);
                if (isNaN(rowDate.getTime())) return true;

                if (filterMonth) {
                    const m = rowDate.getMonth() + 1;
                    if (m !== parseInt(filterMonth, 10)) return false;
                }

                if (filterFrom && rowDate < filterFrom) return false;
                if (filterTo && rowDate > filterTo) return false;

                return true;
            });

            function redrawTables() {
                dtProjects.draw();
                dtSalesOrders.draw();
            }

            // ---------- Factory chips ----------
            factoryChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    factoryChips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');

                    filterFactory = chip.dataset.factory || 'Jubail';

                    // On factory change:
                    // - Non-western: reset salesman to all (if chip exists)
                    // - Western: select first allowed salesman for that factory (since no "all")
                    if (USER_REGION !== 'western' && hasAllSalesmanChip()) {
                        filterSalesman = 'all';
                        salesmanChips.forEach(c => c.classList.remove('active'));
                        const allSalesChip = document.querySelector('.coord-chip[data-salesman="all"]');
                        if (allSalesChip) allSalesChip.classList.add('active');
                    } else {
                        const allowed = getAllowedSalesmenForFactory(filterFactory);
                        const firstAllowedChip = Array.from(salesmanChips).find(c => {
                            const v = (c.dataset.salesman || '').toUpperCase();
                            return allowed.includes(v);
                        });

                        salesmanChips.forEach(c => c.classList.remove('active'));

                        if (firstAllowedChip) {
                            firstAllowedChip.classList.add('active');
                            filterSalesman = (firstAllowedChip.dataset.salesman || '').toUpperCase();
                        } else {
                            // fallback
                            filterSalesman = 'all';
                        }
                    }

                    redrawTables();
                });
            });

            // ---------- KPI recalculation ----------
            function refreshKpis() {
                const projCount = dtProjects.rows({ filter: 'applied' }).count();
                const soCount   = dtSalesOrders.rows({ filter: 'applied' }).count();

                let soTotal = 0;
                dtSalesOrders
                    .column(2, { filter: 'applied' }) // PO Value (SAR) is column 2
                    .data()
                    .each(function (value) {
                        let num = 0;
                        if (typeof value === 'number') {
                            num = value;
                        } else if (typeof value === 'string') {
                            num = parseFloat(value.replace(/[^0-9.-]/g, '')) || 0;
                        }
                        if (!isNaN(num)) soTotal += num;
                    });

                if (elKpiProjects)   elKpiProjects.textContent   = projCount.toLocaleString('en-SA');
                if (elKpiSoCount)    elKpiSoCount.textContent    = soCount.toLocaleString('en-SA');
                if (elKpiSoValueNum) elKpiSoValueNum.textContent = soTotal.toLocaleString('en-SA');
            }

            // ---------- Highcharts ----------
            let regionChart = Highcharts.chart('coordinatorRegionStacked', {
                chart: { type: 'column', backgroundColor: '#0f172a' },
                title: { text: 'PO Value by Region', style: { color: '#e5e7eb' } },
                xAxis: {
                    categories: ['Eastern', 'Central', 'Western'],
                    crosshair: true,
                    labels: { style: { color: '#cbd5e1' } }
                },
                yAxis: {
                    min: 0,
                    title: { text: 'Value (SAR)', style: { color: '#cbd5e1' } },
                    labels: { style: { color: '#cbd5e1' } },
                    gridLineColor: '#1e293b'
                },
                legend: { itemStyle: { color: '#e5e7eb' } },
                tooltip: {
                    shared: true,
                    backgroundColor: '#1e293b',
                    borderColor: '#475569',
                    style: { color: 'white' },
                    formatter: function () {
                        let total = 0;
                        this.points.forEach(p => total += p.y);
                        return (
                            '<b>' + this.x + '</b><br/>' +
                            this.points.map(p => p.series.name + ': ' + fmtSAR(p.y)).join('<br/>') +
                            '<br/><span style="font-weight:600">Total: ' + fmtSAR(total) + '</span>'
                        );
                    }
                },
                plotOptions: { column: { borderWidth: 0 } },
                series: [{ name: 'PO Value', data: [0, 0, 0] }]
            });

            function refreshChartFromTable() {
                if (!regionChart) return;

                const sums = { 'Eastern': 0, 'Central': 0, 'Western': 0 };

                dtSalesOrders.rows({ filter: 'applied' }).every(function () {
                    const row = this.data();

                    // Area index 8, PO Value index 2
                    const areaRaw = (row[8] || '').trim();
                    const areaKey = areaRaw || 'Unknown';

                    let val = row[2];
                    if (typeof val === 'string') val = parseFloat(val.replace(/[^0-9.-]/g, '')) || 0;
                    if (typeof val !== 'number' || isNaN(val)) val = 0;

                    if (areaKey in sums) sums[areaKey] += val;
                });

                const cats = ['Eastern', 'Central', 'Western'];
                const data = cats.map(r => sums[r] || 0);

                regionChart.xAxis[0].setCategories(cats, false);
                regionChart.series[0].setData(data, true);
            }

            // ---------- DataTables draw hooks ----------
            dtProjects.on('draw', function () { refreshKpis(); });
            dtSalesOrders.on('draw', function () { refreshKpis(); refreshChartFromTable(); });

            // ---------- Region chips ----------
            regionChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    regionChips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');

                    filterRegion = chip.dataset.region || 'all';
                    redrawTables();
                });
            });

            // ---------- Salesman chips ----------
            salesmanChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    salesmanChips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');

                    const v = chip.dataset.salesman || 'all';
                    filterSalesman = v === 'all' ? 'all' : v.toUpperCase();

                    redrawTables();
                });
            });

            // ---------- Month / date inputs ----------
            if (monthSelect) {
                monthSelect.addEventListener('change', () => {
                    filterMonth = monthSelect.value || '';
                    redrawTables();
                });
            }

            if (fromInput) {
                fromInput.addEventListener('change', () => {
                    filterFrom = fromInput.value ? new Date(fromInput.value) : null;
                    redrawTables();
                });
            }

            if (toInput) {
                toInput.addEventListener('change', () => {
                    filterTo = toInput.value ? new Date(toInput.value) : null;
                    redrawTables();
                });
            }

            // ---------- Reset filters ----------
            if (btnResetFilters) {
                btnResetFilters.addEventListener('click', () => {

                    // 1) FACTORY reset
                    filterFactory = (USER_REGION === 'western') ? 'Madinah' : 'Jubail';
                    setActiveChipBy('.coord-chip[data-factory]', 'factory', filterFactory);

                    // 2) REGION reset
                    if (USER_REGION === 'western') {
                        filterRegion = 'Western';
                        setActiveChipBy('.coord-chip[data-region]', 'region', 'Western');
                    } else {
                        filterRegion = 'all';
                        setActiveChipBy('.coord-chip[data-region]', 'region', 'all');
                    }

                    // 3) SALESMAN reset
                    if (USER_REGION !== 'western' && hasAllSalesmanChip()) {
                        filterSalesman = 'all';
                        setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');
                    } else {
                        // western: choose first allowed for default factory
                        const allowed = getAllowedSalesmenForFactory(filterFactory);
                        const firstAllowedChip = Array.from(salesmanChips).find(c => {
                            const v = (c.dataset.salesman || '').toUpperCase();
                            return allowed.includes(v);
                        });

                        salesmanChips.forEach(c => c.classList.remove('active'));
                        if (firstAllowedChip) {
                            firstAllowedChip.classList.add('active');
                            filterSalesman = (firstAllowedChip.dataset.salesman || '').toUpperCase();
                        } else {
                            filterSalesman = 'all';
                        }
                    }

                    // 4) dates/month reset
                    filterMonth = '';
                    filterFrom  = null;
                    filterTo    = null;

                    if (monthSelect) monthSelect.value = '';
                    if (fromInput)   fromInput.value   = '';
                    if (toInput)     toInput.value     = '';

                    redrawTables();
                });
            }

            // ---------- Toggle tables ----------
            if (btnProj && btnSO && wrapProjects && wrapSalesOrder) {
                btnProj.addEventListener('click', () => {
                    btnProj.classList.add('btn-primary', 'active');
                    btnProj.classList.remove('btn-outline-primary');

                    btnSO.classList.remove('btn-primary', 'active');
                    btnSO.classList.add('btn-outline-primary');

                    wrapProjects.classList.remove('d-none');
                    wrapSalesOrder.classList.add('d-none');

                    dtProjects.columns.adjust().draw(false);
                });

                btnSO.addEventListener('click', () => {
                    btnSO.classList.add('btn-primary', 'active');
                    btnSO.classList.remove('btn-outline-primary');

                    btnProj.classList.remove('btn-primary', 'active');
                    btnProj.classList.add('btn-outline-primary');

                    wrapProjects.classList.add('d-none');
                    wrapSalesOrder.classList.remove('d-none');

                    dtSalesOrders.columns.adjust().draw(false);
                });
            }

            // ----- Multi-quotation helpers -----
            function recalcMultiTotals() {
                const priceInput = document.getElementById('coord_price');
                MAIN_QUOTATION_VALUE = parseFloat(priceInput?.value || '0') || 0;

                let extraTotal = 0;
                MULTI_SELECTED_IDS.forEach(id => {
                    const qv = parseFloat(MULTI_QV_BY_ID[id] || '0') || 0;
                    extraTotal += qv;
                });

                if (multiSelectedList) {
                    if (MULTI_SELECTED_IDS.size === 0) {
                        multiSelectedList.textContent = '(none)';
                    } else {
                        const cbs = multiContainer
                            ? multiContainer.querySelectorAll('.coord-multi-item:checked')
                            : [];
                        const texts = [];
                        cbs.forEach(cb => {
                            const label = cb.dataset.qno || '';
                            if (label) texts.push(label);
                        });
                        multiSelectedList.textContent = texts.join(', ');
                    }
                }

                const totalQv = MAIN_QUOTATION_VALUE + extraTotal;
                if (multiTotalQvSpan) {
                    multiTotalQvSpan.textContent = new Intl.NumberFormat('en-SA', {
                        maximumFractionDigits: 0
                    }).format(totalQv);
                }
            }

            function setMultiEnabled(enabled) {
                if (!multiEnabled || !multiSearchInput || !multiSearchBtn) return;

                multiEnabled.checked       = enabled;
                multiSearchInput.disabled  = !enabled;
                multiSearchBtn.disabled    = !enabled;

                if (!enabled) {
                    MULTI_SELECTED_IDS = new Set();
                    MULTI_QV_BY_ID     = {};
                    if (multiContainer) {
                        multiContainer.innerHTML =
                            '<div class="text-muted">Enable multiple quotations and search above.</div>';
                    }
                    if (multiSelectedList) multiSelectedList.textContent = '(none)';
                    recalcMultiTotals();
                }
            }

            if (multiEnabled) {
                multiEnabled.addEventListener('change', () => {
                    setMultiEnabled(multiEnabled.checked);
                });
            }

            // ---------- Modal open (view) + delete ----------
            document.addEventListener('click', function (e) {
                // DELETE (soft delete)
                const delBtn = e.target.closest('.btnDeleteCoordinator');
                if (delBtn) {
                    const source = delBtn.dataset.source || '';
                    const id     = delBtn.dataset.id;
                    const label  = delBtn.dataset.label ||
                        (source === 'salesorder' ? 'this sales order' : 'this inquiry');

                    if (!id) return;

                    if (!confirm(`Are you sure you want to delete ${label}? This is a soft delete and can be recovered later by admin.`)) {
                        return;
                    }

                    let url = '';
                    if (source === 'salesorder') {
                        url = "{{ route('coordinator.salesorders.destroy', ['salesorder' => '__ID__']) }}";
                    } else {
                        url = "{{ route('coordinator.projects.destroy', ['project' => '__ID__']) }}";
                    }
                    url = url.replace('__ID__', id);

                    fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                        .then(async resp => {
                            const contentType = resp.headers.get('content-type') || '';
                            let data = null;
                            if (contentType.includes('application/json')) data = await resp.json();

                            if (!resp.ok || !data || !data.ok) {
                                const msg = (data && data.message) ? data.message : 'Error while deleting record.';
                                alert(msg);
                                return;
                            }

                            alert(data.message || 'Record deleted.');
                            window.location.reload();
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Unexpected error while deleting: ' + err.message);
                        });

                    return;
                }

                // VIEW (open modal)
                const btn = e.target.closest('.btnViewCoordinator');
                if (!btn) return;

                const source = btn.dataset.source || '';

                document.getElementById('coord_source').value     = source;
                document.getElementById('coord_record_id').value  = btn.dataset.id || '';

                document.getElementById('coord_project').value       = btn.dataset.project || '';
                document.getElementById('coord_client').value        = btn.dataset.client || '';

                // Salesperson select with alias map
                const salesmanSelect = document.getElementById('coord_salesman');
                if (salesmanSelect) {
                    const rawSm = (btn.dataset.salesman || '').trim().toUpperCase();
                    let canonical = rawSm;

                    if (rawSm) {
                        for (const [canon, aliases] of Object.entries(SALESMAN_ALIASES)) {
                            if (aliases.includes(rawSm)) {
                                canonical = canon;
                                break;
                            }
                        }
                    }

                    if (canonical) {
                        const hasOption = Array.from(salesmanSelect.options)
                            .some(o => o.value === canonical);

                        if (!hasOption) {
                            const opt = new Option(canonical, canonical, true, true);
                            salesmanSelect.add(opt);
                        }

                        salesmanSelect.value = canonical;
                    } else {
                        salesmanSelect.value = '';
                    }
                }

                document.getElementById('coord_location').value       = btn.dataset.location || '';
                document.getElementById('coord_area').value           = btn.dataset.area || '';
                document.getElementById('coord_quotation_no').value   = btn.dataset.quotationNo || '';
                document.getElementById('coord_quotation_date').value = btn.dataset.quotationDate || '';
                document.getElementById('coord_date_received').value  = btn.dataset.dateReceived || '';
                document.getElementById('coord_products').value       = btn.dataset.products || '';
                document.getElementById('coord_price').value          = btn.dataset.price || '';
                document.getElementById('coord_status').value         = btn.dataset.status || '';

                document.getElementById('coord_job_no').value        = btn.dataset.jobNo || '';
                document.getElementById('coord_po_no').value         = btn.dataset.poNo || '';
                document.getElementById('coord_po_date').value       = btn.dataset.poDate || '';
                document.getElementById('coord_po_value').value      = btn.dataset.poValue || '';
                document.getElementById('coord_payment_terms').value = btn.dataset.paymentTerms || '';
                document.getElementById('coord_remarks').value       = btn.dataset.remarks || '';
                document.getElementById('coord_oaa').value           = btn.dataset.oaa || '';

                // Multi-quotation section
                if (multiBlock && multiContainer && multiEnabled) {
                    if (source === 'project') {
                        multiBlock.style.display = 'block';
                    } else {
                        multiBlock.style.display = 'none';
                    }

                    MULTI_SELECTED_IDS = new Set();
                    MULTI_QV_BY_ID     = {};
                    setMultiEnabled(false);
                    recalcMultiTotals();
                }

                if (attachmentsListEl) {
                    attachmentsListEl.innerHTML =
                        '<li class="text-muted">Documents list will appear here (after upload).</li>';
                }

                if (source !== 'salesorder') {
                    if (coordModal) coordModal.show();
                    return;
                }

                // For salesorder, load attachments list first
                const soId = btn.dataset.id;

                if (attachmentsListEl) {
                    attachmentsListEl.innerHTML = '<li class="text-muted">Loading documents...</li>';
                }

                const url = "{{ route('coordinator.salesorders.attachments', ['salesorder' => '__ID__']) }}"
                    .replace('__ID__', soId);

                fetch(url)
                    .then(r => r.json())
                    .then(res => {
                        if (!attachmentsListEl) return;

                        if (!res.ok) {
                            attachmentsListEl.innerHTML =
                                '<li class="text-danger">Unable to load documents.</li>';
                            return;
                        }

                        const atts = res.attachments || [];
                        if (atts.length === 0) {
                            attachmentsListEl.innerHTML =
                                '<li class="text-muted">No documents uploaded.</li>';
                            return;
                        }

                        attachmentsListEl.innerHTML = '';
                        atts.forEach(a => {
                            const li = document.createElement('li');
                            li.className = 'mb-1';

                            const link = document.createElement('a');
                            link.href   = a.url;
                            link.target = '_blank';
                            link.rel    = 'noopener';
                            link.textContent = a.original_name || 'Document';

                            const meta = document.createElement('span');
                            meta.className = 'text-muted ms-1';
                            meta.textContent = a.created_at ? ` (${a.created_at})` : '';

                            li.appendChild(link);
                            li.appendChild(meta);
                            attachmentsListEl.appendChild(li);
                        });
                    })
                    .catch(err => {
                        console.error(err);
                        if (attachmentsListEl) {
                            attachmentsListEl.innerHTML =
                                '<li class="text-danger">Error loading documents.</li>';
                        }
                    })
                    .finally(() => {
                        if (coordModal) coordModal.show();
                    });
            });

            // ---------- Save PO ----------
            if (btnSave) {
                btnSave.addEventListener('click', async () => {
                    const formEl = document.getElementById('coordinatorForm');
                    const fd = new FormData(formEl);
                    fd.append('record_id', document.getElementById('coord_record_id').value);

                    if (multiEnabled && multiEnabled.checked && multiContainer) {
                        const checkboxes = multiContainer.querySelectorAll('.coord-multi-item:checked');
                        checkboxes.forEach(cb => {
                            fd.append('extra_project_ids[]', cb.value);
                        });
                    }

                    btnSave.disabled = true;
                    btnSave.innerText = 'Saving...';

                    try {
                        const resp = await fetch("{{ route('coordinator.storePo') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: fd
                        });

                        const contentType = resp.headers.get('content-type') || '';
                        let data = null;

                        if (contentType.includes('application/json')) {
                            data = await resp.json();
                        }

                        if (!resp.ok) {
                            let msg = 'Error while saving PO.';

                            if (data) {
                                if (data.message) msg = data.message;
                                else if (data.errors) msg = Object.values(data.errors).flat().join('\n');
                            } else if (resp.status === 419) {
                                msg = 'Session expired. Please refresh the page and try again.';
                            }

                            alert(msg);
                            btnSave.disabled = false;
                            btnSave.innerText = 'Po Received';
                            return;
                        }

                        const res = data || {};
                        alert(res.message || 'PO saved successfully.');

                        if (res.ok) window.location.reload();
                        else {
                            btnSave.disabled = false;
                            btnSave.innerText = 'Po Received';
                        }

                    } catch (err) {
                        console.error(err);
                        alert('Unexpected error while saving PO: ' + err.message);
                        btnSave.disabled = false;
                        btnSave.innerText = 'Po Received';
                    }
                });
            }

            // ---------- Excel download buttons (FIXED: send FACTORY + scoped salesmen) ----------
            const btnDownloadExcelMonth = document.getElementById('coord_download_excel_month');
            const btnDownloadExcelYear  = document.getElementById('coord_download_excel_year');

            function buildExportParams({ requireMonth = false } = {}) {
                if (requireMonth && (!monthSelect || !monthSelect.value)) {
                    alert('Please select a month before downloading the Excel file.');
                    return null;
                }

                const params = new URLSearchParams();

                if (requireMonth) params.set('month', monthSelect.value);

                // ✅ Always send factory
                params.set('factory', filterFactory || 'Jubail');

                // ✅ Send region
                params.set('region', filterRegion || 'all');

                // ✅ Send scoped salesman list
                const salesmenList = getSalesmenForExport(); // array of canonical names
                params.set('salesmen', salesmenList.join(',')); // "SOHAIB,TARIQ,JAMAL"

                // Optional single salesman selection
                if (filterSalesman !== 'all') {
                    params.set('salesman', filterSalesman);
                }

                if (fromInput && fromInput.value) params.set('from', fromInput.value);
                if (toInput && toInput.value)     params.set('to', toInput.value);

                return params;
            }

            if (btnDownloadExcelMonth) {
                btnDownloadExcelMonth.addEventListener('click', () => {
                    const params = buildExportParams({ requireMonth: true });
                    if (!params) return;

                    const url = "{{ route('coordinator.salesorders.export') }}" + '?' + params.toString();
                    window.location.href = url;
                });
            }

            if (btnDownloadExcelYear) {
                btnDownloadExcelYear.addEventListener('click', () => {
                    const params = buildExportParams({ requireMonth: false });
                    if (!params) return;

                    const url = "{{ route('coordinator.salesorders.exportYear') }}" + '?' + params.toString();
                    window.location.href = url;
                });
            }

            // ---------- Multi-quotation search ----------
            async function performMultiSearch() {
                if (!multiSearchInput || multiSearchInput.disabled) return;

                const term = multiSearchInput.value.trim();
                if (!term) {
                    alert('Please type a quotation number to search.');
                    return;
                }

                if (multiContainer) multiContainer.innerHTML = '<div class="text-muted">Searching...</div>';

                try {
                    const url = "{{ route('coordinator.searchQuotations') }}" +
                        '?term=' + encodeURIComponent(term);

                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();

                    if (!data.ok) {
                        if (multiContainer) {
                            multiContainer.innerHTML =
                                '<div class="text-danger">Search failed: ' + (data.message || '') + '</div>';
                        }
                        return;
                    }

                    const results = data.results || [];
                    if (results.length === 0) {
                        if (multiContainer) {
                            multiContainer.innerHTML =
                                '<div class="text-muted">No quotations found for this search.</div>';
                        }
                        return;
                    }

                    const list = document.createElement('div');

                    results.forEach(p => {
                        const row = document.createElement('div');
                        row.className = 'form-check mb-1';

                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.className = 'form-check-input coord-multi-item';
                        cb.value = p.id;
                        cb.dataset.qv  = p.quotation_value || 0;
                        cb.dataset.qno = p.quotation_no || '';

                        if (MULTI_SELECTED_IDS.has(String(p.id))) cb.checked = true;

                        MULTI_QV_BY_ID[p.id] = p.quotation_value || 0;

                        cb.addEventListener('change', () => {
                            const id = String(cb.value);
                            if (cb.checked) MULTI_SELECTED_IDS.add(id);
                            else MULTI_SELECTED_IDS.delete(id);
                            recalcMultiTotals();
                        });

                        const label = document.createElement('label');
                        label.className = 'form-check-label small';
                        label.innerHTML =
                            `<strong>${p.quotation_no}</strong> – ${p.project || ''}` +
                            ` <span class="text-muted">(${p.area || ''})</span>` +
                            `<br><span class="text-muted">Q. Value: ${fmtSAR(p.quotation_value || 0)}</span>`;

                        row.appendChild(cb);
                        row.appendChild(label);
                        list.appendChild(row);
                    });

                    if (multiContainer) {
                        multiContainer.innerHTML = '';
                        multiContainer.appendChild(list);
                    }
                    recalcMultiTotals();

                } catch (err) {
                    console.error(err);
                    if (multiContainer) {
                        multiContainer.innerHTML =
                            '<div class="text-danger">Error while searching quotations.</div>';
                    }
                }
            }

            if (multiSearchBtn) {
                multiSearchBtn.addEventListener('click', performMultiSearch);
            }
            if (multiSearchInput) {
                multiSearchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') performMultiSearch();
                });
            }

            // ---------- Initial sync ----------
            // Set active chips to match defaults
            setActiveChipBy('.coord-chip[data-factory]', 'factory', filterFactory);
            if (USER_REGION === 'western') setActiveChipBy('.coord-chip[data-region]', 'region', 'Western');
            else setActiveChipBy('.coord-chip[data-region]', 'region', 'all');

            // Salesman default: western choose first in allowed if current invalid
            (function enforceInitialSalesman() {
                const allowed = getAllowedSalesmenForFactory(filterFactory);
                if (filterSalesman === 'all') {
                    if (USER_REGION === 'western') {
                        const firstAllowed = allowed[0];
                        if (firstAllowed) filterSalesman = firstAllowed;
                    }
                } else {
                    // if initial salesman not in allowed for the factory, fix it
                    if (allowed.length && !allowed.includes(filterSalesman)) {
                        filterSalesman = (USER_REGION === 'western') ? (allowed[0] || 'all') : 'all';
                    }
                }

                // reflect UI
                if (filterSalesman === 'all') setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');
                else setActiveChipBy('.coord-chip[data-salesman]', 'salesman', filterSalesman);
            })();

            redrawTables();
            refreshKpis();
            refreshChartFromTable();

        })();
    </script>

@endpush









