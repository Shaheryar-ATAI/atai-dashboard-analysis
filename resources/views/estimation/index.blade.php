{{-- resources/views/estimation/index.blade.php --}}
    <!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estimation — ATAI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}">

    <style>
        .kpi-card{min-height:260px}.chart-box{min-height:220px}.est-pill{text-transform:none}
        .badge-total{font-size:1rem;font-weight:600;background:#e8f5e9;color:#1b5e20;padding:.4rem .8rem;border-radius:12px}
        @media (max-width:768px){
            .kpi-card{min-height:auto}.chart-box{min-height:180px}
            #estimatorPills{flex-wrap:wrap;gap:.5rem}
            #estimatorPills .nav-link{font-size:.85rem;padding:.25rem .5rem}
            table.dataTable td{font-size:.85rem;white-space:nowrap}
        }
        .estimator-toolbar.glass-row{
            display:flex;justify-content:space-between;align-items:center;
            gap:1rem;background:#faf8f869;backdrop-filter:saturate(180%) blur(8px);
            border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:.75rem 1rem
        }
        .et-left{display:flex;align-items:center;gap:.75rem}
        .et-right{display:flex;align-items:end;gap:.75rem;flex-wrap:wrap}
        .et-field{min-width:9rem}
    </style>
</head>
<body class="bg-body-tertiary">

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

<div class="container-fluid py-4">

    {{-- Estimators + Filters --}}
    <div class="estimator-toolbar glass-row mb-3">
        <div class="et-left">
            <h4 class="mb-0 me-2">Estimators</h4>
            <ul id="estimatorPills" class="nav nav-pills pill-chips"></ul>
        </div>

        <form class="et-right" id="estimatorFilters" onsubmit="return false;">
            <div class="et-field">
                <label class="form-label mb-1 small">Year</label>
                <select class="form-select form-select-sm" id="filterYear">
                    <option value="">All</option>
                    @for ($y = date('Y'); $y >= date('Y')-6; $y--) <option value="{{ $y }}">{{ $y }}</option> @endfor
                </select>
            </div>
            <div class="et-field">
                <label class="form-label mb-1 small">Month</label>
                <select class="form-select form-select-sm" id="filterMonth">
                    <option value="">All</option>
                    @for ($m=1; $m<=12; $m++) <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option> @endfor
                </select>
            </div>
            <div class="et-field">
                <label class="form-label mb-1 small">From</label>
                <input type="date" class="form-control form-control-sm" id="filterFrom">
            </div>
            <div class="et-field">
                <label class="form-label mb-1 small">To</label>
                <input type="date" class="form-control form-control-sm" id="filterTo">
            </div>
            <button id="applyFilters" class="btn btn-primary btn-sm">Apply</button>
            <button id="clearFilters" type="button" class="btn btn-outline-secondary btn-sm">Clear</button>
        </form>
    </div>

    {{-- KPI Row --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm kpi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kpi-title fw-semibold" id="kpi-title">By Estimator (share of value)</span>
                        <span class="badge-total text-bg-info" id="kpi-total-value">SAR 0</span>
                    </div>
                    <div id="chart-estimator" class="chart-box"></div>
                </div>
            </div>
        </div>



        <div class="col-12 col-lg-6">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">By Product (Top 10)</div>
                    <div id="chartProduct" style="height: 240px"></div>
                </div>
            </div>
        </div>
    </div>



            <div class="col-12 col-lg-12">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Monthly Value — Eastern / Central / Western</div>
                        <div id="chartMonthlyRegion" class="chart-box"></div>
                    </div>
                </div>


        </div>


    {{-- Tables --}}
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-all" type="button">All</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-region" type="button">By Region</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-product" type="button">By Product</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-all">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtAll">
                        <thead><tr>
                            <th>#</th><th>Project</th><th>Client</th><th>Region</th>
                            <th>Product</th><th>Value</th><th>Status</th><th>Estimator</th><th>Created</th>
                        </tr></thead>
                    </table>
                </div></div>
        </div>

        <div class="tab-pane fade" id="pane-region">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtRegion">
                        <thead><tr><th>Region</th><th>Count</th><th>Total Value</th></tr></thead>
                    </table>
                </div></div>
        </div>

        <div class="tab-pane fade" id="pane-product">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtProduct">
                        <thead><tr><th>Product</th><th>Count</th><th>Total Value</th></tr></thead>
                    </table>
                </div></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

<script>
    const fmtSAR = n => 'SAR ' + Number(n || 0).toLocaleString(undefined,{maximumFractionDigits:0});
    const fmtCompactSAR = n => {
        const x = Number(n || 0);
        if (x >= 1e9) return 'SAR ' + (x/1e9).toFixed(1) + 'B';
        if (x >= 1e6) return 'SAR ' + (x/1e6).toFixed(1) + 'M';
        if (x >= 1e3) return 'SAR ' + (x/1e3).toFixed(1) + 'k';
        return 'SAR ' + x.toFixed(0);
    };

    (() => {
        let currentEstimator = '';
        const $year = $('#filterYear'), $month = $('#filterMonth'), $from = $('#filterFrom'), $to = $('#filterTo');

        // Build Estimator pills
        fetch('{{ route('estimation.estimators') }}')
            .then(r => r.json())
            .then(list => {
                const ul = document.getElementById('estimatorPills');
                ul.innerHTML = `
          <li class="nav-item"><button class="nav-link est-pill active" data-estimator="">All</button></li>
          ${list.map(n => `<li class="nav-item"><button class="nav-link est-pill" data-estimator="${n}">${n}</button></li>`).join('')}
        `;
                ul.addEventListener('click', (e) => {
                    const btn = e.target.closest('button[data-estimator]'); if (!btn) return;
                    ul.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentEstimator = btn.getAttribute('data-estimator') || '';
                    reloadAll();
                });
                reloadAll();
            });

        // DataTables
        const money = v => 'SAR ' + Number(v || 0).toLocaleString();
        const dtAll = $('#dtAll').DataTable({
            processing:true, serverSide:true, lengthChange:true, order:[[0,'desc']],
            ajax:{url:'{{ route('estimation.datatable.all') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[
                {data:'id', width:60},{data:'project_name'},{data:'client_name'},{data:'area'},
                {data:'atai_products'},{data:'quotation_value', className:'text-end', render:money},
                {data:'status'},{data:'estimator'},{data:'created_at'}
            ]
        });
        const dtRegion = $('#dtRegion').DataTable({
            processing:true, serverSide:true, order:[[1,'desc']],
            ajax:{url:'{{ route('estimation.datatable.region') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[{data:'region'},{data:'cnt', className:'text-end'},{data:'val', className:'text-end', render:money}]
        });
        const dtProduct = $('#dtProduct').DataTable({
            processing:true, serverSide:true, order:[[1,'desc']],
            ajax:{url:'{{ route('estimation.datatable.product') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[{data:'product'},{data:'cnt', className:'text-end'},{data:'val', className:'text-end', render:money}]
        });

        // KPIs
        function loadKpis() {
            const qs = new URLSearchParams(buildFilters()).toString();

            fetch(`{{ route('estimation.kpis') }}?${qs}`)
                .then(r => r.json())
                .then(payload => {
                    // Total badge
                    document.getElementById('kpi-total-value').textContent = fmtSAR(payload?.totals?.value || 0);

                    // ===== Estimator pie =====
                    const titleEl = document.getElementById('kpi-title');
                    if (payload.mode === 'all') {
                        if (titleEl) titleEl.textContent = 'By Estimator (share of value)';
                        Highcharts.chart('chart-estimator', {
                            chart: {
                                type: 'pie',
                                backgroundColor: 'transparent',
                                spacing: [10, 10, 10, 10]
                            },
                            title: { text: null },
                            credits: { enabled: false },

                            colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185'],

                            tooltip: {
                                useHTML: true,
                                backgroundColor: 'rgba(10,15,45,0.95)',
                                borderColor: '#334155',
                                borderRadius: 8,
                                style: { color: '#E8F0FF', fontSize: '13px' },
                                pointFormatter: function () {
                                    return `
        <div style="margin:2px 0;">
          <span style="color:${this.color}">●</span>
          ${this.name}: <b>${Highcharts.numberFormat(this.percentage, 1)}%</b><br/>
          Value: <b>${fmtSAR(this.y)}</b>
        </div>
      `;
                                }
                            },

                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    size: '80%',
                                    borderWidth: 0,
                                    shadow: false,
                                    dataLabels: {
                                        enabled: true,
                                        distance: 20,
                                        softConnector: true,
                                        connectorWidth: 1.3,
                                        connectorColor: 'rgba(255,255,255,0.35)',
                                        style: {
                                            color: '#E8F0FF',
                                            fontWeight: 500,
                                            fontSize: '14px',
                                            textOutline: '2px rgba(0,0,0,0.6)'
                                        },
                                        format: '{point.name}: {point.percentage:.1f}%'
                                    }
                                }
                            },

                            legend: {
                                enabled: true,
                                itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                                itemHoverStyle: { color: '#FFFFFF' }
                            },

                            series: [{
                                name: 'Share',
                                colorByPoint: true,
                                data: payload.estimatorPie || []
                            }],

                            lang: { noData: 'No estimator data available.' },
                            noData: { style: { fontSize: '14px', color: '#E0E7FF', fontWeight: 600 } }
                        });

                    } else {
                        if (titleEl) titleEl.textContent = `${currentEstimator || 'Estimator'} — By Status`;
                        Highcharts.chart('chart-estimator', {
                            chart: {
                                type: 'pie',
                                backgroundColor: 'transparent',
                                spacing: [10, 10, 10, 10]
                            },
                            title: { text: null },
                            credits: { enabled: false },

                            // Consistent ATAI palette
                            colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185', '#22d3ee', '#a3e635'],

                            tooltip: {
                                useHTML: true,
                                backgroundColor: 'rgba(10,15,45,0.95)',
                                borderColor: '#334155',
                                borderRadius: 8,
                                style: { color: '#E8F0FF', fontSize: '13px' },
                                pointFormatter: function () {
                                    const count = Highcharts.numberFormat(this.y, 0);
                                    const val   = fmtSAR(this.options.value || 0);
                                    const pct   = Highcharts.numberFormat(this.percentage, 1) + '%';
                                    return `
        <div style="margin:2px 0;">
          <span style="color:${this.color}">●</span>
          <b>${this.name}</b><br/>
          Projects: <b>${count}</b> &nbsp;•&nbsp; Share: <b>${pct}</b><br/>
          Value: <b>${val}</b>
        </div>
      `;
                                }
                            },

                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    size: '80%',
                                    borderWidth: 0,
                                    shadow: false,
                                    dataLabels: {
                                        enabled: true,
                                        distance: 20,
                                        softConnector: true,
                                        connectorWidth: 1.3,
                                        connectorColor: 'rgba(255,255,255,0.35)',
                                        style: {
                                            color: '#E8F0FF',
                                            fontWeight: 600,
                                            fontSize: '14px',
                                            textOutline: '2px rgba(0,0,0,0.6)'
                                        },
                                        // Show Name + Count (and %)
                                        formatter: function () {
                                            const count = Highcharts.numberFormat(this.y, 0);
                                            const pct   = Highcharts.numberFormat(this.percentage, 1);
                                            return `${this.point.name}: <b>${count}</b> <span style="opacity:.85">(${pct}%)</span>`;
                                        }
                                    }
                                }
                            },

                            legend: {
                                enabled: true,
                                itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                                itemHoverStyle: { color: '#FFFFFF' }
                            },

                            // NOTE: payload.statusPie items should look like: { name, y: <count>, value: <sar> }
                            series: [{
                                name: 'Projects',
                                colorByPoint: true,
                                data: payload.statusPie || []
                            }],

                            lang: { noData: 'No estimator data available.' },
                            noData: { style: { fontSize: '14px', color: '#E0E7FF', fontWeight: 600 } }
                        });

                    }

                    // ===== Monthly Region =====
                    const cats = payload.monthlyRegion?.categories || [];
                    const regionCols = (payload.monthlyRegion?.series || []).map(s => ({
                        name: s.name, type: 'column', stack: 'Value',
                        data: (s.data || []).map(v => Number(v || 0)),
                        dataLabels: {
                            enabled: true,
                            formatter: function () { return this.y >= 5_000_000 ? fmtCompactSAR(this.y) : null; },
                            style: { textOutline: 'none', fontWeight: 600 }
                        }
                    }));
                    const totals = cats.map((_, i) => regionCols.reduce((sum, s) => sum + (s.data[i] || 0), 0));
                    const momPct = totals.map((v, i) =>
                        i === 0 ? 0 : ((totals[i - 1] || 0) > 0 ? Number(((v - totals[i - 1]) * 100 / totals[i - 1]).toFixed(1)) : 0)
                    );

                    Highcharts.chart('chartMonthlyRegion', {
                        chart: {
                            type: 'column',
                            backgroundColor: 'transparent',
                            spacing: [10, 20, 10, 20]
                        },
                        title: { text: null },
                        credits: { enabled: false },

                        colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#22d3ee', '#a3e635', '#fb7185'],

                        xAxis: {
                            categories: payload.monthlyRegion?.categories || [],
                            lineColor: 'rgba(255,255,255,.15)',
                            tickColor: 'rgba(255,255,255,.15)',
                            labels: { style: { color: '#C7D2FE', fontWeight: 600, fontSize: '13px' } }
                        },

                        yAxis: [{
                            title: { text: 'Value (SAR)', style: { color: '#C7D2FE', fontWeight: 700, fontSize: '13px' } },
                            min: 0,
                            gridLineColor: 'rgba(255,255,255,.10)',
                            labels: {
                                style: { color: '#E0E7FF', fontWeight: 600, fontSize: '12px' },
                                formatter() { return fmtSAR(this.value); }
                            }
                        }, {
                            title: { text: null },
                            opposite: true,
                            gridLineColor: 'transparent',
                            labels: { style: { color: '#E0E7FF' } }
                        }],

                        legend: {
                            itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                            itemHoverStyle: { color: '#FFFFFF' }
                        },

                        tooltip: {
                            shared: true,
                            useHTML: true,
                            backgroundColor: 'rgba(10,15,45,0.95)',
                            borderColor: '#334155',
                            borderRadius: 8,
                            style: { color: '#E8F0FF', fontSize: '13px' },
                            formatter: function () {
                                const header = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                                const lines = this.points.map(p =>
                                    `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b></div>`
                                );
                                return header + lines.join('');
                            }
                        },

                        plotOptions: {
                            column: {
                                borderWidth: 0,
                                borderRadius: 3,
                                pointPadding: 0.05,
                                groupPadding: 0.18,
                                states: { hover: { brightness: 0.08 } },
                                dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -10,
                                    crop: false,
                                    overflow: 'none',
                                    style: {
                                        color: '#E8F0FF',
                                        fontSize: '14px',

                                    },
                                    formatter: function () {
                                        const v = Number(this.y || 0);
                                        if (v <= 0) return '';
                                        // Show compact SAR everywhere; keeps columns readable
                                        return v >= 1e9 ? `SAR ${(v/1e9).toFixed(1).replace(/\.0$/,'')}B`
                                            : v >= 1e6 ? `SAR ${(v/1e6).toFixed(1).replace(/\.0$/,'')}M`
                                                : v >= 1e3 ? `SAR ${(v/1e3).toFixed(1).replace(/\.0$/,'')}K`
                                                    : `SAR ${v.toFixed(0)}`;
                                    }
                                }
                            }
                        },

                        series: payload.monthlyRegion?.series || []
                    });

                    // ===== By Product =====
                    Highcharts.chart('chartProduct', {
                        chart: {
                            type: 'column',
                            backgroundColor: 'transparent',
                            spacing: [10, 20, 10, 20]
                        },
                        title: { text: null },
                        credits: { enabled: false },

                        colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#22d3ee', '#a3e635', '#fb7185'],

                        xAxis: {
                            categories: payload.productSeries?.categories || [],
                            lineColor: 'rgba(255,255,255,.15)',
                            tickColor: 'rgba(255,255,255,.15)',
                            labels: {
                                rotation: 0,
                                style: { color: '#C7D2FE', fontWeight: 600, fontSize: '13px' }
                            }
                        },

                        yAxis: {
                            title: { text: 'Value (SAR)', style: { color: '#C7D2FE', fontWeight: 700, fontSize: '13px' } },
                            min: 0,
                            gridLineColor: 'rgba(255,255,255,.10)',
                            labels: {
                                style: { color: '#E0E7FF', fontWeight: 600, fontSize: '12px' },
                                formatter() { return fmtCompactSAR(this.value); }
                            }
                        },

                        legend: {
                            enabled: false // single series, cleaner look
                        },

                        tooltip: {
                            shared: false,
                            useHTML: true,
                            backgroundColor: 'rgba(10,15,45,0.95)',
                            borderColor: '#334155',
                            borderRadius: 8,
                            style: { color: '#E8F0FF', fontSize: '13px' },
                            pointFormatter: function () {
                                return `<b>${this.category}</b><br/>Value: <b>${fmtCompactSAR(this.y)}</b>`;
                            }
                        },

                        plotOptions: {
                            column: {
                                borderWidth: 0,
                                borderRadius: 3,
                                pointPadding: 0.05,
                                groupPadding: 0.18,
                                states: { hover: { brightness: 0.08 } },
                                dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -10,
                                    crop: false,
                                    overflow: 'none',
                                    style: {
                                        color: '#E8F0FF',
                                        fontWeight: 700,
                                        fontSize: '12px',
                                        textOutline: '2px rgba(0,0,0,.7)'
                                    },
                                    formatter: function () {
                                        return this.y ? `SAR ${fmtCompactSAR(this.y)}` : '';
                                    }
                                }
                            }
                        },

                        series: [{
                            name: 'Value',
                            data: payload.productSeries?.values || []
                        }]
                    });

                }) // <-- closes .then(payload => { ... })
                .catch(err => console.error('KPIs load failed:', err)); // optional logging
        } // <-- closes function loadKpis


        function buildFilters(){ return {
            estimator: currentEstimator,
            year: $year.val() || '', month: $month.val() || '',
            from: $from.val() || '', to: $to.val() || ''
        };}

        function reloadAll(){ loadKpis(); dtAll.ajax.reload(null,false); dtRegion.ajax.reload(null,false); dtProduct.ajax.reload(null,false); }

        $('#applyFilters').on('click', reloadAll);
        $('#clearFilters').on('click', ()=>{ $year.val(''); $month.val(''); $from.val(''); $to.val(''); reloadAll(); });
    })();
</script>
</body>
</html>
