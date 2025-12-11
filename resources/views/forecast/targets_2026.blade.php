@php
    $year           = $year           ?? 2026;
    $submissionDate = $submissionDate ?? now()->format('Y-m-d');
    $region         = $region         ?? '';
    $issuedBy       = $issuedBy       ?? '';
    $issuedDate     = $issuedDate     ?? $submissionDate;

    $forecast       = $forecast       ?? [];
    $criteria       = $criteria       ?? [];
    $totAll         = $totAll         ?? 0;

    // New orders list (optional – use whatever your controller sends)
    // If controller sends $rowsA, keep it; else fall back to $newOrders or empty.
    $rowsA = $rowsA ?? ($newOrders ?? []);
@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Targets – {{ $year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 12mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 0.4px solid #aaa;
            padding: 4px 6px;
            vertical-align: middle;
        }

        th {
            background: #e8f0e8;
            font-weight: bold;
        }

        .header-table th { background: #d0e1ff; }

        .sky   { background: #dbe8f6; }  /* left blue panel */
        .pearl { background: #ececec; } /* right gray panel */
        .thead { background: #f5f9f2; }

        .title  { font-weight: 800; font-size: 14px; }
        .subttl { font-weight: 700; }
        .small  { font-size: 10px; color: #666; }

        .no-b   { border: 0 none; }
        .right  { text-align: right; }
        .center { text-align: center; }

        .ibox {
            border: 1px solid #a7a7a7;
            height: 22px;
            padding: 3px 6px;
        }

        .pad  { padding: 8px; }
        .mt6  { margin-top: 6px; }
        .mt10 { margin-top: 10px; }

        .year { font-weight: 800; text-align: center; }

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
            <div class="ibox">21-12-2025</div>
        </td>
    </tr>
    <tr class="no-b">
        <td class="no-b small">ATAI Group</td>
        <td class="no-b"></td>
        <td class="no-b"></td>
        <td class="no-b"></td>
    </tr>
</table>
@if(!empty($showDownloadButton))
    <div style="text-align: right; margin-bottom: 10px;">
        <a href="{{ route('forecast.targets2026.download') }}"
           style="padding:6px 12px;border:1px solid #444;text-decoration:none;font-size:11px;">
            Download PDF
        </a>
    </div>
@endif
{{-- 2 header panels --}}
<table>
    <tr>
        <td class="sky subttl"   style="width:45%;">Sales Data</td>
        <td class="pearl subttl" style="width:55%;">Target Data</td>
    </tr>
    <tr>
        {{-- Sales Data --}}
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

        {{-- Target Data --}}
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:55%;">Annual Target (SAR)</td>
                    <td class="no-b">
                        <div class="ibox">
                            {{ isset($forecast['annual_target'])
                                ? number_format((float)$forecast['annual_target'], 0)
                                : '' }}
                        </div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Monthly Avg Target</td>
                    <td class="no-b">
                        <div class="ibox">
                            {{ isset($forecast['annual_target'])
                                ? number_format((float)$forecast['annual_target'] / 12, 0)
                                : '' }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- A) New Orders --}}
<div class="subttl mt10">A) New Orders Expected This Year -</div>
<table class="mt6">
    <thead class="thead">
    <tr class="head-row">
        <th style="width:40px;">Serial</th>
        <th>Customer Name</th>
        <th>Products</th>
        <th>Project Name</th>
        <th>Quotation No.</th>
        <th style="width:120px;" class="right">Value</th>
        <th>Remarks</th>
    </tr>
    </thead>
    <tbody>
    @php
        $totA    = 0;
        $maxRows = max(40, count($rowsA));   // always at least 20 lines
    @endphp

    @for($i = 0; $i < $maxRows; $i++)
        @php
            $row = $rowsA[$i] ?? [];
            $val = (float)($row['value'] ?? 0);
            $totA += $val;
        @endphp
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $row['customer'] ?? '' }}</td>
            <td>{{ $row['product'] ?? '' }}</td>
            <td>{{ $row['project'] ?? '' }}</td>
            <td>{{ $row['quotation'] ?? '' }}</td>
            <td class="right">
                {{ $val > 0 ? number_format($val, 0) : '' }}
            </td>
            <td>{{ $row['remarks'] ?? '' }}</td>
        </tr>
    @endfor

    <tr class="total-row">
        <td colspan="5" class="right">Total New Forecasted Orders for {{ $year }}</td>
        <td class="right">{{ $totA > 0 ? number_format($totA, 0) : '' }}</td>
        <td></td>
    </tr>
    </tbody>
</table>

{{-- Summary band --}}
{{--<table class="no-b" style="margin-top:8px; margin-bottom:6px;">--}}
{{--    <tr class="no-b">--}}
{{--        <td class="no-b"></td>--}}
{{--        <td class="no-b" style="width:260px;">--}}
{{--            <div class="ibox">--}}
{{--                Total For Year {{ $year }} : SAR {{ $totA > 0 ? number_format($totA, 0) : '' }}--}}
{{--            </div>--}}
{{--        </td>--}}
{{--    </tr>--}}
{{--</table>--}}

<p class="small mt10">ATAI · Generated {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
