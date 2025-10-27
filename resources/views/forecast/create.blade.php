{{-- resources/views/forecast/entry.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    {{-- DataTables assets kept out (not needed here). Kept Bootstrap Icons + Bootstrap + ATAI theme to match projects/index --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ATAI — Monthly Sales Forecast</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        /* page-specific polish (aligned to the projects page spacings) */
        .section-card { border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; }
        .section-card .card-header { background: rgba(255,255,255,.06); border-bottom: 1px solid rgba(255,255,255,.08); }
        .section-card .card-body { padding: 1rem 1rem; }
        .glass-row { backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; padding: .75rem 1rem; background: rgba(255,255,255,.04); }
        .glass-panel { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); }
        .kpi-card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem; }
        .kpi-card .kpi-value { font-weight: 700; font-size: clamp(1.1rem, 1.8vw, 1.5rem); }
        table.table tfoot th, table.table tfoot td { background: rgba(255,255,255,.03); }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>

@php $u = auth()->user(); @endphp
<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        {{-- Brand --}}
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

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('forecast.*') ? 'active' : '' }}" href="{{ route('forecast.create') }}">
                        Forecast
                    </a>
                </li>
            </ul>

            <div class="navbar-right">
                <div class="navbar-text me-2">
                    Logged in as <strong>{{ $u->name ?? '' }}</strong>
                    @if(!empty($u->region)) · <small>{{ $u->region }}</small> @endif
                </div>
                <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                    <button class="btn btn-logout btn-sm" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 forecast-page">
    {{-- Top header row (same vibe as projects) --}}
    <div class="glass-row d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h2 class="mb-2 mb-md-0 fw-bold text-light">Monthly Sales Forecast & Sales Order Targets</h2>
        <div class="d-flex align-items-center gap-2">
            <label class="small text-uppercase text-secondary mb-0">Submission Date</label>
            <input type="date" class="form-control form-control-sm bg-dark text-light border-0"
                   name="__submission_stub" disabled value="{{ now()->toDateString() }}">
        </div>
    </div>

    {{-- KPI glance (kept minimal to match dashboard look) --}}
    <div class="row g-3 mb-4 text-center justify-content-start">
        <div class="col-6 col-md-6">
            <div class="kpi-card p-4">
                <div class="kpi-label text-secondary small text-uppercase">Region</div>
                <div id="kpiRegion" class="kpi-value">{{ $u->region ?? '—' }}</div>
            </div>
        </div>
        <div class="col-6 col-md-6">
            <div class="kpi-card p-4">
                <div class="kpi-label text-secondary small text-uppercase">This Month Total </div>
                <div id="kpiTotal" class="kpi-value">SAR 0</div>
            </div>
        </div>
    </div>

    {{-- FORM --}}
    @if ($errors->any())
        <div class="alert alert-danger glass-panel p-3">
            <strong>Fix the following:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form id="forecastForm" method="post" action="{{ route('forecast.save') }}">
        @csrf

        {{-- Top three blocks in a single row (Sales Data / Forecasting Criteria / Forecast Data) --}}
        <div class="row g-3 mb-3">

            {{-- Sales Data --}}
            <div class="col-12 col-xl-4">
                <div class="card section-card h-100">
                    <div class="card-header"><strong>Sales Data</strong></div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Region</label>
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach (['Eastern','Central','Western'] as $r)
                                    <input class="btn-check" type="radio" name="region"  id="region-{{ $r }}" value="{{ $r }}"
                                        {{ (auth()->user()->region ?? '')===$r ? 'checked':'' }}>
                                    <label class="btn btn-sm btn-outline-light rounded-pill px-3" for="region-{{ $r }}">{{ $r }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-uppercase">Month</label>
                                <select name="sales[month]" class="form-select form-select-sm">
                                    @foreach([1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'] as $k=>$v)
                                        <option value="{{ $k }}" {{ $k===now()->month ? 'selected':'' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-uppercase">Year</label>
                                <select name="year" class="form-select form-select-sm">
                                    @php $y=now()->year; @endphp
                                    @for($i=$y-1;$i<=$y+2;$i++)
                                        <option value="{{ $i }}" {{ $i===$y?'selected':'' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label small text-uppercase">Issued By</label>
                                <input name="sales[issued_by]" class="form-control form-control-sm" value="{{ auth()->user()->name ?? '' }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-uppercase">Issued Date</label>
                                <input type="date" name="sales[issued_date]" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Forecasting Criteria --}}
            <div class="col-12 col-xl-4">
                <div class="card section-card h-100">
                    <div class="card-header"><strong>Forecasting Criteria</strong></div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Price Agreed</label>
                            <input name="criteria[price_agreed]" class="form-control form-control-sm" placeholder="Yes / No / %">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Consultant Approval</label>
                            <input name="criteria[consultant_approval]" class="form-control form-control-sm" placeholder="Approved / Pending">
                        </div>
                        <div class="">
                            <label class="form-label small text-uppercase">Percentage</label>
                            <input name="criteria[percentage]" class="form-control form-control-sm" placeholder="e.g. 60%">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Forecast Data --}}
            <div class="col-12 col-xl-4">
                <div class="card section-card h-100">
                    <div class="card-header"><strong>Forecast Data</strong></div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Month Target</label>
                            <input name="forecast[month_target]" class="form-control form-control-sm" placeholder="SAR 0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Required Turn-over</label>
                            <input name="forecast[required_turnover]" class="form-control form-control-sm" placeholder="SAR 0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-uppercase">Required Forecast</label>
                            <input name="forecast[required_forecast]" class="form-control form-control-sm" placeholder="SAR 0">
                        </div>
                        <div class="">
                            <label class="form-label small text-uppercase">Conversion Ratio</label>
                            <input name="forecast[conversion_ratio]" class="form-control form-control-sm" placeholder="e.g. 20%">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- A) New Orders --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>A) New Orders Expected This Month</strong>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-light" type="button" id="addRowA">+ Row</button>
                    <button class="btn btn-sm btn-outline-light" type="button" id="add10RowA">+10</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="tblA">
                        <thead class="table-dark" style="--bs-table-bg: rgba(255,255,255,.06);">
                        <tr>
                            <th style="width:44px;">#</th>
                            <th>Customer Name</th>
                            <th>Products</th>
                            <th>Project Name</th>
                            <th class="text-end" style="width:160px;">Value (SAR)</th>
                            <th style="width:220px;">Remarks</th>
                            <th style="width:40px;"></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                        <tr>
                            <th></th>
                            <th colspan="3" class="text-end text-uppercase small opacity-75">Total New Current Month Forecasted Orders</th>
                            <th class="text-end">
                                <span id="totalA" class="fw-bold">SAR 0</span>
                                <input type="hidden" name="totals[a]" id="totalAHidden" value="0">
                            </th>
                            <th colspan="2"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- B) Carry-Over --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>B) Carry-Over (from the previous month and expected to close in the current month)</strong>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-light" type="button" id="addRowB">+ Row</button>
                    <button class="btn btn-sm btn-outline-light" type="button" id="add5RowB">+5</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="tblB">
                        <thead class="table-dark" style="--bs-table-bg: rgba(255,255,255,.06);">
                        <tr>
                            <th style="width:44px;">#</th>
                            <th>Customer Name</th>
                            <th>Products</th>
                            <th>Project Name</th>
                            <th class="text-end" style="width:160px;">Value (SAR)</th>
                            <th style="width:220px;">Remarks</th>
                            <th style="width:40px;"></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                        <tr>
                            <th></th>
                            <th colspan="3" class="text-end text-uppercase small opacity-75">Total Carry-over</th>
                            <th class="text-end">
                                <span id="totalB" class="fw-bold">SAR 0</span>
                                <input type="hidden" name="totals[b]" id="totalBHidden" value="0">
                            </th>
                            <th colspan="2"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

