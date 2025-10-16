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
        {{-- Brand (left) --}}
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" alt="ATAI" class="brand-logo me-2">
            <span class="brand-word">ATAI</span>
        </a>

        {{-- Toggler (mobile) --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ataiNav"
                aria-controls="ataiNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Collapse --}}
        <div class="collapse navbar-collapse" id="ataiNav">
            {{-- Centered nav (desktop); scrollable row (mobile) --}}
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}"
                       href="{{ route('projects.index') }}">Quotation KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs(['inquiries.index']) ? 'active' : '' }}"
                       href="{{ route('inquiries.index') }}">Quotation Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.kpi') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.kpi') }}">Sales Order Log KPI</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.manager.index') ? 'active' : '' }}"
                       href="{{ route('salesorders.manager.index') }}">Sales Order Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('estimation.*') ? 'active' : '' }}"
                       href="{{ route('estimation.index') }}">Estimation</a>
                </li>

                @hasanyrole('gm|admin')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salesorders.*') ? 'active' : '' }}"
                       href="{{ route('salesorders.index') }}">Sales Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.area*') ? 'active' : '' }}"
                       href="{{ route('performance.area') }}">Area summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.salesman*') ? 'active' : '' }}"
                       href="{{ route('performance.salesman') }}">SalesMan summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('performance.product*') ? 'active' : '' }}"
                       href="{{ route('performance.product') }}">Product summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                       href="{{ route('powerbi.jump') }}">Accounts Summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('powerbi.jump') ? 'active' : '' }}"
                       href="{{ route('powerbi.jump') }}">Power BI Dashboard</a>
                </li>
                @endhasanyrole
            </ul>

            {{-- Right block (far-right on desktop; full-width row on mobile) --}}
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


    </div>
{{--    <div class="d-flex justify-content-end gap-2 my-3 flex-wrap">--}}
{{--        <span id="badgeCount" class="badge-total text-bg-info">Total Sales-Order No: 0</span>--}}
{{--        <span id="badgeTotal" class="badge-total text-bg-primary">Total Sales-Order Value: SAR 0</span>--}}
{{--    </div>--}}



    <div class="row g-3 mb-4 text-center justify-content-center">
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                {{--                <div class="kpi-label">Total Quotation Value </div>--}}
                <div id="badgeCount"   class="kpi-value">SAR 0</div>
            </div>
        </div>
        <div class="col-6 col-md col-lg">
            <div class="kpi-card shadow-sm p-5 h-150">
                {{--                <div class="kpi-label">Total Quotation Count</div>--}}
                <div id="badgeTotal" class="kpi-value">0</div>
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
        <div class="col-12 col-xl-6">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Orders Value Comparison</div>
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
                    <div class="fw-semibold mb-2"> </div>
                    <div id="hcMonthly" class="hc"></div>
                    <div id="barPoValueByArea" class="hc" style="height: 100px"></div>
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
            if (['ductwork', 'ductworks', 'round duct', 'round ducts'].includes(n)) return 'Ductwork';
            if (['damper', 'dampers', 'fire/smoke dampers', 'fire /smoke dampers'].includes(n)) return 'Dampers';
            if (['sound attenuator', 'sound attenuators', 'sound attenuator(s)'].includes(n)) return 'Sound Attenuators';
            if (['accessory', 'accessories'].includes(n)) return 'Accessories';
            return '';
        }

        // Map display label back to DB-facing value (if your DB uses slightly different forms, edit here)
        function displayToDbFamily(display = '') {
            switch (display) {
                case 'Ductwork':
                    return 'Ductwork';
                case 'Dampers':
                    return 'Dampers';
                case 'Sound Attenuators':
                    return 'Sound Attenuators';
                case 'Accessories':
                    return 'Accessories';
                default:
                    return '';
            }
        }

        // Build filters for the API call
        function filters() {
            const f = {
                year: $('#fYear').val() || '',
                month: $('#fMonth').val() || '',
                from: $('#fFrom').val() || '',
                to: $('#fTo').val() || ''
            };
            const fam = displayToDbFamily(currentFamily);
            if (fam) f.family = fam;
            if (currentStatus) f.status = currentStatus; // Accepted / Pre-Acceptance / Waiting / Rejected
            return f;
        }

        // Generic chip builder (shared by family & status)
        function buildChips(containerId, items, activeVal, onChange) {
            const el = document.getElementById(containerId);
            if (!el) return;

            const html = [
                `<button type="button" class="btn btn-sm ${!activeVal ? 'btn-primary' : 'btn-outline-primary'}" data-val="">All</button>`,
                ...items.map(name =>
                    `<button type="button" class="btn btn-sm ${activeVal === name ? 'btn-primary' : 'btn-outline-primary'}" data-val="${name}">${name}</button>`
                )
            ].join('');
            el.innerHTML = html;

            el.onclick = (e) => {
                const btn = e.target.closest('button[data-val]');
                if (!btn) return;
                el.querySelectorAll('button').forEach(b => b.classList.replace('btn-primary', 'btn-outline-primary'));
                btn.classList.replace('btn-outline-primary', 'btn-primary');
                onChange(btn.getAttribute('data-val') || '');
            };
        }

        async function loadKPIs() {
            const qs = new URLSearchParams(filters()).toString();
            const res = await fetch(`{{ route('salesorders.manager.kpis') }}?${qs}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
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
                : ['Accepted', 'Pre-Acceptance', 'Waiting', 'Rejected'];

            buildChips('statusChips', statusList, currentStatus, (val) => {
                currentStatus = val;            // exact status text
                loadKPIs();
            });

            // ----- Chart 1: Area + Status (you were rendering multiMonthly here; keep as you had) -----
            (function () {
                const catsIso = payload.multiMonthly?.categories || [];
                const catsNice = catsIso.map(ym => {
                    const [y, m] = String(ym || '').split('-');
                    if (!y || !m) return ym || '';
                    return new Date(Number(y), Number(m) - 1, 1).toLocaleString('en', {month: 'short'}) + ' ' + String(y).slice(-2);
                });
                const barSeries = payload.multiMonthly?.bars || [];
                const lineSeries = payload.multiMonthly?.lines || [];

                Highcharts.chart('hcArea', {
                    chart: {
                        backgroundColor: 'transparent',
                        spacing: [10, 20, 10, 20]
                    },
                    title: { text: null },
                    credits: { enabled: false },

                    colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185'], // columns first, then lines if any

                    xAxis: {
                        categories: catsNice,
                        lineColor: 'rgba(255,255,255,.14)',
                        tickColor: 'rgba(255,255,255,.14)',
                        labels: { style: { color: '#C7D2FE', fontSize: '13px', fontWeight: 600 } }
                    },

                    yAxis: [{
                        title: { text: 'Value (SAR)', style: { color: '#C7D2FE', fontSize: '13px', fontWeight: 700 } },
                        min: 0,
                        gridLineColor: 'rgba(255,255,255,.12)',
                        labels: {
                            style: { color: '#E0E7FF', fontSize: '12px', fontWeight: 600 },
                            formatter() { return fmtSAR(this.value); }
                        }
                    }],

                    legend: {
                        align: 'center',
                        itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                        itemHoverStyle: { color: '#FFFFFF' }
                    },

                    tooltip: {
                        shared: true,
                        useHTML: true,
                        backgroundColor: 'rgba(10,15,45,0.95)',
                        borderColor: '#334155',
                        borderRadius: 8,
                        style: { color: '#E8F0FF', fontSize: '13px' },
                        formatter() {
                            const header = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                            const lines = this.points.map(p => {
                                const val = p.series.type === 'spline' ? p.y.toLocaleString() : fmtSAR(p.y);
                                return `<div><span style="color:${p.color}">●</span> ${p.series.name}: <b>${val}</b></div>`;
                            });
                            return header + lines.join('');
                        }
                    },

                    plotOptions: {
                        column: {
                            grouping: true,
                            groupPadding: 0.14,
                            pointPadding: 0.04,
                            borderWidth: 0,
                            borderRadius: 3,
                            states: { hover: { brightness: 0.08 } },
                            dataLabels: {
                                enabled: true,
                                rotation: -90,
                                align: 'center',
                                verticalAlign: 'bottom',
                                inside: false,
                                y: -10,
                                crop: false,
                                overflow: 'none',
                                style: {
                                    color: '#E8F0FF',
                                    fontWeight: 700,
                                    fontSize: '12px',
                                    textOutline: '2px rgba(0,0,0,.7)'
                                },
                                formatter() { return this.y ? fmtCompactSAR(this.y) : ''; }
                            }
                        },

                        spline: {
                            lineWidth: 3,
                            marker: { enabled: true, radius: 4, fillColor: '#fff', lineWidth: 2 },
                            dataLabels: {
                                enabled: true,
                                y: -8,
                                style: {
                                    color: '#FBBF24',            // readable warm accent for lines
                                    fontWeight: 700,
                                    fontSize: '12px',
                                    textOutline: '1px rgba(0,0,0,.45)'
                                },
                                formatter() { return fmtCompactSAR(this.y); }
                            }
                        }
                    },

                    // Use your prepared series arrays
                    series: [...barSeries, ...lineSeries]
                });

            })();

            function fmtCompactSAR(val) {
                if (val == null || isNaN(val)) return '';
                if (Math.abs(val) >= 1_000_000_000) return (val / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'B';
                if (Math.abs(val) >= 1_000_000) return (val / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
                if (Math.abs(val) >= 1_000) return (val / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
                return val.toLocaleString();
            }

// optional: percent formatter with one decimal, trims .0
            function fmtPct(v) {
                if (v == null || isNaN(v)) return '';
                return Number(v).toFixed(1).replace(/\.0$/, '') + '%';
            }

            // ----- Chart 2: Status Pie (value) -----
            Highcharts.chart('hcStatus', {
                chart: {
                    type: 'pie',
                    backgroundColor: 'transparent',
                    spacing: [10, 10, 10, 10]
                },
                title: { text: null },
                credits: { enabled: false },

                colors: ['#60a5fa', '#8b5cf6', '#34d399', '#f59e0b', '#fb7185'],

                tooltip: {
                    useHTML: true,
                    backgroundColor: 'rgba(10,15,45,0.95)',
                    borderColor: '#334155',
                    borderRadius: 8,
                    style: { color: '#E8F0FF', fontSize: '13px' },
                    pointFormatter() {
                        return `<b>SAR ${fmtCompactSAR(this.y)}</b>`;
                    }
                },

                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        size: '80%',
                        borderWidth: 0,
                        shadow: false,
                        dataLabels: {
                            enabled: true,
                            distance: 18,
                            softConnector: true,
                            connectorWidth: 1.2,
                            connectorColor: 'rgba(255,255,255,0.35)',
                            style: {
                                color: '#E8F0FF',
                                fontWeight: 400,
                                fontSize: '14px',
                                textOutline: '2px rgba(0,0,0,0.6)'
                            },
                            format: '{point.name}: {point.percentage:.1f}%'
                        }
                    }
                },

                legend: {
                    enabled: true,
                    itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                    itemHoverStyle: { color: '#FFFFFF' }
                },

                series: [{ name: 'Value', data: payload.statusPie || [] }],

                lang: { noData: 'No status values.' },
                noData: {
                    style: { fontSize: '14px', color: '#E0E7FF', fontWeight: 600 }
                }
            });


            // ----- Chart 3: Monthly value + MoM % -----
            const cats = payload.monthly?.categories || [];
            const vals = payload.monthly?.values || [];
            const mom = vals.map((v, i) => i === 0 || !vals[i - 1] ? 0 : Math.round(((v - vals[i - 1]) / vals[i - 1]) * 10000) / 100);

            Highcharts.chart('hcMonthly', {
                chart: {
                    backgroundColor: 'transparent',
                    spacing: [10, 20, 10, 20]
                },
                title: {
                    text: 'Sales Order Monthly Comparison',
                    align: 'left',
                    style: { color: '#E8F0FF', fontSize: '16px', fontWeight: '700' },
                    margin: 10
                },
                credits: { enabled: false },

                colors: ['#60a5fa', '#f59e0b'], // column, spline

                xAxis: {
                    categories: cats.map(ym => {
                        const [y, m] = (ym || '').split('-');
                        return (y && m)
                            ? new Date(y, m - 1, 1).toLocaleString('en', { month: 'short' }) + ' ' + String(y).slice(-2)
                            : ym;
                    }),
                    lineColor: 'rgba(255,255,255,.15)',
                    tickColor: 'rgba(255,255,255,.15)',
                    labels: { style: { color: '#C7D2FE', fontSize: '13px', fontWeight: 600 } }
                },

                yAxis: [
                    {
                        title: { text: 'Value (SAR)', style: { color: '#C7D2FE', fontWeight: 700, fontSize: '13px' } },
                        min: 0,
                        gridLineColor: 'rgba(255,255,255,.10)',
                        labels: {
                            style: { color: '#E0E7FF', fontWeight: 600, fontSize: '13px' },
                            formatter() { return fmtCompactSAR(this.value); }
                        }
                    },
                    {
                        title: { text: 'Percent (%)', style: { color: '#F59E0B', fontWeight: 700, fontSize: '13px' } },
                        opposite: true,
                        min: 0,
                        gridLineColor: 'transparent',
                        labels: {
                            style: { color: '#FBBF24', fontWeight: 600, fontSize: '12px' },
                            formatter() { return fmtPct(this.value); }
                        }
                    }
                ],

                legend: {
                    align: 'center',
                    itemStyle: { color: '#E8F0FF', fontWeight: 600, fontSize: '13px' },
                    itemHoverStyle: { color: '#FFFFFF' }
                },

                tooltip: {
                    shared: false,
                    useHTML: true,
                    backgroundColor: 'rgba(190,190,190,0.95)',
                    borderColor: '#090909',
                    borderRadius: 8,
                    style: { color: '#050505', fontSize: '13px' },
                    formatter() {
                        const head = `<div style="font-weight:700;margin-bottom:4px">${this.x}</div>`;
                        const body = this.series.yAxis.opposite
                            ? `${this.series.name}: <b>${fmtPct(this.y)}</b>`
                            : `${this.series.name}: <b>SAR ${fmtCompactSAR(this.y)}</b>`;
                        return head + body;
                    }
                },

                plotOptions: {
                    column: {
                        borderWidth: 0,
                        borderRadius: 3,
                        pointPadding: 0.06,
                        groupPadding: 0.18,
                        states: { hover: { brightness: 0.08 } },
                        dataLabels: {
                            enabled: true,
                            rotation: -90,
                            align: 'center',
                            verticalAlign: 'bottom',
                            inside: false,
                            y: -10,
                            crop: false,
                            overflow: 'none',
                            style: {
                                color: '#E8F0FF',

                                fontSize: '15px',
                            },
                            formatter() { return this.y > 0 ? `SAR ${fmtCompactSAR(this.y)}` : ''; }
                        }
                    },
                    spline: {
                        lineWidth: 3,
                        marker: { enabled: true, radius: 4, fillColor: '#fff', lineColor: '#f59e0b', lineWidth: 2 },
                        dataLabels: {
                            enabled: true,
                            y: -6,
                            style: {
                                color: '#FBBF24',
                                fontWeight: 700,
                                fontSize: '12px',
                                textOutline: '2px rgba(0,0,0,.55)'
                            },
                            formatter() { return fmtPct(this.y); }
                        }
                    }
                },

                series: [
                    { type: 'column', name: 'Value (SAR)', data: vals },
                    { type: 'spline', name: 'MoM %', yAxis: 1, data: mom, dashStyle: 'ShortDot' }
                ]
            });

        }

        $('#btnApply').on('click', loadKPIs);
        loadKPIs(); // initial
    })();
</script>

</body>
</html>
