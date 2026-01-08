{{-- resources/views/forecast/pdf.blade.php --}}

@php
    // =========================
    // Safe defaults
    // =========================
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

    // =========================
    // Normalizer: accept BOTH key styles
    // - old: customer/product/project/quotation/value/remarks
    // - new: customer_name/products/project_name/quotation_no/value_sar/remarks/product_family/sales_source/percentage
    // =========================
    $norm = function(array $r): array {
        $value = $r['value']
            ?? $r['value_sar']
            ?? 0;

        return [
            'customer'       => $r['customer']        ?? $r['customer_name'] ?? '',
            'product'        => $r['product']         ?? $r['products']      ?? '',
            'project'        => $r['project']         ?? $r['project_name']  ?? '',
            'quotation'      => $r['quotation']       ?? $r['quotation_no']  ?? '',
            'percentage'     => $r['percentage']      ?? '',
            'value'          => (float) preg_replace('/[^\d\.\-]/', '', (string) $value),
            'product_family' => $r['product_family']  ?? '',
            'sales_source'   => $r['sales_source']    ?? '',
            'remarks'        => $r['remarks']         ?? '',
        ];
    };

    $rowsA = array_map($norm, is_array($newOrders) ? $newOrders : []);
    $rowsB = array_map($norm, is_array($carryOver) ? $carryOver : []);

    // Keep only non-empty rows (any field present OR positive value)
    $keep = function(array $r): bool {
        return ($r['customer'] !== '')
            || ($r['product'] !== '')
            || ($r['project'] !== '')
            || ($r['quotation'] !== '')
            || ($r['product_family'] !== '')
            || ($r['sales_source'] !== '')
            || ($r['remarks'] !== '')
            || ((float)($r['value'] ?? 0) > 0);
    };

    $rowsA = array_values(array_filter($rowsA, $keep));
    $rowsB = array_values(array_filter($rowsB, $keep));

    // Totals
    $totA = 0;
    foreach ($rowsA as $r) { $totA += (float)($r['value'] ?? 0); }

    $totB = 0;
    foreach ($rowsB as $r) { $totB += (float)($r['value'] ?? 0); }

    $totAll = $totA + $totB;

    // Format helper
    $fmt0 = function($n) { return number_format((float)$n, 0); };