{{--        --}}{{-- C) Quotations --}}
{{--        <div class="card section-card mb-4">--}}
{{--            <div class="card-header"><strong>C) Quotations</strong></div>--}}
{{--            <div class="card-body">--}}
{{--                <div class="row g-3">--}}
{{--                    <div class="col-12 col-md-6 col-xxl-4">--}}
{{--                        <label class="form-label small text-uppercase">Current Month’s Quotation Number</label>--}}
{{--                        <input name="quotations[number]" class="form-control form-control-sm" placeholder="e.g. 20970">--}}
{{--                    </div>--}}
{{--                    <div class="col-12 col-md-6 col-xxl-4">--}}
{{--                        <label class="form-label small text-uppercase">Current Month’s Quotation Value (SAR)</label>--}}
{{--                        <input name="quotations[value]" class="form-control form-control-sm" placeholder="SAR 0">--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

        {{-- Actions --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-5">
            <div class="small text-secondary">
                <i class="bi bi-info-circle me-1"></i>
                Tip: paste from Excel directly into the table (Customer | Products | Project | Value | Remarks).
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success fw-semibold" type="submit" name="action" value="save">
                    <i class="bi bi-save2 me-1"></i> Save
                </button>
                <button class="btn btn-outline-info" type="submit" formmethod="GET" formaction="{{ route('forecast.pdf') }}" formtarget="_blank" name="action" value="pdf">
                    <i class="bi bi-filetype-pdf me-1"></i> Generate PDF
                </button>
            </div>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
    (function(){
        const tblA = document.querySelector('#tblA tbody');
        const tblB = document.querySelector('#tblB tbody');
        const totalA = document.getElementById('totalA');
        const totalB = document.getElementById('totalB');
        const totalAHidden = document.getElementById('totalAHidden');
        const totalBHidden = document.getElementById('totalBHidden');
        const kpiTotal = document.getElementById('kpiTotal');

        function fmt(n){ n=Number(String(n).replace(/[^\d.-]/g,''))||0; return 'SAR '+ n.toLocaleString(); }
        function parseN(v){ v=String(v||'').replace(/[^\d.-]/g,''); const n=Number(v); return isFinite(n)?n:0; }

        function makeRow(section, idx){
            const name = section === 'A' ? 'new_orders' : 'carry_over';
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td class="serial text-secondary">${idx+1}</td>
            <td><input name="${name}[${idx}][customer_name]" class="form-control form-control-sm"></td>
            <td><input name="${name}[${idx}][products]" class="form-control form-control-sm"></td>
            <td><input name="${name}[${idx}][project_name]" class="form-control form-control-sm"></td>
            <td class="text-end"><input name="${name}[${idx}][value_sar]" class="form-control form-control-sm text-end value"></td>
             <td><input name="${name}[${idx}][remarks]" class="form-control form-control-sm"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger del"><i class="bi bi-x-lg"></i></button></td>
        `;
            return tr;
        }

        function addRows(tbody, section, count){
            const start = tbody.children.length;
            for(let i=0;i<count;i++){
                tbody.appendChild(makeRow(section, start+i));
            }
            renumber(tbody);
            recalc(section);
        }

        function renumber(tbody){
            Array.from(tbody.children).forEach((tr,i)=>{
                tr.querySelector('.serial').textContent = i+1;
            });
        }

        function recalc(section){
            let sumA = 0, sumB = 0;
            tblA.querySelectorAll('.value').forEach(inp=> sumA += parseN(inp.value));
            tblB.querySelectorAll('.value').forEach(inp=> sumB += parseN(inp.value));

            totalA.textContent = fmt(sumA);
            totalB.textContent = fmt(sumB);
            totalAHidden.value = Math.round(sumA);
            totalBHidden.value = Math.round(sumB);

            kpiTotal.textContent = fmt(sumA + sumB);
        }

        function bindTable(tbody, section){
            tbody.addEventListener('input', e=>{
                if(e.target.classList.contains('value')) recalc(section);
            });
            tbody.addEventListener('click', e=>{
                const btn = e.target.closest('.del');
                if(!btn) return;
                btn.closest('tr').remove();
                renumber(tbody);
                recalc(section);
            });
            // Excel paste
            tbody.addEventListener('paste', e=>{
                const t = e.target;
                if(!['INPUT','TEXTAREA'].includes(t.tagName)) return;
                const clip = (e.clipboardData||window.clipboardData).getData('text');
                if(!clip || clip.indexOf('\t')===-1) return;
                e.preventDefault();

                const rows = clip.split(/\r?\n/).filter(r=>r.trim().length);
                const need = Math.max(0, (tbody.children.length - (Array.from(tbody.children).indexOf(t.closest('tr')) + rows.length)));
                if(need>0) addRows(tbody, section, need);

                const startIdx = Array.from(tbody.children).indexOf(t.closest('tr'));
                rows.forEach((row, r)=>{
                    const cols = row.split('\t');
                    const tr = tbody.children[startIdx + r];
                    if(!tr) return;
                    const inputs = tr.querySelectorAll('input');
                    // order: customer, products, project, value, remarks
                    if(inputs[0]) inputs[0].value = cols[0] ?? inputs[0].value;
                    if(inputs[1]) inputs[1].value = cols[1] ?? inputs[1].value;
                    if(inputs[2]) inputs[2].value = cols[2] ?? inputs[2].value;
                    if(inputs[3]) inputs[3].value = cols[3] ?? inputs[3].value;
                    if(inputs[4]) inputs[4].value = cols[4] ?? inputs[4].value;
                });
                recalc(section);
            });
        }

        document.getElementById('addRowA').addEventListener('click', ()=>addRows(tblA,'A',1));
        document.getElementById('add10RowA').addEventListener('click',()=>addRows(tblA,'A',10));
        document.getElementById('addRowB').addEventListener('click', ()=>addRows(tblB,'B',1));
        document.getElementById('add5RowB').addEventListener('click', ()=>addRows(tblB,'B',5));

        bindTable(tblA,'A');
        bindTable(tblB,'B');

        // Seed initial rows to mirror the sheet (10 & 5)
        addRows(tblA,'A',10);
        addRows(tblB,'B',5);
    })();
</script>
</body>
</html>
