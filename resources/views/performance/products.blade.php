<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Product Summary — Performance</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
  <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
  <style>
    .badge-total { font-weight:600; }
    .table-sticky thead th { position: sticky; top: 0; background: var(--bs-body-bg); z-index: 1; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container-fluid">
    <a href="{{ route('projects.index') }}" class="navbar-brand fw-bold">ATAI Projects</a>
  </div>
</nav>

<main class="container py-4">
<ul class="nav nav-pills gap-2 flex-wrap">
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('projects.*')) active @endif"
       href="{{ route('projects.index') }}" aria-current="{{ request()->routeIs('projects.*') ? 'page' : '' }}">
       Projects
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('salesorders.*')) active @endif"
       href="{{ route('salesorders.index') }}">
       Sales Orders
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('performance.index')) active @endif"
       href="{{ route('performance.index') }}">Performance report</a>
  </li>
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('performance.area')) active @endif"
       href="{{ route('performance.area') }}">Area summary</a>
  </li>
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('performance.salesman')) active @endif"
       href="{{ route('performance.salesman') }}">SalesMan summary</a>
  </li>
  <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('performance.products')) active @endif"
       href="{{ route('performance.products') }}">Product summary</a>
  </li>
 <!-- <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('performance.products')) active @endif"
       href="{{ route('performance.products') }}">Dashboard</a>
  </li> -->
   <li class="nav-item">
    <a class="nav-link @if(request()->routeIs('powerbi.jump')) active @endif"
      href="{{ route('powerbi.jump') }}" target="_blank">
  Power BI Dashboard</a>
  </li>
</ul>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <h6 class="mb-0">Product Summary</h6>
        <div class="ms-auto d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width: 120px;">
            <span class="input-group-text">Year</span>
            <select id="yearSelect" class="form-select">
              @for($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
              @endfor
            </select>
          </div>
          <span class="badge text-bg-primary" id="badgeInq">Inquiries: SAR 0</span>
          <span class="badge text-bg-success" id="badgePO">POs: SAR 0</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h6 class="card-title mb-2">Products comparison (Inquiries vs POs)</h6>
      <div id="chartProducts" style="height: 360px;"></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h6 class="card-title">Inquiries — by Product</h6>
      <div class="table-responsive">
        <table id="tblProdInquiries" class="table table-striped table-sticky w-100">
          <thead>
            <tr>
              <th>Product</th>
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
      <h6 class="card-title">POs received — by Product</h6>
      <div class="table-responsive">
        <table id="tblProdPOs" class="table table-striped table-sticky w-100">
          <thead>
            <tr>
              <th>Product</th>
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
   const YEAR_INIT  = {{ (int) $year }};
  const DT_URL_INQ = @json(route('performance.products.data', ['kind' => 'inq']));
  const DT_URL_PO  = @json(route('performance.products.data', ['kind' => 'po']));
  const KPI_URL    = @json(route('performance.products.kpis'));

  const fmtSAR = n => new Intl.NumberFormat('en-SA',{style:'currency',currency:'SAR',maximumFractionDigits:0}).format(Number(n||0));

  const columns = [
    { data:'product', name:'product', orderable:false, searchable:false },
    { data:'jan' }, { data:'feb' }, { data:'mar' }, { data:'apr' }, { data:'may' }, { data:'jun' },
    { data:'jul' }, { data:'aug' }, { data:'sep' }, { data:'oct' }, { data:'nov' }, { data:'december' },
    { data:'total' }
  ];

  function initTable(selector, url, badgeSelector, badgeLabel){
    return $(selector).DataTable({
      processing:true, serverSide:true, searching:true, order:[[13,'desc']],
      ajax:{
        url:url,
        data:d=>{ d.year = $('#yearSelect').val(); }
      },
      columns: columns,
      drawCallback: function(){
        const json = this.api().ajax.json() || {};
        if (badgeSelector && json.sum_total != null) {
          $(badgeSelector).text(badgeLabel + fmtSAR(json.sum_total));
        }
      }
    });
  }

  const dtInq = initTable('#tblProdInquiries', DT_URL_INQ, '#badgeInq', 'Inquiries: ');
  const dtPO  = initTable('#tblProdPOs',       DT_URL_PO,  '#badgePO', 'POs: ');

  $('#yearSelect').on('change', function(){
    dtInq.ajax.reload(null,false);
    dtPO.ajax.reload(null,false);
    loadChart();
  });

  async function loadChart(){
    const year = $('#yearSelect').val();
    const data = await (await fetch(`${KPI_URL}?year=${year}`, {credentials:'same-origin'})).json();
    Highcharts.chart('chartProducts', {
      chart: { type:'column' },
      title: { text: `Products comparison — ${year}` },
      xAxis: { categories: data.categories, crosshair: true },
      yAxis: { min:0, title:{ text:'SAR' } },
      tooltip: { shared:true, valueDecimals:0 },
      plotOptions: { column: { pointPadding:0.1, borderWidth:0 } },
      series: [
        { name:'Inquiries', data:data.inquiries },
        { name:'POs',       data:data.pos }
      ]
    });
    $('#badgeInq').text('Inquiries: ' + fmtSAR(data.sum_inquiries));
    $('#badgePO').text('POs: ' + fmtSAR(data.sum_pos));
  }
  loadChart();
</script>
</body>
</html>
