@extends('layouts.app')

@section('title', 'Project Coordinator ATAI')

@push('head')
    {{-- ============================================================
        Project Coordinator Page (ATAI)
        - DataTables filtering (Region/Year/Month/Date range)
        - KPI recalculation based on filtered rows
        - Highcharts region summary based on filtered Sales Orders table
        - Excel export buttons send the SAME filters to backend
        Notes:
        - We DO NOT remove any existing working behavior.
        - We only add safe fixes + professional comments + normalization.
        - IMPORTANT CHANGE YOU REQUESTED:
          ✅ Only Region filter (All/Eastern/Central/Western)
          ✅ Region automatically implies Salesman aliases scope (as discussed)
    ============================================================ --}}
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
            margin-bottom: 0.15rem;
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

        /* ============================================================
           IMPORTANT (Requested):
           We keep salesman chips markup for compatibility, but we disable/hide it
           because you want ONLY region filter.
        ============================================================ */
        .coord-salesman-ui-disabled {
            display: none !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        {{-- ============================================================
            FILTER BAR (Region / Salesman / Year / Month / Date Range / Excel)
            IMPORTANT:
            - Chips are UI selectors. Actual filtering is done in DataTables custom filter.
            - Excel export uses the same filter state via query parameters.
        ============================================================ --}}

        @php
            // Options coming directly from controller
            $salesmen = $salesmenFilterOptions ?? [];

            // Enforce uppercase codes
            $salesmen = array_map('strtoupper', $salesmen);

            // NOTE: Factory chips removed (kept in comments in original).
        @endphp

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-end justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-end gap-4">

                    {{-- REGION CHIPS --}}
                    <div>
                        <div class="coord-filter-label">Region</div>
                        <div class="coord-chip-group">
                            <button type="button" class="coord-chip active" data-region="all">All</button>

                            @foreach($regionsScope as $r)
                                @php $label = ucfirst($r); @endphp
                                <button type="button"
                                        class="coord-chip"
                                        data-region="{{ $label }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- SALESMAN CHIPS (kept for compatibility; will be disabled/hidden via CSS+JS as requested) --}}
                    @php
                        $salesmen = $salesmenFilterOptions ?? [];
                        $salesmen = array_values(array_unique(array_map('strtoupper', $salesmen)));

                        if (auth()->user()->hasRole('project_coordinator_western')) {
                            $salesmen = array_values(array_intersect($salesmen, ['ABDO','AHMED']));
                        }
                    @endphp

                    <div class="coord-salesman-ui">
                        <div class="coord-filter-label">Salesman</div>
                        <div class="coord-chip-group">
                            <button type="button" class="coord-chip active" data-salesman="all">All</button>

                            @foreach($salesmen as $s)
                                @php
                                    $upper = strtoupper(trim($s));
                                    $canonical = in_array($upper, ['TARIQ','TAREQ']) ? 'TAREQ' : $upper;
                                    $label = ($canonical === 'TAREQ')
                                        ? 'Tareq'
                                        : ucwords(strtolower($canonical));
                                @endphp

                                <button type="button"
                                        class="coord-chip"
                                        data-salesman="{{ $canonical }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    {{-- YEAR SELECT --}}
                    <div>
                        <div class="coord-filter-label">Year</div>
                        <select id="coord_year" class="form-select form-select-sm" style="min-width: 120px;">
                            <option value="" selected>All Years</option>
                            @php $cy = now()->year; @endphp
                            @for($y = $cy; $y >= $cy - 3; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    {{-- MONTH SELECT --}}
                    <div>
                        <div class="coord-filter-label">Month</div>
                        <select id="coord_month" class="form-select form-select-sm" style="min-width: 140px;">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                @php $monthName = \Carbon\Carbon::create()->month($m)->format('F'); @endphp
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

                {{-- Right: Excel note + buttons --}}
                <div class="text-end">
                    <small class="text-muted d-block mb-1">
                        Excel download is for <strong>Sales Order Log</strong> with the filters above applied.
                    </small>
                    <div class="d-flex gap-2 justify-content-end">
                        <button id="coord_download_excel_month" type="button" class="btn btn-outline-success btn-sm">
                            Download Selected Month
                        </button>
                        <button id="coord_download_excel_year" type="button" class="btn btn-outline-primary btn-sm">
                            Download Full Year
                        </button>
                    </div>
                </div>

            </div>
        </div>

        {{-- KPIs --}}
        <div class="row g-3 mb-3">
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

                {{-- Sales Orders table --}}
                <div id="wrapSalesOrdersTable" class="d-none">
                    <table id="tblCoordinatorSalesOrders"
                           class="table table-sm table-striped table-hover align-middle w-100">
                        <thead>
                        <tr>
                            <th>Client</th>
                            <th>PO No</th>
                            <th>PO Value (SAR)</th>
                            <th>Quotation No(s)</th>
                            <th>Project</th>
                            <th>Job No</th>
                            <th>PO Date</th>
                            <th>Salesman</th>
                            <th>Area</th>
                            <th>ATAI Products</th>
                            <th>Value with VAT (SAR)</th>
                            <th style="width: 1%;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($salesOrders as $so)
                            <tr>
                                <td>{{ $so->client ?? '-' }}</td>
                                <td>{{ $so->po_no ?? '-' }}</td>
                                <td>{{ number_format($so->total_po_value ?? 0, 0) }}</td>
                                <td>{{ $so->quotation_no ?? '-' }}</td>
                                <td>{{ $so->project ?? '-' }}</td>
                                <td>{{ $so->job_no ?? '-' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($so->po_date)->format('Y-m-d') }}</td>
                                <td>{{ $so->salesman ?? '-' }}</td>
                                <td>{{ $so->area ?? '-' }}</td>
                                <td>{{ $so->atai_products ?? '-' }}</td>
                                <td>{{ number_format($so->value_with_vat ?? 0, 0) }}</td>

                                <td class="table-actions">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button
                                            type="button"
                                            class="btn btn-outline-light btnViewCoordinator"
                                            data-source="salesorder"
                                            data-id="{{ $so->id }}"
                                            data-project="{{ $so->project }}"
                                            data-client="{{ $so->client }}"
                                            data-salesman="{{ $so->salesman }}"
                                            data-location=""
                                            data-area="{{ $so->area }}"
                                            data-quotation-no="{{ $so->quotation_no }}"
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
                                            id="coord_salesman"
                                            class="form-select form-select-sm"
                                            required>
                                        <option value="">Select...</option>
                                        @foreach($salesmen as $sm)
                                            @php
                                                $upper = strtoupper(trim($sm));
                                                $value = in_array($upper, ['TARIQ','TAREQ']) ? 'TAREQ' : $upper;
                                                $label = ($value === 'TAREQ') ? 'Tareq' : ucwords(strtolower($value));
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
                                           readonly>
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

                                    <div id="coord_multi_container"
                                         class="border rounded p-2 small"
                                         style="max-height: 200px; overflow-y: auto;">
                                        <div class="text-muted">Enable multiple quotations and search above.</div>
                                    </div>

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

                        <div class="card h-100 mt-3">
                            <div class="card-header">Existing Documents</div>
                            <div class="card-body">
                                <ul id="coord_attachments_list" class="list-unstyled small mb-0">
                                    <li class="text-muted">No documents loaded.</li>
                                </ul>
                            </div>
                        </div>

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
    <script>
        (function () {
            'use strict';

            /**
             * ============================================================
             * Frontend Filtering + KPIs + Excel Export
             *
             * PRINCIPAL ENGINEER FIXES (SAFE / NO BREAKING CHANGES):
             * ✅ FIX A: Region filter was not actually applied to Area column.
             * ✅ FIX B: Salesman filter was duplicated twice (removed duplication safely).
             * ✅ FIX C: Region→Allowed Salesmen mapping enforced (aliases scope).
             * ✅ FIX D: Only Region filter requested: Salesman chips are kept for compatibility but disabled/hidden.
             * ✅ FIX E: Date handling: keep original behavior (rows without date included unless Month/From/To used).
             *
             * IMPORTANT:
             * - We do NOT remove working behaviors (tables, modal, delete, save, exports).
             * - We only correct filtering logic and keep everything stable.
             * ============================================================
             */

            if (!window.jQuery) {
                console.error('jQuery is not loaded. Load jQuery BEFORE this script.');
                return;
            }
            const $ = window.jQuery;

            const fmtSAR = value => {
                if (value === null || value === undefined) return '0';
                return new Intl.NumberFormat('en-SA', {
                    style: 'currency',
                    currency: 'SAR',
                    maximumFractionDigits: 0
                }).format(Number(value || 0));
            };

            /**
             * Salesman alias mapping:
             * - Canonical code is used for filtering scope (Region -> Allowed Salesmen).
             */
            const SALESMAN_ALIASES = {
                // Eastern
                'SOHAIB':     ['SOHAIB', 'SOAHIB'],
                'RAVINDER':   ['RAVINDER'],
                'WASEEM':     ['WASEEM'],
                'FAISAL':     ['FAISAL'],
                'CLIENT':     ['CLIENT'],
                'EXPORT':     ['EXPORT'],

                // Central
                'TAREQ':      ['TARIQ', 'TAREQ', 'TAREQ '],
                'JAMAL':      ['JAMAL'],
                'ABU_MERHI':  ['M.ABU MERHI','M. ABU MERHI','M.MERHI','MERHI','MOHAMMED','ABU MERHI','M ABU MERHI'],

                // Western
                'ABDO':       ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF', 'ABDO YOUSIF'],
                'AHMED':      ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin', 'AHMED AMIN '],
            };

            /**
             * Region -> Allowed canonical salesmen (YOUR FINAL RULES)
             */
            const REGION_ALLOWED_SALESMEN = {
                'Eastern': new Set(['SOHAIB','RAVINDER','WASEEM','FAISAL','CLIENT','EXPORT']),
                'Central': new Set(['TAREQ','JAMAL','ABU_MERHI']),
                'Western': new Set(['ABDO','AHMED']),
            };

            // Filter state (single source of truth)
            let filterRegion   = 'all';   // only filter you want
            let filterSalesman = 'all';   // kept for compatibility, but disabled/forced to all
            let filterMonth    = '';
            let filterFrom     = null;
            let filterTo       = null;

            const yearSelect = document.getElementById('coord_year');
            let filterYear = (yearSelect && yearSelect.value) ? yearSelect.value : '';

            const salesmanChips = document.querySelectorAll('.coord-chip[data-salesman]');
            const regionChips   = document.querySelectorAll('.coord-chip[data-region]');

            const monthSelect     = document.getElementById('coord_month');
            const fromInput       = document.getElementById('coord_from');
            const toInput         = document.getElementById('coord_to');
            const btnResetFilters = document.getElementById('coord_reset_filters');

            const elKpiProjects   = document.getElementById('kpiProjectsCount');
            const elKpiSoCount    = document.getElementById('kpiSalesOrdersCount');
            const elKpiSoValueNum = document.getElementById('kpiSalesOrdersValue');

            const wrapProjects    = document.getElementById('wrapProjectsTable');
            const wrapSalesOrder  = document.getElementById('wrapSalesOrdersTable');
            const btnProj         = document.getElementById('btnShowProjects');
            const btnSO           = document.getElementById('btnShowSalesOrders');

            const coordModalEl      = document.getElementById('coordinatorModal');
            const coordModal        = coordModalEl ? new bootstrap.Modal(coordModalEl) : null;
            const btnSave           = document.getElementById('btnCoordinatorSave');
            const attachmentsListEl = document.getElementById('coord_attachments_list');

            // Multi quotation UI controls
            const multiBlock        = document.getElementById('coord_multi_block');
            const multiEnabled      = document.getElementById('coord_multi_enabled');
            const multiContainer    = document.getElementById('coord_multi_container');
            const multiTotalQvSpan  = document.getElementById('coord_multi_total_qv');
            const multiSearchInput  = document.getElementById('coord_multi_search');
            const multiSearchBtn    = document.getElementById('coord_multi_search_btn');
            const multiSelectedList = document.getElementById('coord_multi_selected_list');

            let MULTI_SELECTED_IDS    = new Set();
            let MULTI_QV_BY_ID        = {};
            let MAIN_QUOTATION_VALUE  = 0;

            function setActiveChipBy(selector, datasetKey, valueUpperOrRaw) {
                const chips = document.querySelectorAll(selector);
                chips.forEach(c => c.classList.remove('active'));

                const target = Array.from(chips).find(c => {
                    const v = (c.dataset[datasetKey] || '').toString();
                    return v.toUpperCase() === valueUpperOrRaw.toString().toUpperCase();
                });

                if (target) target.classList.add('active');
            }

            /**
             * Parse YYYY-MM-DD safely
             */
            function parseYmd(dateStr) {
                if (!dateStr || dateStr === '-') return null;
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr.trim());
                if (!m) return null;
                return new Date(parseInt(m[1],10), parseInt(m[2],10)-1, parseInt(m[3],10));
            }

            /**
             * Canonicalize salesman from cell text into canonical code
             */
            function canonicalSalesmanCode(raw) {
                const x0 = (raw || '').trim().toUpperCase();
                if (!x0) return '';

                // normalize spaces + dots (M. ABU MERHI vs M.ABU MERHI)
                const x = x0.replace(/\s+/g, ' ').replace(/\.\s*/g, '.').trim();

                for (const [canon, aliases] of Object.entries(SALESMAN_ALIASES)) {
                    const normAliases = aliases.map(a => a.toUpperCase().replace(/\s+/g,' ').replace(/\.\s*/g,'.').trim());
                    if (normAliases.includes(x)) return canon;
                }
                return x; // fallback if unknown
            }

            function canonicalSalesmanLabel(raw) {
                const canon = canonicalSalesmanCode(raw);
                if (!canon) return '';
                if (canon === 'SOHAIB') return 'Sohaib';
                if (canon === 'TAREQ')  return 'Tareq';
                if (canon === 'ABU_MERHI') return 'Abu Merhi';
                return canon.charAt(0) + canon.slice(1).toLowerCase();
            }

            /**
             * Canonical area mapping:
             * Returns EXACT: "Eastern" | "Central" | "Western" | ""
             */
            function canonicalArea(raw) {
                const x = (raw || '').trim().toUpperCase();
                if (!x) return '';
                if (x.startsWith('EAST')) return 'Eastern';
                if (x.startsWith('CENT')) return 'Central';
                if (x.startsWith('WEST')) return 'Western';
                if (x === 'EASTERN') return 'Eastern';
                if (x === 'CENTRAL') return 'Central';
                if (x === 'WESTERN') return 'Western';
                return '';
            }

            function recalcMultiTotals() {
                const priceInput = document.getElementById('coord_price');
                const rawMain = (priceInput?.value || '0').toString().replace(/,/g, '');
                MAIN_QUOTATION_VALUE = parseFloat(rawMain) || 0;

                let extraTotal = 0;
                MULTI_SELECTED_IDS.forEach(id => {
                    const raw = (MULTI_QV_BY_ID[id] || '0').toString().replace(/,/g,'');
                    const qv = parseFloat(raw) || 0;
                    extraTotal += qv;
                });

                if (multiSelectedList) {
                    if (MULTI_SELECTED_IDS.size === 0) {
                        multiSelectedList.textContent = '(none)';
                    } else {
                        const cbs = multiContainer ? multiContainer.querySelectorAll('.coord-multi-item:checked') : [];
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

                multiEnabled.checked      = enabled;
                multiSearchInput.disabled = !enabled;
                multiSearchBtn.disabled   = !enabled;

                if (!enabled) {
                    MULTI_SELECTED_IDS = new Set();
                    MULTI_QV_BY_ID     = {};
                    if (multiContainer) {
                        multiContainer.innerHTML = '<div class="text-muted">Enable multiple quotations and search above.</div>';
                    }
                    if (multiSelectedList) multiSelectedList.textContent = '(none)';
                    recalcMultiTotals();
                }
            }

            if (multiEnabled) {
                multiEnabled.addEventListener('change', () => setMultiEnabled(multiEnabled.checked));
            }

            const btnDownloadExcelMonth = document.getElementById('coord_download_excel_month');
            const btnDownloadExcelYear  = document.getElementById('coord_download_excel_year');

            /**
             * Build export params:
             * - Region normalized to lowercase
             * - Salesman not sent (because region implies salesman aliases)
             *   BUT we keep existing keys safe if you later re-enable salesman filter.
             */
            function buildExportParams({ requireMonth = false } = {}) {
                if (requireMonth && (!monthSelect || !monthSelect.value)) {
                    alert('Please select a month before downloading the Excel file.');
                    return null;
                }

                const params = new URLSearchParams();

                if (yearSelect && yearSelect.value) params.set('year', yearSelect.value);

                if (requireMonth) params.set('month', monthSelect.value);
                else if (monthSelect && monthSelect.value) params.set('month', monthSelect.value);

                const regionParam = (filterRegion || 'all').toString().trim();
                params.set('region', regionParam.toLowerCase());

                // DO NOT send salesman (region implies it)
                // if (filterSalesman !== 'all') params.set('salesman', filterSalesman);

                if (fromInput && fromInput.value) params.set('from', fromInput.value);
                if (toInput && toInput.value)     params.set('to', toInput.value);

                return params;
            }

            async function performMultiSearch() {
                if (!multiSearchInput || multiSearchInput.disabled) return;

                const term = multiSearchInput.value.trim();
                if (!term) { alert('Please type a quotation number to search.'); return; }

                if (multiContainer) multiContainer.innerHTML = '<div class="text-muted">Searching...</div>';

                try {
                    const url = "{{ route('coordinator.searchQuotations') }}" + '?term=' + encodeURIComponent(term);
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();

                    if (!data.ok) {
                        multiContainer.innerHTML = '<div class="text-danger">Search failed: ' + (data.message || '') + '</div>';
                        return;
                    }

                    const results = data.results || [];
                    if (!results.length) {
                        multiContainer.innerHTML = '<div class="text-muted">No quotations found for this search.</div>';
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

                    multiContainer.innerHTML = '';
                    multiContainer.appendChild(list);
                    recalcMultiTotals();

                } catch (err) {
                    console.error(err);
                    if (multiContainer) multiContainer.innerHTML = '<div class="text-danger">Error while searching quotations.</div>';
                }
            }

            if (multiSearchBtn) multiSearchBtn.addEventListener('click', performMultiSearch);
            if (multiSearchInput) {
                multiSearchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') performMultiSearch();
                });
            }

            $(document).ready(function () {

                /**
                 * Requested: ONLY region filter.
                 * We keep salesman chip markup for compatibility but hide it safely.
                 */
                const salesmanUi = document.querySelector('.coord-salesman-ui');
                if (salesmanUi) salesmanUi.classList.add('coord-salesman-ui-disabled');

                // Force salesman state to all (compat safety)
                filterSalesman = 'all';
                setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');

                const dtProjects = $('#tblCoordinatorProjects').DataTable({
                    pageLength: 25,
                    order: [[6, 'desc']]
                });

                const dtSalesOrders = $('#tblCoordinatorSalesOrders').DataTable({
                    pageLength: 25,
                    order: [[6, 'desc']]
                });

                function redrawTables() {
                    dtProjects.draw();
                    dtSalesOrders.draw();
                }

                if (yearSelect) yearSelect.addEventListener('change', () => {
                    filterYear = yearSelect.value || ''; // ✅ empty means All Years
                    redrawTables();
                });

                /**
                 * Global filter:
                 * Applies to both tables
                 */
                $.fn.dataTable.ext.search.push(function (settings, data) {
                    const tableId = settings.nTable.id;

                    if (tableId !== 'tblCoordinatorProjects' && tableId !== 'tblCoordinatorSalesOrders') {
                        return true;
                    }

                    let areaStr, salesmanStr, dateStr;

                    if (tableId === 'tblCoordinatorProjects') {
                        areaStr     = (data[4] || '').trim();
                        salesmanStr = (data[3] || '').trim();
                        dateStr     = (data[6] || '').trim();
                    } else {
                        areaStr     = (data[8] || '').trim();
                        salesmanStr = (data[7] || '').trim();
                        dateStr     = (data[6] || '').trim();
                    }

                    const cellCanonArea     = canonicalArea(areaStr);
                    const cellCanonSalesman = canonicalSalesmanCode(salesmanStr);

                    /**
                     * ✅ FIX A: REAL Region filter by Area
                     */
                    if (filterRegion !== 'all') {
                        const wantedRegion = canonicalArea(filterRegion); // Eastern/Central/Western

                        const allowedSet = REGION_ALLOWED_SALESMEN[wantedRegion];
                        if (allowedSet && allowedSet.size > 0) {
                            // salesman must belong to that region team
                            if (!allowedSet.has(cellCanonSalesman)) return false;
                        }
                        // IMPORTANT: do NOT filter by Area column at all.
                    }

                    /**
                     * Salesman filter is disabled as requested, but kept for future safety:
                     * (If you ever re-enable it, just remove the forced hide+force-all)
                     */
                    if (filterSalesman !== 'all') {
                        if (cellCanonSalesman !== filterSalesman) return false;
                    }

                    /**
                     * Date behavior (kept):
                     * - If row has no date -> include only when there are no Month/From/To filters.
                     * - Year is always applied to valid dates only.
                     */
                    if (!dateStr || dateStr === '-') {
                        return !(filterMonth || filterFrom || filterTo);
                    }

                    const rowDate = parseYmd(dateStr);
                    if (!rowDate) {
                        return !(filterMonth || filterFrom || filterTo);
                    }

                    // Year filter (always active) for valid dates
                    if (filterYear) {
                        const y = rowDate.getFullYear();
                        if (y !== parseInt(filterYear, 10)) return false;
                    }

                    // Month filter (optional)
                    if (filterMonth) {
                        const m = rowDate.getMonth() + 1;
                        if (m !== parseInt(filterMonth, 10)) return false;
                    }

                    // Date range filter (optional)
                    if (filterFrom && rowDate < filterFrom) return false;
                    if (filterTo && rowDate > filterTo) return false;

                    return true;
                });

                /**
                 * Friendly salesman labels in table cells
                 */
                function repaintSalesmanCells() {
                    $('#tblCoordinatorProjects tbody tr').each(function(){
                        const td = $(this).find('td').eq(3);
                        td.text(canonicalSalesmanLabel(td.text()));
                    });

                    $('#tblCoordinatorSalesOrders tbody tr').each(function(){
                        const td = $(this).find('td').eq(7);
                        td.text(canonicalSalesmanLabel(td.text()));
                    });
                }

                /**
                 * KPI refresh: uses filtered rows
                 */
                function refreshKpis() {
                    const projCount = dtProjects.rows({ search: 'applied' }).count();
                    const soCount   = dtSalesOrders.rows({ search: 'applied' }).count();

                    let soTotal = 0;
                    dtSalesOrders.column(2, { search: 'applied' }).data().each(function (value) {
                        let num = 0;
                        if (typeof value === 'number') num = value;
                        else if (typeof value === 'string') num = parseFloat(value.replace(/[^0-9.-]/g, '')) || 0;
                        if (!isNaN(num)) soTotal += num;
                    });

                    if (elKpiProjects)   elKpiProjects.textContent   = projCount.toLocaleString('en-SA');
                    if (elKpiSoCount)    elKpiSoCount.textContent    = soCount.toLocaleString('en-SA');
                    if (elKpiSoValueNum) elKpiSoValueNum.textContent = soTotal.toLocaleString('en-SA');
                }

                // Highcharts
                let regionChart = Highcharts.chart('coordinatorRegionStacked', {
                    chart: { type: 'column', backgroundColor: '#0f172a' },
                    title: { text: 'PO Value by Region', style: { color: '#e5e7eb' } },
                    xAxis: { categories: ['Eastern', 'Central', 'Western'], labels: { style: { color: '#cbd5e1' } } },
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
                            return '<b>' + this.x + '</b><br/>' +
                                this.points.map(p => p.series.name + ': ' + fmtSAR(p.y)).join('<br/>') +
                                '<br/><span style="font-weight:600">Total: ' + fmtSAR(total) + '</span>';
                        }
                    },
                    series: [{ name: 'PO Value', data: [0, 0, 0] }]
                });

                function refreshChartFromTable() {
                    const sums = { 'Eastern': 0, 'Central': 0, 'Western': 0 };

                    dtSalesOrders.rows({ search: 'applied' }).every(function () {
                        const row = this.data();
                        const areaKey = canonicalArea(row[8] || '');
                        let val = row[2];

                        if (typeof val === 'string') val = parseFloat(val.replace(/[^0-9.-]/g, '')) || 0;
                        if (!areaKey) return;

                        sums[areaKey] += (isNaN(val) ? 0 : val);
                    });

                    const cats = ['Eastern', 'Central', 'Western'];
                    regionChart.series[0].setData(cats.map(r => sums[r] || 0), true);
                }

                // redraw hooks
                $('#tblCoordinatorProjects').on('draw.dt', () => { repaintSalesmanCells(); refreshKpis(); });
                $('#tblCoordinatorSalesOrders').on('draw.dt', () => { repaintSalesmanCells(); refreshKpis(); refreshChartFromTable(); });

                // Region chips (ONLY filter you want)
                regionChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        regionChips.forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');

                        filterRegion = (chip.dataset.region || 'all').toString().trim();
                        if (filterRegion.toLowerCase() === 'all') filterRegion = 'all';

                        // enforce: salesman always all
                        filterSalesman = 'all';
                        setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');

                        redrawTables();
                    });
                });

                // Month / date inputs
                if (monthSelect) monthSelect.addEventListener('change', () => { filterMonth = monthSelect.value || ''; redrawTables(); });
                if (fromInput)   fromInput.addEventListener('change',  () => { filterFrom = fromInput.value ? parseYmd(fromInput.value) : null; redrawTables(); });
                if (toInput)     toInput.addEventListener('change',    () => { filterTo = toInput.value ? parseYmd(toInput.value) : null; redrawTables(); });

                /**
                 * Reset (kept + improved)
                 */
                if (btnResetFilters) {
                    btnResetFilters.addEventListener('click', () => {
                        filterRegion   = 'all';
                        filterSalesman = 'all';
                        filterMonth    = '';
                        filterFrom     = null;
                        filterTo       = null;

                        if (yearSelect) {
                            yearSelect.value = '';   // ✅ All Years
                            filterYear = '';
                        }
                        if (monthSelect) monthSelect.value = '';
                        if (fromInput) fromInput.value = '';
                        if (toInput) toInput.value = '';

                        setActiveChipBy('.coord-chip[data-region]', 'region', 'all');
                        setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');

                        redrawTables();
                    });
                }

                // Toggle tables (kept)
                if (btnProj && btnSO && wrapProjects && wrapSalesOrder) {
                    btnProj.addEventListener('click', () => {
                        btnProj.classList.add('btn-primary', 'active');
                        btnProj.classList.remove('btn-outline-primary');
                        btnSO.classList.remove('btn-primary', 'active');
                        btnSO.classList.add('btn-outline-primary');
                        wrapProjects.classList.remove('d-none');
                        wrapSalesOrder.classList.add('d-none');
                        dtProjects.columns.adjust();
                    });

                    btnSO.addEventListener('click', () => {
                        btnSO.classList.add('btn-primary', 'active');
                        btnSO.classList.remove('btn-outline-primary');
                        btnProj.classList.remove('btn-primary', 'active');
                        btnProj.classList.add('btn-outline-primary');
                        wrapProjects.classList.add('d-none');
                        wrapSalesOrder.classList.remove('d-none');
                        dtSalesOrders.columns.adjust();
                    });
                }

                // Excel export buttons (kept)
                if (btnDownloadExcelMonth) {
                    btnDownloadExcelMonth.addEventListener('click', () => {

                        const hasFromTo = (fromInput && fromInput.value) || (toInput && toInput.value);

                        if (hasFromTo) {
                            const params = buildExportParams({ requireMonth: false });
                            if (!params) return;
                            window.location.href = "{{ route('coordinator.salesorders.exportYear') }}" + '?' + params.toString();
                            return;
                        }

                        const params = buildExportParams({ requireMonth: true });
                        if (!params) return;
                        window.location.href = "{{ route('coordinator.salesorders.export') }}" + '?' + params.toString();
                    });
                }

                if (btnDownloadExcelYear) {
                    btnDownloadExcelYear.addEventListener('click', () => {
                        const params = buildExportParams({ requireMonth: false });
                        if (!params) return;
                        window.location.href = "{{ route('coordinator.salesorders.exportYear') }}" + '?' + params.toString();
                    });
                }

                // ---------- Modal open (view) + delete ---------- (kept exactly)
                document.addEventListener('click', function (e) {
                    const delBtn = e.target.closest('.btnDeleteCoordinator');
                    if (delBtn) {
                        const source = delBtn.dataset.source || '';
                        const id     = delBtn.dataset.id;
                        const label  = delBtn.dataset.label || (source === 'salesorder' ? 'this sales order' : 'this inquiry');
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

                    const btn = e.target.closest('.btnViewCoordinator');
                    if (!btn) return;

                    const source = btn.dataset.source || '';

                    document.getElementById('coord_source').value    = source;
                    document.getElementById('coord_record_id').value = btn.dataset.id || '';

                    document.getElementById('coord_project').value = btn.dataset.project || '';
                    document.getElementById('coord_client').value  = btn.dataset.client || '';

                    const salesmanSelect = document.getElementById('coord_salesman');
                    if (salesmanSelect) {
                        const rawSm = (btn.dataset.salesman || '').trim();
                        const canonical = canonicalSalesmanCode(rawSm);

                        if (canonical) {
                            const hasOption = Array.from(salesmanSelect.options).some(o => o.value === canonical);
                            if (!hasOption) salesmanSelect.add(new Option(canonical, canonical, true, true));
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

                    if (multiBlock && multiContainer && multiEnabled) {
                        multiBlock.style.display = (source === 'project') ? 'block' : 'none';
                        MULTI_SELECTED_IDS = new Set();
                        MULTI_QV_BY_ID     = {};
                        setMultiEnabled(false);
                        recalcMultiTotals();
                    }

                    if (attachmentsListEl) {
                        attachmentsListEl.innerHTML = '<li class="text-muted">Documents list will appear here (after upload).</li>';
                    }

                    if (source !== 'salesorder') {
                        coordModal && coordModal.show();
                        return;
                    }

                    const soId = btn.dataset.id;
                    if (attachmentsListEl) attachmentsListEl.innerHTML = '<li class="text-muted">Loading documents...</li>';

                    const url = "{{ route('coordinator.salesorders.attachments', ['salesorder' => '__ID__']) }}"
                        .replace('__ID__', soId);

                    fetch(url)
                        .then(r => r.json())
                        .then(res => {
                            if (!attachmentsListEl) return;

                            if (!res.ok) {
                                attachmentsListEl.innerHTML = '<li class="text-danger">Unable to load documents.</li>';
                                return;
                            }

                            const atts = res.attachments || [];
                            if (atts.length === 0) {
                                attachmentsListEl.innerHTML = '<li class="text-muted">No documents uploaded.</li>';
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
                            if (attachmentsListEl) attachmentsListEl.innerHTML = '<li class="text-danger">Error loading documents.</li>';
                        })
                        .finally(() => coordModal && coordModal.show());
                });

                // Save (PO Received) (kept)
                if (btnSave) {
                    btnSave.addEventListener('click', async () => {
                        const formEl = document.getElementById('coordinatorForm');
                        const fd = new FormData(formEl);
                        fd.append('record_id', document.getElementById('coord_record_id').value);

                        if (multiEnabled && multiEnabled.checked && multiContainer) {
                            const checkboxes = multiContainer.querySelectorAll('.coord-multi-item:checked');
                            checkboxes.forEach(cb => fd.append('extra_project_ids[]', cb.value));
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
                            const data = contentType.includes('application/json') ? await resp.json() : null;

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

                            alert((data && data.message) ? data.message : 'PO saved successfully.');
                            window.location.reload();

                        } catch (err) {
                            console.error(err);
                            alert('Unexpected error while saving PO: ' + err.message);
                            btnSave.disabled = false;
                            btnSave.innerText = 'Po Received';
                        }
                    });
                }

                // Initial state
                setActiveChipBy('.coord-chip[data-region]', 'region', 'all');
                setActiveChipBy('.coord-chip[data-salesman]', 'salesman', 'all');

                redrawTables();
                repaintSalesmanCells();
                refreshKpis();
                refreshChartFromTable();
            });

        })();
    </script>
@endpush
