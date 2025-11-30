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
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        .modal-backdrop.show {
            z-index: 1990 !important;
            pointer-events: none !important;
            background-color: rgba(0,0,0,0.1);
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
            box-shadow: 0 0 0 1px rgba(59,130,246,.5);
        }

        .coord-filter-note {
            font-size: 0.8rem;
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
                                <button type="button" class="coord-chip" data-region="{{ $label }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @php
                        $salesmenRaw = $salesmenScope ?? [];

                        // Canonical → all spellings that should be treated as the same person
                        $salesmanAliasMap = [
                            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
                            'TARIQ'  => ['TARIQ', 'TAREQ'],
                            'JAMAL'  => ['JAMAL'],
                            'ABDO'   => ['ABDO'],
                            'AHMED'  => ['AHMED'],
                        ];

                        // Build a unique list of canonical names that actually exist in scope
                        $salesmen = [];
                        foreach ($salesmenRaw as $s) {
                            $upper = strtoupper($s);
                            foreach ($salesmanAliasMap as $canonical => $aliases) {
                                if (in_array($upper, $aliases, true)) {
                                    $salesmen[$canonical] = $canonical;
                                    break;
                                }
                            }
                        }
                        $salesmen = array_values($salesmen);
                    @endphp

                    <div>
                        <div class="coord-filter-label">Salesman</div>
                        <div class="coord-chip-group">
                            <button type="button" class="coord-chip active" data-salesman="all">All</button>

                            @foreach($salesmen as $s)
                                @php
                                    $upper = strtoupper($s);
                                    $label = ucwords(strtolower($s)); // Sohaib, Tariq, etc.
                                @endphp
                                <button type="button"
                                        class="coord-chip"
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
                    <table id="tblCoordinatorProjects" class="table table-sm table-striped table-hover align-middle w-100">
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
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-light btnViewCoordinator"
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
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Sales Orders table --}}
                <div id="wrapSalesOrdersTable" class="d-none">
                    <table id="tblCoordinatorSalesOrders" class="table table-sm table-striped table-hover align-middle w-100">
                        <thead>
                        <tr>
                            <th>PO No</th>
                            <th>Quotation No</th>
                            <th>Job No</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Salesman</th>
                            <th>Area</th>
                            <th>ATAI Products</th>
                            <th>PO Date</th>
                            <th>PO Value (SAR)</th>
                            <th>Value with VAT (SAR)</th>
                            <th>Created By</th>
                            <th style="width: 1%;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($salesOrders as $so)
                            <tr>
                                <td>{{ $so->po_no ?? '-' }}</td>
                                <td>{{ $so->quotation_no ?? '-' }}</td>
                                <td>{{ $so->job_no ?? '-' }}</td>
                                <td>{{ $so->project ?? '-' }}</td>
                                <td>{{ $so->client ?? '-' }}</td>
                                <td>{{ $so->salesman ?? '-' }}</td>
                                <td>{{ $so->area ?? '-' }}</td>
                                <td>{{ $so->atai_products ?? '-' }}</td>
                                <td>{{ optional($so->po_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ number_format($so->total_po_value ?? 0, 0) }}</td>
                                <td>{{ number_format($so->value_with_vat ?? (($so->total_po_value ?? 0) * 1.15), 0) }}</td>
                                <td>{{ optional($so->creator)->name ?? '-' }}</td>

                                <td class="table-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-light btnViewCoordinator"
                                        data-source="salesorder"
                                        data-id="{{ $so->id }}"
                                        data-project="{{ $so->project }}"
                                        data-client="{{ $so->client }}"
                                        data-salesman="{{ $so->salesman }}"
                                        data-location="{{ $so->location }}"
                                        data-area="{{ $so->area }}"
                                        data-quotation-no="{{ $so->quotation_no }}"
                                        data-quotation-date="{{ optional($so->quotation_date)->format('Y-m-d') }}"
                                        data-date-received="{{ optional($so->date_received)->format('Y-m-d') }}"
                                        data-products="{{ $so->atai_products }}"
                                        data-price="{{ $so->quotation_value }}"
                                        data-status="{{ strtoupper(trim($so->status ?? '')) }}"
                                        data-oaa="{{ $so->oaa }}"
                                        data-job-no="{{ $so->job_no }}"
                                        data-payment-terms="{{ $so->payment_terms }}"
                                        data-remarks="{{ $so->remarks }}"
                                        data-po-no="{{ $so->po_no }}"
                                        data-po-date="{{ optional($so->po_date)->format('Y-m-d') }}"
                                        data-po-value="{{ $so->total_po_value }}"
                                    >
                                        <i class="bi bi-eye"></i> View
                                    </button>
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
    <div class="modal fade modal-atai" id="coordinatorModal" tabindex="-1" aria-labelledby="coordinatorModalLabel" data-bs-backdrop="static" aria-hidden="true">
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
                                    <input type="text" class="form-control form-control-sm" id="coord_project" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Client</div>
                                    <textarea class="form-control form-control-sm" id="coord_client" rows="2" readonly></textarea>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Salesperson</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_salesman" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Location</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_location" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Area</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_area" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Quotation No</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_quotation_no" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Quotation Date</div>
                                    <input type="date" class="form-control form-control-sm" id="coord_quotation_date" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Date Received</div>
                                    <input type="date" class="form-control form-control-sm" id="coord_date_received" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">ATAI Products</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_products" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Price</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_price" readonly>
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Status</div>
                                    <input type="text" class="form-control form-control-sm" id="coord_status" readonly>
                                </div>

                            </div>

                            {{-- RIGHT COLUMN: editable coordinator fields --}}
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <div class="coordinator-label">Job No</div>
                                    <input type="text" name="job_no" id="coord_job_no" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO No</div>
                                    <input type="text" name="po_no" id="coord_po_no" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO Date</div>
                                    <input type="date" name="po_date" id="coord_po_date" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">PO Value (SAR)</div>
                                    <input type="number" step="0.01" name="po_value" id="coord_po_value" class="form-control form-control-sm">
                                </div>
                                <div class="mb-2">
                                    <div class="coordinator-label">Payment Terms</div>
                                    <select name="payment_terms" id="coord_payment_terms" class="form-select form-select-sm">
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
                                    <textarea name="remarks" id="coord_remarks" rows="4" class="form-control form-control-sm"></textarea>
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
                    maximumFractionDigits: 0
                }).format(value);
            };

            const SALESMAN_ALIASES = {
                'SOHAIB': ['SOHAIB', 'SOAHIB'],
                'TARIQ':  ['TARIQ', 'TAREQ'],
                'JAMAL':  ['JAMAL'],
                'ABDO':   ['ABDO'],
                'AHMED':  ['AHMED'],
            };
            const salesmanChips = document.querySelectorAll('.coord-chip[data-salesman]');
            const regionChips   = document.querySelectorAll('.coord-chip[data-region]');

            let filterSalesman = 'all'; // SOHAIB, TARIQ, etc., or all
            let filterRegion   = 'all'; // Eastern, Central, Western, or all
            let filterMonth    = '';
            let filterFrom     = null;
            let filterTo       = null;

            // ---------- DOM refs ----------
            const monthSelect       = document.getElementById('coord_month');
            const fromInput         = document.getElementById('coord_from');
            const toInput           = document.getElementById('coord_to');
            const btnResetFilters   = document.getElementById('coord_reset_filters');
            // const btnDownloadExcel  = document.getElementById('coord_download_excel');


            const elKpiProjects     = document.getElementById('kpiProjectsCount');
            const elKpiSoCount      = document.getElementById('kpiSalesOrdersCount');
            const elKpiSoValueNum   = document.getElementById('kpiSalesOrdersValue');

            const wrapProjects      = document.getElementById('wrapProjectsTable');
            const wrapSalesOrder    = document.getElementById('wrapSalesOrdersTable');
            const btnProj           = document.getElementById('btnShowProjects');
            const btnSO             = document.getElementById('btnShowSalesOrders');

            const coordModalEl      = document.getElementById('coordinatorModal');
            const coordModal        = coordModalEl ? new bootstrap.Modal(coordModalEl) : null;

            const btnSave           = document.getElementById('btnCoordinatorSave');

            // ---------- DataTables ----------
            const dtProjects = new DataTable('#tblCoordinatorProjects', {
                pageLength: 25,
                order: [[6, 'desc']]
            });

            const dtSalesOrders = new DataTable('#tblCoordinatorSalesOrders', {
                pageLength: 25,
                order: [[8, 'desc']]
            });





            let areaStr, salesmanStr, dateStr;



            salesmanChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    salesmanChips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');

                    const v = chip.dataset.salesman || 'all';
                    filterSalesman = v === 'all' ? 'all' : v.toUpperCase();

                    redrawTables();
                });
            });

            const btnDownloadExcelMonth = document.getElementById('coord_download_excel_month');
            const btnDownloadExcelYear  = document.getElementById('coord_download_excel_year');

            if (btnDownloadExcelMonth) {
                btnDownloadExcelMonth.addEventListener('click', () => {
                    if (!monthSelect || !monthSelect.value) {
                        alert('Please select a month before downloading the Excel file.');
                        return;
                    }

                    const params = new URLSearchParams();
                    params.set('month', monthSelect.value);
                    params.set('region', filterRegion || 'all');

                    if (filterSalesman !== 'all') {
                        params.set('salesman', filterSalesman); // canonical, e.g. "TARIQ"
                    }

                    if (fromInput && fromInput.value) params.set('from', fromInput.value);
                    if (toInput && toInput.value)     params.set('to', toInput.value);

                    const url = "{{ route('coordinator.salesorders.export') }}" + '?' + params.toString();
                    window.location.href = url;
                });
            }

            if (btnDownloadExcelYear) {
                btnDownloadExcelYear.addEventListener('click', () => {
                    const params = new URLSearchParams();
                    params.set('region', filterRegion || 'all');

                    if (filterSalesman !== 'all') {
                        params.set('salesman', filterSalesman);
                    }

                    if (fromInput && fromInput.value) params.set('from', fromInput.value);
                    if (toInput && toInput.value)     params.set('to', toInput.value);

                    const url = "{{ route('coordinator.salesorders.exportYear') }}" + '?' + params.toString();
                    window.location.href = url;
                });
            }



            // ---------- Custom global filter for BOTH tables ----------
            $.fn.dataTable.ext.search.push(function (settings, data) {
                const tableId = settings.nTable.id;

                if (tableId !== 'tblCoordinatorProjects' && tableId !== 'tblCoordinatorSalesOrders') {
                    return true; // ignore other tables
                }

                let areaStr, salesmanStr, dateStr;

                if (tableId === 'tblCoordinatorProjects') {
                    areaStr     = (data[4] || '').trim(); // Area
                    salesmanStr = (data[3] || '').trim(); // Salesman
                    dateStr     = data[6] || '';          // Quotation Date
                } else {
                    areaStr     = (data[6] || '').trim(); // Area
                    salesmanStr = (data[5] || '').trim(); // Salesman
                    dateStr     = data[8] || '';          // PO Date
                }

                // REGION filter (case-insensitive)
                if (filterRegion !== 'all') {
                    const cellRegion = (areaStr || '').toUpperCase();
                    const wanted     = filterRegion.toUpperCase();
                    if (cellRegion !== wanted) {
                        return false;
                    }
                }

                // SALESMAN filter
                // SALESMAN filter with aliases (SOHAIB = SOHAIB + SOAHIB, etc.)
                if (filterSalesman !== 'all') {
                    const cellUpper = (salesmanStr || '').toUpperCase();
                    const aliases   = SALESMAN_ALIASES[filterSalesman] || [filterSalesman];

                    if (!aliases.includes(cellUpper)) {
                        return false;
                    }
                }

                // If no date, but date filters are applied → drop row
                if (!dateStr || dateStr === '-') {
                    if (filterMonth || filterFrom || filterTo) {
                        return false;
                    }
                    return true;
                }

                const rowDate = new Date(dateStr); // expecting YYYY-MM-DD
                if (isNaN(rowDate.getTime())) {
                    return true; // fail open if parse fails
                }

                // MONTH filter
                if (filterMonth) {
                    const m = rowDate.getMonth() + 1;
                    if (m !== parseInt(filterMonth, 10)) {
                        return false;
                    }
                }

                // RANGE filter
                if (filterFrom && rowDate < filterFrom) return false;
                if (filterTo   && rowDate > filterTo)   return false;

                return true;
            });

            function redrawTables() {
                dtProjects.draw();
                dtSalesOrders.draw();
            }

            // ---------- KPI recalculation ----------
            function refreshKpis() {
                const projCount = dtProjects.rows({ filter: 'applied' }).count();
                const soCount   = dtSalesOrders.rows({ filter: 'applied' }).count();

                let soTotal = 0;
                dtSalesOrders
                    .column(9, { filter: 'applied' })
                    .data()
                    .each(function (value) {
                        let num = 0;
                        if (typeof value === 'number') {
                            num = value;
                        } else if (typeof value === 'string') {
                            num = parseFloat(value.replace(/[^0-9.-]/g, '')) || 0;
                        }
                        if (!isNaN(num)) {
                            soTotal += num;
                        }
                    });

                if (elKpiProjects)   elKpiProjects.textContent   = fmtSAR(projCount);
                if (elKpiSoCount)    elKpiSoCount.textContent    = fmtSAR(soCount);
                if (elKpiSoValueNum) elKpiSoValueNum.textContent = fmtSAR(soTotal);
            }

            // ---------- Highcharts (keep reference) ----------
            let regionChart = Highcharts.chart('coordinatorRegionStacked', {
                chart: {
                    type: 'column',
                    backgroundColor: '#0f172a'
                },
                title: {
                    text: 'Quotation vs PO Value by Region',
                    style: { color: '#e5e7eb' }
                },
                xAxis: {
                    categories: @json($chartCategories),
                    crosshair: true,
                    labels: { style: { color: '#cbd5e1' } }
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Value (SAR)',
                        style: { color: '#cbd5e1' }
                    },
                    stackLabels: {
                        enabled: true,
                        formatter: function () {
                            return 'SAR ' + fmtSAR(this.total);
                        },
                        style: {
                            color: '#e5e7eb',
                            textOutline: 'none',
                            fontWeight: '600'
                        }
                    },
                    labels: { style: { color: '#cbd5e1' } },
                    gridLineColor: '#1e293b'
                },
                legend: {
                    itemStyle: { color: '#e5e7eb' }
                },
                tooltip: {
                    shared: true,
                    backgroundColor: '#1e293b',
                    borderColor: '#475569',
                    style: { color: 'white' },
                    formatter: function () {
                        let s = '<b>' + this.x + '</b><br/>';
                        this.points.forEach(p => {
                            s += p.series.name + ': SAR ' + fmtSAR(p.y) + '<br/>';
                        });
                        s += '<span style="font-weight:600">Total: SAR ' +
                            fmtSAR(this.points.reduce((t, p) => t + p.y, 0)) + '</span>';
                        return s;
                    }
                },
                plotOptions: {
                    column: {
                        stacking: 'normal',
                        borderWidth: 0
                    }
                },
                series: [
                    {
                        name: 'PO Value',
                        data: @json($chartPOs)
                    }
                ]
            });

            // Rebuild chart from filtered Sales Orders table
            function refreshChartFromTable() {
                if (!regionChart) return;

                // 🔹 sums[area] = total PO value for that area
                const sums = {};

                dtSalesOrders.rows({ filter: 'applied' }).every(function () {
                    const row = this.data();

                    // Area column index = 6, PO Value = 9 (from your table)
                    const areaRaw = (row[6] || '').trim();
                    const area    = areaRaw || 'Unknown';

                    let val = row[9];

                    if (typeof val === 'string') {
                        val = parseFloat(val.replace(/[^0-9.-]/g, '')) || 0;
                    }
                    if (typeof val !== 'number' || isNaN(val)) {
                        val = 0;
                    }

                    if (!sums[area]) {
                        sums[area] = 0;
                    }
                    sums[area] += val;
                });

                let cats = Object.keys(sums);
                let data = cats.map(region => sums[region]);

                // If nothing after filters, show a neutral “No Data”
                if (cats.length === 0) {
                    cats = ['No Data'];
                    data = [0];
                }

                regionChart.xAxis[0].setCategories(cats, false);
                regionChart.series[0].setData(data, true); // redraw = true
            }

            // ---------- hook draw events ----------
            dtProjects.on('draw', function () {
                refreshKpis();
            });

            dtSalesOrders.on('draw', function () {
                refreshKpis();
                refreshChartFromTable();
            });

           // ---------- Region chips ----------
            regionChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    regionChips.forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');

                    filterRegion = chip.dataset.region || 'all';
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

            if (btnResetFilters) {
                btnResetFilters.addEventListener('click', () => {
                    // region
                    filterRegion = 'all';
                    regionChips.forEach(c => c.classList.remove('active'));
                    const allRegionChip = document.querySelector('.coord-chip[data-region="all"]');
                    if (allRegionChip) allRegionChip.classList.add('active');

                    // salesman
                    filterSalesman = 'all';
                    salesmanChips.forEach(c => c.classList.remove('active'));
                    const allSalesmanChip = document.querySelector('.coord-chip[data-salesman="all"]');
                    if (allSalesmanChip) allSalesmanChip.classList.add('active');

                    // dates/month
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

            // ---------- Modal handling (open) ----------
            const attachmentsListEl = document.getElementById('coord_attachments_list');

            // ---------- Modal handling (open) with attachments ----------
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btnViewCoordinator');
                if (!btn) return; // clicked something else

                const source = btn.dataset.source || '';

                document.getElementById('coord_source').value         = source;
                document.getElementById('coord_record_id').value      = btn.dataset.id || '';

                document.getElementById('coord_project').value        = btn.dataset.project || '';
                document.getElementById('coord_client').value         = btn.dataset.client || '';
                document.getElementById('coord_salesman').value       = btn.dataset.salesman || '';
                document.getElementById('coord_location').value       = btn.dataset.location || '';
                document.getElementById('coord_area').value           = btn.dataset.area || '';
                document.getElementById('coord_quotation_no').value   = btn.dataset.quotationNo || '';
                document.getElementById('coord_quotation_date').value = btn.dataset.quotationDate || '';
                document.getElementById('coord_date_received').value  = btn.dataset.dateReceived || '';
                document.getElementById('coord_products').value       = btn.dataset.products || '';
                document.getElementById('coord_price').value          = btn.dataset.price || '';
                document.getElementById('coord_status').value         = btn.dataset.status || '';

                document.getElementById('coord_job_no').value         = btn.dataset.jobNo || '';
                document.getElementById('coord_po_no').value          = btn.dataset.poNo || '';
                document.getElementById('coord_po_date').value        = btn.dataset.poDate || '';
                document.getElementById('coord_po_value').value       = btn.dataset.poValue || '';
                document.getElementById('coord_payment_terms').value  = btn.dataset.paymentTerms || '';
                document.getElementById('coord_remarks').value        = btn.dataset.remarks || '';
                document.getElementById('coord_oaa').value            = btn.dataset.oaa || '';
                // Default state for attachments
                if (attachmentsListEl) {
                    attachmentsListEl.innerHTML =
                        '<li class="text-muted">Documents list will appear here (after upload).</li>';
                }

                // For inquiries (projects) – no attachments yet, just show modal
                if (source !== 'salesorder') {
                    if (coordModal) coordModal.show();
                    return;
                }

                // For sales orders – load attachments via AJAX
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
                            link.href = a.url;
                            link.target = '_blank';
                            link.rel = 'noopener';
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


            // ---------- Save PO (Po Received button) ----------
            if (btnSave) {
                btnSave.addEventListener('click', async () => {
                    const formEl = document.getElementById('coordinatorForm');
                    const fd = new FormData(formEl);
                    fd.append('record_id', document.getElementById('coord_record_id').value);

                    btnSave.disabled  = true;
                    btnSave.innerText = 'Saving...';

                    try {
                        const resp = await fetch("{{ route('coordinator.storePo') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'   // 👈 IMPORTANT
                            },
                            body: fd
                        });

                        const contentType = resp.headers.get('content-type') || '';
                        let data = null;

                        if (contentType.includes('application/json')) {
                            data = await resp.json();
                        }

                        // ❌ Any HTTP error (422 validation, 403, 500, etc.)
                        if (!resp.ok) {
                            let msg = 'Error while saving PO.';

                            if (data) {
                                if (data.message) {
                                    msg = data.message;
                                } else if (data.errors) {
                                    // Laravel validation errors -> flatten
                                    msg = Object.values(data.errors).flat().join('\n');
                                }
                            } else if (resp.status === 419) {
                                msg = 'Session expired. Please refresh the page and try again.';
                            }

                            alert(msg);
                            btnSave.disabled  = false;
                            btnSave.innerText = 'Po Received';
                            return;
                        }

                        // ✅ HTTP 200 OK with JSON from controller
                        const res = data || {};

                        alert(res.message || 'PO saved successfully.');

                        if (res.ok) {
                            window.location.reload();
                        } else {
                            btnSave.disabled  = false;
                            btnSave.innerText = 'Po Received';
                        }

                    } catch (err) {
                        console.error(err);
                        alert('Unexpected error while saving PO: ' + err.message);
                        btnSave.disabled  = false;
                        btnSave.innerText = 'Po Received';
                    }
                });
            }

            // ---------- Excel download (Sales Orders only, month required) ----------
            {{--if (btnDownloadExcel) {--}}
            {{--    btnDownloadExcel.addEventListener('click', () => {--}}
            {{--        if (!monthSelect || !monthSelect.value) {--}}
            {{--            alert('Please select a month before downloading the Sales Orders Excel file.');--}}
            {{--            return;--}}
            {{--        }--}}

            {{--        const params = new URLSearchParams();--}}
            {{--        params.set('month', monthSelect.value);--}}
            {{--        params.set('region', filterRegion || 'all');--}}

            {{--        if (fromInput && fromInput.value) params.set('from', fromInput.value);--}}
            {{--        if (toInput && toInput.value)     params.set('to', toInput.value);--}}

            {{--        const url = "{{ route('coordinator.salesorders.export') }}" + '?' + params.toString();--}}
            {{--        window.location.href = url;  // triggers file download--}}
            {{--    });--}}
            {{--}--}}

            // Initial draw + KPI + chart sync
            redrawTables();
            refreshKpis();
            refreshChartFromTable();
        })();



    </script>
@endpush






{{--<IfModule mod_rewrite.c>--}}
{{--    <IfModule mod_negotiation.c>--}}
{{--        Options -MultiViews -Indexes--}}
{{--    </IfModule>--}}

{{--    RewriteEngine On--}}

{{--    # Handle Authorization Header--}}
{{--    RewriteCond %{HTTP:Authorization} .--}}
{{--    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]--}}

{{--    # Handle X-XSRF-Token Header--}}
{{--    RewriteCond %{HTTP:x-xsrf-token} .--}}
{{--    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]--}}

{{--    # Redirect Trailing Slashes If Not A Folder...--}}
{{--    RewriteCond %{REQUEST_FILENAME} !-d--}}
{{--    RewriteCond %{REQUEST_URI} (.+)/$--}}
{{--    RewriteRule ^ %1 [L,R=301]--}}

{{--    # Send Requests To Front Controller...--}}
{{--    RewriteCond %{REQUEST_FILENAME} !-d--}}
{{--    RewriteCond %{REQUEST_FILENAME} !-f--}}
{{--    RewriteRule ^ index.php [L]--}}
{{--</IfModule>--}}



