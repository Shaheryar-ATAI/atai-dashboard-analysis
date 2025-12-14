@php
    $year           = $year ?? 2026;
    $submissionDate = $submissionDate ?? now()->format('Y-m-d');
    $region         = $region ?? '';
    $issuedBy       = $issuedBy ?? '';
    $issuedDate     = $issuedDate ?? $submissionDate;

    $forecast = $forecast ?? [];
    $isPdf    = $isPdf ?? false;

    // Sections data coming from controller/form
    $rowsA = $rowsA ?? ($newOrders ?? []);
    $rowsB = $rowsB ?? [];
    $rowsC = $rowsC ?? [];
    $rowsD = $rowsD ?? [];

    // Criteria meanings
    $criteriaLegend = $criteriaLegend ?? [
        'A' => 'Commercial matters agreed & MS approved',
        'B' => 'Commercial matters agreed OR MS approved',
        'C' => 'Neither commercial matters nor MS achieved',
        'D' => 'Project is in bidding stage',
    ];

    // Build ONE combined table with a Category column
    $combined = [];

    $pushRows = function(array $rows, string $cat) use (&$combined) {
        foreach ($rows as $r) {
            // keep empty lines too (so the PDF still has fixed rows)
            $combined[] = array_merge(['category' => $cat], (array)$r);
        }
    };

    $pushRows($rowsA, 'A');
    $pushRows($rowsB, 'B');
    $pushRows($rowsC, 'C');
    $pushRows($rowsD, 'D');

    // If everything is empty, keep some blank rows so PDF looks like a form
    if (count($combined) === 0) {
        for ($i=0; $i<40; $i++) $combined[] = ['category' => ''];
    }

    // Expand criteria code to full text (A -> "A — ....")
    $criteriaText = function($code) use ($criteriaLegend) {
        $c = trim((string)($code ?? ''));
        if ($c === '') return '';
        $upper = strtoupper($c);

        // If user already typed full sentence, keep it
        if (strlen($c) > 2 && !isset($criteriaLegend[$upper])) return $c;

        if (isset($criteriaLegend[$upper])) {
            return $upper . ' — ' . $criteriaLegend[$upper];
        }

        return $c; // fallback
    };

    // Totals
    $totA = 0; $totB = 0; $totC = 0; $totD = 0;
