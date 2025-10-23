<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    {{-- DataTables (Bootstrap 5 build) --}}
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

    {{-- Meta / Bootstrap --}}
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI Projects — Live</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        /* ===============================
           Global / misc
           =============================== */
        .area-badge {
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: .02em;
        }

        .area-Eastern {
            background: #e7f1ff;
            color: #0a58ca;
        }

        .area-Central {
            background: #eaf7ec;
            color: #1f7a1f;
        }

        .area-Western {
            background: #fff4e5;
            color: #b86e00;
        }

        body {
            background-color: #f8fdf8;
        }

        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm,
        table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }

        .row.g-3.align-items-stretch {
            margin-bottom: 0;
        }

        /* Shared chart containers (if used elsewhere) */
        .hc {
            height: 260px;
        }

        /* ===============================
           Cards (gauges + KPI tiles)
           Single source of truth
           =============================== */
        /*:root {*/
        /*    --card-top: #0b0f3a;*/
        /*    --card-bottom: #1c2944;*/
        /*}*/

        /*.kpi-card {*/
        /*    background: linear-gradient(180deg, var(--card-top) 0%, var(--card-bottom) 100%);*/
        /*    color: #dbeafe;*/
        /*    border-radius: 14px;*/
        /*    border: 1px solid rgba(255, 255, 255, 0.06);*/
        /*    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45);*/
        /*}*/


        /*.kpi-card:hover {*/
        /*    transform: translateY(-2px);*/
        /*    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.55);*/
        /*    transition: transform .2s ease, box-shadow .2s ease;*/
        /*}*/

        /*.kpi-label {*/
        /*    font-size: .8rem;*/
        /*    letter-spacing: .08em;*/
        /*    text-transform: uppercase;*/
        /*    color: #93c5fd;*/
        /*    opacity: .95;*/
        /*}*/

        /*.kpi-value {*/
        /*    font-size: 1.6rem;*/
        /*    font-weight: 800;*/
        /*}*/

        /* label under each gauge (IN HAND / BIDDING / etc.) */

        /*.kpi-card .text-center {*/
        /*    color: #e0f2fe !important;*/
        /*    font-weight: 700;*/
        /*    text-shadow: 0 0 6px rgba(0, 255, 255, 0.5);*/
        /*}*/

        /*!* Highcharts canvas background should be transparent *!*/
        /*!*.highcharts-background{ fill:transparent !important; }*!*/

        /*!* ===============================*/
        /*   BRIGHT gauge value styling*/
        /*   (applies to the data label inside solid gauge)*/
        /*   Keep ONLY this set – no duplicates.*/
        /*   =============================== *!*/
        /*.highcharts-data-label text tspan {*/


        }

        /* percentage (2nd line below the number) */
        /*.highcharts-data-label text tspan:nth-child(2) {*/
        /*    fill: #38bdf8 !important; !* bright cyan-blue *!*/
        /*    font-size: 16px !important;*/
        /*    font-weight: 700 !important;*/
        /*    text-shadow: 0 0 8px rgba(56, 189, 248, 0.9);*/
        /*}*/

        /*!* subtitle inside gauge (3rd line, e.g. IN HAND) *!*/
        /*.highcharts-data-label text tspan:nth-child(3) {*/
        /*    fill: #a5f3fc !important; !* soft aqua *!*/
        /*    font-size: 12px !important;*/
        /*    letter-spacing: .06em;*/
        /*    text-transform: uppercase;*/
        /*    text-shadow: 0 0 4px rgba(165, 243, 252, 0.8);*/
        /*}*/
    </style>

</head>
<body>
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

<main class="container-fluid py-4">
    {{-- ===================== FILTERS + FAMILY CHIPS ===================== --}}
    <div class="row g-3 mb-3" id="kpiRow" style="display:none">
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

                    <span id="salesmanWrap" class="d-none">
                        <input type="text" id="salesmanInput" class="form-control form-control-sm" style="width:14rem"
                               placeholder="Salesman (GM/Admin)">
                    </span>

                    <button class="btn btn-sm btn-primary" id="projApply">Update</button>
                </div>


            </div>

            {{-- REMOVED: the two badges above family chips (as requested). --}}

            <div class="d-flex justify-content-center gap-2 my-2 flex-wrap">
                <div id="familyChips" class="btn-group" role="group" aria-label="Product family">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-family="">All</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="ductwork">Ductwork
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="dampers">Dampers</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="sound">Sound Attenuators
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-family="accessories">Accessories
                    </button>
                </div>
            </div>
        </div>
    </div>

{{--    <div class="row g-3 align-items-stretch mb-3">--}}
{{--        <div class="col-lg-6">--}}
{{--            <div id="barByArea" class="card kpi-card">--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div class="col-md-6">--}}
{{--            <div id="pieByStatus" class="kpi-card-pieByStatus">--}}

