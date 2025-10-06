{{-- resources/views/performance/index.blade.php --}}
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>ATAI — Performance</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">
  <style>
    .chip-group .btn { border-radius: 999px; }
    .chip-group .btn.active { background: var(--bs-primary); color: #fff; }
    .total-badge {
      min-width: 130px;
      border: 2px solid var(--bs-primary);
      border-radius: .5rem;
      text-align: center;
      font-weight: 600;
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





  <div class="card">
    <div class="card-header bg-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <strong>Performance report</strong>
        </div>
        <div class="d-flex align-items-center gap-2">
          <!-- <span> Total </span>
          <span id="totalBox" class="px-3 py-1 total-badge">SAR 0</span> -->
        </div>
      </div>
    </div>
    <div class="card-body">




    <div>


{{-- resources/views/performance/index.blade.php --}}
<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-1">Totals ({{ $year }})</h6>
    <div id="poVsQuote" style="height: 340px;"></div>
  </div>
</div>

{{-- Optional: area-wise comparison --}}
<div class="card">
  <div class="card-body">
    <h6 class="mb-1">By Area ({{ $year }}) — Quotations vs POs</h6>
    <div id="poVsQuoteArea" style="height: 380px;"></div>
  </div>
</div>


    </div>

      {{-- Filters row --}}
      <!-- <div class="row g-3 align-items-center mb-3">
        <div class="col-md-4">
          <label class="form-label mb-1">Date range</label>
          <input class="form-control" value="(coming soon)" disabled>
        </div>
        <div class="col-md-4">
          <label for="areaSelect" class="form-label mb-1">Area</label>
          <select id="areaSelect" class="form-select">
            @php $areas = ['All','Eastern','Central','Western']; @endphp
            @foreach($areas as $a)
              <option value="{{ $a }}" {{ (strtolower($a) === strtolower($region ?? '')) ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1 d-block">Family</label>
          <div id="familyChips" class="chip-group btn-group" role="group">
            <button type="button" class="btn btn-outline-primary active" data-family="all">All</button>
            <button type="button" class="btn btn-outline-primary" data-family="ductwork">Ductwork</button>
            <button type="button" class="btn btn-outline-primary" data-family="dampers">Dampers</button>
            <button type="button" class="btn btn-outline-primary" data-family="sound">Sound Attenuators</button>
            <button type="button" class="btn btn-outline-primary" data-family="accessories">Accessories</button>
          </div>
        </div>
      </div> -->

      {{-- Charts --}}
      <!-- <div class="row g-3">
        <div class="col-lg-6">
          <div id="pieByStatus" style="height:300px;"></div>
        </div>
        <div class="col-lg-6">
          <div id="lineByArea" style="height:300px;"></div>
        </div>
      </div> -->

      {{-- Placeholder for future DataTables block --}}
      <!-- <div class="mt-4">
        <div class="card">
          <div class="card-body">
            <div class="text-secondary">Sales manager performance (coming soon)</div>
            <div class="small text-secondary">We’ll load DataTables here later.</div>
          </div>
        </div>
      </div> -->

    </div>
  </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>

<script>
  // --- Config & helpers ---
  const API = @json(route('performance.kpis'));
  const DEFAULT_AREA = @json($region ?? null);

  const fmtSAR = (n) =>
    new Intl.NumberFormat('en-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(Number(n||0));

  let currentFamily = 'all';

  // --- Wire filters ---
  $('#areaSelect').val(DEFAULT_AREA ? DEFAULT_AREA : 'All');

  $('#familyChips').on('click', 'button[data-family]', function() {
    $('#familyChips button').removeClass('active');
    $(this).addClass('active');
    currentFamily = $(this).data('family');
    loadKpis();
  });

  $('#areaSelect').on('change', loadKpis);

  // --- Fetch KPIs and paint ---
  async function loadKpis() {
    const params = new URLSearchParams();
    const area = $('#areaSelect').val() || '';
    if (area && area.toLowerCase() !== 'all') params.set('area', area);
    if (currentFamily && currentFamily !== 'all') params.set('family', currentFamily);

    const url = API + '?' + params.toString();

    let data = { total: 0, status: [], area: [] };
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      data = await res.json();
    } catch (e) {
      console.error('KPI fetch failed', e);
    }

    // Update total box
    document.getElementById('totalBox').textContent = fmtSAR(data.total || 0);

    // Pie: value by status
    const pieData = (data.status || []).map(s => ({
      name: String(s.status || '').toUpperCase() || 'UNKNOWN',
      y: Number(s.sum_price || 0)
    }));
    Highcharts.chart('pieByStatus', {
      title: { text: 'Quotation Value by Status' },
      series: [{ type: 'pie', data: pieData.length ? pieData : [{ name:'NO DATA', y:0 }] }]
    });

    // Line: projects by area (counts)
    const areas = (data.area || []).map(a => a.area || '—');
    const counts = (data.area || []).map(a => Number(a.cnt || 0));
    Highcharts.chart('lineByArea', {
      title: { text: 'Projects by Area' },
      xAxis: { categories: areas },
      yAxis: { title: { text: 'Count' }, allowDecimals: false },
      series: [{ name: 'Projects', data: counts }]
    });
  }

  // Boot
  (async function() {
    await loadKpis();
  })();













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


















</script>
</body>
</html>
