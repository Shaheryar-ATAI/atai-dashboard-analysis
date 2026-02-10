@extends('layouts.app')

@section('title', 'Weekly Report List')

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atai-theme-20260210.css') }}?v={{ time() }}">
    <style>
        .weekly-list .table {
            --bs-table-bg: transparent;
            --bs-table-color: #e5e7eb;
            --bs-table-striped-bg: rgba(255, 255, 255, .04);
            --bs-table-striped-color: #e5e7eb;
            --bs-table-hover-bg: rgba(255, 255, 255, .06);
            --bs-table-hover-color: #ffffff;
            border-color: rgba(255, 255, 255, .12);
        }
        .weekly-list .table thead th {
            color: #f9fafb;
            border-color: rgba(255, 255, 255, .2);
        }
        .weekly-list .table td, .weekly-list .table th {
            border-color: rgba(255, 255, 255, .12);
        }
        .weekly-list .table tbody td {
            color: #e5e7eb;
        }
        .weekly-list .table tbody td a {
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
    </style>
@endpush

@section('content')
    @php
        $monthNo = (int)($month_no ?? now()->month);
        $yearVal = (int)($year ?? now()->year);
        $monthName = \Carbon\Carbon::create()->month($monthNo)->format('F');
        $years = [];
        $cy = now()->year;
        for ($y = $cy + 1; $y >= $cy - 3; $y--) { $years[] = $y; }
    @endphp

    <main class="container-fluid py-4 weekly-list">
        <div class="glass-row d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div>
                <h2 class="mb-1 fw-bold text-light">Weekly Report List</h2>
                <div class="text-secondary small">{{ $monthName }} {{ $yearVal }}</div>
            </div>

            <form method="GET" action="{{ route('weekly.list') }}" class="d-flex flex-wrap align-items-end gap-2">
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
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Reports</div>
                        <div class="kpi-value text-white">{{ number_format($kpis['reports_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Activities (Rows)</div>
                        <div class="kpi-value text-white">{{ number_format($kpis['items_count'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card kpi-card">
                    <div class="card-body py-2">
                        <div class="text-white-50 small">Total Value (SAR)</div>
                        <div class="kpi-value text-white">{{ number_format((float)($kpis['total_value_sar'] ?? 0), 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header"><strong>Weekly Activity Summary</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Week</th>
                            <th>Engineer</th>
                            <th class="text-end">Activities</th>
                            <th class="text-end">Total Value (SAR)</th>
                            <th class="text-end">Download</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $i => $row)
                            @php
                                $start = \Carbon\Carbon::parse($row->week_start);
                                $end = (clone $start)->addDays(6);
                                $label = $start->format('d M') . ' - ' . $end->format('d M');
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $label }}</td>
                                <td>{{ $row->engineer_name }}</td>
                                <td class="text-end">{{ number_format((int)$row->items_count) }}</td>
                                <td class="text-end">{{ number_format((float)$row->total_value_sar, 0) }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" target="_blank"
                                       href="{{ route('weekly.pdf', $row->id) }}">
                                        <i class="bi bi-download me-1"></i> PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">No weekly reports found for {{ $monthName }} {{ $yearVal }}.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
@endsection



