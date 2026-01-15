@extends('layouts.app')

@section('title', 'ATAI Sales Orders — Live')

@push('head')
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Salesman Summary — Performance</title>

    {{-- Only custom styles here – Bootstrap + DataTables come from layouts/app + atai-theme --}}
    <style>
        .badge-total { font-weight: 600; }

        .table-sticky thead th {
            position: sticky;
            top: 0;
            z-index: 1;
        }

        /* Section headings above each table */
        .sales-section-title {
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .15em;
            font-weight: 700;
            font-size: .8rem;
            margin-bottom: .85rem;
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
                <div class="row g-3 align-items-center">

                    {{-- Filters (left) --}}
                    <div class="col-md-4 d-flex flex-wrap align-items-center gap-2">
                        <div>
                            <div class="kpi-shell-label">Salesman Summary</div>
                            <div class="small text-muted">Year-wise comparison</div>
                        </div>

                        <div class="ms-md-3">
                            <div class="kpi-shell-label">Year</div>
                            <select id="yearSelect" class="form-select form-select-sm">
                                @for($y = now()->year; $y >= now()->year - 5; $y--)
                                    <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="mt-2">
                            <a id="btnDownloadPdf"
                               class="btn btn-sm btn-primary"
                               href="{{ route('performance.salesman.pdf') }}?year={{ $year }}">
                                <i class="bi bi-download me-1"></i> Download PDF
                            </a>
                        </div>
                    </div>

                    {{-- KPI cards (right, using global .kpi-card styles) --}}
                    <div class="col-md-8">
                        <div class="row g-2 justify-content-md-end">
                            {{-- Inquiries total --}}
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">Inquiries Total</div>
                                    <div id="badgeInq" class="kpi-value">
                                        SAR 0
                                    </div>
                                </div>
                            </div>

                            {{-- POs total --}}
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="kpi-card shadow-sm p-3 text-center h-100 d-flex flex-column justify-content-center">
                                    <div class="kpi-label">POs Total</div>
                                    <div id="badgePO" class="kpi-value">
                                        SAR 0
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-dark text-light coordinator-kpi-card">
                                    <div class="card-body text-center">
                                        <div class="text-uppercase small text-secondary mb-1">
                                            Gap Coverage
                                        </div>

                                        {{-- Gauge container --}}
                                        <div id="salesman_gap_gauge" style="height: 140px;"></div>

                                        {{-- Gap text --}}
                                        <div class="mt-2 small">
                                            <div id="salesman_gap_text" class="fw-semibold">
                                                Gap: SAR 0
                                            </div>
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
             INQUIRIES TABLE
           ======================= --}}
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title sales-section-title">Inquiries (Quotations) — by Salesman</h6>
                <div class="table-responsive">
                    <table id="tblSalesInquiries" class="table table-striped table-sticky w-100">
                        <thead>
                        <tr>
                            <th>Salesman</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                            <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        {{-- =======================
             POs TABLE
           ======================= --}}
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title sales-section-title">POs received — by Salesman</h6>
                <div class="table-responsive">
                    <table id="tblSalesPOs" class="table table-striped table-sticky w-100">
                        <thead>
                        <tr>
                            <th>Salesman</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                            <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                            <th>Total</th>
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
        const YEAR_INIT = {{ (int) $year }};
        const DT_URL  = @json(route('performance.salesman.data'));
        const KPI_URL = @json(route('performance.salesman.kpis'));

        const fmtSAR = n => new Intl.NumberFormat('en-SA', {
            style: 'currency',
            currency: 'SAR',
            maximumFractionDigits: 0
        }).format(Number(n || 0));

        // Common columns – server returns "december" for Dec
        const columns = [
            { data:'salesman', name:'salesman', orderable:false, searchable:false },
            { data:'jan', name:'jan' }, { data:'feb', name:'feb' }, { data:'mar', name:'mar' },
            { data:'apr', name:'apr' }, { data:'may', name:'may' }, { data:'jun', name:'jun' },
            { data:'jul', name:'jul' }, { data:'aug', name:'aug' }, { data:'sep', name:'sep' },
            { data:'oct', name:'oct' }, { data:'nov', name:'nov' }, { data:'december', name:'december' },
            { data:'total', name:'total' }
        ];

        const currencyRender = function (data, type) {
            if (type === 'display' || type === 'filter') return fmtSAR(data);
            return data;
        };

        document.getElementById('btnDownloadPdf')?.addEventListener('click', function (e) {
            const y = document.getElementById('yearSelect')?.value || YEAR_INIT;
            this.href = `{{ route('performance.salesman.pdf') }}?year=${encodeURIComponent(y)}`;
        });
        function initTable(selector, kind, badgeSelector) {
            return $(selector).DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                order: [[0, 'asc']],
                ajax: {
                    url: DT_URL,
                    data: d => {
                        d.kind = kind;                  // 'inq' or 'po'
                        d.year = $('#yearSelect').val();
                    }
                },
                columns: columns,
                columnDefs: [
                    {
                        targets: [1,2,3,4,5,6,7,8,9,10,11,12,13],
                        render: currencyRender,
                        className: 'text-end'
                    }
                ],
                drawCallback: function () {
                    const json = this.api().ajax.json() || {};
                    if (badgeSelector && json.sum_total != null) {
                        const label = (kind === 'inq' ? 'Inquiries: ' : 'POs: ');
                        $(badgeSelector).text(label + fmtSAR(json.sum_total));
                    }
                }
            });
        }

        const dtInq = initTable('#tblSalesInquiries', 'inq', '#badgeInq');
        const dtPO  = initTable('#tblSalesPOs',       'po',  '#badgePO');

        $('#yearSelect').on('change', function () {
            dtInq.ajax.reload(null, false);
            dtPO.ajax.reload(null, false);
            loadChart();
        });

        async function loadChart() {
            const year = $('#yearSelect').val();
            const res  = await fetch(`${KPI_URL}?year=${year}`, { credentials: 'same-origin' });
            const data = await res.json();

            Highcharts.chart('chartSalesman', {
                chart: {
                    type: 'column',
                    backgroundColor: 'transparent'  // fully match dark theme
                },
                title: {
                    text: `Salesman Comparison — ${year}`,
                    style: {
                        color: '#ffffff',        // white title
                        fontSize: '16px',
                        fontWeight: '600'
                    }
                },
                xAxis: {
                    categories: data.categories,
                    labels: {
                        style: {
                            color: '#d0d0d0',    // white/light text
                            fontSize: '12px',
                            fontWeight: '500'
                        }
                    },
                    lineColor: '#444',
                    tickColor: '#444'
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'SAR',
                        style: {
                            color: '#ffffff',    // white axis title
                            fontWeight: '600'
                        }
                    },
                    labels: {
                        style: {
                            color: '#d0d0d0',    // white/light labels
                            fontSize: '11px'
                        }
                    },
                    gridLineColor: '#333'        // subtle dark lines
                },
                legend: {
                    itemStyle: {
                        color: '#ffffff',        // white legend text
                        fontWeight: '500'
                    }
                },
                tooltip: {
                    shared: true,
                    backgroundColor: '#1a1a1a',  // dark tooltip
                    borderColor: '#333',
                    style: { color: '#fff' },
                    formatter() {
                        const pts = this.points.map(p =>
                            `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`
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
                            inside: false,
                            allowOverlap: false,
                            style: {
                                color: '#ffffff', // <-- perfect white labels
                                fontSize: '11px',
                                fontWeight: '600',
                                textOutline: 'none'
                            },
                            formatter: function () {
                                return fmtSAR(this.y);
                            }
                        }
                    }
                },
                series: [
                    {
                        name: 'Inquiries',
                        data: data.inquiries,
                        color: '#51a7ff'   // your blue color
                    },
                    {
                        name: 'POs',
                        data: data.pos,
                        color: '#9a69e3'   // your purple color
                    }
                ],
                credits: { enabled: false }
            });


            // Sync top KPI cards with chart sums
            $('#badgeInq').text(fmtSAR(data.sum_inquiries));
            $('#badgePO').text(fmtSAR(data.sum_pos));
        }

        // initial render
        $(function () {
            $('#yearSelect').val(YEAR_INIT);
            loadChart();
        });
    </script>
    <script>
        (function () {
            const fmtSAR = n => new Intl.NumberFormat('en-SA', {
                maximumFractionDigits: 0
            }).format(Number(n || 0));

            // Call your kpis() endpoint
            fetch("{{ route('performance.salesman.kpis') }}?year={{ $year }}", {
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(data => {
                    const totalInq = Number(data.sum_inquiries || 0);
                    const totalPos = Number(data.sum_pos || 0);

                    let coverage = 0;
                    if (totalInq > 0) {
                        coverage = (totalPos / totalInq) * 100;
                    }

                    const gap = totalInq - totalPos;   // +ve → more quoted than POs

                    const gaugeEl = document.getElementById('salesman_gap_gauge');
                    if (!gaugeEl) return;

                    Highcharts.chart('salesman_gap_gauge', {
                        chart: {
                            type: 'solidgauge',
                            backgroundColor: 'transparent'
                        },
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
                                [0.25, '#ef4444'], // red
                                [0.6,  '#facc15'], // amber
                                [1.0,  '#22c55e']  // green
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
                        series: [{
                            name: 'Coverage',
                            data: [coverage]
                        }]
                    });

                    // Text under gauge
                    const gapText = document.getElementById('salesman_gap_text');
                    const gapNote = document.getElementById('salesman_gap_note');

                    const gapAbs = Math.abs(gap);
                    const label = 'Gap: SAR ' + fmtSAR(gapAbs);

                    if (gapText) {
                        gapText.textContent = label;
                        gapText.style.color = gap >= 0 ? '#22c55e' : '#f97316';
                    }

                    if (gapNote) {
                        if (gap > 0) {
                            gapNote.textContent = 'MORE QUOTED THAN POS';
                        } else if (gap < 0) {
                            gapNote.textContent = 'MORE POS THAN QUOTED';
                        } else {
                            gapNote.textContent = 'POs MATCH QUOTATIONS';
                        }
                    }
                })
                .catch(err => {
                    console.error('Salesman KPI gauge error:', err);
                });
        })();
    </script>
@endpush
