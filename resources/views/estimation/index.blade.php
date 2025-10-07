{{-- resources/views/estimation/index.blade.php --}}
    <!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estimation — ATAI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}">

    <style>
        .kpi-card{min-height:260px}.chart-box{min-height:220px}.est-pill{text-transform:none}
        .badge-total{font-size:1rem;font-weight:600;background:#e8f5e9;color:#1b5e20;padding:.4rem .8rem;border-radius:12px}
        @media (max-width:768px){
            .kpi-card{min-height:auto}.chart-box{min-height:180px}
            #estimatorPills{flex-wrap:wrap;gap:.5rem}
            #estimatorPills .nav-link{font-size:.85rem;padding:.25rem .5rem}
            table.dataTable td{font-size:.85rem;white-space:nowrap}
        }
        .estimator-toolbar.glass-row{
            display:flex;justify-content:space-between;align-items:center;
            gap:1rem;background:rgba(255,255,255,.6);backdrop-filter:saturate(180%) blur(8px);
            border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:.75rem 1rem
        }
        .et-left{display:flex;align-items:center;gap:.75rem}
        .et-right{display:flex;align-items:end;gap:.75rem;flex-wrap:wrap}
        .et-field{min-width:9rem}
    </style>
</head>
<body class="bg-body-tertiary">

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
                <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                   href="{{ route('projects.index') }}">Quotation KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                       href="{{ route('inquiries.index') }}">Quotation Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Sales Order Log KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Sales Order Log</a>
                </li>

                {{-- Sales roles only --}}
                {{--                @hasanyrole('sales|sales_eastern|sales_central|sales_western')--}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">Estimation</a>
                </li>

                @hasanyrole('gm|admin')
                <li class="nav-item"><a class="nav-link" href="{{ route('salesorders.index') }}">Sales Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('performance.area') }}">Area Summary</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('performance.salesman') }}">Salesman Summary</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('performance.product') }}">Product Summary</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}" href="{{ route('powerbi.jump') }}">Accounts Summary</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('powerbi.jump') }}">Power BI Dashboard</a></li>
                @endhasanyrole
            </ul>

            <div class="navbar-text me-2">
                Logged in as <strong>{{ $u->name ?? '' }}</strong>
                @if(!empty($u->region)) · <small>{{ $u->region }}</small>@endif
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">

    {{-- Estimators + Filters --}}
    <div class="estimator-toolbar glass-row mb-3">
        <div class="et-left">
            <h4 class="mb-0 me-2">Estimators</h4>
            <ul id="estimatorPills" class="nav nav-pills pill-chips"></ul>
        </div>

        <form class="et-right" id="estimatorFilters" onsubmit="return false;">
            <div class="et-field">
                <label class="form-label mb-1 small">Year</label>
                <select class="form-select form-select-sm" id="filterYear">
                    <option value="">All</option>
                    @for ($y = date('Y'); $y >= date('Y')-6; $y--) <option value="{{ $y }}">{{ $y }}</option> @endfor
                </select>
            </div>
            <div class="et-field">
                <label class="form-label mb-1 small">Month</label>
                <select class="form-select form-select-sm" id="filterMonth">
                    <option value="">All</option>
                    @for ($m=1; $m<=12; $m++) <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option> @endfor
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
                        <span class="kpi-title" id="kpi-title">By Estimator (share of value)</span>
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
                    <div id="chartProduct" class="chart-box"></div>
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
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-all" type="button">All</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-region" type="button">By Region</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-product" type="button">By Product</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-all">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtAll">
                        <thead><tr>
                            <th>#</th><th>Project</th><th>Client</th><th>Region</th>
                            <th>Product</th><th>Value</th><th>Status</th><th>Estimator</th><th>Created</th>
                        </tr></thead>
                    </table>
                </div></div>
        </div>

        <div class="tab-pane fade" id="pane-region">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtRegion">
                        <thead><tr><th>Region</th><th>Count</th><th>Total Value</th></tr></thead>
                    </table>
                </div></div>
        </div>

        <div class="tab-pane fade" id="pane-product">
            <div class="card"><div class="card-body">
                    <table class="table table-striped w-100" id="dtProduct">
                        <thead><tr><th>Product</th><th>Count</th><th>Total Value</th></tr></thead>
                    </table>
                </div></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

