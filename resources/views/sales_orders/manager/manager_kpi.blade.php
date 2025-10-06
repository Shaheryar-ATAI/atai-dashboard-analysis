<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Order Log KPI — ATAI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}">

    <style>
        .kpi-card .hc {
            height: 280px;
        }

        .pill-chips .btn {
            text-transform: none;
        }

        .badge-total {
            font-weight: 600;
        }

        .glass {
            background: var(--bs-body-bg);
            border-radius: .75rem;
            padding: .5rem .75rem;
        }
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
            </ul>
            <div class="navbar-text me-2">
                Logged in as <strong>{{ $u->name ?? '' }}</strong>
                @if(!empty($u->region))
                    · <small>{{ $u->region }}</small>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center gap-2 mb-2">
        <select id="fYear" class="form-select form-select-sm" style="width:auto">
            <option value="">All Years</option>
            @for ($y = date('Y'); $y >= date('Y')-6; $y--)
                <option value="{{ $y }}">{{ $y }}</option>
            @endfor
        </select>
        <select id="fMonth" class="form-select form-select-sm" style="width:auto">
            <option value="">All Months</option>
            @for ($m=1;$m<=12;$m++)
                <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option>
            @endfor
        </select>
        <input id="fFrom" type="date" class="form-control form-control-sm" style="width:auto">
        <input id="fTo" type="date" class="form-control form-control-sm" style="width:auto">
        <button id="btnApply" class="btn btn-primary btn-sm">Update</button>

        <div class="d-flex gap-2">
{{--        --}}
{{--        <span class="badge rounded-pill text-bg-success badge-total" id="badgeCount">Orders: 0</span>--}}
{{--        <span class="ms-auto badge rounded-pill text-bg-primary badge-total" id="badgeTotal">Total: SAR 0</span>--}}


        <span id="badgeCount" class="badge-total text-bg-info">Total Sales-Order No.: 0</span>
        <span id="badgeTotal" class="badge-total text-bg-primary">Total Sales-Order Value: SAR 0</span>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-2">
        <div id="familyChips" class="btn-group pill-chips" role="group" aria-label="Families"></div>
    </div>
    <div class="d-flex justify-content-end mb-2">
        <div id="statusChips" class="btn-group pill-chips" role="group" aria-label="Statuses"></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Orders by Area (stacked by Status)</div>
                    <div id="hcArea" class="hc"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Value by Status</div>
                    <div id="hcStatus" class="hc"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Monthly Value — Accepted / Pre-Acceptance / Waiting / Rejected + MoM
                        %
                    </div>
                    <div id="hcMonthly" class="hc"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>

