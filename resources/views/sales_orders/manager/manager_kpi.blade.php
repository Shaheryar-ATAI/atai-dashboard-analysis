@extends('layouts.app')

@section('title', 'ATAI Projects — Live')
@push('head')
    <meta charset="utf-8">
    <meta name="kpi-url" content="{{ route('salesorders.manager.kpis') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Order Log KPI — ATAI</title>
    @php
        $userRegion = strtolower(auth()->user()->region ?? '');
    @endphp
    <meta name="user-region" content="{{ $userRegion }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}">

    <style>
        .badge-total {
            font-weight: 600;
            padding: .45rem .75rem;
            border-radius: 12px
        }

        .btn-chip.btn-outline-primary.active {
            background: #198754;
            border-color: #198754;
            color: #fff
        }

        #toolbar .form-select-sm, #toolbar .form-control-sm {
            width: auto
        }

        /* ===== Switcher & panels ===== */
        #kpiSwitcher {
            position: relative;
        }

        #panelChart {
            position: relative;
            z-index: 1;
        }

        #kpi_status_monthly {
            height: 420px;
        }

        .kpi-toggle-btn {
            position: relative; /* stays in flow */
            z-index: 3;
            pointer-events: auto;
        }

        /* Cards panel scrolls internally */
        #panelCards {
            max-height: 440px;
            overflow-y: auto;
            padding-right: 6px;
        }

        #panelCards .kpi-cards-head {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bs-body-bg);
            padding: .25rem 0 .5rem;
        }

        /* ===== KPI cards grid ===== */
        #monthBoard.months-wrap {
            display: grid;
            grid-template-columns: repeat(3, minmax(260px, 1fr));
            gap: 12px;
        }

        .kpi-card {
            border-radius: 12px;
        }

        .kpi-value {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            font-weight: 800;
        }

        .month-card {
            background: rgba(255, 255, 255, .03);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 12px;
            padding: 12px;
        }

        .month-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .month-title {
            font-weight: 700;
            opacity: .95;
        }

        .month-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .month-kpi {
            background: rgba(255, 255, 255, .045);
            border-radius: 8px;
            padding: 8px;
        }

        .month-kpi .label {
            font-size: 12px;
            opacity: .75;
        }

        .month-kpi .val {
            font-weight: 700;
            font-size: 13px;
        }

        .month-kpi.ok {
            outline: 1px solid rgba(34, 197, 94, .35);
        }

        .month-kpi.warn {
            outline: 1px solid rgba(245, 158, 11, .35);
        }

        .month-kpi.dang {
            outline: 1px solid rgba(239, 68, 68, .35);
        }

        /* ===== Status chips ===== */
        #statusChips.pill-chips {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        /* Base chip */
        #statusChips.pill-chips .btn {
            border-radius: 9999px !important;
            padding: .35rem .85rem;
            font-weight: 700;
            letter-spacing: .2px;
            background: rgba(255, 255, 255, .10) !important;
            border: 1px solid rgba(255, 255, 255, .16) !important;
            color: #e6e9ff !important;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .25);
            transition: background .15s ease, border-color .15s ease, transform .06s ease, color .15s ease;
        }

        #statusChips.pill-chips .btn:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, .16) !important;
            border-color: rgba(255, 255, 255, .24) !important;
        }

        #statusChips.pill-chips .btn:focus-visible {
            outline: 2px solid rgba(149, 197, 61, .45);
            outline-offset: 2px;
        }

        /* Selected chip (single source of truth) */
        #statusChips .btn.active,
        #statusChips [aria-pressed="true"] {

            background: linear-gradient(180deg, rgba(149, 197, 61, .30), rgba(149, 197, 61, .22)) !important;
            border-color: rgba(149, 197, 61, .45) !important;
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(149, 197, 61, .2
            position: relative;
        }


        /* ===== Responsive ===== */
        @media (max-width: 992px) {
            #monthBoard.months-wrap {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
    </style>

@endpush
@section('content')
    @php $u = auth()->user(); @endphp
    <main class="container-fluid py-4">

        {{--    <div id="toolbar" class="d-flex flex-wrap align-items-center gap-2 mb-3">--}}
        {{--            <select id="fYear" class="form-select form-select-sm" style="width:auto">--}}
        {{--                <option value="">All Years</option>--}}
        {{--                @for ($y = date('Y'); $y >= date('Y')-6; $y--) <option value="{{ $y }}">{{ $y }}</option> @endfor--}}
        {{--            </select>--}}
        {{--            <select id="fMonth" class="form-select form-select-sm">--}}
        {{--                <option value="">All Months</option>--}}
        {{--                @for($m=1;$m<=12;$m++)--}}
        {{--                    <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>--}}
        {{--                @endfor--}}
        {{--            </select>--}}
        {{--            <input id="fFrom" type="date" class="form-control form-control-sm" style="width:auto">--}}
        {{--            <input id="fTo" type="date" class="form-control form-control-sm" style="width:auto">--}}
        {{--            <button id="btnApply" class="btn btn-primary btn-sm">Update</button>--}}
        {{--        </div>--}}


        <div id="toolbar" class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <select id="fYear" class="form-select form-select-sm">
                <option value="">All Years</option>
                @for($y=(int)date('Y');$y>=(int)date('Y')-6;$y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <select id="fMonth" class="form-select form-select-sm">
                <option value="">All Months</option>
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>
                @endfor
            </select>

            <input type="date" id="fFrom" class="form-control form-control-sm" placeholder="From">
            <input type="date" id="fTo" class="form-control form-control-sm" placeholder="To">

            <button id="btnApply" class="btn btn-primary btn-sm">Update</button>
        </div>
        <div class="row g-3 mb-4 text-center align-items-stretch">
            <div class="col-12 col-md-4">
                <div class="kpi-card shadow-sm p-4 h-100">
                    <div id="badgeCount" class="kpi-value">Total Sales-Order No.: 0</div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="kpi-card shadow-sm p-4 h-100">
                    <div id="badgeTotal" class="kpi-value">Total Sales-Order Value: SAR 0</div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="kpi-card shadow-sm p-4 h-100">
                    <div id="badgeTarget" class="kpi-value text-success">Monthly Target: SAR 0</div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center mb-2">
            <div id="familyChips" class="btn-group pill-chips" role="group" aria-label="Families"></div>
        </div>
        <div class="d-flex justify-content-end mb-2">
            <div id="statusChips" class="btn-group pill-chips" role="group" aria-label="Statuses"></div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="card kpi-card">
                    <div class="card-body">
                        <div id="hcMonthly" class="hc"></div>
                        <div id="barPoValueByArea" class="hc" style="height:100px"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Switcher: LEFT = status chart, RIGHT = monthly KPI cards -->
        <div class="col-12 mt-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Sales Order Status — Monthly</div>

                    <div id="kpiSwitcher" class="kpi-switcher">
                        <button id="kpiToggleBtn" type="button"
                                class="btn btn-outline-warning btn-sm kpi-toggle-btn"
                                title="Switch chart/cards">Show Cards
                        </button>
                        <!-- Panel A: chart (active by default) -->
                        <div id="panelChart" class="kpi-panel kpi-panel--active">
                            <div id="kpi_status_monthly" style="height:420px"></div>
                        </div>
                        <!-- Panel B: monthly KPI cards -->
                        <div id="panelCards" class="kpi-panel">

                            <div id="monthBoard" class="months-wrap"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card kpi-card mt-3">
            <div class="card-body">
                <div class="fw-semibold mb-2">Products Comparison Monthly progress</div>
                <div id="hcProductClusterMonthly" class="hc"></div>
            </div>
        </div>
    </main>
@endsection
@push('scripts')
    <script>

        let isLoading = false;       // prevent re-entrancy
        let didInitFamily = false;   // init the default chip only once
        let didInitStatus = false;   // init the default chip only once
        (() => {
            const fmtSAR = n => 'SAR ' + Math.round(Number(n || 0)).toLocaleString();
            const USER_REGION = document.querySelector('meta[name="user-region"]')?.content || '';
            const FALLBACK_PRODUCTS = ['Ductwork', 'Dampers', 'Sound Attenuators', 'Accessories'];

            // ✅ default landing year
            const DEFAULT_YEAR = '2025';
// 👇 region-based annual target (matches backend)
            const REGION_TARGETS = {
                eastern: 35_000_000,
                central: 37_000_000,
                western: 30_000_000
            };

            let currentFamily = '';
            let currentStatus = '';
            let currentRegion = '';


            function filters() {
                const f = {
                    year: $('#fYear').val() || DEFAULT_YEAR,
                    month: $('#fMonth').val() || '',
                    from: $('#fFrom').val() || '',
                    to: $('#fTo').val() || '',
                    region: currentRegion || ''
                };
                if (currentFamily) f.family = currentFamily;
                if (currentStatus) f.status = currentStatus;  // ← only if not ''
                return f;
            }

            function buildChips(containerId, items, activeVal, onChange, opts = {includeAll: true}) {
                const el = document.getElementById(containerId);
                if (!el) return;

                const includeAll = !!opts.includeAll;
                const selected = (activeVal && items.includes(activeVal))
                    ? activeVal
                    : (includeAll ? '' : (items[0] || ''));

                // base class for every chip; selection is expressed via .active only
                const chip = (name, isActive) =>
                    `<button type="button"
             class="btn btn-sm ${isActive ? 'active' : ''}"
             data-val="${name}"
             aria-pressed="${isActive ? 'true' : 'false'}">${name || 'All'}</button>`;

                const html = [
                    ...(includeAll ? [chip('', selected === '')] : []),
                    ...items.map(name => chip(name, selected === name))
                ].join('');

                el.innerHTML = html;

                // Click handler: flip .active locally, then notify
                el.onclick = (e) => {
                    const btn = e.target.closest('button[data-val]');
                    if (!btn) return;

                    const val = btn.getAttribute('data-val') ?? '';
                    if (val === activeVal) return; // no-op if same selection

                    el.querySelectorAll('.btn').forEach(b => {
                        b.classList.remove('active');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn.classList.add('active');
                    btn.setAttribute('aria-pressed', 'true');

                    onChange(val);
                };
            }


// compute monthly target for the logged-in region
            const USER_TARGET_MONTHLY = (REGION_TARGETS[USER_REGION] || 0) / 12;

            async function loadKPIs() {
                if (isLoading) return;           // 🔒 guard
                isLoading = true;

                try {
                    const qs = new URLSearchParams(filters()).toString();
                    const res = await fetch(`{{ route('salesorders.manager.kpis') }}?${qs}`, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const payload = res.ok ? await res.json() : {};

                    let KPI_CACHE = null;
                    // badges
                    document.getElementById('badgeCount').textContent =
                        'Total Sales-Order No.: ' + Number(payload?.totals?.count || 0).toLocaleString();
                    document.getElementById('badgeTotal').textContent =
                        'Total Sales-Order Value: ' + fmtSAR(payload?.totals?.value || 0);

                    // families
                    const fams = Array.isArray(payload?.allFamilies) && payload.allFamilies.length
                        ? payload.allFamilies
                        : FALLBACK_PRODUCTS;

                    // initialize default ONCE (don’t call loadKPIs here)
                    if (!didInitFamily) {
                        currentFamily = '';
                        didInitFamily = true;
                    }
                    buildChips(
                        'familyChips',
                        fams,
                        currentFamily,
                        (val) => {
                            if (val === currentFamily) return;
                            currentFamily = val;
                            loadKPIs();               // user-triggered reload
                        },
                        {includeAll: true}
                    );

                    // statuses
                    const stats = Array.isArray(payload?.allStatuses) && payload.allStatuses.length
                        ? payload.allStatuses
                        : ['Accepted', 'Pre-Acceptance', 'Waiting', 'Rejected', 'Cancelled', 'Unknown'];

                    if (!didInitStatus) {
                        currentStatus = '';               // ← All
                        didInitStatus = true;
                    }
                    buildChips(
                        'statusChips',
                        stats,              // e.g., ["Accepted","Pre-Acceptance","Waiting","Rejected","Cancelled","Unknown"]
                        currentStatus,      // '' means All
                        (val) => {          // onChange
                            if (val === currentStatus) return;
                            currentStatus = val;   // '' = All
                            loadKPIs();
                        },
                        {includeAll: true} // ← shows an All chip in front
                    );

                    // monthly simple chart
                    const cats = payload.monthly?.categories || [];
                    const vals = payload.monthly?.values || [];
                    const mom = vals.map((v, i) => i === 0 || !vals[i - 1] ? 0 : Math.round(((v - vals[i - 1]) / vals[i - 1]) * 10000) / 100);

                    Highcharts.chart('hcMonthly', {
                        chart: {backgroundColor: 'transparent', spacing: [10, 20, 10, 20]},
                        title: {
                            text: 'Sales Order Monthly Comparison',
                            align: 'left',
                            style: {color: '#95c53d', fontSize: '16px', fontWeight: '700'},
                            margin: 10
                        },
                        credits: {enabled: false}, colors: ['#60a5fa', '#f59e0b'],
                        xAxis: {
                            categories: cats.map(ym => {
                                const [y, m] = (ym || '').split('-');
                                return (y && m) ? new Date(y, m - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2) : ym;
                            }),
                            lineColor: 'rgba(255,255,255,.15)',
                            tickColor: 'rgba(255,255,255,.15)',
                            labels: {style: {color: '#C7D2FE', fontSize: '13px', fontWeight: 600}}
                        },
                        yAxis: [{
                            title: {text: 'Value (SAR)', style: {color: '#C7D2FE', fontWeight: 700, fontSize: '13px'}},
                            min: 0,
                            gridLineColor: 'rgba(255,255,255,.10)',
                            labels: {
                                style: {color: '#E0E7FF', fontWeight: 600, fontSize: '13px'}, formatter() {
                                    return fmtCompactSAR(this.value);
                                }
                            }
                        },
                            {
                                title: {
                                    text: 'Percent (%)',
                                    style: {color: '#F59E0B', fontWeight: 700, fontSize: '13px'}
                                }, opposite: true, min: 0, gridLineColor: 'transparent',
                                labels: {
                                    style: {color: '#FBBF24', fontWeight: 600, fontSize: '12px'}, formatter() {
                                        return fmtPct(this.value);
                                    }
                                }
                            }],
                        legend: {align: 'center', itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '13px'}},
                        tooltip: {
                            shared: false,
                            useHTML: true,
                            backgroundColor: 'rgba(190,190,190,0.95)',
                            borderColor: '#090909',
                            borderRadius: 8,
                            style: {color: '#050505', fontSize: '13px'},
                            formatter() {
                                const h = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                                const body = this.series.yAxis.opposite ? `${this.series.name}: <b>${fmtPct(this.y)}</b>` : `${this.series.name}: <b>SAR ${fmtCompactSAR(this.y)}</b>`;
                                return h + body;
                            }
                        },
                        plotOptions: {
                            column: {
                                borderWidth: 0, borderRadius: 3, pointPadding: 0.06, groupPadding: 0.18,
                                dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -10,
                                    crop: false,
                                    overflow: 'none',
                                    style: {color: '#E8F0FF', fontSize: '15px'},
                                    formatter() {
                                        return this.y > 0 ? `SAR ${fmtCompactSAR(this.y)}` : '';
                                    }
                                }
                            },
                            spline: {
                                lineWidth: 3,
                                marker: {
                                    enabled: true,
                                    radius: 4,
                                    fillColor: '#fff',
                                    lineColor: '#f59e0b',
                                    lineWidth: 2
                                },
                                dataLabels: {
                                    enabled: true,
                                    y: -6,
                                    style: {
                                        color: '#FBBF24',
                                        fontWeight: 700,
                                        fontSize: '12px',
                                        textOutline: '2px rgba(0,0,0,.55)'
                                    },
                                    formatter() {
                                        return fmtPct(this.y);
                                    }
                                }
                            }
                        },
                        series: [
                            {type: 'column', name: 'Value (SAR)', data: vals},
                            {
                                type: 'line',
                                name: 'Target (' + USER_REGION.toUpperCase() + ')',
                                color: '#22c55e',
                                dashStyle: 'ShortDash',
                                data: Array(vals.length).fill(USER_TARGET_MONTHLY)
                            }
                        ]
                    });

                    document.getElementById('badgeTarget').textContent =
                        'Monthly Target (' + USER_REGION.toUpperCase() + '): ' + fmtSAR(USER_TARGET_MONTHLY);

                    // other renders
                    renderStatusMonthlyChart(payload.multiMonthly);
                    initStatusKpiSwitcher(payload.multiMonthly);
                    renderMonthlyProductValue(payload.monthly_product_value);
                } finally {
                    isLoading = false;   // 🔓 unlock
                }
            }


            function fmtCompactSAR(val) {
                if (val == null || isNaN(val)) return '';
                if (Math.abs(val) >= 1_000_000_000) return (val / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'B';
                if (Math.abs(val) >= 1_000_000) return (val / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                if (Math.abs(val) >= 1_000) return (val / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
                return Number(val).toLocaleString();
            }

            function fmtPct(v) {
                if (v == null || isNaN(v)) return '';
                return Number(v).toFixed(1).replace(/\.0$/, '') + '%';
            }

            // events
            $('#btnApply').on('click', loadKPIs);
            if (!$('#fYear').val()) $('#fYear').val(DEFAULT_YEAR);
            loadKPIs();


            // ===== status chart (for switcher) =====
            function renderStatusMonthlyChart(multiMonthly) {
                const catsIso = multiMonthly?.categories || [];
                const catsNice = catsIso.map(ym => {
                    const [y, m] = String(ym || '').split('-');
                    if (!y || !m) return ym || '';
                    return new Date(Number(y), Number(m) - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
                });

                const barSeries = multiMonthly?.bars || [];

                // --- compute Accepted MoM % (unchanged) ---
                const acceptedBars = barSeries.find(s => String(s.name).toLowerCase() === 'accepted');
                const acceptedVals = acceptedBars?.data || [];
                const acceptedMoM = [];
                let prev = null;
                for (let i = 0; i < acceptedVals.length; i++) {
                    const cur = Number(acceptedVals[i] || 0);
                    let pct = 0;
                    if (prev !== null && prev > 0) {
                        pct = ((cur - prev) / prev) * 100;
                        if (pct > 300) pct = 300;
                        if (pct < -300) pct = -300;
                    }
                    acceptedMoM.push(Math.round(pct * 10) / 10);
                    prev = cur;
                }

                // === PATCH: monthly totals across all STATUS columns ===
                const xCount = catsNice.length;
                const monthTotals = Array(xCount).fill(0);
                barSeries.forEach(s => {
                    // safety: only sum column series
                    if ((s.type || 'column') === 'column') {
                        for (let i = 0; i < xCount; i++) {
                            monthTotals[i] += Number(s.data?.[i] || 0);
                        }
                    }
                });

                Highcharts.chart('kpi_status_monthly', {
                    chart: {backgroundColor: 'transparent', spacing: [10, 20, 10, 20]},
                    title: {text: null}, credits: {enabled: false},
                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185', '#94a3b8'],

                    xAxis: {
                        categories: catsNice, lineColor: 'rgba(255,255,255,.14)', tickColor: 'rgba(255,255,255,.14)',
                        labels: {style: {color: '#C7D2FE', fontSize: '13px', fontWeight: 600}}
                    },

                    yAxis: [{
                        title: {text: 'Value (SAR)', style: {color: '#C7D2FE', fontSize: '13px', fontWeight: 700}},
                        min: 0,
                        gridLineColor: 'rgba(255,255,255,.12)',
                        labels: {
                            style: {color: '#E0E7FF', fontSize: '12px', fontWeight: 600},
                            formatter() {
                                return 'SAR ' + Highcharts.numberFormat(this.value, 0);
                            }
                        }
                    }, {
                        title: {text: 'Accepted MoM (%)', style: {color: '#F59E0B', fontSize: '13px', fontWeight: 700}},
                        opposite: true, min: 0, gridLineColor: 'transparent',
                        labels: {
                            style: {color: '#FBBF24', fontWeight: 600, fontSize: '12px'},
                            formatter() {
                                return Highcharts.numberFormat(this.value, 1) + '%';
                            }
                        }
                    }],

                    legend: {itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '13px'}},

                    tooltip: {
                        shared: true,
                        useHTML: true,
                        backgroundColor: 'rgba(10,15,45,0.95)',
                        borderColor: '#334155',
                        borderRadius: 8,
                        style: {color: '#E8F0FF', fontSize: '13px'},
                        formatter() {
                            const h = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                            return h + this.points.map(p => {
                                const isPercent = p.series.yAxis && p.series.yAxis.opposite; // MoM line
                                const val = isPercent
                                    ? Highcharts.numberFormat(p.y || 0, 1) + '%'
                                    : 'SAR ' + Highcharts.numberFormat(p.y || 0, 0);
                                return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${val}</b></div>`;
                            }).join('');
                        }
                    },

                    plotOptions: {
                        column: {
                            grouping: true, groupPadding: 0.14, pointPadding: 0.04, borderWidth: 0, borderRadius: 3,
                            // === PATCH: show % share inside each column ===
                            dataLabels: {
                                enabled: true,
                                inside: true,
                                style: {
                                    color: '#E8F0FF',
                                    fontWeight: 700,
                                    fontSize: '12px',
                                    textOutline: '1px rgba(0,0,0,.6)'
                                },
                                formatter: function () {
                                    const total = monthTotals[this.point.x] || 0;
                                    if (!total) return '';
                                    const pct = (this.y / total) * 100;
                                    return Highcharts.numberFormat(pct, 1) + '%';
                                }
                            }
                        },
                        spline: {
                            lineWidth: 3, marker: {enabled: false},
                            dataLabels: {
                                enabled: true, y: -8,
                                style: {
                                    color: '#FBBF24',
                                    fontWeight: 700,
                                    fontSize: '12px',
                                    textOutline: '1px rgba(0,0,0,.45)'
                                },
                                formatter() {
                                    return Highcharts.numberFormat(this.y || 0, 1) + '%';
                                }
                            }
                        }
                    },

                    series: [
                        ...barSeries,
                        {
                            type: 'spline',
                            name: 'Accepted MoM %',
                            yAxis: 1,
                            data: acceptedMoM,
                            dashStyle: 'ShortDot',
                            color: '#FBBF24'
                        }
                    ]
                });
            }


            // ===== cards + switcher logic (uses multiMonthly only) =====
            function initStatusKpiSwitcher(mv) {
                const wrap = document.getElementById('kpiSwitcher');
                const panelChart = document.getElementById('panelChart');
                const panelCards = document.getElementById('panelCards');
                const btn = document.getElementById('kpiToggleBtn');
                if (!wrap || !panelChart || !panelCards || !btn) return;

                // ---- source arrays from backend ----
                const srcMonths = mv?.categories || [];
                const series = mv?.bars || [];

                const arrFor = (name) =>
                    series.find(s => String(s.name).toLowerCase() === name.toLowerCase())?.data || [];

                const aAccepted = arrFor('Accepted');
                const aPreAcceptance = arrFor('Pre-Acceptance');
                const aWaiting = arrFor('Waiting');
                const aRejected = arrFor('Rejected');
                const aCancelled = arrFor('Cancelled');
                const aUnknown = arrFor('Unknown');

                // ---- map months -> values ----
                const monthMap = {}; // { "YYYY-MM": {accepted, preacc, waiting, rejected, cancelled, unknown, total} }
                srcMonths.forEach((ym, i) => {
                    const accepted = Number(aAccepted[i] || 0);
                    const preacc = Number(aPreAcceptance[i] || 0);
                    const waiting = Number(aWaiting[i] || 0);
                    const rejected = Number(aRejected[i] || 0);
                    const cancelled = Number(aCancelled[i] || 0);
                    const unknown = Number(aUnknown[i] || 0);
                    const total = accepted + preacc + waiting + rejected + cancelled + unknown;
                    monthMap[ym] = {accepted, preacc, waiting, rejected, cancelled, unknown, total};
                });

                const selectedYear = ($('#fYear').val() || '').trim();
                const ymList = [];
                const monthsForYear = (y) => Array.from({length: 12}, (_, k) => `${y}-${String(k + 1).padStart(2, '0')}`);
                const ymToDate = (ym) => {
                    const [y, m] = ym.split('-').map(Number);
                    return new Date(y, (m || 1) - 1, 1);
                };

                if (selectedYear) {
                    ymList.push(...monthsForYear(selectedYear));
                } else if (srcMonths.length) {
                    const minYM = srcMonths.reduce((a, b) => ymToDate(a) < ymToDate(b) ? a : b);
                    const maxYM = srcMonths.reduce((a, b) => ymToDate(a) > ymToDate(b) ? a : b);
                    const d = ymToDate(minYM);
                    const end = ymToDate(maxYM);
                    while (d <= end) {
                        ymList.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
                        d.setMonth(d.getMonth() + 1);
                    }
                }

                const rows = ymList.map(ym => {
                    const v = monthMap[ym] || {
                        accepted: 0,
                        preacc: 0,
                        waiting: 0,
                        rejected: 0,
                        cancelled: 0,
                        unknown: 0,
                        total: 0
                    };
                    return {m: ym, ...v};
                });

                // compute MoM + conversion
                let prevTotal = null;
                rows.forEach(r => {
                    let momPct = 0;
                    if (prevTotal !== null && prevTotal > 0) {
                        momPct = ((r.total - prevTotal) / prevTotal) * 100;
                        if (momPct > 300) momPct = 300;
                        if (momPct < -300) momPct = -300;
                    }
                    r.mom = Math.round(momPct * 10) / 10;
                    r.conv = r.total > 0 ? (r.accepted / r.total) * 100 : 0;
                    prevTotal = r.total;
                });

                // ---- toggle logic (scoped to this card) ----
                if (btn.dataset.bound === '1') return;   // avoid duplicate handlers on re-init
                btn.dataset.bound = '1';

                let showing = panelChart.classList.contains('kpi-panel--active') ? 'chart' : 'cards';

                const setBtnLabel = () => {
                    btn.textContent = (showing === 'chart') ? 'Show Cards' : 'Show Chart';
                };

                function show(mode) {
                    if (mode === showing) return;
                    showing = mode;
                    panelChart.classList.toggle('kpi-panel--active', mode === 'chart');
                    panelCards.classList.toggle('kpi-panel--active', mode === 'cards');
                    if (mode === 'cards') {
                        renderBoardIntoPanel(rows);
                        panelCards.scrollTop = 0;           // scroll inside the panel only
                    }
                    setBtnLabel();
                }

                setBtnLabel();
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    show(showing === 'chart' ? 'cards' : 'chart');
                });

                // allow clicking a column to flip to cards
                const chart = Highcharts.charts.find(c => c && c.renderTo?.id === 'kpi_status_monthly');
                if (chart) {
                    chart.update({
                        plotOptions: {
                            column: {
                                cursor: 'pointer', point: {
                                    events: {
                                        click() {
                                            show('cards');
                                        }
                                    }
                                }
                            }
                        }
                    }, false);
                    chart.redraw();
                }

                // ---- render the cards ----
                function renderBoardIntoPanel(allRows) {
                    const grid = document.getElementById('monthBoard');
                    if (!grid) return;

                    const fmtPct = (x) => (Number(x) || 0).toFixed(1).replace(/\.0$/, '') + '%';
                    const toNiceMonth = (ym) => {
                        if (!ym || ym.indexOf('-') < 0) return ym || '';
                        const [y, m] = ym.split('-').map(Number);
                        return new Date(y, (m || 1) - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
                    };
                    const formatMaybeSAR = (v) => {
                        if (typeof v === 'number') {
                            const a = Math.abs(v);
                            if (a >= 1e9) return 'SAR ' + (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
                            if (a >= 1e6) return 'SAR ' + (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                            if (a >= 1e3) return 'SAR ' + (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
                            return 'SAR ' + v.toLocaleString();
                        }
                        return String(v);
                    };

                    grid.innerHTML = allRows.map(r => {
                        const tiles = [
                            ['Accepted', r.accepted, 'ok'],
                            ['Pre-Acceptance', r.preacc, 'warn'],
                            ['Waiting', r.waiting, 'warn'],
                            ['Rejected', r.rejected, 'dang'],
                            ['Cancelled', r.cancelled, 'dang'],
                            ['Total', r.total, ''],
                            ['Conversion (Value)', fmtPct(r.conv), (r.conv >= 50 ? 'ok' : (r.conv >= 25 ? 'warn' : 'dang'))],
                            ['MoM Change', (r.mom >= 0 ? '+' : '') + fmtPct(r.mom), r.mom > 0 ? 'ok' : (r.mom < 0 ? 'dang' : '')],
                        ].map(([label, val, tone]) => `
        <div class="month-kpi ${tone}">
          <div class="label">${label}</div>
          <div class="val">${formatMaybeSAR(val)}</div>
        </div>`).join('');

                        return `
        <div class="month-card">
          <div class="month-head">
            <div class="month-title">${toNiceMonth(r.m)}</div>
          </div>
          <div class="month-grid">${tiles}</div>
        </div>`;
                    }).join('');
                }
            }


            function renderMonthlyProductValue(mpv) {
                const catsIso = mpv?.categories || [];
                const catsNice = catsIso.map(ym => {
                    const [y, m] = (ym || '').split('-');
                    return (y && m)
                        ? new Date(Number(y), Number(m) - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2)
                        : ym;
                });

                const seriesIn = Array.isArray(mpv?.series) ? mpv.series : [];

                Highcharts.chart('hcProductClusterMonthly', {
                    chart: {backgroundColor: 'transparent', spacing: [10, 20, 10, 20]},
                    title: {text: null},
                    credits: {enabled: false},
                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185', '#94a3b8', '#f472b6', '#22d3ee'],
                    xAxis: {
                        categories: catsNice,
                        lineColor: 'rgba(255,255,255,.14)',
                        tickColor: 'rgba(255,255,255,.14)',
                        labels: {style: {color: '#C7D2FE', fontSize: '12px', fontWeight: 600}}
                    },
                    yAxis: [{
                        title: {text: 'Value (SAR)', style: {color: '#C7D2FE', fontSize: '13px', fontWeight: 700}},
                        min: 0,
                        gridLineColor: 'rgba(255,255,255,.12)',
                        labels: {
                            style: {color: '#E0E7FF', fontSize: '12px', fontWeight: 600},
                            formatter() {
                                return 'SAR ' + Number(this.value).toLocaleString();
                            }
                        }
                    }, {
                        title: {text: 'Total (MoM %)', style: {color: '#F59E0B', fontSize: '13px', fontWeight: 700}},
                        opposite: true, min: 0, gridLineColor: 'transparent',
                        labels: {
                            style: {color: '#FBBF24', fontWeight: 600, fontSize: '12px'},
                            formatter() {
                                return Highcharts.numberFormat(this.value, 0);
                            }
                        }
                    }],
                    legend: {itemStyle: {color: '#E8F0FF', fontWeight: 600, fontSize: '12px'}},
                    tooltip: {
                        shared: true, useHTML: true,
                        backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                        style: {color: '#E8F0FF', fontSize: '12px'},
                        formatter() {
                            const head = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                            return head + this.points.map(p => {
                                const isLine = (p.series.type === 'spline' || p.series.type === 'line' || p.series.yAxis?.opposite);
                                const val = isLine ? Highcharts.numberFormat(p.y || 0, 0)
                                    : 'SAR ' + Highcharts.numberFormat(p.y || 0, 0);
                                return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${val}</b></div>`;
                            }).join('');
                        }
                    },
                    plotOptions: {
                        column: {
                            grouping: true, groupPadding: 0.18, pointPadding: 0.06, borderWidth: 0, borderRadius: 3,
                            dataLabels: {
                                enabled: true, inside: true, crop: false, overflow: 'none',
                                style: {
                                    color: '#E8F0FF',
                                    fontWeight: 700,
                                    fontSize: '11px',
                                    textOutline: '1px rgba(0,0,0,.6)'
                                }
                            }
                        },
                        spline: {
                            lineWidth: 3, marker: {enabled: false},
                            dataLabels: {enabled: false}
                        }
                    },
                    series: seriesIn
                });
            }


        })();

    </script>

@endpush
