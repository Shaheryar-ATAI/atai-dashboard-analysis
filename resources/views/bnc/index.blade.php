@extends('layouts.app')

@section('title', 'BNC Projects')
@section('body-class', 'bnc-page')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ @filemtime(public_path('css/atai-theme.css')) ?: time() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        /* ============================================================
           BNC PAGE ONLY – modal stacking + click + stuck-backdrop fix
        ============================================================ */
        /* ============================================================
           BNC ONLY – Stable modal stacking (Bootstrap-friendly)
           Put at VERY END of atai-theme.css OR page footer <style>
           ============================================================ */

        /* Ensure close button/header always above glass overlays */
        body.bnc-page .modal-header,
        body.bnc-page .modal-footer,
        body.bnc-page .btn-close{
            position: relative !important;
            z-index: 2 !important;
        }

        /* Don’t let any pseudo overlay eat clicks */
        body.bnc-page .modal-atai .modal-content::before,
        body.bnc-page .modal-atai .modal-content::after,
        body.bnc-page .modal-atai .modal-header::before,
        body.bnc-page .modal-atai .modal-header::after{
            pointer-events: none !important;
        }

        body.bnc-page:not(.modal-open) .modal-backdrop{
            pointer-events: none !important;
        }

        /* If theme uses glass pseudo overlays, never capture clicks */
        .modal-content::before,
        .modal-content::after,
        .modal-header::before,
        .modal-header::after{
            pointer-events:none !important;
        }

        /* Pretty text block for BNC YAML-style sections */
        .bnc-pre{
            white-space: pre-wrap;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.35;
        }

        /* Child row container */
        .bnc-child-wrap{
            padding: 10px 14px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 12px;
            background: rgba(255,255,255,.03);
            backdrop-filter: blur(6px);
        }

        .bnc-child-title{
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .2px;
            margin-bottom: 8px;
            color: #7CFFB2;
        }

        .bnc-child-table{
            margin: 0;
            border-radius: 10px;
            overflow: hidden;
        }

        .bnc-child-table thead th{
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            opacity: .9;
        }

        .bnc-child-table tbody td{
            font-size: 12px;
            padding: 6px 10px !important;
        }

        .bnc-qno{
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            color: #9AE6B4;
        }

        tr.shown td{ border-bottom-color: transparent !important; }

        .bnc-quotes-toggle{
            border-radius: 999px !important;
            padding: 4px 10px !important;
            font-size: 12px !important;
            border-color: rgba(255,255,255,.20) !important;
        }
        .bnc-quotes-toggle:hover{
            border-color: rgba(255,255,255,.35) !important;
        }

        #tblBncProjects thead th{
            font-size: 11px;
            letter-spacing: .35px;
            text-transform: uppercase;
            opacity: .9;
        }


        /* ============================
           BNC MODAL FIX (END OF ALL CSS)
           Scope EVERYTHING to body.bnc-page
           ============================ */

        /* Backdrop must ALWAYS be below modal (BNC only) */
        body.bnc-page .modal-backdrop,
        body.bnc-page .modal-backdrop.fade,
        body.bnc-page .modal-backdrop.show{
            z-index: 2050 !important;
            pointer-events: auto !important; /* allow backdrop click to close */
        }

        /* Modal must ALWAYS be above backdrop (BNC only) */
        body.bnc-page .modal,
        body.bnc-page .modal.show{
            z-index: 2060 !important;
            pointer-events: auto !important;
        }

        /* If multiple modals open (Quote modal over Details modal) */
        body.bnc-page .modal.modal-stack{
            z-index: 2070 !important;
        }
        body.bnc-page .modal-backdrop.modal-stack{
            z-index: 2065 !important;
        }

        /* Make sure no “glass overlay” blocks clicks */
        body.bnc-page .modal-content::before,
        body.bnc-page .modal-content::after,
        body.bnc-page .modal-header::before,
        body.bnc-page .modal-header::after{
            pointer-events: none !important;
        }

        /* Safety: if modal is closed, backdrop should not block page
           DO NOT use display:none (it breaks stacked transitions) */
        body.bnc-page:not(.modal-open) .modal-backdrop{
            pointer-events: none !important;
            opacity: 0 !important;
        }

    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        {{-- Filters row --}}
        <div class="card mb-3 atai-card-dark">
            <div class="card-body py-2">
                <form id="bncFilters" class="row g-2 align-items-end">

                    @hasanyrole('admin|gm')
                    <div class="col-md-2">
                        <label class="form-label form-label-sm text-white-50 mb-1">Region</label>
                        <select name="region" id="bnc_region" class="form-select form-select-sm">
                            <option value="">All Regions</option>
                            <option value="Eastern" {{ request('region') === 'Eastern' ? 'selected' : '' }}>Eastern</option>
                            <option value="Central" {{ request('region') === 'Central' ? 'selected' : '' }}>Central</option>
                            <option value="Western" {{ request('region') === 'Western' ? 'selected' : '' }}>Western</option>
                        </select>
                    </div>
                    @endhasanyrole

                    <div class="col-md-2">
                        <label class="form-label form-label-sm text-white-50 mb-1">Stage</label>
                        <select name="stage" id="bnc_stage" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Concept">Concept</option>
                            <option value="Design">Design</option>
                            <option value="Tender">Tender</option>
                            <option value="Under Construction">Under Construction</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm text-white-50 mb-1">Search</label>
                        <input type="text" name="q" id="bnc_q" class="form-control form-control-sm" placeholder="Project / client / city">
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" id="bncApplyFilters" class="btn btn-sm btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <button type="button" id="bncResetFilters" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- KPI row --}}
        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Total Projects</div>
                        <div id="kpi_total_projects" class="fs-5 fw-semibold text-white">
                            {{ number_format($kpis['total_projects'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Total Value (SAR)</div>
                        <div id="total_value_Sar" class="fs-5 fw-semibold text-white">
                            {{ $kpis['total_value_Sar'] ?? '0' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Approached</div>
                        <div id="kpi_approached" class="fs-5 fw-semibold text-white">
                            {{ number_format($kpis['approached'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Expected Closures (30d)</div>
                        <div id="kpi_expected_close" class="fs-5 fw-semibold text-white">
                            {{ number_format($kpis['expected_close30'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#bncExportModal">
            <i class="bi bi-download me-1"></i> Download PDF
        </button>

        {{-- Main table --}}
        <div class="card atai-card-dark">
            <div class="card-body">
                <table id="tblBncProjects" class="table table-sm table-hover align-middle w-100">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>City</th>
                        <th>Region</th>
                        <th>Stage</th>
                        <th class="text-end">Value (SAR)</th>
                        <th>Quotes</th>
                        <th class="text-end">Quoted Value (SAR)</th>
                        <th>Approached</th>
                        <th>Expected Close</th>
                        <th class="text-end"></th>
                        <th class="d-none">Quotes List</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        {{-- Modal: Project details / edit checkpoints --}}
        <div class="modal fade modal-atai" id="bncDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bnc_modal_title">BNC Project Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="text-white-50 mb-2">Project Information</h6>
                                <dl class="row small mb-3" id="bnc_info_basic"></dl>

                                <hr class="border-secondary my-2">

                                <h6 class="text-white-50 mb-2">Overview Info</h6>
                                <div id="bnc_overview_block" class="bnc-pre small mb-3"></div>

                                <h6 class="text-white-50 mb-2">Parties (Consultant / Contractors / MEP)</h6>
                                <div id="bnc_parties_block" class="bnc-pre small mb-3"></div>

                                <h6 class="text-white-50 mb-2">Latest News</h6>
                                <div id="bnc_latest_block" class="bnc-pre small"></div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-white-50 mb-2">ATAI Checkpoints</h6>
                                <form id="bncCheckpointForm">
                                    @csrf
                                    <input type="hidden" id="bnc_project_id">

                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label form-label-sm">Approached</label>
                                            <select id="bnc_approached_input" name="approached" class="form-select form-select-sm">
                                                <option value="0">No</option>
                                                <option value="1">Yes</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label form-label-sm">Lead Status</label>
                                            <select id="bnc_lead_qualified_input" name="lead_qualified" class="form-select form-select-sm">
                                                <option value="Unknown">Unknown</option>
                                                <option value="Hot">Hot</option>
                                                <option value="Warm">Warm</option>
                                                <option value="Cold">Cold</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label form-label-sm">Penetration %</label>
                                            <input type="number" min="0" max="100" id="bnc_penetration_input" name="penetration_percent" class="form-control form-control-sm">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label form-label-sm">Expected Close Date</label>
                                            <input type="date" id="bnc_expected_input" name="expected_close_date" class="form-control form-control-sm">
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" value="1" id="bnc_boq_shared" name="boq_shared">
                                                <label class="form-check-label small" for="bnc_boq_shared">BOQ Shared</label>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" value="1" id="bnc_submittal_shared" name="submittal_shared">
                                                <label class="form-check-label small" for="bnc_submittal_shared">Submittal Shared</label>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" value="1" id="bnc_submittal_approved" name="submittal_approved">
                                                <label class="form-check-label small" for="bnc_submittal_approved">Submittal Approved</label>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label form-label-sm">Notes</label>
                                            <textarea id="bnc_notes_input" name="notes" class="form-control form-control-sm" rows="3"></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer justify-content-between">
                        <small class="text-white-50" id="bnc_audit_info"></small>
                        <button type="button" class="btn btn-sm btn-primary" id="bncSaveCheckpointBtn">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal: Export PDF --}}
        <div class="modal fade modal-atai" id="bncExportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Export BNC Projects (PDF)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="GET" action="{{ route('bnc.export.pdf') }}" target="_blank">
                        <div class="modal-body">
                            @hasanyrole('admin|gm')
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Region</label>
                                <select name="region" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="Eastern">Eastern</option>
                                    <option value="Central">Central</option>
                                    <option value="Western">Western</option>
                                </select>
                            </div>
                            @endhasanyrole

                            <div class="mb-2">
                                <label class="form-label form-label-sm">Stage</label>
                                <select name="stage" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="Concept">Concept</option>
                                    <option value="Design">Design</option>
                                    <option value="Tender">Tender</option>
                                    <option value="Under Construction">Under Construction</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label form-label-sm">Minimum Value (SAR)</label>
                                <input type="number" name="min_value_sar" class="form-control form-control-sm" value="0" min="0">
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm">Approached</label>
                                    <select name="approached" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-sm btn-primary">Download PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal: Quotation Details --}}
        <div class="modal fade" id="bncQuoteModal" tabindex="-1" aria-labelledby="bncQuoteModalTitle" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bncQuoteModalTitle">Quotation Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="bncQuoteModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
    <script>
        (function () {
            const $ = window.jQuery;
            if (!window.bootstrap) {
                console.error("Bootstrap not found. Modals won't work.");
                return;
            }

            // ============================================================
            // 0) Ensure modals live under <body> (avoid z-index stacking traps)
            // ============================================================
            function moveModalToBody(id) {
                const el = document.getElementById(id);
                if (el && el.parentElement !== document.body) {
                    document.body.appendChild(el);
                }
                return el;
            }

            // Move BNC modals to <body> so backdrop layering works correctly
            moveModalToBody('bncDetailsModal');
            moveModalToBody('bncExportModal');
            moveModalToBody('bncQuoteModal');

            // ============================================================
            // 1) Hard cleanup (when Bootstrap state gets corrupted)
            // ============================================================
            function hardCleanup() {
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }

            // ============================================================
            // 2) Stacked modal manager (z-index + proper backdrop layering)
            // ============================================================
            function restackModals() {
                const openModals = Array.from(document.querySelectorAll('.modal.show'));

                // If no modals open, clean everything
                if (!openModals.length) {
                    hardCleanup();
                    return;
                }

                // ✅ If at least one modal is open, ensure body stays modal-open
                // (Bootstrap sometimes removes it when stacked modals close)
                document.body.classList.add('modal-open');

                // Base Bootstrap backdrop/modal z-index is around 1055/1065.
                // We use higher to beat theme conflicts.
                const baseBackdrop = 2050;
                const baseModal = 2060;

                openModals.forEach((m, i) => {
                    m.style.zIndex = String(baseModal + (i * 10));
                    if (i > 0) m.classList.add('modal-stack');
                    else m.classList.remove('modal-stack');
                });

                // Backdrops: assign increasing z-index to each backdrop
                const backs = Array.from(document.querySelectorAll('.modal-backdrop'));
                backs.forEach((b, i) => {
                    b.style.zIndex = String(baseBackdrop + (i * 10));
                    if (i > 0) b.classList.add('modal-stack');
                    else b.classList.remove('modal-stack');
                });
            }


            // When any modal opens/closes, restack
            document.addEventListener('shown.bs.modal', restackModals);
            document.addEventListener('hidden.bs.modal', () => {
                // allow Bootstrap to remove nodes first
                setTimeout(restackModals, 50);
            });

            // ============================================================
            // 3) Force-close handler (X / Close button always works)
            // ============================================================
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-bs-dismiss="modal"], .btn-close');
                if (!btn) return;

                const modalEl = btn.closest('.modal');
                if (!modalEl) return;

                e.preventDefault();
                e.stopPropagation();

                try {
                    const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
                    inst.hide();
                } catch (err) {
                    console.warn("Force hide fallback:", err);
                    modalEl.classList.remove('show');
                    modalEl.style.display = 'none';
                    modalEl.setAttribute('aria-hidden', 'true');
                    hardCleanup();
                }

                setTimeout(restackModals, 80);
            }, true);

            // ============================================================
            // 4) Quote normalizer + lookup
            // ============================================================
            const normQ = (s) => String(s || '')
                .replace(/\u00A0/g, '')
                .replace(/[\r\n\t]/g, '')
                .replace(/[^0-9A-Za-z]+/g, '')
                .trim()
                .toUpperCase();

            const bncBaseUrl = @json(url('/bnc'));
            const csrfToken = @json(csrf_token());

            async function fetchQuoteLookup(qnos) {
                const url = @json(route('bnc.quotes.lookup'));
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ qnos }),
                });

                const ct = res.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    const text = await res.text();
                    throw new Error(`Lookup returned NON-JSON. First 300 chars:\n${text.substring(0,300)}`);
                }
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(`Lookup HTTP ${res.status}: ${text.substring(0, 300)}`);
                }
                const json = await res.json();
                if (!json || json.ok !== true) throw new Error('Lookup invalid JSON');
                return json;
            }

            const getVal = (id) => (document.getElementById(id)?.value ?? '');
            const fmt = (n) => Number(n || 0).toLocaleString('en-US');
            const safe = (v) => (v === null || v === undefined || v === '') ? '—' : String(v);

            function formatTextBlock(val) {
                if (val === null || val === undefined || val === '') return '—';
                if (Array.isArray(val)) {
                    const lines = val.map(v => {
                        if (v && typeof v === 'object') {
                            const parts = Object.entries(v).map(([k, vv]) => `${k}: ${vv ?? ''}`.trim());
                            return parts.filter(Boolean).join(' | ');
                        }
                        return String(v ?? '').trim();
                    }).filter(Boolean);
                    return lines.length ? lines.join('\n') : '—';
                }
                if (typeof val === 'object') {
                    const lines = Object.entries(val)
                        .map(([k, v]) => `${k}: ${v ?? ''}`.trim())
                        .filter(Boolean);
                    return lines.length ? lines.join('\n') : '—';
                }
                return String(val);
            }

            async function fetchBncDetails(id) {
                const res = await fetch(`${bncBaseUrl}/${encodeURIComponent(id)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(`BNC details HTTP ${res.status}: ${text.substring(0, 200)}`);
                }
                return res.json();
            }

            function parseQuotesList(raw) {
                let list = [];
                if (Array.isArray(raw)) list = raw;

                return (Array.isArray(list) ? list : [])
                    .map(x => ({ no: String(x?.no ?? '').trim(), val: Number(x?.val ?? 0) }))
                    .filter(x => x.no !== '');
            }

            // ============================================================
            // 5) DataTable
            // ============================================================
            const table = $('#tblBncProjects').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 25,
                order: [[5, 'desc']],
                ajax: {
                    url: @json(route('bnc.datatable')),
                    data: function (d) {
                        d.region = getVal('bnc_region');
                        d.stage  = getVal('bnc_stage');
                        d.q      = getVal('bnc_q');
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'project_name', name: 'bnc_projects.project_name' },
                    { data: 'city',         name: 'bnc_projects.city' },
                    { data: 'region',       name: 'bnc_projects.region' },
                    { data: 'stage',        name: 'bnc_projects.stage' },
                    { data: 'value_sar',    name: 'value_sar', className: 'text-end', orderable: true, searchable: false },
                    { data: 'quoted_status',    name: 'quoted_status',    orderable: false, searchable: false },
                    { data: 'quoted_value_sar', name: 'quoted_value_sar', className: 'text-end', orderable: false, searchable: false },
                    { data: 'approached',         name: 'bnc_projects.approached', orderable: false, searchable: false },
                    { data: 'expected_close_date',name: 'bnc_projects.expected_close_date', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                    { data: 'quotes_list', name: 'quotes_list', orderable: false, searchable: false },
                ],
                columnDefs: [{ targets: 11, visible: false, searchable: false }],
                drawCallback: function () {
                    // If bootstrap got confused, fix state
                    restackModals();
                }
            });

            document.getElementById('bncApplyFilters')?.addEventListener('click', () => table.ajax.reload());
            document.getElementById('bncResetFilters')?.addEventListener('click', () => {
                document.getElementById('bncFilters')?.reset();
                table.ajax.reload();
            });

            // ============================================================
            // 6) View BNC Project modal
            // IMPORTANT: your controller returns class "btn-bnc-view"
            // ============================================================
            const detailsEl = document.getElementById('bncDetailsModal');
            const detailsModal = detailsEl ? bootstrap.Modal.getOrCreateInstance(detailsEl, { backdrop:true, keyboard:true }) : null;

            $(document).off('click.bncView', '#tblBncProjects tbody .btn-bnc-view');
            $(document).on('click.bncView', '#tblBncProjects tbody .btn-bnc-view', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (!detailsModal) return;

                const tr = $(this).closest('tr');
                const data = table.row(tr).data() || {};

                document.getElementById('bnc_modal_title').textContent = 'BNC Project Details';

                const id = String($(this).data('id') || data.id || '').trim();
                document.getElementById('bnc_project_id').value = id;

                const dl = document.getElementById('bnc_info_basic');
                dl.innerHTML = `
      <dt class="col-4 text-white-50">Project</dt><dd class="col-8">${safe(data.project_name)}</dd>
      <dt class="col-4 text-white-50">City</dt><dd class="col-8">${safe(data.city)}</dd>
      <dt class="col-4 text-white-50">Region</dt><dd class="col-8">${safe(data.region)}</dd>
      <dt class="col-4 text-white-50">Stage</dt><dd class="col-8">${safe(data.stage)}</dd>
      <dt class="col-4 text-white-50">Value (SAR)</dt><dd class="col-8">${safe(data.value_sar)}</dd>
    `;

                detailsModal.show();
                setTimeout(restackModals, 50);

                // Fill details block + form from API
                const overviewEl = document.getElementById('bnc_overview_block');
                const partiesEl = document.getElementById('bnc_parties_block');
                const latestEl = document.getElementById('bnc_latest_block');
                const auditEl = document.getElementById('bnc_audit_info');

                overviewEl.textContent = 'Loading...';
                partiesEl.textContent = 'Loading...';
                latestEl.textContent = 'Loading...';

                try {
                    const info = await fetchBncDetails(id);

                    document.getElementById('bnc_modal_title').textContent =
                        `BNC Project Details — ${safe(info.project_name)}`;

                    overviewEl.textContent = formatTextBlock(info.overview_info);
                    partiesEl.textContent = formatTextBlock(info.raw_parties);
                    latestEl.textContent = formatTextBlock(info.latest_news);

                    document.getElementById('bnc_approached_input').value = info.approached ? '1' : '0';
                    document.getElementById('bnc_lead_qualified_input').value = info.lead_qualified || 'Unknown';
                    document.getElementById('bnc_penetration_input').value = info.penetration_percent ?? '';
                    document.getElementById('bnc_expected_input').value = info.expected_close_date ?? '';
                    document.getElementById('bnc_boq_shared').checked = !!info.boq_shared;
                    document.getElementById('bnc_submittal_shared').checked = !!info.submittal_shared;
                    document.getElementById('bnc_submittal_approved').checked = !!info.submittal_approved;
                    document.getElementById('bnc_notes_input').value = info.notes ?? '';

                    const updated = info.updated_at ? `Updated: ${info.updated_at}` : '';
                    const created = info.created_at ? `Created: ${info.created_at}` : '';
                    auditEl.textContent = [updated, created].filter(Boolean).join(' • ');
                } catch (err) {
                    console.error(err);
                    overviewEl.textContent = 'Failed to load overview.';
                    partiesEl.textContent = 'Failed to load parties.';
                    latestEl.textContent = 'Failed to load latest news.';
                }
            });

            // ============================================================
            // 6b) Save checkpoint
            // ============================================================
            const saveBtn = document.getElementById('bncSaveCheckpointBtn');
            saveBtn?.addEventListener('click', async () => {
                const id = String(document.getElementById('bnc_project_id')?.value || '').trim();
                if (!id) return;

                const fd = new FormData();
                fd.append('approached', document.getElementById('bnc_approached_input')?.value ?? '0');
                fd.append('lead_qualified', document.getElementById('bnc_lead_qualified_input')?.value ?? 'Unknown');
                fd.append('penetration_percent', document.getElementById('bnc_penetration_input')?.value ?? '');
                fd.append('expected_close_date', document.getElementById('bnc_expected_input')?.value ?? '');
                fd.append('boq_shared', document.getElementById('bnc_boq_shared')?.checked ? '1' : '0');
                fd.append('submittal_shared', document.getElementById('bnc_submittal_shared')?.checked ? '1' : '0');
                fd.append('submittal_approved', document.getElementById('bnc_submittal_approved')?.checked ? '1' : '0');
                fd.append('notes', document.getElementById('bnc_notes_input')?.value ?? '');

                const originalText = saveBtn.textContent;
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                try {
                    const res = await fetch(`${bncBaseUrl}/${encodeURIComponent(id)}`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: fd,
                    });

                    const ct = res.headers.get('content-type') || '';
                    const json = ct.includes('application/json') ? await res.json() : null;
                    if (!res.ok || !json?.ok) {
                        const text = json ? JSON.stringify(json) : await res.text();
                        throw new Error(`Save failed: ${text.substring(0, 200)}`);
                    }

                    document.getElementById('bnc_audit_info').textContent =
                        `Saved: ${new Date().toLocaleString()}`;

                    // Refresh table row values without jumping pages
                    table.ajax.reload(null, false);
                } catch (err) {
                    console.error(err);
                    alert(`Save failed: ${err.message}`);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
            });

            // ============================================================
            // 7) Quotes toggle (child row)
            // ============================================================
            $(document).off('click', '#tblBncProjects tbody .bnc-quotes-toggle');
            $(document).on('click', '#tblBncProjects tbody .bnc-quotes-toggle', async function (e) {
                e.preventDefault();

                const tr   = $(this).closest('tr');
                const row  = table.row(tr);
                const data = row.data() || {};
                const $icon = $(this).find('i.bi');

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    $icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                    return;
                }

                row.child(`
      <div class="bnc-child-wrap">
        <div class="bnc-child-title">Linked Quotations</div>
        <div class="text-muted small">Loading quotation details...</div>
      </div>
    `).show();

                tr.addClass('shown');
                $icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');

                const list = parseQuotesList(data.quotes_list);
                if (!list.length) {
                    row.child(`
        <div class="bnc-child-wrap">
          <div class="bnc-child-title">Linked Quotations</div>
          <div class="text-muted small">No linked quotations found.</div>
        </div>
      `).show();
                    return;
                }

                const qnosNorm = list.map(x => normQ(x.no));
                let lookup = {};
                try {
                    const json = await fetchQuoteLookup(qnosNorm);
                    lookup = json?.data || {};
                } catch (err) {
                    console.error('Lookup failed ❌', err);
                    lookup = {};
                }

                let html = `
      <div class="bnc-child-wrap">
        <div class="bnc-child-title">Linked Quotations</div>
        <table class="table table-sm table-dark table-striped bnc-child-table mb-0">
          <thead>
            <tr>
              <th style="width:30%;">Quotation No.</th>
              <th class="text-end" style="width:15%;">Quoted (SAR)</th>
              <th style="width:18%;">Source</th>
              <th style="width:12%;">PO</th>
              <th class="text-end" style="width:15%;">PO Value (SAR)</th>
              <th class="text-end" style="width:10%;">Action</th>
            </tr>
          </thead>
          <tbody>
    `;

                for (const q of list) {
                    const key = normQ(q.no);
                    const info = lookup[key] || {};
                    const foundIn = info.found_in || 'none';
                    const srcLabel = (foundIn === 'salesorderlog') ? 'Sales Order Log'
                        : (foundIn === 'projects') ? 'Projects' : '—';

                    const poBadge = info.po_received
                        ? `<span class="badge bg-success">PO Received</span>`
                        : `<span class="badge bg-secondary">No PO</span>`;

                    const poVal = info.po_received ? fmt(info.po_value) : '—';

                    html += `
        <tr>
          <td class="bnc-qno">${q.no}</td>
          <td class="text-end">${fmt(q.val)}</td>
          <td>${srcLabel}<div class="text-white-50 small">${safe(info.sales_source)}</div></td>
          <td>${poBadge}</td>
          <td class="text-end">${poVal}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-light bnc-quote-view" data-qno="${q.no}">
              View
            </button>
          </td>
        </tr>
      `;
                }

                html += `</tbody></table></div>`;
                row.child(html).show();
            });

            // ============================================================
            // 8) Quote modal
            // ============================================================
            const quoteEl = document.getElementById('bncQuoteModal');
            const quoteModal = quoteEl ? bootstrap.Modal.getOrCreateInstance(quoteEl, { backdrop:true, keyboard:true }) : null;

            let quoteBusy = false;
            $(document).off('click', '.bnc-quote-view');
            $(document).on('click', '.bnc-quote-view', async function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!quoteModal || quoteBusy) return;

                quoteBusy = true;

                const raw = String($(this).data('qno') || '').trim();
                const key = normQ(raw);

                try {
                    document.getElementById('bncQuoteModalTitle').textContent = 'Quotation Details';
                    document.getElementById('bncQuoteModalBody').innerHTML =
                        `<div class="small text-white-50">Loading quotation details...</div>`;

                    quoteModal.show();
                    setTimeout(restackModals, 50);

                    const json = await fetchQuoteLookup([key]);
                    const info = json?.data?.[key] || null;

                    if (!info || info.found_in === 'none') {
                        document.getElementById('bncQuoteModalBody').innerHTML = `
          <div class="small">
            <div><strong>Quotation:</strong> ${safe(raw)}</div>
            <div class="text-white-50"><strong>Normalized:</strong> ${safe(key)}</div>
            <hr class="border-secondary">
            <div class="text-warning">No details found in Sales Order Log or Projects.</div>
          </div>
        `;
                        return;
                    }

                    document.getElementById('bncQuoteModalBody').innerHTML = `
        <div class="small">
          <div class="mb-2"><strong>Quotation:</strong> ${safe(raw)}</div>
          <div class="mb-2 text-white-50"><strong>Normalized:</strong> ${safe(key)}</div>
          <hr class="border-secondary">
          <div class="row g-2">
            <div class="col-6"><strong>Found In</strong><div>${safe(info.found_in)}</div></div>
            <div class="col-6"><strong>Sales Source</strong><div>${safe(info.sales_source)}</div></div>
            <div class="col-6"><strong>PO Received</strong><div>
              ${info.po_received ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}
            </div></div>
            <div class="col-6"><strong>PO Value</strong><div>${info.po_received ? fmt(info.po_value) : '—'}</div></div>
            <div class="col-6"><strong>PO Count</strong><div>${safe(info.po_count)}</div></div>
            <div class="col-6"><strong>Last PO Date</strong><div>${safe(info.last_po_date)}</div></div>
            <div class="col-6"><strong>Quoted Value</strong><div>${fmt(info.quotation_value)}</div></div>
            <div class="col-6"><strong>Area</strong><div>${safe(info.area)}</div></div>
            <div class="col-6"><strong>Quotation Date</strong><div>${safe(info.quotation_date)}</div></div>
            <div class="col-6"><strong>Updated At</strong><div>${safe(info.updated_at)}</div></div>
          </div>
        </div>
      `;
                } catch (err) {
                    console.error(err);
                    document.getElementById('bncQuoteModalBody').innerHTML = `
        <div class="small">
          <div class="text-danger mb-2"><strong>Lookup failed</strong></div>
          <pre class="small text-white-50 mb-0" style="white-space:pre-wrap;">${safe(err.message)}</pre>
        </div>
      `;
                } finally {
                    quoteBusy = false;
                    setTimeout(restackModals, 50);
                }
            });

            // Safety on first load
            setTimeout(restackModals, 100);

        })();
    </script>



@endpush
