{{-- resources/views/sales_orders/manager/manager_log.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Sales Order Log — Manager</title>

    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        .badge-total { font-weight:600; padding:.45rem .75rem; border-radius:12px }
        .btn-chip.btn-outline-primary.active { background:#198754; border-color:#198754; color:#fff }
        #toolbar .form-select-sm, #toolbar .form-control-sm { width:auto }
        .kpi-card-title { text-align:center; font-weight:600; text-transform:uppercase; font-size:.8rem; margin-top:.35rem; opacity:.9 }
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

    {{-- Filters --}}
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

    <div class="row g-3 mb-4 text-center justify-content-center">
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div id="badgeCount" class="kpi-value">SAR 0</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div id="badgeValue" class="kpi-value">0</div>
            </div>
        </div>
    </div>

    {{-- Family chips --}}
    <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">
        <div id="familyChips" class="btn-group" role="group" aria-label="Product family">
            <button type="button" class="btn btn-sm btn-outline-primary active" data-family="">All</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-family="ductwork">Ductwork</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-family="dampers">Dampers</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-family="sound">Sound Attenuators</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-family="accessories">Accessories</button>
        </div>
    </div>

    {{-- Status tabs --}}
    <ul class="nav nav-tabs mb-3" id="statusTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-status="" type="button">All</button></li>
        <li class="nav-item"><button class="nav-link" data-status="Accepted" type="button">Accepted</button></li>
        <li class="nav-item"><button class="nav-link" data-status="Pre-Acceptance" type="button">Pre-Acceptance</button></li>
        <li class="nav-item"><button class="nav-link" data-status="Rejected" type="button">Rejected</button></li>
        <li class="nav-item"><button class="nav-link" data-status="Waiting" type="button">Waiting</button></li>
    </ul>

    {{-- Search --}}
    <div class="d-flex align-items-center gap-2 mb-2">
        <div class="input-group w-auto">
            <span class="input-group-text">Search</span>
            <input id="searchInput" type="text" class="form-control" placeholder="Project, client, location…">
        </div>
    </div>

    <!-- KPI row: Conversion gauge + PO vs Forecast vs Target -->
    <div class="row g-3 align-items-stretch mb-4">
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="convGauge" style="height:260px"></div>
                <div class="kpi-card-title">Conversion (Quotations → PO's)</div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="convGaugeInHand" style="height:260px"></div>
                <div class="kpi-card-title">In-Hand Conversion</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="convGaugeBidding" style="height:260px"></div>
                <div class="kpi-card-title">Bidding Conversion</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="kpi-card p-3">
                <div id="projectsRegionPie" style="min-height: 260px;"></div>
                <div class="kpi-card-title">Projects Share Per Region</div>
            </div>




        </div>
    </div>



    <div class="row g-3 align-items-stretch mb-4">

        <div class="col-lg-12">
            <div class="card kpi-card p-3 hc-panel">
                <div id="poFcTargetChart" style="height:300px"></div>
            </div>
        </div>
    </div>


    {{-- In-Hand & Bidding Gauges --}}
{{--    <div class="row g-3 mb-4">--}}
{{--        <div class="col-lg-6">--}}
{{--            <div class="kpi-card p-3">--}}
{{--                <div id="convGaugeInHand" style="height:220px"></div>--}}
{{--                <div class="kpi-card-title">In-Hand Conversion</div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div class="col-lg-6">--}}
{{--            <div class="kpi-card p-3">--}}
{{--                <div id="convGaugeBidding" style="height:220px"></div>--}}
{{--                <div class="kpi-card-title">Bidding Conversion</div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}

{{--    <div id="projectsRegionPie" style="min-height: 360px;"></div>--}}

{{--    <div id="salespersonRegionWarning" style="display:none" class="alert alert-warning" role="alert"></div>--}}
{{--    <div id="salespersonRegionTable" class="mt-2"></div>--}}
    {{-- DataTable --}}
    <div class="card">
        <div class="card-body">
            <table id="dtSalesOrders" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>PO No</th>
                    <th>Date</th>
                    <th>SalesMan-Region</th>

                    <th>Client</th>
                    <th>Project-Region</th>
                    <th> Project Location</th>

                    <th>Project</th>
                    <th>Product</th>
                    <th class="text-end">Value with VAT</th>
                    <th class="text-end">PO Value</th>
                    <th>Status</th>
                    <th>Salesperson</th>
                    <th>Remarks</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>

<script>
    /* ======================================================================
       ATAI — Sales Order Log KPI Page JS
       - Uses new backend keys:
           - conversion_gauge_inhand_bidding           (inhand/bidding blocks)
           - conversion_gauge_inhand_bidding_total     (overall combined)
           - project_sum_inhand_biddign                (denominators; typo kept)
       - Backward compatible with legacy keys.
       ====================================================================== */

    /* ================= Helpers ================= */
    const fmtSAR = (n) => 'SAR ' + Number(n || 0).toLocaleString();
    function fmtCompactSAR(n){
        const v = Number(n||0), a = Math.abs(v);
        if (a >= 1e9) return (v/1e9).toFixed(1).replace(/\.0$/,'') + 'B';
        if (a >= 1e6) return (v/1e6).toFixed(1).replace(/\.0$/,'') + 'M';
        if (a >= 1e3) return (v/1e3).toFixed(1).replace(/\.0$/,'') + 'K';
        return v.toLocaleString();
    }

    let currentFamily = '';
    let currentStatus = '';

    const $year  = $('#fYear');
    const $month = $('#fMonth');
    const $from  = $('#fFrom');
    const $to    = $('#fTo');

    function buildFilters(){
        return {
            year:  $year.val()  || '',
            month: $month.val() || '',
            from:  $from.val()  || '',
            to:    $to.val()    || '',
            family: currentFamily,
            status: currentStatus
        };
    }

    /* ================= Charts ================= */

    /** PO vs Forecast vs Target (unchanged) */
    function renderPoFcTarget(payload){
        if(!payload || !Array.isArray(payload.categories)) return;
        const [po, forecast, target] = payload.series || [null,null,null];

        const seriesStaggered = (payload.series || []).map((s, i) => ({
            ...s,
            dataLabels: {
                enabled: true,
                inside: false,
                y: -6 - i * 14,
                padding: 6,
                borderRadius: 6,
                borderWidth: 1,
                style: { color:'#EAF6FF', fontWeight:'800', textOutline:'none', fontSize:'11px' },
                formatter() {
                    const v = Number(this.y || 0);
                    return v > 0 ? ('SAR ' + fmtCompactSAR(v)) : '';
                }
            }
        }));

        Highcharts.chart('poFcTargetChart', {
            chart: { type: 'column', backgroundColor: 'transparent', spacingTop: 22, spacingRight:30 },
            title: { text: 'PO vs Forecast vs Target', align:'center', style:{ color:'#95c53d', fontSize:'16px', fontWeight:'700' }, margin:10 },
            credits: { enabled:false },

            xAxis: {
                categories: payload.categories,
                tickInterval: 1,
                tickmarkPlacement: 'on',
                lineColor: 'rgba(255,255,255,.12)',
                tickColor: 'rgba(255,255,255,.12)',
                showFirstLabel: true,
                showLastLabel: true,
                labels: {
                    style: { color:'#A7B5CC', fontWeight:'600' },
                    step: 1,
                    rotation: 0,
                    autoRotation: undefined,
                    reserveSpace: true,
                    allowOverlap: true,
                    padding: 6
                }
            },

            yAxis: {
                min: 0,
                title: { text:'Value (SAR)', style:{ color:'#CFE0FF', fontWeight:'700' } },
                labels: {
                    style:{ color:'#A7B5CC' },
                    formatter(){ return Highcharts.numberFormat(this.value/1e6,0)+'M'; }
                },
                gridLineColor: 'rgba(255,255,255,0.10)',
                maxPadding: 0.12
            },

            tooltip:{
                shared:true,
                useHTML:true,
                className:'hc-tooltip',
                borderWidth:0,
                formatter:function(){
                    const cat = Highcharts.escapeHTML(this.x);
                    let html = `<div class="label" style="margin-bottom:4px">${cat}</div>`;
                    this.points.forEach(p=>{
                        const dot = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color};margin-right:6px"></span>`;
                        html += `${dot}<span>${Highcharts.escapeHTML(p.series.name)}:</span> <b>SAR ${fmtCompactSAR(p.y)}</b><br/>`;
                    });
                    return html;
                }
            },

            plotOptions:{
                series:{
                    states:{ hover:{ enabled:true, halo:{ size:8, opacity:.25 } } },
                    dataLabels:{
                        enabled:true,
                        padding:6,
                        rotation:-90,
                        borderRadius:6,
                        borderWidth:1,
                        crop:false,
                        overflow:'none',
                        style:{ color:'#EAF6FF', fontWeight:'800', textOutline:'none', fontSize:'11px' },
                        formatter(){
                            const v = Number(this.y||0);
                            if (v<=0) return '';
                            return 'SAR ' + fmtCompactSAR(v);
                        }
                    }
                },
                column:{ pointPadding:0.12, groupPadding:0.18, borderWidth:0, borderRadius:3 }
            },

            colors: ['#60a5fa','#34d399','#f59e0b'],
            series: [
                { ...(target||{}),   color: '#f6a800'  },
                { ...(forecast||{}), color: '#00c896'  },
                { ...(po||{}),       color: '#6dafff'  }
            ]
        });
    }

    /**
     * Solid Gauge renderer (shared)
     * keys: maps backend field names to {pct, quotes, po}
     */
    function renderConvGauge(g, {
        containerId = 'convGauge',
        keys = { pct:'pct', quotes:'quotes_region_sar', po:'po_user_region_raw' }
    } = {}) {
        if (!g) return;

        const pctVal = Number(g?.[keys.pct]    ?? 0);
        const quotes = Number(g?.[keys.quotes] ?? 0);
        const po     = Number(g?.[keys.po]     ?? 0);
        const pct    = Math.max(0, Math.min(100, pctVal));
        const sar    = (n)=> 'SAR ' + Number(n||0).toLocaleString();

        Highcharts.chart(containerId, {
            chart:{ type:'solidgauge', backgroundColor:'transparent' },
            title:null, credits:{enabled:false},
            pane:{
                startAngle:-140, endAngle:140, center:['50%','55%'], size:'100%',
                background:[{ outerRadius:'100%', innerRadius:'70%', shape:'arc', backgroundColor:'rgba(255,255,255,0.08)'}]
            },
            yAxis:{ min:0, max:100, lineWidth:0, tickWidth:0, labels:{enabled:false} },
            tooltip:{
                useHTML:true,
                pointFormatter:function(){
                    return `<div><b>${pct.toFixed(1)}% converted</b></div>
                        <div>Quotation Value <b>${sar(quotes)}</b></div>
                        <div>PO Value <b>${sar(po)}</b></div>`;
                }
            },
            plotOptions:{
                solidgauge:{
                    dataLabels:{
                        useHTML:true, y:-10, borderWidth:0,
                        formatter:function(){
                            return `<div style="text-align:center">
                                  <div style="font-size:26px;font-weight:800;color:#fff">${pct.toFixed(1)}</div>
                                  <div style="font-size:14px;color:#38bdf8;font-weight:700">%</div>
                                </div>`;
                        }
                    }
                }
            },
            series:[{ data:[{ y:pct, color:'#38bdf8' }] }]
        });
    }

    /* ================= NEW: read new backend objects & render gauges ================= */

    /** Safely pick gauge objects from new/old shapes. */
    /** Safely pick gauge objects from new/old shapes. */
    function extractGaugePayload(j){
        // New shared-PO-pool object
        const shared = j?.conversion_shared_pool || null;  // { pool_sar, denoms:{total_sar,inhand_sar,bidding_sar}, pct:{overall,inhand,bidding} }

        // Old shapes (kept for fallback / future)
        const cg         = j?.conversion_gauge_inhand_bidding || null;           // matched quotes↔POs (legacy)
        const cgTotal    = j?.conversion_gauge_inhand_bidding_total || null;     // legacy overall
        const projSums   = j?.project_sum_inhand_biddign || j?.project_sum_inhand_bidding || null;
        const cgLegacy   = j?.conversion_gauge || null;                           // legacy “normal conversion”
        const legacyOverall = cgLegacy || null;

        return { shared, cg, cgTotal, projSums, legacyOverall, cgLegacy };
    }

    /* ================= Fetch KPIs + render ================= */
    async function loadKpisAndCharts(){
        try {
            // Build query from current UI filters
            const qs  = new URLSearchParams(buildFilters()).toString();
            const url = `{{ route('salesorders.manager.kpis') }}?${qs}`;

            // Fetch JSON (AJAX)
            const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
            if (!res.ok) {
                console.error('KPIs fetch failed', res.status, res.statusText);
                return;
            }
            const j = await res.json();

            /* ===== Badges (count/value) ===== */
            $('#badgeCount').text('Total Sales-Order No: ' + Number(j?.totals?.count || 0).toLocaleString());
            $('#badgeValue').text('Total Sales-Order Value: ' + fmtSAR(j?.totals?.value || 0));

            /* ===== Column chart: PO vs Forecast vs Target ===== */
            if (j?.po_forecast_target) {
                renderPoFcTarget(j.po_forecast_target);
            }

            /* ===== Gauges (Overall, In-Hand, Bidding) — uses SHARED PO POOL ===== */
            const { shared, cg, cgTotal, projSums, legacyOverall, cgLegacy } = extractGaugePayload(j);

            // Optional: show the shared pool value in a badge if such element exists
            if (shared?.pool_sar != null && document.querySelector('#badgePoolSar')) {
                $('#badgePoolSar').text(`Shared PO Pool: ${fmtSAR(shared.pool_sar)}`);
            }

            if (shared) {
                // Build gauge-friendly objects (keys: pct, quotes_total_sar, po_total_sar)
                const overObj = {
                    pct:               Number(shared?.pct?.overall || 0),
                    quotes_total_sar:  Number(shared?.denoms?.total_sar || 0),
                    po_total_sar:      Number(shared?.pool_sar || 0),
                };
                const inObj = {
                    pct:               Number(shared?.pct?.inhand || 0),
                    quotes_total_sar:  Number(shared?.denoms?.inhand_sar || 0),
                    po_total_sar:      Number(shared?.pool_sar || 0),
                };
                const bidObj = {
                    pct:               Number(shared?.pct?.bidding || 0),
                    quotes_total_sar:  Number(shared?.denoms?.bidding_sar || 0),
                    po_total_sar:      Number(shared?.pool_sar || 0),
                };

                // Overall
                renderConvGauge(overObj, {
                    containerId: 'convGauge',
                    keys: { pct:'pct', quotes:'quotes_total_sar', po:'po_total_sar' }
                });

                // In-Hand
                renderConvGauge(inObj, {
                    containerId: 'convGaugeInHand',
                    keys: { pct:'pct', quotes:'quotes_total_sar', po:'po_total_sar' }
                });

                // Bidding
                renderConvGauge(bidObj, {
                    containerId: 'convGaugeBidding',
                    keys: { pct:'pct', quotes:'quotes_total_sar', po:'po_total_sar' }
                });

            } else {
                // ===== FALLBACKS (legacy) — keep for safety; comment out if not needed =====

                // Legacy overall (“normal conversion”)
                if (cgLegacy) {
                    const PO_KEY = 'po_user_region_last'; // or 'po_user_region_raw'
                    renderConvGauge(cgLegacy, {
                        containerId: 'convGauge',
                        keys: { pct:'pct', quotes:'quotes_region_sar', po: PO_KEY }
                    });
                }

                // Legacy inhand/bidding matched
                if (cg?.inhand) {
                    renderConvGauge(cg.inhand, {
                        containerId: 'convGaugeInHand',
                        keys: { pct:'pct', quotes:'quotes_total_sar', po:'po_total_sar' }
                    });
                }
                if (cg?.bidding) {
                    renderConvGauge(cg.bidding, {
                        containerId: 'convGaugeBidding',
                        keys: { pct:'pct', quotes:'quotes_total_sar', po:'po_total_sar' }
                    });
                }
            }
            /* ===== end gauges ===== */

            /* ===== Denominator badges (optional UI spans) ===== */
            if (projSums?.inhand && document.querySelector('#badgeInHandDenom')) {
                $('#badgeInHandDenom').text(
                    `In-Hand Quotes: ${fmtSAR(projSums.inhand.sum_sar)} (${Number(projSums.inhand.count).toLocaleString()} quotes)`
                );
            }
            if (projSums?.bidding && document.querySelector('#badgeBiddingDenom')) {
                $('#badgeBiddingDenom').text(
                    `Bidding Quotes: ${fmtSAR(projSums.bidding.sum_sar)} (${Number(projSums.bidding.count).toLocaleString()} quotes)`
                );
            }

            /* ===== Projects Region Pie ===== */
            if (j?.projectsRegionPie) {
                renderProjectsRegionPie(j.projectsRegionPie);
            }

            /* ===== Region mix warning for logged-in user ===== */
            if (j?.user_region_warning) {
                paintRegionShareAlert({
                    home: j.user_region_warning.home || '',
                    home_pct: j.user_region_warning.home_pct || 0,
                    reason: j.user_region_warning.reason || ''
                });
            } else {
                // If your alert has a “hide” method or you remove content:
                paintRegionShareAlert({ home:'', home_pct:0, reason:'' });
            }

            /* ===== (Optional) Additional charts if you use them elsewhere =====
               Example hooks—uncomment if your page defines these renderers:

               if (j?.monthly) renderMonthlyLine(j.monthly);
               if (j?.productSeries) renderTopProductsBar(j.productSeries);
               if (j?.statusPie) renderStatusPie(j.statusPie);
               if (j?.monthly_product_value) renderMonthlyProductValue(j.monthly_product_value);
               if (j?.productCluster) renderProductCluster(j.productCluster);
               if (j?.monthlyProductCluster) renderMonthlyProductCluster(j.monthlyProductCluster);
               if (j?.multiMonthly) renderMultiMonthly(j.multiMonthly);
               if (j?.salesperson_region_mix) renderSalespersonRegionMix(j.salesperson_region_mix);
            */

        } catch (err) {
            console.error('loadKpisAndCharts error:', err);
        }
    }

    /* ================= DataTable (unchanged) ================= */
    let dt = $('#dtSalesOrders').DataTable({
        processing: true,
        serverSide: true,
        order: [[1,'desc']],
        ajax: {
            url: '{{ route('salesorders.manager.datatable') }}',
            data: d => Object.assign(d, buildFilters())
        },
        columns: [
            { data: 'DT_RowIndex',  title:'#', orderable:false, searchable:false },
            { data: 'po_no',         title:'PO No' },
            { data: 'date_rec',      title:'Date' },
            { data: 'region',        title:'Region' },
            { data: 'client_name',   title:'Client' },
            { data: 'project_region',   title:'Project region' },
            { data: 'project_location',   title:'Project Location'},

            { data: 'project_name',  title:'Project' },
            { data: 'product_family',title:'Product' },
            { data: 'value_with_vat',title:'Value with VAT', className:'text-end', render:d=>'SAR '+Number(d||0).toLocaleString() },
            { data: 'po_value',      title:'PO Value',       className:'text-end', render:d=>'SAR '+Number(d||0).toLocaleString() },
            { data: 'status',        title:'Status' },
            { data: 'salesperson',   title:'Salesperson' },
            { data: 'remarks',       title:'Remarks' }
        ]
    });

    /* ================= Events & Boot ================= */
    $('#familyChips').off('click').on('click','[data-family]', async function(){
        $('#familyChips [data-family]').removeClass('active');
        $(this).addClass('active');
        currentFamily = $(this).data('family') || '';
        dt.ajax.reload(null,false);
        await loadKpisAndCharts();
    });

    $('#statusTabs').off('click').on('click','button[data-status]', async function(){
        $('#statusTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status') || '';
        dt.ajax.reload(null,false);
        await loadKpisAndCharts();
    });

    $('#btnApply').off('click').on('click', async ()=>{
        dt.ajax.reload(null,false);
        await loadKpisAndCharts();
    });
    function renderProjectsRegionPie(data) {
        if (!Array.isArray(data)) return;

        // --- fallback for older Highcharts versions ---
        const escapeHTML = (str) => {
            if (typeof str !== 'string') return str;
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        Highcharts.chart('projectsRegionPie', {
            chart: { type: 'pie', backgroundColor: 'transparent' },
            title: {
                text: 'Sales by Projects Region (%)',
                style: { color: '#95c53d', fontSize: '16px', fontWeight: '700' }
            },
            credits: { enabled: false },

            tooltip: {
                useHTML: true,
                formatter: function () {
                    const yPct = Number(this.point.y || 0);
                    const pct  = yPct.toFixed(2) + '%';
                    const sar  = 'SAR ' + Number(this.point.value || 0).toLocaleString();
                    return `<b>${escapeHTML(this.point.name)}</b><br/>
                        Share: <b>${pct}</b><br/>
                        Value: <b>${sar}</b>`;
                }
            },

            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        style: { color: '#EAF6FF', fontWeight: '700', textOutline: 'none' },
                        formatter: function () {
                            const yPct = Number(this.point.y || 0);
                            // Show label only if >= 2%
                            return yPct >= 2
                                ? `${escapeHTML(this.point.name)}: ${yPct.toFixed(1)}%`
                                : null;
                        }
                    }
                }
            },

            series: [{
                name: 'Share',
                data: data
            }]
        });
    }


    // function renderSalespersonRegionMix(kpis) {
    //     // Banner for the logged-in user
    //     const warn = kpis.user_region_warning;
    //     const banner = document.getElementById('salespersonRegionWarning');
    //     if (warn && warn.show) {
    //         banner.style.display = 'block';
    //         banner.innerHTML = `
    //   <strong>Heads up:</strong> your home region (<em>${warn.home}</em>) share is
    //   <strong>${warn.home_pct}%</strong>. ${warn.reason || ''}
    // `;
    //     } else {
    //         banner.style.display = 'none';
    //         banner.innerHTML = '';
    //     }
    //
    //     // Quick list/table for management
    //     const rows = (kpis.salesperson_region_mix || []).map(r => {
    //         const e = r.pct.eastern ?? 0, c = r.pct.central ?? 0, w = r.pct.western ?? 0;
    //         const flag = r.warn ? '⚠️' : '✅';
    //         return `
    //   <tr>
    //     <td>${flag}</td>
    //     <td>${r.salesperson}</td>
    //     <td>${r.home_region ?? '-'}</td>
    //     <td style="text-align:right">${r.home_pct?.toFixed(1) ?? '0.0'}%</td>
    //     <td style="text-align:right">${e.toFixed(1)}%</td>
    //     <td style="text-align:right">${c.toFixed(1)}%</td>
    //     <td style="text-align:right">${w.toFixed(1)}%</td>
    //   </tr>`;
    //     }).join('');
    //
    //     const table = `
    // <div class="card">
    //   <div class="card-header">Salesperson region mix (flag if home &lt; 50%)</div>
    //   <div class="card-body p-0">
    //     <table class="table table-sm mb-0">
    //       <thead>
    //         <tr>
    //           <th style="width:36px"></th>
    //           <th>Salesperson</th>
    //           <th>Home</th>
    //           <th style="text-align:right">Home %</th>
    //           <th style="text-align:right">Eastern %</th>
    //           <th style="text-align:right">Central %</th>
    //           <th style="text-align:right">Western %</th>
    //         </tr>
    //       </thead>
    //       <tbody>${rows}</tbody>
    //     </table>
    //   </div>
    // </div>`;
    //     document.getElementById('salespersonRegionTable').innerHTML = table;
    // }






    // Creates (once) and toggles a pulsing warning icon on the pie
    function paintRegionShareAlert({ home = '-', home_pct = 0, reason = '' } = {}) {
        const host = document.getElementById('projectsRegionPie');
        if (!host) return;

        // create the icon once
        let icon = host.querySelector('.warn-pulse');
        if (!icon) {
            icon = document.createElement('div');
            icon.className = 'warn-pulse';
            icon.innerHTML = `
      <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true">
        <path fill="#ff1a1a" d="M1 21h22L12 2 1 21z"></path>
        <rect x="11" y="8" width="2" height="7" fill="#fff"/>
        <rect x="11" y="17" width="2" height="2" fill="#fff"/>
      </svg>
    `;
            host.appendChild(icon);
        }

        // Tooltip text
        icon.title = `⚠ Home region: ${home.toUpperCase()} — ${home_pct.toFixed(1)}%. ${reason || ''}`;

        // Show only if under threshold
        icon.style.display = Number(home_pct || 0) < 50 ? 'block' : 'none';
    }

    $('#searchInput').on('input', e=> dt.search(e.target.value).draw());

    // Initial boot
    (async function boot(){
        await loadKpisAndCharts();
    })();
</script>

</body>
</html>
