{{-- resources/views/projects/index.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    {{-- DataTables (Bootstrap 5 build) --}}
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

    {{-- Meta / Bootstrap --}}
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI Projects — Live</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        /* Small helpers for area badges */
        .area-badge {
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: .02em;
        }

        .area-Eastern {
            background: #e7f1ff;
            color: #0a58ca;
        }

        .area-Central {
            background: #eaf7ec;
            color: #1f7a1f;
        }

        .area-Western {
            background: #fff4e5;
            color: #b86e00;
        }

        /* Make header filter row look light */
        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm, table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }

        /* Keep action column content to the right */
        #tblBidding td:last-child, #tblInhand td:last-child, #tblLost td:last-child {
            text-align: end;
        }

        /* Chart cards – compact like Sales Orders dashboard */
        .kpi-card .hc {
            height: 260px;
        }

        .kpi-card .card-body {
            padding: .75rem 1rem;
        }
    </style>
</head>
<body>

@php $u = auth()->user(); @endphp
<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2">
            <span class="brand-word">ATAI</span>
        </a>

        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                {{-- Always visible --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Inquiries</a>
                </li>

                {{-- Sales roles only --}}
{{--                @hasanyrole('sales|sales_eastern|sales_central|sales_western')--}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">Estimation</a>
                </li>
{{--                @endhasanyrole--}}

                {{-- GM/Admin only --}}
                @hasanyrole('gm|admin')
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('salesorders.*') ? 'active' : '' }}"
                                        href="{{ route('salesorders.index') }}">Sales Orders</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.index') ? 'active' : '' }}"
                                        href="{{ route('performance.index') }}">Performance report</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.area*') ? 'active' : '' }}"
                                        href="{{ route('performance.area') }}">Area summary</a></li>
                <li class="nav-item"><a
                        class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active' : '' }}"
                        href="{{ route('performance.salesman') }}">SalesMan summary</a></li>
                <li class="nav-item"><a
                        class="nav-link {{ request()->routeIs('performance.product*') ? 'active' : '' }}"
                        href="{{ route('performance.product') }}">Product summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                                        href="{{ route('powerbi.jump') }}">Accounts Summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                                        href="{{ route('powerbi.jump') }}">Power BI Dashboard</a></li>
                @endhasanyrole
            </ul>

            <div class="navbar-text me-2">
                Logged in as <strong>{{ $u->name ?? '' }}</strong>
                @if(!empty($u->region))
                    · <small>{{ $u->region }}</small>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    {{-- ===== KPI SUMMARY (Highcharts) ===== --}}



    <div class="row g-3 mb-3" id="kpiRow" style="display:none">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="projFilters">
                <select id="projYear" class="form-select form-select-sm" style="width:auto">
                    <option value="">All Years</option>
                    @for($y = (int)date('Y'); $y >= (int)date('Y')-6; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                <span id="projRegionWrap">
          <select id="projRegion" class="form-select form-select-sm" style="width:auto">
            <option value="">All Region</option>
            <option value="Eastern">Eastern</option>
            <option value="Central">Central</option>
            <option value="Western">Western</option>
          </select>
        </span>

                <div class="d-flex gap-2 align-items-center">
                    {{-- Month select --}}
                    <select id="monthSelect" class="form-select form-select-sm" style="width:auto">
                        <option value="">All Months</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>
                        @endfor
                    </select>

                    {{-- OR date range (overrides year/month if used) --}}
                    <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:auto"
                           placeholder="From">
                    <input type="date" id="dateTo" class="form-control form-control-sm" style="width:auto"
                           placeholder="To">

                    {{-- GM/Admin: optional free-text salesman filter for forecast --}}
                    <span id="salesmanWrap" class="d-none">
            <input type="text" id="salesmanInput" class="form-control form-control-sm" style="width:14rem"
                   placeholder="Salesman (GM/Admin)">
          </span>

                    <button class="btn btn-sm btn-primary" id="projApply">Update</button>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">
                <div id="familyChips" class="btn-group" role="group" aria-label="Product family">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-family="">All</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="ductwork">Ductwork</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="dampers">Dampers</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="sound">Sound Attenuators</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="accessories">Accessories</button>
                </div>
            </div>
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h6 class="mb-1">Inquiries Dashboard</h6>
                            <div class="text-secondary small">Live KPIs from MySQL (scoped by your role/region)</div>
                        </div>
                        <div class="d-flex gap-2">
                            <span id="kpiBadgeProjects" class="badge-total text-bg-info">Projects: 0</span>
                            <span id="kpiBadgeValue" class="badge-total text-bg-primary">Total Value: SAR 0</span>
                        </div>
                    </div>
                    <div class="row mt-3 g-3">
                        <div class="col-md-6">
                            <div id="barByArea" class="hc"></div>
                        </div>
                        <div class="col-md-6">
                            <div id="pieByStatus" class="hc"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 mt-3">
        <div id="barMonthlyByArea" class="hc"></div>
    </div>
{{--    --}}{{-- ===== FORECAST KPI (Highcharts) ===== --}}
{{--    <div class="row g-3 mt-2" id="forecastRow" style="display:none">--}}
{{--        <div class="col-12">--}}
{{--            <div class="card kpi-card">--}}
{{--                <div class="card-body">--}}
{{--                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">--}}
{{--                        <div>--}}
{{--                            <h6 class="mb-1">Sales Forecast Dashboard</h6>--}}
{{--                            <div class="text-secondary small">From forecast table (filters & role applied)</div>--}}
{{--                        </div>--}}
{{--                        <div class="d-flex gap-2">--}}
{{--                            <span id="fcBadgeScope" class="badge-total text-bg-secondary">All Salesmen</span>--}}
{{--                            <span id="fcBadgeValue" class="badge-total text-bg-primary">Forecast Total: SAR 0</span>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <div class="row mt-3 g-3">--}}
{{--                        <div class="col-md-6">--}}
{{--                            <div id="fcBarByArea" class="hc"></div>--}}
{{--                        </div>--}}
{{--                        <div class="col-md-6">--}}
{{--                            <div id="fcBarBySalesman" class="hc"></div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}

    {{-- ===== Family filter chips (All / Ductwork / Dampers / Sound / Accessories) ===== --}}

    {{-- ===== TABS (Bidding / In-Hand / Lost) ===== --}}
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-target="#bidding" data-bs-toggle="tab" type="button" role="tab">
                Bidding
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#inhand" data-bs-toggle="tab" type="button" role="tab">In-Hand
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#lost" data-bs-toggle="tab" type="button" role="tab">Lost</button>
        </li>
    </ul>

    @php $areas = ['','Eastern','Central','Western']; @endphp
    <div class="tab-content border-start border-end border-bottom p-3 rounded-bottom">

        {{-- ---------- BIDDING TAB ---------- --}}
        <div class="tab-pane fade show active" id="bidding" role="tabpanel" tabindex="0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group w-auto">
                    <span class="input-group-text">Search</span>
                    <input id="searchBidding" type="text" class="form-control" placeholder="Project, client, location…">
                </div>
                <span id="sumBidding" class="badge-total badge-total-outline text-bg-info ms-2">Total: SAR 0</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped w-100" id="tblBidding">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Area</th>
                        <th>Quotation No</th>
                        <th>ATAI Products</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>

                    </thead>
                </table>
            </div>
        </div>

        {{-- ---------- IN-HAND TAB ---------- --}}
        <div class="tab-pane fade" id="inhand" role="tabpanel" tabindex="0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group w-auto">
                    <span class="input-group-text">Search</span>
                    <input id="searchInhand" type="text" class="form-control" placeholder="Project, client, location…">
                </div>
                <span id="sumInhand" class="badge-total badge-total-outline text-bg-info ms-2">Total: SAR 0</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped w-100" id="tblInhand">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Area</th>
                        <th>Quotation No</th>
                        <th>ATAI Products</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>

                    </thead>
                </table>
            </div>
        </div>

        {{-- ---------- LOST TAB ---------- --}}
        <div class="tab-pane fade" id="lost" role="tabpanel" tabindex="0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group w-auto">
                    <span class="input-group-text">Search</span>
                    <input id="searchLost" type="text" class="form-control" placeholder="Project, client, location…">
                </div>
                <span id="sumLost" class="badge badge-total-outline text-bg-info ms-2">Total: SAR 0</span>
            </div>

            <div class="table-responsive">
                <table class="table table-striped w-100" id="tblLost">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Area</th>
                        <th>Quotation No</th>
                        <th>ATAI Products</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>

                    </thead>
                </table>
            </div>
        </div>
    </div>
</main>

{{-- ===== BIDDING MODAL ===== --}}
<div class="modal fade" id="biddingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="biddingModalLabel">Bidding Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Details</h6>
                                <dl class="row mb-0" id="biddingDetails"></dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Checklist (Bidding)</h6>
                                <div id="biddingChecklist" class="vstack gap-2"></div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span>Progress</span><strong id="biddingProgressPct">0%</strong>
                                    </div>
                                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" id="biddingProgressBar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label for="biddingComments" class="form-label mb-1">Comments</label>
                                    <textarea id="biddingComments" class="form-control" rows="3"
                                              placeholder="Add notes, next steps, follow-ups"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /row -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="saveBiddingBtn" type="button">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- ===== IN-HAND MODAL ===== --}}
<div class="modal fade" id="inhandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inhandModalLabel">In-Hand Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Details</h6>
                                <dl class="row mb-0" id="inhandDetails"></dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Checklist (In-Hand)</h6>
                                <div id="inhandChecklist" class="vstack gap-2"></div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span>Progress</span><strong id="inhandProgressPct">0%</strong>
                                    </div>
                                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" id="inhandProgressBar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label for="inhandComments" class="form-label mb-1">Comments</label>
                                    <textarea id="inhandComments" class="form-control" rows="3"
                                              placeholder="Add delivery notes, PO milestones, etc."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /row -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="saveInhandBtn" type="button">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- ===== LOST MODAL ===== --}}
<div class="modal fade" id="lostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lostModalLabel">Lost Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Details</h6>
                                <dl class="row mb-0" id="lostDetails"></dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Checklist (Lost)</h6>
                                <div id="lostChecklist" class="vstack gap-2"></div>
                                <div class="mt-3">
                                    <label for="lostTechReason" class="form-label mb-1">Technical reason
                                        (specify)</label>
                                    <textarea id="lostTechReason" class="form-control" rows="3"
                                              placeholder="Enter technical reason…" disabled></textarea>
                                </div>
                                <div class="mt-3">
                                    <label for="lostComments" class="form-label mb-1">Comments</label>
                                    <textarea id="lostComments" class="form-control" rows="3"
                                              placeholder="Notes / lessons learned"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /row -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="saveLostBtn" type="button">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Toast (generic success) --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="successToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite"
         aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg">Updated.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
        </div>
    </div>
</div>

{{-- Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>
<script>
    /* =============================================================================
     *  CONFIG & HELPERS
     * ============================================================================= */
    const API = @json(url('/api'));
    const DT_URL = @json(route('projects.datatable'));
    const $ = window.jQuery;
    $.fn.dataTable.ext.errMode = 'console';

    const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
        style: 'currency',
        currency: 'SAR',
        maximumFractionDigits: 0
    }).format(Number(n || 0));

    // global state (no window.*)
    let PROJ_YEAR   = '';
    let PROJ_REGION = '';
    let ATAI_ME     = null;
    let CAN_VIEW_ALL = false;
    let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

    /* =============================================================================
     *  KPI CHARTS
     * ============================================================================= */
    async function loadKpis() {
        const row = document.getElementById('kpiRow');
        if (row) row.style.display = '';

        const yearSel   = document.querySelector('#projYear');
        const monthSel  = document.querySelector('#monthSelect');
        const dateFromI = document.querySelector('#dateFrom');
        const dateToI   = document.querySelector('#dateTo');
        const regionSel = document.querySelector('#projRegion');

        const year   = yearSel?.value   || PROJ_YEAR || '';
        const month  = monthSel?.value  || '';
        const df     = dateFromI?.value || '';
        const dt     = dateToI?.value   || '';
        const family = currentFamily    || '';
        const region = regionSel?.value || PROJ_REGION || '';

        const url = new URL("{{ route('projects.kpis') }}", window.location.origin);
        if (df) url.searchParams.set('date_from', df);
        if (dt) url.searchParams.set('date_to', dt);
        if (!df && !dt) {
            if (month) url.searchParams.set('month', month);
            if (year)  url.searchParams.set('year', year);
        }
        if (family) url.searchParams.set('family', family);
        if (region) url.searchParams.set('area', region);

        let resp = { area: [], status: [], total_value: 0, total_count: 0 };
        try {
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) resp = await res.json();
        } catch (e) {
            console.warn('KPI fetch failed', e);
        }

        document.getElementById('kpiBadgeProjects').textContent = 'Projects: ' + Number(resp.total_count || 0).toLocaleString();
        document.getElementById('kpiBadgeValue').textContent    = 'Total Value: ' + fmtSAR(Number(resp.total_value || 0));

        const baseHC = {
            chart: { height: 260, spacing: [8, 8, 8, 8] },
            credits: { enabled: false },
            legend: { enabled: false },
            plotOptions: {
                column: { dataLabels: { enabled: true, formatter(){ return (this.y ?? 0).toLocaleString(); } } }
            }
        };

        // Bar: Inquiries/Projects by Area (clustered Inhand vs Bidding)
        const areaStatus = resp.area_status || { categories: [], series: [] };
        if (document.getElementById('barByArea')) {
            Highcharts.chart('barByArea', {
                chart: { type: 'column', height: 260, spacing: [8,8,8,8] },
                title: { text: 'Inquiries by Area' },
                credits: { enabled: false },
                xAxis: { categories: areaStatus.categories },
                yAxis: { title: { text: 'Count' } },
                legend: { enabled: true },
                tooltip: {
                    shared: true,
                    pointFormat: '<span style="color:{point.color}">●</span> {series.name}: <b>{point.y}</b><br/>'
                },
                plotOptions: { column: { dataLabels: { enabled: true } } },
                series: areaStatus.series
            });
        }

        // Pie: Value by Status
        const pieEl = document.getElementById('pieByStatus');
        if (pieEl) {
            const statusArr = Array.isArray(resp.status) ? resp.status : [];
            const hasPie = statusArr.some(s => Number(s.sum_value) > 0);

            const chart = Highcharts.chart('pieByStatus', Highcharts.merge(baseHC, {
                chart: { height: 260, spacing: [8,8,8,8] },
                title: { text: 'Value by Status (SAR)' },
                credits: { enabled: false },
                series: hasPie ? [{
                    type: 'pie',
                    data: statusArr.map(s => ({ name: String(s.status || '—').toUpperCase(), y: Number(s.sum_value || 0) }))
                }] : []
            }));

            if (!hasPie && Highcharts.Chart.prototype.showNoData) chart.showNoData();
        }

        // MONTHLY CLUSTERED BAR (Eastern/Central/Western)
        const monthlyEl = document.getElementById('barMonthlyByArea');
        if (monthlyEl) {
            const monthly = resp.monthly_area || { categories: [], series: [] };
            const hasMonthly = (monthly.series||[]).some(s => (s.data||[]).some(v => Number(v) > 0));

            const chart = Highcharts.chart('barMonthlyByArea', Highcharts.merge(baseHC, {
                chart: { type: 'column' },
                title: { text: 'Inquiries by Month & Area' },
                xAxis: {
                    categories: monthly.categories || [],
                    labels: {
                        formatter(){
                            const [Y,M] = String(this.value||'').split('-');
                            if (!Y || !M) return this.value || '';
                            const d = new Date(+Y, +M-1, 1);
                            return d.toLocaleString('en', { month:'short', year:'2-digit' });
                        }
                    }
                },
                yAxis: { title: { text: 'Count' } },
                legend: { enabled: true },
                tooltip: {
                    shared: true,
                    headerFormat: '<b>{point.key}</b><br/>',
                    pointFormat: '<span style="color:{point.color}">●</span> {series.name}: <b>{point.y:,.0f}</b><br/>'
                },
                plotOptions: {
                    column: {
                        grouping: true,
                        groupPadding: 0.15,
                        pointPadding: 0.05,
                        dataLabels: { enabled: true, formatter(){ return (this.y ?? 0).toLocaleString(); } }
                    }
                },
                series: (monthly.series||[]).map(s => ({ type:'column', name:s.name, data:s.data || [] })),
                lang: { noData: 'No monthly data.' },
                noData: { style: { fontSize:'12px', color:'#6c757d' } }
            }));

            if (!hasMonthly && Highcharts.Chart.prototype.showNoData) chart.showNoData();
        }
    } // <-- END loadKpis()

    /* =============================================================================
     *  DATA TABLES (3 tabs)
     * ============================================================================= */
    const projectColumns = [
        {data: 'id', name: 'id', width: '64px'},
        {data: 'name', name: 'name'},
        {data: 'client', name: 'client'},
        {data: 'location', name: 'location'},
        {data: 'area_badge', name: 'area', orderable: true, searchable: false},
        {data: 'quotation_no', name: 'quotation_no'},
        {data: 'atai_products', name: 'atai_products'},
        {data: 'quotation_value_fmt', name: 'quotation_value', orderable: true, searchable: false, className: 'text-end'},
        {data: 'status_badge', name: 'status', orderable: true, searchable: false},
        {data: 'actions', orderable: false, searchable: false, className: 'text-end'}
    ];

    function initProjectsTable(selector, status, sumSelector) {
        const $table = $(selector);
        const $filterRow = $table.find('thead tr.filters');

        const table = $table.DataTable({
            processing: true,
            serverSide: true,
            order: [[0,'desc']],
            ajax: {
                url: DT_URL,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                dataSrc: (json) => json.data || [],
                data: (d) => {
                    d.status = status;
                    d.family = currentFamily || '';
                    const year   = document.querySelector('#projYear')?.value || '';
                    const month  = document.querySelector('#monthSelect')?.value || '';
                    const dFrom  = document.querySelector('#dateFrom')?.value || '';
                    const dTo    = document.querySelector('#dateTo')?.value || '';
                    const region = document.querySelector('#projRegion')?.value || '';
                    if (dFrom) d.date_from = dFrom;
                    if (dTo)   d.date_to   = dTo;
                    if (!dFrom && !dTo) {
                        if (month) d.month = month;
                        if (year)  d.year  = year;
                    }
                    if (region) d.region = region;
                }
            },
            columns: projectColumns,
            language: { search: "Global search:", lengthMenu: "Show _MENU_" },
            drawCallback: function() {
                const json = this.api().ajax.json() || {};
                const sum  = Number(json.sum_quotation_value || 0);
                const el   = document.querySelector(sumSelector);
                if (el) el.textContent = 'Total: ' + fmtSAR(sum);
            }
        });

        // Per-column filters
        $filterRow.find('th').each(function (i) {
            const $in  = $(this).find('input');
            const $sel = $(this).find('select');
            if ($in.length)  $in.on('keyup change', () => table.column(i).search($in.val()).draw());
            if ($sel.length) $sel.on('change',      () => table.column(i).search($sel.val()).draw());
        });

        return table;
    }

    // Modals: simple hooks (details/checklists wiring kept)
    const checklistBidding = [
        {key:"mep_contractor_appointed", label:"MEP Contractor appointed"},
        {key:"boq_quoted",               label:"BOQ  quoted"},
        {key:"boq_submitted",            label:"BOQ submitted & quoted"},
        {key:"priced_at_discount",       label:"Priced at discount"},
    ];
    const checklistInhand = [
        {key:"submittal_approved",       label:"Consultant approved — Submittal approved", weight:25},
        {key:"sample_approved",          label:"Consultant approved — Sample approved",     weight:25},
        {key:"commercial_terms_agreed",  label:"Commercial terms / Payment terms agreed",   weight:50},
        {key:"no_approval_or_terms",     label:"No consultant approval or commercial terms agreed", weight:0},
        {key:"discount_offered_as_standard", label:"Discount offered as per standard",      weight:0},
    ];

    function renderChecklist(containerId, list, prefix, values = {}) {
        const cont = document.getElementById(containerId);
        if (!cont) return;
        cont.innerHTML = '';
        list.forEach(item => {
            const id = `${prefix}_${item.key}`;
            const checked = !!values[item.key];
            cont.insertAdjacentHTML('beforeend', `
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="${id}" ${checked ? 'checked' : ''}>
        <label class="form-check-label" for="${id}">${item.label}</label>
      </div>`);
        });
    }

    function updateChecklistProgress(kind) {
        if (kind === 'bidding') {
            const keys = checklistBidding.map(i => i.key);
            const done = keys.filter(k => document.getElementById(`bid_${k}`)?.checked).length;
            const pct  = keys.length ? Math.round(done/keys.length*100) : 0;
            document.getElementById('biddingProgressPct').textContent = pct + '%';
            const bar = document.getElementById('biddingProgressBar');
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', String(pct));
            return;
        }
        let total = 0;
        checklistInhand.forEach(i => {
            const el = document.getElementById(`ih_${i.key}`);
            if (el && el.checked) total += (Number(i.weight) || 0);
        });
        const pct = Math.max(0, Math.min(100, Math.round(total)));
        document.getElementById('inhandProgressPct').textContent = pct + '%';
        const bar = document.getElementById('inhandProgressBar');
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', String(pct));
    }

    document.getElementById('biddingModal')?.addEventListener('change', (e) => {
        if (e.target && e.target.matches('input.form-check-input[id^="bid_"]')) updateChecklistProgress('bidding');
    });
    document.getElementById('inhandModal')?.addEventListener('change', (e) => {
        if (e.target && e.target.matches('input.form-check-input[id^="ih_"]')) updateChecklistProgress('inhand');
    });

    function normalizeRow(row) {
        const qVal = row.quotationValue ?? row.quotation_value ?? row.price ?? 0;
        return {
            id: row.id,
            name:         row.name           ?? row.projectName        ?? '-',
            client:       row.client         ?? row.clientName         ?? '-',
            location:     row.location       ?? row.projectLocation    ?? '-',
            area:         row.area           ?? '-',
            quotationValue: Number(qVal),
            quotationNo:  row.quotationNo    ?? row.quotation_no       ?? '-',
            ataiProducts: row.ataiProducts   ?? row.atai_products      ?? '-',
            quotationDate: row.quotationDate ?? row.quotation_date     ?? null,
            estimator:     row.estimator     ?? row.action1            ?? null,
            dateRec:       row.dateRec       ?? row.date_rec           ?? null,
            status: String(row.status ?? '').toLowerCase(),
            checklist: row.checklist ?? {},
            comments:  row.comments  ?? '',
        };
    }

    function fillDetails(dlId, p) {
        const dl = document.getElementById(dlId);
        if (!dl) return;
        const rows = [
            ['Project', p.name],
            ['Client', p.client],
            ['Location', p.location],
            ['Area', p.area || '—'],
            ['Quotation No', p.quotationNo || '—'],
            ...(p.quotationDate ? [['Quotation Date', p.quotationDate]] : []),
            ...(p.dateRec ?        [['Date Received', p.dateRec]]       : []),
            ['ATAI Products', p.ataiProducts || '—'],
            ...(p.estimator ? [['Estimator', p.estimator]] : []),
            ['Price', fmtSAR(Number(p.quotationValue || 0))],
            ['Status', String(p.status || '').toUpperCase()],
        ];
        dl.innerHTML = rows.map(([label, val]) =>
            `<dt class="col-5 text-muted">${label}</dt><dd class="col-7">${val ?? '—'}</dd>`).join('');
    }

    function openProjectModalFromData(row) {
        const p = normalizeRow(row);
        const values = p.checklist || {};
        let kind = 'bidding';
        if (p.status === 'inhand' || p.status === 'in-hand') kind = 'inhand';
        else if (p.status === 'lost') kind = 'lost';

        if (kind === 'bidding') {
            document.getElementById('biddingModalLabel').textContent = p.name || 'Project';
            fillDetails('biddingDetails', p);
            renderChecklist('biddingChecklist', checklistBidding, 'bid', values);
            updateChecklistProgress('bidding');
            new bootstrap.Modal(document.getElementById('biddingModal')).show();
            return;
        }
        if (kind === 'inhand') {
            document.getElementById('inhandModalLabel').textContent = p.name || 'Project';
            fillDetails('inhandDetails', p);
            renderChecklist('inhandChecklist', checklistInhand, 'ih', values);
            updateChecklistProgress('inhand');
            new bootstrap.Modal(document.getElementById('inhandModal')).show();
            return;
        }
        document.getElementById('lostModalLabel').textContent = p.name || 'Project';
        fillDetails('lostDetails', p);
        new bootstrap.Modal(document.getElementById('lostModal')).show();
    }

    // View button handler (fetch detail before opening)
    $(document).on('click', '[data-action="view"]', async function (e) {
        e.preventDefault();
        const $tr = $(this).closest('tr');
        const tryGetRow = (dt) => (dt ? dt.row($tr).data() : null);
        let row =
            tryGetRow($('#tblBidding').DataTable()) ||
            tryGetRow($('#tblInhand').DataTable()) ||
            tryGetRow($('#tblLost').DataTable());
        if (!row || !row.id) return;

        let detail = null;
        try {
            const res = await fetch(`/projects/${row.id}`, { credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
            if (res.ok) detail = await res.json();
        } catch (err) { console.warn('Detail fetch failed', err); }

        if (detail) row = hydrateRowWithDetail(row, detail);
        openProjectModalFromData(row);
    });

    // Resize columns when switching tabs
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            setTimeout(() => {
                if (dtBid)  dtBid.columns.adjust();
                if (dtIn)   dtIn.columns.adjust();
                if (dtLost) dtLost.columns.adjust();
            }, 50);
        });
    });

    // Global search
    let dtBid, dtIn, dtLost;
    document.getElementById('searchBidding')?.addEventListener('input', e => dtBid  && dtBid.search(e.target.value).draw());
    document.getElementById('searchInhand')?.addEventListener('input', e => dtIn   && dtIn.search(e.target.value).draw());
    document.getElementById('searchLost')  ?.addEventListener('input', e => dtLost && dtLost.search(e.target.value).draw());

    function getDT(sel){ return $.fn.dataTable.isDataTable(sel) ? $(sel).DataTable() : null; }

    // Family chips → refresh DT + KPI
    $(document).on('click', '#familyChips [data-family]', function (e) {
        e.preventDefault();
        $('#familyChips [data-family]').removeClass('active');
        this.classList.add('active');
        currentFamily = this.getAttribute('data-family') || '';
        getDT('#tblBidding')?.ajax.reload(null, false);
        getDT('#tblInhand')?.ajax.reload(null, false);
        getDT('#tblLost')?.ajax.reload(null, false);
        loadKpis();
    });

    // Apply filters
    document.getElementById('projApply')?.addEventListener('click', () => {
        PROJ_YEAR   = document.getElementById('projYear')?.value || '';
        PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';
        loadKpis();
        if (dtBid)  dtBid.ajax.reload(null, false);
        if (dtIn)   dtIn.ajax.reload(null, false);
        if (dtLost) dtLost.ajax.reload(null, false);
    });

    /* =============================================================================
     *  BOOT
     * ============================================================================= */
    (async function boot() {
        try {
            const res = await fetch('/me', { credentials:'same-origin' });
            if (!res.ok) throw new Error('Not authenticated');
            ATAI_ME = await res.json();
        } catch {
            window.location.href = '{{ route('login') }}';
            return;
        }

        CAN_VIEW_ALL = !!(ATAI_ME?.canViewAll);
        if (!CAN_VIEW_ALL) {
            document.getElementById('projRegionWrap')?.classList.add('d-none');
        } else {
            document.getElementById('salesmanWrap')?.classList.remove('d-none');
        }

        dtBid  = initProjectsTable('#tblBidding', 'bidding', '#sumBidding');
        dtIn   = initProjectsTable('#tblInhand', 'inhand', '#sumInhand');
        dtLost = initProjectsTable('#tblLost', 'lost',   '#sumLost');

        await loadKpis();
    })();

    function hydrateRowWithDetail(row, d) {
        return {
            ...row,
            name:            d.projectName     ?? row.name,
            client:          d.clientName      ?? row.client,
            location:        d.projectLocation ?? row.location,
            area:            d.area            ?? row.area,
            quotationNo:     d.quotationNo     ?? row.quotationNo,
            quotationDate:   d.quotationDate   ?? row.quotationDate,
            action1:         d.action1         ?? row.action1,
            ataiProducts:    d.ataiProducts    ?? row.ataiProducts,
            quotationValue:  d.quotationValue  ?? row.quotationValue,
            status:          d.status          ?? row.status,
            checklist:       d.checklist       ?? row.checklist,
            comments:        d.comments        ?? row.comments,
            dateRec:         d.dateRec         ?? row.dateRec,
            clientReference: d.clientReference ?? row.clientReference,
            projectType:     d.projectType     ?? row.projectType,
            salesperson:     d.salesperson     ?? row.salesperson,
        };
    }
</script>

</body>
</html>
