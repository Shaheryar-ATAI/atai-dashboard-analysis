{{-- resources/views/forecast/create.blade.php --}}
@extends('layouts.app')

@section('title', 'ATAI Projects — Live')
@push('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI — Monthly Sales Forecast</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ time() }}">
    <style>
        .section-card {
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
        }

        .section-card .card-header {
            background: rgba(255, 255, 255, .06);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .section-card .card-body {
            padding: 1rem 1rem;
        }

        .glass-row {
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
            padding: .75rem 1rem;
            background: rgba(255, 255, 255, .04);
        }

        .glass-panel {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .08);
        }

        .kpi-card {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
        }

        .kpi-card .kpi-value {
            font-weight: 700;
            font-size: clamp(1.1rem, 1.8vw, 1.5rem);
        }

        table.table tfoot th, table.table tfoot td {
            background: rgba(255, 255, 255, .03);
        }

        .table thead th {
            white-space: nowrap;
        }

        .row-error {
            outline: 2px solid #dc3545;
            outline-offset: -2px;
        }

        td.pf-cell:empty::before {
            content: '';
        }

        /* Make sure the select actually renders and is visible */
        td.pf-cell .pf-wrap {
            min-width: 150px;
        }

        td.pf-cell select.form-select {
            display: block;
            width: 100%;
            min-height: calc(1.5em + .5rem + 2px);
            appearance: auto; /* override any custom appearance */
            background-color: inherit; /* don’t blend into bg as “invisible” */
            color: inherit;
        }

        /* Header: keep a dark band with readable text */
        #tblA thead, #tblB thead {
            --bs-table-bg: #1f2636; /* dark slate */
            --bs-table-color: #eaf2ff; /* light text */
            background-color: var(--bs-table-bg) !important;
            color: var(--bs-table-color) !important;
            border-bottom: 1px solid rgba(255, 255, 255, .15);
        }

        #tblA thead th, #tblB thead th {
            background-color: #1f2636 !important; /* use the thead color */
            color: inherit !important;
            border-color: rgba(255, 255, 255, .18);
        }

        /* Body: stay white with visible borders (from previous step) */
        #tblA, #tblB {
            --bs-table-bg: #ffffff;
            background-color: #ffffff;
            color: #111;
        }

        #tblA > :not(caption) > * > *,
        #tblB > :not(caption) > * > * {
            background-color: #ffffff !important;
            border-color: rgba(0, 0, 0, .12);
        }

        /* Inputs/selects inside cells should be readable on white */
        #tblA input.form-control, #tblA select.form-select,
        #tblB input.form-control, #tblB select.form-select {
            background-color: #ffffff !important;
            color: #111 !important;
            border-color: rgba(0, 0, 0, .18);
        }

        /* Make placeholders (e.g., '%' in the % column) visible */
        #tblA input::placeholder, #tblB input::placeholder {
            color: #6b7280; /* gray-500 */
            opacity: 1;
        }

        /* % column: keep narrow, centered, and legible */
        #tblA td .pct, #tblB td .pct {
            text-align: center;
            padding-right: .5rem;
            font-weight: 600;
        }

        /* Make totals/footer row match the white body */
        #tblA tfoot th, #tblA tfoot td,
        #tblB tfoot th, #tblB tfoot td {
            background-color: #ffffff !important;
            color: #111 !important;
            border-top: 2px solid rgba(0, 0, 0, .15); /* visible separator */
        }

        /* (optional) subtle gray footer instead of white */
        /*
        #tblA tfoot th, #tblA tfoot td,
        #tblB tfoot th, #tblB tfoot td {
          background-color: #f7f7f8 !important;
          color: #111 !important;
          border-top: 2px solid rgba(0,0,0,.1);
        }
        */

        /* (optional) keep the footer “sticky” while scrolling inside the table */
        #tblA tfoot, #tblB tfoot {
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

    </style>