<script>
    (() => {
        const fmtSAR = n => 'SAR ' + Number(n || 0).toLocaleString();

        // --- Canonical product families (display order) ---
        const MAIN_FAMILIES = ['Ductwork', 'Dampers', 'Sound Attenuators', 'Accessories'];
        let currentFamily = '';  // display label ('' = All)
        let currentStatus = '';  // '' = All

        // Normalize DB/product labels to our display labels
        function normalizeFamily(name = '') {
            const n = String(name).trim().toLowerCase();
            if (['ductwork','ductworks','round duct','round ducts'].includes(n)) return 'Ductwork';
            if (['damper','dampers','fire/smoke dampers','fire /smoke dampers'].includes(n)) return 'Dampers';
            if (['sound attenuator','sound attenuators','sound attenuator(s)'].includes(n)) return 'Sound Attenuators';
            if (['accessory','accessories'].includes(n)) return 'Accessories';
            return '';
        }

        // Map display label back to DB-facing value (if your DB uses slightly different forms, edit here)
        function displayToDbFamily(display = '') {
            switch (display) {
                case 'Ductwork':          return 'Ductwork';
                case 'Dampers':           return 'Dampers';
                case 'Sound Attenuators': return 'Sound Attenuators';
                case 'Accessories':       return 'Accessories';
                default:                  return '';
            }
        }

        // Build filters for the API call
        function filters() {
            const f = {
                year  : $('#fYear').val()  || '',
                month : $('#fMonth').val() || '',
                from  : $('#fFrom').val()  || '',
                to    : $('#fTo').val()    || ''
            };
            const fam = displayToDbFamily(currentFamily);
            if (fam)    f.family = fam;
            if (currentStatus) f.status = currentStatus; // Accepted / Pre-Acceptance / Waiting / Rejected
            return f;
        }

        // Generic chip builder (shared by family & status)
        function buildChips(containerId, items, activeVal, onChange) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const html = [
                `<button type="button" class="btn btn-sm ${!activeVal ? 'btn-primary':'btn-outline-primary'}" data-val="">All</button>`,
                ...items.map(name =>
                    `<button type="button" class="btn btn-sm ${activeVal===name ? 'btn-primary':'btn-outline-primary'}" data-val="${name}">${name}</button>`
                )
            ].join('');
            el.innerHTML = html;

            el.onclick = (e) => {
                const btn = e.target.closest('button[data-val]');
                if (!btn) return;
                el.querySelectorAll('button').forEach(b => b.classList.replace('btn-primary','btn-outline-primary'));
                btn.classList.replace('btn-outline-primary','btn-primary');
                onChange(btn.getAttribute('data-val') || '');
            };
        }

        async function loadKPIs() {
            const qs = new URLSearchParams(filters()).toString();
            const res = await fetch(`{{ route('salesorders.manager.kpis') }}?${qs}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
            const payload = res.ok ? await res.json() : {};

            // Badges
            document.getElementById('badgeCount').textContent =
                'Total Sales-Order No.: ' + Number(payload?.totals?.count || 0).toLocaleString();
            document.getElementById('badgeTotal').textContent =
                'Total Sales-Order Value: ' + fmtSAR(payload?.totals?.value || 0);

            // ----- PRODUCT chips (force canonical order; fall back to MAIN_FAMILIES) -----
            const familiesFromApi = Array.isArray(payload?.families) ? payload.families : [];
            const normalizedSet = new Set(familiesFromApi.map(normalizeFamily).filter(Boolean));
            const orderedFamilies = MAIN_FAMILIES.filter(f => normalizedSet.has(f));
            const families = orderedFamilies.length ? orderedFamilies : MAIN_FAMILIES;

            buildChips('familyChips', families, currentFamily, (val) => {
                currentFamily = val;            // display label
                loadKPIs();                     // re-query with new filter
            });

            // ----- STATUS chips (Accepted / Pre-Acceptance / Waiting / Rejected) -----
            const statusList = Array.isArray(payload?.statuses) && payload.statuses.length
                ? payload.statuses
                : ['Accepted','Pre-Acceptance','Waiting','Rejected'];

            buildChips('statusChips', statusList, currentStatus, (val) => {
                currentStatus = val;            // exact status text
                loadKPIs();
            });

            // ----- Chart 1: Area + Status (you were rendering multiMonthly here; keep as you had) -----
            (function(){
                const catsIso   = payload.multiMonthly?.categories || [];
                const catsNice  = catsIso.map(ym => {
                    const [y,m] = String(ym||'').split('-');
                    if (!y || !m) return ym || '';
                    return new Date(Number(y), Number(m)-1, 1).toLocaleString('en',{month:'short'}) + ' ' + String(y).slice(-2);
                });
                const barSeries  = payload.multiMonthly?.bars  || [];
                const lineSeries = payload.multiMonthly?.lines || [];

                Highcharts.chart('hcArea', {
                    chart: { backgroundColor:'transparent' },
                    title: { text:null },
                    xAxis: { categories: catsNice },
                    yAxis: [{ title:{ text:'Value (SAR)' }, min:0, labels:{ formatter(){ return fmtSAR(this.value); }}}],
                    tooltip: {
                        shared:true,
                        formatter(){
                            return `<b>${this.x}</b><br/>` + this.points.map(p =>
                                `<span style="color:${p.color}">\u25CF</span> ${p.series.name}: <b>${p.series.type==='spline' ? p.y.toLocaleString() : fmtSAR(p.y)}</b>`
                            ).join('<br/>');
                        }
                    },
                    plotOptions: {
                        column: { grouping:true, groupPadding:0.12, pointPadding:0.02,
                            dataLabels:{ enabled:true, formatter(){ return this.y ? fmtSAR(this.y) : '' } } },
                        spline: { marker:{enabled:false}, dataLabels:{ enabled:true, formatter(){ return this.y.toLocaleString(); } } }
                    },
                    series: [...barSeries, ...lineSeries],
                    credits: { enabled:false }
                });
            })();

            // ----- Chart 2: Status Pie (value) -----
            Highcharts.chart('hcStatus', {
                chart: { type:'pie', backgroundColor:'transparent' },
                title: { text:null },
                tooltip: { pointFormatter(){ return `<b>${fmtSAR(this.y)}</b>`; } },
                plotOptions: { pie: { dataLabels:{ enabled:true, format:'{point.name}: {point.percentage:.1f}%' } } },
                series: [{ name:'Value', data: payload.statusPie || [] }],
                credits: { enabled:false }
            });

            // ----- Chart 3: Monthly value + MoM % -----
            const cats = payload.monthly?.categories || [];
            const vals = payload.monthly?.values || [];
            const mom  = vals.map((v,i)=> i===0 || !vals[i-1] ? 0 : Math.round(((v-vals[i-1])/vals[i-1])*10000)/100);

            Highcharts.chart('hcMonthly', {
                title: { text:null },
                xAxis: { categories: cats.map(ym => {
                        const [y,m] = (ym||'').split('-');
                        return (y && m) ? new Date(y, m-1, 1).toLocaleString('en',{month:'short'}) + ' ' + String(y).slice(-2) : ym;
                    })},
                yAxis: [{
                    title: { text:'Value (SAR)' }, min:0,
                    labels: { formatter(){ return fmtSAR(this.value); } }
                },{
                    title: { text:'Percent (%)' }, opposite:true, min:0
                }],
                tooltip: {
                    shared:false,
                    formatter(){
                        return this.series.yAxis.opposite
                            ? `<b>${this.x}</b><br/>${this.series.name}: <b>${this.y}%</b>`
                            : `<b>${this.x}</b><br/>${this.series.name}: <b>${fmtSAR(this.y)}</b>`;
                    }
                },
                plotOptions: {
                    column: { dataLabels:{ enabled:true, formatter(){ return this.y ? fmtSAR(this.y) : '' } } },
                    spline: { marker:{enabled:false}, dataLabels:{ enabled:true, formatter(){ return `${this.y}%`; } } }
                },
                series: [
                    { type:'column', name:'Value (SAR)', data: vals },
                    { type:'spline', name:'MoM %', yAxis:1, data: mom, dashStyle:'ShortDot' }
                ],
                credits: { enabled:false }
            });
        }

        $('#btnApply').on('click', loadKPIs);
        loadKPIs(); // initial
    })();
</script>

</body>
</html>
