{{-- resources/views/estimation/index.blade.php --}}
@extends('layouts.app')

@section('title', 'ATAI Projects — Live')
@push('head')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estimation — ATAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('css/atai-theme-20260210.css') }}">

    <style>
        .kpi-card {
            min-height: 260px
        }

        .chart-box {
            min-height: 220px
        }

        .est-pill {
            text-transform: none
        }

        .badge-total {
            font-size: 1rem;
            font-weight: 600;
            background: #e8f5e9;
            color: #1b5e20;
            padding: .4rem .8rem;
            border-radius: 12px
        }

        @media (max-width: 768px) {
            .kpi-card {
                min-height: auto
            }

            .chart-box {
                min-height: 180px
            }

            #estimatorPills {
                flex-wrap: wrap;
                gap: .5rem
            }

            #estimatorPills .nav-link {
                font-size: .85rem;
                padding: .25rem .5rem
            }

            table.dataTable td {
                font-size: .85rem;
                white-space: nowrap
            }
        }

        .estimator-toolbar.glass-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            background: #faf8f869;
            backdrop-filter: saturate(180%) blur(8px);
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: 12px;
            padding: .75rem 1rem
        }

        .et-left {
            display: flex;
            align-items: center;
            gap: .75rem
        }

        .et-right {
            display: flex;
            align-items: end;
            gap: .75rem;
            flex-wrap: wrap
        }

        .et-field {
            min-width: 9rem
        }


    </style>
