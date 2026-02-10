@extends('layouts.app')

@section('title', 'ATAI Projects — Live')

@push('head')
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Sales Order Log — Manager</title>
{{--    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">--}}

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
{{--    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">--}}
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme-20260210.css') }}?v={{ filemtime(public_path('css/atai-theme-20260210.css')) }}">

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

        /* ===== Region chips (All / Eastern / Central / Western) ===== */
        #regionChips {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        #regionChips .btn {
            border-radius: 9999px;
            font-weight: 600;
            letter-spacing: 0.2px;
            padding: 0.35rem 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05);
            color: #e6e9ff;
            opacity: 0.75;
            transition: all 0.15s ease;
        }

        #regionChips .btn:hover {
            opacity: 1;
            border-color: rgba(255, 255, 255, 0.25);
        }

        /* Active (selected) state — same glow/gradient as Value/Count */
        #regionChips .btn.active {
            color: #0b1220;
            background: linear-gradient(180deg, #9aff8a, #6ff16a);
            border-color: rgba(0, 0, 0, 0.15);
            box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.35);
        }

        .kpi-card-title {
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            font-size: .8rem;
            margin-top: .35rem;
            opacity: .9
        }

        #statusChips .btn.active,
        #statusChips [aria-pressed="true"] {
            background: linear-gradient(180deg, rgba(149, 197, 61, .30), rgba(149, 197, 61, .22)) !important;
            border-color: rgba(149, 197, 61, .45) !important;
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(149, 197, 61, .2);
            position: relative;
        }
    </style>
@endpush
@section('content')

    @php $u = auth()->user();
   $isManager = $u && ($u->hasRole('admin') || $u->hasRole('gm'));@endphp

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
            @if($isManager)
                <div class="ms-2">
                    <select id="fSalesman" class="form-select form-select-sm">
                        <option value="">All Salesmen</option>
                        {{-- Options will be loaded via API for freshness --}}
                    </select>
                </div>
            @endif

            <button id="btnApply" class="btn btn-primary btn-sm">Update</button>

            <div class="form-check ms-2">
                <input class="form-check-input" type="checkbox" id="fIncludeRejected">
                <label class="form-check-label text-muted" for="fIncludeRejected">
                    Include Rejected Value
                </label>
            </div>
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
        <!-- Region chips -->
        <div class="d-flex justify-content-center gap-2 my-3 flex-wrap">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap" id="regionChips">
                <span class="text-muted me-2">Region:</span>
                <button type="button" class="btn btn-sm btn-outline-primary active" data-region="">
                    All
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-region="eastern">
                    Eastern
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-region="central">
                    Central
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-region="western">
                    Western
                </button>
            </div>
        </div>


        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-between my-3 gap-2">
                <!-- LEFT: Status tabs -->
                <div class="status-wrap">
                    <ul class="nav nav-tabs mb-0" id="statusTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-status="" type="button">All</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-status="Acceptance" type="button">Acceptance</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-status="Pre-Acceptance" type="button">Pre-Acceptance</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-status="Rejected" type="button">Rejected</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-status="Waiting" type="button">Waiting</button>
                        </li>
                    </ul>
                </div>

                <!-- RIGHT: Family chips -->
                <div class="family-wrap ms-auto">
                    <div id="familyChips" class="btn-group flex-wrap" role="group" aria-label="Product family">
                        <button type="button" class="btn btn-sm btn-outline-primary active" data-family="">All</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-family="ductwork">Ductwork
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-family="dampers">Dampers
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-family="sound">Sound
                            Attenuators
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-family="accessories">
                            Accessories
                        </button>
                    </div>
                </div>
            </div>
        </div>


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
                    <div class="kpi-card-title">Conversion (Quotations → Sales Order)</div>
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
@endsection

