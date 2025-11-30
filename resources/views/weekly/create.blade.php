{{-- resources/views/weekly/create.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI — Weekly Sales Activities Report</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        .section-card {
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
        }

        .section-card .card-header {
            background: rgba(255, 255, 255, .06);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .table thead th {
            white-space: nowrap;
        }

        .text-slim {
            letter-spacing: .02em;
        }

        .row-error {
            outline: 2px solid #dc3545;
            outline-offset: -2px;
        }

        .thin-input {
            padding-top: .375rem;
            padding-bottom: .375rem;
        }

        .week-badge {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
        }


        /* Inputs inside the table: make them readable */
        #weeklyTbl input[type="text"],
        #weeklyTbl input[type="tel"],
        #weeklyTbl input[type="number"],
        #weeklyTbl input[type="date"],
        #weeklyTbl textarea,
        #weeklyTbl .form-control,
        #weeklyTbl .form-select {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
            height: 32px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 500;
        }

        #weeklyTbl .form-control:focus,
        #weeklyTbl .form-select:focus {
            outline: none;
            border-color: #0c7135 !important;
            box-shadow: 0 0 0 0.15rem rgba(12, 113, 53, .25);
        }

        #weeklyTbl input::placeholder,
        #weeklyTbl textarea::placeholder {
            color: #6b7280 !important;
            opacity: 1 !important;
        }

    </style>
</head>
<body>
@php
    /** @var \App\Models\User|null $u */
    $u   = auth()->user();
    $rid = session('report_id'); // set by controller after save
@endphp

<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2">
            <span class="brand-word">ATAI</span>
        </a>

        <div class="collapse navbar-collapse show">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('projects.index') }}">Quotation KPI</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('inquiries.index') }}">Quotation Log</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('salesorders.manager.kpi') }}">Sales Order Log
                        KPI</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('salesorders.manager.index') }}">Sales Order
                        Log</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('estimation.index') }}">Estimation</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('forecast.create') }}">Forecast</a></li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('weekly.*') ? 'active' : '' }}"
                       href="{{ route('weekly.create') }}">
                        Weekly Reports
                    </a>
                </li>
            </ul>

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

    <div class="glass-row d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h2 class="mb-2 mb-md-0 fw-bold text-light">Weekly Sales Activities Report</h2>
        <div class="text-secondary small">
            Tip: Paste from Excel using columns: Customer | Project | Location | Value | Status | Contact | Mobile |
            Visiting Date | Notes.
        </div>
    </div>

    {{-- Success alert after save --}}
    @if(session('ok') && $rid)
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            Your weekly report was saved successfully. You can now download the PDF.
            <a class="btn btn-sm btn-outline-success ms-2" target="_blank" href="{{ route('weekly.pdf', $rid) }}"
               id="pdfLink">
                <i class="bi bi-filetype-pdf me-1"></i> Download PDF
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form id="weeklyForm" class="section-card card" method="POST" action="{{ route('weekly.save') }}">
        @csrf

        <div class="card-header">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-5">
                    <label class="form-label small text-uppercase text-secondary mb-1">Sales Engineer Name</label>
                    <input name="engineer_name" class="form-control form-control-sm thin-input"
                           value="{{ $u->name ?? '' }}" placeholder="Type name…">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-uppercase text-secondary mb-1">Week Starting (Sun)</label>
                    <input type="date" name="week_date" id="weekDate"
                           class="form-control form-control-sm thin-input"
                           value="{{ old('week_date') }}" autocomplete="off">
                </div>

                <div class="col-12 col-md-4 text-md-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                        <span id="weekRange" class="badge week-badge text-secondary"></span>
                        <button type="button" id="addRow" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Row
                        </button>
                        <button type="button" id="add10" class="btn btn-outline-light btn-sm">+10</button>
                        <button type="button" id="clearAll" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="weeklyTbl">
                    <thead class="table-dark" style="--bs-table-bg: rgba(255,255,255,.06);">
                    <tr>
                        <th style="width:52px">No</th>
                        <th>Customer Name</th>
                        <th>Project NAME</th>
                        <th style="width:160px">Quotation #</th>
                        <th>Project Location</th>
                        <th style="width:130px">Value</th>
                        <th style="width:180px">Status of the project</th>
                        <th>Contact Name</th>
                        <th style="width:160px">Contact Mobile #</th>
                        <th style="width:170px">Visiting Date</th>
                        <th>Notes</th>
                        {{-- NEW: Delete column --}}
                        <th style="width:40px"></th>
                    </tr>
                    </thead>
                    <tbody id="rowsBody"></tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-secondary small text-slim">
                Rows: <span id="rowCount">0</span>
            </div>
            <div class="d-flex gap-2">
                @if($rid)
                    {{-- After save: show PDF --}}
                    <button type="button" class="btn btn-outline-info" id="printPdf" data-report-id="{{ $rid }}">
                        <i class="bi bi-filetype-pdf me-1"></i> Print / PDF
                    </button>
                @else
                    {{-- Before save: only Save --}}
                    <button type="submit" class="btn btn-success fw-semibold" id="saveBtn">
                        <i class="bi bi-save2 me-1"></i> Save Weekly Report
                    </button>
                @endif
            </div>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const body = document.getElementById('rowsBody');
        const rowCount = document.getElementById('rowCount');
        const addRowBtn = document.getElementById('addRow');
        const add10Btn = document.getElementById('add10');
        const clearBtn = document.getElementById('clearAll');
        const form = document.getElementById('weeklyForm');
        const saveBtn = document.getElementById('saveBtn');
        const pdfBtn = document.getElementById('printPdf');
        const weekDate = document.getElementById('weekDate');
        const weekRange = document.getElementById('weekRange');

