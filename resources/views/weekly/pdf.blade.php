@php
    use Carbon\Carbon;

    $weekStart = Carbon::parse($report->week_start)->startOfDay();
    $weekEnd   = $weekStart->copy()->addDays(4);
    $today     = Carbon::now();

    $engineer  = $report->engineer_name ?: ($user->name ?? '—');
    $region    = $user->region ?? '—';

    $fmtSAR = fn($n) => number_format((float)($n ?? 0), 2);
    $dash   = fn($v) => ($v === null || $v === '' ? '—' : $v);

    $logoPath = public_path('images/atai-logo.png');
@endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Weekly Sales Activities Report</title>
    <style>
        @page { margin: 20mm 18mm 28mm 18mm; }
        body  { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }

        /* ===== HEADER BAR ===== */
        .header {
            background:#0c7135; color:#fff; padding:10px 14px; border-radius:6px;
        }
        .header-table { width:100%; border-collapse:collapse; }
        .header-table td { vertical-align:middle; }
        .logo { height:50px; display:block; }
        .company { font-size:18px; font-weight:800; letter-spacing:.3px; }
        .subtitle { font-size:13px; font-weight:600; color:#e8ffe8; }

        /* ===== META BOX ===== */
        .meta {
            width:100%; border-collapse:collapse; margin:10px 0 15px 0;
        }
        .meta td {
            border:1px solid #d9d9d9; padding:6px 8px; font-size:11.5px;
        }
        .meta .lb {
            background:#f3f6f3; font-weight:600; color:#333; width:130px;
        }

        /* ===== MAIN TABLE ===== */
        table.grid {
            width:100%;
            margin:0 auto;                 /* center the table */
            table-layout:fixed;            /* prevent column expansion */
            border-collapse:collapse;
            border:1px solid #e6e6e6;
        }
        .grid thead th {
            background:#f2f2f2; color:#222; font-weight:600;
            text-align:left; padding:6px 6px; border:1px solid #e6e6e6;
        }
        .grid tbody td {
            border:1px solid #e6e6e6; padding:6px 6px;
            font-size:8px;                 /* small but readable on A4 */
            vertical-align:top;
            word-wrap:break-word;          /* dompdf-friendly wrapping */
            word-break:break-word;
        }
        .grid tbody tr:nth-child(odd) td { background:#fcfcfc; }
        .center { text-align:center; }
        .right  { text-align:right; }
        .nowrap { white-space:nowrap; }

        /* Quotation # column (4th): allow hard breaks on dots/numbers */
        .grid thead th:nth-child(4),
        .grid tbody td:nth-child(4) {
            word-break:break-all;
        }

        /* Keep Mobile & Date in one line to stay compact */
        .grid thead th:nth-child(9),
        .grid tbody td:nth-child(9),
        .grid thead th:nth-child(10),
        .grid tbody td:nth-child(10) {
            white-space:nowrap;
        }

        /* ===== FOOTER ===== */
        .footer {
            position:fixed; bottom:-8px; left:0; right:0;
            text-align:center; font-size:11px; color:#777;
        }
        .pagenum:before { content: counter(page); }
    </style>

</head>
<body>

<!-- ===== HEADER BAR ===== -->
<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:70px">
                @if(file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="ATAI">
                @endif
            </td>
            <td>
                <div class="company">ARABIAN THERMAL AIRE INDUSTRIES CO. LTD</div>
                <div class="subtitle">Weekly Sales Activities Report</div>
            </td>
        </tr>
    </table>
</div>

<!-- ===== META INFORMATION ===== -->
<table class="meta">
    <tr><td class="lb">Week</td><td>{{ $weekStart->format('d M Y') }} – {{ $weekEnd->format('d M Y') }}</td></tr>
    <tr><td class="lb">Generated</td><td>{{ $today->format('d M Y') }}</td></tr>
    <tr><td class="lb">Sales Engineer</td><td>{{ $engineer }}</td></tr>
    <tr><td class="lb">Region</td><td>{{ ucfirst($region) }}</td></tr>
</table>

<!-- ===== MAIN DATA TABLE ===== -->
<table class="grid">
    <colgroup>
        <col style="width:3%;">   <!-- # -->
        <col style="width:13%;">  <!-- Customer -->
        <col style="width:15%;">  <!-- Project -->
        <col style="width:15%;">  <!-- Quotation # -->
        <col style="width:9%;">   <!-- Location -->
        <col style="width:8%;">   <!-- Value -->
        <col style="width:9%;">   <!-- Status -->
        <col style="width:9%;">   <!-- Contact -->
        <col style="width:7%;">   <!-- Mobile -->
        <col style="width:7%;">   <!-- Date -->
        <col style="width:15%;">  <!-- Notes -->
    </colgroup>

    <thead>
    <tr>
        <th class="center">#</th>
        <th>Customer</th>
        <th>Project</th>
        <th>Quotation #</th>
        <th>Location</th>
        <th class="right">Value (SAR)</th>
        <th>Status</th>
        <th>Contact</th>
        <th>Mobile</th>
        <th class="nowrap">Date</th>
        <th>Notes</th>
    </tr>
    </thead>

    <tbody>
    @forelse($items as $it)
        <tr>
            <td class="center">{{ $it->row_no }}</td>
            <td>{{ $dash($it->customer_name) }}</td>
            <td>{{ $dash($it->project_name) }}</td>
            <td>{{ $dash($it->quotation_no) }}</td>
            <td>{{ $dash($it->project_location) }}</td>
            <td class="right">{{ $fmtSAR($it->value_sar) }}</td>
            <td>{{ $dash($it->project_status) }}</td>
            <td>{{ $dash($it->contact_name) }}</td>
            <td class="nowrap">{{ $dash($it->contact_mobile_e164) }}</td>
            <td class="nowrap">
                {{ $it->visit_date ? Carbon::parse($it->visit_date)->format('d/m/Y') : '—' }}
            </td>
            <td>{{ $dash($it->notes) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="10" class="center" style="color:#777;">No records available.</td>
        </tr>
    @endforelse
    </tbody>
</table>



<!-- ===== FOOTER ===== -->
<div class="footer">
    © {{ date('Y') }} Arabian Thermal Aire Industries Co. Ltd — All Rights Reserved · Page <span class="pagenum"></span>
</div>

</body>
</html>