@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Monthly Sales Forecast & Targets</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm; }

        * { box-sizing: border-box; }

        @font-face {
            font-family: 'DejaVu Sans';
            font-style: normal;
            font-weight: normal;
            src: url("{{ storage_path('fonts/DejaVuSans.ttf') }}") format('truetype');
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        table { border-collapse: collapse; width: 100%; }

        th, td {
            border: 1px solid #cfcfcf;
            padding: 6px 8px;
            vertical-align: middle;
        }

        th {
            background: #e8f0e8;
            font-weight: 700;
        }

        .sky   { background: #dbe8f6; }   /* left blue panel */
        .mint  { background: #e8f2df; }   /* middle green panel */
        .pearl { background: #ececec; }   /* right gray panel */

        .thead { background: #f5f9f2; }

        .title { font-weight: 800; font-size: 14px; }
        .subttl { font-weight: 700; }
        .small { font-size: 10px; color: #666; }

        .total-row { background: #f7f7f7; font-weight: 700; }
        .head-row  { background: #eaf2e1; font-weight: 700; }

        .no-b { border: 0 none; }
        .right { text-align: right; }
        .center { text-align: center; }

        .ibox {
            border: 1px solid #a7a7a7;
            height: 22px;
            padding: 3px 6px;
        }

        .pad { padding: 8px; }
        .mt6 { margin-top: 6px; }
        .mt10 { margin-top: 10px; }
        .year { font-weight: 800; text-align: center; }

        /* keep long text readable */
        .wrap { word-break: break-word; white-space: normal; }
    </style>
</head>
<body>

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
        <td class="no-b small">ATAI Group</td>
        <td class="no-b"></td>
        <td class="no-b"></td>
        <td class="no-b"></td>
    </tr>
</table>

<!-- Three header panels -->
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
                    <td class="no-b"><div class="ibox">{{ $region }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Month/Year</td>
                    <td class="no-b"><div class="ibox">{{ $monthYear }}</div></td>
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

        <!-- Forecasting Criteria -->
        <td class="pad">
            <table class="no-b">
                <tr class="no-b">
                    <td class="no-b" style="width:55%;">Price Agreed</td>
                    <td class="no-b"><div class="ibox">{{ $criteria['price_agreed'] ?? '' }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Consultant Approval</td>
                    <td class="no-b"><div class="ibox">{{ $criteria['consultant_approval'] ?? '' }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Overall Percentage</td>
                    <td class="no-b"><div class="ibox">{{ $criteria['percentage'] ?? '' }}</div></td>
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
                            {{ isset($forecast['month_target']) ? $fmt0($forecast['month_target']) : '' }}
                        </div>
                    </td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Required Turn-over</td>
                    <td class="no-b"><div class="ibox">{{ $forecast['required_turnover'] ?? '' }}</div></td>
                </tr>
                <tr class="no-b">
                    <td class="no-b">Required Forecast</td>
                    <td class="no-b"><div class="ibox">{{ $forecast['required_forecast'] ?? '' }}</div></td>
                </tr>
                {{-- If you removed conversion_ratio in UI, keep it safe here --}}
                <tr class="no-b">
                    <td class="no-b">Conversion Ratio</td>
                    <td class="no-b"><div class="ibox">{{ $forecast['conversion_ratio'] ?? '' }}</div></td>
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
        <th style="width:30px;">Serial</th>
        <th style="width:120px;">Customer Name</th>
        <th style="width:120px;">Products</th>
        <th style="width:120px;">Project Name</th>
        <th style="width:120px;">Quotation No.</th>

        {{-- ✅ NEW --}}
        <th style="width:30px;" class="center">% (≥75)</th>
        <th style="width:100px;">Product Family</th>
        <th style="width:80px;">Sales Source</th>

        <th style="width:50px;" class="right">Value</th>
        <th>Remarks</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rowsA as $i => $r)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td class="wrap">{{ $r['customer'] }}</td>
            <td class="wrap">{{ $r['product'] }}</td>
            <td class="wrap">{{ $r['project'] }}</td>
            <td class="center">{{ $r['quotation'] }}</td>

            {{-- ✅ NEW --}}
            <td class="center">{{ $r['percentage'] !== '' ? $r['percentage'].'%' : '' }}</td>
            <td class="center">{{ $r['product_family'] }}</td>
            <td class="center">{{ $r['sales_source'] }}</td>

            <td class="right">{{ $fmt0($r['value']) }}</td>
            <td class="wrap">{{ $r['remarks'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="10" class="center small">No rows</td>
        </tr>
    @endforelse

    <tr class="total-row">
        <td colspan="8" class="right">Total New Current Month Forecasted Orders</td>
        <td class="right">{{ $fmt0($totA) }}</td>
        <td></td>
    </tr>
    </tbody>
</table>

<!-- B) Carry-Over -->
<div class="subttl mt10">B) Carry-Over (from the previous month and expected to close in the current month)</div>
<table class="mt6">
    <thead class="thead">
    <tr class="head-row">
        <th style="width:30px;">Serial</th>
        <th style="width:120px;">Customer Name</th>
        <th style="width:120px;">Products</th>
        <th style="width:120px;">Project Name</th>
        <th style="width:120px;">Quotation No.</th>

        {{-- ✅ NEW --}}
        <th style="width:30px;" class="center">% (≥75)</th>
        <th style="width:100px;">Product Family</th>
        <th style="width:80px;">Sales Source</th>

        <th style="width:50px;" class="right">Value</th>
        <th>Remarks</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rowsB as $i => $r)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td class="wrap">{{ $r['customer'] }}</td>
            <td class="wrap">{{ $r['product'] }}</td>
            <td class="wrap">{{ $r['project'] }}</td>
            <td class="center">{{ $r['quotation'] }}</td>

            {{-- ✅ NEW --}}
            <td class="center">{{ $r['percentage'] !== '' ? $r['percentage'].'%' : '' }}</td>
            <td class="center">{{ $r['product_family'] }}</td>
            <td class="center">{{ $r['sales_source'] }}</td>

            <td class="right">{{ $fmt0($r['value']) }}</td>
            <td class="wrap">{{ $r['remarks'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="10" class="center small">No rows</td>
        </tr>
    @endforelse

    <tr class="total-row">
        <td colspan="8" class="right">Total Carry-over</td>
        <td class="right">{{ $fmt0($totB) }}</td>
        <td></td>
    </tr>
    </tbody>
</table>

<!-- This Month Total band -->
<table class="no-b" style="margin-top:8px; margin-bottom:6px;">
    <tr class="no-b">
        <td class="no-b"></td>
        <td class="no-b" style="text-align:right; font-weight:700;">This Month Total :</td>
        <td class="no-b" style="width:160px;">
            <div class="ibox">{{ $fmt0($totAll) }}</div>
        </td>
    </tr>
</table>

<p class="small mt10">ATAI · Generated {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