@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Targets – {{ $year }}</title>

    <style>
        @page { size: A4 landscape; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 11px; color: #111; }

        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.4px solid #aaa; padding: 4px 6px; vertical-align: middle; }
        th { background: #e8f0e8; font-weight: bold; }

        .sky   { background: #dbe8f6; }
        .pearl { background: #ececec; }
        .thead { background: #f5f9f2; }

        .title  { font-weight: 800; font-size: 14px; }
        .subttl { font-weight: 700; }
        .small  { font-size: 10px; color: #666; }

        .no-b   { border: 0 none !important; }
        .right  { text-align: right; }
        .center { text-align: center; }

        .ibox { border: 1px solid #a7a7a7; height: 22px; padding: 3px 6px; }
        .pad  { padding: 8px; }
        .mt6  { margin-top: 6px; }
        .mt10 { margin-top: 10px; }
        .mt14 { margin-top: 14px; }
        .year { font-weight: 800; text-align: center; }

        .total-row td { background: #f2f2f2; font-weight: 700; }

        @font-face {
            font-family: 'DejaVu Sans';
            font-style: normal;
            font-weight: normal;
            src: url("{{ storage_path('fonts/DejaVuSans.ttf') }}") format('truetype');
        }
    </style>
</head>
<body>

{{-- Title row --}}
<table class="no-b" style="margin-bottom:6px;">
    <tr class="no-b">
        <td class="no-b title">Annual Sales Target – {{ $year }}</td>
        <td class="no-b year">{{ $year }}</td>
        <td class="no-b right subttl">Submission Date</td>
        <td style="width:150px;">
            <div class="ibox">{{ $submissionDate }}</div>
        </td>
    </tr>
    <tr class="no-b">
        <td class="no-b small">ATAI Group</td>
        <td class="no-b"></td>
        <td class="no-b"></td>
        <td class="no-b"></td>
    </tr>
</table>

{{-- Portal-only button (hidden in PDF) --}}
@if(!empty($showDownloadButton) && !$isPdf)
    <div style="text-align:right; margin-bottom:10px;">
        <a href="{{ route('forecast.targets2026.download') }}"
           style="padding:6px 12px;border:1px solid #444;text-decoration:none;font-size:11px;">
            Download PDF
        </a>
    </div>
@endif

{{-- 2 header panels --}}
<table>
    <tr>
        <td class="sky subttl" style="width:45%;">Sales Data</td>
        <td class="pearl subttl" style="width:55%;">Target Data</td>
    </tr>
    <tr>
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:35%;">Region</td>
                    <td class="no-b"><div class="ibox">{{ $region }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Year</td>
                    <td class="no-b"><div class="ibox">{{ $year }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Issued By</td>
                    <td class="no-b"><div class="ibox">{{ $issuedBy }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Issued Date</td>
                    <td class="no-b"><div class="ibox">{{ $issuedDate }}</div></td>
                </tr>
            </table>
        </td>

        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:55%;">Annual Target (SAR)</td>
                    <td class="no-b">
                        <div class="ibox">
                            {{ isset($forecast['annual_target']) ? number_format((float)$forecast['annual_target'], 0) : '' }}
                        </div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Monthly Avg Target</td>
                    <td class="no-b">
                        <div class="ibox">
                            {{ isset($forecast['annual_target']) ? number_format((float)$forecast['annual_target']/12, 0) : '' }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ✅ ONE TABLE ONLY --}}
<div class="subttl mt10">Forecast for 2026</div>

<table class="mt6">
    <thead class="thead">
    <tr>
        <th style="width:40px;">Serial</th>
{{--        <th style="width:36px;">Cat</th>--}}
        <th>Customer Name</th>
        <th>Products</th>
        <th>Project Name</th>
        <th>Quotation No.</th>
        <th style="width:120px;" class="right">Value</th>
        <th style="width:70px;">Status</th>
        <th style="width:260px;">Forecast Criteria</th>
        <th>Remarks</th>
    </tr>
    </thead>
    <tbody>
    @php
        // Keep a nice fixed number of visible lines in PDF
        $minRows = 25;
        $maxRows = max($minRows, count($combined));
        $grand = 0;
    @endphp

    @for($i = 0; $i < $maxRows; $i++)
        @php
            $row = $combined[$i] ?? [];
            $cat = $row['category'] ?? '';
            $val = (float)($row['value'] ?? 0);

            if ($cat === 'A') $totA += $val;
            if ($cat === 'B') $totB += $val;
            if ($cat === 'C') $totC += $val;
            if ($cat === 'D') $totD += $val;

            $grand += $val;

            // If the row has forecast_criteria code like "A", expand it to full meaning
            $crit = $criteriaText($row['forecast_criteria'] ?? '');

            // If user didn't fill criteria, but category exists, you may want to show meaning based on category:
            if ($crit === '' && isset($criteriaLegend[$cat])) {
                $crit = $cat . ' — ' . $criteriaLegend[$cat];
            }
        @endphp

        <tr>
            <td class="center">{{ $i + 1 }}</td>
{{--            <td class="center">{{ $cat }}</td>--}}
            <td>{{ $row['customer'] ?? '' }}</td>
            <td>{{ $row['product'] ?? '' }}</td>
            <td>{{ $row['project'] ?? '' }}</td>
            <td>{{ $row['quotation'] ?? '' }}</td>
            <td class="right">{{ $val > 0 ? number_format($val, 0) : '' }}</td>
            <td>{{ $row['status'] ?? '' }}</td>
            <td>{{ $crit }}</td>
            <td>{{ $row['remarks'] ?? '' }}</td>
        </tr>
    @endfor

    <tr class="total-row">
        <td colspan="6" class="right">Totals</td>
        <td class="right">{{ $grand > 0 ? number_format($grand, 0) : '' }}</td>
        <td colspan="3" class="small">

        </td>
    </tr>
    </tbody>
</table>

<p class="small mt10">ATAI · Generated {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
