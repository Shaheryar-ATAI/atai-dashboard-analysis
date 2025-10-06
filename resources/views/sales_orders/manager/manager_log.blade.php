{{-- resources/views/sales_orders/manager/manager_log.blade.php --}}
    <!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Sales Order Log — Manager</title>

    <link href="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.8/datatables.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/atai-theme.css') }}?v={{ filemtime(public_path('css/atai-theme.css')) }}">

    <style>
        .badge-total { font-weight:600; padding:.45rem .75rem; border-radius:12px }
        .btn-chip.btn-outline-primary.active { background:#198754; border-color:#198754; color:#fff }
        #toolbar .form-select-sm, #toolbar .form-control-sm { width:auto }
    </style>
</head>
<body>

@php $u = auth()->user(); @endphp
<nav class="navbar navbar-atai navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('projects.index') }}">
            <img src="{{ asset('images/atai-logo.png') }}" class="brand-logo me-2" alt="ATAI">
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
                @if(!empty($u->region)) · <small>{{ $u->region }}</small>@endif
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-logout btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">

    {{-- Filters like Inquiries --}}
    <div id="toolbar" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <select id="fYear" class="form-select form-select-sm">
            <option value="">All Years</option>
            @for($y=(int)date('Y');$y>=(int)date('Y')-6;$y--)
                <option value="{{ $y }}">{{ $y }}</option>
            @endfor
        </select>

        <select id="fMonth" class="form-select form-select-sm">
            <option value="">All Months</option>
            @for($m=1;$m<=12;$m++)
                <option value="{{ $m }}">{{ date('M', mktime(0,0,0,$m,1)) }}</option>
            @endfor
        </select>

        <input type="date" id="fFrom" class="form-control form-control-sm" placeholder="From">
        <input type="date" id="fTo" class="form-control form-control-sm" placeholder="To">

        <button id="btnApply" class="btn btn-primary btn-sm">Update</button>

        <div class="ms-auto d-flex gap-2">
            <span id="badgeCount"  class="badge-total text-bg-info">Total Sales-Order No: 0</span>
            <span id="badgeValue"  class="badge-total text-bg-primary">Total Sales-Order Value: SAR 0</span>
        </div>
    </div>

    {{-- Family chips --}}
    <div class="d-flex gap-2 mb-2 flex-wrap" id="familyChips">
        <button class="btn btn-sm btn-outline-primary btn-chip active" data-family="">All</button>
        <button class="btn btn-sm btn-outline-primary btn-chip" data-family="Ductwork">Ductwork</button>
        <button class="btn btn-sm btn-outline-primary btn-chip" data-family="Dampers">Dampers</button>
        <button class="btn btn-sm btn-outline-primary btn-chip" data-family="Sound Attenuators">Sound Attenuators</button>
        <button class="btn btn-sm btn-outline-primary btn-chip" data-family="Accessories">Accessories</button>
    </div>

    {{-- Status tabs --}}
    <ul class="nav nav-tabs mb-3" id="statusTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-status="" type="button">All</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="Accepted" type="button">Accepted</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="Pre-Acceptance" type="button">Pre-Acceptance</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="Rejected" type="button">Rejected</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-status="Waiting" type="button">Waiting</button>
        </li>
    </ul>

    {{-- Search & total (like Inquiries) --}}
    <div class="d-flex align-items-center gap-2 mb-2">
        <div class="input-group w-auto">
            <span class="input-group-text">Search</span>
            <input id="searchInput" type="text" class="form-control" placeholder="Project, client, location…">
        </div>
    </div>

    {{-- DataTable --}}
    <div class="card">
        <div class="card-body">
            <table id="dtSalesOrders" class="table table-striped w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>PO No</th>
                    <th>Date</th>
                    <th>Region</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Product</th>
                    <th class="text-end">Value with VAT</th>
                    <th class="text-end">PO Value</th>
                    <th>Status</th>
                    <th>Salesperson</th>
                    <th>Remarks</th>   <!-- NEW -->
                </tr>
                </thead>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const fmtSAR = (n)=>'SAR '+Number(n||0).toLocaleString();

    let currentFamily = '';
    let currentStatus = '';

    const $year  = $('#fYear');
    const $month = $('#fMonth');
    const $from  = $('#fFrom');
    const $to    = $('#fTo');

    // Family chips
    $('#familyChips').on('click', '.btn-chip', function(){
        $('#familyChips .btn-chip').removeClass('active');
        $(this).addClass('active');
        currentFamily = $(this).data('family') || '';
        dt.ajax.reload(null,false);
        refreshTotals();
    });

    // Status tabs
    $('#statusTabs').on('click', 'button[data-status]', function(){
        $('#statusTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status') || '';
        dt.ajax.reload(null,false);
        refreshTotals();
    });
    // Build filter payload (same spirit as Inquiries)
    function buildFilters(){
        return {
            year:  $year.val()  || '',
            month: $month.val() || '',
            from:  $from.val()  || '',
            to:    $to.val()    || '',
            family: currentFamily,
            status: currentStatus
        };
    }

    // Totals badge (count + sum)
    async function refreshTotals(){
        const qs = new URLSearchParams(buildFilters()).toString();
        const res = await fetch(`{{ route('salesorders.manager.kpis') }}?${qs}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        if(!res.ok) return;
        const j = await res.json();
        $('#badgeCount').text('Total Sales-Order No.: '+Number(j?.totals?.count||0).toLocaleString());
        $('#badgeValue').text('Total Sales-Order Value: '+fmtSAR(j?.totals?.value||0));
    }

    // Apply button
    $('#btnApply').on('click', ()=>{
        dt.ajax.reload(null,false);
        refreshTotals();
    });

    // DataTable
    const dt = $('#dtSalesOrders').DataTable({
        processing: true,
        serverSide: true,
        order: [[1,'desc']], // sort by PO No (change if you prefer)
        ajax: {
            url: '{{ route('salesorders.manager.datatable') }}',
            data: d => Object.assign(d, buildFilters())
        },
        columns: [
            { data: 'DT_RowIndex', title:'#', orderable:false, searchable:false },
            { data: 'po_no',         title:'PO No' },
            { data: 'date_rec',      title:'Date' },
            { data: 'region',        title:'Region' },
            { data: 'client_name',   title:'Client' },
            { data: 'project_name',  title:'Project' },
            { data: 'product_family',title:'Product' },
            { data: 'value_with_vat',title:'Value with VAT', className:'text-end', render:d=>'SAR '+Number(d||0).toLocaleString() },
            { data: 'po_value',      title:'PO Value',       className:'text-end', render:d=>'SAR '+Number(d||0).toLocaleString() },
            { data: 'status',        title:'Status' },
            { data: 'remarks',       title:'Remarks' },
            { data: 'salesperson',   title:'Salesperson' }
        ]
    });

    // Global search
    $('#searchInput').on('input', e=> dt.search(e.target.value).draw());

    // First load totals
    refreshTotals();
</script>
</body>
</html>
