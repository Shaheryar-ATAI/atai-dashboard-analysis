<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATAI – Salesman Summary {{ $year }}</title>
    <style>
        /* --------- Base page ---------- */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            background: #e5e7eb; /* light grey page */
        }

        h1, h2, h3, h4 {
            margin: 0;
            color: #111827;
        }

        .page {
            background: #ffffff;
            border-radius: 10px;
            padding: 16px 18px;
        }

        /* --------- Header row ---------- */
        .header {
            width: 100%;
            margin-bottom: 8px;
        }
        .header-left { float: left; }
        .header-right {
            float: right;
            text-align: right;
            font-size: 9px;
            color: #6b7280;
        }
        .subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-top: 3px;
        }
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* --------- KPI strip ---------- */
        .kpi-strip{ margin-top:10px; margin-bottom:10px; width:100%; }

        .kpi-row{
            width:100%;
            border-collapse:collapse;     /* ✅ IMPORTANT for dompdf */
            table-layout:fixed;           /* ✅ forces 3 equal columns */
        }
        .kpi-row td{
            width:33.333%;
            vertical-align:top;
            padding-right:8px;            /* spacing between cards */
            background: transparent !important;
            border: 0 !important;
        }
        .kpi-row td:last-child{ padding-right:0; }

        .kpi-card{
            width:100%;
            padding:8px 10px;
            border-radius:8px;
            border:1px solid #e5e7eb;
            background:#f9fafb;
        }

        .kpi-label, .kpi-footnote{ font-size:8px; }
        .kpi-footnote{
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .kpi-value{ font-size:13px; font-weight:700; margin-top:3px; }
        /* --------- Summary blocks ---------- */
        .summary-card {
            margin-top: 8px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            padding: 8px 10px;
        }
        .summary-title {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
            color: #374151;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table th,
        .summary-table td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 9px;
        }
        .summary-table th {
            background: #e5e7eb;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #4b5563;
        }
        .summary-table .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* --------- Section titles ---------- */
        .section-title {
            margin-top: 10px;
            font-size: 10px;
            font-weight: 700;
            color: #111827;
        }
        .section-sub {
            font-size: 8px;
            color: #6b7280;
            margin-bottom: 2px;
        }

        /* --------- Data tables ---------- */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data th,
        table.data td {
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            font-size: 8.5px;
        }
        table.data th {
            background: #f3f4f6;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #4b5563;
        }
        table.data td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* --------- Salesman badges (same idea as Area badges) ---------- */
        .salesman-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
        }
        .sohaib { background:#0ea5e9; color:#083344; }
        .tariq  { background:#a78bfa; color:#312e81; }
        .jamal  { background:#22c55e; color:#064e3b; }
        .abdo   { background:#f97316; color:#7c2d12; }
        .ahmed  { background:#facc15; color:#78350f; }
        .other  { background:#e5e7eb; color:#374151; }
        .area-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
        }
        .central { background:#22c55e; color:#064e3b; }
        .eastern { background:#0ea5e9; color:#083344; }
        .western { background:#f97316; color:#7c2d12; }
    </style>
</head>
<body>
<div class="page">

    {{-- ========= HEADER ========= --}}
    <div class="header clearfix">
        <div class="header-left">
            <h1>ATAI – Salesman Summary</h1>
            <div class="subtitle">Year: {{ $year }}</div>
        </div>
        <div class="header-right">
            <div>Report Date</div>
            <div><strong>{{ $today }}</strong></div>
        </div>
    </div>

    {{-- ========= KPI STRIP ========= --}}
    @php
        $inqTotal = (float)($kpis['inquiries_total'] ?? 0);
        $poTotal  = (float)($kpis['pos_total'] ?? 0);
        $gapVal   = (float)($kpis['gap_value'] ?? ($inqTotal - $poTotal));
        $gapPct   = (float)($kpis['gap_percent'] ?? ($inqTotal > 0 ? round($poTotal / $inqTotal * 100, 1) : 0));

        // badge class helper
        $badgeClass = function($name) {
            $k = strtolower(trim($name));
            if (str_contains($k, 'sohaib')) return 'sohaib';
            if (str_contains($k, 'tariq')) return 'tariq';
            if (str_contains($k, 'jamal')) return 'jamal';
            if (str_contains($k, 'abdo')) return 'abdo';
            if (str_contains($k, 'ahmed')) return 'ahmed';
            return 'other';
        };
    @endphp

    <div class="kpi-strip">
        <table class="kpi-row">
            <tr>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">Inquiries Total</div>
                        <div class="kpi-value">SAR {{ number_format($inqTotal, 0) }}</div>
                        <div class="kpi-footnote">Sum of enquiry values (projects)</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">POs Total</div>
                        <div class="kpi-value">SAR {{ number_format($poTotal, 0) }}</div>
                        <div class="kpi-footnote">Sum of PO values (sales orders)</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">Gap Coverage (POs vs Quotations)</div>
                        <div class="kpi-value">{{ $gapPct }}%</div>
                        <div class="kpi-footnote">
                            Gap: SAR {{ number_format($gapVal, 0) }}<br>
                            {{ $poTotal < $inqTotal ? 'More quoted than POs' : 'More POs than quoted' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ========= SALESMAN SUMMARY (replaces Area Summary card) ========= --}}
    @php
        $salesmanSummary = [];

        foreach ($inquiriesBySalesman as $salesman => $row) {
            $inqTotal = (float) end($row);

            $poRow    = $posBySalesman[$salesman] ?? array_fill(0, count($row), 0);
            $poTotal  = (float) end($poRow);

            $salesmanSummary[] = [
                'salesman' => $salesman,
                'inq' => $inqTotal,
                'pos' => $poTotal,
            ];
        }
    @endphp

    <div class="summary-card">
        <div class="summary-title">
            Quotations vs POs by Salesman — Year {{ $year }}
        </div>
        <table class="summary-table">
            <thead>
            <tr>
                <th>Salesman</th>
                <th class="num">Quotations (SAR)</th>
                <th class="num">POs Received (SAR)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($salesmanSummary as $row)
                <tr>
                    <td>
                        <span class="salesman-badge {{ $badgeClass($row['salesman']) }}">
                            {{ strtoupper($row['salesman']) }}
                        </span>
                    </td>
                    <td class="num">SAR {{ number_format($row['inq'], 0) }}</td>
                    <td class="num">SAR {{ number_format($row['pos'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{--    --}}{{-- ========= INQUIRIES TABLE ========= --}}
    {{--    <div class="section-title">Inquiries (Estimations) — sums by salesman</div>--}}
    {{--    <div class="section-sub">Values by month based on quotation date (projects table).</div>--}}

    {{--    <table class="data">--}}
    {{--        <thead>--}}
    {{--        <tr>--}}
    {{--            <th>Salesman</th>--}}
    {{--            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)--}}
    {{--                <th class="num">{{ strtoupper($m) }}</th>--}}
    {{--            @endforeach--}}
    {{--        </tr>--}}
    {{--        </thead>--}}
    {{--        <tbody>--}}
    {{--        @foreach($inquiriesBySalesman as $salesman => $row)--}}
    {{--            <tr>--}}
    {{--                <td>--}}
    {{--                    <span class="salesman-badge {{ $badgeClass($salesman) }}">--}}
    {{--                        {{ strtoupper($salesman) }}--}}
    {{--                    </span>--}}
    {{--                </td>--}}
    {{--                @foreach($row as $val)--}}
    {{--                    <td class="num">SAR {{ number_format($val, 0) }}</td>--}}
    {{--                @endforeach--}}
    {{--            </tr>--}}
    {{--        @endforeach--}}
    {{--        </tbody>--}}
    {{--    </table>--}}
    {{-- ========= REGION SUMMARY (Salesman grouped) ========= --}}
    @php
        // build region summary for the top card (totals from last column)
        $regionSummary = [];
        foreach ($inqByRegion as $region => $row) {
            $inqTot = (float) end($row);
            $poRow  = $poByRegion[$region] ?? array_fill(0, count($row), 0);
            $poTot  = (float) end($poRow);

            $regionSummary[] = [
                'region' => $region,
                'inq' => $inqTot,
                'pos' => $poTot,
            ];
        }
    @endphp

    <div class="summary-card">
        <div class="summary-title">
            Quotations vs POs by Region (Salesman grouping) — Year {{ $year }}
        </div>
        <table class="summary-table">
            <thead>
            <tr>
                <th>Region</th>
                <th class="num">Quotations (SAR)</th>
                <th class="num">POs Received (SAR)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($regionSummary as $r)
                @php $aLower = strtolower($r['region']); @endphp
                <tr>
                    <td>
                        <span class="area-badge {{ $aLower }}">{{ strtoupper($r['region']) }}</span>
                    </td>
                    <td class="num">SAR {{ number_format($r['inq'], 0) }}</td>
                    <td class="num">SAR {{ number_format($r['pos'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- ========= INQUIRIES BY REGION TABLE ========= --}}
    <div class="section-title">Inquiries (Estimations) — sums by region (Salesman grouping)</div>
    <div class="section-sub">Eastern = Sohaib, Central = Tariq + Jamal, Western = Abdo + Ahmed.</div>

    <table class="data">
        <thead>
        <tr>
            <th>Region</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($inqByRegion as $region => $row)
            <tr>
                @php $aLower = strtolower($region); @endphp
                <td><span class="area-badge {{ $aLower }}">{{ strtoupper($region) }}</span></td>
                @foreach($row as $val)
                    <td class="num">SAR {{ number_format($val, 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- ========= POS BY REGION TABLE ========= --}}
    <div class="section-title" style="margin-top:12px;">
        POs (Sales Orders Received) — sums by region (Salesman grouping)
    </div>
    <div class="section-sub">Values by month based on PO received date (salesorderlog table).</div>

    <table class="data">
        <thead>
        <tr>
            <th>Region</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($poByRegion as $region => $row)
            <tr>
                @php $aLower = strtolower($region); @endphp
                <td><span class="area-badge {{ $aLower }}">{{ strtoupper($region) }}</span></td>
                @foreach($row as $val)
                    <td class="num">SAR {{ number_format($val, 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    {{-- ========= POS TABLE ========= --}}
    <div class="section-title" style="margin-top:12px;">
        POs (Sales Orders Received) — sums by salesman
    </div>
    <div class="section-sub">Values by month based on PO received date (salesorderlog table).</div>

    <table class="data">
        <thead>
        <tr>
            <th>Salesman</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($posBySalesman as $salesman => $row)
            <tr>
                <td>
                    <span class="salesman-badge {{ $badgeClass($salesman) }}">
                        {{ strtoupper($salesman) }}
                    </span>
                </td>
                @foreach($row as $val)
                    <td class="num">SAR {{ number_format($val, 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

</div>
</body>
</html>