// --- Quotation format (same as server) ---
        const QTN_RE = /^S\.\d{4}\.\d+\.\d{4}\.[A-Z]{2}\.R\d+$/i;

// mark input invalid/valid
        function markValid(el, ok) {
            el.classList.toggle('is-invalid', !ok);
            el.classList.toggle('is-valid', !!ok);
        }

// on input: uppercase, strip spaces, validate
        function wireQuotationValidation(container) {
            container.querySelectorAll('input.qtn').forEach((el) => {
                // initialize from existing value (e.g., after paste)
                const sanitize = () => {
                    const before = el.value;
                    // uppercase & remove all whitespace
                    el.value = before.toUpperCase().replace(/\s+/g, '');
                    // empty is OK (nullable), otherwise must match
                    const ok = (el.value === '' || QTN_RE.test(el.value));
                    markValid(el, ok);
                    el.setCustomValidity(ok ? '' : 'Quotation format must be like S.4135.1.2605.MH.R0');
                };
                el.addEventListener('input', sanitize);
                sanitize();
            });
        }

// call once after first 10 seed rows
        wireQuotationValidation(document);

// re-wire after rows are added or after paste
        const _addRowsOrig = addRows;
        addRows = function (n) {
            _addRowsOrig(n);
            wireQuotationValidation(document);
        };

// also re-validate after renumber (safe)
        const _renumberOrig = renumber;
        renumber = function () {
            _renumberOrig();
            wireQuotationValidation(document);
        };

