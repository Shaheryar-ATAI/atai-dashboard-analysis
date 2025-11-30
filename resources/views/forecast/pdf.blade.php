@php


    $totA   = $totA   ?? 0;
    $totB   = $totB   ?? 0;
    $totAll = $totAll ?? 0;

@endphp


    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Monthly Sales Forecast & Targets</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        @page {
            margin: 20px 25px;
        }

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
        }

        th {
            background: #e8f0e8;
            font-weight: bold;
        }

        .header-table th {
            background: #d0e1ff;
        }

        /* palette */
        .sky {
            background: #dbe8f6;
        }

        /* left blue panel */
        .mint {
            background: #e8f2df;
        }

        /* middle green panel */
        .pearl {
            background: #ececec;
        }

        /* right gray panel */

        .thead {
            background: #f5f9f2;
        }

        .title {
            font-weight: 800;
            font-size: 14px;
        }

        .subttl {
            font-weight: 700;
        }

        .small {
            font-size: 10px;
            color: #666;
        }

        .total-row {
            background: #f7f7f7;
            font-weight: 700;
        }

        .head-row {
            background: #eaf2e1;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #cfcfcf;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .no-b {
            border: 0 none;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .ibox {
            border: 1px solid #a7a7a7;
            height: 22px;
            padding: 3px 6px;
        }

        .pad {
            padding: 8px;
        }

        .mt6 {
            margin-top: 6px;
        }

        .mt10 {
            margin-top: 10px;
        }

        .year {
            font-weight: 800;
            text-align: center;
        }


        @font-face {
            font-family: 'DejaVu Sans';
            font-style: normal;
            font-weight: normal;
            src: url("{{ storage_path('fonts/DejaVuSans.ttf') }}") format('truetype');
        }

        body {
            font-family: 'DejaVu Sans', DejaVu Sans, sans-serif;
        }
    </style>
</head>
<body>
@php
    // ---- Safe defaults to avoid "Undefined variable" ----
    $year            = $year            ?? now()->format('Y');
    $submissionDate  = $submissionDate  ?? now()->format('Y-m-d');
    $region          = $region          ?? '';
    $monthYear       = $monthYear       ?? '';
    $issuedBy        = $issuedBy        ?? '';
    $issuedDate      = $issuedDate      ?? $submissionDate;
    $criteria        = $criteria        ?? [];
    $forecast        = $forecast        ?? [];
    $newOrders       = $newOrders       ?? [];
    $carryOver       = $carryOver       ?? [];

    // ---- Keep only non-empty rows (any field present or positive value) ----
    $keep = function($r){
        return filled($r['customer'] ?? null)
            || filled($r['product']  ?? null)
            || filled($r['project']  ?? null)
            || filled($r['remarks']  ?? null)
            || (float)($r['value'] ?? 0) > 0;
    };
    $rowsA = array_values(array_filter($newOrders,  $keep));
    $rowsB = array_values(array_filter($carryOver,  $keep));
@endphp

    <!-- Sheet title row -->
<table class="no-b" style="margin-bottom:6px;">
    <tr class="no-b">
        <td class="no-b title">Monthly Sales Forecast and Sales Order Targets</td>
        <td class="no-b year">{{ $year }}</td>
        <td class="no-b right subttl">Submission Date</td>
        <td style="width:150px;">
            <div class="ibox">{{ $submissionDate }}</div>
        </td>
    </tr>
    <tr class="no-b">
        <td class="no-b small">ATIA Group</td>
        <td class="no-b"></td>
        <td class="no-b"></td>
        <td class="no-b"></td>
    </tr>
</table>

<!-- Three header panels (Sales Data, Forecasting Criteria, Forecast Data) -->
<table>
    <tr>
        <td class="sky subttl" style="width:36%;">Sales Data</td>
        <td class="mint subttl" style="width:30%;">Forecasting Criteria</td>
        <td class="pearl subttl">Forecast Data</td>
    </tr>
    <tr>
        <!-- Sales Data -->
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:35%;">Region</td>
                    <td class="no-b">
                        <div class="ibox">{{ $region }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Month/Year</td>
                    <td class="no-b">
                        <div class="ibox">{{ $monthYear }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Issued By</td>
                    <td class="no-b">
                        <div class="ibox">{{ $issuedBy }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Issued Date</td>
                    <td class="no-b">
                        <div class="ibox">{{ $issuedDate }}</div>
                    </td>
                </tr>
            </table>
        </td>

        <!-- Forecasting Criteria -->
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:55%;">Price Agreed</td>
                    <td class="no-b">
                        <div class="ibox">{{ $criteria['price_agreed'] ?? '' }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Consultant Approval</td>
                    <td class="no-b">
                        <div class="ibox">{{ $criteria['consultant_approval'] ?? '' }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Percentage</td>
                    <td class="no-b">
                        <div class="ibox">{{ $criteria['percentage'] ?? '' }}</div>
                    </td>
                </tr>
            </table>
        </td>

        <!-- Forecast Data -->
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:55%;">Month Target</td>
                    <td class="no-b">
                        <div class="ibox">
                            {{ isset($forecast['month_target']) ? number_format((float)$forecast['month_target'], 0) : '' }}
                        </div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Required Turn-over</td>
                    <td class="no-b">
                        <div class="ibox">{{ $forecast['required_turnover'] ?? '' }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Required Forecast</td>
                    <td class="no-b">
                        <div class="ibox">{{ $forecast['required_forecast'] ?? '' }}</div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Conversion Ratio</td>
                    <td class="no-b">
                        <div class="ibox">{{ $forecast['conversion_ratio'] ?? '' }}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- A) New Orders -->
<div class="subttl mt10">A) New Orders Expected This Month -</div>
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
    @php $totA = 0; @endphp
    @forelse($rowsA as $i => $r)
        @php $totA += (float)($r['value'] ?? 0); @endphp
        <tr>
            <td class="center">{{ $i+1 }}</td>
            <td>{{ $r['customer'] ?? '' }}</td>
            <td>{{ $r['product'] ?? '' }}</td>
            <td>{{ $r['project'] ?? '' }}</td>
            <td>{{ $r['quotation'] ?? '' }}</td>
            <td class="right">{{ number_format((float)($r['value'] ?? 0), 0) }}</td>
            <td>{{ $r['remarks'] ?? '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="center small">No rows</td>
        </tr>
    @endforelse
    <tr class="total-row">
        <td colspan="5" class="right">Total New Current Month Forecasted Orders</td>
        <td class="right">{{ number_format($totA, 0) }}</td>
        <td></td>
    </tr>
    </tbody>
</table>

<!-- B) Carry-Over -->
<div class="subttl mt10">B) Carry-Over (from the previous month and expected to close in the current month)</div>
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
    @php $totB = 0; @endphp
    @forelse($rowsB as $i => $r)
        @php $totB += (float)($r['value'] ?? 0); @endphp
        <tr>
            <td class="center">{{ $i+1 }}</td>
            <td>{{ $r['customer'] ?? '' }}</td>
            <td>{{ $r['product'] ?? '' }}</td>
            <td>{{ $r['project'] ?? '' }}</td>
            <td>{{ $r['quotation'] ?? '' }}</td>
            <td class="right">{{ number_format((float)($r['value'] ?? 0), 0) }}</td>
            <td>{{ $r['remarks'] ?? '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="center small">No rows</td>
        </tr>
    @endforelse
    <tr class="total-row">
        <td colspan="5" class="right">Total Carry-over</td>

        <td class="right">{{ number_format($totB, 0) }}</td>
        <td></td>
    </tr>
    </tbody>
</table>

<!-- This Month Total band -->
<table class="no-b" style="margin-top:8px; margin-bottom:6px;">
    <tr class="no-b">
        <td class="no-b"></td>
        <td class="no-b" style="text-align:right; font-weight:700;">
            This Month Total :
        </td>
        <td class="no-b" style="width:160px;">
            <div class="ibox">
                {{ isset($forecast['month_target']) ? number_format((int)$totAll, 0) : '' }}
            </div>
        </td>
    </tr>
</table>


<p class="small mt10">ATAI · Generated {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
