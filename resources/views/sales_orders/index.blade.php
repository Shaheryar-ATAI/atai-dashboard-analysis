{{-- resources/views/salesorderlog/index.blade.php --}}
@extends('layouts.app')

@section('title', 'ATAI Sales Orders — Live')

@push('head')
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>

    <style>
        :root {
            --atai-bg:        #050814;
            --atai-surface:   #0b1020;
            --atai-surface-soft: #111729;
            --atai-border:    rgba(255, 255, 255, 0.06);
            --atai-border-strong: rgba(255, 255, 255, 0.12);
            --atai-text-main: #f8f9fa;
            --atai-text-muted:#9ca3af;
            --atai-chip-bg:   #111827;
        }

        body {
            background-color: var(--atai-bg);
        }

        main.salesorders-shell {
            min-height: calc(100vh - 80px);
        }

        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm,
        table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }

        .badge-total {
            font-weight: 600;
            font-size: .85rem;
            letter-spacing: .02em;
        }

        td.details-control {
            cursor: pointer;
        }

        /* KPI card – match Projects / overall dark theme */
        .kpi-card {
            background: radial-gradient(circle at top left, #111827 0, #050814 55%, #020617 100%);
            color: var(--atai-text-main);
            border-radius: 0.85rem;
            border: 1px solid var(--atai-border);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.55);
        }

        .kpi-card .card-body {
            background: transparent;
        }

        .kpi-toolbar {
            border-bottom: 1px solid var(--atai-border);
            padding-bottom: .35rem;
            margin-bottom: .75rem;
        }

        .kpi-toolbar select.form-select-sm {
            min-width: 130px;
            background-color: var(--atai-chip-bg);
            border-color: var(--atai-border);
            color: var(--atai-text-main);
        }

        .kpi-toolbar select.form-select-sm:focus {
            box-shadow: 0 0 0 .15rem rgba(59, 130, 246, .25);
        }

        .kpi-toolbar .btn-primary.btn-sm {
            padding-inline: 1.3rem;
            font-weight: 500;
            border-radius: 999px;
        }

        .kpi-badge-bar .badge-total {
            border-radius: 999px;
            padding: .35rem .9rem;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background-image: linear-gradient(90deg, #2563eb, #22c55e);
        }

        .kpi-badge-bar .badge-total.text-bg-info {
            background-image: linear-gradient(90deg, #06b6d4, #8b5cf6);
        }

        .kpi-badge-bar .badge-total small {
            font-weight: 400;
            color: rgba(255, 255, 255, .8);
        }

        /* Highcharts wrappers */
        .kpi-card [id^="chart"] {
            background-color: transparent;
            min-height: 220px;
        }

        /* Top clients */
        .top-clients-card {
            background-color: var(--atai-surface);
            color: var(--atai-text-main);
            border-color: var(--atai-border);
            border-radius: .85rem;
            overflow: hidden;
        }

        .top-clients-card .card-header {
            background: linear-gradient(90deg, rgba(37,99,235,.25), transparent);
            border-bottom-color: var(--atai-border-strong);
            color: #e5e7eb;
            padding-block: .45rem;
            font-size: .9rem;
        }

        .top-clients-card .table {
            margin-bottom: 0;
            font-size: .82rem;
        }

        .top-clients-card .table thead th {
            border-color: var(--atai-border-strong);
            color: var(--atai-text-muted);
            font-weight: 500;
        }

        .top-clients-card .table tbody tr:nth-child(even) {
            background-color: rgba(15, 23, 42, 0.75);
        }

        .top-clients-card .table tbody tr:nth-child(odd) {
            background-color: rgba(15, 23, 42, 0.25);
        }

        .top-clients-card .table td,
        .top-clients-card .table th {
            border-color: var(--atai-border);
            color: var(--atai-text-main);
            vertical-align: middle;
            padding-block: .35rem;
        }

        .top-clients-scroll {
            max-height: 280px;
            overflow-y: auto;
        }

        /* DataTable – dark */
        #tblSales.table {
            background-color: var(--atai-surface);
            color: var(--atai-text-main);
            border-radius: .85rem;
            overflow: hidden;
        }

        #tblSales.table thead th {
            background-color: #020617;
            color: var(--atai-text-muted);
            border-bottom: 1px solid var(--atai-border-strong);
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        #tblSales.table tbody tr:nth-child(even) {
            background-color: rgba(15, 23, 42, .9);
        }

        #tblSales.table tbody tr:nth-child(odd) {
            background-color: rgba(15, 23, 42, .6);
        }

        #tblSales.table td {
            border-color: var(--atai-border);
            font-size: .83rem;
            vertical-align: middle;
        }

        #tblSales.table td.text-end {
            font-variant-numeric: tabular-nums;
        }

        /* Territory mix cards */
        .mix-card {
            background-color: var(--atai-surface);
            border-radius: .9rem;
            padding: .6rem .8rem;
            min-width: 180px;
            border: 1px solid var(--atai-border);
        }

        .mix-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .25rem;
        }

        .mix-name {
            font-weight: 600;
            font-size: .86rem;
        }

        .mix-chip {
            background-color: var(--atai-chip-bg);
            border-radius: 999px;
            padding: .1rem .6rem;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--atai-text-muted);
        }

        .mix-main {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .mix-sub {
            font-size: .75rem;
            color: var(--atai-text-muted);
        }

        .mix-card.mix-ok {
            border-color: rgba(34, 197, 94, 0.55);
        }

        .mix-card.mix-watch {
            border-color: rgba(234, 179, 8, 0.7);
        }

        .mix-card.mix-flag {
            border-color: rgba(248, 113, 113, 0.9);
        }

        .card.section-card {
            background-color: var(--atai-surface);
            border-radius: .85rem;
            border-color: var(--atai-border);
            color: var(--atai-text-main);
        }

        .section-card .card-body {
            padding: .9rem .9rem .8rem;
        }

        .section-card h5 {
            font-size: .95rem;
        }

        .section-card small.text-muted {
            color: var(--atai-text-muted) !important;
        }
    </style>
@endpush

@section('content')
    <main class="container-fluid py-4 salesorders-shell">

        {{-- KPI + Charts --}}
        <div class="card kpi-card mb-3">
            <div class="card-body">
                <div class="kpi-toolbar d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select id="kpiYear" class="form-select form-select-sm">
                            <option value="">All Years</option>
                            @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>

                        <select id="kpiRegion" class="form-select form-select-sm">
                            <option value="">All Regions</option>
                            <option>Eastern</option>
                            <option>Central</option>
                            <option>Western</option>
                        </select>

                        <button id="kpiApply" class="btn btn-sm btn-primary">
                            Update
                        </button>
                    </div>

                    <div class="d-flex flex-wrap gap-2 kpi-badge-bar">
                        <span id="badgeTotalVAT" class="badge-total text-bg-primary">
                            <small>Total (VAT)</small>
                            SAR 0
                        </span>
                        <span id="badgeTotalPO" class="badge-total text-bg-info">
                            <small>Total PO</small>
                            SAR 0
                        </span>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div id="chartRegion"></div>
                    </div>
                    <div class="col-md-6">
                        <div id="chartStatus"></div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <div id="chartMonthly"></div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-12 col-lg-6">
                        <div class="card top-clients-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><strong>Top Clients (by VAT)</strong></span>
                            </div>
                            <div class="card-body p-0 top-clients-scroll">
                                <table class="table table-sm mb-0">
                                    <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody id="topClientsBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    {{-- Right side reserved if you later add another chart --}}
                    {{-- <div class="col-12 col-lg-6"><div id="chartCurrency"></div></div> --}}
                </div>
            </div>
        </div>

        {{-- Sales Orders table --}}
        <div class="table-responsive mb-3">
            <table class="table table-striped w-100" id="tblSales">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>PO No</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Region</th>
                    <th>Proj. Location</th>
                    <th>Currency</th>
                    <th class="text-end">PO Value</th>
                    <th class="text-end">Value w/ VAT</th>
                    <th>Status</th>
                </tr>
                </thead>
            </table>
        </div>

        {{-- Territory Mix – POs --}}
        <div class="card section-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Territory Mix <small class="text-muted">Accepted POs</small></h5>
                    <small class="text-muted">
                        Outside% = orders outside assigned region
                    </small>
                </div>
                <div id="mixRow" class="d-flex flex-wrap gap-3 mt-3"></div>
            </div>
        </div>

        {{-- Territory Mix – Inquiries --}}
        <div class="card section-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Territory Mix <small class="text-muted">Inquiries</small></h5>
                    <small class="text-muted">
                        Outside% = inquiries outside assigned region
                    </small>
                </div>
                <div id="mixRowInq" class="d-flex flex-wrap gap-3 mt-3"></div>
            </div>
        </div>

    </main>
@endsection

@push('scripts')
    <script>
        $.fn.dataTable.ext.errMode = 'console';

        const fmt = n => Number(n || 0).toLocaleString('en-SA', {maximumFractionDigits: 2});

        // global guard
        window.fmt = window.fmt || fmt;

        $(function () {
            const $year   = $('#kpiYear');
            const $region = $('#kpiRegion');

            const soTable = $('#tblSales').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/sales-orders/datatable',
                    type: 'GET',
                    data: function (d) {
                        d.year   = $year.val()   || '';
                        d.region = $region.val() || '';
                    }
                },
                columns: [
                    {data: 'date_rec_d',      name: 'date_rec_d',      orderable: true, searchable: false},
                    {data: 'po_no',           name: 'po_no',           orderable: true, searchable: false},
                    {data: 'client_name',     name: 'client_name',     orderable: true, searchable: false},
                    {data: 'project_name',    name: 'project_name',    orderable: true, searchable: false},
                    {data: 'region_name',     name: 'region_name',     orderable: true, searchable: false},
                    {data: 'project_location',name: 'project_location',orderable: true, searchable: false},
                    {data: 'cur',             name: 'cur',             orderable: true, searchable: false},
                    {
                        data: 'po_value',
                        name: 'po_value',
                        orderable: true,
                        searchable: false,
                        className: 'text-end',
                        render: d => fmt(d)
                    },
                    {
                        data: 'value_with_vat',
                        name: 'value_with_vat',
                        orderable: true,
                        searchable: false,
                        className: 'text-end',
                        render: d => fmt(d)
                    },
                    {data: 'status', name: 'status', orderable: true, searchable: false}
                ]
            });

            // when datatable response arrives, we could update extra badges if backend sends them
            $('#tblSales').on('xhr.dt', function (_e, _settings, json) {
                if (!json) return;
                // if later you want separate PO / VAT badges from datatable payload, you can hook here
            });

            // Single source of truth for the Update button
            $('#kpiApply').on('click', function () {
                loadKpis();
                loadForecast();
                loadTerritoryMixSales();
                loadTerritoryMixInquiries();
                soTable.ajax.reload(null, true);
            });

            // first load
            loadKpis();
            loadForecast();
            loadTerritoryMixSales();
            loadTerritoryMixInquiries();
        });

        // ===== Highcharts dark theme =====
        Highcharts.setOptions({
            chart: {
                backgroundColor: 'transparent',
                style: { color: '#e5e7eb', fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' }
            },
            title: {
                style: { color: '#f9fafb', fontSize: '13px', fontWeight: '600' }
            },
            xAxis: {
                labels: { style: { color: '#9ca3af', fontSize: '11px' } },
                title:  { style: { color: '#9ca3af', fontSize: '11px' } }
            },
            yAxis: {
                labels: { style: { color: '#9ca3af', fontSize: '11px' } },
                title:  { style: { color: '#9ca3af', fontSize: '11px' } },
                gridLineColor: 'rgba(148,163,184,0.18)'
            },
            legend: {
                itemStyle:      { color: '#e5e7eb', fontSize: '11px' },
                itemHoverStyle: { color: '#ffffff' }
            },
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.95)',
                borderColor:     'rgba(148,163,184,0.55)',
                style: { color: '#f9fafb', fontSize: '11px' }
            }
        });

        const hcBase = {
            chart: { height: 220, spacing: [8, 8, 8, 8], backgroundColor: 'transparent' },
            credits: { enabled: false },
            legend:  { enabled: false }
        };

        async function loadKpis() {
            const year   = document.getElementById('kpiYear').value || '';
            const region = document.getElementById('kpiRegion').value || '';

            const url = new URL("{{ route('salesorders.kpis') }}", window.location.origin);
            if (year)   url.searchParams.set('year', year);
            if (region) url.searchParams.set('region', region);

            const res = await fetch(url, {credentials: 'same-origin'});
            if (!res.ok) {
                console.error('kpis', await res.text());
                return;
            }
            const d = await res.json();

            // Top chips
            document.getElementById('badgeTotalVAT').textContent =
                'Total (VAT): SAR ' + fmt(Number(d.totals?.value_with_vat || 0));

            document.getElementById('badgeTotalPO').textContent =
                'Total PO: SAR ' + fmt(Number(d.totals?.po_value || 0));

            // Region chart (stacked)
            (function () {
                const payload = d.by_region_status || null;
                if (payload && payload.categories && payload.series) {
                    Highcharts.chart('chartRegion', Highcharts.merge(hcBase, {
                        legend: { enabled: true },
                        title:  { text: 'By Region (VAT)' },
                        xAxis:  { categories: payload.categories },
                        yAxis: {
                            min: 0,
                            title: { text: 'SAR' },
                            stackLabels: {
                                enabled: true,
                                formatter() { return Highcharts.numberFormat(this.total, 0); }
                            }
                        },
                        plotOptions: {
                            column: {
                                stacking: 'normal',
                                dataLabels: {
                                    enabled: true,
                                    formatter() { return this.y ? Highcharts.numberFormat(this.y, 0) : ''; }
                                }
                            }
                        },
                        tooltip: {
                            shared: true,
                            pointFormatter() {
                                return `<span style="color:${this.color}">\u25CF</span> ${this.series.name}: <b>${Highcharts.numberFormat(this.y || 0, 0)}</b><br/>`;
                            }
                        },
                        series: payload.series.map(s => ({
                            type: 'column',
                            name: s.name,
                            data: s.data,
                            stack: s.stack || 'VAT'
                        }))
                    }));
                } else {
                    Highcharts.chart('chartRegion', Highcharts.merge(hcBase, {
                        title: { text: 'By Region (VAT)' },
                        xAxis: { categories: (d.by_region || []).map(x => x.region || '—') },
                        yAxis: { title: { text: 'SAR' } },
                        series: [{ type: 'column', data: (d.by_region || []).map(x => Number(x.total || 0)) }]
                    }));
                }
            })();

            // Status pie
            Highcharts.chart('chartStatus', Highcharts.merge(hcBase, {
                title:  { text: 'By Status (VAT)' },
                legend: { enabled: true },
                series: [{
                    type: 'pie',
                    data: (d.by_status || []).map(x => ({ name: x.status || '—', y: Number(x.total || 0) }))
                }]
            }));

            // Monthly stacked
            (function () {
                const payload = d.monthly_status || null;
                if (payload && payload.categories && payload.series) {
                    Highcharts.chart('chartMonthly', Highcharts.merge(hcBase, {
                        legend: { enabled: true },
                        title:  { text: 'Monthly (VAT)' },
                        xAxis: {
                            categories: payload.categories,
                            labels: {
                                formatter() {
                                    const [Y, M] = String(this.value || '').split('-');
                                    if (!Y || !M) return this.value || '';
                                    const dt = new Date(+Y, +M - 1, 1);
                                    return dt.toLocaleString('en', {month: 'short', year: '2-digit'});
                                }
                            }
                        },
                        yAxis: {
                            min: 0,
                            title: { text: 'SAR' },
                            stackLabels: {
                                enabled: true,
                                formatter() { return Highcharts.numberFormat(this.total, 0); }
                            }
                        },
                        plotOptions: {
                            column: {
                                stacking: 'normal',
                                dataLabels: {
                                    enabled: true,
                                    formatter() { return this.y ? Highcharts.numberFormat(this.y, 0) : ''; }
                                }
                            }
                        },
                        tooltip: {
                            shared: true,
                            pointFormatter() {
                                return `<span style="color:${this.color}">\u25CF</span> ${this.series.name}: <b>${Highcharts.numberFormat(this.y || 0, 0)}</b><br/>`;
                            }
                        },
                        series: payload.series.map(s => ({
                            type: 'column',
                            name: s.name,
                            data: s.data,
                            stack: s.stack || 'VAT'
                        }))
                    }));
                } else {
                    Highcharts.chart('chartMonthly', Highcharts.merge(hcBase, {
                        title: { text: 'Monthly (VAT)' },
                        xAxis: { categories: (d.monthly || []).map(m => m.ym) },
                        yAxis: { title: { text: 'SAR' } },
                        series: [{ type: 'column', data: (d.monthly || []).map(m => Number(m.total || 0)) }]
                    }));
                }
            })();

            // Top clients
            const body = document.getElementById('topClientsBody');
            if (body) {
                body.innerHTML = '';
                (d.top_clients || []).forEach(c => {
                    body.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td>${c.client || '—'}</td>
                        <td class="text-end">${Number(c.orders || 0)}</td>
                        <td class="text-end">${fmt(Number(c.total || 0))}</td>
                    </tr>
                `);
                });
            }
        }

        const fmtSAR = n => 'SAR ' + fmt(n);

        async function loadForecast() {
            const row = document.getElementById('forecastRow');
            if (row) row.style.display = '';

            const year   = document.getElementById('kpiYear')?.value || '';
            const region = document.getElementById('kpiRegion')?.value || '';

            const url = new URL("{{ route('forecast.kpis') }}", window.location.origin);
            if (year)   url.searchParams.set('year', year);
            if (region) url.searchParams.set('area', region);

            let data = {
                area: [],
                salesman: [],
                total_value: 0,
                monthly_region_metrics: {categories: [], series: []},
                region_summary: {categories: [], series: []}
            };

            try {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) data = await res.json();
            } catch (e) {
                console.warn('Forecast fetch failed', e);
            }

            const scopeLabel = region || 'All Regions';
            const scopeEl = document.getElementById('fcBadgeScope');
            if (scopeEl) scopeEl.textContent = scopeLabel;
            const totalEl = document.getElementById('fcBadgeValue');
            if (totalEl) totalEl.textContent = 'Forecast Total: ' + fmtSAR(Number(data.total_value || 0));

            const baseHC = {
                chart: { height: 260, spacing: [8, 8, 8, 8] },
                credits: { enabled: false },
                legend: { enabled: false },
                tooltip: { pointFormat: 'SAR {point.y:,.0f}' },
                plotOptions: {
                    column: {
                        dataLabels: {
                            enabled: true,
                            formatter() { return this.y ? ('SAR ' + (this.y ?? 0).toLocaleString()) : ''; }
                        }
                    }
                }
            };

            (function renderBySalesman() {
                const wrap = document.getElementById('fcBarBySalesman');
                if (!wrap) return;
                const s = Array.isArray(data.salesman) ? data.salesman : [];
                if (s.length) {
                    Highcharts.chart('fcBarBySalesman', Highcharts.merge(baseHC, {
                        title: { text: 'Forecast by Salesman (SAR)' + (region ? ` — ${region}` : '') },
                        xAxis: {
                            categories: s.map(x => x.salesman || '—'),
                            labels: { rotation: -30 }
                        },
                        yAxis: { title: { text: 'SAR' } },
                        series: [{
                            type: 'column',
                            name: 'Forecast',
                            data: s.map(x => Number(x.sum_value || 0))
                        }]
                    }));
                } else {
                    wrap.innerHTML = '<div class="text-secondary small">No salesman data.</div>';
                }
            })();

            (function renderMonthlyTotals() {
                const elId = 'fcMonthlyTotals';
                const el   = document.getElementById(elId);
                if (!el) return;

                const mf = data.monthly_forecast  || {categories: [], series: []};
                const mi = data.monthly_inquiries || {categories: [], series: []};
                const ms = data.monthly_sales     || {categories: [], series: []};

                Highcharts.chart(elId, Highcharts.merge(baseHC, {
                    chart: { type: 'column', height: 220 },
                    legend: { enabled: true },
                    title: { text: 'Monthly Totals • Forecast vs Inquiries vs Sales' },
                    xAxis: {
                        categories: mf.categories || [],
                        labels: {
                            formatter() {
                                const [Y, M] = String(this.value || '').split('-');
                                if (!Y || !M) return this.value || '';
                                const d = new Date(+Y, +M - 1, 1);
                                return d.toLocaleString('en', {month: 'short', year: '2-digit'});
                            }
                        }
                    },
                    yAxis: { title: { text: 'SAR' } },
                    series: [
                        { name: 'Forecast',  data: (mf.series || []).map(Number) },
                        { name: 'Inquiries', data: (mi.series || []).map(Number) },
                        { name: 'Sales',     data: (ms.series || []).map(Number) }
                    ],
                    lang: { noData: 'No monthly totals.' },
                    noData: { style: { fontSize: '12px', color: '#6c757d' } }
                }));
            })();
        }

        function renderMixCards(list, hostId) {
            const host = document.getElementById(hostId);
            if (!host) return;
            host.innerHTML = '';

            if (!list || !list.length) {
                host.innerHTML = '<div class="text-secondary small">No data.</div>';
                return;
            }

            for (const r of list) {
                const cls =
                    r.outside_percent >= 50 ? 'mix-flag' :
                        r.outside_percent >= 35 ? 'mix-watch' : 'mix-ok';

                host.insertAdjacentHTML('beforeend', `
                <div class="mix-card ${cls}">
                    <div class="mix-top">
                        <span class="mix-name">${r.sales_man}</span>
                        <span class="mix-chip">${r.assigned_region}</span>
                    </div>
                    <div class="mix-main">${Number(r.outside_percent).toFixed(2)}%</div>
                    <div class="mix-sub">${r.outside_projects} / ${r.total_projects} outside</div>
                </div>
            `);
            }
        }

        async function loadTerritoryMixSales() {
            const year   = document.getElementById('kpiYear')?.value || '';
            const region = document.getElementById('kpiRegion')?.value || '';

            const url = new URL("{{ route('sales-orders.territory-sales') }}", window.location.origin);
            if (year)   url.searchParams.set('year', year);
            if (region) url.searchParams.set('region', region);

            let list = [];
            try {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) list = await res.json();
            } catch (e) {
                console.warn('territory-mix failed', e);
            }

            renderMixCards(list, 'mixRow');
        }

        async function loadTerritoryMixInquiries() {
            const year   = document.getElementById('kpiYear')?.value || '';
            const region = document.getElementById('kpiRegion')?.value || '';

            const url = new URL("{{ route('projects.territory-inquiries') }}", window.location.origin);
            if (year)   url.searchParams.set('year', year);
            if (region) url.searchParams.set('region', region);

            let data = [];
            try {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) data = await res.json();
            } catch (e) {
                console.warn('projects territory-mix failed', e);
            }

            renderMixCards(data, 'mixRowInq');
        }
    </script>
@endpush
