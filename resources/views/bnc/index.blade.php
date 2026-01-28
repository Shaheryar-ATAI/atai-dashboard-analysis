@extends('layouts.app')

@section('title', 'BNC Projects')

@push('head')
    {{-- DataTables (Bootstrap 5 build) --}}
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        /* BNC PAGE ONLY – kill the dark overlay completely */
        .modal-backdrop,
        .modal-backdrop.fade,
        .modal-backdrop.show {
            background-color: transparent !important;
            opacity: 0 !important;
        }

        /* Keep modals above content */
        .modal {
            z-index: 1065 !important;
        }

        /* Pretty text block for BNC YAML-style sections */
        .bnc-pre {
            white-space: pre-wrap;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.35;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        {{-- Header + Upload button --}}
{{--        <div class="d-flex justify-content-between align-items-center mb-3">--}}
{{--            <h1 class="h4 mb-0 text-white">BNC Projects</h1>--}}

{{--            @hasrole('admin')--}}
{{--            <button class="btn btn-sm btn-outline-light"--}}
{{--                    data-bs-toggle="modal"--}}
{{--                    data-bs-target="#bncUploadModal">--}}
{{--                <i class="bi bi-upload me-1"></i>--}}
{{--                Upload BNC Excel--}}
{{--            </button>--}}
{{--            @endhasrole--}}
{{--        </div>--}}

        {{-- Filters row --}}
        <div class="card mb-3 atai-card-dark">
            <div class="card-body py-2">
                <form id="bncFilters" class="row g-2 align-items-end">

                    {{-- Region filter only for admin+gm; sales see fixed region via backend --}}
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
                        <label class="form-label form-label-sm text-white-50 mb-1">Lead Status</label>
                        <select name="lead_qualified" id="bnc_lead_qualified" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Hot">Hot</option>
                            <option value="Warm">Warm</option>
                            <option value="Cold">Cold</option>
                            <option value="Unknown">Unknown</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm text-white-50 mb-1">Approached</label>
                        <select name="approached" id="bnc_approached" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label form-label-sm text-white-50 mb-1">Search</label>
                        <input type="text" name="q" id="bnc_q" class="form-control form-control-sm"
                               placeholder="Project / client / city">
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" id="bncApplyFilters" class="btn btn-sm btn-primary flex-grow-1">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <button type="button" id="bncResetFilters" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-light"
                            data-bs-toggle="modal" data-bs-target="#bncExportModal">
                        <i class="bi bi-download me-1"></i> Download PDF
                    </button>
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
{{--            <div class="col-md-2">--}}
{{--                <div class="card kpi-card">--}}
{{--                    <div class="card-body py-2">--}}
{{--                        <div class="text-white-50 small">Total Value (USD)</div>--}}
{{--                        <div id="kpi_total_value" class="fs-5 fw-semibold text-white">--}}
{{--                            {{ $kpis['total_value'] ?? '0' }}--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

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
                        <div class="text-white-50 small">Qualified (Hot/Warm)</div>
                        <div id="kpi_qualified" class="fs-5 fw-semibold text-white">
                            {{ number_format($kpis['qualified'] ?? 0) }}
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
{{--                        <th>Value (USD)</th>--}}
                        <th>Value (SAR)</th>
                        <th>Quotes</th>                {{-- compact button --}}
                        <th>Quoted Value (SAR)</th>
                        <th class="quotes-detail">Quotes Detail</th> {{-- hidden column for child row --}}
                        <th>Approached</th>
                        <th>Lead</th>
                        <th>Penetration %</th>
                        <th>Expected Close</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    {{-- filled by DataTables via AJAX --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: Project details / edit checkpoints --}}
    <div class="modal fade modal-atai" id="bncDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bnc_modal_title">BNC Project Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Left: BNC info --}}
                        <div class="col-md-6">
                            <h6 class="text-white-50 mb-2">Project Information</h6>
                            <dl class="row small mb-3" id="bnc_info_basic">
                                {{-- filled by JS --}}
                            </dl>

                            <hr class="border-secondary my-2">

                            <h6 class="text-white-50 mb-2">Overview Info</h6>
                            <div id="bnc_overview_block" class="bnc-pre small mb-3"></div>

                            <h6 class="text-white-50 mb-2">Parties (Consultant / Contractors / MEP)</h6>
                            <div id="bnc_parties_block" class="bnc-pre small mb-3"></div>

                            <h6 class="text-white-50 mb-2">Latest News</h6>
                            <div id="bnc_latest_block" class="bnc-pre small"></div>
                        </div>

                        {{-- Right: Checkpoints form --}}
                        <div class="col-md-6">
                            <h6 class="text-white-50 mb-2">ATAI Checkpoints</h6>
                            <form id="bncCheckpointForm">
                                @csrf
                                <input type="hidden" id="bnc_project_id">

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm">Approached</label>
                                        <select id="bnc_approached_input" name="approached"
                                                class="form-select form-select-sm">
                                            <option value="0">No</option>
                                            <option value="1">Yes</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm">Lead Status</label>
                                        <select id="bnc_lead_qualified_input" name="lead_qualified"
                                                class="form-select form-select-sm">
                                            <option value="Unknown">Unknown</option>
                                            <option value="Hot">Hot</option>
                                            <option value="Warm">Warm</option>
                                            <option value="Cold">Cold</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm">Penetration %</label>
                                        <input type="number" min="0" max="100"
                                               id="bnc_penetration_input"
                                               name="penetration_percent"
                                               class="form-control form-control-sm">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm">Expected Close Date</label>
                                        <input type="date" id="bnc_expected_input"
                                               name="expected_close_date"
                                               class="form-control form-control-sm">
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="bnc_boq_shared" name="boq_shared">
                                            <label class="form-check-label small" for="bnc_boq_shared">
                                                BOQ Shared
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="bnc_submittal_shared" name="submittal_shared">
                                            <label class="form-check-label small" for="bnc_submittal_shared">
                                                Submittal Shared
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="bnc_submittal_approved" name="submittal_approved">
                                            <label class="form-check-label small" for="bnc_submittal_approved">
                                                Submittal Approved
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Notes</label>
                                        <textarea id="bnc_notes_input" name="notes"
                                                  class="form-control form-control-sm" rows="3"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <small class="text-white-50" id="bnc_audit_info"></small>
                    <button type="button" class="btn btn-sm btn-primary" id="bncSaveCheckpointBtn">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Upload BNC Excel (admin only) --}}
    @hasrole('admin')
    <div class="modal fade modal-atai" id="bncUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload BNC Excel</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bncUploadForm"
                      method="POST"
                      action="{{ route('bnc.upload') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label form-label-sm">Region</label>
                            <select name="region" class="form-select form-select-sm" required>
                                <option value="">-- Select Region --</option>
                                <option value="Eastern">Eastern</option>
                                <option value="Central">Central</option>
                                <option value="Western">Western</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-label-sm">BNC Excel File</label>
                            <input type="file" name="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   accept=".csv" required>
                            <div class="form-text text-white-50 small">
                                Export from BNC website and upload here.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
{{--                        <button type="submit" class="btn btn-sm btn-primary">--}}
{{--                            <i class="bi bi-upload me-1"></i> Upload & Import--}}
{{--                        </button>--}}
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endhasrole



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
                                <label class="form-label form-label-sm">Lead Status</label>
                                <select name="lead_qualified" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="Hot">Hot</option>
                                    <option value="Warm">Warm</option>
                                    <option value="Cold">Cold</option>
                                    <option value="Unknown">Unknown</option>
                                </select>
                            </div>
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
                        <button type="submit" class="btn btn-sm btn-primary">
                            Download PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
    <script>
        (function () {
            const $ = window.jQuery;

            const getVal = (id) => {
                const el = document.getElementById(id);
                return el ? el.value : '';
            };
            const quotesCache = new Map();

            const fmtSAR = (n) => {
                const num = Number(n || 0);
                return num > 0 ? num.toLocaleString('en-US') : '—';
            };

            function quoteLabel(count){
                if (!count || count <= 0) return 'Not quoted';
                return count === 1 ? '1 quote' : `${count} quotes`;
            }
            const formatDate = (val) => {
                if (!val) return '';
                const s = String(val);
                const datePart = s.split('T')[0];
                if (datePart === '1970-01-01' || datePart === '1969-12-31') return '';
                return datePart;
            };

            // DataTable
            const table = $('#tblBncProjects').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 25,
                order: [[5, 'desc']], // Value (USD)
                ajax: {
                    url: @json(route('bnc.datatable')),
                    data: function (d) {
                        d.region         = getVal('bnc_region');
                        d.stage          = getVal('bnc_stage');
                        d.lead_qualified = getVal('bnc_lead_qualified');
                        d.approached     = getVal('bnc_approached');
                        d.q              = getVal('bnc_q');
                    }
                },
                columns: [
                    { data: 'DT_RowIndex',         name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'project_name',        name: 'project_name' },
                    { data: 'city',                name: 'city' },
                    { data: 'region',              name: 'region' },
                    { data: 'stage',               name: 'stage' },
                    // { data: 'value_usd',           name: 'value_usd',           className: 'text-end' },
                    { data: 'value_sar',           name: 'value_sar',           className: 'text-end', orderable: false, searchable: false },
                    { data: 'quoted_status',       name: 'quoted_status',       orderable: false, searchable: false },
                    { data: 'quoted_value_sar',    name: 'quoted_value_sar',    className: 'text-end', orderable: false, searchable: false },
                    { data: 'quotes_detail_html',  name: 'quotes_detail_html' }, // hidden via columnDefs
                    { data: 'approached',          name: 'approached',          orderable: false, searchable: false },
                    { data: 'lead_qualified',      name: 'lead_qualified' },
                    { data: 'penetration_percent', name: 'penetration_percent', className: 'text-center' },
                    { data: 'expected_close_date', name: 'expected_close_date' },
                    { data: 'actions',             name: 'actions',             orderable: false, searchable: false, className: 'text-end' },
                ],
                drawCallback: function () {
                    const api = this.api();

                    api.rows({ page: 'current' }).every(function () {
                        const d = this.data();
                        if (!d || !d.id) return;

                        const id = d.id;

                        // if cached, just paint
                        if (quotesCache.has(id)) {
                            paintRow(id, quotesCache.get(id));
                            return;
                        }

                        // fetch once per visible row
                        fetch(@json(url('/bnc')) + '/' + id + '/quotes')
                            .then(r => r.json())
                            .then(json => {
                                quotesCache.set(id, json);
                                paintRow(id, json);
                            })
                            .catch(() => {
                                // fail safe
                                paintRow(id, { count: 0, total: 0, coverage_pct: 0, html: '' });
                            });
                    });
                },
                columnDefs: [
                    {
                        targets: 'quotes-detail',
                        visible: false,
                        searchable: false
                    }
                ]
            });

            // Filters
            document.getElementById('bncApplyFilters').addEventListener('click', function () {
                table.ajax.reload();
            });

            document.getElementById('bncResetFilters').addEventListener('click', function () {
                document.getElementById('bncFilters').reset();
                table.ajax.reload();
            });

            // --- Quotes child row toggle ---
            $('#tblBncProjects tbody').on('click', '.bnc-quotes-toggle', function () {
                const tr  = $(this).closest('tr');
                const row = table.row(tr);
                const id  = this.dataset.bncId;

                const info = quotesCache.get(Number(id)) || quotesCache.get(id);

                // if not loaded yet, fetch then open
                const openRow = (json) => {
                    if (Number(json.count || 0) <= 0) return; // ✅ no dropdown for Not quoted

                    const html = json.html || '<div class="px-3 py-2 text-muted small">No quotations linked yet.</div>';

                    const $icon = $(this).find('i.bi');

                    if (row.child.isShown()) {
                        row.child.hide();
                        tr.removeClass('shown');
                        $icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                    } else {
                        row.child(html).show();
                        tr.addClass('shown');
                        $icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                    }
                };

                if (info) return openRow(info);

                fetch(@json(url('/bnc')) + '/' + id + '/quotes')
                    .then(r => r.json())
                    .then(json => {
                        quotesCache.set(Number(id), json);
                        paintRow(Number(id), json);
                        openRow(json);
                    });
            });

            function paintRow(id, info) {
                const count = Number(info?.count || 0);
                const total = Number(info?.total || 0);
                const cov   = Number(info?.coverage_pct || 0);

                // Update quoted total
                document.querySelectorAll(`.bnc-quoted-total[data-bnc-id="${id}"]`)
                    .forEach(el => el.textContent = count > 0 ? fmtSAR(total) : '—');

                // Update coverage badge
                document.querySelectorAll(`.bnc-coverage[data-bnc-id="${id}"]`)
                    .forEach(el => {
                        if (count <= 0) {
                            el.textContent = '—';
                            el.classList.remove('bg-success','bg-warning','bg-danger');
                            el.classList.add('bg-secondary');
                        } else {
                            el.textContent = `${cov}%`;
                            el.classList.remove('bg-secondary','bg-success','bg-warning','bg-danger');
                            el.classList.add(cov >= 70 ? 'bg-success' : (cov >= 30 ? 'bg-warning' : 'bg-danger'));
                        }
                    });

                // Update quotes button
                document.querySelectorAll(`.bnc-quotes-toggle[data-bnc-id="${id}"]`)
                    .forEach(btn => {
                        const labelEl = btn.querySelector('.bnc-quotes-label');
                        const iconEl  = btn.querySelector('i.bi');

                        if (count <= 0) {
                            btn.disabled = true;
                            btn.classList.remove('btn-outline-light');
                            btn.classList.add('btn-outline-danger');
                            labelEl.textContent = 'Not quoted';
                            iconEl?.classList.add('d-none');
                        } else {
                            btn.disabled = false;
                            btn.classList.remove('btn-outline-danger');
                            btn.classList.add('btn-outline-light');
                            labelEl.textContent = quoteLabel(count);
                            iconEl?.classList.remove('d-none');
                        }

                        // store payload on button for click handler
                        btn.dataset.loaded = '1';
                    });
            }

            // View button -> JSON -> modal
            $(document).on('click', '.btn-bnc-view', function () {
                const id = $(this).data('id');
                if (!id) return;

                fetch(@json(url('/bnc')) + '/' + id)
                    .then(res => res.json())
                    .then(data => {
                        fillBncModal(data);
                        const modal = new bootstrap.Modal(document.getElementById('bncDetailsModal'));
                        modal.show();
                    });
            });

            function fillBncModal(p) {
                const safe = (v) => (v === null || v === undefined) ? '' : String(v).trim();

                const fmtNum = (n) => Number(n || 0).toLocaleString('en-US');

                const formatDate = (val) => {
                    if (!val) return '';
                    const s = String(val);
                    const datePart = s.split('T')[0];
                    if (datePart === '1970-01-01' || datePart === '1969-12-31') return '';
                    return datePart;
                };

                const section = (title, lines) => {
                    const cleaned = (lines || []).map(safe).filter(Boolean);
                    if (!cleaned.length) return '';
                    return `${title}\n${cleaned.map(x => '• ' + x).join('\n')}\n`;
                };

                const partyBlock = (label, obj) => {
                    if (!obj || typeof obj !== 'object') return '';
                    const lines = [];
                    if (obj.name) lines.push(`Name: ${obj.name}`);
                    if (obj.key_contact) lines.push(`Key Contact: ${obj.key_contact}`);
                    if (obj.phone) lines.push(`Phone: ${obj.phone}`);
                    if (obj.email) lines.push(`Email: ${obj.email}`);
                    if (obj.award_date) lines.push(`Award Date: ${safe(obj.award_date)}`);
                    if (obj.award_value) lines.push(`Award Value: ${fmtNum(obj.award_value)}`);
                    return section(label, lines);
                };

                // Title
                document.getElementById('bnc_modal_title').textContent =
                    safe(p.project_name) + (p.reference_no ? ' (' + p.reference_no + ')' : '');

                // Basic info
                const basic = document.getElementById('bnc_info_basic');
                basic.innerHTML = `
        <dt class="col-sm-4">City</dt><dd class="col-sm-8">${safe(p.city)}</dd>
        <dt class="col-sm-4">Region</dt><dd class="col-sm-8">${safe(p.region)}</dd>
        <dt class="col-sm-4">Stage</dt><dd class="col-sm-8">${safe(p.stage)}</dd>
        <dt class="col-sm-4">Industry</dt><dd class="col-sm-8">${safe(p.industry)}</dd>
        <dt class="col-sm-4">Client/Owner</dt><dd class="col-sm-8">${safe(p.client)}</dd>
        <dt class="col-sm-4">Value (USD)</dt><dd class="col-sm-8">${fmtNum(p.value_usd)}</dd>
        <dt class="col-sm-4">Award Date</dt><dd class="col-sm-8">${formatDate(p.award_date)}</dd>
    `;

                // Overview Info (clean, consistent)
                const overviewLines = [
                    `Reference No: ${safe(p.reference_no)}`,
                    `Project: ${safe(p.project_name)}`,
                    `City: ${safe(p.city)}`,
                    `Region: ${safe(p.region)}`,
                    `Stage: ${safe(p.stage)}`,
                    `Industry: ${safe(p.industry)}`,
                    `Client/Owner: ${safe(p.client)}`,
                    // `Value (USD): ${fmtNum(p.value_usd)}`,
                    `Award Date: ${formatDate(p.award_date)}`,
                    `Datasets: ${safe(p.datasets)}`,
                ].filter(line => !line.endsWith(': '));

                document.getElementById('bnc_overview_block').textContent = overviewLines.join('\n');

                // Parties (prefer raw_parties, fallback to old)
                const rp = p.raw_parties || {};
                let partiesTxt = '';
                partiesTxt += partyBlock('Owners', rp.owners);
                partiesTxt += partyBlock('Lead/Infra/FEED/Design Consultants', rp.lead_consultant);
                partiesTxt += partyBlock('MEP Consultants', rp.mep_consultant);
                partiesTxt += partyBlock('MEP Contractors', rp.mep_contractor);
                partiesTxt += partyBlock('Main/Infra/EPC Contractors', rp.main_epc);

                if (!partiesTxt.trim()) {
                    partiesTxt = [
                        section('Client / Owner', [p.client]),
                        section('Consultant(s)', [p.consultant]),
                        section('Main Contractor(s)', [p.main_contractor]),
                        section('MEP Contractor(s)', [p.mep_contractor]),
                    ].join('\n').trim();
                }

                document.getElementById('bnc_parties_block').textContent =
                    partiesTxt.trim() || 'No party/contact details available.';

                // Latest news
                document.getElementById('bnc_latest_block').textContent =
                    safe(p.latest_news) || 'No project news/updates found in the latest import.';

                // Checkpoints form
                document.getElementById('bnc_project_id').value = p.id;
                document.getElementById('bnc_approached_input').value = p.approached ? '1' : '0';
                document.getElementById('bnc_lead_qualified_input').value = p.lead_qualified ?? 'Unknown';
                document.getElementById('bnc_penetration_input').value = p.penetration_percent ?? 0;
                document.getElementById('bnc_expected_input').value = p.expected_close_date ?? '';

                document.getElementById('bnc_boq_shared').checked = !!p.boq_shared;
                document.getElementById('bnc_submittal_shared').checked = !!p.submittal_shared;
                document.getElementById('bnc_submittal_approved').checked = !!p.submittal_approved;
                document.getElementById('bnc_notes_input').value = p.notes ?? '';

                // Audit
                let auditText = '';
                if (p.created_at) auditText += 'Created: ' + p.created_at;
                if (p.updated_at) auditText += (auditText ? ' | ' : '') + 'Last updated: ' + p.updated_at;
                document.getElementById('bnc_audit_info').textContent = auditText;
            }


            // Save checkpoints
            document.getElementById('bncSaveCheckpointBtn')?.addEventListener('click', function () {
                const form = document.getElementById('bncCheckpointForm');
                const id   = document.getElementById('bnc_project_id').value;
                if (!id) return;

                const formData = new FormData(form);

                fetch(@json(url('/bnc')) + '/' + id, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': formData.get('_token'),
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                    .then(res => res.json())
                    .then(() => {
                        alert('Saved');
                        table.ajax.reload(null, false);
                    });
            });
        })();

        // Global backdrop cleanup (same pattern as other pages)
        function cleanupBackdrops() {
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
        }

        document.addEventListener('shown.bs.modal', function () {
            cleanupBackdrops();
        });
    </script>
@endpush
