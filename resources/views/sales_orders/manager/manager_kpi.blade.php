<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Order Log KPI — ATAI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}">

    <style>
        .kpi-card .hc { height: 280px; }
        .pill-chips .btn { text-transform: none; }
        .badge-total { font-weight: 600; }
        .glass { background: var(--bs-body-bg); border-radius: .75rem; padding: .5rem .75rem; }

        /* Minimal switcher/card CSS so the right panel is visible */
        .kpi-switcher { position: relative; }
        .kpi-panel { display: none; }
        .kpi-panel--active { display: block; }
        .kpi-cards-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:.75rem; }
        .cards-title { font-weight:700; font-size:1.125rem; }
        .muted { opacity:.75; font-size:.9rem; }
        .kpi-cards-grid {
            display:grid; gap:.75rem;
            grid-template-columns: repeat(4, minmax(0,1fr));
        }
        .kpi-mini { padding:.75rem; border-radius:.5rem; background: rgba(255,255,255,.04); }
        .kpi-mini.ok   { outline:1px solid rgba(34,197,94,.35); }
        .kpi-mini.warn { outline:1px solid rgba(245,158,11,.35); }
        .kpi-mini.dang { outline:1px solid rgba(239,68,68,.35); }
        .kpi-label { font-size:.85rem; opacity:.8; }
        .kpi-value { font-weight:700; font-size:1.1rem; }
        @media (max-width: 992px){
            .kpi-cards-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }

        /* Better chip layout */
        #familyChips, #statusChips {
            display:flex; flex-wrap:wrap; gap:.4rem;
        }
        #familyChips .btn, #statusChips .btn { border-radius: 999px; }
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

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ataiNav"
                aria-controls="ataiNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="ataiNav">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}" href="{{ route('projects.index') }}">Quotation KPI</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs(['inquiries.index']) ? 'active' : '' }}" href="{{ route('inquiries.index') }}">Quotation Log</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('salesorders.manager.kpi') ? 'active' : '' }}" href="{{ route('salesorders.manager.kpi') }}">Sales Order Log KPI</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('salesorders.manager.index') ? 'active' : '' }}" href="{{ route('salesorders.manager.index') }}">Sales Order Log</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}" href="{{ route('estimation.index') }}">Estimation</a></li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('forecast.*') ? 'active' : '' }}" href="{{ route('forecast.create') }}">
                        Forecast
                    </a>
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

            <div class="navbar-right">
                <div class="navbar-text me-2">
                    Logged in as <strong>{{ $u->name ?? '' }}</strong>
                    @if(!empty($u->region))
                        · <small>{{ $u->region }}</small>
                    @endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                    <button class="btn btn-logout btn-sm" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center gap-2 mb-2">
        <select id="fYear" class="form-select form-select-sm" style="width:auto">
            <option value="">All Years</option>
            @for ($y = date('Y'); $y >= date('Y')-6; $y--) <option value="{{ $y }}">{{ $y }}</option> @endfor
        </select>
        <select id="fMonth" class="form-select form-select-sm" style="width:auto">
            <option value="">All Months</option>
            @for ($m=1;$m<=12;$m++) <option value="{{ $m }}">{{ date('F', mktime(0,0,0,$m,1)) }}</option> @endfor
        </select>
        <input id="fFrom" type="date" class="form-control form-control-sm" style="width:auto">
        <input id="fTo" type="date" class="form-control form-control-sm" style="width:auto">
        <button id="btnApply" class="btn btn-primary btn-sm">Update</button>
    </div>

    <div class="row g-3 mb-4 text-center justify-content-center">
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div id="badgeCount" class="kpi-value">Total Sales-Order No.: 0</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                <div id="badgeTotal" class="kpi-value">Total Sales-Order Value: SAR 0</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center mb-2">
        <div id="familyChips" class="btn-group pill-chips" role="group" aria-label="Families"></div>
    </div>
    <div class="d-flex justify-content-end mb-2">
        <div id="statusChips" class="btn-group pill-chips" role="group" aria-label="Statuses"></div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card kpi-card">
                <div class="card-body">
                    <div id="hcMonthly" class="hc"></div>
                    <div id="barPoValueByArea" class="hc" style="height:100px"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Switcher: LEFT = status chart, RIGHT = monthly KPI cards -->
    <div class="col-12 mt-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="fw-semibold mb-2">Sales Order Status — Monthly</div>

                <div id="kpiSwitcher" class="kpi-switcher">
                    <button id="kpiToggleBtn" type="button"
                            class="btn btn-outline-warning btn-sm kpi-toggle-btn"
                            title="Switch chart/cards">Show Cards</button>
                    <!-- Panel A: chart (active by default) -->
                    <div id="panelChart" class="kpi-panel kpi-panel--active">
                        <div id="kpi_status_monthly" style="height:420px"></div>
                    </div>
                    <!-- Panel B: monthly KPI cards -->
                    <div id="panelCards" class="kpi-panel">
                        <div class="kpi-cards-head">
                            <div class="muted">Move mouse left = chart · right = cards</div>
                            <div class="muted">Showing all months</div>
                        </div>
                        <div id="monthBoard" class="months-wrap"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="card kpi-cardinitStatusKpiSwitcher mt-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Products Comparison Monthly progress</div>
            <div id="hcProductClusterMonthly" class="hc"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>

