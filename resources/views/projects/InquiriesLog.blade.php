`{{-- resources/views/projects/index.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    {{-- DataTables (Bootstrap 5 build) --}}
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    {{-- Meta / Bootstrap --}}
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI Projects — Live</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        /* Make header filter row look light */
        table.dataTable thead tr.filters th { background: var(--bs-tertiary-bg); }
        table.dataTable thead .form-control-sm, table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }
        /* Keep action column content to the right */
        #tblBidding td:last-child, #tblInhand td:last-child, #tblLost td:last-child, #tblPOreceived td:last-child { text-align: end; }
        /* Chart cards – compact like Sales Orders dashboard */
        .kpi-card .hc { height: 260px; }
        .kpi-card .card-body { padding: .75rem 1rem; }
    </style>
</head>
<body>

@php $u = auth()->user(); @endphp
<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        {{-- Brand (left) --}}
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2">
            <span class="brand-word">ATAI</span>
        </a>

        {{-- Toggler (mobile) --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ataiNav"
                aria-controls="ataiNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Collapse --}}
        <div class="collapse navbar-collapse" id="ataiNav">
            {{-- Centered nav (desktop); scrollable row (mobile) --}}
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Quotation KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs(['inquiries.index']) ? 'active' : '' }}"
                       href="{{ route('inquiries.index') }}">Quotation Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.kpi') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.kpi') }}">Sales Order Log KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.index') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.index') }}">Sales Order Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">Estimation</a>
                </li>

                @hasanyrole('gm|admin')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.*') ? 'active' : '' }}"
                       href="{{ route('salesorders.index') }}">Sales Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.area*') ? 'active' : '' }}"
                       href="{{ route('performance.area') }}">Area summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active' : '' }}"
                       href="{{ route('performance.salesman') }}">SalesMan summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.product*') ? 'active' : '' }}"
                       href="{{ route('performance.product') }}">Product summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                       href="{{ route('powerbi.jump') }}">Accounts Summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                       href="{{ route('powerbi.jump') }}">Power BI Dashboard</a>
                </li>
                @endhasanyrole
            </ul>

            {{-- Right block (far-right on desktop; full-width row on mobile) --}}
            <div class="navbar-right">
                <div class="navbar-text me-2">
                    Logged in as <strong>{{ $u->name ?? '' }}</strong>
                    @if(!empty($u->region))
                        · <small>{{ $u->region }}</small>
                    @endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                    <button class="btn btn-logout btn-sm" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">

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
                <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:auto" placeholder="From">
                <input type="date" id="dateTo" class="form-control form-control-sm" style="width:auto" placeholder="To">

                {{-- GM/Admin: optional free-text salesman filter for forecast --}}
                <span id="salesmanWrap" class="d-none">
                    <input type="text" id="salesmanInput" class="form-control form-control-sm" style="width:14rem"
                           placeholder="Salesman (GM/Admin)">
                </span>

                <button class="btn btn-sm btn-primary" id="projApply">Update</button>
            </div>
        </div>

{{--        <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">--}}
{{--            <span id="kpiBadgeProjects" class="badge-total text-bg-info">Total Quotations No. : 0</span>--}}
{{--            <span id="kpiBadgeValue" class="badge-total text-bg-primary">Total Quotations Value: SAR 0</span>--}}
{{--        </div>--}}


        <div class="row g-3 mb-4 text-center justify-content-center">
            <div class="col-6 col-md col-lg">
                <div class="kpi-card shadow-sm p-5 h-150">
                    <div class="kpi-label"></div>
                    <div id="kpiBadgeProjects" class="kpi-value">SAR 0</div>
                </div>
            </div>
            <div class="col-6 col-md col-lg">
                <div class="kpi-card shadow-sm p-5 h-150">
                    <div class="kpi-label"></div>
                    <div id="kpiBadgeValue" class="kpi-value">0</div>
                </div>
            </div>

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

    {{-- ===== TABS (Bidding / In-Hand / Lost / PO received) ===== --}}
{{--    <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">--}}
{{--        <div id="familyChips" class="btn-group" role="group" aria-label="Product family">--}}
{{--            <button class="nav-link active" data-bs-target="#bidding" data-bs-toggle="tab" type="button" role="tab">Bidding</button>--}}
{{--            <button class="nav-link" data-bs-target="#inhand" data-bs-toggle="tab" type="button" role="tab">In-Hand</button>--}}
{{--            <button class="nav-link" data-bs-target="#lost" data-bs-toggle="tab" type="button" role="tab">Lost</button>--}}
{{--        --}}
{{--        </div>--}}
{{--    </div>--}}


    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-target="#bidding" data-bs-toggle="tab" type="button" role="tab">Bidding</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#inhand" data-bs-toggle="tab" type="button" role="tab">In-Hand</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#lost" data-bs-toggle="tab" type="button" role="tab">Lost</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#POreceived" data-bs-toggle="tab" type="button" role="tab">PO received</button>
        </li>
    </ul>

    <div class="tab-content border-start border-end border-bottom p-3 rounded-bottom">
        {{-- ---------- BIDDING TAB ---------- --}}
        <div class="tab-pane fade show active" id="bidding" role="tabpanel" tabindex="0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group w-auto">
                    <span class="input-group-text">Search</span>
                    <input id="searchBidding" type="text" class="form-control" placeholder="Project, client, location…">
                </div>

{{--                <span id="sumBidding" class="badge-total text-bg-primary"> SAR 0</span>--}}




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
{{--                <span id="sumInhand" class="badge-total text-bg-info">Total: SAR 0</span>--}}
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
{{--                <span id="sumLost" class="badge-total text-bg-danger">Total: SAR 0</span>--}}
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
                        <th>Quotation Date</th>

                        <th>ATAI Products</th>
                        <th>Quotation Price</th>

                        <th>Status</th>
{{--                        <th class="text-end">Actions</th>--}}
                    </tr>
                    </thead>
                </table>
            </div>
        </div>

        {{-- ---------- PO RECEIVED TAB ---------- --}}
        <div class="tab-pane fade" id="POreceived" role="tabpanel" tabindex="0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="input-group w-auto">
                    <span class="input-group-text">Search</span>
                    <input id="searchPOreceived" type="text" class="form-control" placeholder="Project, client, location…">
                </div>
{{--                <span id="sumPOreceived" class="badge-total text-bg-primary">Total: SAR 0</span>--}}
            </div>

            <div class="table-responsive">
                <table class="table table-striped w-100" id="tblPOreceived">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Area</th>
                        <th>Quotation No</th>
                        <th>Quotation Date</th>
                        <th>PO No(s)</th>
                        <th>PO Date</th>
                        <th>ATAI Products</th>
                        <th>Quotation Price</th>
                        <th>PO Value</th>
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
<div class="modal fade modal-atai" id="biddingModal" tabindex="-1" aria-hidden="true">
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
                <div class="modal-action">

                    <button class="btn btn-danger" id="BiddingLostBtn" type="button">LOST</button>
                    <button class="btn btn-success" id="BiddingPOBtn" type="button">PO RECEIVED</button>
                </div>
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="saveBiddingBtn" type="button">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- ===== IN-HAND MODAL ===== --}}
<div class="modal fade modal-atai" id="inhandModal" tabindex="-1" aria-hidden="true">
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
                <div class="modal-action">
                    <button class="btn btn-danger" id="LostStatusBtn" type="button">LOST</button>
                    <button class="btn btn-success" id="POreceivedStatusBtn" type="button">PO RECEIVED</button>
                </div>
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="saveInhandBtn" type="button">Save</button>
            </div>






        </div>
    </div>
</div>

{{-- ===== LOST MODAL ===== --}}
<div class="modal fade modal-atai" id="lostModal" tabindex="-1" aria-hidden="true">
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

{{-- ===== PO RECEIVED MODAL ===== --}}
<div class="modal fade modal-atai" id="POreceivedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dia log modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="POreceivedModalLabel">PO received Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Details</h6>
                                <dl class="row mb-0" id="POreceivedDetails"></dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Notes</h6>
                                <div id="POreceivedNotesList" class="vstack gap-1 small mb-3"></div>
                                <label for="POreceivedComments" class="form-label mb-1">Add a note</label>
                                <textarea id="POreceivedComments" class="form-control" rows="3"
                                          placeholder="Delivery plan, PO milestones, etc."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Close</button>
                <button class="btn btn-primary" id="savePOreceivedBtn" type="button">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Toast (generic success) --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
    <div id="successToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg">Updated.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-body text-center p-4">
                <div id="confirmIcon" class="mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:2.4rem;"></i>
                </div>
                <h5 class="fw-bold mb-2" id="confirmTitle">Confirm</h5>
                <p class="text-muted mb-4" id="confirmText">Are you sure?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn px-4" id="confirmOkBtn">Continue</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Scripts --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
    /* =============================================================================
     *  CONFIG & HELPERS
     * ============================================================================= */
    const API    = @json(url('/api'));
    const DT_URL = @json(route('projects.datatable'));
    const $      = window.jQuery;

    let CURRENT_PROJECT_ID = null;
    let CURRENT_QUOTATION_NO = '';
    $.fn.dataTable.ext.errMode = 'console';

    const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
        style: 'currency', currency: 'SAR', maximumFractionDigits: 0
    }).format(Number(n || 0));

    // global state
    let PROJ_YEAR   = '';
    let PROJ_REGION = '';
    let ATAI_ME     = null;
    let CAN_VIEW_ALL = false;
    let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

    /* =============================================================================
     *  DATA TABLES (4 tabs)
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

    // PO/Lost tabs need extra columns
    const projectColumnsPO = [
        {data: 'id', name: 'id', width: '64px'},
        {data: 'name', name: 'name'},
        {data: 'client', name: 'client'},
        {data: 'location', name: 'location'},
        {data: 'area_badge', name: 'area', orderable: true, searchable: false},
        {data: 'quotation_no', name: 'quotation_no'},
        {data: 'quotation_date', name: 'quotation_date'},
        {data: 'po_nos', name: 'po_nos'},
        {data: 'po_date', name: 'po_date'},
        {data: 'atai_products', name: 'atai_products'},
        {data: 'quotation_value_fmt', name: 'quotation_value', orderable: true, searchable: false, className: 'text-end'},
        {data: 'total_po_value_fmt', name: 'total_po_value', orderable: true, searchable: false, className: 'text-end'},
        {data: 'status_badge', name: 'status', orderable: true, searchable: false},
        {data: 'actions', orderable: false, searchable: false, className: 'text-end'}
    ];

    function initProjectsTable(selector, status, sumSelector) {
        const $table = $(selector);
        const statusMap = { bidding: 'Bidding', inhand: 'In-Hand', lost: 'Lost', poreceived: 'PO-Received' };
        const cols = (status === 'poreceived') ? projectColumnsPO : projectColumns;

        return $table.DataTable({
            processing: true,
            serverSide: true,
            order: [[0, 'desc']],
            ajax: {
                url: DT_URL,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: (d) => {
                    d.status_norm = statusMap[status] || '';
                    d.family = currentFamily || '';

                    const year  = document.querySelector('#projYear')?.value || '';
                    const month = document.querySelector('#monthSelect')?.value || '';
                    const dFrom = document.querySelector('#dateFrom')?.value || '';
                    const dTo   = document.querySelector('#dateTo')?.value || '';

                    const areaSel = document.querySelector('#projRegion')?.value || '';
                    const area = CAN_VIEW_ALL ? areaSel : (PROJ_REGION || '');

                    if (dFrom) d.date_from = dFrom;
                    if (dTo)   d.date_to   = dTo;
                    if (!dFrom && !dTo) {
                        if (month) d.month = month;
                        const yr = year || PROJ_YEAR || '';
                        if (yr) d.year = yr;
                    }
                    if (area) d.area = area;
                },
                dataSrc: (json) => {
                    // 1) Immediately set header with GLOBAL values from this single response
                    const pEl = document.getElementById('kpiBadgeProjects'); // count
                    const vEl = document.getElementById('kpiBadgeValue');    // value

                    if (pEl) pEl.textContent = `Total Quotation No : ${Number(json?.recordsTotal || 0)}`;
                    if (vEl) vEl.textContent = `Total Quotation Value: ${json?.header_sum_value_fmt || 'SAR 0'}`;
                    FIRST_HEADER_PRIMED = true;

                    // 2) Store this tab’s totals (for switching)
                    const tabSum = Number(json?.sum_quotation_value || 0);
                    const tabCnt = Number(json?.recordsFiltered || 0);
                    TAB_SUMS[status]   = tabSum;
                    TAB_COUNTS[status] = tabCnt;
                    TAB_LOADED[status] = true;

                    // 3) Update small badge inside this tab
                    const el = document.querySelector(sumSelector);
                    if (el) el.textContent = 'Total: ' + fmtSAR(tabSum);

                    // 4) If the user is currently looking at this tab, override the header with this tab’s totals
                    if (SHOW_CURRENT_TAB_ONLY) updateHeaderBadges(status);

                    return json?.data || [];
                }
            },
            columns: cols
        });
    }

    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
            const tabId = e.target.getAttribute('data-bs-target') || '';
            let key = null;
            if (tabId.includes('bidding'))    key = 'bidding';
            if (tabId.includes('inhand'))     key = 'inhand';
            if (tabId.includes('lost'))       key = 'lost';
            if (tabId.includes('POreceived')) key = 'poreceived';

            SHOW_CURRENT_TAB_ONLY = !!key;
            updateHeaderBadges(key);
        });
    });
    /* =============================================================================
     *  CHECKLISTS + DETAIL HELPERS
     * ============================================================================= */
    const checklistBidding = [
        {key: "mep_contractor_appointed", label: "MEP Contractor appointed"},
        {key: "boq_quoted",               label: "BOQ  quoted"},
        {key: "boq_submitted",            label: "BOQ submitted"},
        {key: "priced_at_discount",       label: "Priced at discount"},
    ];
    const checklistInhand = [
        {key: "submittal_approved",         label: "Consultant approved — Submittal approved",        weight: 25},
        {key: "sample_approved",            label: "Consultant approved — Sample approved",            weight: 25},
        {key: "commercial_terms_agreed",    label: "Commercial terms / Payment terms agreed",          weight: 50},
        {key: "no_approval_or_terms",       label: "No consultant approval or commercial terms agreed",weight: 0},
        {key: "discount_offered_as_standard",label:"Discount offered as per standard",                 weight: 0},
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
            const pct = keys.length ? Math.round(done / keys.length * 100) : 0;
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
            ...row,
            id: row.id,
            name: row.name ?? row.projectName ?? '-',
            client: row.client ?? row.clientName ?? '-',
            location: row.location ?? row.projectLocation ?? '-',
            area: row.area ?? '-',
            quotationValue: Number(qVal),
            quotationNo: row.quotationNo ?? row.quotation_no ?? '-',
            ataiProducts: row.ataiProducts ?? row.atai_products ?? '-',
            quotationDate: row.quotationDate ?? row.quotation_date ?? null,
            estimator: row.estimator ?? row.action1 ?? null,
            dateRec: row.dateRec ?? row.date_rec ?? null,
            status: String(row.status ?? '').toLowerCase(),
            checklist: row.checklist ?? {},
            comments: row.comments ?? '',
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
            ...(p.dateRec ? [['Date Received', p.dateRec]] : []),
            ['ATAI Products', p.ataiProducts || '—'],
            ...(p.estimator ? [['Estimator', p.estimator]] : []),
            ['Price', fmtSAR(Number(p.quotationValue || 0))],
            ['Status', String(p.status || '').toUpperCase()],
        ];
        dl.innerHTML = rows.map(([label, val]) =>
            `<dt class="col-5 text-muted">${label}</dt><dd class="col-7">${val ?? '—'}</dd>`).join('');
    }

    function normalizeStatusToKind(s) {
        const t = String(s || '').toLowerCase().trim();
        if (/^po[-_\s]?received|po[-_\s]?recieved$/.test(t) || t === 'po') return 'poreceived';
        if (/^in[\s-]?hand$/.test(t) || ['accepted','won','order','order in hand','ih'].includes(t)) return 'inhand';
        if (['bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry'].includes(t)) return 'bidding';
        if (['lost','rejected','cancelled','canceled','closed lost','declined','not awarded'].includes(t)) return 'lost';
        return 'bidding';
    }

    function openProjectModalFromData(row) {
        const p = normalizeRow(row);
        CURRENT_PROJECT_ID   = p.id || row.id || null;
        CURRENT_QUOTATION_NO = p.quotationNo || p.quotation_no || '-';

        const kind = normalizeStatusToKind(p.status);

        if (kind === 'bidding') {
            document.getElementById('biddingModalLabel').textContent = p.name || 'Project';
            fillDetails('biddingDetails', p);
            renderChecklist('biddingChecklist', checklistBidding, 'bid', p.checklistBidding || {});
            if (typeof p.biddingProgress === 'number') {
                const pct = p.biddingProgress;
                document.getElementById('biddingProgressPct').textContent = pct + '%';
                const bar = document.getElementById('biddingProgressBar');
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', String(pct));
            } else {
                updateChecklistProgress('bidding');
            }
            let notesWrap = document.getElementById('biddingNotesBody');
            if (!notesWrap) {
                const wrap = document.createElement('div');
                wrap.className = 'mt-3 small';
                wrap.innerHTML = '<div class="fw-semibold mb-1">Saved notes</div><div id="biddingNotesBody" class="vstack gap-1"></div>';
                document.querySelector('#biddingModal .card-body').appendChild(wrap);
                notesWrap = document.getElementById('biddingNotesBody');
            }
            notesWrap.innerHTML = (p.notes || []).map(n =>
                `<div class="border-start ps-2">
               <div class="text-muted">${(n.created_at || '').replace('T', ' ').replace('Z', '')}</div>
               <div>${n.note}</div>
             </div>`).join('') || '<div class="text-muted">No notes yet.</div>';

            new bootstrap.Modal(document.getElementById('biddingModal')).show();
            return;
        }

        if (kind === 'inhand') {
            document.getElementById('inhandModalLabel').textContent = p.name || 'Project';
            fillDetails('inhandDetails', p);
            renderChecklist('inhandChecklist', checklistInhand, 'ih', p.checklistInhand || {});
            if (typeof p.inhandProgress === 'number') {
                const pct = p.inhandProgress;
                document.getElementById('inhandProgressPct').textContent = pct + '%';
                const bar = document.getElementById('inhandProgressBar');
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', String(pct));
            } else {
                updateChecklistProgress('inhand');
            }
            let notesWrap = document.getElementById('inhandNotesBody');
            if (!notesWrap) {
                const wrap = document.createElement('div');
                wrap.className = 'mt-3 small';
                wrap.innerHTML = '<div class="fw-semibold mb-1">Saved notes</div><div id="inhandNotesBody" class="vstack gap-1"></div>';
                document.querySelector('#inhandModal .card-body').appendChild(wrap);
                notesWrap = document.getElementById('inhandNotesBody');
            }
            notesWrap.innerHTML = (p.notes || []).map(n =>
                `<div class="border-start ps-2">
               <div class="text-muted">${(n.created_at || '').replace('T', ' ').replace('Z', '')}</div>
               <div>${n.note}</div>
             </div>`).join('') || '<div class="text-muted">No notes yet.</div>';

            new bootstrap.Modal(document.getElementById('inhandModal')).show();
            return;
        }

        if (kind === 'lost') {
            document.getElementById('lostModalLabel').textContent = p.name || 'Project';
            fillDetails('lostDetails', p);
            new bootstrap.Modal(document.getElementById('lostModal')).show();
            return;
        }

        if (kind === 'poreceived') {
            document.getElementById('POreceivedModalLabel').textContent = p.name || 'Project';
            fillDetails('POreceivedDetails', p);
            const list = document.getElementById('POreceivedNotesList');
            if (list) {
                list.innerHTML = (p.notes || []).map(n => `
                <div class="border-start ps-2">
                  <div class="text-muted">${(n.created_at || '').replace('T',' ').replace('Z','')}</div>
                  <div>${n.note}</div>
                </div>`).join('') || '<div class="text-muted">No notes yet.</div>';
            }
            new bootstrap.Modal(document.getElementById('POreceivedModal')).show();
            return;
        }
    }

    // View button handler (fetch detail before opening)
    $(document).on('click', '[data-action="view"]', async function (e) {
        e.preventDefault();
        const $tr = $(this).closest('tr');
        const tryGetRow = (dt) => (dt ? dt.row($tr).data() : null);
        let row =
            tryGetRow($('#tblBidding').DataTable()) ||
            tryGetRow($('#tblInhand').DataTable()) ||
            tryGetRow($('#tblLost').DataTable()) ||
            tryGetRow($('#tblPOreceived').DataTable());
        if (!row || !row.id) return;

        let detail = null;
        try {
            const res = await fetch(`/projects/${row.id}`, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (res.ok) detail = await res.json();
        } catch (err) {
            console.warn('Detail fetch failed', err);
        }

        if (detail) row = hydrateRowWithDetail(row, detail);
        openProjectModalFromData(row);
    });

    // Resize columns when switching tabs
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            setTimeout(() => {
                if (dtBid) dtBid.columns.adjust();
                if (dtIn)  dtIn.columns.adjust();
                if (dtLost) dtLost.columns.adjust();
                if (dtPO)  dtPO.columns.adjust();
            }, 50);
        });
    });

    // Global search
    let dtBid, dtIn, dtLost, dtPO;
    document.getElementById('searchBidding')?.addEventListener('input', e => dtBid && dtBid.search(e.target.value).draw());
    document.getElementById('searchInhand')?.addEventListener('input', e => dtIn && dtIn.search(e.target.value).draw());
    document.getElementById('searchLost')?.addEventListener('input', e => dtLost && dtLost.search(e.target.value).draw());
    document.getElementById('searchPOreceived')?.addEventListener('input', e => dtPO && dtPO.search(e.target.value).draw());
    function getDT(sel) { return $.fn.dataTable.isDataTable(sel) ? $(sel).DataTable() : null; }

    // Family chips → refresh DT + KPI

    $(document).on('click', '#familyChips [data-family]', function (e) {
        e.preventDefault();
        $('#familyChips [data-family]').removeClass('active');
        this.classList.add('active');
        currentFamily = this.getAttribute('data-family') || '';
        resetTabTotals(); // important
        dtBid?.ajax.reload(null, false);
        dtIn?.ajax.reload(null, false);
        dtLost?.ajax.reload(null, false);
        dtPO?.ajax.reload(null, false);
    });
    // Apply filters
    document.getElementById('projApply')?.addEventListener('click', () => {
        PROJ_YEAR   = document.getElementById('projYear')?.value || '';
        PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';
        resetTabTotals(); // important
        dtBid?.ajax.reload(null, false);
        dtIn?.ajax.reload(null, false);
        dtLost?.ajax.reload(null, false);
        dtPO?.ajax.reload(null, false);
    });
    /* =============================================================================
     *  BOOT
     * ============================================================================= */
    (async function boot() {
        try {
            const res = await fetch('/me', {credentials: 'same-origin'});
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

        dtBid = initProjectsTable('#tblBidding', 'bidding',   '#sumBidding');
        dtIn  = initProjectsTable('#tblInhand',  'inhand',    '#sumInhand');
        dtLost= initProjectsTable('#tblLost',    'lost',      '#sumLost');
        dtPO  = initProjectsTable('#tblPOreceived', 'poreceived', '#sumPOreceived');
    })();

    function hydrateRowWithDetail(row, d) {
        return {
            ...row,
            name: d.projectName ?? row.name,
            client: d.clientName ?? row.client,
            location: d.projectLocation ?? row.location,
            area: d.area ?? row.area,
            quotationNo: d.quotationNo ?? row.quotationNo,
            quotationDate: d.quotationDate ?? row.quotationDate,
            action1: d.action1 ?? row.action1,
            ataiProducts: d.ataiProducts ?? row.ataiProducts,
            quotationValue: d.quotationValue ?? row.quotationValue,
            status: d.status ?? row.status,

            checklistBidding: d.checklistBidding ?? {},
            checklistInhand:  d.checklistInhand ?? {},

            biddingProgress: d.biddingProgress,
            inhandProgress:  d.inhandProgress,

            notes: d.notes ?? [],
            progressHistory: d.progressHistory ?? [],

            dateRec: d.dateRec ?? row.dateRec,
            clientReference: d.clientReference ?? row.clientReference,
            projectType: d.projectType ?? row.projectType,
            salesperson: d.salesperson ?? row.salesperson,
        };
    }

    // Totals state for header
    // ===== Totals state =====
    let TAB_SUMS   = { bidding: 0, inhand: 0, lost: 0, poreceived: 0 };
    let TAB_COUNTS = { bidding: 0, inhand: 0, lost: 0, poreceived: 0 };

    // Track which tabs have finished their first load
    let TAB_LOADED = { bidding: false, inhand: false, lost: false, poreceived: false };

    // Header behavior flags
    let SHOW_CURRENT_TAB_ONLY = false; // toggled by tab clicks
    let FIRST_HEADER_PRIMED   = false; // set once we used recordsTotal the very first time






    function resetTabTotals() {
        TAB_SUMS   = { bidding: 0, inhand: 0, lost: 0, poreceived: 0 };
        TAB_COUNTS = { bidding: 0, inhand: 0, lost: 0, poreceived: 0 };
        TAB_LOADED = { bidding: false, inhand: false, lost: false, poreceived: false };
        SHOW_CURRENT_TAB_ONLY = false;
        FIRST_HEADER_PRIMED   = false; // allow priming again after filter change
        updateHeaderBadges();
    }
    function updateHeaderBadges(currentTab = null) {
        let totalCnt, totalSum;

        if (SHOW_CURRENT_TAB_ONLY && currentTab) {
            totalCnt = TAB_COUNTS[currentTab] || 0;
            totalSum = TAB_SUMS[currentTab] || 0;
        } else {
            // fallback to global already set in dataSrc; nothing to do here
            return;
        }

        const pEl = document.getElementById('kpiBadgeProjects');
        const vEl = document.getElementById('kpiBadgeValue');
        if (pEl) pEl.textContent = `Total Quotation No : ${totalCnt}`;
        if (vEl) vEl.textContent = `Total Quotation Value: ${fmtSAR(totalSum)}`;
    }
    function primeHeaderFromResponse(json) {
        if (FIRST_HEADER_PRIMED) return;
        const pEl = document.getElementById('kpiBadgeProjects');
        const vEl = document.getElementById('kpiBadgeValue');
        if (pEl) pEl.textContent = `Total Quotation No : ${Number(json?.recordsTotal || 0)}`;
        // While sums for all tabs load, show "calculating…" or leave as-is
        if (vEl) vEl.textContent = `Total Quotation Value: calculating…`;
        FIRST_HEADER_PRIMED = true;
    }
    function showToast(msg) {
        const toastEl = document.getElementById('successToast');
        if (!toastEl) return;
        document.getElementById('toastMsg').textContent = msg || 'Updated.';
        new bootstrap.Toast(toastEl).show();
    }

    async function postProjectUpdate(pid, payload) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(`/projects/${pid}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            const j = await res.json().catch(() => ({}));
            throw new Error(j?.message || res.statusText);
        }
        return res.json();
    }

    /* =============================================================================
     *  SAVE / STATUS HANDLERS
     * ============================================================================= */

    document.getElementById('saveBiddingBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');
        const checked = (id) => !!document.getElementById(id)?.checked;
        const payloadChecklist = {
            action: 'checklist_bidding',
            mep_contractor_appointed: checked('bid_mep_contractor_appointed'),
            boq_quoted:               checked('bid_boq_quoted'),
            boq_submitted:            checked('bid_boq_submitted'),
            priced_at_discount:       checked('bid_priced_at_discount')
        };

        try {
            const r1 = await postProjectUpdate(CURRENT_PROJECT_ID, payloadChecklist);
            if (r1?.ok) {
                const pct = Number(r1.progress || 0);
                document.getElementById('biddingProgressPct').textContent = pct + '%';
                const bar = document.getElementById('biddingProgressBar');
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', String(pct));
            }

            const noteText = (document.getElementById('biddingComments')?.value || '').trim();
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, {action: 'add_note', note: noteText});
                document.getElementById('biddingComments').value = '';
            }

            showToast('Bidding checklist saved.');
            getDT('#tblBidding')?.ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });

    document.getElementById('saveInhandBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');
        const checked = (id) => !!document.getElementById(id)?.checked;
        const payload = {
            action: 'checklist_inhand',
            submittal_approved:          checked('ih_submittal_approved'),
            sample_approved:             checked('ih_sample_approved'),
            commercial_terms_agreed:     checked('ih_commercial_terms_agreed'),
            no_approval_or_terms:        checked('ih_no_approval_or_terms'),
            discount_offered_as_standard:checked('ih_discount_offered_as_standard'),
        };

        try {
            const r = await postProjectUpdate(CURRENT_PROJECT_ID, payload);
            if (r?.ok) {
                const pct = Number(r.progress || 0);
                document.getElementById('inhandProgressPct').textContent = pct + '%';
                const bar = document.getElementById('inhandProgressBar');
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', String(pct));
            }

            const noteText = (document.getElementById('inhandComments')?.value || '').trim();
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, {action: 'add_note', note: noteText});
                document.getElementById('inhandComments').value = '';
            }

            showToast('In-Hand checklist saved.');
            getDT('#tblInhand')?.ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });

    document.getElementById('savePOreceivedBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');
        const noteText = (document.getElementById('POreceivedComments')?.value || '').trim();
        try {
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, { action: 'add_note', note: noteText });
                document.getElementById('POreceivedComments').value = '';
            }
            showToast('PO received note saved.');
            getDT('#tblPOreceived')?.ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });

    // Confirmation dialog helper
    function showConfirmDialog({ title, html, confirmText='Continue', tone='warning' }) {
        return new Promise((resolve) => {
            const toneMap = {
                warning: { icon: 'bi-exclamation-triangle-fill text-warning', btn: 'btn-warning' },
                danger:  { icon: 'bi-x-octagon-fill text-danger',            btn: 'btn-danger'  },
                success: { icon: 'bi-check-circle-fill text-success',        btn: 'btn-success' },
                info:    { icon: 'bi-info-circle-fill text-info',            btn: 'btn-info'    }
            };
            const t = toneMap[tone] || toneMap.warning;

            document.getElementById('confirmTitle').textContent = title || 'Confirm';
            document.getElementById('confirmText').innerHTML    = html || 'Are you sure?';
            document.getElementById('confirmIcon').innerHTML    = `<i class="bi ${t.icon}" style="font-size:2.4rem;"></i>`;

            const okBtn = document.getElementById('confirmOkBtn');
            okBtn.textContent = confirmText;
            okBtn.className   = 'btn ' + t.btn + ' px-4';

            const el = document.getElementById('confirmActionModal');
            const modal = new bootstrap.Modal(el, { backdrop: true, focus: true });
            let done = false;

            const finalize = (val) => {
                if (done) return;
                done = true;
                modal.hide();
                el.removeEventListener('hidden.bs.modal', onHidden);
                resolve(val);
            };
            const onHidden = () => finalize(false);

            el.addEventListener('hidden.bs.modal', onHidden, { once: true });
            okBtn.onclick = () => finalize(true);

            modal.show();
        });
    }

    async function changeProjectStatus(toStatus) {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');
        const confirmed = await showConfirmDialog({
            title: 'Confirm status change',
            html: `Are you sure you want to mark quotation <b>${CURRENT_QUOTATION_NO}</b> as <b>${toStatus.toUpperCase()}</b>?`,
            confirmText: 'Yes, update',
            tone: /^lost$/i.test(toStatus) ? 'danger' : 'success'
        });
        if (!confirmed) return;

        // Disable any visible buttons during update
        ['LostStatusBtn','POreceivedStatusBtn','BiddingLostBtn','BiddingPOBtn'].forEach(id => {
            const b = document.getElementById(id);
            if (b) b.setAttribute('disabled','disabled');
        });

        try {
            const res = await postProjectUpdate(CURRENT_PROJECT_ID, {
                action: 'update_status',
                to_status: toStatus
            });
            if (!res?.ok) throw new Error(res?.message || 'Update failed');

            // Close any open modal cleanly
            ['inhandModal','biddingModal','lostModal','POreceivedModal'].forEach(mid => {
                const el = document.getElementById(mid);
                const inst = el ? bootstrap.Modal.getInstance(el) : null;
                inst?.hide();
            });
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');

            const qno = CURRENT_QUOTATION_NO || '';
            showToast(`Quotation ${qno} moved to ${toStatus.toUpperCase()}.`);

            // Refresh all tables
            getDT('#tblInhand')?.ajax.reload(null, false);
            getDT('#tblLost')?.ajax.reload(null, false);
            getDT('#tblPOreceived')?.ajax.reload(null, false);
            getDT('#tblBidding')?.ajax.reload(null, false);

        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        } finally {
            ['LostStatusBtn','POreceivedStatusBtn','BiddingLostBtn','BiddingPOBtn'].forEach(id => {
                const b = document.getElementById(id);
                if (b) b.removeAttribute('disabled');
            });
        }
    }

    // Wire buttons (In-Hand + Bidding)
    document.getElementById('LostStatusBtn')?.addEventListener('click', () => changeProjectStatus('Lost'));
    document.getElementById('POreceivedStatusBtn')?.addEventListener('click', () => changeProjectStatus('PO-received'));
    document.getElementById('BiddingLostBtn')?.addEventListener('click', () => changeProjectStatus('Lost'));
    document.getElementById('BiddingPOBtn')?.addEventListener('click', () => changeProjectStatus('PO-received'));

</script>
</body>
</html>
`
