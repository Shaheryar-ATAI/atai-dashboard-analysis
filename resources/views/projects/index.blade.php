{{-- resources/views/projects/index.blade.php --}}
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
        /* Small helpers for area badges */
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

        /* Make header filter row look light */
        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm, table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }



        /* Chart cards – compact like Sales Orders dashboard */
        .kpi-card .hc {
            height: 260px;
        }

        .kpi-card .card-body {
            padding: .75rem 1rem;
        }
    </style>
</head>
<body>

@php $u = auth()->user(); @endphp
<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2">
            <span class="brand-word">ATAI</span>
        </a>

        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                {{-- Always visible --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Quotation KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.kpis') ? 'active' : '' }}"
                       href="{{ route('inquiries.index') }}">Quotation Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.kpi') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.kpi') }}">
                        Sales Order Log KPI
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.index') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.index') }}">
                        Sales Order Log
                    </a>
                </li>
                {{-- Sales roles only --}}
                {{--                @hasanyrole('sales|sales_eastern|sales_central|sales_western')--}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">Estimation</a>
                </li>
                {{--                @endhasanyrole--}}

                {{-- GM/Admin only --}}
                @hasanyrole('gm|admin')
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('salesorders.*') ? 'active' : '' }}"
                                        href="{{ route('salesorders.index') }}">Sales Orders</a></li>
                {{--                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.index') ? 'active' : '' }}"--}}
                {{--                                        href="{{ route('performance.index') }}">Performance report</a></li>--}}
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.area*') ? 'active' : '' }}"
                                        href="{{ route('performance.area') }}">Area summary</a></li>
                <li class="nav-item"><a
                        class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active' : '' }}"
                        href="{{ route('performance.salesman') }}">SalesMan summary</a></li>
                <li class="nav-item"><a
                        class="nav-link {{ request()->routeIs('performance.product*') ? 'active' : '' }}"
                        href="{{ route('performance.product') }}">Product summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                                        href="{{ route('powerbi.jump') }}">Accounts Summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                                        href="{{ route('powerbi.jump') }}">Power BI Dashboard</a></li>
                @endhasanyrole
            </ul>

            <div class="navbar-text me-2">
                Logged in as <strong>{{ $u->name ?? '' }}</strong>
                @if(!empty($u->region))
                    · <small>{{ $u->region }}</small>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    {{-- ===== KPI SUMMARY (Highcharts) ===== --}}


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
                    {{-- Month select --}}
                    <select id="monthSelect" class="form-select form-select-sm" style="width:auto">
                        <option value="">All Months</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>
                        @endfor
                    </select>

                    {{-- OR date range (overrides year/month if used) --}}
                    <input type="date" id="dateFrom" class="form-control form-control-sm" style="width:auto"
                           placeholder="From">
                    <input type="date" id="dateTo" class="form-control form-control-sm" style="width:auto"
                           placeholder="To">

                    {{-- GM/Admin: optional free-text salesman filter for forecast --}}
                    <span id="salesmanWrap" class="d-none">
                        <input type="text" id="salesmanInput" class="form-control form-control-sm" style="width:14rem"
                               placeholder="Salesman (GM/Admin)">
                                 </span>

                    <button class="btn btn-sm btn-primary" id="projApply">Update</button>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">
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
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h6 class="mb-1">Inquiries Dashboard</h6>
                            <div class="text-secondary small">Live KPIs from MySQL (scoped by your role/region)</div>
                        </div>
                        <div class="d-flex gap-2">
                            <span id="kpiBadgeProjects" class="badge-total text-bg-info">Total Quotation No.: 0</span>
                            <span id="kpiBadgeValue" class="badge-total text-bg-primary">Total Quotation Value: SAR 0</span>
                        </div>
                    </div>
                    <div class="row mt-3 g-3">
                        <div class="col-md-6">
                            <div id="barByArea" class="hc"></div>
                        </div>
                        <div class="col-md-6">
                            <div id="pieByStatus" class="hc"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--        <div class="col-12 mt-3">--}}
    {{--            <div id="barMonthlyByArea" class="hc"></div>--}}
    {{--        </div>--}}
    <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">
        <span id="fcBadgeValue" class="badge-total text-bg-primary">Forecast Total: SAR 0</span>
        <span id="fcBadgeConv" class="badge-total text-bg-info">Conversion Rate: 0%</span>
    </div>

    <div id="barMonthlyValueTarget" class="hc" style="height:400px"></div>










    {{--    --}}{{-- ===== FORECAST KPI (Highcharts) ===== --}}
    {{--    <div class="row g-3 mt-2" id="forecastRow" style="display:none">--}}
    {{--        <div class="col-12">--}}
    {{--            <div class="card kpi-card">--}}
    {{--                <div class="card-body">--}}
    {{--                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">--}}
    {{--                        <div>--}}
    {{--                            <h6 class="mb-1">Sales Forecast Dashboard</h6>--}}
    {{--                            <div class="text-secondary small">From forecast table (filters & role applied)</div>--}}
    {{--                        </div>--}}
    {{--                        <div class="d-flex gap-2">--}}
    {{--                            <span id="fcBadgeScope" class="badge-total text-bg-secondary">All Salesmen</span>--}}
    {{--                            <span id="fcBadgeValue" class="badge-total text-bg-primary">Forecast Total: SAR 0</span>--}}
    {{--                        </div>--}}
    {{--                    </div>--}}
    {{--                    <div class="row mt-3 g-3">--}}
    {{--                        <div class="col-md-6">--}}
    {{--                            <div id="fcBarByArea" class="hc"></div>--}}
    {{--                        </div>--}}
    {{--                        <div class="col-md-6">--}}
    {{--                            <div id="fcBarBySalesman" class="hc"></div>--}}
    {{--                        </div>--}}
    {{--                    </div>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    </div>--}}

    {{-- ===== Family filter chips (All / Ductwork / Dampers / Sound / Accessories) ===== --}}



    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>
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

        // global state (no window.*)
        let PROJ_YEAR = '';
        let PROJ_REGION = '';
        let ATAI_ME = null;
        let CAN_VIEW_ALL = false;
        let currentFamily = ''; // '', 'ductwork','dampers','sound','accessories'

        /* =============================================================================
         *  KPI CHARTS
         * ============================================================================= */
        async function loadKpis() {
            const row = document.getElementById('kpiRow');
            if (row) row.style.display = '';

            const yearSel = document.querySelector('#projYear');
            const monthSel = document.querySelector('#monthSelect');
            const dateFromI = document.querySelector('#dateFrom');
            const dateToI = document.querySelector('#dateTo');
            const regionSel = document.querySelector('#projRegion');

            const year = yearSel?.value || PROJ_YEAR || '';
            const month = monthSel?.value || '';
            const df = dateFromI?.value || '';
            const dt = dateToI?.value || '';
            const family = currentFamily || '';
            const region = regionSel?.value || PROJ_REGION || '';

            const url = new URL("{{ route('projects.kpis') }}", window.location.origin);
            if (df) url.searchParams.set('date_from', df);
            if (dt) url.searchParams.set('date_to', dt);
            if (!df && !dt) {
                if (month) url.searchParams.set('month', month);
                if (year) url.searchParams.set('year', year);
            }
            if (family) url.searchParams.set('family', family);
            if (region) url.searchParams.set('area', region);

            let resp = {area: [], status: [], total_value: 0, total_count: 0};
            try {
                const res = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                if (res.ok) resp = await res.json();
                const payload = resp.monthly_value_status_with_target || {};
                // prefer the scalar the API used for the chart; fallback to 20M
                const targetValue = Number(payload.target_value ?? 20000000);

                // In-Hand total for conversion rate
                const colIH = (payload.series || []).find(s => /in-hand/i.test(s.name)) || { data: [] };
                const totalInhandValue = (colIH.data || []).reduce((sum, v) => sum + Number(v || 0), 0);

            // Update badges
                updateForecastBadges(targetValue, totalInhandValue);

            } catch (e) {
                console.warn('KPI fetch failed', e);
            }

            document.getElementById('kpiBadgeProjects').textContent = 'Total Quotation NO.: ' + Number(resp.total_count || 0).toLocaleString();
            document.getElementById('kpiBadgeValue').textContent = 'Total Quotation Value: ' + fmtSAR(Number(resp.total_value || 0));

            const baseHC = {
                chart: {height: 260, spacing: [8, 8, 8, 8]},
                credits: {enabled: false},
                legend: {enabled: false},
                plotOptions: {
                    column: {
                        dataLabels: {
                            enabled: true, formatter() {
                                return (this.y ?? 0).toLocaleString();
                            }
                        }
                    }
                }
            };

            {
                const areaStatus = resp.area_status || {categories: [], series: []};
                const el = document.getElementById('barByArea');
                if (el) {
                    Highcharts.chart('barByArea', {
                        chart: {type: 'column', height: 260, spacing: [8, 8, 8, 8]},
                        title: {text: 'Inquiries by Area'},
                        credits: {enabled: false},
                        xAxis: {categories: areaStatus.categories},
                        yAxis: {
                            min: 0,
                            title: {text: 'Count'},               // bar height = count
                            stackLabels: {
                                enabled: true,
                                formatter: function () {
                                    return this.total;
                                } // total counts on top (keep if stacked)
                            }
                        },
                        legend: {enabled: true},
                        tooltip: {
                            shared: true,
                            useHTML: true,
                            formatter: function () {
                                const header = `<b>${this.x}</b>`;
                                const lines = this.points.map(p => {
                                    const sar = p.point && typeof p.point.sar !== 'undefined' ? p.point.sar : 0;
                                    return `<span style="color:${p.color}">●</span> ${p.series.name}: `
                                        + `Count <b>${p.y}</b> • Value <b>${fmtSAR(sar)}</b>`;
                                });
                                return [header].concat(lines).join('<br/>');
                            }
                        },
                        plotOptions: {
                            column: {
                                dataLabels: {
                                    enabled: true,
                                    rotation: -90,            // vertical
                                    align: 'center',
                                    verticalAlign: 'bottom',  // position above the bar
                                    inside: false,
                                    y: -6,                    // nudge up a bit
                                    formatter: function () {
                                        const sar = (this.point && typeof this.point.sar !== 'undefined') ? this.point.sar : 0;
                                        // Use Highcharts format if available, or your own fmtSAR
                                        return 'SAR ' + Highcharts.numberFormat(Number(sar), 0);
                                        // or: return fmtSAR(sar);
                                    },
                                    style: { textOutline: 'none', fontWeight: '600', fontSize: '14px', color: '#000' },
                                    crop: false,
                                    overflow: 'none'
                                },
                                pointPadding: 0.05,
                                borderWidth: 0,
                                grouping: true
                            }
                        },
                        series: areaStatus.series
                    });
                }
            }

            // -----------------------------
            // Pie: Value by Status (In-Hand / Bidding / Lost)
            // -----------------------------
            {
                const el = document.getElementById('pieByStatus');
                if (el) {
                    const rows = resp.status || [];
                    const buckets = ['In-Hand', 'Bidding', 'Lost'];
                    const data = buckets.map(b => {
                        const r = rows.find(x => (x.status_norm || x.status) === b);
                        return {name: b, y: Number(r?.sum_value || 0)};
                    });
                    const hasData = data.some(d => d.y > 0);

                    const chart = Highcharts.chart('pieByStatus', Highcharts.merge(baseHC, {
                        chart: {type: 'pie'},
                        title: {text: 'Value by Status'},
                        tooltip: {pointFormat: '<b>{point.percentage:.1f}%</b><br/>Value: <b>{point.y:,.0f}</b>'},
                        plotOptions: {
                            pie: {
                                allowPointSelect: true,
                                dataLabels: {enabled: true, format: '{point.name}: {point.percentage:.1f}%'}
                            }
                        },
                        series: [{name: 'Value', data}],
                        lang: {noData: 'No status values.'},
                        noData: {style: {fontSize: '14px', color: '#6c757d'}}
                    }));

                    if (!hasData && Highcharts.Chart.prototype.showNoData) chart.showNoData();
                }
            }

            // // -----------------------------
            // // Monthly: Grouped (Area) + Stacked (Status). Fallback to simple monthly.
            // // -----------------------------
            // {
            //     const el = document.getElementById('barMonthlyByArea');
            //     if (el) {
            //         const val = resp.monthly_area_status_value || {categories: [], series: []};
            //         const fallback = resp.monthly_area_status || {categories: [], series: []}; // counts fallback
            //
            //         const rawCats = (val.categories && val.series?.length) ? val.categories : (fallback.categories || []);
            //         const series = (val.series && val.series.length) ? val.series : (fallback.series || []);
            //
            //         const monthLabel = (ym) => {
            //             if (!ym || ym.indexOf('-') < 0) return ym || '';
            //             const [y, m] = ym.split('-').map(Number);
            //             const d = new Date(y, (m || 1) - 1, 1);
            //             return d.toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
            //         };
            //         const categories = rawCats.map(monthLabel);
            //
            //         // SAR formatter
            //         const fmtSARshort = (n) => {
            //             const x = Number(n || 0);
            //             if (Math.abs(x) >= 1_000_000) return `SAR ${(x / 1_000_000).toFixed(1)}M`;
            //             if (Math.abs(x) >= 1_000) return `SAR ${(x / 1_000).toFixed(0)}k`;
            //             return `SAR ${x.toFixed(0)}`;
            //         };
            //
            //         const hasData = (series || []).some(s => (s.data || []).some(v => Number(v) > 0));
            //
            //         Highcharts.chart(el, {
            //             chart: {type: 'column'},
            //             title: {text: 'Inquiries Value by Month • Area (stacked by Status)'},
            //             credits: {enabled: false},
            //             xAxis: {categories},
            //             yAxis: {
            //                 min: 0,
            //                 title: {text: 'Value (SAR)'},
            //                 stackLabels: {
            //                     enabled: true,
            //                     formatter: function () {
            //                         return fmtSARshort(this.total);
            //                     }
            //                 }
            //             },
            //             legend: {align: 'center'}, // only 3 items (In-Hand/Bidding/Lost) via linkedTo
            //             plotOptions: {
            //                 column: {
            //                     stacking: 'normal',
            //                     borderWidth: 0,
            //                     dataLabels: {
            //                         enabled: true,
            //                         formatter: function () {
            //                             const v = Number(this.y || 0);
            //                             return v > 0 ? fmtSARshort(v) : '';
            //                         }
            //                     },
            //                     // Make groups compact and clearly separated per month
            //                     pointPadding: 0.05,
            //                     groupPadding: 0.18
            //                 }
            //             },
            //             tooltip: {
            //                 shared: false,
            //                 formatter: function () {
            //                     // Linked series are "Area – Status"
            //                     if (this.series.name.includes(' – ')) {
            //                         const [area, status] = this.series.name.split(' – ');
            //                         return `<b>${this.x}</b><br/>Area: <b>${area}</b><br/>Status: <b>${status}</b><br/>Value: <b>${fmtSARshort(this.y)}</b>`;
            //                     }
            //                     return `<b>${this.x}</b><br/>Status: <b>${this.series.name}</b>`;
            //                 }
            //             },
            //
            //         });
            //
            //         if (!hasData && Highcharts.Chart.prototype.showNoData) Highcharts.chart(el).showNoData();
            //     }
            // }

            // -----------------------------
// Monthly Value vs Target — grouped columns + two % lines
// -----------------------------
            {
                const el = document.getElementById('barMonthlyValueTarget');
                if (el) {
                    const payload = resp.monthly_value_status_with_target || {
                        categories: [], series: [], target_value: 0
                    };

                    // month label "Jan 25"
                    const monthLabel = (ym) => {
                        if (!ym || ym.indexOf('-') < 0) return ym || '';
                        const [y, m] = ym.split('-').map(Number);
                        return new Date(y, (m || 1) - 1, 1)
                            .toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
                    };

                    const cats = (payload.categories || []).map(monthLabel);

                    // pull 3 column series by status (ignore any 'stack' to make them CLUSTERED)
                    const colIH = (payload.series || []).find(s => /in-hand/i.test(s.name)) || {data: []};
                    const colBD = (payload.series || []).find(s => /bidding/i.test(s.name)) || {data: []};
                    const colLT = (payload.series || []).find(s => /lost/i.test(s.name)) || {data: []};

                    // compute totals per month for MoM %
                    const totals = cats.map((_, i) =>
                        Number(colIH.data?.[i] || 0) + Number(colBD.data?.[i] || 0) + Number(colLT.data?.[i] || 0)
                    );
                    const momPct = totals.map((t, i) => (i === 0 || !totals[i - 1])
                        ? 0 : Math.round(((t - totals[i - 1]) / totals[i - 1]) * 10000) / 100
                    );

                    // find existing Target % line from API; if missing, build from target_value
                    const targetLine = (payload.series || []).find(s => /target.*%/i.test(s.name));
                    const targetPct = targetLine?.data || []; // already %
                    // basic SAR short formatter
                    const fmtSARshort = (n) => {
                        const x = Number(n || 0);
                        if (Math.abs(x) >= 1_000_000) return `SAR ${(x / 1_000_000).toFixed(1)}M`;
                        if (Math.abs(x) >= 1_000) return `SAR ${(x / 1_000).toFixed(0)}k`;
                        return `SAR ${x.toFixed(0)}`;
                    };

                    Highcharts.chart(el, {
                        chart: {zoomType: 'x'},
                        title: {text: 'Monthly Value — In-Hand / Bidding / Lost + Target & MoM %'},
                        credits: {enabled: false},
                        xAxis: {categories: cats, tickInterval: 1,
                            minPadding: 0.1,   // adds left padding
                            maxPadding: 0.1,   // adds right padding
                            labels: {
                                rotation: 0
                            }},
                        yAxis: [{
                            title: {text: 'Value (SAR)'}, min: 0,
                            labels: {
                                formatter() {
                                    return fmtSARshort(this.value);
                                }
                            }
                        }, {
                            title: {text: 'Percent (%)'}, min: 0, max: 200, opposite: true
                        }],
                        legend: {align: 'center'},
                        plotOptions: {
                            column: {
                                grouping: true, // clustered (NOT stacked)
                                borderWidth: 0,
                                pointPadding: 0.1,
                                groupPadding: 0.18,
                                dataLabels: {
                                    enabled: true,
                                    formatter() {
                                        const v = Number(this.y || 0);
                                        return v > 0 ? fmtSARshort(v) : '';
                                    }
                                }
                            },
                            spline: {
                                marker: {enabled: false},
                                dataLabels: {
                                    enabled: true,
                                    formatter() {
                                        return typeof this.y === 'number' ? `${this.y.toFixed(0)}%` : '';
                                    }
                                }
                            }
                        },
                        tooltip: {
                            shared: false,
                            formatter: function () {
                                const isPct = this.series.yAxis.opposite;
                                return `<b>${this.x}</b><br/>${
                                    isPct
                                        ? `${this.series.name}: <b>${this.y}%</b>`
                                        : `${this.series.name}: <b>${fmtSARshort(this.y)}</b>`
                                }`;
                            }
                        },
                        series: [
                            // 3 grouped columns on SAR axis
                            {type: 'column', name: 'In-Hand (SAR)', data: colIH.data || [],stack: 'Value',  dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -4,
                                    format: 'SAR {y:,.0f}',
                                    style: { fontSize: '14px', color: '#000', fontWeight: 'bold' }
                                }},
                            {type: 'column', name: 'Bidding (SAR)', data: colBD.data || [],stack: 'Value',  dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -4,
                                    format: 'SAR {y:,.0f}',
                                    style: { fontSize: '14px', color: '#000' }
                                }},
                            {type: 'column', name: 'Lost (SAR)', data: colLT.data || [],stack: 'Value',  dataLabels: {
                                    enabled: true,
                                    rotation: -90,
                                    align: 'center',
                                    verticalAlign: 'bottom',
                                    inside: false,
                                    y: -4,
                                    format: 'SAR {y:,.0f}',
                                    style: { fontSize: '14px', color: '#000', fontWeight: 'bold' }
                                }},
                            // % lines on right axis
                            {
                                type: 'spline',
                                name: 'Target Attainment %',
                                yAxis: 1,
                                data: targetPct,
                                // OPTIONAL: color: '#2ca02c' // green like the mock
                            }
                        ]
                    });
                }
            }


        }


        // Family chips → refresh DT + KPI
        $(document).on('click', '#familyChips [data-family]', function (e) {
            e.preventDefault();
            $('#familyChips [data-family]').removeClass('active');
            this.classList.add('active');
            currentFamily = this.getAttribute('data-family') || '';
            // getDT('#tblBidding')?.ajax.reload(null, false);
            // getDT('#tblInhand')?.ajax.reload(null, false);
            // getDT('#tblLost')?.ajax.reload(null, false);
            loadKpis();
        });

        // Apply filters
        document.getElementById('projApply')?.addEventListener('click', () => {
            PROJ_YEAR = document.getElementById('projYear')?.value || '';
            PROJ_REGION = CAN_VIEW_ALL ? (document.getElementById('projRegion')?.value || '') : '';
            loadKpis();
            if (dtBid) dtBid.ajax.reload(null, false);
            if (dtIn) dtIn.ajax.reload(null, false);
            if (dtLost) dtLost.ajax.reload(null, false);
        });

        /* =============================================================================
         *  BOOT
         * ============================================================================= */
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


        async function saveProject(projectId){
            const payload = {
                comments: document.querySelector('#comments').value || '',
                checklist: {
                    mep_contractor_appointed: document.querySelector('#chk_mep')?.checked ?? false,
                    boq_quoted:               document.querySelector('#chk_boq_quoted')?.checked ?? false,
                    boq_submitted:            document.querySelector('#chk_boq_submitted')?.checked ?? false,
                    priced_at_discount:       document.querySelector('#chk_discount')?.checked ?? false,
                }
                // Optional: status if user explicitly picked it on UI
                // status: document.querySelector('#statusSelect')?.value || null,
            };

            const res = await fetch(`/projects/${projectId}`, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if(json?.ok){
                // update row in DataTable and close modal
                // dt.row(rowIndex).data(json.project).draw(false);
                // show toast with json.project.checklist_progress + json.project.status
            }
        }


        function updateForecastBadges(totalForecast, totalInhand) {
            const fcBadge = document.getElementById('fcBadgeValue');
            const convBadge = document.getElementById('fcBadgeConv');

            const forecastVal = Number(totalForecast || 0);
            const inhandVal   = Number(totalInhand || 0);
            const rate = forecastVal > 0 ? (inhandVal / forecastVal) * 100 : 0;

            if (fcBadge)  fcBadge.textContent  = 'Forecast Total: ' + fmtSAR(forecastVal);
            if (convBadge) convBadge.textContent = 'Conversion Rate: ' + rate.toFixed(1) + '%';
        }

    </script>


</body>
</html>
