<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ATAI — Area Summary</title>

  <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">
  <style>
    table.dataTable thead tr.filters th { background: var(--bs-tertiary-bg); }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
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
                    <a class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Inquiries</a>
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



  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <h6 class="mb-0">Area Summary</h6>

        <div class="ms-auto d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width: 120px;">
            <span class="input-group-text">Year</span>
            <select id="yearSelect" class="form-select">
              @for($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
              @endfor
            </select>
          </div>

          <span class="badge-total text-bg-primary" id="inqBadge">Inquiries Total: SAR 0</span>
          <span class="badge-total text-bg-info"    id="poBadge">POs Total: SAR 0</span>
        </div>
      </div>
    </div>
  </div>




<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-1">Totals ({{ $year }})</h6>
    <div id="poVsQuote" style="height: 280px;"></div>
  </div>
</div>

{{-- Optional: area-wise comparison --}}
<div class="card">
  <div class="card-body">
    <h6 class="mb-1">By Area ({{ $year }}) — Quotations vs POs</h6>
    <div id="poVsQuoteArea" style="height: 280px;"></div>
  </div>
</div>




<div class="card mt-3">
  <div class="card-header d-flex gap-2 align-items-center">
    <strong>Performance (Month & YTD)</strong>
    <select id="sel-month" class="form-select form-select-sm" style="width:120px">
      @for($m=1;$m<=12;$m++) <option value="{{$m}}" {{ $m==date('n')?'selected':'' }}>
        {{ DateTime::createFromFormat('!m',$m)->format('M') }}
      </option>@endfor
    </select>
    <span class="ms-auto small text-muted">values in SAR</span>
  </div>
  <div class="card-body" id="area-kpis">
    {{-- JS will inject the cards here --}}
  </div>
</div>










  {{-- Inquiries table --}}
  <div class="card mb-4">
    <div class="card-header">
      <strong>Inquiries (Estimations)</strong> <span class="text-secondary small">— sums by area</span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped w-100" id="tblAreaInquiries">
          <thead>
            <tr>
              <th>Area</th>
              <th class="num">Jan</th><th class="num">Feb</th><th class="num">Mar</th><th class="num">Apr</th>
              <th class="num">May</th><th class="num">Jun</th><th class="num">Jul</th><th class="num">Aug</th>
              <th class="num">Sep</th><th class="num">Oct</th><th class="num">Nov</th><th class="num">Dec</th>
              <th class="num">Total</th>
            </tr>
            <tr class="filters">
              <th><input class="form-control form-control-sm" placeholder="Area"></th>
              @for($i=0;$i<13;$i++)<th></th>@endfor
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>

  {{-- POs table --}}
  <div class="card">
    <div class="card-header">
      <strong>POs (Sales Orders Received)</strong> <span class="text-secondary small">— sums by area</span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped w-100" id="tblAreaPOs">
          <thead>
          <tr>
            <th>Area</th>
            <th class="num">Jan</th><th class="num">Feb</th><th class="num">Mar</th><th class="num">Apr</th>
            <th class="num">May</th><th class="num">Jun</th><th class="num">Jul</th><th class="num">Aug</th>
            <th class="num">Sep</th><th class="num">Oct</th><th class="num">Nov</th><th class="num">Dec</th>
            <th class="num">Total</th>
          </tr>
          <tr class="filters">
            <th><input class="form-control form-control-sm" placeholder="Area"></th>
            @for($i=0;$i<13;$i++)<th></th>@endfor
          </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>















</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
  const fmtSAR = (n) => new Intl.NumberFormat('en-SA',{
    style:'currency', currency:'SAR', maximumFractionDigits:0
  }).format(n||0);

  const DATA_URL = @json(route('performance.area.data'));
  let year = @json($year);

  const columns = [
    {data:'area', name:'area'},
    {data:'jan', name:'jan', className:'num'},
    {data:'feb', name:'feb', className:'num'},
    {data:'mar', name:'mar', className:'num'},
    {data:'apr', name:'apr', className:'num'},
    {data:'may', name:'may', className:'num'},
    {data:'jun', name:'jun', className:'num'},
    {data:'jul', name:'jul', className:'num'},
    {data:'aug', name:'aug', className:'num'},
    {data:'sep', name:'sep', className:'num'},
    {data:'oct', name:'oct', className:'num'},
    {data:'nov', name:'nov', className:'num'},
    {data:'december', name:'december', className:'num'}, // <- NOT "dec"
    {data:'total', name:'total', className:'num'}
  ];

  function moneyRender(data, type) {
    if (type === 'display' || type === 'filter') return fmtSAR(Number(data||0));
    return data;
  }
  // apply money formatter to all numeric columns
  for (let i=1;i<columns.length;i++) columns[i].render = moneyRender;

  function initTable(selector, kind, badgeSel) {
    const $tbl = $(selector);
    const $filterRow = $tbl.find('thead tr.filters');

    const dt = $tbl.DataTable({
      processing:true, serverSide:true, order:[[0,'asc']],
      ajax: {
        url: DATA_URL,
        data: d => { d.kind = kind; d.year = year; }
      },
      columns,
      drawCallback: function(){
        const json = this.api().ajax.json() || {};
        if (badgeSel) $(badgeSel).text(
          (kind==='pos' ? 'POs Total: ' : 'Inquiries Total: ') + fmtSAR(json.sum_total||0)
        );
      }
    });

    // per-column filter
    $filterRow.find('th input').on('keyup change', function(){
      dt.column(0).search(this.value).draw();
    });
    return dt;
  }

  let dtInq = initTable('#tblAreaInquiries', 'inquiries', '#inqBadge');
  let dtPos = initTable('#tblAreaPOs',        'pos',        '#poBadge');

  $('#yearSelect').on('change', function(){
    year = this.value;
    dtInq.ajax.reload();
    dtPos.ajax.reload();
  });











  // Totals coming from controller (safe defaults)
  const totals = {
    quotations: Number(@json($quotationTotal ?? 0)),
    pos:        Number(@json($poTotal ?? 0)),
    year:       Number(@json($year ?? new Date().getFullYear()))
  };
  const gap = Math.abs(totals.quotations - totals.pos);

  Highcharts.setOptions({ lang: { thousandsSep: ',' } });

  // === Chart 1: Overall totals ===
  Highcharts.chart('poVsQuote', {
    chart: { type: 'column' },
    title: { text: 'Quotations vs POs (Total)' },
    subtitle: { text: `Gap: <b>${fmtSAR(gap)}</b> ${totals.quotations >= totals.pos ? '(more quoted)' : '(more POs)'}` },
    xAxis: { categories: [`Year ${totals.year}`], crosshair: true },
    yAxis: {
      min: 0,
      title: { text: 'Value (SAR)' },
      labels: { formatter() { return fmtSAR(this.value).replace('SAR', ''); } }
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
      column: { pointPadding: 0.2, borderWidth: 0, dataLabels: { enabled: true, formatter() { return fmtSAR(this.y); } } }
    },
    series: [
      { name: 'Quotations',  data: [ totals.quotations ] },
      { name: 'POs Received', data: [ totals.pos ] }
    ],
    credits: { enabled: false },
    legend: { reversed: false }
  });

  // === Chart 2 (optional): area-wise series ===
  const byArea = @json($byArea ?? []);
  if (byArea.length) {
    const cats = byArea.map(r => r.area);
    const quotations = byArea.map(r => Number(r.quotations || 0));
    const pos = byArea.map(r => Number(r.pos || 0));

    Highcharts.chart('poVsQuoteArea', {
      chart: { type: 'column' },
      title: { text: 'Quotations vs POs by Area' },
      xAxis: { categories: cats, crosshair: true },
      yAxis: {
        min: 0,
        title: { text: 'Value (SAR)' },
        labels: { formatter() { return fmtSAR(this.value).replace('SAR',''); } }
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
        column: { pointPadding: 0.2, borderWidth: 0, dataLabels: { enabled: true, formatter() { return fmtSAR(this.y); } } }
      },
      series: [
        { name: 'Quotations',  data: quotations },
        { name: 'POs Received', data: pos }
      ],
      credits: { enabled: false }
    });
  }

















  (function(){
  const year  = $('#sel-year').val();            // you already have this on page
  let month   = $('#sel-month').val();

  function fmt(n){ return new Intl.NumberFormat('en-SA', {maximumFractionDigits:0}).format(n||0); }

  function card(label, v){
    return `
      <div class="col">
        <div class="border rounded p-2">
          <div class="small text-muted">${label}</div>
          <div><strong>${fmt(v.actual)}</strong></div>
          <div class="text-muted small">Budget: ${fmt(v.budget)}</div>
          <div class="${v.variance>=0?'text-success':'text-danger'} small">Variance: ${fmt(v.variance)}</div>
          <div class="small">Percent: ${v.percent===null ? '-' : (v.percent+'%')}</div>
        </div>
      </div>`;
  }

  function rowBlock(title, data){
    return `
      <div class="mb-3">
        <div class="fw-semibold mb-2">${title}</div>
        <div class="row row-cols-4 g-2">
          ${card('Saudi Arabia', data['Saudi Arabia'])}
          ${card('Export', data['Export'])}
          ${card('Total', data['total'])}
        </div>
      </div>`;
  }

  function loadKpis(){
    $.getJSON('{{ route('performance.area.kpis') }}', {year: $('#sel-year').val(), month: $('#sel-month').val()}, function(r){
      const html = `
        <div class="row"><div class="col-md-6">
          <h6>Inquiries</h6>
          ${rowBlock('Month', { 'Saudi Arabia': r.inquiries.month['Saudi Arabia']||{actual:0,budget:0,variance:0,percent:null},
                                'Export':       r.inquiries.month['Export']||{actual:0,budget:0,variance:0,percent:null},
                                'total':        r.inquiries.month_total })}
          ${rowBlock('Year to date', { 'Saudi Arabia': r.inquiries.ytd['Saudi Arabia']||{actual:0,budget:0,variance:0,percent:null},
                                       'Export':       r.inquiries.ytd['Export']||{actual:0,budget:0,variance:0,percent:null},
                                       'total':        r.inquiries.ytd_total })}
        </div><div class="col-md-6">
          <h6>POs</h6>
          ${rowBlock('Month', { 'Saudi Arabia': r.pos.month['Saudi Arabia']||{actual:0,budget:0,variance:0,percent:null},
                                'Export':       r.pos.month['Export']||{actual:0,budget:0,variance:0,percent:null},
                                'total':        r.pos.month_total })}
          ${rowBlock('Year to date', { 'Saudi Arabia': r.pos.ytd['Saudi Arabia']||{actual:0,budget:0,variance:0,percent:null},
                                       'Export':       r.pos.ytd['Export']||{actual:0,budget:0,variance:0,percent:null},
                                       'total':        r.pos.ytd_total })}
        </div></div>`;
      $('#area-kpis').html(html);
    });
  }

  $('#sel-month, #sel-year').on('change', loadKpis);
  loadKpis();
})();





</script>
</body>
</html>