@endpush
@section('content')
    <body class="bg-body-tertiary">

    @php $u = auth()->user(); @endphp


    <div class="container-fluid py-4">

        {{-- Estimators + Filters --}}
        <div class="estimator-toolbar glass-row mb-3">
            <div class="et-left">
                <h4 class="mb-0 me-2">Estimators</h4>
                <ul id="estimatorPills" class="nav nav-pills pill-chips"></ul>
            </div>

            <!-- Centered COUNT / VALUE toggle -->
            <div class="et-center">
                <div id="metricToggle" class="metric-toggle pill-chips" role="tablist">
                    <input type="radio" class="btn-check" name="metric" id="metricValue" value="value"
                           autocomplete="off" checked>
                    <label class="chip-btn" for="metricValue">Value</label>

                    <input type="radio" class="btn-check" name="metric" id="metricCount" value="count"
                           autocomplete="off">
                    <label class="chip-btn" for="metricCount">Count</label>
                </div>
            </div>


            <form class="et-right" id="estimatorFilters" onsubmit="return false;">
                <div class="et-field">
                    <label class="form-label mb-1 small">Year</label>
                    <select class="form-select form-select-sm" id="filterYear">
                        <option value="">All</option>
                        @for ($y = date('Y'); $y >= date('Y')-6; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="et-field">
                    <label class="form-label mb-1 small">Month</label>
                    <select class="form-select form-select-sm" id="filterMonth">
                        <option value="">All</option>
                        @for ($m=1; $m<=12; $m++)
                            <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                        @endfor
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
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-all" type="button">All
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-region" type="button">By Region
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-product" type="button">By Product
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-all">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-striped w-100" id="dtAll">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Region</th>
                                <th>Product</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Estimator</th>
                                <th>Created</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-region">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-striped w-100" id="dtRegion">
                            <thead>
                            <tr>
                                <th>Region</th>
                                <th>Count</th>
                                <th>Total Value</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-product">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-striped w-100" id="dtProduct">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Count</th>
                                <th>Total Value</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection
    @push('scripts')

        <script>
            // --- global metric state (radio: name="metric", values: 'value' | 'count')
            let metric = 'value';

            const fmtSAR = n => 'SAR ' + Number(n || 0).toLocaleString(undefined, {maximumFractionDigits: 0});
            const fmtCompactSAR = n => {
                const x = Number(n || 0);
                if (x >= 1e9) return 'SAR ' + (x / 1e9).toFixed(1) + 'B';
                if (x >= 1e6) return 'SAR ' + (x / 1e6).toFixed(1) + 'M';
                if (x >= 1e3) return 'SAR ' + (x / 1e3).toFixed(1) + 'k';
                return 'SAR ' + x.toFixed(0);
            };

            (() => {
                let currentEstimator = '';
                const $year = $('#filterYear'), $month = $('#filterMonth'), $from = $('#filterFrom'),
                    $to = $('#filterTo');

                // =========================
                // Build Estimator pills
                // =========================
                fetch('{{ route('estimation.estimators') }}')
                    .then(r => r.json())
                    .then(list => {
                        const ul = document.getElementById('estimatorPills');
                        ul.innerHTML = `
          <li class="nav-item"><button class="nav-link est-pill active" data-estimator="">All</button></li>
          ${list.map(n => `<li class="nav-item"><button class="nav-link est-pill" data-estimator="${n}">${n}</button></li>`).join('')}
        `;
                        ul.addEventListener('click', (e) => {
                            const btn = e.target.closest('button[data-estimator]');
                            if (!btn) return;
                            ul.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            currentEstimator = btn.getAttribute('data-estimator') || '';
                            reloadAll();
                        });
                        reloadAll();
                    });

                // =========================
                // DataTables
                // =========================
                const money = v => 'SAR ' + Number(v || 0).toLocaleString();
                const dtAll = $('#dtAll').DataTable({
                    processing: true, serverSide: true, lengthChange: true, order: [[0, 'desc']],
                    ajax: {url: '{{ route('estimation.datatable.all') }}', data: d => Object.assign(d, buildFilters())},
                    columns: [
                        {data: 'id', width: 60}, {data: 'project_name'}, {data: 'client_name'}, {data: 'area'},
                        {data: 'atai_products'}, {data: 'quotation_value', className: 'text-end', render: money},
                        {data: 'status'}, {data: 'estimator'}, {data: 'created_at'}
                    ]
                });
                const dtRegion = $('#dtRegion').DataTable({
                    processing: true, serverSide: true, order: [[1, 'desc']],
                    ajax: {
                        url: '{{ route('estimation.datatable.region') }}',
                        data: d => Object.assign(d, buildFilters())
                    },
                    columns: [{data: 'region'}, {data: 'cnt', className: 'text-end'}, {
                        data: 'val',
                        className: 'text-end',
                        render: money
                    }]
                });
                const dtProduct = $('#dtProduct').DataTable({
                    processing: true, serverSide: true, order: [[1, 'desc']],
                    ajax: {
                        url: '{{ route('estimation.datatable.product') }}',
                        data: d => Object.assign(d, buildFilters())
                    },
                    columns: [{data: 'product'}, {data: 'cnt', className: 'text-end'}, {
                        data: 'val',
                        className: 'text-end',
                        render: money
                    }]
                });

                // =========================
                // KPIs / Charts
                // =========================
                function loadKpis() {
                    const qs = new URLSearchParams(buildFilters()).toString();

                    fetch(`{{ route('estimation.kpis') }}?${qs}`)
                        .then(r => r.json())
                        .then(payload => {
                            // 0) Helper mappers + safe fallbacks
                            const estimatorPieRaw = Array.isArray(payload?.estimatorPie) ? payload.estimatorPie : [];
                            const statusPieRaw = Array.isArray(payload?.statusPie) ? payload.statusPie : [];

                            // If backend sends [{name, value, count}] for ALL mode:
                            const dataAll = estimatorPieRaw.map(p => ({
                                name: p.name,
                                y: metric === 'count' ? Number(p.count ?? p.y ?? 0) : Number(p.value ?? p.y ?? 0),
                                __value: Number(p.value ?? 0),
                                __count: Number(p.count ?? 0),
                            }));

                            // If backend sends [{name, value, count}] (SINGLE mode Bidding/In-Hand)
                            const dataSingle = statusPieRaw.map(p => ({
                                name: p.name,
                                y: metric === 'count' ? Number(p.count ?? p.y ?? 0) : Number(p.value ?? p.y ?? 0),
                                __value: Number(p.value ?? 0),
                                __count: Number(p.count ?? 0),
                            }));

                            // 1) Total badge (metric-aware)
                            if (metric === 'value') {
                                document.getElementById('kpi-total-value').textContent =
                                    fmtSAR(payload?.totals?.value || 0);
                            } else {
                                document.getElementById('kpi-total-value').textContent =
                                    'Total Projects: ' + Number(payload?.totals?.count || 0).toLocaleString();
                            }

                            // 2) Estimator PIE
                            const titleEl = document.getElementById('kpi-title');
                            if (payload.mode === 'all') {
                                if (titleEl) titleEl.textContent =
                                    (metric === 'count') ? 'By Estimator (share of count)' : 'By Estimator (share of value)';

                                Highcharts.chart('chart-estimator', {
                                    chart: {type: 'pie', backgroundColor: 'transparent', spacing: [10, 10, 10, 10]},
                                    title: {text: null}, credits: {enabled: false},
                                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185'],
                                    tooltip: {
                                        useHTML: true,
                                        backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                                        style: {color: '#E8F0FF', fontSize: '13px'},
                                        pointFormatter: function () {
                                            const v = fmtSAR(this.options.__value ?? this.y);
                                            const c = Number(this.options.__count ?? this.y).toLocaleString();
                                            const pct = Highcharts.numberFormat(this.percentage, 1) + '%';
                                            const body = (metric === 'count')
                                                ? `Projects: <b>${c}</b> &nbsp;•&nbsp; Share: <b>${pct}</b><br/>Value: <b>${v}</b>`
                                                : `Value: <b>${v}</b> &nbsp;•&nbsp; Share: <b>${pct}</b><br/>Projects: <b>${c}</b>`;
                                            return `<div style="margin:2px 0;"><span style="color:${this.color}">●</span> ${this.name}<br/>${body}</div>`;
                                        }
                                    },
                                    plotOptions: {
                                        pie: {
                                            allowPointSelect: true, size: '80%', borderWidth: 0, shadow: false,
                                            dataLabels: {
                                                enabled: true, distance: 20, softConnector: true, connectorWidth: 1.3,
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
                                        itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '13px'},
                                        itemHoverStyle: {color: '#FFFFFF'}
                                    },
                                    series: [{
                                        name: metric === 'count' ? 'Projects' : 'Value',
                                        colorByPoint: true,
                                        data: dataAll
                                    }],
                                    lang: {noData: 'No estimator data available.'},
                                    noData: {style: {fontSize: '14px', color: '#E0E7FF', fontWeight: 600}}
                                });

                            } else {
                                if (titleEl) titleEl.textContent = `${currentEstimator || 'Estimator'} — By Status`;

                                Highcharts.chart('chart-estimator', {
                                    chart: {type: 'pie', backgroundColor: 'transparent', spacing: [10, 10, 10, 10]},
                                    title: {text: null}, credits: {enabled: false},
                                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185', '#22d3ee', '#a3e635'],
                                    tooltip: {
                                        useHTML: true,
                                        backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                                        style: {color: '#E8F0FF', fontSize: '13px'},
                                        pointFormatter: function () {
                                            const c = Number(this.options.__count ?? this.y).toLocaleString();
                                            const v = fmtSAR(this.options.__value ?? this.y);
                                            const pct = Highcharts.numberFormat(this.percentage, 1) + '%';
                                            return `
                    <div style="margin:2px 0;">
                      <span style="color:${this.color}">●</span>
                      <b>${this.name}</b><br/>
                      Projects: <b>${c}</b> &nbsp;•&nbsp; Share: <b>${pct}</b><br/>
                      Value: <b>${v}</b>
                    </div>`;
                                        }
                                    },
                                    plotOptions: {
                                        pie: {
                                            allowPointSelect: true, size: '80%', borderWidth: 0, shadow: false,
                                            dataLabels: {
                                                enabled: true, distance: 20, softConnector: true, connectorWidth: 1.3,
                                                connectorColor: 'rgba(255,255,255,0.35)',
                                                style: {
                                                    color: '#E8F0FF',
                                                    fontWeight: 600,
                                                    fontSize: '14px',
                                                    textOutline: '2px rgba(0,0,0,0.6)'
                                                },
                                                formatter: function () {
                                                    const c = Number(this.options.__count ?? this.y);
                                                    const pct = Highcharts.numberFormat(this.percentage, 1);
                                                    return `${this.point.name}: <b>${c.toLocaleString()}</b> <span style="opacity:.85">(${pct}%)</span>`;
                                                }
                                            }
                                        }
                                    },
                                    legend: {
                                        enabled: true,
                                        itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '13px'},
                                        itemHoverStyle: {color: '#FFFFFF'}
                                    },
                                    series: [{
                                        name: metric === 'count' ? 'Projects' : 'Value',
                                        colorByPoint: true,
                                        data: dataSingle
                                    }],
                                    lang: {noData: 'No estimator data available.'},
                                    noData: {style: {fontSize: '14px', color: '#E0E7FF', fontWeight: 600}}
                                });
                            }

                            // 3) Monthly Region (supports new payload.monthlyRegion.series_value / series_count)
                            const mr = payload.monthlyRegion || {};
                            const cats = Array.isArray(mr.categories) ? mr.categories : [];
                            const seriesValue = Array.isArray(mr.series_value) ? mr.series_value : (mr.series || []); // fallback
                            const seriesCount = Array.isArray(mr.series_count) ? mr.series_count : null;

                            const mrSeries = (metric === 'count' && seriesCount) ? seriesCount : seriesValue;

                            Highcharts.chart('chartMonthlyRegion', {
                                chart: {type: 'column', backgroundColor: 'transparent', spacing: [10, 20, 10, 20]},
                                title: {text: null}, credits: {enabled: false},
                                colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#22d3ee', '#a3e635', '#fb7185'],
                                xAxis: {
                                    categories: cats,
                                    lineColor: 'rgba(255,255,255,.15)',
                                    tickColor: 'rgba(255,255,255,.15)',
                                    labels: {style: {color: '#C7D2FE', fontWeight: 600, fontSize: '13px'}}
                                },
                                yAxis: [{
                                    title: {
                                        text: (metric === 'count') ? 'Projects (count)' : 'Value (SAR)',
                                        style: {color: '#C7D2FE', fontWeight: 700, fontSize: '13px'}
                                    },
                                    min: 0,
                                    gridLineColor: 'rgba(255,255,255,.10)',
                                    labels: {
                                        style: {color: '#E0E7FF', fontWeight: 600, fontSize: '12px'},
                                        formatter() {
                                            return (metric === 'count')
                                                ? Number(this.value).toLocaleString()
                                                : fmtSAR(this.value);
                                        }
                                    }
                                }],
                                legend: {
                                    itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '13px'},
                                    itemHoverStyle: {color: '#FFFFFF'}
                                },
                                tooltip: {
                                    shared: true, useHTML: true,
                                    backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                                    style: {color: '#E8F0FF', fontSize: '13px'},
                                    formatter() {
                                        const header = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                                        const lines = this.points.map(p => {
                                            const val = (metric === 'count')
                                                ? Number(p.y || 0).toLocaleString()
                                                : fmtSAR(p.y);
                                            return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${val}</b></div>`;
                                        });
                                        return header + lines.join('');
                                    }
                                },
                                plotOptions: {
                                    column: {
                                        borderWidth: 0, borderRadius: 3, pointPadding: 0.05, groupPadding: 0.18,
                                        states: {hover: {brightness: 0.08}},
                                        dataLabels: {
                                            enabled: true, rotation: -90, align: 'center', verticalAlign: 'bottom',
                                            inside: false, y: -10, crop: false, overflow: 'none',
                                            style: {color: '#E8F0FF', fontSize: '14px'},
                                            formatter() {
                                                const v = Number(this.y || 0);
                                                if (!v) return '';
                                                if (metric === 'count') return v.toLocaleString();
                                                return (v >= 1e9) ? `SAR ${(v / 1e9).toFixed(1).replace(/\.0$/, '')}B`
                                                    : (v >= 1e6) ? `SAR ${(v / 1e6).toFixed(1).replace(/\.0$/, '')}M`
                                                        : (v >= 1e3) ? `SAR ${(v / 1e3).toFixed(1).replace(/\.0$/, '')}K`
                                                            : `SAR ${v.toFixed(0)}`;
                                            }
                                        }
                                    }
                                },
                                series: mrSeries
                            });

                            // 4) By Product (Top 10) — supports productSeries.value / productSeries.count
                            const ps = payload.productSeries || {};
                            const prodCats = ps.categories || [];
                            const prodVal = Array.isArray(ps.value) ? ps.value : (ps.values || []);  // fallback: old 'values'
                            const prodCnt = Array.isArray(ps.count) ? ps.count : null;
                            const prodData = (metric === 'count' && prodCnt) ? prodCnt : prodVal;

                            // quick label tweak
                            const prodTitleEl = document.querySelector('.card.kpi-card .fw-semibold.mb-2');
                            if (prodTitleEl) prodTitleEl.textContent =
                                (metric === 'count') ? 'By Product (Top 10 — Count)' : 'By Product (Top 10)';

                            Highcharts.chart('chartProduct', {
                                chart: {type: 'column', backgroundColor: 'transparent', spacing: [10, 20, 10, 20]},
                                title: {text: null}, credits: {enabled: false},
                                colors: ['#60a5fa'],
                                xAxis: {
                                    categories: prodCats,
                                    lineColor: 'rgba(255,255,255,.15)',
                                    tickColor: 'rgba(255,255,255,.15)',
                                    labels: {rotation: 0, style: {color: '#C7D2FE', fontWeight: 600, fontSize: '13px'}}
                                },
                                yAxis: {
                                    title: {
                                        text: (metric === 'count') ? 'Count' : 'Value (SAR)',
                                        style: {color: '#C7D2FE', fontWeight: 700, fontSize: '13px'}
                                    },
                                    min: 0, gridLineColor: 'rgba(255,255,255,.10)',
                                    labels: {
                                        style: {color: '#E0E7FF', fontWeight: 600, fontSize: '12px'},
                                        formatter() {
                                            return (metric === 'count') ? Number(this.value).toLocaleString() : fmtCompactSAR(this.value);
                                        }
                                    }
                                },
                                legend: {enabled: false},
                                tooltip: {
                                    shared: false, useHTML: true,
                                    backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                                    style: {color: '#E8F0FF', fontSize: '13px'},
                                    pointFormatter() {
                                        return `<b>${this.category}</b><br/>${
                                            metric === 'count'
                                                ? ('Count: <b>' + Number(this.y).toLocaleString() + '</b>')
                                                : ('Value: <b>' + fmtCompactSAR(this.y) + '</b>')
                                        }`;
                                    }
                                },
                                plotOptions: {
                                    column: {
                                        borderWidth: 0, borderRadius: 3, pointPadding: 0.05, groupPadding: 0.18,
                                        states: {hover: {brightness: 0.08}},
                                        dataLabels: {
                                            enabled: true, rotation: -90, align: 'center', verticalAlign: 'bottom',
                                            inside: false, y: -10, crop: false, overflow: 'none',
                                            style: {
                                                color: '#E8F0FF',
                                                fontWeight: 700,
                                                fontSize: '12px',
                                                textOutline: '2px rgba(0,0,0,.7)'
                                            },
                                            formatter() {
                                                if (!this.y) return '';
                                                return (metric === 'count') ? Number(this.y).toLocaleString() : `SAR ${fmtCompactSAR(this.y)}`;
                                            }
                                        }
                                    }
                                },
                                series: [{name: (metric === 'count') ? 'Count' : 'Value', data: prodData}]
                            });
                        })
                        .catch(err => console.error('KPIs load failed:', err));
                }

                // =========================
                // Helpers / Events
                // =========================
                function buildFilters() {
                    return {
                        estimator: currentEstimator,
                        year: $year.val() || '',
                        month: $month.val() || '',
                        from: $from.val() || '',
                        to: $to.val() || '',
                        metric // sent to backend
                    };
                }

                // Radio change (expects inputs like: <input type="radio" name="metric" value="value|count">)
                document.getElementById('metricToggle')?.addEventListener('change', (e) => {
                    const inp = e.target.closest('input[name="metric"]');
                    if (!inp) return;
                    metric = inp.value === 'count' ? 'count' : 'value';
                    // Immediate label tweaks
                    document.getElementById('kpi-title').textContent =
                        (metric === 'value') ? 'By Estimator (share of value)' : 'By Estimator (share of count)';
                    reloadAll();
                });

                function reloadAll() {
                    loadKpis();
                    dtAll.ajax.reload(null, false);
                    dtRegion.ajax.reload(null, false);
                    dtProduct.ajax.reload(null, false);
                }

                $('#applyFilters').on('click', reloadAll);
                $('#clearFilters').on('click', () => {
                    $year.val('');
                    $month.val('');
                    $from.val('');
                    $to.val('');
                    reloadAll();
                });
            })();
        </script>
    @endpush