// block submit if any invalid quotation present
        form?.addEventListener('submit', function (e) {
            let bad = false;
            document.querySelectorAll('input.qtn').forEach((el) => {
                // trigger validation one last time
                el.dispatchEvent(new Event('input', {bubbles: false}));
                if (!el.checkValidity()) bad = true;
            });
            if (bad) {
                e.preventDefault();
                e.stopPropagation();
                // focus the first bad one
                const firstBad = document.querySelector('input.qtn.is-invalid');
                if (firstBad) firstBad.focus();
            }
        });

        // Normalize old non-ISO dates if any
        if (weekDate.value && weekDate.value.includes('/')) weekDate.value = '';

        // Local helpers (avoid timezone shifts)
        const toYMD = (d) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const da = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${da}`;
        };
        const startOfWeekSundayLocal = (d) => {
            const t = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            t.setDate(t.getDate() - t.getDay()); // 0=Sun
            return t;
        };
        const endOfThu = (sun) => {
            const d = new Date(sun);
            d.setDate(d.getDate() + 4);
            return d;
        };
        const fmt = (d) => d.toLocaleDateString('en-SA', {day: '2-digit', month: 'short', year: 'numeric'});

        function updateWeekRangeAndSnap() {
            let chosen = weekDate.valueAsDate || new Date();
            const sun = startOfWeekSundayLocal(chosen);
            weekDate.value = toYMD(sun);
            const thu = endOfThu(sun);
            weekRange.textContent = `Week: ${fmt(sun)} — ${fmt(thu)} (Sun–Thu)`;
        }

        updateWeekRangeAndSnap();
        weekDate.addEventListener('change', updateWeekRangeAndSnap);

        const STATUS = ['Inquiry', 'Quoted', 'Follow-up', 'Negotiation', 'In-Hand', 'Lost', 'On Hold', 'Postponed', 'Closed'];

        function statusSelect(name) {
            // First option: empty value, human text “Select Status”
            let opts = '<option value="">Select Status</option>';
            for (const v of STATUS) opts += `<option value="${v}">${v}</option>`;
            return `<select name="${name}" class="form-select form-select-sm thin-input">${opts}</select>`;
        }

        function makeRow(i) {
            const base = 'rows[' + i + ']';
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="serial text-secondary fw-semibold">' + (i + 1) + '</td>' +
                '<td><input name="' + base + '[customer]" class="form-control form-control-sm thin-input" placeholder="Customer"></td>' +
                '<td><input name="' + base + '[project]" class="form-control form-control-sm thin-input" placeholder="Project"></td>' +
                '<td><input name="' + base + '[quotation_no]"class="form-control form-control-sm thin-input qtn" placeholder="S.0000.0.0000.XX.R0"  maxlength="64" inputmode="text" pattern="^S\\.\\d{4}\\.\\d+\\.\\d{4}\\.[A-Z]{2}\\.R\\d+$" title="Format: S.&lt;num&gt;.&lt;num&gt;.&lt;num&gt;.MH.R&lt;num&gt; e.g., S.4135.1.2605.MH.R0"></td>' +
                '<td><input name="' + base + '[location]" class="form-control form-control-sm thin-input" placeholder="Location"></td>' +
                '<td><input name="' + base + '[value]" class="form-control form-control-sm thin-input text-end" placeholder="SAR 0"></td>' +
                '<td>' + statusSelect(base + '[status]') + '</td>' +
                '<td><input name="' + base + '[contact_name]" class="form-control form-control-sm thin-input" placeholder="Contact"></td>' +
                '<td><input name="' + base + '[contact_mobile]" type="tel" class="form-control form-control-sm thin-input phone-field" placeholder="+966-5XXXXXXXX" inputmode="numeric" maxlength="16"></td>' +
                '<td><input type="date" name="' + base + '[visit_date]" class="form-control form-control-sm thin-input"></td>' +
                '<td><input name="' + base + '[notes]" class="form-control form-control-sm thin-input" placeholder="Notes"></td>' +
                // NEW: delete button
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger del" title="Delete row"><i class="bi bi-x-lg"></i></button></td>';
            return tr;
        }

        function renumber() {
            const trs = body.children;
            for (let i = 0; i < trs.length; i++) {
                trs[i].querySelector('.serial').textContent = i + 1;
                trs[i].querySelectorAll('input, select, textarea').forEach(function (el) {
                    // replace rows[OLD] with rows[i]
                    el.name = el.name.replace(/rows\[\d+]/, 'rows[' + i + ']');
                });
            }
            rowCount.textContent = trs.length;
        }

        function addRows(n) {
            const start = body.children.length;
            for (let k = 0; k < n; k++) {
                body.appendChild(makeRow(start + k));
            }
            renumber();
        }

        // Paste from Excel/Sheets
        body.addEventListener('paste', function (e) {
            const t = e.target;
            if (!t || !['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName)) return;
            const txt = (e.clipboardData || window.clipboardData).getData('text');
            if (!txt || !txt.includes('\t')) return;
            e.preventDefault();

            const lines = txt.split(/\r?\n/).filter(r => r.trim().length);
            if (!lines.length) return;

            const startIdx = Array.prototype.indexOf.call(body.children, t.closest('tr'));
            const need = Math.max(0, startIdx + lines.length - body.children.length);
            if (need > 0) addRows(need); // also renumbers

            lines.forEach(function (line, r) {
                const cols = line.split('\t');
                const tr = body.children[startIdx + r];
                if (!tr) return;
                const q = sel => tr.querySelector(sel);
                q('input[name*="[customer]"]').value = cols[0] || '';
                q('input[name*="[project]"]').value = cols[1] || '';
                q('input[name*="[quotation_no]"]').value = cols[2] || '';     // <-- NEW
                q('input[name*="[location]"]').value = cols[3] || '';
                q('input[name*="[value]"]').value = cols[4] || '';
                const st = q('select[name*="[status]"]');
                if (st && cols[5] !== undefined) st.value = cols[5];
                q('input[name*="[contact_name]"]').value = cols[6] || '';
                q('input[name*="[contact_mobile]"]').value = cols[7] || '';
                if (cols[8]) {
                    const cleaned = cols[8].split('.').join('-');
                    q('input[name*="[visit_date]"]').value = cleaned;
                }
                q('input[name*="[notes]"]').value = cols[9] || '';
            });

            renumber();
        });

        addRowBtn?.addEventListener('click', function () {
            addRows(1);
        });
        add10Btn?.addEventListener('click', function () {
            addRows(10);
        });
        clearBtn?.addEventListener('click', function () {
            body.innerHTML = '';
            renumber();
        });

        // Delete row (delegated)
        body.addEventListener('click', function (e) {
            const btn = e.target.closest('.del');
            if (!btn) return;
            btn.closest('tr').remove();
            renumber();
        });

        // Seed 10 rows initially
        addRows(10);

        // Prevent Enter submit
        form?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Save: prevent double submit
        form?.addEventListener('submit', function () {
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
            }
        });

        // PDF open + optional auto-reload (like Forecast)
        let shouldReloadAfterPdf = false;
        if (pdfBtn && !pdfBtn.dataset.reportId) {
            pdfBtn.disabled = true;
        }
        pdfBtn?.addEventListener('click', function () {
            const id = this.dataset.reportId;
            if (!id) return;
            shouldReloadAfterPdf = true;
            window.open(`/weekly/${id}/pdf`, '_blank');
            // Fallback in case focus doesn't come back (pop-up blockers, etc.)
            setTimeout(() => {
                if (shouldReloadAfterPdf) location.reload();
            }, 6000);
        });
        window.addEventListener('focus', () => {
            if (shouldReloadAfterPdf) {
                shouldReloadAfterPdf = false;
                location.reload();
            }
        });
    });
</script>
</body>
</html>