@push('scripts')

    <script>
        /* ======================================================================
           ATAI — Sales Order Log KPI Page JS
           - Uses new backend keys:
               - conversion_gauge_inhand_bidding           (inhand/bidding blocks)
               - conversion_gauge_inhand_bidding_total     (overall combined)
               - project_sum_inhand_biddign                (denominators; typo kept)
           - Backward compatible with legacy keys.
           ====================================================================== */
        const USER_NAME = @json($u->name ?? '');
        const USER_REGION = @json(strtolower($u->region ?? ''));
        const IS_MANAGER = @json(($u && ($u->hasRole('admin') || $u->hasRole('gm'))) ? true : false);
        const $salesman = $('#fSalesman');
        let currentSalesman = '';
        /* ================= Helpers ================= */
        const fmtSAR = (n) => 'SAR ' + Number(n || 0).toLocaleString();

        function fmtCompactSAR(n) {
            const v = Number(n || 0), a = Math.abs(v);
            if (a >= 1e9) return (v / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
            if (a >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
            if (a >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
            return v.toLocaleString();
        }

        let currentFamily = '';
        let currentStatus = '';
        let currentRegion = '';
        const $year = $('#fYear');
        const $month = $('#fMonth');
        const $from = $('#fFrom');
        const $to = $('#fTo');
        const DEFAULT_YEAR = '2026';

        function buildFilters() {
            const st = (currentStatus || '').toString().trim();
            const stLower = st.toLowerCase();

            // ✅ Read checkbox state (this is the missing part)
            const includeRejectedChecked = $('#fIncludeRejected').is(':checked');

            // ✅ Rule:
            // - Always include rejected if user is on "Rejected" tab
            // - Otherwise, include rejected only if checkbox is ticked
            const includeRejected = (stLower === 'rejected' || includeRejectedChecked) ? 1 : 0;

            return {
                year: $year.val() || DEFAULT_YEAR,
                month: $month.val() || '',
                from: $from.val() || '',
                to: $to.val() || '',
                family: currentFamily || '',

                // backend reads these
                oaa: st,
                status: st,

                region: currentRegion || '',
                salesman: IS_MANAGER ? (currentSalesman || '') : '',

                // ✅ FIXED
                include_rejected: includeRejected
            };
        }

        async function loadSalesmenDropdown() {
            if (!IS_MANAGER || !$salesman.length) return;

            try {
                const res = await fetch(`{{ route('salesorders.manager.salesmen') }}`, {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (!res.ok) return;

                const list = await res.json(); // expected: ["SOHAIB","TARIQ","JAMAL",...]
                const opts = ['<option value="">All Salesmen</option>']
                    .concat(list.map(n => `<option value="${String(n)}">${String(n)}</option>`));
                $salesman.html(opts.join(''));

            } catch (e) {
                console.error('loadSalesmenDropdown failed', e);
            }
        }
        $(document).on('change', '#fSalesman', async function () {
            currentSalesman = $(this).val() || '';
            dt.ajax.reload(null, false);
            await loadKpisAndCharts();
        });
        function homeRegionByUser(name, explicitRegion) {
            const key = String(name || '').trim().toUpperCase().split(' ')[0];
            if (['SOHAIB', 'SOAHIB'].includes(key)) return 'eastern';
            if (['TARIQ', 'TAREQ', 'JAMAL'].includes(key)) return 'central';
            if (['ABDO', 'ABDUL', 'ABDOU', 'AHMED'].includes(key)) return 'western';
            return ['eastern', 'central', 'western'].includes(explicitRegion) ? explicitRegion : 'eastern';
        }

        /* ================= Charts ================= */

        /** PO vs Forecast vs Target (unchanged) */
        function renderPoFcTarget(payload) {
            if (!payload || !Array.isArray(payload.categories)) return;
            const [po, forecast, target] = payload.series || [null, null, null];

            const seriesStaggered = (payload.series || []).map((s, i) => ({
                ...s,
                dataLabels: {
                    enabled: true,
                    inside: false,
                    y: -6 - i * 14,
                    padding: 6,
                    borderRadius: 6,
                    borderWidth: 1,
                    style: {color: '#EAF6FF', fontWeight: '800', textOutline: 'none', fontSize: '11px'},
                    formatter() {
                        const v = Number(this.y || 0);
                        return v > 0 ? ('SAR ' + fmtCompactSAR(v)) : '';
                    }
                }
            }));

            Highcharts.chart('poFcTargetChart', {
                chart: {type: 'column', backgroundColor: 'transparent', spacingTop: 22, spacingRight: 30},
                title: {
                    text: 'PO vs Forecast vs Target',
                    align: 'center',
                    style: {color: '#95c53d', fontSize: '16px', fontWeight: '700'},
                    margin: 10
                },
                credits: {enabled: false},

                xAxis: {
                    categories: payload.categories,
                    tickInterval: 1,
                    tickmarkPlacement: 'on',
                    lineColor: 'rgba(255,255,255,.12)',
                    tickColor: 'rgba(255,255,255,.12)',
                    showFirstLabel: true,
                    showLastLabel: true,
                    labels: {
                        style: {color: '#A7B5CC', fontWeight: '600'},
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
                    title: {text: 'Value (SAR)', style: {color: '#CFE0FF', fontWeight: '700'}},
                    labels: {
                        style: {color: '#A7B5CC'},
                        formatter() {
                            return Highcharts.numberFormat(this.value / 1e6, 0) + 'M';
                        }
                    },
                    gridLineColor: 'rgba(255,255,255,0.10)',
                    maxPadding: 0.12
                },

                tooltip: {
                    shared: true,
                    useHTML: true,
                    className: 'hc-tooltip',
                    borderWidth: 0,
                    formatter: function () {
                        const cat = Highcharts.escapeHTML(this.x);
                        let html = `<div class="label" style="margin-bottom:4px">${cat}</div>`;
                        this.points.forEach(p => {
                            const dot = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color};margin-right:6px"></span>`;
                            html += `${dot}<span>${Highcharts.escapeHTML(p.series.name)}:</span> <b>SAR ${fmtCompactSAR(p.y)}</b><br/>`;
                        });
                        return html;
                    }
                },

                plotOptions: {
                    series: {
                        states: {hover: {enabled: true, halo: {size: 8, opacity: .25}}},
                        dataLabels: {
                            enabled: true,
                            padding: 6,
                            rotation: -90,
                            borderRadius: 6,
                            borderWidth: 1,
                            crop: false,
                            overflow: 'none',
                            style: {color: '#EAF6FF', fontWeight: '800', textOutline: 'none', fontSize: '11px'},
                            formatter() {
                                const v = Number(this.y || 0);
                                if (v <= 0) return '';
                                return 'SAR ' + fmtCompactSAR(v);
                            }
                        }
                    },
                    column: {pointPadding: 0.12, groupPadding: 0.18, borderWidth: 0, borderRadius: 3}
                },

                colors: ['#60a5fa', '#34d399', '#f59e0b'],
                series: [
                    {...(target || {}), color: '#f6a800'},
                    {...(forecast || {}), color: '#00c896'},
                    {...(po || {}), color: '#6dafff'}
                ]
            });
        }

        /**
         * Solid Gauge renderer (shared)
         * keys: maps backend field names to {pct, quotes, po}
         */
        function renderConvGauge(g, {
            containerId = 'convGauge',
            keys = {pct: 'pct', quotes: 'quotes_region_sar', po: 'po_user_region_raw'}
        } = {}) {
            if (!g) return;

            const pctVal = Number(g?.[keys.pct] ?? 0);
            const quotes = Number(g?.[keys.quotes] ?? 0);
            const po = Number(g?.[keys.po] ?? 0);
            const pct = Math.max(0, Math.min(100, pctVal));
            const sar = (n) => 'SAR ' + Number(n || 0).toLocaleString();

            Highcharts.chart(containerId, {
                chart: {type: 'solidgauge', backgroundColor: 'transparent'},
                title: null, credits: {enabled: false},
                pane: {
                    startAngle: -140, endAngle: 140, center: ['50%', '55%'], size: '100%',
                    background: [{
                        outerRadius: '100%',
                        innerRadius: '70%',
                        shape: 'arc',
                        backgroundColor: 'rgba(255,255,255,0.08)'
                    }]
                },
                yAxis: {min: 0, max: 100, lineWidth: 0, tickWidth: 0, labels: {enabled: false}},
                tooltip: {
                    useHTML: true,
                    pointFormatter: function () {
                        return `<div><b>${pct.toFixed(1)}% converted</b></div>
                        <div>PO Value <b>${sar(po)}</b></div>
                        <div>Quotation Value <b>${sar(quotes)}</b></div>
                       `;
                    }
                },
                plotOptions: {
                    solidgauge: {
                        dataLabels: {
                            useHTML: true, y: -10, borderWidth: 0,
                            formatter: function () {
                                return `<div style="text-align:center">
                                  <div style="font-size:26px;font-weight:800;color:#fff">${pct.toFixed(1)}</div>
                                  <div style="font-size:14px;color:#38bdf8;font-weight:700">%</div>
                                </div>`;
                            }
                        }
                    }
                },
                series: [{data: [{y: pct, color: '#38bdf8'}]}]
            });
        }

        /* ================= NEW: read new backend objects & render gauges ================= */

        /** Safely pick gauge objects from new/old shapes. */
        /** Safely pick gauge objects from new/old shapes. */
        function extractGaugePayload(j) {
            // New shared-PO-pool object
            const shared = j?.conversion_shared_pool || null;  // { pool_sar, denoms:{total_sar,inhand_sar,bidding_sar}, pct:{overall,inhand,bidding} }

            // Old shapes (kept for fallback / future)
            const cg = j?.conversion_gauge_inhand_bidding || null;           // matched quotes↔POs (legacy)
            const cgTotal = j?.conversion_gauge_inhand_bidding_total || null;     // legacy overall
            const projSums = j?.project_sum_inhand_biddign || j?.project_sum_inhand_bidding || null;
            const cgLegacy = j?.conversion_gauge || null;                           // legacy “normal conversion”
            const legacyOverall = cgLegacy || null;

            return {shared, cg, cgTotal, projSums, legacyOverall, cgLegacy};
        }

        /* ================= Fetch KPIs + render ================= */
        async function loadKpisAndCharts() {
            try {
                // Build query from current UI filters
                const qs = new URLSearchParams(buildFilters()).toString();
                const url = `{{ route('salesorders.manager.kpis') }}?${qs}`;

                // Fetch JSON (AJAX)
                const res = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
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
                const {shared, cg, cgTotal, projSums, legacyOverall, cgLegacy} = extractGaugePayload(j);

                // Optional: show the shared pool value in a badge if such element exists
                if (shared?.pool_sar != null && document.querySelector('#badgePoolSar')) {
                    $('#badgePoolSar').text(`Shared PO Pool: ${fmtSAR(shared.pool_sar)}`);
                }

                if (shared) {
                    // Build gauge-friendly objects (keys: pct, quotes_total_sar, po_total_sar)
                    const overObj = {
                        pct: Number(shared?.pct?.overall || 0),
                        quotes_total_sar: Number(shared?.denoms?.total_sar || 0),
                        po_total_sar: Number(shared?.pool_sar || 0),
                    };
                    const inObj = {
                        pct: Number(shared?.pct?.inhand || 0),
                        quotes_total_sar: Number(shared?.denoms?.inhand_sar || 0),
                        po_total_sar: Number(shared?.pool_sar || 0),
                    };
                    const bidObj = {
                        pct: Number(shared?.pct?.bidding || 0),
                        quotes_total_sar: Number(shared?.denoms?.bidding_sar || 0),
                        po_total_sar: Number(shared?.pool_sar || 0),
                    };

                    // Overall
                    renderConvGauge(overObj, {
                        containerId: 'convGauge',
                        keys: {pct: 'pct', quotes: 'quotes_total_sar', po: 'po_total_sar'}
                    });

                    // In-Hand
                    renderConvGauge(inObj, {
                        containerId: 'convGaugeInHand',
                        keys: {pct: 'pct', quotes: 'quotes_total_sar', po: 'po_total_sar'}
                    });

                    // Bidding
                    renderConvGauge(bidObj, {
                        containerId: 'convGaugeBidding',
                        keys: {pct: 'pct', quotes: 'quotes_total_sar', po: 'po_total_sar'}
                    });

                } else {
                    // ===== FALLBACKS (legacy) — keep for safety; comment out if not needed =====

                    // Legacy overall (“normal conversion”)
                    if (cgLegacy) {
                        const PO_KEY = 'po_user_region_last'; // or 'po_user_region_raw'
                        renderConvGauge(cgLegacy, {
                            containerId: 'convGauge',
                            keys: {pct: 'pct', quotes: 'quotes_region_sar', po: PO_KEY}
                        });
                    }

                    // Legacy inhand/bidding matched
                    if (cg?.inhand) {
                        renderConvGauge(cg.inhand, {
                            containerId: 'convGaugeInHand',
                            keys: {pct: 'pct', quotes: 'quotes_total_sar', po: 'po_total_sar'}
                        });
                    }
                    if (cg?.bidding) {
                        renderConvGauge(cg.bidding, {
                            containerId: 'convGaugeBidding',
                            keys: {pct: 'pct', quotes: 'quotes_total_sar', po: 'po_total_sar'}
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
                    applyRegionAlert(j.projectsRegionPie);
                }

                /* ===== Region mix warning for logged-in user ===== */
                // if (j?.user_region_warning) {
                //     paintRegionShareAlert({
                //         home: j.user_region_warning.home || '',
                //         home_pct: j.user_region_warning.home_pct || 0,
                //     });
                // } else {
                //     paintRegionShareAlert({ home:'', home_pct:0, reason:'' });
                // }

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
            order: [[1, 'desc']],
            ajax: {
                url: '{{ route('salesorders.manager.datatable') }}',
                data: d => Object.assign(d, buildFilters())
            },
            columns: [
                {data: 'DT_RowIndex', title: '#', orderable: false, searchable: false},
                {data: 'po_no', title: 'PO No'},
                {data: 'date_rec', title: 'Date'},
                {data: 'region', title: 'Region'},
                {data: 'client_name', title: 'Client'},
                {data: 'project_region', title: 'Project region'},
                {data: 'project_location', title: 'Project Location'},

                {data: 'project_name', title: 'Project'},
                {data: 'product_family', title: 'Product'},
                {
                    data: 'value_with_vat',
                    title: 'Value with VAT',
                    className: 'text-end',
                    render: d => 'SAR ' + Number(d || 0).toLocaleString()
                },
                {
                    data: 'po_value',
                    title: 'PO Value',
                    className: 'text-end',
                    render: d => 'SAR ' + Number(d || 0).toLocaleString()
                },
                {data: 'sales_oaa', title: 'Sales OAA'},
                {data: 'salesperson', title: 'Salesperson'},
                {data: 'remarks', title: 'Remarks'}
            ]
        });

        /* ================= Events & Boot ================= */
        $('#familyChips').off('click').on('click', '[data-family]', async function () {
            $('#familyChips [data-family]').removeClass('active');
            $(this).addClass('active');
            currentFamily = $(this).data('family') || '';
            dt.ajax.reload(null, false);
            await loadKpisAndCharts();
        });

        $('#statusTabs').off('click').on('click', 'button[data-status]', async function () {
            $('#statusTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            currentStatus = $(this).data('status') || '';
            dt.ajax.reload(null, false);
            await loadKpisAndCharts();
        });

        $('#btnApply').off('click').on('click', async () => {
            dt.ajax.reload(null, false);
            await loadKpisAndCharts();
        });

        $('#fIncludeRejected').off('change').on('change', async function () {
            dt.ajax.reload(null, false);
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
                chart: {type: 'pie', backgroundColor: 'transparent'},
                title: {
                    text: 'Sales by Projects Region (%)',
                    style: {color: '#95c53d', fontSize: '16px', fontWeight: '700'}
                },
                credits: {enabled: false},

                tooltip: {
                    useHTML: true,
                    formatter: function () {
                        const yPct = Number(this.point.y || 0);
                        const pct = yPct.toFixed(2) + '%';
                        const sar = 'SAR ' + Number(this.point.value || 0).toLocaleString();
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
                            style: {color: '#EAF6FF', fontWeight: '700', textOutline: 'none'},
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

        // Decide if the alert should show based on the pie data + logged-in user
        function applyRegionAlert(pieData) {
            if (!Array.isArray(pieData) || !pieData.length) {
                paintRegionShareAlert({show: false});
                return;
            }

            // normalize & sort
            const norm = s => String(s || '').trim().toLowerCase();
            const pie = pieData
                .map(r => ({name: norm(r.name), pct: Number(r.y || 0), sar: Number(r.value || 0)}))
                .sort((a, b) => b.pct - a.pct);

            const top = pie[0];
            const second = pie[1] || {pct: 0};
            const homeName = norm(homeRegionByUser(USER_NAME, USER_REGION));
            const home = pie.find(r => r.name === homeName) || {pct: 0, sar: 0};

            // thresholds (tweak)
            const THRESH_HOME_MIN = 50; // alert if home share is below this
            const THRESH_GAP = 10; // or if some other region is ahead of home by ≥ this

            // Show alert when home is NOT dominant
            const homeBelowMin = home.pct < THRESH_HOME_MIN;
            const otherLeadsHome = (top.name !== homeName) && ((top.pct - home.pct) >= THRESH_GAP);

            const show = homeBelowMin || otherLeadsHome;

            const fmt = n => new Intl.NumberFormat('en-SA', {
                style: 'currency', currency: 'SAR', maximumFractionDigits: 0
            }).format(n || 0);

            let title = '';
            if (homeBelowMin && !otherLeadsHome) {
                title = `Heads up: Your home region (${homeName}) is only ${home.pct.toFixed(1)}% (${fmt(home.sar)}).`;
            } else if (otherLeadsHome) {
                title = `Heads up: ${top.name} leads at ${top.pct.toFixed(1)}% (${fmt(top.sar)}). Your home (${homeName}) is ${home.pct.toFixed(1)}%.`;
            }

            paintRegionShareAlert({show, title});
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
        // Creates (once) and toggles a pulsing warning icon on the pie
        function paintRegionShareAlert({show = false, title = ''} = {}) {
            const host = document.getElementById('projectsRegionPie');
            if (!host) return;

            let icon = host.querySelector('.warn-pulse');
            if (!icon) {
                icon = document.createElement('div');
                icon.className = 'warn-pulse';
                icon.style.display = 'none';
                icon.style.position = 'absolute';
                icon.style.right = '8px';
                icon.style.top = '8px';
                icon.innerHTML = `
      <svg viewBox="0 0 24 24" width="44" height="44" aria-hidden="true">
        <path fill="#ff3b3b" d="M1 21h22L12 2 1 21z"></path>
        <rect x="11" y="8" width="2" height="7" fill="#fff"/>
        <rect x="11" y="17" width="2" height="2" fill="#fff"/>
      </svg>`;
                host.style.position = 'relative';
                host.appendChild(icon);
            }
            icon.title = title || '';
            icon.style.display = show ? 'block' : 'none';
        }

        $('#searchInput').on('input', e => dt.search(e.target.value).draw());

        // Initial boot
        (async function boot() {
            if (!$year.val()) $year.val(DEFAULT_YEAR);

            await loadSalesmenDropdown();   // ✅ only runs for GM/Admin
            await loadKpisAndCharts();
        })();


        $('#regionChips').off('click').on('click', '[data-region]', async function () {
            $('#regionChips [data-region]').removeClass('active');
            $(this).addClass('active');

            currentRegion = $(this).data('region') || '';  // '' = All (no region pin)

            // Reload table & KPIs with the new region
            dt.ajax.reload(null, false);
            await loadKpisAndCharts();
        });
    </script>

@endpush