<script>
    (() => {
        const fmtSAR = n => 'SAR ' + Math.round(Number(n || 0)).toLocaleString();
        // Fallback list (used only if API doesn't send allFamilies)
        const FALLBACK_PRODUCTS = [
            'Ductwork','Round Duct','SEMI',
            'Dampers','Volume Dampers','Fire / Smoke Dampers',
            'Sound Attenuators',
            'Accessories','Access Doors','Actuators','Louvers'
        ];

        let currentFamily = '';
        let currentStatus = '';

        function filters(){
            const f = {
                year:  $('#fYear').val()  || '',
                month: $('#fMonth').val() || '',
                from:  $('#fFrom').val()  || '',
                to:    $('#fTo').val()    || ''
            };
            if (currentFamily) f.family = currentFamily;   // pass EXACT chip text
            if (currentStatus) f.status = currentStatus;
            return f;
        }

        function buildChips(containerId, items, activeVal, onChange) {
            const el = document.getElementById(containerId);
            if (!el) return;
            const html = [
                `<button type="button" class="btn btn-sm ${!activeVal ? 'btn-primary' : 'btn-outline-primary'}" data-val="">All</button>`,
                ...items.map(name => `<button type="button" class="btn btn-sm ${activeVal === name ? 'btn-primary' : 'btn-outline-primary'}" data-val="${name}">${name}</button>`)
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

        async function loadKPIs(){
            const qs = new URLSearchParams(filters()).toString();
            const res = await fetch(`{{ route('salesorders.manager.kpis') }}?${qs}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
            const payload = res.ok ? await res.json() : {};

            // badges
            document.getElementById('badgeCount').textContent =
                'Total Sales-Order No.: ' + Number(payload?.totals?.count || 0).toLocaleString();
            document.getElementById('badgeTotal').textContent =
                'Total Sales-Order Value: ' + fmtSAR(payload?.totals?.value || 0);

            // families/status chips (USE API LIST; fallback only if needed)
            const fams = Array.isArray(payload?.allFamilies) && payload.allFamilies.length
                ? payload.allFamilies
                : FALLBACK_PRODUCTS;
            buildChips('familyChips', fams, currentFamily, (val) => { currentFamily = val; loadKPIs(); });

            const stats = Array.isArray(payload?.allStatuses) && payload.allStatuses.length
                ? payload.allStatuses
                : ['Accepted','Pre-Acceptance','Waiting','Rejected','Cancelled','Unknown'];
            buildChips('statusChips', stats, currentStatus, (val) => { currentStatus = val; loadKPIs(); });

            // monthly simple chart
            const cats = payload.monthly?.categories || [];
            const vals = payload.monthly?.values || [];
            const mom  = vals.map((v,i)=> i===0||!vals[i-1] ? 0 : Math.round(((v-vals[i-1])/vals[i-1])*10000)/100);

            Highcharts.chart('hcMonthly', {
                chart:{ backgroundColor:'transparent', spacing:[10,20,10,20] },
                title:{ text:'Sales Order Monthly Comparison', align:'left', style:{ color:'#E8F0FF', fontSize:'16px', fontWeight:'700' }, margin:10 },
                credits:{ enabled:false }, colors:['#60a5fa','#f59e0b'],
                xAxis:{ categories: cats.map(ym => { const [y,m]=(ym||'').split('-'); return (y&&m)?new Date(y,m-1,1).toLocaleString('en',{month:'short'})+' '+String(y).slice(-2):ym; }),
                    lineColor:'rgba(255,255,255,.15)', tickColor:'rgba(255,255,255,.15)', labels:{ style:{ color:'#C7D2FE', fontSize:'13px', fontWeight:600 } } },
                yAxis:[{ title:{ text:'Value (SAR)', style:{ color:'#C7D2FE', fontWeight:700, fontSize:'13px' } }, min:0, gridLineColor:'rgba(255,255,255,.10)',
                    labels:{ style:{ color:'#E0E7FF', fontWeight:600, fontSize:'13px' }, formatter(){ return fmtCompactSAR(this.value); } } },
                    { title:{ text:'Percent (%)', style:{ color:'#F59E0B', fontWeight:700, fontSize:'13px' } }, opposite:true, min:0, gridLineColor:'transparent',
                        labels:{ style:{ color:'#FBBF24', fontWeight:600, fontSize:'12px' }, formatter(){ return fmtPct(this.value); } } }],
                legend:{ align:'center', itemStyle:{ color:'#E8F0FF', fontWeight:600, fontSize:'13px' } },
                tooltip:{ shared:false, useHTML:true, backgroundColor:'rgba(190,190,190,0.95)', borderColor:'#090909', borderRadius:8, style:{ color:'#050505', fontSize:'13px' },
                    formatter(){ const h=`<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                        const body = this.series.yAxis.opposite ? `${this.series.name}: <b>${fmtPct(this.y)}</b>` : `${this.series.name}: <b>SAR ${fmtCompactSAR(this.y)}</b>`;
                        return h + body; } },
                plotOptions:{
                    column:{ borderWidth:0, borderRadius:3, pointPadding:0.06, groupPadding:0.18,
                        dataLabels:{ enabled:true, rotation:-90, align:'center', verticalAlign:'bottom', inside:false, y:-10, crop:false, overflow:'none',
                            style:{ color:'#E8F0FF', fontSize:'15px' }, formatter(){ return this.y>0 ? `SAR ${fmtCompactSAR(this.y)}` : ''; } } },
                    spline:{ lineWidth:3, marker:{ enabled:true, radius:4, fillColor:'#fff', lineColor:'#f59e0b', lineWidth:2 },
                        dataLabels:{ enabled:true, y:-6, style:{ color:'#FBBF24', fontWeight:700, fontSize:'12px', textOutline:'2px rgba(0,0,0,.55)' }, formatter(){ return fmtPct(this.y); } } }
                },
                series:[ { type:'column', name:'Value (SAR)', data:vals }, { type:'spline', name:'MoM %', yAxis:1, data:mom, dashStyle:'ShortDot' } ]
            });

            // === Render status chart inside the switcher, then init monthly cards ===
            renderStatusMonthlyChart(payload.multiMonthly);
            initStatusKpiSwitcher(payload.multiMonthly);
            renderMonthlyProductCluster(payload.monthlyProductCluster);
        }

        // helpers
        function fmtCompactSAR(val){ if(val==null||isNaN(val)) return '';
            if(Math.abs(val)>=1_000_000_000) return (val/1_000_000_000).toFixed(1).replace(/\.0$/,'')+'B';
            if(Math.abs(val)>=1_000_000)     return (val/1_000_000).toFixed(1).replace(/\.0$/,'')+'M';
            if(Math.abs(val)>=1_000)         return (val/1_000).toFixed(1).replace(/\.0$/,'')+'K';
            return Number(val).toLocaleString(); }
        function fmtPct(v){ if(v==null||isNaN(v)) return ''; return Number(v).toFixed(1).replace(/\.0$/,'')+'%'; }

        // events
        $('#btnApply').on('click', loadKPIs);
        loadKPIs();

        // ===== status chart (for switcher) =====
        function renderStatusMonthlyChart(multiMonthly){
            const catsIso  = multiMonthly?.categories || [];
            const catsNice = catsIso.map(ym => {
                const [y,m]=String(ym||'').split('-'); if(!y||!m) return ym||'';
                return new Date(Number(y), Number(m)-1, 1).toLocaleString('en',{month:'short'}) + ' ' + String(y).slice(-2);
            });

            const barSeries = multiMonthly?.bars || [];

            // --- compute Accepted MoM % (unchanged) ---
            const acceptedBars = barSeries.find(s => String(s.name).toLowerCase() === 'accepted');
            const acceptedVals = acceptedBars?.data || [];
            const acceptedMoM = [];
            let prev = null;
            for (let i=0;i<acceptedVals.length;i++){
                const cur = Number(acceptedVals[i]||0);
                let pct = 0;
                if (prev!==null && prev>0){
                    pct = ((cur - prev)/prev)*100;
                    if (pct>300) pct=300; if (pct<-300) pct=-300;
                }
                acceptedMoM.push(Math.round(pct*10)/10);
                prev = cur;
            }

            // === PATCH: monthly totals across all STATUS columns ===
            const xCount = catsNice.length;
            const monthTotals = Array(xCount).fill(0);
            barSeries.forEach(s => {
                // safety: only sum column series
                if ((s.type || 'column') === 'column') {
                    for (let i=0;i<xCount;i++){
                        monthTotals[i] += Number(s.data?.[i] || 0);
                    }
                }
            });

            Highcharts.chart('kpi_status_monthly', {
                chart:{ backgroundColor:'transparent', spacing:[10,20,10,20] },
                title:{ text:null }, credits:{ enabled:false },
                colors:['#60a5fa','#8b5cf6','#34d399','#f59e0b','#fb7185','#94a3b8'],

                xAxis:{ categories:catsNice, lineColor:'rgba(255,255,255,.14)', tickColor:'rgba(255,255,255,.14)',
                    labels:{ style:{ color:'#C7D2FE', fontSize:'13px', fontWeight:600 } } },

                yAxis:[{
                    title:{ text:'Value (SAR)', style:{ color:'#C7D2FE', fontSize:'13px', fontWeight:700 } }, min:0,
                    gridLineColor:'rgba(255,255,255,.12)',
                    labels:{ style:{ color:'#E0E7FF', fontSize:'12px', fontWeight:600 },
                        formatter(){ return 'SAR ' + Highcharts.numberFormat(this.value,0); } }
                },{
                    title:{ text:'Accepted MoM (%)', style:{ color:'#F59E0B', fontSize:'13px', fontWeight:700 } },
                    opposite:true, min:0, gridLineColor:'transparent',
                    labels:{ style:{ color:'#FBBF24', fontWeight:600, fontSize:'12px' },
                        formatter(){ return Highcharts.numberFormat(this.value,1)+'%'; } }
                }],

                legend:{ itemStyle:{ color:'#E8F0FF', fontWeight:600, fontSize:'13px' } },

                tooltip:{
                    shared:true, useHTML:true, backgroundColor:'rgba(10,15,45,0.95)', borderColor:'#334155', borderRadius:8,
                    style:{ color:'#E8F0FF', fontSize:'13px' },
                    formatter(){
                        const h = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                        return h + this.points.map(p=>{
                            const isPercent = p.series.yAxis && p.series.yAxis.opposite; // MoM line
                            const val = isPercent
                                ? Highcharts.numberFormat(p.y||0,1)+'%'
                                : 'SAR ' + Highcharts.numberFormat(p.y||0,0);
                            return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${val}</b></div>`;
                        }).join('');
                    }
                },

                plotOptions:{
                    column:{
                        grouping:true, groupPadding:0.14, pointPadding:0.04, borderWidth:0, borderRadius:3,
                        // === PATCH: show % share inside each column ===
                        dataLabels:{
                            enabled:true,
                            inside:true,
                            style:{ color:'#E8F0FF', fontWeight:700, fontSize:'12px', textOutline:'1px rgba(0,0,0,.6)' },
                            formatter:function(){
                                const total = monthTotals[this.point.x] || 0;
                                if (!total) return '';
                                const pct = (this.y / total) * 100;
                                return Highcharts.numberFormat(pct, 1) + '%';
                            }
                        }
                    },
                    spline:{ lineWidth:3, marker:{ enabled:false },
                        dataLabels:{ enabled:true, y:-8,
                            style:{ color:'#FBBF24', fontWeight:700, fontSize:'12px', textOutline:'1px rgba(0,0,0,.45)' },
                            formatter(){ return Highcharts.numberFormat(this.y||0,1)+'%'; } }
                    }
                },

                series:[
                    ...barSeries,
                    { type:'spline', name:'Accepted MoM %', yAxis:1, data:acceptedMoM, dashStyle:'ShortDot', color:'#FBBF24' }
                ]
            });
        }




        // ===== cards + switcher logic (uses multiMonthly only) =====
        function initStatusKpiSwitcher(mv){
            const wrap = document.getElementById('kpiSwitcher');
            const panelChart = document.getElementById('panelChart');
            const panelCards = document.getElementById('panelCards');
            const btn = document.getElementById('kpiToggleBtn');
            if(!wrap || !panelChart || !panelCards || !btn) return;

            // ---- source (sparse) arrays from backend ----
            const srcMonths = mv?.categories || [];   // e.g. ["2025-01","2025-03","2025-04",...]
            const series    = mv?.bars || [];

            const arrFor = (name) =>
                series.find(s => String(s.name).toLowerCase() === name.toLowerCase())?.data || [];

            const aAccepted      = arrFor('Accepted');
            const aPreAcceptance = arrFor('Pre-Acceptance');
            const aWaiting       = arrFor('Waiting');
            const aRejected      = arrFor('Rejected');
            const aCancelled     = arrFor('Cancelled');
            const aUnknown       = arrFor('Unknown');

            // ---- map months -> values (so we can pad easily) ----
            const monthMap = {}; // { "YYYY-MM": {accepted, preacc, waiting, rejected, cancelled, unknown, total} }
            srcMonths.forEach((ym, i) => {
                const accepted  = Number(aAccepted[i]      || 0);
                const preacc    = Number(aPreAcceptance[i] || 0);
                const waiting   = Number(aWaiting[i]       || 0);
                const rejected  = Number(aRejected[i]      || 0);
                const cancelled = Number(aCancelled[i]     || 0);
                const unknown   = Number(aUnknown[i]       || 0);
                const total     = accepted + preacc + waiting + rejected + cancelled + unknown;
                monthMap[ym] = { accepted, preacc, waiting, rejected, cancelled, unknown, total };
            });

            // ---- decide the full set of months to show ----
            const selectedYear = ($('#fYear').val() || '').trim();
            const ymList = [];

            function monthsForYear(y){
                return Array.from({length:12}, (_,k)=> `${y}-${String(k+1).padStart(2,'0')}`);
            }
            function ymToDate(ym){ const [y,m]=ym.split('-').map(Number); return new Date(y, (m||1)-1, 1); }

            if (selectedYear) {
                // Jan..Dec for chosen year
                ymList.push(...monthsForYear(selectedYear));
            } else if (srcMonths.length) {
                // continuous range between min & max present in data
                const minYM = srcMonths.reduce((a,b)=> ymToDate(a) < ymToDate(b) ? a : b);
                const maxYM = srcMonths.reduce((a,b)=> ymToDate(a) > ymToDate(b) ? a : b);
                const d = ymToDate(minYM);
                const end = ymToDate(maxYM);
                while (d <= end) {
                    ymList.push(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`);
                    d.setMonth(d.getMonth()+1);
                }
            }

            // ---- build padded rows (zeros where missing) ----
            const rows = ymList.map(ym => {
                const v = monthMap[ym] || {accepted:0, preacc:0, waiting:0, rejected:0, cancelled:0, unknown:0, total:0};
                return { m: ym, ...v };
            });

            // compute MoM + conversion on the padded sequence
            let prevTotal = null;

            rows.forEach(r => {
                let momPct = 0;

                if (prevTotal !== null && prevTotal > 0) {
                    const diff = r.total - prevTotal;
                    momPct = (diff / prevTotal) * 100; // true MoM % change

                    // clamp to avoid insane spikes
                    if (momPct > 300) momPct = 300;
                    if (momPct < -300) momPct = -300;
                }

                // round to 1 decimal
                r.mom = Math.round(momPct * 10) / 10;

                // conversion rate (Accepted / Total)
                r.conv = r.total > 0 ? (r.accepted / r.total) * 100 : 0;

                // keep for next iteration
                prevTotal = r.total;
            });

            // ---- toggle logic (default = chart) ----
            let showing = 'chart';
            const setBtnLabel = () => { btn.textContent = (showing === 'chart') ? 'Show Cards' : 'Show Chart'; };
            const show = (mode) => {
                if(mode === showing) return;
                showing = mode;
                if(mode==='chart'){
                    panelChart.classList.add('kpi-panel--active');
                    panelCards.classList.remove('kpi-panel--active');
                }else{
                    panelCards.classList.add('kpi-panel--active');
                    panelChart.classList.remove('kpi-panel--active');
                    renderBoardIntoPanel(rows);   // render the full board when entering Cards
                }
                setBtnLabel();
            };
            setBtnLabel();

            // button toggles views
            btn.addEventListener('click', () => show(showing === 'chart' ? 'cards' : 'chart'));

            // clicking a column also switches to Cards
            const chart = Highcharts.charts.find(c => c && c.renderTo?.id === 'kpi_status_monthly');
            if(chart){
                chart.update({
                    plotOptions:{ column:{ cursor:'pointer', point:{ events:{ click(){ show('cards'); } } } } }
                }, false);
                chart.redraw();
            }

            // ---- board renderer (inside Cards panel) ----
            function renderBoardIntoPanel(allRows){
                const grid = document.getElementById('monthBoard');
                if(!grid) return;

                grid.innerHTML = allRows.map(r => {
                    const tiles = [
                        ['Accepted',           r.accepted,  'ok'],
                        ['Pre-Acceptance',     r.preacc,    'warn'],
                        ['Waiting',            r.waiting,   'warn'],
                        ['Rejected',           r.rejected,  'dang'],
                        ['Cancelled',          r.cancelled, 'dang'],
                        ['Total',              r.total,     ''],
                        ['Conversion (Value)', fmtPct(r.conv), (r.conv>=50?'ok':(r.conv>=25?'warn':'dang'))],
                        ['MoM Change',         (r.mom>=0?'+':'')+fmtPct(r.mom), r.mom>0?'ok':(r.mom<0?'dang':'')],
                    ].map(([label,val,tone]) => `
        <div class="month-kpi ${tone}">
          <div class="label">${label}</div>
          <div class="val">${formatMaybeSAR(val)}</div>
        </div>
      `).join('');

                    return `
        <div class="month-card">
          <div class="month-head">
            <div class="month-title">${toNiceMonth(r.m)}</div>
          </div>
          <div class="month-grid">${tiles}</div>
        </div>
      `;
                }).join('');
            }

            // helpers
            function toNiceMonth(ym){ if(!ym || ym.indexOf('-')<0) return ym||''; const [y,m]=ym.split('-').map(Number);
                return new Date(y,(m||1)-1,1).toLocaleString('en',{month:'short'})+' '+String(y).slice(-2); }
            function fmtPct(x){ return (Number(x)||0).toFixed(1).replace(/\.0$/,'')+'%'; }
            function formatMaybeSAR(v){
                if (typeof v === 'number') {
                    const a = Math.abs(v);
                    if (a >= 1e9) return 'SAR ' + (v/1e9).toFixed(1).replace(/\.0$/,'') + 'B';
                    if (a >= 1e6) return 'SAR ' + (v/1e6).toFixed(1).replace(/\.0$/,'') + 'M';
                    if (a >= 1e3) return 'SAR ' + (v/1e3).toFixed(1).replace(/\.0$/,'') + 'K';
                    return 'SAR ' + v.toLocaleString();
                }
                return String(v);
            }
        }


        function renderMonthlyProductCluster(mpc) {
            const catsIso  = mpc?.categories || [];
            const catsNice = catsIso.map(ym => {
                const [y,m] = String(ym||'').split('-');
                return (y && m)
                    ? new Date(Number(y), Number(m)-1, 1).toLocaleString('en', {month:'short'}) + ' ' + String(y).slice(-2)
                    : ym;
            });

            const seriesIn = mpc?.series || [];

            // ---- compute monthly totals across all product series ----
            const xCount = catsNice.length;
            const monthTotals = Array(xCount).fill(0);
            seriesIn.forEach(s => {
                if ((s.type || 'column') === 'column') {
                    for (let i = 0; i < xCount; i++) {
                        monthTotals[i] += Number(s.data?.[i] || 0);
                    }
                }
            });

            Highcharts.chart('hcProductClusterMonthly', {
                chart: { type: 'column', backgroundColor: 'transparent', spacing: [10,20,10,20] },
                title: { text: null }, credits: { enabled: false },

                colors: ['#60a5fa','#8b5cf6','#34d399','#f59e0b','#fb7185','#94a3b8','#f472b6','#22d3ee'],

                xAxis: {
                    categories: catsNice,
                    lineColor: 'rgba(255,255,255,.14)',
                    tickColor: 'rgba(255,255,255,.14)',
                    labels: { style: { color: '#C7D2FE', fontSize: '12px', fontWeight: 600 } }
                },
                yAxis: {
                    min: 0,
                    title: { text: 'Value (SAR)', style: { color: '#C7D2FE', fontSize: '13px', fontWeight: 700 } },
                    gridLineColor: 'rgba(255,255,255,.12)',
                    labels: {
                        style: { color: '#E0E7FF', fontSize: '12px', fontWeight: 600 },
                        formatter() { return 'SAR ' + Number(this.value).toLocaleString(); }
                    }
                },

                legend: { itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '12px' } },

                tooltip: {
                    shared: true, useHTML: true,
                    backgroundColor: 'rgba(10,15,45,0.95)', borderColor: '#334155', borderRadius: 8,
                    style: { color: '#E8F0FF', fontSize: '12px' },
                    formatter() {
                        const head = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                        const total = this.points.reduce((a,p)=>a+(p?.y||0),0);
                        const lines = this.points.map(p => {
                            const pct = total ? (p.y/total)*100 : 0;
                            return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>SAR ${Highcharts.numberFormat(p.y||0,0)}</b> <span style="opacity:.8">(${Highcharts.numberFormat(pct,1)}%)</span></div>`;
                        });
                        lines.push(`<div style="margin-top:4px;opacity:.9">Total: <b>SAR ${Highcharts.numberFormat(total,0)}</b></div>`);
                        return head + lines.join('');
                    }
                },

                plotOptions: {
                    column: {
                        grouping: true, groupPadding: 0.18, pointPadding: 0.06, borderWidth: 0, borderRadius: 3,
                        dataLabels: {
                            enabled: true,
                            inside: true,               // show inside the bar
                            crop: false, overflow: 'none',
                            style: { color: '#E8F0FF', fontWeight: 700, fontSize: '11px', textOutline: '1px rgba(0,0,0,.6)' },
                            formatter() {
                                const total = monthTotals[this.point.x] || 0;
                                if (!total) return '';
                                const pct = (this.y / total) * 100;
                                // hide tiny values
                                if (pct < 1) return '';   // tweak threshold if needed
                                return Highcharts.numberFormat(pct, 1) + '%';
                            }
                        }
                    }
                },

                series: seriesIn,

                lang: { noData: 'No data.' },
                noData: { style: { fontSize: '14px', color: '#E0E7FF', fontWeight: 600 } }
            });
        }


    })();
</script>

</body>
</html>