@endpush
@section('content')

    @php $u = auth()->user(); @endphp


    {{-- Toast (bottom-right) --}}
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="forecastToast" class="toast align-items-center text-bg-danger border-0" role="alert"
             aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">Validation failed.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
            </div>
        </div>
    </div>

    <main class="container-fluid py-4 forecast-page">
        <div class="glass-row d-flex flex-wrap align-items-center justify-content-between mb-3">
            <h2 class="mb-2 mb-md-0 fw-bold text-light">Monthly Sales Forecast & Sales Order Targets</h2>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-uppercase text-secondary mb-0">Submission Date</label>
                <input type="date" class="form-control form-control-sm bg-dark text-light border-0"
                       name="__submission_stub" disabled value="{{ now()->toDateString() }}">
            </div>
        </div>

        <div class="row g-3 mb-4 text-center justify-content-start">
            <div class="col-6 col-md-6">
                <div class="kpi-card p-4">
                    <div class="kpi-label text-secondary small text-uppercase">Region</div>
                    <div id="kpiRegion" class="kpi-value">{{ $u->region ?? '—' }}</div>
                </div>
            </div>
            <div class="col-6 col-md-6">
                <div class="kpi-card p-4">
                    <div class="kpi-label text-secondary small text-uppercase">This Month Total</div>
                    <div id="kpiTotal" class="kpi-value">SAR 0</div>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger glass-panel p-3">
                <strong>Fix the following:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="forecastForm" method="post" action="{{ route('forecast.save') }}">
            @csrf

            {{-- Hidden fields synced for controller --}}
            <input type="hidden" name="year" id="year_hidden" value="{{ now()->year }}">
            <input type="hidden" name="month_no" id="month_no">
            <input type="hidden" name="month" id="month_full">

            <div class="row g-3 mb-3">
                {{-- Sales Data --}}
                <div class="col-12 col-xl-4">
                    <div class="card section-card h-100">
                        <div class="card-header"><strong>Sales Data</strong></div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Region</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach (['Eastern','Central','Western'] as $r)
                                        <input class="btn-check" type="radio" name="region" id="region-{{ $r }}"
                                               value="{{ $r }}" {{ (auth()->user()->region ?? '')===$r ? 'checked':'' }}>
                                        <label class="btn btn-sm btn-outline-light rounded-pill px-3"
                                               for="region-{{ $r }}">{{ $r }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small text-uppercase">Month</label>
                                    <select disabled id="monthSelect" class="form-select form-select-sm">
                                        @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $k=>$v)
                                            <option disabled
                                                    value="{{ $k }}" {{ $k===now()->month ? 'selected':'' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-uppercase">Year</label>
                                    <select disabled name="year" id="yearSelect" class="form-select form-select-sm">
                                        @php $y=now()->year; @endphp
                                        @for($i=$y-1;$i<=$y+2;$i++)
                                            <option value="{{ $i }}" {{ $i===$y?'selected':'' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            {{-- No Save/PDF here (avoid duplicate buttons/IDs) --}}
                        </div>
                    </div>
                </div>

                {{-- Forecasting Criteria --}}
                <div class="col-12 col-xl-4">
                    <div class="card section-card h-100">
                        <div class="card-header"><strong>Forecasting Criteria</strong></div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Price Agreed</label>
                                <input name="price_agreed" class="form-control form-control-sm"
                                       placeholder="Yes / No / %">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Consultant Approval</label>
                                <input name="consultant_approval" class="form-control form-control-sm"
                                       placeholder="Approved / Pending">
                            </div>
                            <div class="">
                                <label class="form-label small text-uppercase">Overall Percentage (Optional)</label>
                                <input name="percentage" class="form-control form-control-sm" placeholder="e.g. 60%">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Forecast Data --}}
                <div class="col-12 col-xl-4">
                    <div class="card section-card h-100">
                        <div class="card-header"><strong>Forecast Data</strong></div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Month Target</label>
                                <input name="month_target" class="form-control form-control-sm" placeholder="SAR 0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Required Turn-over</label>
                                <input name="required_turnover" class="form-control form-control-sm"
                                       placeholder="SAR 0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-uppercase">Required Forecast</label>
                                <input name="required_forecast" class="form-control form-control-sm"
                                       placeholder="SAR 0">
                            </div>
                            <div class="">
                                <label class="form-label small text-uppercase">Conversion Ratio</label>
                                <input name="conversion_ratio" class="form-control form-control-sm"
                                       placeholder="e.g. 20%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- A) New Orders --}}
            <div class="card section-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>A) New Orders Expected This Month</strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-light" type="button" id="addRowA">+ Row</button>
                        <button class="btn btn-sm btn-outline-light" type="button" id="add10RowA">+10</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tblA">
                            <thead class="table-dark" style="--bs-table-bg: rgba(255,255,255,.06);">
                            <tr>
                                <th style="width:44px;">#</th>
                                <th>Customer Name</th>
                                <th>Products</th>
                                <th>Project Name</th>
                                <th style="width:160px;">Quotation No.</th>
                                <th style="width:110px;" class="text-center">% (optional)</th>
                                <th class="text-end" style="width:140px;">Value (SAR)</th>
                                <th style="width:160px;">Product Family</th>
                                <th style="width:220px;">Remarks</th>
                                <th style="width:40px;"></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                            <tr>
                                <th></th>
                                <th colspan="4" class="text-end text-uppercase small opacity-75">Total New Current Month
                                    Forecasted Orders
                                </th>
                                <th class="text-end">
                                    <span id="totalA" class="fw-bold">SAR 0</span>
                                    <input type="hidden" name="totals[a]" id="totalAHidden" value="0">
                                </th>
                                <th colspan="3"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- B) Carry-Over --}}
            <div class="card section-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>B) Carry-Over (from the previous month and expected to close in the current month)</strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-light" type="button" id="addRowB">+ Row</button>
                        <button class="btn btn-sm btn-outline-light" type="button" id="add5RowB">+5</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="tblB">
                            <thead class="table-dark" style="--bs-table-bg: rgba(255,255,255,.06);">
                            <tr>
                                <th style="width:44px;">#</th>
                                <th>Customer Name</th>
                                <th>Products</th>
                                <th>Project Name</th>
                                <th style="width:160px;">Quotation No.</th>
                                <th style="width:110px;" class="text-center">% (≥75)</th>
                                <th class="text-end" style="width:140px;">Value (SAR)</th>
                                <th style="width:160px;">Product Family</th>
                                <th style="width:220px;">Remarks</th>
                                <th style="width:40px;"></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                            <tr>
                                <th></th>
                                <th colspan="4" class="text-end text-uppercase small opacity-75">Total Carry-over</th>
                                <th class="text-end">
                                    <span id="totalB" class="fw-bold">SAR 0</span>
                                    <input type="hidden" name="totals[b]" id="totalBHidden" value="0">
                                </th>
                                <th colspan="3"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-5">
                <div class="small text-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    Tip: paste from Excel directly into the table (Customer | Products | Project | % | Value | Family |
                    Remarks).
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success fw-semibold js-save" type="button">
                        <i class="bi bi-save2 me-1"></i> Save
                    </button>
                    <button class="btn btn-outline-info d-none js-pdf" type="submit"
                            formmethod="POST" formaction="{{ route('forecast.pdf') }}" formtarget="_blank"
                            formnovalidate>
                        <i class="bi bi-filetype-pdf me-1"></i> Generate PDF
                    </button>
                </div>
            </div>
        </form>
    </main>
