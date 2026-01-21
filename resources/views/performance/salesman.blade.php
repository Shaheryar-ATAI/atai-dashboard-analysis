@extends('layouts.app')

@section('title', 'ATAI Salesman Summary — Performance')

@push('head')
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>

    {{-- Only custom styles here – Bootstrap + Highcharts come from layouts/app + atai-theme --}}
    <style>
        .badge-total { font-weight: 600; }

        /* ====== Section headings above each table ====== */
        .sales-section-title{
            text-align:center;
            text-transform:uppercase;
            letter-spacing:.15em;
            font-weight:700;
            font-size:.8rem;
            margin-bottom:.85rem;
            color: rgba(255,255,255,.92);
        }

        /* ====== DataTables sticky header (kept compatible even if you don't use DT) ====== */
        .table-sticky thead th{
            position: sticky;
            top: 0;
            z-index: 2;
            background: rgba(15,23,42,.92);
            color: rgba(255,255,255,.92);
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        /* ====== Matrix tables (normal tables, rendered by JS) ====== */
        .section-card{ margin-bottom: 1.25rem; }

        .matrix-wrap{
            overflow:auto;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(2,6,23,.35);
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }

        .matrix-table{
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
            min-width: 1100px;
            color: rgba(255,255,255,.88);
        }

        .matrix-table th,
        .matrix-table td{
            border-right: 1px solid rgba(255,255,255,.08);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 8px 10px;
            white-space: nowrap;
            vertical-align: middle;
            background: transparent;
        }

        .matrix-table thead th{
            position: sticky;
            top: 0;
            z-index: 3;
            background: rgba(15,23,42,.92);
            color: rgba(255,255,255,.92);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .08em;
            border-bottom: 1px solid rgba(255,255,255,.14);
        }

        .matrix-table tbody tr:hover td{
            background: rgba(255,255,255,.03);
        }

        .matrix-table td.num,
        .matrix-table td.pct{
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* Sticky left columns */
        .matrix-left-sticky{
            position: sticky;
            left: 0;
            z-index: 4;
            background: rgba(2,6,23,.75);
            color: rgba(255,255,255,.92);
            border-right: 1px solid rgba(255,255,255,.12);
        }
        .matrix-left-sticky-2{
            position: sticky;
            left: 160px; /* matches salesman column width below */
            z-index: 4;
            background: rgba(2,6,23,.70);
            color: rgba(255,255,255,.92);
            border-right: 1px solid rgba(255,255,255,.12);
        }

        .matrix-salesman{ width:160px; font-weight:700; }
        .matrix-label{ width:210px; font-weight:600; }

        /* Make Bootstrap "text-muted" readable in dark cards */
        .text-muted { color: rgba(255,255,255,.55) !important; }
    </style>
@endpush

@section('content')
    @php $u = auth()->user(); @endphp

    <main class="container-fluid py-4">

        {{-- =======================
             TOP FILTER + KPI BAR
           ======================= --}}
        <div class="card kpi-shell mb-3">
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">

                    {{-- Filters (left) --}}
                    <div class="col-md-4 d-flex flex-wrap align-items-center gap-2">
                        <div>
                            <div class="kpi-shell-label">Salesman Summary</div>
                            <div class="small text-muted">Web view matches PDF sections</div>
                        </div>

                        <div class="ms-md-3">
                            <div class="kpi-shell-label">Year</div>
                            <select id="yearSelect" class="form-select form-select-sm">
                                @for($y = now()->year; $y >= now()->year - 5; $y--)
                                    <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="ms-md-2">
                            <div class="kpi-shell-label">Area</div>
                            <select id="areaSelect" class="form-select form-select-sm">
                                <option value="All">All</option>
                                <option value="Eastern">Eastern</option>
                                <option value="Central">Central</option>
                                <option value="Western">Western</option>
                            </select>
                        </div>

                        <div class="mt-2">
                            <a id="btnDownloadPdf"
                               class="btn btn-sm btn-primary"
                               href="{{ route('performance.salesman.pdf') }}?year={{ (int)$year }}&area=All">
                                <i class="bi bi-download me-1"></i> Download PDF
                            </a>
                        </div>
                    </div>

                    {{-- KPI cards (right, using global .kpi-card styles) --}}
                    <div class="col-md-8">
                        <div class="row g-2 justify-content-md-end">

                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">Inquiries Total</div>
                                    <div id="badgeInq" class="kpi-value">SAR 0</div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">POs Total</div>
                                    <div id="badgePO" class="kpi-value">SAR 0</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card bg-dark text-light coordinator-kpi-card">
                                    <div class="card-body text-center">
                                        <div class="text-uppercase small text-secondary mb-1">Gap Coverage</div>
                                        <div id="salesman_gap_gauge" style="height: 140px;"></div>

                                        <div class="mt-2 small">
                                            <div id="salesman_gap_text" class="fw-semibold">Gap: SAR 0</div>
                                            <div id="salesman_gap_note" class="text-uppercase mt-1"
                                                 style="letter-spacing: .12em; font-size: .75rem;">
                                                POs vs Quotations
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- =======================
             BAR CHART
           ======================= --}}
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Salesman comparison (Inquiries vs POs)</h6>
                <div id="chartSalesman" style="height: 360px;"></div>
            </div>
        </div>

        {{-- =======================
             INQUIRIES (by Salesman) — NORMAL TABLE
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Inquiries (Quotations) — by Salesman</h6>

                <div class="matrix-wrap">
                    <table class="matrix-table table-sticky" id="tblSalesInquiries">
                        <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Salesman</th>
                            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                                <th class="text-end">{{ $m }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td class="p-3 text-muted" colspan="14">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- =======================
             POs (by Salesman) — NORMAL TABLE
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">
                <h6 class="card-title sales-section-title">POs received — by Salesman</h6>

                <div class="matrix-wrap">
                    <table class="matrix-table table-sticky" id="tblSalesPOs">
                        <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Salesman</th>
                            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                                <th class="text-end">{{ $m }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        <tr><td class="p-3 text-muted" colspan="14">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- =======================
             PERFORMANCE MATRIX (PDF MATCH)
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Performance Matrix — Forecast / Target / Inquiries / POs / Conversion</h6>
                <div class="matrix-wrap" id="perfMatrixWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>
            </div>
        </div>

        {{-- =======================
             PRODUCT MATRIX — INQUIRIES
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Product Matrix — Inquiries</h6>
                <div class="matrix-wrap" id="inqProdWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>
            </div>
        </div>

        {{-- =======================
             PRODUCT MATRIX — POs
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Product Matrix — POs</h6>
                <div class="matrix-wrap" id="poProdWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>
            </div>
        </div>

        {{-- =======================
             ESTIMATORS + TOTALS (PDF MATCH)
           ======================= --}}
        <div class="card mb-4 section-card">
            <div class="card-body">

                <h6 class="card-title sales-section-title">Inquiries — By Estimator</h6>
                <div class="matrix-wrap" id="estimatorWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>

                <hr class="my-3"/>

                <h6 class="card-title sales-section-title">Total Inquiries — By Month</h6>
                <div class="matrix-wrap" id="totalInqMonthWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>

                <hr class="my-3"/>

                <h6 class="card-title sales-section-title">Total Inquiries — By Product</h6>
                <div class="matrix-wrap" id="totalInqProductWrap">
                    <div class="p-3 text-muted">Loading...</div>
                </div>

            </div>
        </div>

    </main>
@endsection

@push('scripts')
    <script>
        // =========================
        // ROUTES
        // =========================
        const YEAR_INIT  = {{ (int) $year }};
        const KPI_URL    = @json(route('performance.salesman.kpis'));
        const MATRIX_URL = @json(route('performance.salesman.matrix')); // ✅ YOU MUST CREATE THIS ROUTE/ENDPOINT

        // =========================
        // HELPERS
        // =========================
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'];

        const money = (n) => {
            n = Number(n || 0);
            if (!n) return '–';
            return 'SAR ' + n.toLocaleString('en-SA', { maximumFractionDigits: 0 });
        };
        const pct = (n) => {
            n = Number(n || 0);
            return n.toFixed(1) + '%';
        };

        function currentYear(){ return document.getElementById('yearSelect')?.value || YEAR_INIT; }
        function currentArea(){ return document.getElementById('areaSelect')?.value || 'All'; }

        // Always normalize arrays to 13 cells
        function pad13(arr){
            const a = Array.isArray(arr) ? arr.slice(0, 13) : [];
            while (a.length < 13) a.push(0);
            return a;
        }

        // =========================
        // PDF DOWNLOAD LINK (year+area)
        // =========================
        document.getElementById('btnDownloadPdf')?.addEventListener('click', function () {
            const y = currentYear();
            const a = currentArea();
            this.href = `{{ route('performance.salesman.pdf') }}?year=${encodeURIComponent(y)}&area=${encodeURIComponent(a)}`;
        });

        // =========================
        // CHART + KPI + GAUGE
        // =========================
        async function loadTopKpisAndChart(){
            const year = currentYear();
            const area = currentArea();

            const res  = await fetch(`${KPI_URL}?year=${encodeURIComponent(year)}&area=${encodeURIComponent(area)}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            // KPIs
            const totalInq = Number(data.sum_inquiries || 0);
            const totalPos = Number(data.sum_pos || 0);
            document.getElementById('badgeInq').textContent = money(totalInq);
            document.getElementById('badgePO').textContent  = money(totalPos);

            // Column chart
            Highcharts.chart('chartSalesman', {
                chart: { type: 'column', backgroundColor: 'transparent' },
                title: {
                    text: `Salesman Comparison — ${year}`,
                    style: { color: '#ffffff', fontSize: '16px', fontWeight: '600' }
                },
                xAxis: {
                    categories: data.categories || [],
                    labels: { style: { color: '#d0d0d0', fontSize: '12px', fontWeight: '500' } },
                    lineColor: '#444', tickColor: '#444'
                },
                yAxis: {
                    min: 0,
                    title: { text: 'SAR', style: { color: '#ffffff', fontWeight: '600' } },
                    labels: { style: { color: '#d0d0d0', fontSize: '11px' } },
                    gridLineColor: '#333'
                },
                legend: { itemStyle: { color: '#ffffff', fontWeight: '500' } },
                tooltip: {
                    shared: true,
                    backgroundColor: '#1a1a1a',
                    borderColor: '#333',
                    style: { color: '#fff' },
                    formatter() {
                        const pts = this.points.map(p =>
                            `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${money(p.y)}</b>`
                        ).join('<br/>');
                        return `<b>${this.x}</b><br/>${pts}`;
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.08,
                        borderWidth: 0,
                        borderRadius: 3,
                        dataLabels: {
                            enabled: true,
                            style: { color: '#ffffff', fontSize: '11px', fontWeight: '600', textOutline: 'none' },
                            formatter: function () { return money(this.y); }
                        }
                    }
                },
                series: [
                    { name: 'Inquiries', data: data.inquiries || [], color: '#51a7ff' },
                    { name: 'POs',       data: data.pos || [],      color: '#9a69e3' }
                ],
                credits: { enabled: false }
            });

            // Gauge
            const coverage = totalInq > 0 ? (totalPos / totalInq) * 100 : 0;
            const gap = totalInq - totalPos;

            Highcharts.chart('salesman_gap_gauge', {
                chart: { type: 'solidgauge', backgroundColor: 'transparent' },
                title: null,
                pane: {
                    center: ['50%', '80%'],
                    size: '140%',
                    startAngle: -90,
                    endAngle: 90,
                    background: {
                        innerRadius: '60%',
                        outerRadius: '100%',
                        shape: 'arc',
                        borderWidth: 0,
                        backgroundColor: '#1f2937'
                    }
                },
                tooltip: { enabled: false },
                yAxis: {
                    min: 0,
                    max: 100,
                    lineWidth: 0,
                    tickWidth: 0,
                    tickAmount: 0,
                    labels: { enabled: false },
                    stops: [
                        [0.25, '#ef4444'],
                        [0.6,  '#facc15'],
                        [1.0,  '#22c55e']
                    ]
                },
                plotOptions: {
                    solidgauge: {
                        dataLabels: {
                            useHTML: true,
                            borderWidth: 0,
                            padding: 0,
                            y: -20,
                            style: { color: '#e5e7eb' },
                            format:
                                '<div style="text-align:center">' +
                                '<div style="font-size:22px;font-weight:600">{y:.0f}%</div>' +
                                '<div style="font-size:11px;">POs vs Quotations</div>' +
                                '</div>'
                        }
                    }
                },
                credits: { enabled: false },
                series: [{ name: 'Coverage', data: [coverage] }]
            });

            const gapText = document.getElementById('salesman_gap_text');
            const gapNote = document.getElementById('salesman_gap_note');
            const gapAbs  = Math.abs(gap);

            if (gapText) {
                gapText.textContent = 'Gap: ' + money(gapAbs);
                gapText.style.color = gap >= 0 ? '#22c55e' : '#f97316';
            }
            if (gapNote) {
                gapNote.textContent = gap > 0 ? 'MORE QUOTED THAN POS'
                    : (gap < 0 ? 'MORE POS THAN QUOTED' : 'POS MATCH QUOTATIONS');
            }
        }

        // =========================
        // SIMPLE TABLE RENDER: Salesman monthly (inq/po)
        // =========================
        function renderSalesmanMonthTable(tableId, objBySalesman){
            const tbody = document.querySelector(`#${tableId} tbody`);
            if (!tbody) return;

            const keys = Object.keys(objBySalesman || {}).sort();
            if (!keys.length) {
                tbody.innerHTML = `<tr><td class="p-3 text-muted" colspan="14">No data.</td></tr>`;
                return;
            }

            let html = '';
            keys.forEach(s => {
                const row = pad13(objBySalesman[s]);
                html += `<tr>
                    <td class="matrix-left-sticky matrix-salesman">${s}</td>
                    ${row.map(v => `<td class="text-end">${money(v)}</td>`).join('')}
                </tr>`;
            });

            tbody.innerHTML = html;
        }

        // =========================
        // MATRIX RENDERERS (PDF MATCH)
        // =========================
        function monthsHeaderHtml(){
            return months.map(m => `<th class="text-end">${m}</th>`).join('');
        }

        function renderPerfMatrix(perf){
            const obj = perf || {};
            const salesmanKeys = Object.keys(obj).sort();

            const metricOrder = [
                ['FORECAST','Forecast', 'money'],
                ['TARGET','Target', 'money'],
                ['PERF', 'Performance', 'perfHtml'],   // optional (if you include in payload)
                ['INQUIRIES','Inquiries', 'money'],
                ['POS','Sales Orders', 'money'],
                ['CONV_PCT','Conversion %', 'pct'],
            ];

            let html = `
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Salesman</th>
                            <th class="matrix-left-sticky-2 matrix-label">Metric</th>
                            ${monthsHeaderHtml()}
                        </tr>
                    </thead>
                    <tbody>
            `;

            if (!salesmanKeys.length) {
                html += `<tr><td class="p-3 text-muted" colspan="15">No data.</td></tr>`;
            } else {
                salesmanKeys.forEach(s => {
                    const m = obj[s] || {};
                    metricOrder.forEach(([key, label, kind], idx) => {
                        const row = pad13(m[key]);

                        html += `<tr>
                            <td class="matrix-left-sticky matrix-salesman">${idx===0 ? s : ''}</td>
                            <td class="matrix-left-sticky-2 matrix-label">${label}</td>
                        `;

                        if (kind === 'pct') {
                            html += row.map(v => `<td class="pct">${pct(v)}</td>`).join('');
                        } else if (kind === 'perfHtml') {
                            // if controller returns HTML pills per cell: [{html:"<span...>"}]
                            html += row.map(cell => {
                                const h = (cell && typeof cell === 'object' && cell.html) ? cell.html : '–';
                                return `<td class="pct">${h}</td>`;
                            }).join('');
                        } else {
                            html += row.map(v => `<td class="num">${money(v)}</td>`).join('');
                        }

                        html += `</tr>`;
                    });
                });
            }

            html += `</tbody></table>`;
            document.getElementById('perfMatrixWrap').innerHTML = html;
        }

        function renderProdMatrix(data, mountId){
            const obj = data || {};

            let html = `
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Salesman</th>
                            <th class="matrix-left-sticky-2 matrix-label">Product</th>
                            ${monthsHeaderHtml()}
                        </tr>
                    </thead>
                    <tbody>
            `;

            const salesmen = Object.keys(obj).sort();
            if (!salesmen.length) {
                html += `<tr><td class="p-3 text-muted" colspan="15">No data.</td></tr>`;
            } else {
                salesmen.forEach(s => {
                    const products = obj[s] || {};
                    const prodKeys = Object.keys(products).sort();
                    if (!prodKeys.length) {
                        html += `<tr>
                            <td class="matrix-left-sticky matrix-salesman">${s}</td>
                            <td class="matrix-left-sticky-2 matrix-label text-muted">No products</td>
                            ${Array(13).fill(0).map(()=>`<td class="text-end">–</td>`).join('')}
                        </tr>`;
                        return;
                    }
                    prodKeys.forEach((p, i) => {
                        const row = pad13(products[p]);
                        html += `<tr>
                            <td class="matrix-left-sticky matrix-salesman">${i===0 ? s : ''}</td>
                            <td class="matrix-left-sticky-2 matrix-label">${p}</td>
                            ${row.map(v => `<td class="num">${money(v)}</td>`).join('')}
                        </tr>`;
                    });
                });
            }

            html += `</tbody></table>`;
            document.getElementById(mountId).innerHTML = html;
        }

        function renderEstimatorTable(est){
            const obj = est || {};
            let html = `
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Estimator</th>
                            ${monthsHeaderHtml()}
                        </tr>
                    </thead>
                    <tbody>
            `;

            const keys = Object.keys(obj).sort();
            if (!keys.length) {
                html += `<tr><td class="p-3 text-muted" colspan="14">No data.</td></tr>`;
            } else {
                keys.forEach(k => {
                    const row = pad13(obj[k]);
                    html += `<tr>
                        <td class="matrix-left-sticky matrix-salesman">${k}</td>
                        ${row.map(v => `<td class="num">${money(v)}</td>`).join('')}
                    </tr>`;
                });
            }

            html += `</tbody></table>`;
            document.getElementById('estimatorWrap').innerHTML = html;
        }

        function renderTotalMonth(arr){
            const row = pad13(arr);
            const html = `
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">TOTAL</th>
                            ${monthsHeaderHtml()}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="matrix-left-sticky matrix-salesman">TOTAL</td>
                            ${row.map(v => `<td class="num">${money(v)}</td>`).join('')}
                        </tr>
                    </tbody>
                </table>
            `;
            document.getElementById('totalInqMonthWrap').innerHTML = html;
        }

        function renderTotalProduct(obj){
            const data = obj || {};
            let html = `
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="matrix-left-sticky matrix-salesman">Product</th>
                            ${monthsHeaderHtml()}
                        </tr>
                    </thead>
                    <tbody>
            `;

            const keys = Object.keys(data).sort();
            if (!keys.length) {
                html += `<tr><td class="p-3 text-muted" colspan="14">No data.</td></tr>`;
            } else {
                keys.forEach(p => {
                    const row = pad13(data[p]);
                    html += `<tr>
                        <td class="matrix-left-sticky matrix-salesman">${p}</td>
                        ${row.map(v => `<td class="num">${money(v)}</td>`).join('')}
                    </tr>`;
                });
            }

            html += `</tbody></table>`;
            document.getElementById('totalInqProductWrap').innerHTML = html;
        }

        // =========================
        // MAIN MATRIX LOADER (ONE CALL)
        // Controller must return:
        // - inquiriesBySalesman, posBySalesman
        // - salesmanKpiMatrix, inqProductMatrix, poProductMatrix
        // - inquiriesByEstimator, totalInquiriesByMonth, totalInquiriesByProduct
        // =========================
        async function loadAllTables(){
            const year = currentYear();
            const area = currentArea();

            const url = `${MATRIX_URL}?year=${encodeURIComponent(year)}&area=${encodeURIComponent(area)}`;
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const payload = await res.json();

            // Salesman monthly
            renderSalesmanMonthTable('tblSalesInquiries', payload.inquiriesBySalesman || {});
            renderSalesmanMonthTable('tblSalesPOs',       payload.posBySalesman       || {});

            // Matrices (PDF match)
            renderPerfMatrix(payload.salesmanKpiMatrix || {});
            renderProdMatrix(payload.inqProductMatrix  || {}, 'inqProdWrap');
            renderProdMatrix(payload.poProductMatrix   || {}, 'poProdWrap');

            // Estimator + totals
            renderEstimatorTable(payload.inquiriesByEstimator || {});
            renderTotalMonth(payload.totalInquiriesByMonth || []);
            renderTotalProduct(payload.totalInquiriesByProduct || {});
        }

        // =========================
        // EVENTS
        // =========================
        document.getElementById('yearSelect')?.addEventListener('change', async () => {
            await loadTopKpisAndChart();
            await loadAllTables();
        });

        document.getElementById('areaSelect')?.addEventListener('change', async () => {
            await loadTopKpisAndChart();
            await loadAllTables();
        });

        // =========================
        // INIT
        // =========================
        document.addEventListener('DOMContentLoaded', async () => {
            document.getElementById('yearSelect').value = YEAR_INIT;
            await loadTopKpisAndChart();
            await loadAllTables();
        });
    </script>
@endpush