{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}



















    {{-- ===================== GAUGES ROW ===================== --}}
    <div class="row g-3 align-items-stretch mb-3">
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="g_inhand" style="height:220px"></div>
                <div class="text-center fw-semibold small text-uppercase mt-2">In Hand</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="g_bidding" style="height:220px"></div>
                <div class="text-center fw-semibold small text-uppercase mt-2">Bidding</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="g_convRate" style="height:220px"></div>
                <div class="text-center fw-semibold small text-uppercase mt-2">Conversion Rate</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="g_targetAchieved" style="height:220px"></div>
                <div class="text-center fw-semibold small text-uppercase mt-2">Target Achieved</div>
            </div>
        </div>
    </div>

    {{-- ===================== KPI CARDS ===================== --}}
    <div class="row g-3 mb-4 text-center justify-content-center">
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div class="kpi-label">Total Quotation Value </div>
                <div id="kpi_totalSales" class="kpi-value">SAR 0</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div class="kpi-label">Total Quotation Count</div>
                <div id="kpi_totalInquiries" class="kpi-value">0</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div class="kpi-label">Conversion Rate</div>
                <div id="kpi_conversion" class="kpi-value">0%</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div class="kpi-label">Sales Target(Anually)</div>
                <div id="kpi_target" class="kpi-value">SAR 0</div>
            </div>
        </div>
{{--        <div class="col-6 col-md col-lg">--}}
{{--            <div class="kpi-card shadow-sm p-5 h-150">--}}
{{--                <div class="kpi-label">Actual (PO Received)</div>--}}
{{--                <div class="kpi-value" id="m_actualValue">SAR 0</div>--}}
{{--            </div>--}}
{{--        </div>--}}
    </div>
    {{--     ===================== OPTIONAL CHARTS =====================--}}
    {{--         The following sections are left out of the layout per your latest--}}
    {{--         design, but the JS can still draw them if you uncomment the HTML.--}}
    {{--         Keep them commented to avoid extra height and payload.--}}


    <div class="row g-3 align-items-stretch mb-3">
        <div class="col-lg-4">
            <div class="kpi-card p-4">
                <div id="barByArea" style="height:220px"></div>

            </div>
        </div>
        <div class="col-lg-4">
            <div class="kpi-card p-4">
                <div id="pieByStatus" style="height:220px"></div>

            </div>
        </div>
        <div class="col-lg-4">
            <div class="kpi-card p-4">
                <div id="projectsFunnel"  style="height:220px"></div>

            </div>
        </div>

    </div>



    <div id="barMonthlyValueTarget" class="kpi-card" style="height:400px"></div>
    <!-- AFTER the Monthly Value vs Target card -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span></span>
{{--            <span>Product-wise Inquiry Value (Monthly) — Eastern / Salesman Scope</span>--}}
{{--            <small class="text-muted">stacked columns by product + total spline</small>--}}
        </div>
        <div class="card-body">
            <div id="kpi_productwise" style="height: 420px;"></div>
        </div>
    </div>


</main>