<script>
    const fmtSAR = n => 'SAR ' + Number(n || 0).toLocaleString(undefined,{maximumFractionDigits:0});
    const fmtCompactSAR = n => {
        const x = Number(n || 0);
        if (x >= 1e9) return 'SAR ' + (x/1e9).toFixed(1) + 'B';
        if (x >= 1e6) return 'SAR ' + (x/1e6).toFixed(1) + 'M';
        if (x >= 1e3) return 'SAR ' + (x/1e3).toFixed(1) + 'k';
        return 'SAR ' + x.toFixed(0);
    };

    (() => {
        let currentEstimator = '';
        const $year = $('#filterYear'), $month = $('#filterMonth'), $from = $('#filterFrom'), $to = $('#filterTo');

        // Build Estimator pills
        fetch('{{ route('estimation.estimators') }}')
            .then(r => r.json())
            .then(list => {
                const ul = document.getElementById('estimatorPills');
                ul.innerHTML = `
          <li class="nav-item"><button class="nav-link est-pill active" data-estimator="">All</button></li>
          ${list.map(n => `<li class="nav-item"><button class="nav-link est-pill" data-estimator="${n}">${n}</button></li>`).join('')}
        `;
                ul.addEventListener('click', (e) => {
                    const btn = e.target.closest('button[data-estimator]'); if (!btn) return;
                    ul.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentEstimator = btn.getAttribute('data-estimator') || '';
                    reloadAll();
                });
                reloadAll();
            });

        // DataTables
        const money = v => 'SAR ' + Number(v || 0).toLocaleString();
        const dtAll = $('#dtAll').DataTable({
            processing:true, serverSide:true, lengthChange:true, order:[[0,'desc']],
            ajax:{url:'{{ route('estimation.datatable.all') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[
                {data:'id', width:60},{data:'project_name'},{data:'client_name'},{data:'area'},
                {data:'atai_products'},{data:'quotation_value', className:'text-end', render:money},
                {data:'status'},{data:'estimator'},{data:'created_at'}
            ]
        });
        const dtRegion = $('#dtRegion').DataTable({
            processing:true, serverSide:true, order:[[1,'desc']],
            ajax:{url:'{{ route('estimation.datatable.region') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[{data:'region'},{data:'cnt', className:'text-end'},{data:'val', className:'text-end', render:money}]
        });
        const dtProduct = $('#dtProduct').DataTable({
            processing:true, serverSide:true, order:[[1,'desc']],
            ajax:{url:'{{ route('estimation.datatable.product') }}', data:d=>Object.assign(d, buildFilters())},
            columns:[{data:'product'},{data:'cnt', className:'text-end'},{data:'val', className:'text-end', render:money}]
        });

        // KPIs
        function loadKpis(){
            const qs = new URLSearchParams(buildFilters()).toString();
            fetch(`{{ route('estimation.kpis') }}?${qs}`)
                .then(r=>r.json())
                .then(payload=>{
                    document.getElementById('kpi-total-value').textContent = fmtSAR(payload?.totals?.value || 0);

                    const titleEl = document.getElementById('kpi-title');
                    if (payload.mode === 'all') {
                        if (titleEl) titleEl.textContent = 'By Estimator (share of value)';
                        Highcharts.chart('chart-estimator',{
                            chart:{type:'pie',backgroundColor:'transparent'}, title:{text:null},
                            tooltip:{pointFormatter:function(){return `<span style="color:${this.color}">●</span> ${this.name}: <b>${Highcharts.numberFormat(this.percentage,1)}%</b><br/>Value: <b>${fmtSAR(this.y)}</b>`;}},
                            plotOptions:{pie:{dataLabels:{enabled:true,format:'{point.name}: {point.percentage:.1f}%'}}},
                            series:[{name:'Share', colorByPoint:true, data:payload.estimatorPie || []}],
                            credits:{enabled:false}
                        });
                    } else {
                        if (titleEl) titleEl.textContent = `${currentEstimator || 'Estimator'} — By Status`;
                        Highcharts.chart('chart-estimator',{
                            chart:{type:'pie',backgroundColor:'transparent'}, title:{text:null},
                            tooltip:{pointFormatter:function(){return `<span style="color:${this.color}">●</span> ${this.name}: <b>${Highcharts.numberFormat(this.y,0)}</b><br/>Value: <b>${fmtSAR(this.options.value || 0)}</b>`;}},
                            plotOptions:{pie:{dataLabels:{enabled:true,format:'{point.name}: {point.y}'}}},
                            series:[{name:'Projects', colorByPoint:true, data:payload.statusPie || []}],
                            credits:{enabled:false}
                        });
                    }

                    // Monthly Region — stacked columns + Total + MoM%
                    const cats = payload.monthlyRegion?.categories || [];
                    const regionCols = (payload.monthlyRegion?.series || []).map(s=>({
                        name:s.name, type:'column', stack:'Value',
                        data:(s.data||[]).map(v=>Number(v||0)),
                        dataLabels:{enabled:true, formatter:function(){return this.y>=5_000_000?fmtCompactSAR(this.y):null;}, style:{textOutline:'none', fontWeight:600}}
                    }));
                    const totals = cats.map((_,i)=>regionCols.reduce((sum,s)=>sum+(s.data[i]||0),0));
                    const momPct = totals.map((v,i)=> i===0?0: ((totals[i-1]||0)>0 ? Number(((v-totals[i-1])*100/totals[i-1]).toFixed(1)) : 0));

                    Highcharts.chart('chartMonthlyRegion', {
                        chart: { type: 'column', backgroundColor: 'transparent' },
                        title: { text: null },
                        xAxis: { categories: payload.monthlyRegion?.categories || [] },
                        yAxis: [{
                            title: { text: 'Value (SAR)' },
                            labels: { formatter(){ return fmtSAR(this.value); } }
                        }, {
                            title: { text: null },
                            opposite: true
                        }],
                        legend: { itemStyle: { fontWeight: 600 } },
                        tooltip: {
                            shared: true,
                            formatter: function () {
                                const header = `<b>${this.x}</b><br/>`;
                                const lines = this.points.map(p =>
                                    `<span style="color:${p.color}">●</span> ${p.series.name}: <b>${fmtSAR(p.y)}</b>`
                                );
                                return header + lines.join('<br/>');
                            }
                        },
                        plotOptions: {
                            column: {
                                pointPadding: 0.08,      // space inside each column group
                                groupPadding: 0.14,      // space between groups (months)
                                borderWidth: 0,
                                dataLabels: {
                                    enabled: true,
                                    crop: false,
                                    overflow: 'none',
                                    rotation: -90,            // ✅ rotate text vertically
                                    align: 'center',          // ✅ center-align on the bar
                                    verticalAlign: 'bottom',  // ✅ position above bar
                                    inside: false,            // ✅ keep it above, not inside
                                    y: -6,                    // ✅ small offset upward
                                    formatter: function () {
                                        // only show labels for reasonably big values to avoid clutter
                                        return this.y >= 2_000_000 ? fmtCompactSAR(this.y) : null;
                                    },
                                    style: {
                                        textOutline: 'none',
                                        fontWeight: 600,
                                        color: '#000',          // optional for readability
                                        fontSize: '10px'
                                    }
                                }
                            }
                        },
                        series: payload.monthlyRegion?.series || [],
                        credits: { enabled: false }
                    });

                    // By Product (Value)
                    Highcharts.chart('chartProduct',{
                        chart:{type:'column', backgroundColor:'transparent'}, title:{text:null},
                        xAxis:{categories:payload.productSeries?.categories || []},
                        yAxis:{title:{text:'Value (SAR)'}, labels:{formatter:function(){return fmtSAR(this.value);}}},
                        tooltip:{pointFormatter:function(){return `<b>${fmtSAR(this.y)}</b>`;}},
                        plotOptions:{column:{dataLabels:{enabled:true, formatter:function(){return fmtSAR(this.y);}, style:{textOutline:'none', fontWeight:600}}}},
                        series:[{name:'Value', data:payload.productSeries?.values || []}],
                        credits:{enabled:false}
                    });
                });
        }

        function buildFilters(){ return {
            estimator: currentEstimator,
            year: $year.val() || '', month: $month.val() || '',
            from: $from.val() || '', to: $to.val() || ''
        };}

        function reloadAll(){ loadKpis(); dtAll.ajax.reload(null,false); dtRegion.ajax.reload(null,false); dtProduct.ajax.reload(null,false); }

        $('#applyFilters').on('click', reloadAll);
        $('#clearFilters').on('click', ()=>{ $year.val(''); $month.val(''); $from.val(''); $to.val(''); reloadAll(); });
    })();
</script>
</body>
</html>
