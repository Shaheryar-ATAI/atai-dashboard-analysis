{{-- resources/views/projects/index.blade.php --}}
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
        table.dataTable thead tr.filters th { background: var(--bs-tertiary-bg); }
        table.dataTable thead .form-control-sm, table.dataTable thead .form-select-sm { height: calc(1.5em + .5rem + 2px); }
        #tblBidding td:last-child, #tblInhand td:last-child, #tblLost td:last-child, #tblPOreceived td:last-child { text-align: end; }

        /* KPI header cards */
        .kpi-card .hc { height: 260px; }
        .kpi-card .card-body { padding: .75rem 1rem; }
        .kpi-value { font-size: 1.8rem; font-weight: 800; letter-spacing: .3px; }
        .kpi-label { font-size: .8rem; text-transform: uppercase; color: #6b7280; }
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

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ataiNav"
                aria-controls="ataiNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="ataiNav">
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
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('forecast.*') ? 'active' : '' }}" href="{{ route('forecast.create') }}">
                        Forecast
                    </a>
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

            <div class="navbar-right">
                <div class="navbar-text me-2">
                    Logged in as <strong>{{ $u->name ?? '' }}</strong>
                    @if(!empty($u->region)) · <small>{{ $u->region }}</small> @endif
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
                <select id="monthSelect" class="form-select form-select-sm" style="width:auto">
                    <option value="">All Months</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>
                    @endfor
                </select>

                <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:auto" placeholder="From">
                <input type="date" id="dateTo" class="form-control form-control-sm" style="width:auto" placeholder="To">

                <span id="salesmanWrap" class="d-none">
                    <input type="text" id="salesmanInput" class="form-control form-control-sm" style="width:14rem"
                           placeholder="Salesman (GM/Admin)">
                </span>

                <button class="btn btn-sm btn-primary" id="projApply">Update</button>
            </div>
        </div>

        {{-- KPI cards (compact) --}}
        <div class="row g-3 mb-4 text-center justify-content-center">
            <div class="col-6 col-md col-lg">
                <div class="kpi-card shadow-sm p-4 h-150">
                    <div class="kpi-label">Total Quotations</div>
                    <div id="kpiBadgeProjects" class="kpi-value">0</div>
                </div>
            </div>
            <div class="col-6 col-md col-lg">
                <div class="kpi-card shadow-sm p-4 h-150">
                    <div class="kpi-label">Total Value</div>
                    <div id="kpiBadgeValue" class="kpi-value">SAR 0</div>
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

        {{-- ===== TABS (Bidding / In-Hand / Lost / PO received / No PO) ===== --}}
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-target="#bidding" data-bs-toggle="tab" type="button" role="tab">Bidding</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-target="#inhand" data-bs-toggle="tab" type="button" role="tab">In-Hand</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-target="#lost" data-bs-toggle="tab" type="button" role="tab">Lost</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-target="#POreceived" data-bs-toggle="tab" type="button" role="tab">Sales Order Received</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-target="#POnotreceived" data-bs-toggle="tab" type="button" role="tab">Sales Order Not Received</button></li>
        </ul>

        <div class="tab-content border-start border-end border-bottom p-3 rounded-bottom">
            {{-- ---------- BIDDING TAB ---------- --}}
            <div class="tab-pane fade show active" id="bidding" role="tabpanel" tabindex="0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="input-group w-auto">
                        <span class="input-group-text">Search</span>
                        <input id="searchBidding" type="text" class="form-control" placeholder="Project, client, location…">
                    </div>
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
                            <th>Progress</th>
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
                            <th>Progress</th>
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
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- ---------- PO NOT RECEIVED TAB ---------- --}}
            <div class="tab-pane fade" id="POnotreceived" role="tabpanel" tabindex="0">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="input-group w-auto">
                        <span class="input-group-text">Search</span>
                        <input id="searchNoPOreceived" type="text" class="form-control" placeholder="Project, client, location…">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped w-100" id="tblNoPOreceived">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Location</th>
                            <th>Area</th>
                            <th>Quotation No</th>
                            <th>ATAI Products</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                    </table>
                </div>
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
                </div>

                {{-- Upload Submittal (Bidding) --}}
                <div class="mt-3">
                    <label class="form-label mb-1">Upload Submittal (PDF)</label>
                    <input id="biddingSubmittalFile" type="file" accept="application/pdf" class="form-control">
                    <button class="btn btn-sm btn-outline-primary mt-2" id="uploadBiddingSubmittalBtn" type="button">
                        Upload Submittal
                    </button>

                    <div id="biddingSubmittalPreview" class="submittal-preview d-none">
                        <iframe id="biddingSubmittalFrame"></iframe>
                        <a id="biddingSubmittalLink" target="_blank" rel="noopener"></a>
                    </div>
                </div>



