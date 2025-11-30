{{-- resources/views/salesorderlog/index.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>ATAI Sales Orders — Live</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">
    <style>
        table.dataTable thead tr.filters th {
            background: var(--bs-tertiary-bg);
        }

        table.dataTable thead .form-control-sm,
        table.dataTable thead .form-select-sm {
            height: calc(1.5em + .5rem + 2px);
        }

        .badge-total {
            font-weight: 600;
        }

        td.details-control {
            cursor: pointer;
        }

        /* KPI card - match Projects dark background */
        .kpi-card,
        .kpi-card .card-body {
            background-color: #050814;   /* or whatever your main dark bg is */
            color: #f8f9fa;
            border-color: rgba(255, 255, 255, 0.08);
        }

        /* Remove white header look if any */
        .kpi-card .card-header {
            background-color: transparent;
            border-bottom-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        /* Highcharts containers inside KPI card should not be white boxes */
        .kpi-card #chartRegion,
        .kpi-card #chartStatus,
        .kpi-card #chartMonthly {
            background-color: transparent;
        }

        /* Top Clients card – white text on dark */
        .top-clients-card {
            background-color: #050814;
            color: #f8f9fa;
            border-color: rgba(255, 255, 255, 0.08);
        }

        .top-clients-card .card-header {
            background-color: transparent;
            border-bottom-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .top-clients-card .table,
        .top-clients-card .table th,
        .top-clients-card .table td {
            color: #f8f9fa;
        }

        .top-clients-card .table thead th {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .top-clients-card .table tbody tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .top-clients-card .table tbody tr:nth-child(odd) {
            background-color: transparent;
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
                    <a class="nav-link {{ request()->routeIs('inquiriesLog') ? 'active' : '' }}"
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


    <div class="card kpi-card mb-2">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex gap-2">
                    <select id="kpiYear" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        @for($y = date('Y'); $y >= date('Y')-5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    <select id="kpiRegion" class="form-select form-select-sm">
                        <option value="">All Regions</option>
                        <option>Eastern</option>
                        <option>Central</option>
                        <option>Western</option>
                    </select>
                    <button id="kpiApply" class="btn btn-sm btn-primary">Update</button>
                </div>
                <div class="d-flex gap-2">
                    <span id="badgeTotalVAT" class="badge-total text-bg-primary">Total (VAT): SAR 0</span>
                    <span id="badgeTotalPO" class="badge-total text-bg-info">Total PO: SAR 0</span>
                </div>
            </div>

            <div class="row g-2 mt-2">
                <div class="col-md-6">
                    <div id="chartRegion"></div>
                </div>
                <div class="col-md-6">
                    <div id="chartStatus"></div>
                </div>
                <div class="col-12">
                    <div id="chartMonthly"></div>
                </div>
            </div>

            <div class="row g-2 mt-2">
                <div class="col-12 col-lg-6">
                    <div class="card top-clients-card">
                        <div class="card-header py-1"><strong>Top Clients (by VAT)</strong></div>
                        <div class="card-body p-0">
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
                <!-- <div class="col-12 col-lg-6"><div id="chartCurrency"></div></div> -->
            </div>
        </div>
    </div>

{{--    <div class="d-flex align-items-center gap-2 my-2 flex-wrap">--}}
{{--        <span id="sumPo" class="badge-total text-bg-primary ">Total PO: SAR 0</span>--}}
{{--        <span id="sumVat" class="badge-total text-bg-success">Total with VAT: SAR 0</span>--}}
{{--    </div>--}}
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
{{--                            <span id="fcBadgeScope" class="badge-total text-bg-info">All Salesmen</span>--}}
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
{{--                    <div id="fcMonthlyRegionMetrics" class="mt-3" style="height:280px"></div>--}}
{{--                    <div id="fcRegionSummary" class="mt-3" style="height:240px"></div>--}}
{{--                    <div id="fcMonthlyTotals" class="mt-3" style="height:220px"></div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
    <div class="table-responsive">
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

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Territory Mix <small class="text-muted">Accepted POs</small></h5>
                <small class="text-muted">Outside% = orders outside assigned region</small>
            </div>
            <div id="mixRow" class="d-flex flex-wrap gap-3 mt-3"></div>
        </div>
    </div>



    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Territory Mix <small class="text-muted">Inquiries</small></h5>
                <small class="text-muted">Outside% = inquiries outside assigned region</small>
            </div>
            <div id="mixRowInq" class="d-flex flex-wrap gap-3 mt-3"></div>
        </div>
    </div>


</main>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
    $.fn.dataTable.ext.errMode = 'console';
    const fmt = n => Number(n || 0).toLocaleString('en-US', {maximumFractionDigits: 2});

    // small formatter guard (if you already have fmt, this is ignored)
    window.fmt = window.fmt || function (n) {
        return Number(n || 0).toLocaleString('en-SA', {maximumFractionDigits: 2});
    };

    $(function () {
        const $year = $('#kpiYear');
        const $region = $('#kpiRegion');

        // --- DataTable init
        const soTable = $('#tblSales').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/sales-orders/datatable',       // <- your datatableLog route
                type: 'GET',
                data: function (d) {                  // send the same filters as the KPIs
                    d.year = $year.val() || '';
                    d.region = $region.val() || '';
                }
            },
            columns: [
                { data: 'date_rec_d',       name: 'date_rec_d',       orderable: true,  searchable: false }, // prefix search on date works
                { data: 'po_no',            name: 'po_no',            orderable: true,  searchable: false },
                { data: 'client_name',      name: 'client_name',      orderable: true,  searchable: false },
                { data: 'project_name',     name: 'project_name',     orderable: true,  searchable: false },
                { data: 'region_name',      name: 'region_name',      orderable: true,  searchable: false },
                { data: 'project_location', name: 'project_location', orderable: true,  searchable: false },
                { data: 'cur',              name: 'cur',              orderable: true,  searchable: false },
                { data: 'po_value',         name: 'po_value',         orderable: true,  searchable: false, className:'text-end',
                    render: d => fmt(d) },
                { data: 'value_with_vat',   name: 'value_with_vat',   orderable: true,  searchable: false, className:'text-end',
                    render: d => fmt(d) },
                { data: 'status',           name: 'status',           orderable: true,  searchable: false }
            ]
        });

        // --- Update totals badges whenever the table fetch completes
        $('#tblSales').on('xhr.dt', function (_e, _settings, json) {
            updateBadges(json || {});
        });

        function updateBadges(j) {
            const po = fmt(j?.sum_po_value ?? 0);
            const vat = fmt(j?.sum_value_with_vat ?? 0);
            $('#sumPo').text('Total PO: SAR ' + po);
            $('#sumVat').text('Total with VAT: SAR ' + vat);
        }

        // --- When GM/Admin clicks "Update": refresh KPIs AND reload the table
        $('#kpiApply').on('click', function () {
            // your existing KPI refreshers
            if (typeof loadKpis === 'function') loadKpis();
            if (typeof loadForecast === 'function') loadForecast();

            // reload the grid with the new year/region params
            soTable.ajax.reload(null, true);
        });

        // Optional: if you want auto-reload on select change (without pressing Update)
        // $year.add($region).on('change', () => soTable.ajax.reload(null, true));
    });

    // ==== KPI DASHBOARD ====
    Highcharts.setOptions({
        chart: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            style: { color: '#f8f9fa' }
        },
        title: { style: { color: '#ffffff' } },
        xAxis: {
            labels: { style: { color: '#e5e7eb' } },
            title: { style: { color: '#e5e7eb' } }
        },
        yAxis: {
            labels: { style: { color: '#e5e7eb' } },
            title: { style: { color: '#e5e7eb' } }
        },
        legend: {
            itemStyle: { color: '#f8f9fa' },
            itemHoverStyle: { color: '#ffffff' }
        },
        tooltip: {
            backgroundColor: 'rgba(12,78,239,0.95)',
            style: { color: '#ffffff' }
        }
    });
    const hcBase = {
        chart: { height: 220, spacing: [8, 8, 8, 8], backgroundColor: 'transparent' },
        credits: { enabled: false },
        legend: { enabled: false }
    };

    async function loadKpis() {
        const year = document.getElementById('kpiYear').value || '';

        const region = document.getElementById('kpiRegion').value || '';
        const url = new URL("{{ route('salesorders.kpis') }}", window.location.origin);
        if (year) url.searchParams.set('year', year);
        if (region) url.searchParams.set('region', region);

        const res = await fetch(url, {credentials: 'same-origin'});
        if (!res.ok) {
            console.error('kpis', await res.text());
            return;
        }
        const d = await res.json();

        // Totals (chips)
        document.getElementById('badgeTotalVAT').textContent = 'Total (VAT): SAR ' + fmt(Number(d.totals?.value_with_vat || 0));
        document.getElementById('badgeTotalPO').textContent = 'Total PO: SAR ' + fmt(Number(d.totals?.po_value || 0));

        // -----------------------------
        // Region (VAT) — Stacked by Status
        // falls back to the simple total if new payload missing
        // -----------------------------
        (function () {
            const el = document.getElementById('chartRegion');
            if (!el) return;

            const payload = d.by_region_status || null;
            if (payload && payload.categories && payload.series) {
                Highcharts.chart('chartRegion', Highcharts.merge(hcBase, {
                    legend: {enabled: true},
                    title: {text: 'By Region (VAT)'},
                    xAxis: {categories: payload.categories},
                    yAxis: {
                        min: 0, title: {text: 'SAR'}, stackLabels: {
                            enabled: true, formatter() {
                                return Highcharts.numberFormat(this.total, 0);
                            }
                        }
                    },
                    plotOptions: {
                        column: {
                            stacking: 'normal', dataLabels: {
                                enabled: true, formatter() {
                                    return this.y ? Highcharts.numberFormat(this.y, 0) : '';
                                }
                            }
                        }
                    },
                    tooltip: {
                        shared: true,
                        pointFormatter() {
                            return `<span style="color:${this.color}">●</span> ${this.series.name}: <b>${Highcharts.numberFormat(this.y || 0, 0)}</b><br/>`;
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
                // Fallback: your original single-series region totals
                Highcharts.chart('chartRegion', Highcharts.merge(hcBase, {
                    title: {text: 'By Region (VAT)'},
                    xAxis: {categories: (d.by_region || []).map(x => x.region || '—')},
                    yAxis: {title: {text: 'SAR'}},
                    series: [{type: 'column', data: (d.by_region || []).map(x => Number(x.total || 0))}]
                }));
            }
        })();

        // -----------------------------
        // Status (pie) — unchanged
        // -----------------------------
        Highcharts.chart('chartStatus', Highcharts.merge(hcBase, {
            title: {text: 'By Status (VAT)'},
            legend: {enabled: true},
            series: [{
                type: 'pie',
                data: (d.by_status || []).map(x => ({name: x.status || '—', y: Number(x.total || 0)}))
            }]
        }));

        // -----------------------------
        // Monthly (VAT) — Stacked by Status
        // falls back to simple monthly total if new payload missing
        // -----------------------------
        (function () {
            const el = document.getElementById('chartMonthly');
            if (!el) return;

            const payload = d.monthly_status || null; // { categories: ['2025-01',...], series: [{name:'Accepted', data:[...], stack:'VAT'}] }
            if (payload && payload.categories && payload.series) {
                Highcharts.chart('chartMonthly', Highcharts.merge(hcBase, {
                    legend: {enabled: true},
                    title: {text: 'Monthly (VAT)'},
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
                        min: 0, title: {text: 'SAR'}, stackLabels: {
                            enabled: true, formatter() {
                                return Highcharts.numberFormat(this.total, 0);
                            }
                        }
                    },
                    plotOptions: {
                        column: {
                            stacking: 'normal', dataLabels: {
                                enabled: true, formatter() {
                                    return this.y ? Highcharts.numberFormat(this.y, 0) : '';
                                }
                            }
                        }
                    },
                    tooltip: {
                        shared: true,
                        pointFormatter() {
                            return `<span style="color:${this.color}">●</span> ${this.series.name}: <b>${Highcharts.numberFormat(this.y || 0, 0)}</b><br/>`;
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
                // Fallback: your original single-series monthly totals
                Highcharts.chart('chartMonthly', Highcharts.merge(hcBase, {
                    title: {text: 'Monthly (VAT)'},
                    xAxis: {categories: (d.monthly || []).map(m => m.ym)},
                    yAxis: {title: {text: 'SAR'}},
                    series: [{type: 'column', data: (d.monthly || []).map(m => Number(m.total || 0))}]
                }));
            }
        })();

        // -----------------------------
        // (Optional) Monthly × Region × Status — Grouped + Stacked
        // Requires a container with id="chartMonthlyRegionStatus"
        // -----------------------------
        (function () {
            const el = document.getElementById('chartMonthlyRegionStatus');
            if (!el) return; // skip if you didn't add it

            const payload = d.monthly_region_status || null; // { categories: months, series: [{name:'Eastern – Accepted', stack:'Eastern', data:[...]}] }
            if (!payload) {
                el.innerHTML = '<div class="text-muted p-3">No monthly-region-status data.</div>';
                return;
            }

            Highcharts.chart('chartMonthlyRegionStatus', Highcharts.merge(hcBase, {
                chart: {height: 260},
                legend: {enabled: true},
                title: {text: 'Monthly × Region × Status (VAT)'},
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
                    min: 0, title: {text: 'SAR'}, stackLabels: {
                        enabled: true, formatter() {
                            return Highcharts.numberFormat(this.total, 0);
                        }
                    }
                },
                plotOptions: {
                    column: {
                        stacking: 'normal',
                        groupPadding: 0.12,
                        pointPadding: 0.04,
                        dataLabels: {
                            enabled: true, formatter() {
                                return this.y ? Highcharts.numberFormat(this.y, 0) : '';
                            }
                        }
                    }
                },
                tooltip: {
                    shared: true, useHTML: true,
                    formatter() {
                        const byRegion = {};
                        (this.points || []).forEach(p => {
                            // series.name looks like "Eastern – Accepted"
                            const [regionName, statusName] = String(p.series.name).split(' – ');
                            if (!byRegion[regionName]) byRegion[regionName] = [];
                            byRegion[regionName].push(`${statusName}: <b>${Highcharts.numberFormat(p.y || 0, 0)}</b>`);
                        });
                        let s = `<b>${this.x}</b><br/>`;
                        Object.keys(byRegion).forEach(rg => {
                            s += `<span style="font-weight:600">${rg}</span><br/>${byRegion[rg].join(' • ')}<br/>`;
                        });
                        return s;
                    }
                },
                series: (payload.series || []).map(s => ({type: 'column', name: s.name, data: s.data, stack: s.stack}))
            }));
        })();

        // Top clients table  <-- uses client + orders + total
        const body = document.getElementById('topClientsBody');
        if (body) {
            body.innerHTML = '';
            (d.top_clients || []).forEach(c => {
                body.insertAdjacentHTML('beforeend',
                    `<tr>
           <td>${c.client || '—'}</td>
           <td class="text-end">${Number(c.orders || 0)}</td>
           <td class="text-end">${fmt(Number(c.total || 0))}</td>
         </tr>`
                );
            });
        }

        // Currency (pie) if you enable it:
        // Highcharts.chart('chartCurrency', Highcharts.merge(hcBase, {
        //   title:{ text:'By Currency (VAT)' },
        //   legend:{ enabled:true },
        //   series:[{ type:'pie', data:(d.by_currency||[]).map(x=>({name:x.currency || '—', y:Number(x.total || 0)})) }]
        // }));
    }

    document.getElementById('kpiApply')?.addEventListener('click', async () => {
        await loadKpis();        // your KPI function
        await loadForecast();    // your forecast KPI function (if on this page)
        if ($.fn.dataTable.isDataTable('#tblSalesOrders')) {
            $('#tblSalesOrders').DataTable().ajax.reload(null, false); // no paging reset
        }
    });
    loadKpis(); // initial
    /* =============================================================================
     *  FORECAST CHARTS
     * ============================================================================= */
    const fmtSAR = n => 'SAR ' + fmt(n);

    async function loadForecast() {
        // show the row container
        const row = document.getElementById('forecastRow');
        if (row) row.style.display = '';

        // Use the existing filters on THIS page
        const year = document.getElementById('kpiYear')?.value || '';
        const region = document.getElementById('kpiRegion')?.value || '';

        // Build request to /forecast/kpis
        const url = new URL("{{ route('forecast.kpis') }}", window.location.origin);
        if (year) url.searchParams.set('year', year);
        if (region) url.searchParams.set('area', region);   // API expects "area"

        let data = {
            area: [],
            salesman: [],
            total_value: 0,
            monthly_region_metrics: {categories: [], series: []},
            region_summary: {categories: [], series: []},
        };

        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (res.ok) {
                data = await res.json();
            } else {
                console.error('forecast.kpis', await res.text());
            }
        } catch (e) {
            console.warn('Forecast fetch failed', e);
        }

        // Badges
        const scopeLabel = region ? region : 'All Regions';
        const scopeEl = document.getElementById('fcBadgeScope');
        if (scopeEl) scopeEl.textContent = scopeLabel;
        const totalEl = document.getElementById('fcBadgeValue');
        if (totalEl) totalEl.textContent = 'Forecast Total: ' + fmtSAR(Number(data.total_value || 0));

        // Highcharts base
        const baseHC = {
            chart: {height: 260, spacing: [8, 8, 8, 8]},
            credits: {enabled: false},
            legend: {enabled: false},
            tooltip: {pointFormat: 'SAR {point.y:,.0f}'},
            plotOptions: {
                column: {
                    dataLabels: {
                        enabled: true,
                        formatter() {
                            return this.y ? ('SAR ' + (this.y ?? 0).toLocaleString()) : '';
                        }
                    }
                }
            }
        };

        // Forecast by Area
        // if (document.getElementById('fcBarByArea')) {
        //     Highcharts.chart('fcBarByArea', Highcharts.merge(baseHC, {
        //         title: {text: 'Forecast by Area (SAR)'},
        //         xAxis: {categories: (data.area || []).map(a => a.area || '—')},
        //         yAxis: {title: {text: 'SAR'}},
        //         series: [{
        //             type: 'column',
        //             name: 'Forecast',
        //             data: (data.area || []).map(a => Number(a.sum_value || 0))
        //         }]
        //     }));
        // }

        // Forecast by Salesman — show if we have data (no GM/Admin flag on this page)
        (function renderBySalesman() {
            const salesWrap = document.getElementById('fcBarBySalesman');
            if (!salesWrap) return;
            const s = Array.isArray(data.salesman) ? data.salesman : [];
            if (s.length) {
                Highcharts.chart('fcBarBySalesman', Highcharts.merge(baseHC, {
                    title: {text: 'Forecast by Salesman (SAR)' + (region ? ` — ${region}` : '')},
                    xAxis: {categories: s.map(x => x.salesman || '—'), labels: {rotation: -30}},
                    yAxis: {title: {text: 'SAR'}},
                    series: [{type: 'column', name: 'Forecast', data: s.map(x => Number(x.sum_value || 0))}]
                }));
            } else {
                salesWrap.innerHTML = '<div class="text-secondary small">No salesman data.</div>';
            }
        })();

        // ============================
        // NEW: Monthly × Region × Metric
        // ============================
        // (function renderMonthlyRegionMetrics() {
        //     const elId = 'fcMonthlyRegionMetrics';
        //     const el = document.getElementById(elId);
        //     if (!el) return;
        //
        //     const payload = (data.monthly_region_metrics || {categories: [], series: []});
        //     const hasData = (payload.series || []).some(s => (s.data || []).some(v => Number(v) > 0));
        //
        //     Highcharts.chart(elId, Highcharts.merge(baseHC, {
        //         chart: {type: 'column', height: 280},
        //         legend: {enabled: true},
        //         title: {text: 'Monthly by Region • Forecast vs Inquiries vs Sales'},
        //         xAxis: {
        //             categories: payload.categories || [],
        //             labels: {
        //                 formatter() {
        //                     const [Y, M] = String(this.value || '').split('-');
        //                     if (!Y || !M) return this.value || '';
        //                     const d = new Date(+Y, +M - 1, 1);
        //                     return d.toLocaleString('en', {month: 'short', year: '2-digit'});
        //                 }
        //             }
        //         },
        //         yAxis: {min: 0, title: {text: 'SAR'}, stackLabels: {enabled: true}},
        //         plotOptions: {column: {stacking: 'normal', groupPadding: 0.12, pointPadding: 0.04}},
        //         tooltip: {
        //             shared: true, useHTML: true,
        //             formatter() {
        //                 const byRegion = {};
        //                 (this.points || []).forEach(p => {
        //                     const [rg, metric] = String(p.series.name).split(' – ');
        //                     (byRegion[rg] ||= []).push(`${metric}: <b>${Highcharts.numberFormat(p.y || 0, 0)}</b>`);
        //                 });
        //                 let s = `<b>${this.x}</b><br/>`;
        //                 Object.keys(byRegion).forEach(rg => {
        //                     s += `<span style="font-weight:600">${rg}</span><br/>${byRegion[rg].join(' • ')}<br/>`;
        //                 });
        //                 return s;
        //             }
        //         },
        //         series: (payload.series || []).map(s => ({
        //             type: 'column',
        //             name: s.name,
        //             data: s.data || [],
        //             stack: s.stack || undefined
        //         })),
        //         lang: {noData: 'No monthly-region data.'},
        //         noData: {style: {fontSize: '12px', color: '#6c757d'}}
        //     }));
        //
        //     if (!hasData && Highcharts.Chart.prototype.showNoData) {
        //         const ch = Highcharts.charts.find(c => c && c.renderTo && c.renderTo.id === elId);
        //         ch?.showNoData();
        //     }
        // })();


        // ============================
        // NEW: Region Summary (totals)
        // ============================
        // (function renderRegionSummary() {
        //     const elId = 'fcRegionSummary';
        //     const el = document.getElementById(elId);
        //     if (!el) return;
        //
        //     const rs = data.region_summary || {categories: [], series: []};
        //     Highcharts.chart(elId, Highcharts.merge(baseHC, {
        //         chart: {type: 'column', height: 240},
        //         legend: {enabled: true},
        //         title: {text: 'Region Summary • Forecast vs Inquiries vs Sales'},
        //         xAxis: {categories: rs.categories || []},
        //         yAxis: {title: {text: 'SAR'}},
        //         plotOptions: {column: {grouping: true}},
        //         tooltip: {
        //             shared: true, pointFormatter() {
        //                 return `SAR ${Highcharts.numberFormat(this.y || 0, 0)}`;
        //             }
        //         },
        //         series: (rs.series || []).map(s => ({type: 'column', name: s.name, data: s.data || []})),
        //         lang: {noData: 'No region summary.'},
        //         noData: {style: {fontSize: '12px', color: '#6c757d'}}
        //     }));
        // })();

        // ===== Simple Monthly Totals (3 series) =====
        (function renderMonthlyTotals() {
            const elId = 'fcMonthlyTotals';
            const el = document.getElementById(elId);
            if (!el) return;

            const mf = data.monthly_forecast || {categories: [], series: []};
            const mi = data.monthly_inquiries || {categories: [], series: []};
            const ms = data.monthly_sales || {categories: [], series: []};

            Highcharts.chart(elId, Highcharts.merge(baseHC, {
                chart: {type: 'column', height: 220},
                legend: {enabled: true},
                title: {text: 'Monthly Totals • Forecast vs Inquiries vs Sales'},
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
                yAxis: {title: {text: 'SAR'}},
                series: [
                    {name: 'Forecast', data: (mf.series || []).map(Number)},
                    {name: 'Inquiries', data: (mi.series || []).map(Number)},
                    {name: 'Sales', data: (ms.series || []).map(Number)},
                ],
                lang: {noData: 'No monthly totals.'},
                noData: {style: {fontSize: '12px', color: '#6c757d'}}
            }));
        })();
    }

    // Call it initially and whenever you hit the KPI Update button
    document.getElementById('kpiApply')?.addEventListener('click', () => {
        loadKpis();
        loadForecast();
    });
    // Initial load
    loadForecast();



    async function loadTerritoryMixSales() {
        const year   = document.getElementById('kpiYear')?.value || '';
        const region = document.getElementById('kpiRegion')?.value || '';

        const url = new URL("{{ route('sales-orders.territory-sales') }}", window.location.origin);
        if (year) url.searchParams.set('year', year);
        if (region) url.searchParams.set('region', region);

        let list = [];
        try {
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (res.ok) list = await res.json();
        } catch(e){ console.warn('territory-mix failed', e); }

        const host = document.getElementById('mixRow');
        if (!host) return;
        host.innerHTML = '';

        if (!list.length) {
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
        <div class="mix-main">${r.outside_percent.toFixed(2)}%</div>
        <div class="mix-sub">${r.outside_projects} / ${r.total_projects} outside</div>
      </div>
    `);
        }
    }
    // call on page load and after KPI filters change
    loadTerritoryMixSales();
    document.getElementById('kpiApply')?.addEventListener('click', loadTerritoryMixSales);




    function renderMixCards(list, hostId){
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

    async function loadTerritoryMixInquiries(){
        const year   = document.getElementById('kpiYear')?.value || '';
        const region = document.getElementById('kpiRegion')?.value || '';

        const url = new URL("{{ route('projects.territory-inquiries') }}", window.location.origin);
        if (year) url.searchParams.set('year', year);
        if (region) url.searchParams.set('region', region);

        let data = [];
        try {
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (res.ok) data = await res.json();
        } catch(e){ console.warn('projects territory-mix failed', e); }

        renderMixCards(data, 'mixRowInq');
    }

    // call both when the page loads and when the KPI filters update
    document.addEventListener('DOMContentLoaded', () => {
        loadTerritoryMixSales();          // your existing POs function
        loadTerritoryMixInquiries(); // new Inquiries function
    });
    document.getElementById('kpiApply')?.addEventListener('click', () => {
        loadTerritoryMixSales();
        loadTerritoryMixInquiries();
    });
















</script>
</body>
</html>
