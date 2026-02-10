@extends('layouts.app')

@section('title', 'Forecast List')

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme-20260210.css') }}?v={{ time() }}">
        <style>
        .forecast-list .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e5e7eb;
            --bs-table-striped-bg: rgba(255, 255, 255, .04);
            --bs-table-striped-color: #e5e7eb;
            --bs-table-hover-bg: rgba(255, 255, 255, .06);
            --bs-table-hover-color: #ffffff;
            border-color: rgba(255, 255, 255, .12);
        }
        .forecast-list .table thead th {
            color: #f9fafb;
            border-color: rgba(255, 255, 255, .2);
        }
        .forecast-list .table td, .forecast-list .table th {
            border-color: rgba(255, 255, 255, .12);
        }
        .forecast-list .table tbody td {
            color: #e5e7eb;
        }
        .forecast-list .table tbody td a {
            color: #e5e7eb;
        }
        .section-card {
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
        }
        .section-card .card-header {
            background: rgba(255, 255, 255, .06);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }
        .glass-row {
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
            padding: .75rem 1rem;
            background: rgba(255, 255, 255, .04);
        }
        .kpi-card {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 1rem;
        }
        .kpi-card .kpi-value {
            font-weight: 700;
            font-size: clamp(1.05rem, 1.6vw, 1.4rem);
        }
        .table thead th { white-space: nowrap; }
        .table tfoot th, .table tfoot td {
            background: rgba(255, 255, 255, .03);
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
    @php
        $monthNo = (int)($month_no ?? now()->month);
        $yearVal = (int)($year ?? now()->year);
        $monthName = $month ?? date('F', mktime(0, 0, 0, $monthNo, 1));
        $years = [];
        $cy = now()->year;
        for ($y = $cy + 1; $y >= $cy - 3; $y--) { $years[] = $y; }
    @endphp

    <main class="container-fluid py-4 forecast-page forecast-list">
        <div class="glass-row d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div>
                <h2 class="mb-1 fw-bold text-light">Forecast List</h2>
                <div class="text-secondary small">{{ $region ?: '-' }} • {{ $salesman ?: '-' }}</div>
            </div>

            <div class="d-flex flex-wrap align-items-end gap-2">
                <form method="GET" action="{{ route('forecast.list') }}" class="d-flex flex-wrap align-items-end gap-2">
                    <div>
                        <label class="small text-uppercase text-secondary mb-0">Month</label>
                        <select name="month_no" class="form-select form-select-sm bg-dark text-light border-0">
                            @for($m = 1; $m <= 12; $m++)
                                @php $label = \Carbon\Carbon::create()->month($m)->format('F'); @endphp
                                <option value="{{ $m }}" @if($m === $monthNo) selected @endif>{{ $label }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="small text-uppercase text-secondary mb-0">Year</label>
                        <select name="year" class="form-select form-select-sm bg-dark text-light border-0">
                            @foreach($years as $y)
                                <option value="{{ $y }}" @if($y === $yearVal) selected @endif>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                    </div>
                </form>

                <form method="GET" action="{{ route('forecast.pdf.saved') }}" target="_blank">
                    <input type="hidden" name="month_no" value="{{ $monthNo }}">
                    <input type="hidden" name="year" value="{{ $yearVal }}">
                    <button type="submit" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-download me-1"></i> Download PDF
                    </button>
                </form>
            </div>
        </div>

        {{-- A) New Orders KPIs --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">New Orders Count</div>
                        <div class="kpi-value text-white">{{ number_format($kpis['new_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">New Orders Value (SAR)</div>
                        <div class="kpi-value text-white">{{ number_format((float)($kpis['new_value'] ?? 0), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-header"><strong>A) New Orders Expected This Month</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer Name</th>
                            <th>Products</th>
                            <th>Project Name</th>
                            <th>Quotation No.</th>
                            <th class="text-center">% (optional)</th>
                            <th class="text-end">Value (SAR)</th>
                            <th>Product Family</th>
                            <th>Sales Source</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($newRows as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row->customer_name }}</td>
                                <td>{{ $row->products }}</td>
                                <td>{{ $row->project_name }}</td>
                                <td>{{ $row->quotation_no }}</td>
                                <td class="text-center">{{ $row->percentage ?? '' }}</td>
                                <td class="text-end">{{ number_format((float)$row->value_sar, 0) }}</td>
                                <td>{{ $row->product_family }}</td>
                                <td>{{ $row->sales_source }}</td>
                                <td>{{ $row->remarks }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-3">No rows for {{ $monthName }} {{ $yearVal }}.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Total New Orders</th>
                            <th class="text-end">{{ number_format((float)($kpis['new_value'] ?? 0), 0) }}</th>
                            <th colspan="3"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- B) Carry-Over KPIs --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Carry-Over Count</div>
                        <div class="kpi-value text-white">{{ number_format($kpis['carry_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Carry-Over Value (SAR)</div>
                        <div class="kpi-value text-white">{{ number_format((float)($kpis['carry_value'] ?? 0), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header"><strong>B) Carry-Over (from previous month)</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer Name</th>
                            <th>Products</th>
                            <th>Project Name</th>
                            <th>Quotation No.</th>
                            <th class="text-center">% (>=75)</th>
                            <th class="text-end">Value (SAR)</th>
                            <th>Product Family</th>
                            <th>Sales Source</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($carryRows as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row->customer_name }}</td>
                                <td>{{ $row->products }}</td>
                                <td>{{ $row->project_name }}</td>
                                <td>{{ $row->quotation_no }}</td>
                                <td class="text-center">{{ $row->percentage ?? '' }}</td>
                                <td class="text-end">{{ number_format((float)$row->value_sar, 0) }}</td>
                                <td>{{ $row->product_family }}</td>
                                <td>{{ $row->sales_source }}</td>
                                <td>{{ $row->remarks }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-3">No carry-over rows for {{ $monthName }} {{ $yearVal }}.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Total Carry-Over</th>
                            <th class="text-end">{{ number_format((float)($kpis['carry_value'] ?? 0), 0) }}</th>
                            <th colspan="3"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </main>
@endsection