{{-- Scripts --}}
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>
<script src="https://code.highcharts.com/modules/funnel.js"></script>
<script>
    /* =============================================================================
     *  CONFIG & HELPERS
     * ============================================================================= */
    const API = @json(url('/api'));
    const DT_URL = @json(route('projects.datatable'));
    const $ = window.jQuery;
    $.fn.dataTable.ext.errMode = 'console';

    const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
        style: 'currency', currency: 'SAR', maximumFractionDigits: 0
    }).format(Number(n || 0));

    let PROJ_YEAR = '';
    let PROJ_REGION = '';
    let ATAI_ME = null;
    let CAN_VIEW_ALL = false;
    let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

    function fmtCompactSAR(n) {
        const v = Number(n || 0), a = Math.abs(v);
        if (a >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
        if (a >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
        if (a >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
        return v.toLocaleString();
    }

    function fmtPct(v) {
        return (v == null || isNaN(v)) ? '' : (Number(v).toFixed(1).replace(/\.0$/, '') + '%');
    }

    const fmtSARtight = (n) => 'SAR ' + new Intl.NumberFormat('en-SA', {maximumFractionDigits: 0}).format(Number(n || 0));

    /* =============================================================================
     *  KPI FETCH + UPDATE
     * ============================================================================= */
    async function loadKpis() {
        document.getElementById('kpiRow')?.style?.setProperty('display', '');

        const year = document.querySelector('#projYear')?.value || PROJ_YEAR || '';
        const month = document.querySelector('#monthSelect')?.value || '';
        const df = document.querySelector('#dateFrom')?.value || '';
        const dt = document.querySelector('#dateTo')?.value || '';
        const family = currentFamily || '';
        const region = (CAN_VIEW_ALL ? (document.querySelector('#projRegion')?.value || '') : PROJ_REGION) || '';

        const url = new URL("{{ route('projects.kpis') }}", window.location.origin);
        if (df) url.searchParams.set('date_from', df);
        if (dt) url.searchParams.set('date_to', dt);
        if (!df && !dt) {
            if (month) url.searchParams.set('month', month);
            if (year) url.searchParams.set('year', year);
        }
        if (family) url.searchParams.set('family', family);
        if (region) url.searchParams.set('area', region);

        let resp = {};
        try {
            const res = await fetch(url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!res.ok) throw new Error('fetch failed');
            resp = await res.json();
            updateDialsAndCards(resp);
            // Optional charts are disabled in markup; leave code below commented.
            renderAreaAndPie(resp);
            renderMonthlyTarget(resp);
            renderFunnel(resp);
            renderMonthlyProductWiseChart(resp);
        } catch (e) {
            console.warn('KPI fetch failed', e);
            return;
        }
    }

    /* =============================================================================
     *  DASHBOARD DIALS + CARDS
     * ============================================================================= */
    function formatShortNumber(num) {
        num = Number(num || 0);
        if (num >= 1_000_000_000) return (num / 1_000_000_000).toFixed(1) + 'B';
        if (num >= 1_000_000) return (num / 1_000_000).toFixed(1) + 'M';
        if (num >= 1_000) return (num / 1_000).toFixed(1) + 'K';
        return num.toFixed(0);
    }

    function solidGaugePercent(el, pct, displayValue, opts = {}) {
        const color = opts.color || '#00f6f6';
        const unit = opts.unit || 'SAR';
        const sub = opts.subtitle || 'IN HAND';
        const poVal = Number(opts.po_value || 0);
        const bal = Number(opts.balance_value || 0);
        const safePct = Math.max(0, Math.min(100, Number(pct) || 0));
        return Highcharts.chart(el, {
            chart: {type: 'solidgauge', backgroundColor: 'transparent'},
            title: null,
            credits: {enabled: false},
            pane: {
                startAngle: -140,
                endAngle: 140,
                center: ['50%', '55%'],
                size: '100%',
                background: [{
                    outerRadius: '100%',
                    innerRadius: '70%',
                    shape: 'arc',
                    backgroundColor: 'rgba(255,255,255,0.08)'
                }]
            },
            yAxis: {
                min: 0, max: 100, lineWidth: 0, tickWidth: 0, labels: {enabled: false},
                stops: [[0, color], [1, color]]
            },
    //         tooltip: {
    //             useHTML: true,
    // //             pointFormatter: function () {
    // //                 const fmt = v => {
    // //                     v = Number(v || 0);
    // //                     if (v >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
    // //                     if (v >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
    // //                     if (v >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
    // //                     return v.toFixed(0);
    // //                 };
    // //                 // NOTE: `unit` is for the center value; PO & Quotation are SAR figures
    // //                 return `
    // //
    // //   <div><b>${(Number(pct) || 0).toFixed(1)}% achieved</b></div>
    // //   <div>Center value: <b>${fmt(displayValue)}</b> ${unit}</div>
    // //   <div>PO (latest rev): <b>${fmt(poVal)}</b> SAR</div>
    // //   <div>Quotation Sum: <b>${fmt(bal)}</b> SAR</div>
    // // `;
    // //             }
    //         },
            plotOptions: {
                solidgauge: {
                    rounded: true,
                    dataLabels: {
                        useHTML: true,
                        y: -10,
                        borderWidth: 0,
                        formatter: function () {
                            const main = `
          <div style="
            font-size:24px;font-weight:800;color:#fff;
            text-shadow:0 0 6px rgba(255,255,255,.9),0 0 14px rgba(0,255,255,.35),0 0 22px rgba(0,255,255,.25);
          ">
            ${unit === '%' ? Number(displayValue || 0).toFixed(1) : (() => {
                                const v = Number(displayValue || 0), a = Math.abs(v);
                                if (a >= 1e9) return (v/1e9).toFixed(1).replace(/\.0$/, '') + 'B';
                                if (a >= 1e6) return (v/1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                                if (a >= 1e3) return (v/1e3).toFixed(1).replace(/\.0$/, '') + 'K';
                                return v.toFixed(0);
                            })()}
          </div>`;
                            const unitLine = `<div style="font-size:14px;color:#a5f3fc">${unit}</div>`;
                            const subLine  = sub ? `<div style="font-size:12px;color:#a5f3fc;letter-spacing:.06em;text-transform:uppercase">${sub}</div>` : '';
                            return `<div style="text-align:center">${main}${unitLine}${subLine}</div>`;
                        }
                    }
                }
            },
            series: [{
                data: [{ y: safePct, color }]
            }]
        });
    }


    function setText(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function setTextFirst(ids, html) {
        for (const id of ids) {
            const el = document.getElementById(id);
            if (el) {
                el.innerHTML = html;
                return true;
            }
        }
        return false;
    }

    function updateDialsAndCards(resp) {
        const gauges = resp?.gauges || {};
        const convTotals = resp?.conversion_totals || {};
        const qp = resp?.quote_phase || {};

        // ---------- Canonical values from backend ----------
        const totalQuotedValue  = Number(qp.total_quoted_value || 0);
        const conversionPct     = Number(qp.conversion_pct || 0);       // In-Hand / Total
        const ytdTargetValue    = Number(qp.ytd_target_value || 0);
        const targetAchPct      = Number(qp.target_achieved_pct || 0);  // Total / YTD target
        const monthlyQuoteTarget= Number(qp.monthly_quote_target || 3_000_000);

        // ---------- In-Hand & Bidding gauges (already server-computed as % of total) ----------
        const inhandG  = gauges?.inhand  || {};
        const biddingG = gauges?.bidding || {};

        solidGaugePercent('g_inhand',
            Number(inhandG.pct || 0),
            Number(inhandG.display_value || 0),
            {
                color: '#00c9a7',
                unit: inhandG.unit || 'SAR',
                subtitle: 'IN HAND',

            }
        );

        solidGaugePercent('g_bidding',
            Number(biddingG.pct || 0),
            Number(biddingG.display_value || 0),
            {
                color: '#3b82f6',
                unit: biddingG.unit || 'SAR',
                subtitle: 'BIDDING',
                po_value: 0,
                balance_value: 0
            }
        );

        // ---------- Conversion gauge (use the SAME % for arc and label) ----------
        const cg = qp.conversion_pct || {};
        solidGaugePercent(
            'g_convRate',
            Number(cg || 0),        // arc %
            Number(cg || 0),        // center text shows same %
            {
                color: '#38bdf8',
                unit: '%',                // this unit is only for the center text
                subtitle: 'QUOTE CONV %',
                po_value: Number(cg.po_user_region_last ?? cg.po_user_region_raw ?? 0), // SAR
                balance_value: Number(cg.quotes_region_sar || 0),                        // SAR
            }
        );

        // ---------- Target Achieved gauge (quotes vs YTD target) ----------
        solidGaugePercent(
            'g_targetAchieved',
            Math.max(0, Math.min(100, targetAchPct)),  // arc = Total / YTD target × 100
            totalQuotedValue,                           // center value shows actual quoted SAR
            { color: '#f59e0b', unit: 'SAR', subtitle: 'TARGET ACHIEVED' }
        );

        // ---------- KPI cards ----------
        setText('kpi_totalSales', fmtSAR(totalQuotedValue));
        setText(
            'kpi_totalInquiries',
            Number(convTotals.total_inquiries ?? resp.total_count ?? 0).toLocaleString()
        );
        setText('kpi_conversion', conversionPct.toFixed(1) + '%');  // same as gauge
        setText('kpi_target', fmtSAR(ytdTargetValue));
        setText('m_actualValue', fmtSARtight(totalQuotedValue));    // “Actual” = quotes in quote phase
    }



    /* =============================================================================
     *  OPTIONAL CHART RENDERERS (HTML is commented out above)
     *  Keep these functions for future use; currently not called.
     * ============================================================================= */
    function renderAreaAndPie(resp) {
        const areaStatus = resp.area_status || {categories: [], series: []};
        Highcharts.chart('barByArea', {
            chart: {
                type: 'column',
                height: 260,
                spacing: [8, 16, 8, 16],
                backgroundColor: 'transparent'
            },
            title: {
                text: 'Regional Inquiry Distribution',
                style: { color: '#E8F0FF', fontSize: '16px', fontWeight: 700 }
            },
            credits: { enabled: false },

            colors: ['#60a5fa', '#8b5cf6', '#34d399'], // In-Hand, Bidding, Lost

            xAxis: {
                categories: areaStatus.categories,
                lineColor: 'rgba(255,255,255,.14)',
                tickColor: 'rgba(255,255,255,.14)',
                labels: { style: { color: '#C7D2FE', fontWeight: 600, fontSize: '13px' } }
            },

            yAxis: {
                min: 0,
                title: { text: 'Count', style: { color: '#C7D2FE', fontWeight: 700, fontSize: '13px' } },
                gridLineColor: 'rgba(255,255,255,.12)',
                labels: { style: { color: '#E0E7FF', fontWeight: 600, fontSize: '12px' } },
                stackLabels: {
                    enabled: true,
                    style: { color: '#E8F0FF', fontWeight: 700, textOutline: '2px rgba(0,0,0,.7)' },
                    formatter() { return this.total; }
                }
            },

            legend: {
                enabled: true,
                itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                itemHoverStyle: { color: '#FFFFFF' }
            },

            tooltip: {
                shared: true,
                useHTML: true,
                backgroundColor: 'rgba(10,15,45,.95)',
                borderColor: '#334155',
                borderRadius: 8,
                style: { color: '#E8F0FF', fontSize: '13px' },
                formatter: function () {
                    const header = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                    const fmt = v => (v >= 1e9 ? (v/1e9).toFixed(1).replace(/\.0$/,'')+'B'
                        : v >= 1e6 ? (v/1e6).toFixed(1).replace(/\.0$/,'')+'M'
                            : v >= 1e3 ? (v/1e3).toFixed(1).replace(/\.0$/,'')+'K'
                                : Number(v||0).toFixed(0));
                    const lines = this.points.map(p => {
                        const sar = (p.point && typeof p.point.sar !== 'undefined') ? Number(p.point.sar) : 0;
                        return `<div><span style="color:${p.color}">●</span>
                ${p.series.name}: Count <b>${p.y}</b> • Value <b>${fmt(sar)}</b></div>`;
                    });
                    return header + lines.join('');
                }
            },

            plotOptions: {
                column: {
                    grouping: true,
                    borderWidth: 0,
                    borderRadius: 3,
                    pointPadding: 0.06,
                    groupPadding: 0.18,
                    states: { hover: { brightness: 0.08 } },
                    dataLabels: {
                        enabled: true,

                        align: 'center',
                        verticalAlign: 'bottom',
                        inside: false,
                        y: -8,
                        crop: false,
                        overflow: 'none',
                        style: {
                            color: '#E8F0FF',
                            fontWeight: 700,
                            fontSize: '12px',
                            textOutline: '2px rgba(0,0,0,.7)'
                        },
                        formatter: function () {
                            const sar = (this.point && typeof this.point.sar !== 'undefined') ? Number(this.point.sar) : 0;
                            if (sar <= 0) return '';
                            return sar >= 1e9 ? `SAR ${(sar/1e9).toFixed(1).replace(/\.0$/,'')}B`
                                : sar >= 1e6 ? `SAR ${(sar/1e6).toFixed(1).replace(/\.0$/,'')}M`
                                    : sar >= 1e3 ? `SAR ${(sar/1e3).toFixed(1).replace(/\.0$/,'')}K`
                                        : `SAR ${sar.toFixed(0)}`;
                        }
                    }
                }
            },

            series: areaStatus.series
        });

        const rows = resp.status || [];
        const buckets = ['In-Hand', 'Bidding', 'Lost'];
        const data = buckets.map(b => {
            const r = rows.find(x => (x.status_norm || x.status) === b);
            return {name: b, y: Number(r?.sum_value || 0)};
        });
        const hasData = data.some(d => d.y > 0);
        const chart = Highcharts.chart('pieByStatus', {
            chart: {
                type: 'pie',
                backgroundColor: 'transparent',
                spacing: [10, 10, 10, 10]
            },
            title: {
                text: 'Quotations  Distribution by Status',
                style: { color: '#E8F0FF', fontSize: '16px', fontWeight: '700' }
            },
            credits: { enabled: false },
            tooltip: {
                useHTML: true,
                backgroundColor: 'rgba(10,15,45,0.95)',
                borderColor: '#334155',
                borderRadius: 8,
                style: { color: '#E8F0FF', fontSize: '13px' },
                pointFormat: '<b>{point.percentage:.1f}%</b><br/>Value: <b>SAR {point.y:,.0f}</b>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    borderWidth: 0,
                    size: '80%',
                    shadow: false,
                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b'],
                    dataLabels: {
                        enabled: true,
                        distance: 18,
                        connectorWidth: 1.2,
                        connectorColor: 'rgba(255,255,255,0.35)',
                        style: {
                            color: '#E8F0FF',
                            fontWeight: 300,
                            fontSize: '15px',
                            textOutline: '2px rgba(0,0,0,0.6)'
                        },
                        format: '{point.name}: {point.percentage:.1f}%'
                    }
                }
            },
            legend: {
                enabled: false,
                itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' }
            },
            series: [{ name: 'Value', data }],
            lang: { noData: 'No status values.' },
            noData: {
                style: { fontSize: '14px', color: '#E0E7FF', fontWeight: 600 }
            }
        });
        if (!hasData && Highcharts.Chart.prototype.showNoData) chart.showNoData();
    }

    function renderMonthlyTarget(resp) {
        const payload = resp.monthly_value_status_with_target || {categories: [], series: [], target_value: 0};
        const monthLabel = (ym) => {
            if (!ym || ym.indexOf('-') < 0) return ym || '';
            const [y, m] = ym.split('-').map(Number);
            return new Date(y, (m || 1) - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
        };
        const cats = (payload.categories || []).map(monthLabel);

        const colIH = (payload.series || []).find(s => /in-hand/i.test(s.name)) || {data: []};
        const colBD = (payload.series || []).find(s => /bidding/i.test(s.name)) || {data: []};
        const colLT = (payload.series || []).find(s => /lost/i.test(s.name)) || {data: []};
        const targetPct = ((payload.series || []).find(s => /target.*%/i.test(s.name))?.data) || [];

        const series = [
            {type: 'column', name: 'In-Hand (SAR)', data: colIH.data || []},
            {type: 'column', name: 'Bidding (SAR)', data: colBD.data || []},
            {type: 'column', name: 'Lost (SAR)', data: colLT.data || []}
        ];
        if (Array.isArray(targetPct) && targetPct.length) {
            series.push({type: 'spline', name: 'Target Attainment %', yAxis: 1, data: targetPct});
        }

        Highcharts.chart('barMonthlyValueTarget', {
            chart: {
                zoomType: 'x',
                backgroundColor: 'transparent',
                spacing: [10, 20, 10, 20]
            },
            title: {
                text: 'Quotation vs Target',
                style: { color: '#E8F0FF', fontSize: '16px', fontWeight: '700' }
            },
            credits: { enabled: false },

            xAxis: {
                categories: cats,
                tickInterval: 1,
                minPadding: 0.1,
                maxPadding: 0.1,
                lineColor: 'rgba(255,255,255,.15)',
                tickColor: 'rgba(255,255,255,.15)',
                labels: {
                    rotation: 0,
                    style: { color: '#C7D2FE', fontSize: '13px', fontWeight: 600 }
                }
            },

            yAxis: [
                {
                    title: {
                        text: 'Value (SAR)',
                        style: { color: '#C7D2FE', fontWeight: 700, fontSize: '13px' }
                    },
                    min: 0,
                    gridLineColor: 'rgba(255,255,255,.10)',
                    labels: {
                        style: { color: '#E0E7FF', fontWeight: 600, fontSize: '12px' },
                        formatter() { return fmtCompactSAR(this.value); }
                    }
                },
                {
                    title: {
                        text: 'Percent (%)',
                        style: { color: '#F59E0B', fontWeight: 700, fontSize: '13px' }
                    },
                    opposite: true,
                    min: 0,
                    gridLineColor: 'transparent',
                    labels: {
                        style: { color: '#FBBF24', fontWeight: 600, fontSize: '12px' },
                        formatter() { return fmtPct(this.value); }
                    }
                }
            ],

            legend: {
                align: 'center',
                itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                itemHoverStyle: { color: '#FFFFFF' }
            },

            tooltip: {
                shared: false,
                useHTML: true,
                backgroundColor: 'rgba(10,15,45,0.95)',
                borderColor: '#334155',
                borderRadius: 8,
                style: { color: '#E8F0FF', fontSize: '13px' },
                formatter: function () {
                    const isPct = this.series.yAxis.opposite;
                    return `<b>${this.x}</b><br/>${
                        isPct
                            ? `${this.series.name}: <b>${fmtPct(this.y)}</b>`
                            : `${this.series.name}: <b>SAR ${fmtCompactSAR(this.y)}</b>`
                    }`;
                }
            },

            plotOptions: {
                column: {
                    grouping: true,
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
                            fontWeight: 100,
                            fontSize: '15px',

                        },
                        formatter() {
                            return this.y > 0 ? `SAR ${fmtCompactSAR(this.y)}` : '';
                        }
                    }
                },
                spline: {
                    lineWidth: 3,
                    color: '#f59e0b',
                    marker: { enabled: true, radius: 4, fillColor: '#fff', lineColor: '#f59e0b', lineWidth: 2 },
                    dataLabels: {
                        enabled: true,
                        y: -8,
                        style: {
                            color: '#FBBF24',

                            fontSize: '12px',

                        },
                        formatter() {
                            return fmtPct(this.y);
                        }
                    }
                }
            },

            colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b'],
            series
        });
    }

    function renderMonthlyProductWiseChart(resp) {
        const mpv = resp?.monthly_product_value;
        const containerId = 'kpi_productwise';
        if (!mpv || !Array.isArray(mpv.categories) || !Array.isArray(mpv.series)) return;

        // --- helpers ---
        const fmtCompact = (n) => {
            const v = Number(n || 0), a = Math.abs(v);
            if (a >= 1e9) return (v/1e9).toFixed(1).replace(/\.0$/,'') + 'B';
            if (a >= 1e6) return (v/1e6).toFixed(1).replace(/\.0$/,'') + 'M';
            if (a >= 1e3) return (v/1e3).toFixed(1).replace(/\.0$/,'') + 'K';
            return v.toLocaleString();
        };

        // --- find a global y max over all COLUMN series to set a visibility threshold ---
        const columnSeriesOnly = (mpv.series || []).filter(s => (s.type || 'column') === 'column' && !/total/i.test(s.name || ''));
        let globalMax = 0;
        columnSeriesOnly.forEach(s => {
            (s.data || []).forEach(v => { globalMax = Math.max(globalMax, Number(v || 0)); });
        });
        // Show labels only if a bar is >= 6% of the largest bar (tune as needed)
        const DL_THRESHOLD = globalMax * 0.06;

        // --- rebuild series: keep columns as columns; convert "Total" to MoM spline ---
        // --- rebuild series: keep columns as columns; convert "Total" to MoM spline ---
        const rebuilt = (mpv.series || []).map(s => {
            if (s.type === 'spline' || /total/i.test(s.name || '')) {
                return {
                    ...s,
                    type: 'spline',
                    yAxis: 1,
                    zIndex: 6,
                    color: '#facc15',
                    lineWidth: 3,
                    marker: { enabled: true, radius: 3 },
                    dataLabels: {
                        enabled: true,
                        // show them all
                        allowOverlap: true,
                        crop: false,
                        // keep readable chip
                        backgroundColor: 'rgba(17,24,39,.92)',
                        borderColor: 'rgba(255,255,255,.18)',
                        borderWidth: 1,
                        borderRadius: 6,
                        padding: 3,
                        y: -10,
                        style: { color: '#fff', fontWeight: 700, textOutline: 'none', fontSize: '11px' },
                        formatter() {
                            const mom = Number(this.y || 0);
                            const sign = mom > 0 ? '▲' : (mom < 0 ? '▼' : '•');
                            return `${sign} ${Highcharts.numberFormat(mom, 1)}%`;
                        }
                    },plotOptions: {
                        column: {
                            groupPadding: 0.2,
                            pointPadding: 0.1,
                            borderRadius: 2,
                            pointWidth: 26,    // optional for uniform look
                        },
                        series: {
                            clip: false ,
                            states: { hover: { brightness: 5} },
                        }
                    },
                    tooltip: {
                        pointFormatter: function () {
                            const mom = Number(this.y || 0);
                            const sar = Number(this.point?.sar || 0);
                            return `<span style="color:${this.color}">●</span> ${Highcharts.escapeHTML(this.series.name)}:
                  <b>${Highcharts.numberFormat(mom,1)}%</b><br/>
                  <span style="opacity:.85">Total: SAR ${Highcharts.numberFormat(sar,0)}</span><br/>`;
                        }
                    }
                };
            }

            // Column series (products)
            return {
                ...s,
                type: 'column',
                borderWidth: 0,
                // TWO labels per bar, both forced visible
                dataLabels: [
                    // 1) inside bar: % contribution
                    {
                        enabled: true,
                        inside: true,
                        align: 'center',
                        verticalAlign: 'middle',
                        allowOverlap: true,   // <- never hide
                        crop: false,
                        overflow: 'none',
                        style: {
                            color: '#0b132b',
                            fontWeight: 200,
                            textOutline: 'none',
                            fontSize: '9px'
                        },
                        formatter() {
                            const total = monthTotalAt(this.series.chart, this.point.index);
                            const v = Number(this.y || 0);
                            if (!total || v <= 0) return '';
                            const pct = (v / total) * 100;
                            return Highcharts.numberFormat(pct, 1) + '%';
                        }
                    },
                    // 2) above bar: SAR value
                    {
                        enabled: true,
                        inside: false,
                        align: 'center',
                        y: -6,
                        allowOverlap: true,   // <- never hide
                        crop: false,
                        rotation:-90,
                        overflow: 'none',
                        backgroundColor: 'rgba(178,174,174,0.99)',
                        borderColor: 'rgba(255,255,255,.18)',
                        borderWidth: 1,
                        borderRadius: 6,
                        padding: 3,
                        style: {
                            color: '#000000',
                            fontWeight: 600,
                            textOutline: 'none',
                            fontSize: '12px'
                        },
                        formatter() {
                            const v = Number(this.y || 0);
                            if (v >= 1e9) return 'SAR ' + (v/1e9).toFixed(1).replace(/\.0$/,'') + 'B';
                            if (v >= 1e6) return 'SAR ' + (v/1e6).toFixed(1).replace(/\.0$/,'') + 'M';
                            if (v >= 1e3) return 'SAR ' + (v/1e3).toFixed(1).replace(/\.0$/,'') + 'K';
                            return 'SAR ' + v.toFixed(0);
                        }
                    }
                ],
                tooltip: {
                    pointFormatter: function () {
                        const total = monthTotalAt(this.series.chart, this.point.index);
                        const pct = total ? (this.y / total) * 100 : 0;
                        const valM = Highcharts.numberFormat((this.y || 0) / 1e6, 2);
                        return `<span style="color:${this.color}">●</span> ${Highcharts.escapeHTML(this.series.name)}:
                <b>SAR ${valM}M</b> (${Highcharts.numberFormat(pct,1)}%)<br/>`;
                    }
                }
            };
        });


        const chart = Highcharts.chart(containerId, {
            chart: { backgroundColor: 'transparent', spacing: [8, 10, 10, 10] },
            title: {
                text: 'Monthly Product Performance & Trendline',
                style: { color: '#E8F0FF', fontWeight: 'bold' }
            },
            subtitle: { text: '' },
            xAxis: {
                categories: mpv.categories,
                labels: {
                    style: { color: '#A7B5CC' },
                    rotation: 0,
                    autoRotation: undefined, // keep horizontal
                    step: mpv.categories.length > 14 ? 2 : 1 // show every 2nd label if crowded
                },
                tickLength: 0,
                lineColor: 'rgba(255,255,255,0.2)'
            },
            yAxis: [{
                title: { text: 'Value (SAR)', style: { color: '#A7B5CC' } },
                labels: {
                    style: { color: '#A7B5CC' },
                    formatter() { return Highcharts.numberFormat(this.value / 1e6, 0) + 'M'; }
                },
                gridLineColor: 'rgba(255,255,255,0.08)'
            }, {
                title: { text: 'MoM (%)', style: { color: '#facc15' } },
                labels: {
                    style: { color: '#facc15' },
                    formatter() { return Highcharts.numberFormat(this.value, 0) + '%'; }
                },
                maxPadding: 0.1,
                opposite: true
            }],
            legend: {
                itemStyle: { color: '#E8F0FF', fontWeight: 500 },
                itemHoverStyle: { color: '#FFF' },
                backgroundColor: 'transparent'
            },
            tooltip: {
                shared: true,
                backgroundColor: 'rgba(15,23,42,0.95)',
                borderColor: '#475569',
                style: { color: '#E8F0FF', fontSize: '12px' },
                crosshairs: [{ width: 1, color: 'rgba(255,255,255,.15)' }]
            },
            plotOptions: {
                column: {
                    groupPadding: 0.12,
                    pointPadding: 0.05,
                    borderRadius: 2
                },
                series: {
                    states: { hover: { brightness: 0 } },
                    events: {
                        show() { updateContribution(this.chart); },
                        hide() { updateContribution(this.chart); }
                    }
                },
                spline: { states: { hover: { lineWidthPlus: 0 } } }
            },
            series: rebuilt
        });

        function monthTotalAt(chart, pointIndex) {
            return chart.series
                .filter(s => s.type === 'column' && s.visible !== false)
                .reduce((sum, s) => sum + Number(s.yData?.[pointIndex] || 0), 0);
        }

        function updateContribution(chartInstance) {
            // no per-series toggling of datalabels; we already reduce clutter with a threshold
            chartInstance.redraw();
        }
    }






    function renderFunnel(resp) {
        // Pull stages from your existing /projects.kpis response
        const stages = (resp?.funnel_value?.stages || []).map(s => ({
            name: s.name,
            y: Number(s.value || 0)
        }));

        // If container is missing or there’s no data, bail gracefully
        if (!document.getElementById('projectsFunnel')) return;
        if (!stages.length) {
            Highcharts.chart('projectsFunnel', {
                title: { text: 'Quotation By Value' },
                chart: { type: 'funnel', backgroundColor: 'transparent' },
                credits: { enabled: false },
                lang: { noData: 'No funnel data.' },
                noData: { style: { color: '#c28b01', fontWeight: 600 } }
            });
            return;
        }

        Highcharts.chart('projectsFunnel', {
            chart: { type: 'funnel', backgroundColor: 'transparent' },
            title: { text: 'Project Lifecycle Value Distribution', style: { color: '#E8F0FF', fontWeight: 700 } },
            credits: { enabled: false },
            tooltip: {
                pointFormatter() { return `<b>${fmtSAR(this.y)}</b>`; }
            },
            plotOptions: {
                series: {
                    borderWidth: 0,
                    dataLabels: {
                        enabled: true,
                              // ✅ center text inside the funnel
                        softConnector: false,      // removes the connector lines
                        align: 'center',           // horizontally center text
                        verticalAlign: 'middle',   // vertically center text
                        style: {
                            color: '#95c53d',      // text color for dark background
                            fontWeight: '100',
                            textOutline: 'none',
                            fontSize: '11px'
                        },
                        formatter: function () {
                            const v = this.point.y || 0;
                            const a = Math.abs(v);
                            const compact =
                                a >= 1e9 ? (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B' :
                                    a >= 1e6 ? (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' :
                                        a >= 1e3 ? (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K' :
                                            v.toLocaleString();

                            return `<b>${this.point.name}</b><br>SAR ${compact}`;
                        }
                    },
                    neckWidth: '30%',
                    neckHeight: '25%',
                    width: '80%'
                }
            },
            series: [{ name: 'Value', data: stages }]
        });
    }
    /* =============================================================================
     *  EVENTS & BOOT
     * ============================================================================= */
    $(document).on('click', '#familyChips [data-family]', function (e) {
        e.preventDefault();
        $('#familyChips [data-family]').removeClass('active');
        this.classList.add('active');
        currentFamily = this.getAttribute('data-family') || '';
        loadKpis();
    });

    document.getElementById('projApply')?.addEventListener('click', () => {
        PROJ_YEAR = document.getElementById('projYear')?.value || '';
        PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';
        loadKpis();
    });

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
        await loadKpis();
    })();





</script>
</body>
</html>