{{--                <div id="biddingSubmittalPreview" class="submittal-preview d-none">--}}
{{--                    <iframe id="biddingSubmittalFrame"></iframe>--}}
{{--                    <a id="biddingSubmittalLink" target="_blank" rel="noopener"></a>--}}
{{--                </div>--}}
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
                </div>

                {{-- Upload Submittal (In-Hand) --}}
                <div class="mt-3">
                    <label class="form-label mb-1">Upload Submittal (PDF)</label>
                    <input id="inhandSubmittalFile" type="file" accept="application/pdf" class="form-control">
                    <button class="btn btn-sm btn-outline-primary mt-2" id="uploadInhandSubmittalBtn" type="button">
                        Upload Submittal
                    </button>

                    <div id="inhandSubmittalPreview" class="submittal-preview d-none">
                        <div class="small text-muted mb-1">Uploaded submittal:</div>
                        <div class="ratio ratio-16x9 border rounded overflow-hidden">
                            <iframe id="inhandSubmittalFrame" src="" title="Submittal" loading="lazy"></iframe>
                        </div>
                        <a id="inhandSubmittalLink" class="d-inline-block mt-2" href="#" target="_blank" rel="noopener">Open full PDF</a>
                    </div>
                </div>
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
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

    let CURRENT_PROJECT_ID   = null;
    let CURRENT_QUOTATION_NO = '';
    $.fn.dataTable.ext.errMode = 'console';

    const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
        style: 'currency', currency: 'SAR', maximumFractionDigits: 0
    }).format(Number(n || 0));

    // global state
    let PROJ_YEAR     = '2025';
    let PROJ_REGION   = '';
    let ATAI_ME       = null;
    let CAN_VIEW_ALL  = false;
    let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

    /* ===== Totals state ===== */
    let TAB_SUMS   = { bidding: 0, inhand: 0, lost: 0, poreceived: 0, ponotreceived: 0 };
    let TAB_COUNTS = { bidding: 0, inhand: 0, lost: 0, poreceived: 0, ponotreceived: 0 };
    let TAB_LOADED = { bidding: false, inhand: false, lost: false, poreceived: false, ponotreceived: false };
    let SHOW_CURRENT_TAB_ONLY = false;
    let FIRST_HEADER_PRIMED   = false;

    function resetTabTotals() {
        TAB_SUMS   = { bidding: 0, inhand: 0, lost: 0, poreceived: 0, ponotreceived: 0 };
        TAB_COUNTS = { bidding: 0, inhand: 0, lost: 0, poreceived: 0, ponotreceived: 0 };
        TAB_LOADED = { bidding: false, inhand: false, lost: false, poreceived: false, ponotreceived: false };
        SHOW_CURRENT_TAB_ONLY = false;
        FIRST_HEADER_PRIMED   = false;
        updateHeaderBadges();
    }
    function updateHeaderBadges(currentTab = null) {
        if (!(SHOW_CURRENT_TAB_ONLY && currentTab)) return;
        const totalCnt = TAB_COUNTS[currentTab] || 0;
        const totalSum = TAB_SUMS[currentTab] || 0;
        const pEl = document.getElementById('kpiBadgeProjects');
        const vEl = document.getElementById('kpiBadgeValue');
        if (pEl) pEl.textContent = String(totalCnt);
        if (vEl) vEl.textContent = fmtSAR(totalSum);
    }
    function showToast(msg) {
        const toastEl = document.getElementById('successToast');
        if (!toastEl) return;
        document.getElementById('toastMsg').textContent = msg || 'Updated.';
        new bootstrap.Toast(toastEl).show();
    }

    /* =============================================================================
     *  DATA TABLES (4+1 tabs)
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
        {data: 'progress_pct', name: 'progress_pct', className: 'text-nowrap', orderable: true, searchable: false, render: renderProgressCell},
        {data: 'actions', orderable: false, searchable: false, className: 'text-end'}
    ];
    const projectColumnsLost = [
        {data: 'id', name: 'id', width: '64px'},
        {data: 'name', name: 'name'},
        {data: 'client', name: 'client'},
        {data: 'location', name: 'location'},
        {data: 'area_badge', name: 'area', orderable: true, searchable: false},
        {data: 'quotation_no', name: 'quotation_no'},
        {data: 'quotation_date', name: 'quotation_date'},
        {data: 'atai_products', name: 'atai_products'},
        {data: 'quotation_value_fmt', name: 'quotation_value', orderable: true, searchable: false, className: 'text-end'},
        {data: 'status_badge', name: 'status', orderable: true, searchable: false}
    ];
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
    ];
    const projectColumnsNoPO = [
        {data: 'id', name: 'id', width: '64px'},
        {data: 'name', name: 'name'},
        {data: 'client', name: 'client'},
        {data: 'location', name: 'location'},
        {data: 'area_badge', name: 'area', orderable: true, searchable: false},
        {data: 'quotation_no', name: 'quotation_no'},
        {data: 'atai_products', name: 'atai_products'},
        {data: 'status_badge', name: 'status', orderable: true, searchable: false},
    ];

    function renderProgressCell(data, type) {
        const raw = (data == null ? null : parseInt(data, 10));
        const pct = Math.max(0, Math.min(100, isNaN(raw) ? 0 : raw));
        if (type === 'sort' || type === 'type' || type === 'filter') return pct;
        if (data == null) return '<span class="text-muted">—</span>';
        return `
      <div class="d-flex align-items-center gap-2" title="${pct}%">
        <div class="progress progress-thin flex-grow-1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${pct}">
          <div class="progress-bar bg-success" style="width:${pct}%"></div>
        </div>
        <small class="text-muted fw-semibold">${pct}%</small>
      </div>`;
    }

    function initProjectsTable(selector, status) {
        const $table = $(selector);
        const statusMap = {
            bidding: 'Bidding',
            inhand: 'In-Hand',
            lost: 'Lost',
            poreceived: 'PO-Received',
            // ponotreceived: handled specially below
        };
        let cols;
        if (status === 'poreceived') cols = projectColumnsPO;
        else if (status === 'ponotreceived') cols = projectColumnsNoPO;
        else if (status === 'lost') cols = projectColumnsLost;
        else cols = projectColumns;

        return $table.DataTable({
            processing: true,
            serverSide: true,
            order: (status === 'bidding' || status === 'inhand') ? [[9, 'desc']] : [[0, 'desc']],
            ajax: {
                url: DT_URL,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: (d) => {
                    // ---- status + special flags for "No PO" tab ----
                    if (status === 'ponotreceived') {
                        d.status_norm = 'In-Hand';
                        d.include_no_po = 1;
                        d.only_without_po = 1;
                    } else {
                        d.status_norm = statusMap[status] || '';
                    }

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

                    // Optional: pass raw salesman text only if GM/Admin wants to narrow forecast-side counts
                    const sTxt = document.getElementById('salesmanInput')?.value?.trim();
                    if (sTxt && CAN_VIEW_ALL) d.salesman = sTxt;
                },
                dataSrc: (json) => {
                    // Prime header once (use raw numeric if available)
                    if (!FIRST_HEADER_PRIMED) {
                        const pEl = document.getElementById('kpiBadgeProjects');
                        const vEl = document.getElementById('kpiBadgeValue');
                        const totalCnt = Number(json?.recordsTotal || 0);
                        const headerSum = Number(json?.header_sum_value ?? json?.sum_quotation_value ?? 0);
                        if (pEl) pEl.textContent = String(totalCnt);
                        if (vEl) vEl.textContent = fmtSAR(headerSum);
                        FIRST_HEADER_PRIMED = true;
                    }

                    // Store this tab totals
                    const tabSum = Number(json?.sum_quotation_value || 0);
                    const tabCnt = Number(json?.recordsFiltered || 0);
                    TAB_SUMS[status]   = tabSum;
                    TAB_COUNTS[status] = tabCnt;
                    TAB_LOADED[status] = true;

                    // If user is on this tab, override the header with this tab totals
                    if (SHOW_CURRENT_TAB_ONLY) updateHeaderBadges(status);

                    return json?.data || [];
                },
                error: (xhr, textStatus, err) => {
                    console.error('[DT ajax error]', status, xhr.status, xhr.responseText || textStatus || err);
                    showToast('Failed to load ' + (status || 'table') + ' data (' + xhr.status + '). Check console.');
                }
            },
            columns: cols,
            drawCallback: function() {
                // Make "Project" column clickable to open modal (col index 1)
                const api = this.api();
                $(api.table().body()).off('click.openRow').on('click.openRow', 'td:nth-child(2)', function () {
                    const rowData = api.row(this.closest('tr')).data();
                    if (rowData && rowData.id) openProjectModalFromData(rowData);
                });
            }
        });
    }

    /* Tabs → update header to this tab’s totals */
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
            const tabId = (e.target.getAttribute('data-bs-target') || '').toLowerCase();
            let key = null;
            if (tabId.includes('bidding')) key = 'bidding';
            else if (tabId.includes('inhand')) key = 'inhand';
            else if (tabId.includes('lost')) key = 'lost';
            else if (tabId.includes('poreceived')) key = 'poreceived';
            else if (tabId.includes('ponotreceived')) key = 'ponotreceived';

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
        {key: "submittal_approved",          label: "Consultant approved — Submittal approved",        weight: 25},
        {key: "sample_approved",             label: "Consultant approved — Sample approved",            weight: 25},
        {key: "commercial_terms_agreed",     label: "Commercial terms / Payment terms agreed",          weight: 50},
        {key: "no_approval_or_terms",        label: "No consultant approval or commercial terms agreed",weight: 0},
        {key: "discount_offered_as_standard",label:"Discount offered as per standard",                  weight: 0},
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
            if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }
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
        if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }
    }
    document.getElementById('biddingModal')?.addEventListener('shown.bs.modal', () => {
        const frame = document.getElementById('biddingSubmittalFrame');
        const url   = SUBMITTAL_STATE.bidding.url;
        if (frame && url && !frame.src) {
            frame.style.pointerEvents = 'none';
            frame.onload = () => { frame.style.pointerEvents = 'auto'; };
            setTimeout(() => { frame.src = url + `?v=${Date.now()}#view=FitH`; }, 50);
        }
    });
    document.getElementById('inhandModal')?.addEventListener('shown.bs.modal', () => {
        const frame = document.getElementById('inhandSubmittalFrame');
        const url   = SUBMITTAL_STATE.inhand.url;
        if (frame && url && !frame.src) {
            frame.style.pointerEvents = 'none';
            frame.onload = () => { frame.style.pointerEvents = 'auto'; };
            setTimeout(() => { frame.src = url + `?v=${Date.now()}#view=FitH`; }, 50);
        }
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
            loadSubmittalPreview(CURRENT_PROJECT_ID, 'bidding');
            document.getElementById('biddingModalLabel').textContent = p.name || 'Project';
            fillDetails('biddingDetails', p);
            renderChecklist('biddingChecklist', checklistBidding, 'bid', p.checklistBidding || {});
            if (typeof p.biddingProgress === 'number') {
                const pct = p.biddingProgress;
                document.getElementById('biddingProgressPct').textContent = pct + '%';
                const bar = document.getElementById('biddingProgressBar');
                if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }
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
            loadSubmittalPreview(CURRENT_PROJECT_ID, 'inhand');
            document.getElementById('inhandModalLabel').textContent = p.name || 'Project';
            fillDetails('inhandDetails', p);
            renderChecklist('inhandChecklist', checklistInhand, 'ih', p.checklistInhand || {});
            if (typeof p.inhandProgress === 'number') {
                const pct = p.inhandProgress;
                document.getElementById('inhandProgressPct').textContent = pct + '%';
                const bar = document.getElementById('inhandProgressBar');
                if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }
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

    /* View button handler (if actions column exists) */
    $(document).on('click', '[data-action="view"]', async function (e) {
        e.preventDefault();
        const $tr = $(this).closest('tr');
        const tryGetRow = (dt) => (dt ? dt.row($tr).data() : null);
        let row =
            tryGetRow($('#tblBidding').DataTable()) ||
            tryGetRow($('#tblInhand').DataTable()) ||
            tryGetRow($('#tblLost').DataTable()) ||
            tryGetRow($('#tblPOreceived').DataTable()) ||
            tryGetRow($('#tblNoPOreceived').DataTable());
        if (!row || !row.id) return;

        let detail = null;
        try {
            const res = await fetch(`/projects/${row.id}`, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (res.ok) detail = await res.json();
        } catch (err) { console.warn('Detail fetch failed', err); }
        if (detail) row = hydrateRowWithDetail(row, detail);
        openProjectModalFromData(row);
    });

    /* Resize columns when switching tabs */
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            setTimeout(() => {
                dtBid?.columns.adjust();
                dtIn?.columns.adjust();
                dtLost?.columns.adjust();
                dtPO?.columns.adjust();
                dtNoPO?.columns.adjust();
            }, 50);
        });
    });

    /* Global search */
    let dtBid, dtIn, dtLost, dtPO, dtNoPO;
    document.getElementById('searchBidding')?.addEventListener('input', e => dtBid && dtBid.search(e.target.value).draw());
    document.getElementById('searchInhand')?.addEventListener('input', e => dtIn && dtIn.search(e.target.value).draw());
    document.getElementById('searchLost')?.addEventListener('input', e => dtLost && dtLost.search(e.target.value).draw());
    document.getElementById('searchPOreceived')?.addEventListener('input', e => dtPO && dtPO.search(e.target.value).draw());
    document.getElementById('searchNoPOreceived')?.addEventListener('input', e => dtNoPO && dtNoPO.search(e.target.value).draw());

    /* Family chips → refresh DT */
    $(document).on('click', '#familyChips [data-family]', function (e) {
        e.preventDefault();
        $('#familyChips [data-family]').removeClass('active');
        this.classList.add('active');
        currentFamily = this.getAttribute('data-family') || '';
        resetTabTotals();
        dtBid?.ajax.reload(null, false);
        dtIn?.ajax.reload(null, false);
        dtLost?.ajax.reload(null, false);
        dtPO?.ajax.reload(null, false);
        dtNoPO?.ajax.reload(null, false);
    });

    /* Apply filters */
    document.getElementById('projApply')?.addEventListener('click', () => {
        PROJ_YEAR   = document.getElementById('projYear')?.value || '';
        PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';
        resetTabTotals();
        dtBid?.ajax.reload(null, false);
        dtIn?.ajax.reload(null, false);
        dtLost?.ajax.reload(null, false);
        dtPO?.ajax.reload(null, false);
        dtNoPO?.ajax.reload(null, false);
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

        // preselect 2025 in the UI on first load
        const yearSel = document.getElementById('projYear');
        if (yearSel && !yearSel.value) {
            const opt = [...yearSel.options].find(o => o.value === '2025');
            if (opt) yearSel.value = '2025';
        }

        dtBid  = initProjectsTable('#tblBidding',      'bidding');
        dtIn   = initProjectsTable('#tblInhand',       'inhand');
        dtLost = initProjectsTable('#tblLost',         'lost');
        dtPO   = initProjectsTable('#tblPOreceived',   'poreceived');
        dtNoPO = initProjectsTable('#tblNoPOreceived', 'ponotreceived');
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

    /* =============================================================================
     *  Network helpers
     * ============================================================================= */
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
    async function postChecklist(pid, phase, payload) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const url  = phase === 'bidding'
            ? `/projects/${pid}/checklist/bidding`
            : `/projects/${pid}/checklist/inhand`;

        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error((await res.json().catch(()=>({}))).message || res.statusText);
        return res.json();
    }

    /* =============================================================================
     *  SAVE / STATUS HANDLERS
     * ============================================================================= */
    document.getElementById('saveBiddingBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');

        if (!SUBMITTAL_STATE.bidding.has) {
            const ok = await showConfirmDialog({
                title: 'No submittal uploaded',
                html: 'You have not uploaded the submittal PDF for <b>Bidding</b>. Do you want to save anyway?',
                confirmText: 'Save without submittal',
                tone: 'warning'
            });
            if (!ok) return;
        }

        const checked = (id) => !!document.getElementById(id)?.checked;
        try {
            const r = await postChecklist(CURRENT_PROJECT_ID, 'bidding', {
                mep_contractor_appointed: checked('bid_mep_contractor_appointed'),
                boq_quoted:               checked('bid_boq_quoted'),
                boq_submitted:            checked('bid_boq_submitted'),
                priced_at_discount:       checked('bid_priced_at_discount'),
            });
            const pct = Number(r.progress || 0);
            document.getElementById('biddingProgressPct').textContent = pct + '%';
            const bar = document.getElementById('biddingProgressBar');
            if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }

            const noteText = (document.getElementById('biddingComments')?.value || '').trim();
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, { action: 'add_note', note: noteText });
                document.getElementById('biddingComments').value = '';
            }

            showToast('Bidding checklist saved.');
            $('#tblBidding').DataTable().ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });

    document.getElementById('saveInhandBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');

        if (!SUBMITTAL_STATE.inhand.has) {
            const ok = await showConfirmDialog({
                title: 'No submittal uploaded',
                html: 'You have not uploaded the submittal PDF for <b>In-Hand</b>. Do you want to save anyway?',
                confirmText: 'Save without submittal',
                tone: 'warning'
            });
            if (!ok) return;
        }

        const checked = (id) => !!document.getElementById(id)?.checked;
        try {
            const r = await postChecklist(CURRENT_PROJECT_ID, 'inhand', {
                submittal_approved:           checked('ih_submittal_approved'),
                sample_approved:              checked('ih_sample_approved'),
                commercial_terms_agreed:      checked('ih_commercial_terms_agreed'),
                no_approval_or_terms:         checked('ih_no_approval_or_terms'),
                discount_offered_as_standard: checked('ih_discount_offered_as_standard'),
            });

            const pct = Number(r.progress || 0);
            document.getElementById('inhandProgressPct').textContent = pct + '%';
            const bar = document.getElementById('inhandProgressBar');
            if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', String(pct)); }

            const noteText = (document.getElementById('inhandComments')?.value || '').trim();
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, { action: 'add_note', note: noteText });
                document.getElementById('inhandComments').value = '';
            }

            showToast('In-Hand checklist saved.');
            $('#tblInhand').DataTable().ajax.reload(null, false);
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
            bootstrap.Modal.getInstance(document.getElementById('POreceivedModal'))?.hide();
            $('#tblPOreceived').DataTable().ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });

    /* Confirmation dialog helper */
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
            document.getElementById('confirmTitle').style.color = '#f59e0b';
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

        ['LostStatusBtn','POreceivedStatusBtn','BiddingLostBtn','BiddingPOBtn'].forEach(id => {
            const b = document.getElementById(id);
            if (b) b.setAttribute('disabled','disabled');
        });

        try {
            const res = await postProjectUpdate(CURRENT_PROJECT_ID, {
                action: 'update_status',
                to_status: toStatus // 'Lost' or 'PO-Received'
            });
            if (!res?.ok) throw new Error(res?.message || 'Update failed');

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

            $('#tblInhand').DataTable().ajax.reload(null, false);
            $('#tblLost').DataTable().ajax.reload(null, false);
            $('#tblPOreceived').DataTable().ajax.reload(null, false);
            $('#tblBidding').DataTable().ajax.reload(null, false);
            $('#tblNoPOreceived').DataTable().ajax.reload(null, false);
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        } finally {
            ['LostStatusBtn','POreceivedStatusBtn','BiddingLostBtn','BiddingPOBtn'].forEach(id => {
                const b = document.getElementById(id);
                if (b) b.removeAttribute('disabled');
            });
        }
    }

    /* Wire status-change buttons */
    document.getElementById('LostStatusBtn')?.addEventListener('click', () => changeProjectStatus('Lost'));
    document.getElementById('POreceivedStatusBtn')?.addEventListener('click', () => changeProjectStatus('PO-Received'));
    document.getElementById('BiddingLostBtn')?.addEventListener('click', () => changeProjectStatus('Lost'));
    document.getElementById('BiddingPOBtn')?.addEventListener('click', () => changeProjectStatus('PO-Received'));

    /* =============================================================================
     * Submittals (shared for Bidding + In-Hand)
     * ============================================================================= */
    const SUBMITTAL_STATE = {
        bidding: { has: false, url: "", name: "" },
        inhand:  { has: false, url: "", name: "" }
    };
    function _ids(phase) {
        return (phase === 'inhand')
            ? { file: 'inhandSubmittalFile', wrap: 'inhandSubmittalPreview', frame: 'inhandSubmittalFrame', link: 'inhandSubmittalLink', btn: 'uploadInhandSubmittalBtn' }
            : { file: 'biddingSubmittalFile', wrap: 'biddingSubmittalPreview', frame: 'biddingSubmittalFrame', link: 'biddingSubmittalLink', btn: 'uploadBiddingSubmittalBtn' };
    }



    // Make any returned URL absolute against current origin
    // Make any returned URL absolute against current origin
    function sameOrigin(u) {
        if (!u) return '';
        try {
            const url = new URL(u, window.location.origin);
            // Force current origin to avoid CORS (127.0.0.1:8000 vs localhost:80 issues)
            return window.location.origin + url.pathname + url.search + url.hash;
        } catch {
            return u;
        }
    }

    async function loadSubmittalPreview(projectId, phase = 'bidding') {
        const ids = _ids(phase);
        SUBMITTAL_STATE[phase] = { has: false, url: "", name: "" };

        const wrap  = document.getElementById(ids.wrap);
        const frame = document.getElementById(ids.frame);
        const link  = document.getElementById(ids.link);

        if (wrap)  wrap.classList.add('d-none');
        if (frame) { frame.removeAttribute('src'); frame.style.pointerEvents = 'none'; }
        if (link)  { link.removeAttribute('href'); link.textContent = 'Open PDF'; }

        try {
            const res = await fetch(`/projects/${projectId}/submittal/${phase}`, { credentials: 'same-origin' });
            if (!res.ok) return;
            const j = await res.json();
            if (!j?.exists) return;

            // Prefer /storage/... if available, otherwise use the stream route
            const urlRel   = j.url_rel || '';
            const urlStream= j.url_stream || '';
            const base     = urlRel ? sameOrigin(urlRel) : sameOrigin(urlStream);
            const final    = base + (base.includes('?') ? '&' : '?') + 'v=' + Date.now();

            // quick HEAD check
            let ok = false;
            try { ok = (await fetch(final, { method: 'HEAD', credentials: 'same-origin' })).ok; } catch {}
            const chosen = ok ? final : sameOrigin(urlStream) + (urlStream ? `?v=${Date.now()}` : '');

            SUBMITTAL_STATE[phase] = { has: !!chosen, url: chosen, name: j.name || 'Open PDF' };

            if (link && chosen) { link.href = chosen; link.textContent = j.name || 'Open PDF'; }
            if (frame && chosen) {
                frame.onload = () => { frame.style.pointerEvents = 'auto'; };
                setTimeout(() => { frame.src = chosen + '#view=FitH'; }, 50);
            }
            if (wrap && chosen) wrap.classList.remove('d-none');
        } catch {/* ignore */}
    }

    function attachSubmittalUpload(phase = 'bidding') {
        const ids = _ids(phase);
        const btn = document.getElementById(ids.btn);
        if (!btn) return;

        btn.addEventListener('click', async () => {
            if (!CURRENT_PROJECT_ID) return alert('No project selected.');
            const inp = document.getElementById(ids.file);
            const f = inp?.files?.[0];
            if (!f) return alert('Please choose a PDF file first.');
            if (f.type && f.type !== 'application/pdf') return alert('Only PDF files are allowed.');
            if (typeof f.size === 'number' && f.size > 25 * 1024 * 1024) return alert('PDF is too large (limit 25 MB).');

            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading…';

            const fd = new FormData();
            fd.append('file', f);
            fd.append('phase', phase);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const res = await fetch(`/projects/${CURRENT_PROJECT_ID}/submittal`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    body: fd
                });

                const ctype = res.headers.get('content-type') || '';
                if (!res.ok) {
                    const msg = ctype.includes('application/json') ? (await res.json().catch(() => ({}))).message : `Upload failed (${res.status})`;
                    throw new Error(msg || 'Upload failed');
                }

                const j = await res.json();
                const urlRel = j.url_rel || '';
                const urlStream = j.url_stream || '';
                const base = urlRel ? sameOrigin(urlRel) : sameOrigin(urlStream);
                const final = base + (base.includes('?') ? '&' : '?') + 'v=' + Date.now();

                SUBMITTAL_STATE[phase] = {has: true, url: final, name: j.name || 'Open PDF'};

                const wrap = document.getElementById(ids.wrap);
                const frame = document.getElementById(ids.frame);
                const link = document.getElementById(ids.link);

                if (link) {
                    link.href = final;
                    link.textContent = j.name || 'Open PDF';
                }
                if (wrap) wrap.classList.remove('d-none');
                if (frame) {
                    frame.removeAttribute('src');
                    frame.setAttribute('loading', 'lazy');
                    frame.style.pointerEvents = 'none';
                    frame.onload = () => {
                        frame.style.pointerEvents = 'auto';
                    };
                    setTimeout(() => {
                        frame.src = final + '#view=FitH';
                    }, 50);
                }

                showToast('Submittal uploaded.');
                if (inp) inp.value = '';
            } catch (err) {
                alert('Upload failed: ' + (err?.message || err));
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    }
    attachSubmittalUpload('bidding');
    attachSubmittalUpload('inhand');

    /* LOST modal Save (note + optional mark Lost) */
    document.getElementById('saveLostBtn')?.addEventListener('click', async () => {
        if (!CURRENT_PROJECT_ID) return alert('No project selected.');
        const noteText = (document.getElementById('lostComments')?.value || '').trim();

        try {
            if (noteText) {
                await postProjectUpdate(CURRENT_PROJECT_ID, { action: 'add_note', note: noteText });
                document.getElementById('lostComments').value = '';
            }
            const confirmed = await showConfirmDialog({
                title: 'Mark as LOST?',
                html: `Mark quotation <b>${CURRENT_QUOTATION_NO}</b> as <b>LOST</b>?`,
                confirmText: 'Yes, mark LOST',
                tone: 'danger'
            });
            if (confirmed) {
                await postProjectUpdate(CURRENT_PROJECT_ID, { action: 'update_status', to_status: 'Lost' });
            }

            bootstrap.Modal.getInstance(document.getElementById('lostModal'))?.hide();
            $('#tblLost').DataTable().ajax.reload(null, false);
            $('#tblBidding').DataTable().ajax.reload(null, false);
            $('#tblInhand').DataTable().ajax.reload(null, false);
            showToast('Lost details saved.');
        } catch (err) {
            alert('Save failed: ' + (err?.message || err));
        }
    });
</script>

</body>
</html>