@endsection


@push('scripts')

    <script>
        (function () {
            // ---------- Month sync ----------
            const monthMap = {
                1: 'January',
                2: 'February',
                3: 'March',
                4: 'April',
                5: 'May',
                6: 'June',
                7: 'July',
                8: 'August',
                9: 'September',
                10: 'October',
                11: 'November',
                12: 'December'
            };
            const monthSelect = document.getElementById('monthSelect');
            const monthNoHidden = document.getElementById('month_no');
            const monthFullHidden = document.getElementById('month_full');

            function syncMonthFields() {
                if (!monthNoHidden || !monthFullHidden) return;
                const m = monthSelect ? (parseInt(monthSelect.value, 10) || (new Date().getMonth() + 1)) : (new Date().getMonth() + 1);
                monthNoHidden.value = String(m);
                monthFullHidden.value = monthMap[m] || '';
            }

            if (monthSelect) monthSelect.addEventListener('change', syncMonthFields);
            syncMonthFields();

            const yearSelect = document.getElementById('yearSelect');
            const yearHidden = document.getElementById('year_hidden');

            function syncYearField() {
                if (!yearHidden) return;
                const y = yearSelect ? (parseInt(yearSelect.value, 10) || (new Date().getFullYear())) : (new Date().getFullYear());
                yearHidden.value = String(y);
            }

            if (yearSelect) yearSelect.addEventListener('change', syncYearField);
            syncYearField();

            // ---------- Tables / totals ----------
            const tblA = document.querySelector('#tblA tbody');
            const tblB = document.querySelector('#tblB tbody');
            const totalA = document.getElementById('totalA');
            const totalB = document.getElementById('totalB');
            const totalAHidden = document.getElementById('totalAHidden');
            const totalBHidden = document.getElementById('totalBHidden');
            const kpiTotal = document.getElementById('kpiTotal');

            function fmt(n) {
                n = Number(String(n).replace(/[^\d.-]/g, '')) || 0;
                return 'SAR ' + n.toLocaleString();
            }

            function parseN(v) {
                v = String(v || '').replace(/[^\d.-]/g, '');
                const n = Number(v);
                return isFinite(n) ? n : 0;
            }

            function familySelect(name) {
                return `
      <div class="pf-wrap">
        <select name="${name}" class="form-select form-select-sm text-center w-100" style="background:#f0f3f7!important;color:#000!important;border:1px solid #e1e3e5!important">
          <option value="">Select Product</option>
          <option value="Ductwork">Ductwork</option>
          <option value="Dampers">Dampers</option>
          <option value="Sound">Sound</option>
          <option value="Accessories">Accessories</option>
        </select>
      </div>`;
            }

            function makeRow(section, idx) {
                const key = section === 'A' ? 'new_orders' : 'carry_over';
                const tr = document.createElement('tr');
                tr.innerHTML = `
      <td class="serial text-secondary">${idx + 1}</td>
      <td><input name="${key}[${idx}][customer_name]" class="form-control form-control-sm"></td>
      <td><input name="${key}[${idx}][products]" class="form-control form-control-sm"></td>
      <td><input name="${key}[${idx}][project_name]" class="form-control form-control-sm"></td>
      <td>
        <input name="${key}[${idx}][quotation_no]" class="form-control form-control-sm qtn" placeholder="S.0000.0.0000.XX.R0">
      </td>
      <td class="text-center" style="max-width:110px;">
        <input name="${key}[${idx}][percentage]" type="number" min="0" max="100" class="form-control form-control-sm text-center pct" placeholder="%">
      </td>
      <td class="text-end"><input name="${key}[${idx}][value_sar]" class="form-control form-control-sm text-end value"></td>
      <td class="pf-cell">${familySelect(`${key}[${idx}][product_family]`)}</td>
      <td><input name="${key}[${idx}][remarks]" class="form-control form-control-sm"></td>
      <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger del"><i class="bi bi-x-lg"></i></button></td>`;
                return tr;
            }

            function addRows(tbody, section, count) {
                const start = tbody.children.length;
                for (let i = 0; i < count; i++) tbody.appendChild(makeRow(section, start + i));
                reindexTableNames(tbody, section);
                wireQuotationValidation(tbody);
                recalc();
            }

            function recalc() {
                let sumA = 0, sumB = 0;
                if (tblA) tblA.querySelectorAll('.value').forEach(inp => sumA += parseN(inp.value));
                if (tblB) tblB.querySelectorAll('.value').forEach(inp => sumB += parseN(inp.value));
                if (totalA) totalA.textContent = fmt(sumA);
                if (totalB) totalB.textContent = fmt(sumB);
                if (totalAHidden) totalAHidden.value = Math.round(sumA);
                if (totalBHidden) totalBHidden.value = Math.round(sumB);
                if (kpiTotal) kpiTotal.textContent = fmt(sumA + sumB);
            }

            function bindTable(tbody, section) {
                if (!tbody) return;

                // delete row
                tbody.addEventListener('click', e => {
                    const btn = e.target.closest('.del');
                    if (!btn) return;
                    btn.closest('tr').remove();
                    reindexTableNames(tbody, section);
                    recalc();
                });

                // recalc on value change
                const recalcIfValue = (e) => {
                    if (e.target && e.target.classList && e.target.classList.contains('value')) recalc();
                };
                tbody.addEventListener('input', recalcIfValue);
                tbody.addEventListener('change', recalcIfValue);
                tbody.addEventListener('paste', recalcIfValue);

                // format currency on blur
                tbody.addEventListener('blur', e => {
                    if (!e.target || !e.target.classList || !e.target.classList.contains('value')) return;
                    const raw = String(e.target.value).replace(/[^\d.-]/g, '');
                    const n = Number(raw);
                    e.target.value = Number.isFinite(n) ? n.toLocaleString() : '';
                    recalc();
                }, true);
            }

            // Buttons
            const addRowA = document.getElementById('addRowA');
            const add10RowA = document.getElementById('add10RowA');
            const addRowB = document.getElementById('addRowB');
            const add5RowB = document.getElementById('add5RowB');

            if (addRowA) addRowA.addEventListener('click', () => addRows(tblA, 'A', 1));
            if (add10RowA) add10RowA.addEventListener('click', () => addRows(tblA, 'A', 10));
            if (addRowB) addRowB.addEventListener('click', () => addRows(tblB, 'B', 1));
            if (add5RowB) add5RowB.addEventListener('click', () => addRows(tblB, 'B', 5));

            bindTable(tblA, 'A');
            bindTable(tblB, 'B');
            if (tblA && tblA.children.length === 0) addRows(tblA, 'A', 10);
            if (tblB && tblB.children.length === 0) addRows(tblB, 'B', 5);

            wireQuotationValidation(tblA);
            wireQuotationValidation(tblB);
            recalc();

            // ---------- Toast + Save/PDF gating ----------
            const form = document.getElementById('forecastForm');
            const pdfBtns = document.querySelectorAll('.js-pdf');
            const saveBtns = document.querySelectorAll('.js-save');
            const toastEl = document.getElementById('forecastToast');
            const toastBody = document.getElementById('toastBody');
            let shouldReloadAfterPdf = false;

            const toast = (window.bootstrap && toastEl)
                ? new bootstrap.Toast(toastEl, {autohide: true, delay: 1000})
                : null;

            function enablePdfButtons(enable) {
                pdfBtns.forEach(btn => btn.classList.toggle('d-none', !enable));
                saveBtns.forEach(btn => btn.classList.toggle('d-none', !!enable));
            }

            // If any quotation is invalid on load, just disable PDF (don’t exit the script)
            if (document.querySelector('input.qtn.is-invalid')) {
                enablePdfButtons(false);
            }

            pdfBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (form && !form.reportValidity()) {
                        shouldReloadAfterPdf = false;
                        e.preventDefault();
                        return;
                    }
                    shouldReloadAfterPdf = true;
                    setTimeout(() => {
                        if (shouldReloadAfterPdf) window.location.reload();
                    }, 5000);
                });
            });

            window.addEventListener('focus', () => {
                if (shouldReloadAfterPdf) {
                    shouldReloadAfterPdf = false;
                    window.location.reload();
                }
            });

            function clearRowErrors() {
                document.querySelectorAll('input.is-invalid, select.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                    el.removeAttribute('title');
                });
                document.querySelectorAll('tr.row-error').forEach(tr => tr.classList.remove('row-error'));
            }

            function showIssues(issues) {
                if (!toast || !toastBody) {
                    alert('Validation failed:\n' + JSON.stringify(issues, null, 2));
                    return;
                }
                const list = (issues || []).map(it => {
                    const niceSection = it.section === 'new' ? 'New' : 'Carry';
                    const rowNo = (it.index + 1);
                    const msgs = (it.messages || []).map(m => `<div>• ${m}</div>`).join('');
                    return `<div class="mb-2"><strong>${niceSection} Row ${rowNo}</strong>${msgs}</div>`;
                }).join('');
                toastBody.innerHTML = `<div class="fw-semibold mb-1">Please fix the following:</div>${list || 'Invalid inputs.'}`;
                toastEl.classList.remove('text-bg-success');
                toastEl.classList.add('text-bg-danger');
                toast.show();
            }

            function hasAnyData(tbody) {
                if (!tbody) return false;
                return [...tbody.querySelectorAll('input, select')].some(el => {
                    const v = (el.value ?? '').toString().trim();
                    return v !== '' && v !== 'Select Product';
                });
            }

            async function handleSaveClick() {
                clearRowErrors();

                if (!hasAnyData(tblA) && !hasAnyData(tblB)) {
                    showIssues([{section: 'new', index: 0, messages: ['Please add at least one row before saving.']}]);
                    enablePdfButtons(false);
                    return;
                }

                reindexTableNames(tblA, 'A');
                reindexTableNames(tblB, 'B');
                const fd = new FormData(form);

                const res = await fetch("{{ route('forecast.save') }}", {
                    method: 'POST',
                    credentials: 'same-origin',                 // ✅ include session cookie
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: fd
                });

                const contentType = res.headers.get('content-type') || '';

                if (res.status === 422) {
                    let data = {};
                    try {
                        data = contentType.includes('application/json') ? await res.json() : {};
                    } catch (e) {
                    }
                    const issues = data.issues || data.errors || [];
                    showIssues(Array.isArray(issues) ? issues : []);
                    enablePdfButtons(false);
                    return;
                }

                if (!res.ok) {
                    let msg = `Save failed (${res.status}). `;
                    try {
                        if (contentType.includes('application/json')) {
                            const j = await res.json();
                            if (j.message) msg += j.message;
                        } else {
                            const html = await res.text();
                            msg += String(html).replace(/<[^>]+>/g, ' ').trim().slice(0, 400);
                        }
                    } catch (_) {
                    }
                    if (toast && toastBody) {
                        toastBody.textContent = msg;
                        toastEl.classList.remove('text-bg-success');
                        toastEl.classList.add('text-bg-danger');
                        toast.show();
                    } else {
                        alert(msg);
                    }
                    enablePdfButtons(false);
                    return;
                }

                let data = {};
                try {
                    data = contentType.includes('application/json') ? await res.json() : {};
                } catch (e) {
                }

                if (data.ok) {
                    enablePdfButtons(true);
                    if (toast && toastBody) {
                        toastBody.innerHTML = '<div class="fw-semibold">Saved successfully. You can now generate the PDF.</div>';
                        toastEl.classList.remove('text-bg-danger');
                        toastEl.classList.add('text-bg-success');
                        toast.show();
                    } else {
                        alert('Saved. You can now generate the PDF.');
                    }
                } else {
                    if (toast && toastBody) {
                        toastBody.textContent = 'Save failed. Please try again.';
                        toastEl.classList.remove('text-bg-success');
                        toastEl.classList.add('text-bg-danger');
                        toast.show();
                    } else {
                        alert('Save failed. Please try again.');
                    }
                    enablePdfButtons(false);
                }
            }

            document.querySelectorAll('.js-save').forEach(btn => btn.addEventListener('click', handleSaveClick));

            // Prevent Enter from submitting the form
            const formEl = document.getElementById('forecastForm');
            if (formEl) {
                formEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            }
        })(); // end IIFE

        // -------- Helpers outside IIFE (names & quotation validator) --------
        function setRowNames(tr, key, i) {
            const map = {
                customer: `input[name*="[customer_name]"]`,
                products: `input[name*="[products]"]`,
                project: `input[name*="[project_name]"]`,
                quote: `input[name*="[quotation_no]"]`,
                pct: `input[name*="[percentage]"]`,
                value: `input[name*="[value_sar]"]`,
                family: `select[name*="[product_family]"]`,
                remarks: `input[name*="[remarks]"]`,
            };
            const sel = (q) => tr.querySelector(q);
            sel(map.customer)?.setAttribute('name', `${key}[${i}][customer_name]`);
            sel(map.products)?.setAttribute('name', `${key}[${i}][products]`);
            sel(map.project)?.setAttribute('name', `${key}[${i}][project_name]`);
            sel(map.quote)?.setAttribute('name', `${key}[${i}][quotation_no]`);
            sel(map.pct)?.setAttribute('name', `${key}[${i}][percentage]`);
            sel(map.value)?.setAttribute('name', `${key}[${i}][value_sar]`);
            sel(map.family)?.setAttribute('name', `${key}[${i}][product_family]`);
            sel(map.remarks)?.setAttribute('name', `${key}[${i}][remarks]`);
        }

        function reindexTableNames(tbody, section) {
            if (!tbody) return;
            const key = section === 'A' ? 'new_orders' : 'carry_over';
            [...tbody.children].forEach((tr, i) => {
                tr.querySelector('.serial').textContent = i + 1;
                setRowNames(tr, key, i);
            });
        }

        // Quotation format: S.4135.1.2605.MH.R0 → S.0000.0.0000.XX.R0
        const QTN_RE = /^[A-Z]\.\d{4}\.\d\.\d{4}\.[A-Z]{2}\.R\d+$/;

        function markValid(el, ok) {
            el.classList.toggle('is-invalid', !ok);
        }

        /** Advisory-only validator (doesn't block submit). */
        function wireQuotationValidation(container) {
            if (!container) return;
            container.querySelectorAll('input.qtn').forEach((el) => {
                if (el.__qtnWired) {
                    el.__qtnSanitize?.();
                    return;
                }
                const sanitize = () => {
                    const before = el.value || '';
                    el.value = before.toUpperCase().replace(/\s+/g, '');
                    const ok = (el.value === '' || QTN_RE.test(el.value));
                    markValid(el, ok);
                };
                el.__qtnSanitize = sanitize;
                el.addEventListener('input', sanitize);
                sanitize();
                el.__qtnWired = true;
            });
        }
    </script>

@endpush
