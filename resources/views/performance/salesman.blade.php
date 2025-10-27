<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Salesman Summary — Performance</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">
    <style>
        .badge-total { font-weight:600; }
        .table-sticky thead th { position: sticky; top: 0; background: var(--bs-body-bg); z-index: 1; }
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
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">Inquiries</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}" href="{{ route('estimation.index') }}">Estimation</a>
                </li>

                @hasanyrole('gm|admin')
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('salesorders.*') ? 'active' : '' }}" href="{{ route('salesorders.index') }}">Sales Orders</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.area*') ? 'active' : '' }}" href="{{ route('performance.area') }}">Area summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active' : '' }}" href="{{ route('performance.salesman') }}">SalesMan summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('performance.product*') ? 'active' : '' }}" href="{{ route('performance.product') }}">Product summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}" href="{{ route('powerbi.jump') }}">Accounts Summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}" href="{{ route('powerbi.jump') }}">Power BI Dashboard</a></li>
                @endhasanyrole
            </ul>

            <div class="navbar-text me-2">
                Logged in as <strong>{{ $u->name ?? '' }}</strong>
                @if(!empty($u->region)) · <small>{{ $u->region }}</small> @endif
            </div>

            <form method="POST" action="{{ route('logout') }}"> @csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">

    {{-- Filters / badges --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h6 class="mb-0">Sales Man Summary</h6>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 120px;">
                        <span class="input-group-text">Year</span>
                        <select id="yearSelect" class="form-select">
                            @for($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <span class="badge-total text-bg-info" id="badgeInq">Inquiries: SAR 0</span>
                    <span class="badge-total text-bg-primary" id="badgePO">POs: SAR 0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- (FIX) removed an extra stray </div> here --}}

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title mb-2">Salesman comparison (Inquiries vs POs)</h6>
            <div id="chartSalesman" style="height: 360px;"></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">Inquiries (Quotations) — by Salesman</h6>
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

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">POs received — by Salesman</h6>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
    const YEAR_INIT = {{ (int) $year }};
    const DT_URL  = @json(route('performance.salesman.data'));
    const KPI_URL = @json(route('performance.salesman.kpis'));

    const fmtSAR = n => new Intl.NumberFormat('en-SA', {
        style: 'currency', currency: 'SAR', maximumFractionDigits: 0
    }).format(Number(n||0));

    // Common columns (server returns 'december' for December)
    const columns = [
        { data:'salesman', name:'salesman', orderable:false, searchable:false },
        { data:'jan', name:'jan' }, { data:'feb', name:'feb' }, { data:'mar', name:'mar' },
        { data:'apr', name:'apr' }, { data:'may', name:'may' }, { data:'jun', name:'jun' },
        { data:'jul', name:'jul' }, { data:'aug', name:'aug' }, { data:'sep', name:'sep' },
        { data:'oct', name:'oct' }, { data:'nov', name:'nov' }, { data:'december', name:'december' },
        { data:'total', name:'total' }
    ];

    // Currency renderer for numeric cells
    const currencyRender = function(data, type){
        if (type === 'display' || type === 'filter') return fmtSAR(data);
        return data;
    };

    function initTable(selector, kind, badgeSelector){
        return $(selector).DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            order: [[0,'asc']],
            ajax: {
                url: DT_URL,
                data: d => {
                    d.kind = kind;             // 'inq' or 'po'
                    d.year = $('#yearSelect').val();
                }
            },
            columns: columns,
            columnDefs: [
                // format numbers to SAR (skip the 'salesman' column at index 0)
                { targets: [1,2,3,4,5,6,7,8,9,10,11,12,13], render: currencyRender, className: 'text-end' }
            ],
            drawCallback: function(){
                const json = this.api().ajax.json() || {};
                if (badgeSelector && json.sum_total != null) {
                    $(badgeSelector).text((kind === 'inq' ? 'Inquiries: ' : 'POs: ') + fmtSAR(json.sum_total));
                }
            }
        });
    }

    const dtInq = initTable('#tblSalesInquiries','inq', '#badgeInq');
    const dtPO  = initTable('#tblSalesPOs','po',  '#badgePO');

    $('#yearSelect').on('change', function(){
        dtInq.ajax.reload(null, false);
        dtPO.ajax.reload(null, false);
        loadChart();
    });

    async function loadChart(){
        const year = $('#yearSelect').val();
        const res  = await fetch(`${KPI_URL}?year=${year}`, {credentials:'same-origin'});
        const data = await res.json();

        Highcharts.chart('chartSalesman', {
            chart: { type: 'column' },
            title: { text: `Salesman comparison — ${year}` },
            xAxis: { categories: data.categories, crosshair: true },
            yAxis: { min: 0, title: { text: 'SAR' } },
            tooltip: {
                shared: true,
                formatter() {
                    const pts = this.points.map(p => `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`).join('<br/>');
                    return `<b>${this.x}</b><br/>${pts}`;
                }
            },
            plotOptions: { column: { pointPadding: 0.08, borderWidth: 0 } },
            series: [
                { name: 'Inquiries', data: data.inquiries },
                { name: 'POs',       data: data.pos }
            ]
        });

        $('#badgeInq').text('Inquiries: ' + fmtSAR(data.sum_inquiries));
        $('#badgePO').text('POs: ' + fmtSAR(data.sum_pos));
    }

    // initial render
    loadChart();
</script>
</body>
</html>
