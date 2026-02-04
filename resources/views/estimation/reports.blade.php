@extends('layouts.app')

@section('title', 'ATAI Projects — Live')
@push('head')

    {{-- DataTables (Bootstrap 5 build) --}}
{{--    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">--}}
{{--    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">--}}
    {{--<link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">--}}

    <style>
        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm, table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }

        #tblBidding td:last-child, #tblInhand td:last-child, #tblLost td:last-child, #tblPOreceived td:last-child {
            text-align: end;
        }

        /* KPI header cards */
        .kpi-card .hc {
            height: 260px;
        }

        .kpi-card .card-body {
            padding: .75rem 1rem;
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .kpi-label {
            font-size: .8rem;
            text-transform: uppercase;
            color: #6b7280;
        }

        .toast, .toast-container {
            z-index: 1067;
        }

        .submittal-preview iframe {
            pointer-events: auto;
        }

        .submittal-preview iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-dropdown {
            background-color: #1f1f1f !important;
            border-color: #444 !important;
            color: #fff !important;
        }

        .select2-results__option {
            color: #fff !important;
        }

        .select2-search__field {
            background-color: #111 !important;
            color: #fff !important;
            border: 1px solid #555 !important;
        }

        .select2-container .select2-selection--single {
            height: 38px !important;
            padding: 5px 10px;
            background-color: #111 !important;
            border: 1px solid #444 !important;
            color: #fff !important;
        }

        /* Confirm dialog look */
        #confirmActionModal .modal-content {
            background-color: #f8fafc !important;
            color: #111 !important;
            opacity: 1 !important;
            filter: none !important;
        }

        .modal-backdrop,
        .modal-backdrop.show {
            background-color: rgba(0, 0, 0, 0.55) !important;
            z-index: 1080 !important;
            pointer-events: none;
        }

        /* === NEW: Horizontal scroll for estimator tables === */
        .estimator-scroll-x {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: .5rem;
        }

        .estimator-scroll-x table.dataTable {
            min-width: 1400px; /* enough width so all columns show fully */
        }

        /* Prevent column wrapping so header/body stay aligned */
        table.dataTable thead th,
        table.dataTable tbody td {
            white-space: nowrap;
        }

        /* === NEW: Make Add Inquiry modal body scroll inside viewport === */
        #modalAddInquiry .modal-dialog-scrollable .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
    </style>
@endpush

@section('content')

    <main class="container-fluid py-4 estimator-inquiries-page">

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

                    <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:auto"
                           placeholder="From">
                    <input type="date" id="dateTo" class="form-control form-control-sm" style="width:auto"
                           placeholder="To">

                    <span id="salesmanWrap" >
                        <select id="salesmanInput" class="form-select form-select-sm" style="width:auto">
                            <option value="">All Salesmen</option>
                            @foreach($salesmen as $sm)
                                <option value="{{ $sm->name }}">{{ $sm->name }}</option>
                            @endforeach
                        </select>
                    </span>

                    <button class="btn btn-sm btn-primary" id="projApply">Update</button>
                </div>

                <button class="btn btn-sm btn-info" id="btnExportMonthly">
                    <i class="bi bi-file-earmark-excel"></i>
                    Export Monthly
                </button>
                <button class="btn btn-sm btn-success" id="btnExportExcel">
                    <i class="bi bi-file-earmark-excel"></i>
                    Export weekly Excel
                </button>
                <button class="btn btn-success btn-sm" id="btnAddInquiry">
                    + Inquiry
                </button>
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

            {{-- ===== TABS (All / Bidding / In-Hand) ===== --}}
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active"
                            data-bs-target="#all"
                            data-bs-toggle="tab"
                            type="button"
                            role="tab">
                        All
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link"
                            data-bs-target="#bidding"
                            data-bs-toggle="tab"
                            type="button"
                            role="tab">
                        Bidding
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link"
                            data-bs-target="#inhand"
                            data-bs-toggle="tab"
                            type="button"
                            role="tab">
                        In-Hand
                    </button>
                </li>
            </ul>

            <div class="tab-content border-start border-end border-bottom p-3 rounded-bottom">
                {{-- ---------- All TAB ---------- --}}
                <div class="tab-pane fade show active" id="all" role="tabpanel" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <div class="input-group w-auto">
                            <span class="input-group-text">Search</span>
                            <input id="searchAll" type="text" class="form-control"
                                   placeholder="Project, client, location…">
                        </div>
                    </div>

                    {{-- NEW: add estimator-scroll-x --}}
                    <div class="table-responsive estimator-scroll-x">
                        <table class="table table-striped w-100" id="tblAll">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Sales Man</th>
                                <th>Location</th>
                                <th>Area</th>
                                <th>Quotation No</th>
                                <th>Rev</th>
                                <th>ATAI Products</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Quotation Date</th>
                                <th>Received Date</th>
                                <th>Inserted At</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>

                {{-- ---------- BIDDING TAB ---------- --}}
                <div class="tab-pane fade" id="bidding" role="tabpanel" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <div class="input-group w-auto">
                            <span class="input-group-text">Search</span>
                            <input id="searchBidding" type="text" class="form-control"
                                   placeholder="Project, client, location…">
                        </div>
                    </div>

                    {{-- NEW: add estimator-scroll-x --}}
                    <div class="table-responsive estimator-scroll-x">
                        <table class="table table-striped w-100" id="tblBidding">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Sales Man</th>
                                <th>Location</th>
                                <th>Area</th>
                                <th>Quotation No</th>
                                <th>Rev</th>
                                <th>ATAI Products</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Quotation Date</th>
                                <th>Recieved Date</th>
                                <th>Inserted At</th>
                                <th>Action</th>
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
                            <input id="searchInhand" type="text" class="form-control"
                                   placeholder="Project, client, location…">
                        </div>
                    </div>

                    {{-- NEW: add estimator-scroll-x --}}
                    <div class="table-responsive estimator-scroll-x">
                        <table class="table table-striped w-100" id="tblInhand">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Sales Man</th>
                                <th>Location</th>
                                <th>Area</th>
                                <th>Quotation No</th>
                                <th>Rev</th>
                                <th>ATAI Products</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Quotation Date</th>
                                <th>Recieved Date</th>
                                <th>Inserted At</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        {{-- ---------- Add / Edit Inquiry Modal ---------- --}}
        <div class="modal fade modal-atai" id="modalAddInquiry" data-bs-backdrop="false" tabindex="-1"
             aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Inquiry</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="formAddInquiry">
                        @csrf
                        <input type="hidden" name="inquiry_id" id="inquiry_id">
                        <div class="modal-body">
                            <div class="col-md-6">
                                <label class="form-label">Estimator</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       value="{{ auth()->user()->name ?? '' }}"
                                       readonly>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Project</label>
                                    <select id="projectSelect" name="project"
                                            class="form-select form-select-sm"></select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Client</label>
                                    <select id="clientSelect" name="client" class="form-select form-select-sm"></select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Sales Man</label>
                                    <select id="salesmanSelect" name="salesman" class="form-select form-select-sm" required>
                                        <option value="">Select...</option>
                                        @foreach($salesmen as $sm)
                                            <option value="{{ $sm->name }}">{{ $sm->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Area</label>
                                    <select id="areaSelect" name="area" class="form-select form-select-sm" required>
                                        <option value="">Select...</option>
                                        <option value="Eastern">Eastern</option>
                                        <option value="Central">Central</option>
                                        <option value="Western">Western</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Technical Base</label>
                                    <select name="technical_base" class="form-select form-select-sm">
                                        <option value="">Select...</option>
                                        <option value="BOQ">BOQ</option>
                                        <option value="Specs">Specs</option>
                                        <option value="Budget">Budgetary</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Technical Submittal</label>
                                    <select id="technical_submittal" name="technical_submittal"
                                            class="form-select form-select-sm">
                                        <option value="">Select...</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <div class="input-group input-group-sm">
                                        <select id="locationSelect" class="form-select">
                                            <option value="">Select area first</option>
                                        </select>

                                        <input type="text"
                                               id="locationInput"
                                               name="location"
                                               class="form-control"
                                               placeholder="Type or paste location"
                                               disabled>
                                    </div>
                                    <div class="form-text">
                                        Select from list or type/paste a location after choosing Area.
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Quotation No</label>
                                    <input type="text" name="quotation_no" class="form-control form-control-sm"
                                           required>
                                </div>

                                {{-- NEW: Revision selector --}}
                                <div class="col-md-3">
                                    <label class="form-label">Revision</label>
                                    <select name="revision_no" class="form-select form-select-sm">
                                        <option value="0">Original</option>
                                        <option value="1">Rev 1</option>
                                        <option value="2">Rev 2</option>
                                        <option value="3">Rev 3</option>
                                        <option value="4">Rev 4</option>
                                        <option value="5">Rev 5</option>
                                        <option value="6">Rev 6</option>
                                        <option value="7">Rev 7</option>
                                        <option value="8">Rev 8</option>
                                        <option value="9">Rev 9</option>
                                        <option value="10">Rev 10</option>

                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Quotation Date</label>
                                    <input type="date" name="quotation_date" class="form-control form-control-sm"
                                           required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Date Received</label>
                                    <input type="date" name="date_received" class="form-control form-control-sm"
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">ATAI Products</label>
                                    <select id="ataiProductsSelect" name="atai_products" class="form-select form-select-sm" required>
                                        <option value="">Select Product</option>
                                        <option value="Ductwork">Ductwork</option>
                                        <option value="Ductwork and Accessories">Ductwork and Accessories</option>
                                        <option value="Dampers">Dampers</option>
                                        <option value="Louvers">Louvers</option>
                                        <option value="Sound Attenuators">Sound Attenuators</option>
                                        <option value="Accessories">Accessories</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Price (SAR)</label>
                                    <input type="number" step="0.01" name="price" class="form-control form-control-sm"
                                           required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select form-select-sm" required>
                                        <option value="BIDDING">Bidding</option>
                                        <option value="IN-HAND">In-Hand</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact Email</label>
                                <input type="email" name="contact_email" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Company Address</label>
                                <input type="text" name="company_address" class="form-control form-control-sm">
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-success btn-sm">
                                Save Inquiry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    {{-- Toast (generic success) --}}
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
        <div id="successToast" class="toast align-items-center text-bg-success border-0" role="status"
             aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMsg">Updated.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
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
@endsection

@push('scripts')
    <script>
        /* =============================================================================
         *  CONFIG & HELPERS
         * ============================================================================= */
        const API = @json(url('/api'));
        const DT_URL = @json(route('estimator.inquiries.datatable'));
        const $ = window.jQuery;

        let CURRENT_PROJECT_ID = null;
        let CURRENT_QUOTATION_NO = '';
        $.fn.dataTable.ext.errMode = 'console';

        const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
            style: 'currency', currency: 'SAR', maximumFractionDigits: 0
        }).format(Number(n || 0));

        function cleanupBackdrops() {
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
        }

        // global state
        let PROJ_YEAR = '2026';
        let PROJ_REGION = '';
        let ATAI_ME = null;
        let CAN_VIEW_ALL = false;
        let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

        /* ===== Totals state (only bidding + inhand for estimator) ===== */
        let TAB_SUMS = {all: 0, bidding: 0, inhand: 0};
        let TAB_COUNTS = {all: 0, bidding: 0, inhand: 0};
        let TAB_LOADED = {all: false, bidding: false, inhand: false};

        // Default tab = All
        let CURRENT_TAB_KEY = 'all';
        let CURRENT_INQUIRY_ID = null;

        const CREATE_URL = '{{ route('estimator.inquiries.store') }}';
        const SHOW_URL = '{{ route('estimator.inquiries.show', ['inquiry' => '__ID__']) }}';
        const UPDATE_URL = '{{ route('estimator.inquiries.update', ['inquiry' => '__ID__']) }}';
        const DELETE_URL = '{{ route('estimator.inquiries.destroy', ['inquiry' => '__ID__']) }}';

        function resetTabTotals() {
            TAB_SUMS = {all: 0, bidding: 0, inhand: 0};
            TAB_COUNTS = {all: 0, bidding: 0, inhand: 0};
            TAB_LOADED = {all: false, bidding: false, inhand: false};
            updateHeaderBadges();
        }

        function updateHeaderBadges(tabKey = CURRENT_TAB_KEY) {
            if (!tabKey) return;
            const totalCnt = TAB_COUNTS[tabKey] || 0;
            const totalSum = TAB_SUMS[tabKey] || 0;

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
         *  AREA → LOCATIONS (for estimator modal)
         * ============================================================================= */
        const AREA_LOCATIONS = {
            Eastern: ['Dammam', 'Al Khobar', 'Dhahran', 'Jubail', 'Jubail Industrial City', 'Qatif', 'Saihat', 'Tarout', 'Safwa', 'Ras Tanura', 'Abqaiq (Buqayq)',
                'Hofuf', 'Al Mubarraz', 'Al Ahsa (Al Hasa)', 'Uqair', 'Salwa', 'Haradh', 'Ras Al Khair', 'Tanajib', 'Manifa', 'Khursaniyah', 'Jazan Offshore (not city)',
                'Al Qatif Industrial', 'Um Al Sahek', 'Hafar Al-Batin',
                'Qaisumah', 'Arar Border (Eastern access)', 'Al Khafji', 'Anak', 'Jish', 'Awamiya', 'Al Nairyah', 'Al Udhailiyah', 'Ain Dar', 'Abu Hadriyah',
                'Oyayna (E. fringe)', 'Thaj', 'Al Batha', 'Al Jafr', 'Oman Border Check (Salwa)', 'Remah (Ahsa)', 'Al Uqair Beach',
                'Taraf', 'Ghawar Camp', 'Al Qurayyah', 'Abu Ali Island', 'Ras Al-Juraib', 'Ras Abu Gamys', 'Safaniya', 'Ras Al Qalyah', 'Ras Al Zour'
            ],

            Central: ['Riyadh', 'Al Kharj', 'Diriyah', 'Ad Diriyah New City', 'Al Majma\'ah', 'Al Zulfi', 'Shaqra', 'Thadiq', 'Huraymila', 'Rimah', 'Al Ghat', 'Al Quwayiyah',
                'Wadi ad-Dawasir', 'As Sulayyil', 'Afif', 'Dawadmi', 'Al Aflaj (Layla)', 'Hotat Bani Tamim', 'Al Muzahmiyya', 'Al Uyaynah', 'Rumah', 'Tamir', 'Huraymila Industrial',
                'Buraydah', 'Unaizah', 'Ar Rass', 'Al Bukayriyah', 'Al Mithnab', 'Al Badayea', 'Uyun AlJiwa', 'Asyah', 'Al Shinan', 'Hail', 'Baqaa',
                'Ghazalah', 'Shaqra Industrial', 'Jalajil', 'Tharmada', 'Al Tumair', 'Tabarjal (Al Jouf)', 'Sakaka', 'Dumat Al Jandal', 'Rafha (N. Border)',
                'Arar (N. Border)', 'Turaif', 'Qurayyat', 'Al Uwayqilah', 'Hafar Al-Batin (often Central ops)',
                'Al Khubara (Qassim)', 'Al Riyadh Industrial City 1/2/3'
            ],
            Western: ['Jeddah', 'Makkah', 'Madinah', 'Yanbu', 'Rabigh', 'King Abdullah Economic City (KAEC)', 'Taif', 'Thuwal', 'Laith (Al Lith)', 'Umluj', 'Al Wajh', 'Duba',
                'Haql', 'NEOM (Sharma)', 'Tabuk', 'Tayma', 'Al Ula', 'Khaybar', 'Badr', 'Mahd adh Dhahab', 'Wajh Industrial', 'Baha', 'Baljurashi', 'Al Makhwah', 'Qilwah', 'Abha', 'Khamis Mushait', 'Muhayil',
                'Bishah', 'Tathlith', 'Tanomah', 'Sarat Abidah', 'Dhahran Al Janub', 'Jazan', 'Sabya', 'Abu Arish', 'Samtah', 'Baish',
                'Farasan', 'Ad Darb', 'Ahad Al Masarihah', 'Al Aridhah', 'Najran', 'Sharurah', 'Hubuna', 'Badr Al Janoub', 'Yadamah', 'Khobash', 'Thar', 'Al Kharkhir',
                'Al Qunfudah Industrial', 'Yanbu Industrial City', 'Mastoura', 'Ranyah', 'Turubah', 'Al Kamel', 'Khulays'
            ]
        };

        function populateLocations(area) {
            const $loc = $('#locationSelect');
            $loc.empty();

            if (!area || !AREA_LOCATIONS[area]) {
                $loc.append('<option value="">Select area first</option>');
                $('#locationInput').val('').prop('disabled', true);
                return;
            }

            $loc.append('<option value="">Select...</option>');
            AREA_LOCATIONS[area].forEach(function (city) {
                $loc.append($('<option>', {value: city, text: city}));
            });

            $('#locationInput').prop('disabled', false);
        }

        // when Area changes → repopulate locations + enable/disable input
        $('#areaSelect').on('change', function () {
            const area = $(this).val();
            populateLocations(area);
        });

        // when user picks a location from dropdown, copy into text input
        $('#locationSelect').on('change', function () {
            const val = $(this).val() || '';
            $('#locationInput').val(val);
        });

        /* =============================================================================
         *  ADD / EDIT INQUIRY MODAL (Estimator)
         * ============================================================================= */
        $(function () {


            function applyProductsBySalesman(keepValue = true) {
                const sm = String($('#salesmanSelect').val() || '').trim().toUpperCase();
                const isWesternSpecial = (sm === 'ABDO' || sm === 'AHMED');

                const $p = $('#ataiProductsSelect');
                const current = String($p.val() || '').trim().toUpperCase();

                // Base options (everyone)
                const base = [
                    { v: '', t: 'Select Product' },
                    { v: 'DUCTWORK', t: 'DUCTWORK' },
                    { v: 'DAMPERS', t: 'DAMPERS' },
                    { v: 'LOUVERS', t: 'LOUVERS' },
                    { v: 'SOUND ATTENUATORS', t: 'SOUND ATTENUATORS' },
                    { v: 'ACCESSORIES', t: 'ACCESSORIES' },
                ];

                // Extra options (ONLY ABDO + AHMED)
                const extra = [
                    { v: 'PRE-INSULATED DUCTWORK', t: 'PRE-INSULATED DUCTWORK' },
                    { v: 'SPIRAL DUCTWORK', t: 'SPIRAL DUCTWORK' },
                ];

                const list = isWesternSpecial ? base.concat(extra) : base;

                // rebuild options
                $p.empty();
                list.forEach(o => $p.append(new Option(o.t, o.v)));

                // strict: if not allowed anymore, reset to blank
                const allowed = new Set(list.map(x => x.v));
                if (keepValue && allowed.has(current)) {
                    $p.val(current);
                } else {
                    $p.val('');
                }

                $p.trigger('change');
            }

        // When salesman changes → update products list
            $(document).on('change', '#salesmanSelect', function () {
                applyProductsBySalesman(true);
            });

        // When opening modal (Add)
            $('#btnAddInquiry').on('click', function () {
                setTimeout(() => applyProductsBySalesman(false), 0);
            });


            // Open modal
            $('#btnAddInquiry').on('click', function () {
                CURRENT_INQUIRY_ID = null;
                $('#inquiry_id').val('');
                $('#formAddInquiry')[0].reset();

                $('#projectSelect').val(null).trigger('change');
                $('#clientSelect').val(null).trigger('change');

                $('#locationSelect').empty().append('<option value="">Select area first</option>');
                $('#locationInput').val('').prop('disabled', true);

                $('#modalAddInquiry .modal-title').text('Add New Inquiry');
                $('#modalAddInquiry').modal('show');
            });

            // Select2 project
            $('#projectSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalAddInquiry'),
                placeholder: 'Type or select project',
                tags: true,
                minimumInputLength: 1,
                ajax: {
                    url: '{{ route('estimator.inquiries.options') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {term: params.term || '', field: 'project'};
                    },
                    processResults: function (data) {
                        return {results: data.results || []};
                    },
                    cache: true
                },
                language: {
                    searching: () => 'Searching…',
                    noResults: () => 'No project found — press Enter to use this name.',
                    errorLoading: () => 'Could not load suggestions.'
                },
                escapeMarkup: m => m
            });

            // Select2 client
            $('#clientSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalAddInquiry'),
                placeholder: 'Type or select client',
                tags: true,
                minimumInputLength: 1,
                ajax: {
                    url: '{{ route('estimator.inquiries.options') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {term: params.term || '', field: 'client'};
                    },
                    processResults: function (data) {
                        return {results: data.results || []};
                    },
                    cache: true
                },
                language: {
                    searching: () => 'Searching…',
                    noResults: () => 'No client found — press Enter to use this name.',
                    errorLoading: () => 'Could not load suggestions.'
                },
                escapeMarkup: m => m
            });

            // Submit form via AJAX
            $('#formAddInquiry').on('submit', function (e) {
                e.preventDefault();

                const $form = $(this);
                const formData = $form.serialize();

                let url = CREATE_URL;
                let method = 'POST';

                if (CURRENT_INQUIRY_ID) {
                    url = UPDATE_URL.replace('__ID__', CURRENT_INQUIRY_ID);
                    method = 'PUT';
                }

                $.ajax({
                    url: url,
                    method: 'POST',   // we’ll send _method when needed
                    data: method === 'PUT'
                        ? formData + '&_method=PUT'
                        : formData,
                    success: function (resp) {
                        if (resp.ok) {
                            $('#modalAddInquiry').modal('hide');

                            // reload tables
                            if (dtAll) dtAll.ajax.reload(null, false);
                            if (dtBid) dtBid.ajax.reload(null, false);
                            if (dtIn) dtIn.ajax.reload(null, false);

                            showToast(
                                CURRENT_INQUIRY_ID
                                    ? 'Inquiry updated successfully.'
                                    : 'Inquiry created successfully.'
                            );
                        }
                    },
                    error: function (xhr) {
                        let msg = 'Error while saving.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            });
        });

        // Delegated handler for EDIT
        $(document).on('click', '[data-action="edit"]', function () {
            const id = this.getAttribute('data-id');
            if (!id) return;

            const url = SHOW_URL.replace('__ID__', id);

            fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(r => r.json())
                .then(resp => {
                    if (!resp.ok || !resp.data) {
                        alert('Unable to load inquiry details.');
                        return;
                    }

                    const d = resp.data;
                    CURRENT_INQUIRY_ID = d.id;
                    $('#inquiry_id').val(d.id);

                    // Fill simple inputs
                    $('input[name="quotation_no"]').val(d.quotation_no);
                    $('input[name="quotation_date"]').val(d.quotation_date);
                    $('input[name="date_received"]').val(d.date_received);
                    $('input[name="price"]').val(d.price);
                    $('input[name="contact_person"]').val(d.contact_person || '');
                    $('input[name="contact_number"]').val(d.contact_number || '');
                    $('input[name="contact_email"]').val(d.contact_email || '');
                    $('input[name="company_address"]').val(d.company_address || '');

                    // NEW: revision
                    $('select[name="revision_no"]').val(d.revision_no ?? 0).trigger('change');

                    // Selects (normal)
                    $('#salesmanSelect').val(d.salesman).trigger('change');

                    $('select[name="area"]').val(d.area).trigger('change');
                    $('select[name="technical_base"]').val(d.technical_base || '').trigger('change');
                    $('select[name="technical_submittal"]').val(d.technical_submittal || '').trigger('change');
                    // after salesman applies list, then set product
                    setTimeout(() => {
                        $('#ataiProductsSelect').val(String(d.atai_products || '').toUpperCase()).trigger('change');
                    }, 0);
                    $('select[name="status"]').val(d.status).trigger('change');

                    // For area → locations, repopulate then set location input
                    populateLocations(d.area);

                    $('#locationInput').val(d.location || '');

                    if (d.location && AREA_LOCATIONS[d.area] && AREA_LOCATIONS[d.area].includes(d.location)) {
                        $('#locationSelect').val(d.location);
                    } else {
                        $('#locationSelect').val('');
                    }

                    $('#locationInput').prop('disabled', !d.area);

                    // Select2 (project & client)
                    const projectText = d.project || '';
                    if (projectText) {
                        const projOpt = new Option(projectText, projectText, true, true);
                        $('#projectSelect').html('').append(projOpt).trigger('change');
                    } else {
                        $('#projectSelect').val(null).trigger('change');
                    }

                    const clientText = d.client || '';
                    if (clientText) {
                        const clientOpt = new Option(clientText, clientText, true, true);
                        $('#clientSelect').html('').append(clientOpt).trigger('change');
                    } else {
                        $('#clientSelect').val(null).trigger('change');
                    }

                    $('#modalAddInquiry .modal-title').text('Edit Inquiry');
                    $('#modalAddInquiry').modal('show');
                })
                .catch(err => {
                    console.error(err);
                    alert('Error loading inquiry details.');
                });
        });

        /* =============================================================================
         *  DATA TABLES (All / Bidding / In-Hand)
         * ============================================================================= */

        const projectColumns = [
            {data: 'id', name: 'id', width: '64px'},
            {data: 'name', name: 'name'},
            {data: 'client', name: 'client'},
            {data: 'salesperson', name: 'salesperson'},
            {data: 'location', name: 'location'},
            {data: 'area_badge', name: 'area', orderable: true, searchable: false},
            {data: 'quotation_no', name: 'quotation_no'},

            // NEW: Revision column
            {
                data: 'revision_no',
                name: 'revision_no',
                orderable: true,
                searchable: false,
                className: 'text-center',
                render: function (data, type, row) {
                    const raw = (data !== undefined && data !== null) ? data : row.revision_no;
                    const n = parseInt(raw ?? 0, 10);
                    const safe = isNaN(n) ? 0 : n;

                    if (type === 'sort' || type === 'type' || type === 'filter') {
                        return safe;
                    }

                    if (!safe) {
                        return '<span class="badge bg-secondary-subtle text-secondary">Orig</span>';
                    }

                    return `<span class="badge bg-info-subtle text-info">R${safe}</span>`;
                }
            },

            {data: 'atai_products', name: 'atai_products'},
            {
                data: 'quotation_value_fmt',
                name: 'quotation_value',
                orderable: true,
                searchable: false,
                className: 'text-end'
            },
            {
                data: 'status_badge',
                name: 'status',
                orderable: true,
                searchable: false
            },
            {data: 'quotation_date', name: 'quotation_date'},
            {data: 'date_rec', name: 'date_rec'},
            {data: 'created_at_fmt', name: 'created_at'},
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                className: 'text-end'
            },
        ];

        function renderProgressCell(data, type) {
            const raw = (data == null ? null : parseInt(data, 10));
            const pct = Math.max(0, Math.min(100, isNaN(raw) ? 0 : raw));
            if (type === 'sort' || type === 'type' || type === 'filter') return pct;
            if (data == null) return '<span class="text-muted">—</span>';
            return `
          <div class="d-flex align-items-center gap-2" title="${pct}%">
            <div class="progress progress-thin flex-grow-1" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="${pct}">
              <div class="progress-bar bg-success" style="width:${pct}%"></div>
            </div>
            <small class="text-muted fw-semibold">${pct}%</small>
          </div>`;
        }

        function initProjectsTable(selector, statusKey) {
            const $table = $(selector);
            const statusMap = {
                all: '',
                bidding: 'Bidding',
                inhand: 'In-Hand'
            };

            return $table.DataTable({
                processing: true,
                serverSide: true,
                order: [[9, 'desc']],
                scrollX: true,          // NEW: enable horizontal scrolling
                scrollCollapse: true,   // NEW: don't force extra height
                autoWidth: false,       // NEW: use our CSS widths
                ajax: {
                    url: DT_URL,
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    data: (d) => {
                        d.status_norm = statusMap[statusKey] || '';
                        d.family = currentFamily || '';

                        const year = document.querySelector('#projYear')?.value || '';
                        const month = document.querySelector('#monthSelect')?.value || '';
                        const dFrom = document.querySelector('#dateFrom')?.value || '';
                        const dTo = document.querySelector('#dateTo')?.value || '';

                        const areaSel = document.querySelector('#projRegion')?.value || '';
                        const area = CAN_VIEW_ALL ? areaSel : (PROJ_REGION || '');

                        if (dFrom) d.date_from = dFrom;
                        if (dTo) d.date_to = dTo;
                        if (!dFrom && !dTo) {
                            if (month) d.month = month;
                            const yr = year || PROJ_YEAR || '';
                            if (yr) d.year = yr;
                        }
                        if (area) d.area = area;

                        const sTxt = document.getElementById('salesmanInput')?.value?.trim();
                        if (sTxt) d.salesman = sTxt;
                    },
                    dataSrc: (json) => {
                        const totalCount = Number(
                            (json?.recordsTotal !== undefined && json?.recordsTotal !== null)
                                ? json.recordsTotal
                                : (json?.recordsFiltered || 0)
                        );
                        const totalSum = Number(
                            (json?.header_sum_value !== undefined && json?.header_sum_value !== null)
                                ? json.header_sum_value
                                : (json?.sum_quotation_value || 0)
                        );

                        TAB_SUMS[statusKey] = totalSum;
                        TAB_COUNTS[statusKey] = totalCount;
                        TAB_LOADED[statusKey] = true;

                        if (CURRENT_TAB_KEY === statusKey) {
                            updateHeaderBadges(statusKey);
                        }

                        return json?.data || [];
                    },
                    error: (xhr, textStatus, err) => {
                        console.error('[DT ajax error]', statusKey, xhr.status, xhr.responseText || textStatus || err);
                        showToast('Failed to load ' + (statusKey || 'table') + ' data (' + xhr.status + '). Check console.');
                    }
                },
                columns: projectColumns,
                drawCallback: function () {
                    const api = this.api();
                    $(api.table().body())
                        .off('click.openRow')
                        .on('click.openRow', 'td:nth-child(2)', function () {
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
                if (tabId.includes('all')) key = 'all';
                else if (tabId.includes('bidding')) key = 'bidding';
                else if (tabId.includes('inhand')) key = 'inhand';

                if (!key) return;

                CURRENT_TAB_KEY = key;
                updateHeaderBadges(CURRENT_TAB_KEY);

                // NEW: readjust columns after tab change (important for scrollX)
                setTimeout(() => {
                    dtAll?.columns.adjust();
                    dtBid?.columns.adjust();
                    dtIn?.columns.adjust();
                }, 50);
            });
        });

        /* Normalization helpers for detail modals (Bidding / In-Hand) */
        function normalizeRow(row) {
            const qVal = row.quotationValue ?? row.quotation_value ?? row.price ?? 0;
            const revRaw = row.revision_no ?? row.revisionNo ?? 0;
            const revNum = Number(revRaw ?? 0);
            const safeRev = isNaN(revNum) ? 0 : revNum;

            return {
                ...row,
                id: row.id,
                name: row.name ?? row.projectName ?? '-',
                client: row.client ?? row.clientName ?? '-',
                salesperson: row.salesperson ?? '-',
                location: row.location ?? row.projectLocation ?? '-',
                area: row.area ?? '-',
                quotationValue: Number(qVal),
                quotationNo: row.quotationNo ?? row.quotation_no ?? '-',
                ataiProducts: row.ataiProducts ?? row.atai_products ?? '-',
                quotationDate: row.quotationDate ?? row.quotation_date ?? null,
                estimator: row.estimator ?? row.action1 ?? null,
                dateRec: row.dateRec ?? row.date_rec ?? null,
                status: String(row.status_display ?? row.status ?? '').toLowerCase(),
                checklist: row.checklist ?? {},
                comments: row.comments ?? '',
                revision_no: safeRev,
                revisionLabel: safeRev === 0 ? 'Original' : `R${safeRev}`,
            };
        }

        function fillDetails(dlId, p) {
            const dl = document.getElementById(dlId);
            if (!dl) return;

            const rows = [
                ['Project', p.name],
                ['Client', p.client],
                ['salesperson', p.salesperson],
                ['Location', p.location],
                ['Area', p.area || '—'],
                ['Quotation No', p.quotationNo || '—'],
                ...(p.revisionLabel ? [['Revision', p.revisionLabel]] : []),
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
            if (/^in[\s-]?hand$/.test(t) || ['accepted', 'won', 'order', 'order in hand', 'ih'].includes(t)) return 'inhand';
            return 'bidding';
        }

        document.getElementById('searchAll')?.addEventListener('input', e => {
            dtAll && dtAll.search(e.target.value).draw();
        });

        function openProjectModalFromData(row) {
            const p = normalizeRow(row);
            CURRENT_PROJECT_ID = p.id || row.id || null;
            CURRENT_QUOTATION_NO = p.quotationNo || p.quotation_no || '-';
            const kind = normalizeStatusToKind(p.status);

            if (kind === 'bidding') {
                loadSubmittalPreview(CURRENT_PROJECT_ID, 'bidding');
                document.getElementById('biddingModalLabel').textContent = p.name || 'Project';
                fillDetails('biddingDetails', p);
                renderChecklist('biddingChecklist', checklistBidding, 'bid', p.checklistBidding || {});
                setBiddingActionButtonsEnabled(false);
                if (typeof p.biddingProgress === 'number') {
                    const pct = Math.max(0, Math.min(100, Number(p.biddingProgress)));
                    document.getElementById('biddingProgressPct').textContent = pct + '%';
                    const bar = document.getElementById('biddingProgressBar');
                    if (bar) {
                        bar.style.width = pct + '%';
                        bar.setAttribute('aria-valuenow', String(pct));
                    }
                    setBiddingActionButtonsEnabled(pct === 100);
                } else {
                    updateChecklistProgress('bidding');
                }
                let notesWrap = document.getElementById('biddingNotesBody');
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
                    if (bar) {
                        bar.style.width = pct + '%';
                        bar.setAttribute('aria-valuenow', String(pct));
                    }
                } else {
                    updateChecklistProgress('inhand');
                }
                let notesWrap = document.getElementById('inhandNotesBody');
                notesWrap.innerHTML = (p.notes || []).map(n =>
                    `<div class="border-start ps-2">
                       <div class="text-muted">${(n.created_at || '').replace('T', ' ').replace('Z', '')}</div>
                       <div>${n.note}</div>
                     </div>`).join('') || '<div class="text-muted">No notes yet.</div>';
                new bootstrap.Modal(document.getElementById('inhandModal')).show();
                return;
            }
        }

        /* Global search per tab */
        let dtAll, dtBid, dtIn;

        document.getElementById('searchBidding')?.addEventListener('input', e => {
            dtBid && dtBid.search(e.target.value).draw();
        });

        document.getElementById('searchInhand')?.addEventListener('input', e => {
            if (dtIn) {
                dtIn.search(e.target.value);
                dtIn.page(0).draw('page');
            }
        });

        /* Family chips → refresh DT */
        $(document).on('click', '#familyChips [data-family]', function (e) {
            e.preventDefault();
            $('#familyChips [data-family]').removeClass('active');
            this.classList.add('active');
            currentFamily = this.getAttribute('data-family') || '';
            resetTabTotals();
            dtAll?.ajax.reload(null, false);
            dtBid?.ajax.reload(null, false);
            dtIn?.ajax.reload(null, false);
        });

        /* Apply filters */
        document.getElementById('projApply')?.addEventListener('click', () => {
            const yearEl = document.getElementById('projYear');
            const monthEl = document.getElementById('monthSelect');
            const fromEl = document.getElementById('dateFrom');
            const toEl = document.getElementById('dateTo');

            const month = monthEl?.value || '';
            const dFrom = fromEl?.value || '';
            const dTo = toEl?.value || '';

            const year = yearEl?.value || '';

            /**
             * Allow:
             * - Year only
             * - Month + Year
             * - Date range (From/To)
             * - Nothing (All Years)
             *
             * Block only:
             * - Month selected without Year
             */
            if (month && !year) {
                alert('Please select a Year when filtering by Month.');
                return;
            }

            PROJ_YEAR = yearEl?.value || '';
            PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';

            resetTabTotals();
            dtAll?.ajax.reload(null, false);
            dtBid?.ajax.reload(null, false);
            dtIn?.ajax.reload(null, false);
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

            const yearSel = document.getElementById('projYear');
            if (yearSel && !yearSel.value) {
                const opt = [...yearSel.options].find(o => o.value === '2026');
                if (opt) yearSel.value = '2026';
            }

            dtAll = initProjectsTable('#tblAll', 'all');
            dtBid = initProjectsTable('#tblBidding', 'bidding');
            dtIn = initProjectsTable('#tblInhand', 'inhand');
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
                status: d.status_display ?? d.status ?? row.status,

                checklistBidding: d.checklistBidding ?? {},
                checklistInhand: d.checklistInhand ?? {},

                biddingProgress: d.biddingProgress,
                inhandProgress: d.inhandProgress,

                notes: d.notes ?? [],
                progressHistory: d.progressHistory ?? [],

                dateRec: d.dateRec ?? row.dateRec,
                clientReference: d.clientReference ?? row.clientReference,
                projectType: d.projectType ?? row.projectType,
                salesperson: d.salesperson ?? row.salesperson,
                revision_no: d.revision_no ?? row.revision_no ?? 0,
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
            const url = phase === 'bidding'
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
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || res.statusText);
            return res.json();
        }

        /* =============================================================================
         *  DATE PASTE NORMALIZER
         * ============================================================================= */
        function normalizeDateInput(selector) {
            document.querySelectorAll(selector).forEach(input => {
                input.addEventListener('paste', function (e) {
                    e.preventDefault();

                    let text = (e.clipboardData || window.clipboardData).getData('text').trim();
                    text = text.replace(/[\.\ \/]/g, '-');

                    let parts = text.split('-');
                    let formatted = '';

                    if (parts.length === 3) {
                        if (parts[0].length === 4) {
                            formatted = `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
                        } else {
                            formatted = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
                        }
                    }

                    if (formatted.length === 10) {
                        this.value = formatted;
                        this.dispatchEvent(new Event('change'));
                    }
                });
            });
        }

        normalizeDateInput('input[name="quotation_date"], input[name="date_received"]');

        /* =============================================================================
         *  EXPORT BUTTONS
         * ============================================================================= */
        function buildExportParams() {
            const year = document.getElementById('projYear')?.value || '';
            const month = document.getElementById('monthSelect')?.value || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';
            const areaSel = document.getElementById('projRegion')?.value || '';
            const salesman = document.getElementById('salesmanInput')?.value?.trim() || '';
            const family = document.querySelector('#familyChips .active')?.getAttribute('data-family') || '';

            const params = new URLSearchParams();
            if (year) params.append('year', year);
            if (month) params.append('month', month);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (areaSel) params.append('area', areaSel);
            if (salesman) params.append('salesman', salesman);
            if (family) params.append('family', family);

            return params.toString();
        }

        document.getElementById('btnExportExcel')?.addEventListener('click', () => {
            const year = document.getElementById('projYear')?.value || '';
            const month = document.getElementById('monthSelect')?.value || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';

            if (!month && !dateFrom && !dateTo) {
                alert('Please select a Month or a From/To date range for Weekly export.');
                return;
            }

            if (month && !year) {
                alert('Please select a Year when exporting by Month.');
                return;
            }

            const qs = buildExportParams();
            window.location.href = '{{ route('estimation.reports.export.weekly') }}' + (qs ? ('?' + qs) : '');
        });

        document.getElementById('btnExportMonthly')?.addEventListener('click', () => {
            const year = $('#projYear').val();
            const month = $('#monthSelect').val();

            if (!year || !month) {
                alert('Please select  Month for Monthly export.');
                return;
            }

            const qs = buildExportParams();
            window.location.href = '{{ route('estimation.reports.export.monthly') }}' + (qs ? ('?' + qs) : '');
        });

        let PENDING_DELETE_ID = null;
        const confirmModalEl = document.getElementById('confirmActionModal');
        const confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;

        // Click trash icon
        $(document).on('click', '[data-action="delete"]', function () {
            const id = this.getAttribute('data-id');
            if (!id || !confirmModal) return;

            PENDING_DELETE_ID = id;

            document.getElementById('confirmTitle').textContent = 'Delete Inquiry';
            document.getElementById('confirmText').textContent =
                'Are you sure you want to delete this inquiry?';

            const btn = document.getElementById('confirmOkBtn');
            btn.classList.remove('btn-primary', 'btn-warning');
            btn.classList.add('btn-danger');

            cleanupBackdrops();

            confirmModal.show();
        });

        // When user clicks OK in confirm
        document.getElementById('confirmOkBtn')?.addEventListener('click', function () {
            if (!PENDING_DELETE_ID) return;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const url = DELETE_URL.replace('__ID__', PENDING_DELETE_ID);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: '_method=DELETE'
            })
                .then(r => r.json())
                .then(resp => {
                    confirmModal.hide();
                    cleanupBackdrops();

                    PENDING_DELETE_ID = null;

                    if (!resp.ok) {
                        alert(resp.message || 'Delete failed.');
                        return;
                    }

                    dtAll?.ajax.reload(null, false);
                    dtBid?.ajax.reload(null, false);
                    dtIn?.ajax.reload(null, false);

                    showToast('Inquiry deleted.');
                })
                .catch(err => {
                    console.error(err);
                    alert('Unexpected error while deleting.');
                });
        });

        document.addEventListener('hidden.bs.modal', (e) => {
            if (e.target && e.target.id === 'confirmActionModal') {
                cleanupBackdrops();
            }
        });
    </script>
@endpush
