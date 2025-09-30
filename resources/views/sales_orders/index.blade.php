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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">

  <style>
    table.dataTable thead tr.filters th { background: var(--bs-tertiary-bg); }
    table.dataTable thead .form-control-sm,
    table.dataTable thead .form-select-sm { height: calc(1.5em + .5rem + 2px); }
    .badge-total { font-weight:600; }
    td.details-control { cursor: pointer; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="{{ route('projects.index') }}">ATAI Projects</a>
    <div class="ms-auto d-flex align-items-center gap-2"></div>
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

<div class="card mb-2">
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
          <option>Eastern</option><option>Central</option><option>Western</option>
        </select>
        <button id="kpiApply" class="btn btn-sm btn-primary">Update</button>
      </div>
      <div class="d-flex gap-2">
        <span id="badgeTotalVAT" class="badge text-bg-primary">Total (VAT): SAR 0</span>
        <span id="badgeTotalPO"  class="badge text-bg-info">Total PO: SAR 0</span>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-6"><div id="chartRegion"></div></div>
      <div class="col-md-6"><div id="chartStatus"></div></div>
      <div class="col-12"><div id="chartMonthly"></div></div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header py-1"><strong>Top Clients (by VAT)</strong></div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <thead><tr><th>Client</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr></thead>
              <tbody id="topClientsBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- <div class="col-12 col-lg-6"><div id="chartCurrency"></div></div> -->
    </div>
  </div>
</div>

<div class="d-flex align-items-center gap-2 my-2 flex-wrap">
  <span id="sumPo"  class="badge text-bg-info  badge-total">Total PO: SAR 0</span>
  <span id="sumVat" class="badge text-bg-primary badge-total">Total with VAT: SAR 0</span>
</div>

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
</main>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
$.fn.dataTable.ext.errMode = 'console';
const fmt = n => Number(n||0).toLocaleString('en-US', { maximumFractionDigits: 2 });

$(function(){
  const dt = $('#tblSales').DataTable({
    processing: true,
    serverSide: true,
    order: [[0,'desc']],
    pageLength: 25,
    ajax: {
      url: "{{ route('salesorders.datatable') }}",
      type: 'GET',
      dataSrc: function (json) {
        updateBadges(json);
        return json.data ?? [];
      }
    },
    columns: [
      { data: 'date_rec_d',       name: 'date_rec_d' },
      { data: 'po_no',            name: 'po_no' },
      { data: 'client_name',      name: 'client_name' },
      { data: 'project_name',     name: 'project_name' },
      { data: 'region_name',      name: 'region_name' },
      { data: 'project_location', name: 'project_location' },
      { data: 'cur',              name: 'cur' },
      { data:'po_value',         name:'po_value',       className:'text-end', render: d => fmt(d) },
      { data:'value_with_vat',   name:'value_with_vat', className:'text-end', render: d => fmt(d) },
      { data: 'status',           name: 'status' }
    ]
  });

  $('#tblSales').on('xhr.dt', function(_, settings, json){
    updateBadges(json || {});
  });

  function updateBadges(j){
    const po  = fmt(j?.sum_po_value ?? 0);
    const vat = fmt(j?.sum_value_with_vat ?? 0);
    document.getElementById('sumPo').textContent  = 'Total PO: SAR ' + po;
    document.getElementById('sumVat').textContent = 'Total with VAT: SAR ' + vat;
  }
});

// ==== KPI DASHBOARD ====
const hcBase = { chart:{ height:220, spacing:[8,8,8,8] }, credits:{ enabled:false }, legend:{ enabled:false } };

async function loadKpis(){
  const year = document.getElementById('kpiYear').value || '';
  const region = document.getElementById('kpiRegion').value || '';
  const url = new URL("{{ route('salesorders.kpis') }}", window.location.origin);
  if (year)   url.searchParams.set('year', year);
  if (region) url.searchParams.set('region', region);

  const res = await fetch(url, { credentials:'same-origin' });
  if(!res.ok){ console.error('kpis', await res.text()); return; }
  const d = await res.json();

  // Totals (chips)
  document.getElementById('badgeTotalVAT').textContent = 'Total (VAT): SAR ' + fmt(Number(d.totals.value_with_vat || 0));
  document.getElementById('badgeTotalPO').textContent  = 'Total PO: SAR ' + fmt(Number(d.totals.po_value || 0));

  // Region (column)  <-- uses region + total
  Highcharts.chart('chartRegion', Highcharts.merge(hcBase, {
    title:{ text:'By Region (VAT)' },
    xAxis:{ categories:(d.by_region||[]).map(x=>x.region || '—') },
    yAxis:{ title:{ text:'SAR' } },
    series:[{ type:'column', data:(d.by_region||[]).map(x=>Number(x.total || 0)) }]
  }));

  // Status (pie)  <-- uses status + total
  Highcharts.chart('chartStatus', Highcharts.merge(hcBase, {
    title:{ text:'By Status (VAT)' },
    series:[{ type:'pie', data:(d.by_status||[]).map(x=>({ name:x.status || '—', y:Number(x.total || 0) })) }]
  }));

  // Monthly (column)  <-- uses ym + total
  Highcharts.chart('chartMonthly', Highcharts.merge(hcBase, {
    title:{ text:'Monthly (VAT)' },
    xAxis:{ categories:(d.monthly||[]).map(m=>m.ym) },
    yAxis:{ title:{ text:'SAR' } },
    series:[{ type:'column', data:(d.monthly||[]).map(m=>Number(m.total || 0)) }]
  }));

  // Top clients table  <-- uses client + orders + total
  const body = document.getElementById('topClientsBody');
  body.innerHTML = '';
  (d.top_clients||[]).forEach(c=>{
    body.insertAdjacentHTML('beforeend',
      `<tr>
         <td>${c.client || '—'}</td>
         <td class="text-end">${Number(c.orders || 0)}</td>
         <td class="text-end">${fmt(Number(c.total || 0))}</td>
       </tr>`
    );
  });

  // Currency (pie)  <-- uses currency + total
  // If/when you render it:
  // Highcharts.chart('chartCurrency', Highcharts.merge(hcBase, {
  //   title:{ text:'By Currency (VAT)' },
  //   series:[{ type:'pie', data:(d.by_currency||[]).map(x=>({name:x.currency || '—', y:Number(x.total || 0)})) }]
  // }));
}

document.getElementById('kpiApply').addEventListener('click', loadKpis);
loadKpis(); // initial
</script>
</body>
</html>
