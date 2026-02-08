<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Order Log Overview</title>
    <style>
        @page { margin: 12px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            background: #ffffff;
        }
        .page {
            background: #ffffff;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .header { width: 100%; margin-bottom: 8px; }
        .header-left { float: left; }
        .header-right { float: right; text-align: right; font-size: 9px; color: #6b7280; }
        .subtitle { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .clearfix::after { content: ""; display: table; clear: both; }

        .filters { margin: 6px 0 8px; }
        .chip {
            display: inline-block;
            padding: 2px 8px;
            margin-right: 6px;
            border-radius: 999px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            font-size: 9px;
            color: #374151;
        }

        /* Tables */
        .block {
            border: 1px solid #000;
        }
        .block th,
        .block td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        .hdr-yellow { background: #ffe600; font-weight: 700; }
        .fill-blue { background: #cfeaf7; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }
        .red { color: #e11d48; font-weight: 700; }

        /* Layout */
        .grid-top {
            display: table;
            width: 100%;
            margin-top: 6px;
        }
        .col-left,
        .col-mid,
        .col-right {
            display: table-cell;
            vertical-align: top;
        }
        .col-left { width: 22%; padding-right: 10px; }
        .col-mid { width: 56%; }
        .col-right { width: 22%; padding-left: 10px; }

        .chart-box {
            background: #404040;
            border: 2px solid #111;
            height: 260px;
            position: relative;
        }
        .chart-title {
            position: absolute;
            top: 6px;
            left: 0;
            right: 0;
            text-align: center;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
        }
        .chart-img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .monthly {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .monthly th,
        .monthly td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
    </style>
</head>
<body>
@php
    $regionNorm = is_string($region ?? null) ? trim($region) : 'all';
    $regionLabel = ($regionNorm === '' || strtolower($regionNorm) === 'all')
        ? 'All Regions'
        : ucfirst(strtolower($regionNorm));

    $yearLabel = $yearLabel ?? (!empty($year) ? $year : 'All Years');

    $monthLabel = 'All Months';
    if (!empty($month) && is_numeric($month)) {
        $monthLabel = \Carbon\Carbon::create()->month((int)$month)->format('F');
    }

    $dateRange = '';
    if (!empty($from) || !empty($to)) {
        $dateRange = trim(($from ?: '') . ' to ' . ($to ?: ''));
    }
@endphp

<div class="page">
    <div class="header clearfix">
        <div class="header-left">
            <h2>Sales Order Log Overview {{ $yearLabel }}</h2>
        </div>
        <div class="header-right">
            <div>Report Date</div>
            <div><strong>{{ $today }}</strong></div>
        </div>
    </div>

    <div class="filters">
        <span class="chip">Region: {{ $regionLabel }}</span>
        <span class="chip">Year: {{ $yearLabel }}</span>
        <span class="chip">Month: {{ $monthLabel }}</span>
        @if($dateRange !== '')
            <span class="chip">Date: {{ $dateRange }}</span>
        @endif
    </div>

    {{-- ======= TOP GRID ======= --}}
    <div class="grid-top">
        <div class="col-left">
            <table class="block" style="width:100%; border-collapse:collapse;">
                <tr class="hdr-yellow">
                    <th>Regional Concertration</th>
                    <th class="center">Total Orders Regional Concertration ( Accepted &amp; Pre Acceptance)</th>
                    <th class="center">Rejected Value</th>
                </tr>
                @php
                    $rt = $regionTotals ?? ['Eastern'=>0,'Western'=>0,'Central'=>0];
                    $rb = $rejectedByRegion ?? ['Eastern'=>0,'Western'=>0,'Central'=>0];
                @endphp
                <tr class="fill-blue">
                    <td>KSA Eastern</td>
                    <td class="num">{{ number_format($rt['Eastern'] ?? 0, 0) }}</td>
                    <td class="num">{{ number_format($rb['Eastern'] ?? 0, 0) }}</td>
                </tr>
                <tr class="fill-blue">
                    <td>KSA Western</td>
                    <td class="num">{{ number_format($rt['Western'] ?? 0, 0) }}</td>
                    <td class="num">{{ number_format($rb['Western'] ?? 0, 0) }}</td>
                </tr>
                <tr class="fill-blue">
                    <td>KSA Central</td>
                    <td class="num">{{ number_format($rt['Central'] ?? 0, 0) }}</td>
                    <td class="num">{{ number_format($rb['Central'] ?? 0, 0) }}</td>
                </tr>
                <tr>
                    <td class="center red">Total</td>
                    <td class="num red">{{ number_format(($rt['Eastern'] ?? 0)+($rt['Western'] ?? 0)+($rt['Central'] ?? 0), 0) }}</td>
                    <td class="num red">{{ number_format(($rb['Eastern'] ?? 0)+($rb['Western'] ?? 0)+($rb['Central'] ?? 0), 0) }}</td>
                </tr>
            </table>
        </div>

        <div class="col-mid">
            <div class="chart-box">
                @if(!empty($chartDataUri))
                    <img class="chart-img" src="{{ $chartDataUri }}" alt="Chart">
                @elseif($chartImagePath)
                    <img class="chart-img" src="{{ $chartImagePath }}" alt="Chart">
                @else
                    <div style="color:#e5e7eb; text-align:center; padding-top:110px; font-size:10px;">
                        Chart image not available
                    </div>
                @endif
            </div>
        </div>

        <div class="col-right">
            @php
                $counts = $statusCounts ?? [];
                $values = $statusValues ?? [];
                $countTotal = $totalOrdersCount ?? array_sum($counts);
                $valueTotal = $totalOrdersValue ?? array_sum($values);
            @endphp
            <table class="block" style="width:100%; border-collapse:collapse; margin-bottom:6px;">
                <tr class="hdr-yellow">
                    <th>Order Status</th>
                    <th class="center">No. Of Orders</th>
                </tr>
                <tr class="fill-blue"><td>Accepted</td><td class="num">{{ $counts['ACCEPTANCE'] ?? 0 }}</td></tr>
                <tr class="fill-blue"><td>Pre-Acceptance</td><td class="num">{{ $counts['PRE-ACCEPTANCE'] ?? 0 }}</td></tr>
                <tr class="fill-blue"><td>Rejected</td><td class="num">{{ $counts['REJECTED'] ?? 0 }}</td></tr>
                <tr class="fill-blue"><td>Cancelled</td><td class="num">{{ $counts['CANCELLED'] ?? 0 }}</td></tr>
                <tr>
                    <td class="center red">Total</td>
                    <td class="num red">{{ $countTotal }}</td>
                </tr>
            </table>

            <table class="block" style="width:100%; border-collapse:collapse;">
                <tr class="hdr-yellow">
                    <th>Order Status</th>
                    <th class="center">Value</th>
                </tr>
                <tr class="fill-blue"><td>Accepted</td><td class="num">{{ number_format($values['ACCEPTANCE'] ?? 0, 0) }}</td></tr>
                <tr class="fill-blue"><td>Pre-Acceptance</td><td class="num">{{ number_format($values['PRE-ACCEPTANCE'] ?? 0, 0) }}</td></tr>
                <tr class="fill-blue"><td>Rejected</td><td class="num">{{ number_format($values['REJECTED'] ?? 0, 0) }}</td></tr>
                <tr class="fill-blue"><td>Cancelled</td><td class="num">{{ number_format($values['CANCELLED'] ?? 0, 0) }}</td></tr>
                <tr>
                    <td class="center red">Total</td>
                    <td class="num red">{{ number_format($valueTotal, 0) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ======= MONTHLY TABLE ======= --}}
    @php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $mb = $monthlyByRegion ?? [
            'Eastern' => array_fill(1, 12, 0.0),
            'Western' => array_fill(1, 12, 0.0),
            'Central' => array_fill(1, 12, 0.0),
        ];
    @endphp
    <table class="monthly">
        <tr class="hdr-yellow">
            <th>Region / Country</th>
            @foreach($months as $m)
                <th class="center">{{ $m }}</th>
            @endforeach
            <th class="center">Total</th>
        </tr>
        @foreach(['Eastern' => 'KSA Eastern', 'Western' => 'KSA Western', 'Central' => 'KSA Central'] as $key => $label)
            @php
                $row = $mb[$key] ?? array_fill(1, 12, 0.0);
                $rowTotal = array_sum($row);
            @endphp
            <tr>
                <td class="fill-blue">{{ $label }}</td>
                @for($i = 1; $i <= 12; $i++)
                    <td class="num">{{ $row[$i] > 0 ? number_format($row[$i], 0) : '-' }}</td>
                @endfor
                <td class="num">{{ number_format($rowTotal, 0) }}</td>
            </tr>
        @endforeach
        @php
            $grandTotal = 0;
            for ($i = 1; $i <= 12; $i++) {
                $grandTotal += ($mb['Eastern'][$i] ?? 0) + ($mb['Western'][$i] ?? 0) + ($mb['Central'][$i] ?? 0);
            }
        @endphp
        <tr>
            <td class="center red">Total</td>
            @for($i = 1; $i <= 12; $i++)
                @php
                    $sumM = ($mb['Eastern'][$i] ?? 0) + ($mb['Western'][$i] ?? 0) + ($mb['Central'][$i] ?? 0);
                @endphp
                <td class="num red">{{ $sumM > 0 ? number_format($sumM, 0) : '-' }}</td>
            @endfor
            <td class="num red">{{ number_format($grandTotal, 0) }}</td>
        </tr>
    </table>
</div>
</body>
</html>
