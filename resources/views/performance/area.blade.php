{{-- resources/views/performance/area.blade.php --}}
@extends('layouts.app')

@section('title', 'ATAI Sales Orders — Live')

@push('head')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ATAI — Area Summary</title>

    <style>
        :root {
            --atai-card-bg: #050814;
            --atai-card-border: rgba(255, 255, 255, 0.08);
            --atai-soft: rgba(255, 255, 255, 0.03);
        }

        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* ===== Top KPI shell ===== */
        .kpi-shell {
            background: radial-gradient(circle at top left, #111827 0, #020617 55%, #000 100%);
            border-color: var(--atai-card-border);
            color: #e5e7eb;
        }

        .kpi-shell .form-select,
        .kpi-shell .form-control,
        .kpi-shell .btn {
            border-radius: 999px;
        }

        .kpi-shell-label {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #9ca3af;
        }

        /* ===== Cards / tables (dark) ===== */
        .card-dark {
            background-color: var(--atai-card-bg);
            border-color: var(--atai-card-border);
            color: #e5e7eb;
        }

        .card-dark .card-header {
            background: linear-gradient(90deg, rgba(15,23,42,.9), rgba(15,23,42,0.4));
            border-bottom-color: var(--atai-card-border);
        }

        .card-dark .table {
            margin-bottom: 0;
            color: #e5e7eb;
        }

        .card-dark .table thead th {
            background: #020617;
            border-bottom-color: var(--atai-card-border);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .card-dark .table tbody tr:nth-child(even) {
            background-color: var(--atai-soft);
        }

        .card-dark .table tbody tr:nth-child(odd) {
            background-color: transparent;
        }

        .card-dark .dataTables_wrapper .dataTables_info,
        .card-dark .dataTables_wrapper .dataTables_paginate,
        .card-dark .dataTables_wrapper .dataTables_length,
        .card-dark .dataTables_wrapper .dataTables_filter {
            color: #9ca3af;
            font-size: .8rem;
        }

        .card-dark .dataTables_wrapper .form-control,
        .card-dark .dataTables_wrapper .form-select {
            background-color: #020617;
            border-color: rgba(148,163,184,.4);
            color: #e5e7eb;
            font-size: .8rem;
        }

        .card-dark .dataTables_wrapper .page-link {
            background-color: #020617;
            border-color: rgba(148,163,184,.5);
            color: #e5e7eb;
        }

        .card-dark .dataTables_wrapper .page-item.active .page-link {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }

        /* ===== Month/YTD KPI mini cards ===== */
        .perf-card {
            border-radius: 1rem;
            background: radial-gradient(circle at top left, #1f2937 0, #020617 65%);
            border: 1px solid rgba(148,163,184,.35);
            padding: .7rem .9rem;
            font-size: .8rem;
        }

        .perf-label {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #9ca3af;
        }

        .perf-val {
            font-size: .9rem;
        }

        .perf-var-pos {
            color: #4ade80;
        }

        .perf-var-neg {
            color: #f97373;
        }

        .hc-container {
            min-height: 260px;
        }
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
                <div class="row g-3 align-items-stretch">
                    {{-- Filters (left) --}}
                    <div class="col-md-4 d-flex flex-wrap align-items-center gap-2">
                        <div>
                            <div class="kpi-shell-label">Year</div>
                            <select id="yearSelect" class="form-select form-select-sm">
                                @for($y = now()->year; $y >= now()->year - 5; $y--)
                                    <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <button id="btnApplyArea" class="btn btn-sm btn-primary mt-3 mt-md-4">
                            Update
                        </button>
                    </div>

                    {{-- KPI cards (right, using global .kpi-card styles) --}}
                    <div class="col-md-8">
                        <div class="row g-2 justify-content-md-end align-items-stretch">
                            {{-- Inquiries total --}}
                            <div class="col-12 col-sm-4 col-lg-4">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">Inquiries Total</div>
                                    <div id="inqBadge" class="kpi-value">SAR 0</div>
                                </div>
                            </div>

                            {{-- POs total --}}
                            <div class="col-12 col-sm-4 col-lg-4">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">POs Total</div>
                                    <div id="poBadge" class="kpi-value">SAR 0</div>
                                </div>
                            </div>


                            {{-- Gap coverage + gauge --}}
                            <div class="col-12 col-sm-4 col-lg-4">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column">

                                    <div class="kpi-label mb-2">Gap Coverage</div>

                                    <!-- FIXED: proper reserved height so gauge does NOT shrink -->
                                    <div class="gauge-wrap flex-grow-0 mx-auto" style="height:120px; width:140px;">
                                        <div id="gapGauge" style="height:100%; width:100%;"></div>
                                    </div>

                                    <div class="mt-2">
                                        <div id="gapBadge" class="fw-semibold" style="color:#7fffa0;">
                                            Gap: SAR 0
                                        </div>
                                        <div id="gapTrend" class="kpi-foot small text-uppercase mt-1"></div>
                                    </div>
                                </div>
                            </div>


                        </div> {{-- inner row --}}
                    </div> {{-- col-md-8 --}}
                </div> {{-- outer row --}}
            </div>
        </div>


        {{-- =======================
             CHARTS ROW
           ======================= --}}
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card card-dark h-100">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Quotations vs POs (Total)</h6>
                    </div>
                    <div class="card-body">
                        <div id="poVsQuote" class="hc-container"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-dark h-100">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Quotations vs POs by Area</h6>
                    </div>
                    <div class="card-body">
                        <div id="poVsQuoteArea" class="hc-container"></div>
                    </div>
                </div>
            </div>
        </div>

{{--        --}}{{-- =======================--}}
{{--             MONTH / YTD KPIS--}}
{{--           ======================= --}}
{{--        <div class="card card-dark mt-3 mb-4">--}}
{{--            <div class="card-header d-flex gap-2 align-items-center">--}}
{{--                <strong>Performance (Month &amp; YTD)</strong>--}}
{{--                <select id="sel-month" class="form-select form-select-sm ms-2" style="width:120px">--}}
{{--                    @for($m=1;$m<=12;$m++)--}}
{{--                        <option value="{{$m}}" {{ $m==date('n')?'selected':'' }}>--}}
{{--                            {{ DateTime::createFromFormat('!m',$m)->format('M') }}--}}
{{--                        </option>--}}
{{--                    @endfor--}}
{{--                </select>--}}
{{--                <span class="ms-auto small text-muted">values in SAR</span>--}}
{{--            </div>--}}
{{--            <div class="card-body" id="area-kpis">--}}
{{--                --}}{{-- JS will inject the KPI mini-cards here --}}
{{--            </div>--}}
{{--        </div>--}}

        {{-- =======================
             INQUIRIES TABLE
           ======================= --}}
        <div class="card card-dark mb-4">
            <div class="card-header">
                <strong>Inquiries (Estimations)</strong>
                <span class="text-secondary small"> — sums by area</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm w-100" id="tblAreaInquiries">
                        <thead>
                        <tr>
                            <th>Area</th>
                            <th class="num">Jan</th>
                            <th class="num">Feb</th>
                            <th class="num">Mar</th>
                            <th class="num">Apr</th>
                            <th class="num">May</th>
                            <th class="num">Jun</th>
                            <th class="num">Jul</th>
                            <th class="num">Aug</th>
                            <th class="num">Sep</th>
                            <th class="num">Oct</th>
                            <th class="num">Nov</th>
                            <th class="num">Dec</th>
                            <th class="num">Total</th>
                        </tr>
                        <tr class="filters">
                            <th><input class="form-control form-control-sm" placeholder="Area"></th>
                            @for($i=0;$i<13;$i++)
                                <th></th>
                            @endfor
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        {{-- =======================
             POS TABLE
           ======================= --}}
        <div class="card card-dark">
            <div class="card-header">
                <strong>POs (Sales Orders Received)</strong>
                <span class="text-secondary small"> — sums by area</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm w-100" id="tblAreaPOs">
                        <thead>
                        <tr>
                            <th>Area</th>
                            <th class="num">Jan</th>
                            <th class="num">Feb</th>
                            <th class="num">Mar</th>
                            <th class="num">Apr</th>
                            <th class="num">May</th>
                            <th class="num">Jun</th>
                            <th class="num">Jul</th>
                            <th class="num">Aug</th>
                            <th class="num">Sep</th>
                            <th class="num">Oct</th>
                            <th class="num">Nov</th>
                            <th class="num">Dec</th>
                            <th class="num">Total</th>
                        </tr>
                        <tr class="filters">
                            <th><input class="form-control form-control-sm" placeholder="Area"></th>
                            @for($i=0;$i<13;$i++)
                                <th></th>
                            @endfor
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

    </main>
@endsection

@push('scripts')
    <script>
        const fmtSAR = (n) => new Intl.NumberFormat('en-SA', {
            style: 'currency', currency: 'SAR', maximumFractionDigits: 0
        }).format(n || 0);

        const DATA_URL = @json(route('performance.area.data'));
        let gapGaugeChart = null;
        let year = @json($year);
        let lastInqJson = null;
        let lastPoJson  = null;

        const columns = [
            {data: 'area', name: 'area'},
            {data: 'jan', name: 'jan', className: 'num'},
            {data: 'feb', name: 'feb', className: 'num'},
            {data: 'mar', name: 'mar', className: 'num'},
            {data: 'apr', name: 'apr', className: 'num'},
            {data: 'may', name: 'may', className: 'num'},
            {data: 'jun', name: 'jun', className: 'num'},
            {data: 'jul', name: 'jul', className: 'num'},
            {data: 'aug', name: 'aug', className: 'num'},
            {data: 'sep', name: 'sep', className: 'num'},
            {data: 'oct', name: 'oct', className: 'num'},
            {data: 'nov', name: 'nov', className: 'num'},
            {data: 'december', name: 'december', className: 'num'}, // not "dec"
            {data: 'total', name: 'total', className: 'num'}
        ];

        function moneyRender(data, type) {
            if (type === 'display' || type === 'filter') return fmtSAR(Number(data || 0));
            return data;
        }
        for (let i = 1; i < columns.length; i++) columns[i].render = moneyRender;

        function initTable(selector, kind, badgeSel) {
            const $tbl       = $(selector);
            const $filterRow = $tbl.find('thead tr.filters');

            const dt = $tbl.DataTable({
                processing: true,
                serverSide: true,
                order: [[0, 'asc']],
                ajax: {
                    url: DATA_URL,
                    data: d => {
                        d.kind = kind;
                        d.year = year;
                    }
                },
                columns,
                drawCallback: function () {
                    const json = this.api().ajax.json() || {};

                    if (kind === 'inquiries') {
                        lastInqJson = json;
                    } else {
                        lastPoJson = json;
                    }

                    // Set KPI card value (formatted SAR)
                    if (badgeSel) {
                        $(badgeSel).text(fmtSAR(json.sum_total || 0));
                    }

                    updateGapBadge();
                    refreshCharts();
                }
            });

            // per-column filter on Area
            $filterRow.find('th input').on('keyup change', function () {
                dt.column(0).search(this.value).draw();
            });

            return dt;
        }
        function renderGapGauge(inqTotal, poTotal) {
            if (typeof Highcharts === 'undefined') return;

            // PO coverage vs quotations (0–100%)
            let coverage = 0;
            if (inqTotal > 0) {
                coverage = Math.round((poTotal / inqTotal) * 100);
                if (coverage > 150) coverage = 150; // cap a bit above 100
            }

            const gaugeOptions = {
                chart: {
                    type: 'solidgauge',
                    backgroundColor: 'transparent',
                },
                title: null,
                pane: {
                    center: ['50%', '85%'],
                    size: '140%',
                    startAngle: -90,
                    endAngle: 90,
                    background: {
                        innerRadius: '60%',
                        outerRadius: '100%',
                        shape: 'arc',
                        borderWidth: 0,
                        backgroundColor: 'rgba(255,255,255,0.05)'
                    }
                },
                tooltip: { enabled: false },
                yAxis: {
                    min: 0,
                    max: 150, // allow a bit above 100%
                    lineWidth: 0,
                    tickWidth: 0,
                    minorTickInterval: null,
                    labels: { enabled: false },
                    // red → yellow → green
                    stops: [
                        [0.6, '#ef4444'],
                        [0.8, '#facc15'],
                        [1.0, '#22c55e']
                    ]
                },
                plotOptions: {
                    solidgauge: {
                        dataLabels: {
                            useHTML: true,
                            borderWidth: 0,
                            y: -10,
                            style: { textOutline: 'none' },
                            format:
                                '<div style="text-align:center;">' +
                                '<span style="font-size:18px;font-weight:800;color:#e5e7eb;">{y:.0f}%</span><br/>' +
                                '<span style="font-size:10px;color:#9ca3af;">POs vs Quotations</span>' +
                                '</div>'
                        }
                    }
                },
                credits: { enabled: false },
                series: [{
                    name: 'Coverage',
                    data: [coverage]
                }]
            };

            if (gapGaugeChart) {
                gapGaugeChart.series[0].setData([coverage], true);
            } else {
                gapGaugeChart = Highcharts.chart('gapGauge', gaugeOptions);
            }
        }


        function updateGapBadge() {
            if (!lastInqJson || !lastPoJson) return;

            const inqTotal = Number(lastInqJson.sum_total || 0);
            const poTotal  = Number(lastPoJson.sum_total || 0);
            const gap      = Math.abs(inqTotal - poTotal);

            $('#gapBadge').text('Gap: ' + fmtSAR(gap));

            const trendText = inqTotal >= poTotal
                ? 'MORE QUOTED THAN POS'
                : 'MORE POS THAN QUOTED';

            $('#gapTrend').text(trendText);

            renderGapGauge(inqTotal, poTotal);
        }

        // ========= DATATABLES INIT =========
        let dtInq = initTable('#tblAreaInquiries', 'inquiries', '#inqBadge');
        let dtPos = initTable('#tblAreaPOs', 'pos', '#poBadge');

        $('#yearSelect').on('change', function () {
            year = this.value;
        });

        $('#btnApplyArea').on('click', function () {
            dtInq.ajax.reload();
            dtPos.ajax.reload();
            loadAreaKpis();
        });

        // ========= HIGHCHARTS GLOBAL THEME =========
        Highcharts.setOptions({
            chart: {
                backgroundColor: 'transparent',
                style: {
                    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
                }
            },
            title: {style: {color: '#f9fafb'}},
            xAxis: {
                labels: {style: {color: '#e5e7eb'}},
                title: {style: {color: '#e5e7eb'}}
            },
            yAxis: {
                labels: {style: {color: '#e5e7eb'}},
                title: {style: {color: '#e5e7eb'}}
            },
            legend: {
                itemStyle: {color: '#e5e7eb'},
                itemHoverStyle: {color: '#ffffff'}
            },
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.95)',
                style: {color: '#f9fafb'}
            },
            lang: {thousandsSep: ','}
        });

        function refreshCharts() {
            if (!lastInqJson || !lastPoJson) return;

            const inqRows = lastInqJson.data || [];
            const poRows  = lastPoJson.data || [];

            // overall totals
            const inqTotal = Number(lastInqJson.sum_total || 0);
            const poTotal  = Number(lastPoJson.sum_total || 0);

            Highcharts.chart('poVsQuote', {
                chart: {type: 'column'},
                title: {text: 'Quotations vs POs (Total)'},
                subtitle: {text: 'Year ' + year},
                xAxis: {categories: ['Year ' + year], crosshair: true},
                yAxis: {
                    min: 0,
                    title: {text: 'Value (SAR)'},
                    labels: {
                        formatter() {
                            return fmtSAR(this.value).replace('SAR', '');
                        }
                    }
                },
                tooltip: {
                    shared: true,
                    useHTML: true,
                    formatter: function () {
                        return this.points.map(p =>
                            `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`
                        ).join('<br>');
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0,
                        dataLabels: {
                            enabled: true,
                            formatter() { return fmtSAR(this.y); }
                        }
                    }
                },
                series: [
                    {name: 'Quotations', data: [inqTotal]},
                    {name: 'POs Received', data: [poTotal]}
                ],
                credits: {enabled: false}
            });

            // by area
            const areasSet = new Set();
            inqRows.forEach(r => areasSet.add(r.area));
            poRows.forEach(r => areasSet.add(r.area));
            const areas = Array.from(areasSet);

            const areaInq = {};
            inqRows.forEach(r => areaInq[r.area] = Number(r.total || 0));
            const areaPos = {};
            poRows.forEach(r => areaPos[r.area] = Number(r.total || 0));

            const inqSeries = areas.map(a => areaInq[a] || 0);
            const poSeries  = areas.map(a => areaPos[a] || 0);

            Highcharts.chart('poVsQuoteArea', {
                chart: {type: 'column'},
                title: {text: 'Quotations vs POs by Area'},
                xAxis: {categories: areas, crosshair: true},
                yAxis: {
                    min: 0,
                    title: {text: 'Value (SAR)'},
                    labels: {
                        formatter() {
                            return fmtSAR(this.value).replace('SAR', '');
                        }
                    }
                },
                tooltip: {
                    shared: true,
                    useHTML: true,
                    formatter: function () {
                        return `<b>${this.x}</b><br>` + this.points.map(p =>
                            `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`
                        ).join('<br>');
                    }
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0,
                        dataLabels: {
                            enabled: true,
                            formatter() { return fmtSAR(this.y); }
                        }
                    }
                },
                series: [
                    {name: 'Quotations', data: inqSeries},
                    {name: 'POs Received', data: poSeries}
                ],
                credits: {enabled: false}
            });
        }

        // ========= MONTH/YTD KPI CARDS =========
        function loadAreaKpis() {
            $.getJSON('{{ route('performance.area.kpis') }}', {
                year: $('#yearSelect').val(),
                month: $('#sel-month').val()
            }, function (r) {
                function fmt(n) {
                    return new Intl.NumberFormat('en-SA', {maximumFractionDigits: 0}).format(n || 0);
                }

                function card(label, v) {
                    if (!v) v = {actual: 0, budget: 0, variance: 0, percent: null};
                    const varCls = (v.variance ?? 0) >= 0 ? 'perf-var-pos' : 'perf-var-neg';
                    const pct    = v.percent === null ? '-' : v.percent + '%';

                    return `
                        <div class="col">
                          <div class="perf-card">
                            <div class="perf-label">${label}</div>
                            <div class="perf-val fw-semibold">${fmt(v.actual)}</div>
                            <div class="text-muted small">Budget: ${fmt(v.budget)}</div>
                            <div class="${varCls} small">Variance: ${fmt(v.variance)}</div>
                            <div class="small">Percent: ${pct}</div>
                          </div>
                        </div>`;
                }

                function rowBlock(title, data) {
                    return `
                      <div class="mb-3">
                        <div class="fw-semibold mb-2">${title}</div>
                        <div class="row row-cols-1 row-cols-md-3 g-2">
                          ${card('Saudi Arabia', data['Saudi Arabia'])}
                          ${card('Export', data['Export'])}
                          ${card('Total', data['total'])}
                        </div>
                      </div>`;
                }

                const html = `
                  <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                      <h6 class="mb-3">Inquiries</h6>
                      ${rowBlock('Month', {
                    'Saudi Arabia': r.inquiries.month['Saudi Arabia'] ?? null,
                    'Export':       r.inquiries.month['Export'] ?? null,
                    'total':        r.inquiries.month_total ?? null
                })}
                      ${rowBlock('Year to date', {
                    'Saudi Arabia': r.inquiries.ytd['Saudi Arabia'] ?? null,
                    'Export':       r.inquiries.ytd['Export'] ?? null,
                    'total':        r.inquiries.ytd_total ?? null
                })}
                    </div>
                    <div class="col-md-6">
                      <h6 class="mb-3">POs</h6>
                      ${rowBlock('Month', {
                    'Saudi Arabia': r.pos.month['Saudi Arabia'] ?? null,
                    'Export':       r.pos.month['Export'] ?? null,
                    'total':        r.pos.month_total ?? null
                })}
                      ${rowBlock('Year to date', {
                    'Saudi Arabia': r.pos.ytd['Saudi Arabia'] ?? null,
                    'Export':       r.pos.ytd['Export'] ?? null,
                    'total':        r.pos.ytd_total ?? null
                })}
                    </div>
                  </div>`;

                $('#area-kpis').html(html);
            });
        }

        $('#sel-month').on('change', loadAreaKpis);

        // initial load
        $(function () {
            loadAreaKpis();
        });
    </script>
@endpush
