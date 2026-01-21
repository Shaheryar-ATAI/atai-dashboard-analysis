<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATAI – Salesman Summary {{ $year }}</title>

    <style>
        /* ============================================================
           ATAI – DOMPDF PROFESSIONAL REPORT (A4 LANDSCAPE)
           - DOMPDF-safe: avoid flex reliance, use tables/float
           - Consistent styling across all tables
           - Fixes: "Parent table not found for table cell"
           - Adds: Region Distribution Matrix with flags
        ============================================================ */

        /* A4 landscape gives enough width for matrix tables */
        @page { margin: 14px 14px; size: A4 landscape; }

        * { box-sizing: border-box; }

        body{
            margin:0; padding:0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color:#111827;
            background:#ffffff;
        }

        .page{ background:#ffffff; padding: 12px 12px; }
        .page-break{ page-break-before: always; }

        /* ---------------- SECTION TITLES ---------------- */
        .section-title{ margin-top:10px; font-size:10px; font-weight:700; color:#111827; }
        .section-sub{ font-size:8px; color:#6b7280; margin-bottom:2px; }

        /* ---------------- HEADER (DOMPDF-safe using table) ---------------- */
        .header-table{
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
            margin-bottom: 6px;
        }
        .header-table td{
            vertical-align:top;
            padding:0;
            border:0 !important;
        }
        .header-title{ width:55%; }
        .header-meta{ width:45%; text-align:right; }
        .h1{
            font-size:16px;
            font-weight:800;
            margin:0;
            line-height:1.15;
            color:#111827;
        }
        .report-date{
            font-size:9px;
            color:#6b7280;
            margin-top:2px;
        }

        /* pills */
        .report-context{ margin-top:4px; white-space:nowrap; }
        .context-pill{
            display:inline-block;
            padding:3px 10px;
            border-radius:999px;
            font-size:9px;
            font-weight:800;
            line-height:1.1;
            border:1px solid #e5e7eb;
            background:#f3f4f6;
            color:#111827;
            margin-left:6px;
        }
        .context-pill.year{
            background:#eef2ff;
            border-color:#c7d2fe;
            color:#1e3a8a;
        }
        .context-pill.area.all{ background:#ede9fe; border-color:#ddd6fe; color:#4c1d95; }
        .context-pill.area.eastern{ background:#e0f2fe; border-color:#bae6fd; color:#075985; }
        .context-pill.area.central{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
        .context-pill.area.western{ background:#ffedd5; border-color:#fed7aa; color:#9a3412; }

        /* ---------------- KPI STRIP ---------------- */
        .kpi-strip{ margin-top:10px; margin-bottom:10px; width:100%; }
        .kpi-row{ width:100%; border-collapse:collapse; table-layout:fixed; }
        .kpi-row td{ width:33.333%; vertical-align:top; padding-right:8px; border:0 !important; }
        .kpi-row td:last-child{ padding-right:0; }

        .kpi-card{
            width:100%;
            padding:8px 10px;
            border-radius:8px;
            border:1px solid #e5e7eb;
            background:#f9fafb;
        }
        .kpi-label{ font-size:8px; color:#6b7280; }
        .kpi-value{ font-size:13px; font-weight:800; margin-top:3px; }
        .kpi-footnote{ font-size:8px; color:#6b7280; margin-top:3px; line-height:1.2; }

        /* ---------------- SUMMARY CARDS ---------------- */
        .summary-card{
            margin-top: 8px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 8px 10px;
        }
        .summary-title{
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 6px;
            color: #374151;
        }

        .summary-table{
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
        }
        .summary-table th,
        .summary-table td{
            border:1px solid #e5e7eb;
            padding:4px 6px;
            font-size:9px;
        }
        .summary-table th{
            background:#f3f4f6;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#4b5563;
            text-align:left;
        }
        .summary-table td.num{
            text-align:right;
            white-space:nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* ---------------- TABLE BASICS (DOMPDF paging rules) ---------------- */
        table{ width:100%; border-collapse:collapse; page-break-inside:auto; }
        thead{ display: table-header-group; }
        tfoot{ display: table-footer-group; }
        tr{ page-break-inside: auto; }
        .keep-together{ page-break-inside: avoid; }

        /* ---------------- DATA TABLES (Region/Salesman monthly) ---------------- */
        table.data{
            table-layout: fixed;
            margin-top: 6px;
        }
        table.data th, table.data td{
            border:1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 9px;
            line-height:1.15;
        }
        table.data th{
            background:#f3f4f6;
            text-transform: uppercase;
            letter-spacing:.04em;
            color:#4b5563;
        }
        table.data td.num{
            text-align:right;
            white-space:nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* ---------------- MATRIX TABLES (Product + Performance) ---------------- */
        table.matrix{
            table-layout: fixed;
            margin-top: 6px;
        }
        table.matrix th, table.matrix td{
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            vertical-align: middle;
            font-size: 8px;
            line-height: 1.15;
        }
        table.matrix th{
            background:#f3f4f6;
            text-transform: uppercase;
            letter-spacing:.04em;
            color:#4b5563;
            font-size: 8px;
        }

        .matrix .salesmanCell,
        .matrix .rowLabelCell{
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .matrix .salesmanCell{
            background:#fafafa;
            font-weight:800;
            vertical-align: top;
            padding-top: 6px;
        }
        .matrix .rowLabelCell{
            background:#ffffff;
            font-weight:800;
        }
        .matrix td.num{
            text-align:right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .matrix td.pct{
            text-align:right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .matrix td.dash{
            text-align:center;
            color:#9ca3af;
        }

        /* ---------------- BADGES (Salesman/Region Pills) ---------------- */
        .salesman-badge,
        .area-badge{
            display:inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
        }

        /* Salesmen */
        .sohaib { background:#0ea5e9; color:#083344; }
        .tariq  { background:#a78bfa; color:#312e81; }
        .jamal  { background:#22c55e; color:#064e3b; }
        .abdo   { background:#f97316; color:#7c2d12; }
        .ahmed  { background:#facc15; color:#78350f; }
        .other  { background:#e5e7eb; color:#374151; }

        /* Regions */
        .central{ background:#22c55e; color:#064e3b; }
        .eastern{ background:#0ea5e9; color:#083344; }
        .western{ background:#f97316; color:#7c2d12; }

        /* ---------------- Product mix flags (pills) ---------------- */
        .flag-pill{
            display:inline-block;
            padding:1px 6px;
            border-radius:999px;
            font-size:7px;
            font-weight:900;
            margin-left:6px;
            white-space:nowrap;
            border:1px solid #e5e7eb;
        }
        .flag-high{ background:#fee2e2; color:#991b1b; border-color:#fecaca; } /* red */
        .flag-low { background:#ffedd5; color:#9a3412; border-color:#fed7aa; } /* orange */
        .flag-ok  { background:#dcfce7; color:#166534; border-color:#bbf7d0; } /* green */
        .small-muted{ font-size:7px; color:#6b7280; margin-left:6px; white-space:nowrap; }

        /* ---------------- Performance flags ---------------- */
        .flag{
            display:inline-block;
            padding:2px 10px;
            border-radius:999px;
            font-size:8px;
            font-weight:900;
            letter-spacing:.02em;
            white-space:nowrap;
        }
        .flag-excellent{ background:#dcfce7; color:#14532d; border:1px solid #86efac; }
        .flag-good{ background:#e0f2fe; color:#075985; border:1px solid #7dd3fc; }
        .flag-acceptable{ background:#fef9c3; color:#713f12; border:1px solid #fde047; }
        .flag-attn{ background:#ffedd5; color:#7c2d12; border:1px solid #fdba74; }
        .flag-danger{ background:#fee2e2; color:#7f1d1d; border:1px solid #fca5a5; }
        .flag-na{ background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }

        /* ============================================================
           ✅ REGION DISTRIBUTION MATRIX (matches table.data look)
           - Put inside a .page (critical for DOMPDF)
           - Uses colgroup widths to keep alignment stable
        ============================================================ */
        table.region-matrix{
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
            margin-top: 10px;
        }
        table.region-matrix th, table.region-matrix td{
            border:1px solid #e5e7eb;
            padding:4px 6px;
            font-size:9px;
            line-height:1.15;
        }
        table.region-matrix th{
            background:#f3f4f6;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#4b5563;
        }
        .region-matrix td.num{
            text-align:right;
            white-space:nowrap;
            font-variant-numeric: tabular-nums;
        }
        .region-matrix .salesmanCell{
            background:#fafafa;
            font-weight:800;
            vertical-align:top;
        }
        .region-matrix .regionCell{
            color:#4b5563;
            font-weight:700;
        }
        .pill{
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            font-size:8px;
            font-weight:900;
            white-space:nowrap;
            border:1px solid #e5e7eb;
            margin-left:6px;
        }
        .pill-flag{ background:#fef3c7; color:#78350f; border-color:#fde68a; }  /* FLAG */
        .pill-dom { background:#fee2e2; color:#7f1d1d; border-color:#fecaca; }  /* DOMINANT */
        .section-title{
            margin-top:12px;
            font-size:14px;
            font-weight:700;
            color:#111827;
            text-align:center;   /* ✅ add this */
        }
    </style>
</head>

<body>

@php
    /* ============================================================
       Helpers
    ============================================================ */
    $sar = function($v){
        $v = (float)$v;
        return $v == 0 ? '-' : 'SAR '.number_format($v,0);
    };

    $inqTotal = (float)($kpis['inquiries_total'] ?? 0);
    $poTotal  = (float)($kpis['pos_total'] ?? 0);

    $gapVal = (float)($kpis['gap_value'] ?? ($inqTotal - $poTotal));
    $gapPct = (float)($kpis['gap_percent'] ?? ($inqTotal > 0 ? round($poTotal / $inqTotal * 100, 1) : 0));

    $badgeClass = function($name) {
        $k = strtolower(trim((string)$name));
        if (str_contains($k, 'sohaib')) return 'sohaib';
        if (str_contains($k, 'tariq')) return 'tariq';
        if (str_contains($k, 'jamal')) return 'jamal';
        if (str_contains($k, 'abdo')) return 'abdo';
        if (str_contains($k, 'ahmed')) return 'ahmed';
        return 'other';
    };

    /* ============================================================
       Targets / Region-to-salesmen mapping
    ============================================================ */
    $yearlyTargets = [
        'Eastern' => 50000000,
        'Central' => 50000000,
        'Western' => 36000000,
    ];

    // Expected region per salesman (your business rule)
    $salesmanToRegion = [
        'SOHAIB' => 'Eastern',
        'TARIQ'  => 'Central',
        'JAMAL'  => 'Central',
        'ABDO'   => 'Western',
        'AHMED'  => 'Western',
    ];

    // How many salesmen share each region (Central has 2)
    $regionSalesmenCount = [];
    foreach ($salesmanToRegion as $s => $r) {
        $regionSalesmenCount[$r] = ($regionSalesmenCount[$r] ?? 0) + 1;
    }

    // Inject TARGET rows into salesmanKpiMatrix
    foreach (($salesmanKpiMatrix ?? []) as $salesman => &$metrics) {
        $sKey = strtoupper(trim($salesman));
        $region = $salesmanToRegion[$sKey] ?? null;

        $yearTarget = $region ? ($yearlyTargets[$region] ?? 0) : 0;
        $monthlyTarget = $yearTarget > 0 ? ($yearTarget / 12) : 0;

        // split if multiple salesmen share region target
        $div = $region ? max(1, ($regionSalesmenCount[$region] ?? 1)) : 1;
        $salesmanMonthlyTarget = $monthlyTarget / $div;

        $targetRow = array_fill(0, 12, $salesmanMonthlyTarget);
        $targetRow[] = $salesmanMonthlyTarget * 12;

        $metrics['TARGET'] = $targetRow;
    }
    unset($metrics);
@endphp

{{-- ============================================================
   PAGE 1: Header + KPIs + Summary cards
============================================================ --}}
<div class="page">

    <!-- ========= HEADER ========= -->
    <table class="header-table">
        <tr>
            <td class="header-title">
                <div class="h1">ATAI – Salesman Summary</div>
            </td>
            <td class="header-meta">
                <div class="report-date">
                    Report Date<br>
                    <strong>{{ $today }}</strong>
                </div>

                @php
                    $areaNorm = $area ?? 'All';
                    $areaNorm = ucfirst(strtolower(trim($areaNorm)));
                    if (!in_array($areaNorm, ['Eastern','Central','Western'], true)) $areaNorm = 'All';
                @endphp

                <div class="report-context">
                    <span class="context-pill year">Year: {{ $year }}</span>
                    <span class="context-pill area {{ strtolower($areaNorm) }}">
                        Area: {{ strtoupper($areaNorm) }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ========= KPI STRIP ========= -->
    <div class="kpi-strip">
        <table class="kpi-row">
            <tr>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">Inquiries Total</div>
                        <div class="kpi-value">{{ $sar($inqTotal) }}</div>
                        <div class="kpi-footnote">Sum of enquiry values (projects)</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">POs Total</div>
                        <div class="kpi-value">{{ $sar($poTotal) }}</div>
                        <div class="kpi-footnote">Sum of PO values (sales orders)</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-card">
                        <div class="kpi-label">Gap Coverage (POs vs Quotations)</div>
                        <div class="kpi-value">{{ number_format($gapPct, 1) }}%</div>
                        <div class="kpi-footnote">
                            Gap: {{ $sar($gapVal) }}<br>
                            {{ $poTotal < $inqTotal ? 'More quoted than POs' : 'More POs than quoted' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ========= YEARLY TARGETS ========= -->
    @php
        $targetRows = [
            'Eastern' => ['badge' => 'eastern', 'target' => $yearlyTargets['Eastern'] ?? 0, 'salesmen' => ['SOHAIB' => 'sohaib']],
            'Central' => ['badge' => 'central', 'target' => $yearlyTargets['Central'] ?? 0, 'salesmen' => ['TARIQ' => 'tariq', 'JAMAL' => 'jamal']],
            'Western' => ['badge' => 'western', 'target' => $yearlyTargets['Western'] ?? 0, 'salesmen' => ['ABDO' => 'abdo', 'AHMED' => 'ahmed']],
        ];

        if ($areaNorm !== 'All') {
            $targetRows = array_intersect_key($targetRows, [$areaNorm => true]);
        }
    @endphp

    <div class="summary-card keep-together">
        <div class="summary-title">Yearly Target (SAR) — {{ $year }}</div>
        <table class="summary-table">
            <thead>
            <tr>
                <th>Region</th>
                <th class="num">Target (Year)</th>
                <th>Salesmen</th>
            </tr>
            </thead>
            <tbody>
            @foreach($targetRows as $region => $info)
                <tr>
                    <td><span class="area-badge {{ $info['badge'] }}">{{ strtoupper($region) }}</span></td>
                    <td class="num">SAR {{ number_format((float)$info['target'],0) }}</td>
                    <td>
                        @foreach($info['salesmen'] as $name => $cls)
                            <span class="salesman-badge {{ $cls }}">{{ $name }}</span>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <!-- ========= SUMMARY: BY SALESMAN ========= -->
    @php
        $salesmanSummary = [];
        foreach (($inquiriesBySalesman ?? []) as $salesman => $row) {
            $inqT = (float) end($row);
            $poRow = ($posBySalesman[$salesman] ?? array_fill(0, count($row), 0));
            $poT = (float) end($poRow);
            $salesmanSummary[] = ['salesman'=>$salesman, 'inq'=>$inqT, 'pos'=>$poT];
        }
    @endphp

    <div class="summary-card">
        <div class="summary-title">Quotations vs POs by Salesman — Year {{ $year }}</div>
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
                    <td><span class="salesman-badge {{ $badgeClass($row['salesman']) }}">{{ strtoupper($row['salesman']) }}</span></td>
                    <td class="num">{{ $sar($row['inq']) }}</td>
                    <td class="num">{{ $sar($row['pos']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <!-- ========= SUMMARY: BY REGION ========= -->
    @php
        $regionSummary = [];
        foreach (($inqByRegion ?? []) as $region => $row) {
            $inqTot = (float) end($row);
            $poRow  = ($poByRegion[$region] ?? array_fill(0, count($row), 0));
            $poTot  = (float) end($poRow);
            $regionSummary[] = ['region'=>$region, 'inq'=>$inqTot, 'pos'=>$poTot];
        }
    @endphp

    <div class="summary-card">
        <div class="summary-title">Quotations vs POs by Region — Year {{ $year }}</div>
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
                    <td><span class="area-badge {{ $aLower }}">{{ strtoupper($r['region']) }}</span></td>
                    <td class="num">{{ $sar($r['inq']) }}</td>
                    <td class="num">{{ $sar($r['pos']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

</div>


{{-- ============================================================
   PAGE 2: Inquiries & POs monthly + ✅ Region Distribution Matrix
   IMPORTANT: This table MUST be inside .page for DOMPDF stability
============================================================ --}}
<div class="page-break"></div>
<div class="page">

{{--    <div class="section-title">Inquiries (Estimations) — sums by region (Salesman grouping)</div>--}}
{{--    <div class="section-sub">Eastern = Sohaib, Central = Tariq + Jamal, Western = Abdo + Ahmed.</div>--}}

{{--    <table class="data">--}}
{{--        <thead>--}}
{{--        <tr>--}}
{{--            <th>Region</th>--}}
{{--            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)--}}
{{--                <th class="num">{{ strtoupper($m) }}</th>--}}
{{--            @endforeach--}}
{{--        </tr>--}}
{{--        </thead>--}}
{{--        <tbody>--}}
{{--        @foreach(($inqByRegion ?? []) as $region => $row)--}}
{{--            <tr>--}}
{{--                @php $aLower = strtolower($region); @endphp--}}
{{--                <td><span class="area-badge {{ $aLower }}">{{ strtoupper($region) }}</span></td>--}}
{{--                @foreach($row as $val)--}}
{{--                    <td class="num">{{ $sar($val) }}</td>--}}
{{--                @endforeach--}}
{{--            </tr>--}}
{{--        @endforeach--}}
{{--        </tbody>--}}
{{--    </table>--}}

    <div class="section-title" style="margin-top:12px;">POs (Sales Orders Received) — sums by region</div>
    <div class="section-sub">Values by month based on PO received date (salesorderlog).</div>

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
        @foreach(($poByRegion ?? []) as $region => $row)
            <tr>
                @php $aLower = strtolower($region); @endphp
                <td><span class="area-badge {{ $aLower }}">{{ strtoupper($region) }}</span></td>
                @foreach($row as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

{{--    <div class="section-title" style="margin-top:12px;">POs (Sales Orders Received) — sums by salesman</div>--}}
{{--    <div class="section-sub">Values by month based on PO received date (salesorderlog).</div>--}}

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
{{--        @foreach(($posBySalesman ?? []) as $salesman => $row)--}}
{{--            <tr>--}}
{{--                <td><span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span></td>--}}
{{--                @foreach($row as $val)--}}
{{--                    <td class="num">{{ $sar($val) }}</td>--}}
{{--                @endforeach--}}
{{--            </tr>--}}
{{--        @endforeach--}}
{{--        </tbody>--}}
{{--    </table>--}}

    {{-- ============================================================
       ✅ PO REGION DISTRIBUTION MATRIX (Sohaib/Eastern rule)
       - FLAG if expected region share < 50%
       - DOMINANT if any other region >= 50%
    ============================================================ --}}
    @php
        $months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'];
        $regions = ['Eastern','Central','Western'];
        $fmt = fn($v) => number_format((float)$v, 0);

        $expectedRegionOf = function(string $salesman){
            $s = strtoupper(trim($salesman));
            if ($s === 'SOHAIB') return 'Eastern';
            if (in_array($s, ['TARIQ','JAMAL'], true)) return 'Central';
            if (in_array($s, ['ABDO','AHMED'], true)) return 'Western';
            return 'Other';
        };
    @endphp

    <div class="section-title" style="margin-top:12px;">POs — Region Distribution by Salesman</div>
{{--    <div class="section-sub">Rule: if expected region share is below 50% → FLAG. If any other region ≥ 50% → DOMINANT.</div>--}}

    <table class="region-matrix">
        <colgroup>
            <col style="width:150px;">
            <col style="width:140px;">
            @for($i=0;$i<12;$i++) <col style="width:55px;"> @endfor
            <col style="width:75px;">
        </colgroup>

        <thead>
        <tr>
            <th>Salesman</th>
            <th>Project Region</th>
            @foreach($months as $m)
                <th style="text-align:right;">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @foreach(($poRegionMatrix ?? []) as $salesman => $byRegion)
            @php
                $expected = $expectedRegionOf($salesman);

                $totE = (float)($byRegion['Eastern'][12] ?? 0);
                $totC = (float)($byRegion['Central'][12] ?? 0);
                $totW = (float)($byRegion['Western'][12] ?? 0);
                $grand = $totE + $totC + $totW;

                $share = function(string $rg) use ($totE,$totC,$totW,$grand){
                    if ($grand <= 0) return 0.0;
                    $v = ($rg === 'Eastern') ? $totE : (($rg === 'Central') ? $totC : (($rg === 'Western') ? $totW : 0));
                    return $v / $grand;
                };

                $expectedShare = $share($expected);
                $flagSalesman  = ($grand > 0 && $expected !== 'Other' && $expectedShare < 0.50);
            @endphp

            @foreach($regions as $i => $rg)
                @php
                    $row = $byRegion[$rg] ?? array_fill(0, 13, 0);
                    $rgShare = $share($rg);
                    $flagRegion = ($grand > 0 && $expected !== 'Other' && $rg !== $expected && $rgShare >= 0.50);
                @endphp

                <tr>
                    @if($i === 0)
                        <td rowspan="{{ count($regions) }}" class="salesmanCell">
                            <span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span>

                            @if($flagSalesman)
                                <span class="pill pill-flag">FLAG</span>
                            @endif

                            <br>
{{--                            <span class="small-muted">--}}
{{--                                Expected: {{ $expected }} @if($grand>0) ({{ round($expectedShare*100,1) }}%) @endif--}}
{{--                            </span>--}}
                        </td>
                    @endif

                    <td class="regionCell">
                        {{ $rg }}
                        @if($grand>0) ({{ round($rgShare*100,1) }}%) @endif
                        @if($flagRegion)
                            <span class="pill pill-dom">DOMINANT</span>
                        @endif
                    </td>

                    @for($c=0; $c<13; $c++)
                        <td class="num">{{ $fmt($row[$c] ?? 0) }}</td>
                    @endfor
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>

</div>


{{-- ============================================================
   PAGE 3: Product matrices + Product mix flags
============================================================ --}}
@php
    // Product mix targets (percent of TOTAL per salesman)
    $mixTargets = [
        'DUCTWORK'          => 60,
        'ACCESSORIES'       => 10,
        'SOUND ATTENUATORS' => 10,
        'DAMPERS'           => 10,
        'LOUVERS'           => 10,
    ];

    // tolerance
    $tolHigh = 8;
    $tolLow  = 8;

    $mixFlag = function(string $product, float $actualPct) use ($mixTargets, $tolHigh, $tolLow) {
        $p = strtoupper(trim($product));
        $t = $mixTargets[$p] ?? null;
        if ($t === null) return ['', ''];

        // HARD RULE: 0% => danger
        if ($actualPct == 0.0) return ['Critical', 'flag-high'];

        if ($actualPct > ($t + $tolHigh)) return ['HIGH', 'flag-high'];
        if ($actualPct < ($t - $tolLow))  return ['LOW',  'flag-low'];

        return ['', ''];
    };

    $salesmanTotalFromProducts = function(array $products){
        $sum = 0.0;
        foreach ($products as $prod => $row) {
            $sum += (float)($row[12] ?? 0);
        }
        return $sum;
    };
@endphp

<div class="page-break"></div>
<div class="page">
    <div class="section-title">Product Matrix — Inquiries (Projects)</div>
    <div class="section-sub">Rows: Salesman → Product. Columns: Month-wise sums (SAR).</div>

    @php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'];

        // helper: ALWAYS 13 cells
        $pad13row = function($row){
            $row = is_array($row) ? array_values($row) : [];
            $row = array_pad($row, 13, 0);
            return array_slice($row, 0, 13);
        };
    @endphp

    <table class="matrix">
        {{-- ✅ Fixed widths = stable layout on every page --}}
        <colgroup>
            <col style="width:70px;">   {{-- Salesman --}}
            <col style="width:145px;">  {{-- Product --}}
            @for($i=0;$i<12;$i++)
                <col style="width:42px;"> {{-- months --}}
            @endfor
            <col style="width:55px;">   {{-- Total --}}
        </colgroup>

        <thead>
        <tr>
            <th rowspan="2">Salesman</th>
            <th rowspan="2">Product</th>
            <th colspan="13" class="num">Inquiries (SAR)</th>
        </tr>
        <tr>
            @foreach($months as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse(($inqProductMatrix ?? []) as $salesman => $products)
            @php
                $products = $products ?? [];
                $isFirstRow = true; // ✅ show salesman only once per group
            @endphp

            @foreach($products as $product => $row)
                @php
                    $row = $pad13row($row);

                    $salesmanTotal = $salesmanTotalFromProducts($products);
                    $prodTotal     = (float)($row[12] ?? 0);
                    $actualPct     = $salesmanTotal > 0 ? round(($prodTotal / $salesmanTotal) * 100, 1) : 0.0;

                    [$flagTxt, $flagCls] = $mixFlag($product, $actualPct);
                    $target = $mixTargets[strtoupper(trim($product))] ?? null;
                @endphp

                <tr>
                    {{-- ✅ Salesman shows only once (no rowspan) --}}
                    <td class="salesmanCell">
                        @if($isFirstRow)
                            <span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span>
                            @php $isFirstRow = false; @endphp
                        @else
                            &nbsp;
                        @endif
                    </td>

                    <td class="rowLabelCell">
                        {{ strtoupper($product) }}
                        <span class="small-muted">
                    {{ $actualPct }}%{{ $target !== null ? ' (T:' . $target . '%)' : '' }}
                </span>
                        @if($flagTxt !== '')
                            <span class="flag-pill {{ $flagCls }}">{{ $flagTxt }}</span>
                        @endif
                    </td>

                    @foreach($row as $val)
                        <td class="num">{{ $sar($val) }}</td>
                    @endforeach
                </tr>
            @endforeach

        @empty
            <tr>
                <td colspan="15" style="text-align:center;color:#6b7280;padding:8px;">No data available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    <div class="page-break"></div>
    <div class="section-title" style="margin-top:14px;">Product Matrix — POs Received (Sales Orders)</div>
    <div class="section-sub">Rows: Salesman → Product. Columns: Month-wise sums (SAR).</div>

    <table class="matrix">
        <thead>
        <tr>
            <th rowspan="2">Salesman</th>
            <th rowspan="2">Product</th>
            <th colspan="13" class="num">POs (SAR)</th>
        </tr>
        <tr>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse(($poProductMatrix ?? []) as $salesman => $products)
            @php $rowspan = max(1, count($products)); $first = true; @endphp

            @foreach($products as $product => $row)
                @php
                    $salesmanTotal = $salesmanTotalFromProducts($products);
                    $prodTotal     = (float)($row[12] ?? 0);
                    $actualPct     = $salesmanTotal > 0 ? round(($prodTotal / $salesmanTotal) * 100, 1) : 0.0;

                    [$flagTxt, $flagCls] = $mixFlag($product, $actualPct);
                    $target = $mixTargets[strtoupper(trim($product))] ?? null;
                @endphp

                <tr>
                    @if($first)
                        <td class="salesmanCell" rowspan="{{ $rowspan }}">
                            <span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span>
                        </td>
                        @php $first = false; @endphp
                    @endif

                    <td class="rowLabelCell">
                        {{ strtoupper($product) }}
                        <span class="small-muted">
                            {{ $actualPct }}%{{ $target !== null ? ' (T:' . $target . '%)' : '' }}
                        </span>
                        @if($flagTxt !== '')
                            <span class="flag-pill {{ $flagCls }}">{{ $flagTxt }}</span>
                        @endif
                    </td>

                    @foreach($row as $val)
                        <td class="num">{{ $sar($val) }}</td>
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="15" style="text-align:center;color:#6b7280;padding:8px;">No data available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

</div>


{{-- ============================================================
   PAGE 4: Performance Matrix (Forecast / Target / Inquiries / POs / Conversion)
============================================================ --}}
<div class="page-break"></div>
<div class="page">

    <div class="section-title">Performance Matrix — Forecast / Target / Inquiries / POs / Conversion</div>
    <div class="section-sub">Conversion% = POs ÷ Inquiries. Total conversion is calculated from totals (not sum of %).</div>

    @php
        $salesmanKpiMatrix = $salesmanKpiMatrix ?? [];

        $labels = [
            'FORECAST'  => 'Forecast',
            'TARGET'    => 'Target',
            'PERF'      => 'Performance Status',
            'INQUIRIES' => 'Inquiries',
            'POS'       => 'Sales Orders',
            'CONV_PCT'  => 'Conversion %',
        ];

        $pad13 = function($arr){
            $arr = is_array($arr) ? array_values($arr) : [];
            $arr = array_pad($arr, 13, 0);
            return array_slice($arr, 0, 13);
        };

        $buildConvRow = function($inqRow, $poRow) use ($pad13){
            $inqRow = $pad13($inqRow);
            $poRow  = $pad13($poRow);

            $out = [];
            for($i=0;$i<12;$i++){
                $inq = (float)$inqRow[$i];
                $po  = (float)$poRow[$i];
                $out[$i] = $inq > 0 ? round(($po / $inq) * 100, 1) : 0.0;
            }
            $tInq = (float)$inqRow[12];
            $tPo  = (float)$poRow[12];
            $out[12] = $tInq > 0 ? round(($tPo / $tInq) * 100, 1) : 0.0;
            return $out;
        };

        // current month progress
        $reportDate = null;
        try { $reportDate = \Carbon\Carbon::createFromFormat('d-m-Y', (string)$today); }
        catch (\Throwable $e) { $reportDate = \Carbon\Carbon::now(); }

        $currentMonth = (int)$reportDate->month;
        $daysInMonth  = (int)$reportDate->daysInMonth;
        $dayOfMonth   = (int)$reportDate->day;

        $currentMonthProgress = $daysInMonth > 0 ? ($dayOfMonth / $daysInMonth) : 1.0;
        $currentMonthProgress = max(0.0, min(1.0, $currentMonthProgress));

        $perfCell = function(float $actual, float $expected, bool $isFuture) {
            if ($isFuture) return ['html' => '<span class="flag flag-na">N/A</span>'];
            if ($expected <= 0) {
                if ($actual <= 0) return ['html' => '<span class="flag flag-danger">0% • DANGER</span>'];
                return ['html' => '<span class="flag flag-na">NO TARGET</span>'];
            }
            if ($actual <= 0) return ['html' => '<span class="flag flag-danger">0% • DANGER</span>'];

            $pct = ($actual / $expected) * 100.0;

            if ($pct >= 100) return ['html' => '<span class="flag flag-excellent"> ' . number_format($pct,0) . '%</span>'];
            if ($pct >= 60)  return ['html' => '<span class="flag flag-good"> ' . number_format($pct,0) . '%</span>'];
            if ($pct >= 50)  return ['html' => '<span class="flag flag-acceptable"> ' . number_format($pct,0) . '%</span>'];
            if ($pct >= 20)  return ['html' => '<span class="flag flag-attn"> ' . number_format($pct,0) . '%</span>'];

            return ['html' => '<span class="flag flag-danger"> ' . number_format($pct,0) . '%</span>'];
        };

        $buildPerfRow = function($poRow, $targetRow) use ($pad13, $perfCell, $currentMonth, $currentMonthProgress) {
            $poRow     = $pad13($poRow);
            $targetRow = $pad13($targetRow);

            $out = [];

            for ($i=0; $i<12; $i++) {
                $monthNo = $i + 1;
                $isFuture = $monthNo > $currentMonth;

                $expected = 0.0;
                if (!$isFuture) {
                    $expected = ($monthNo < $currentMonth)
                        ? (float)$targetRow[$i]
                        : (float)$targetRow[$i] * (float)$currentMonthProgress;
                }

                $actual = (float)$poRow[$i];
                $out[$i] = $perfCell($actual, $expected, $isFuture);
            }

            // TOTAL column (YTD)
            $actualYtd = 0.0;
            $expectedYtd = 0.0;

            for ($i=0; $i<12; $i++) {
                $monthNo = $i + 1;
                if ($monthNo > $currentMonth) continue;

                $actualYtd += (float)$poRow[$i];

                $expectedYtd += ($monthNo < $currentMonth)
                    ? (float)$targetRow[$i]
                    : (float)$targetRow[$i] * (float)$currentMonthProgress;
            }

            $out[12] = $perfCell($actualYtd, $expectedYtd, false);

            return $out;
        };
    @endphp

    <table class="matrix">
        <thead>
        <tr>
            <th rowspan="2">Salesman</th>
            <th rowspan="2">Metric</th>
            <th colspan="13" class="num">Month-wise</th>
        </tr>
        <tr>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse($salesmanKpiMatrix as $salesman => $metrics)
            @php
                $forecastRow = $pad13($metrics['FORECAST']  ?? []);
                $targetRow   = $pad13($metrics['TARGET']    ?? []);
                $inqRow      = $pad13($metrics['INQUIRIES'] ?? []);
                $poRow       = $pad13($metrics['POS']       ?? []);

                $convRow = $buildConvRow($inqRow, $poRow);
                $perfRow = $buildPerfRow($poRow, $targetRow);

                $renderRows = [
                    'FORECAST'  => $forecastRow,
                    'TARGET'    => $targetRow,
                    'PERF'      => $perfRow,
                    'INQUIRIES' => $inqRow,
                    'POS'       => $poRow,
                    'CONV_PCT'  => $convRow,
                ];

                $rowspan = 6;
            @endphp

            <tr class="keep-together">
                <td class="salesmanCell" rowspan="{{ $rowspan }}">
                    <span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span>
                </td>
                <td class="rowLabelCell">{{ $labels['FORECAST'] }}</td>
                @foreach($renderRows['FORECAST'] as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>

            <tr class="keep-together">
                <td class="rowLabelCell">{{ $labels['TARGET'] }}</td>
                @foreach($renderRows['TARGET'] as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>

            <tr class="keep-together">
                <td class="rowLabelCell">{{ $labels['PERF'] }}</td>
                @foreach($renderRows['PERF'] as $cell)
                    <td class="pct">{!! $cell['html'] ?? '-' !!}</td>
                @endforeach
            </tr>

            <tr class="keep-together">
                <td class="rowLabelCell">{{ $labels['INQUIRIES'] }}</td>
                @foreach($renderRows['INQUIRIES'] as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>

            <tr class="keep-together">
                <td class="rowLabelCell">{{ $labels['POS'] }}</td>
                @foreach($renderRows['POS'] as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>

            <tr class="keep-together">
                <td class="rowLabelCell">{{ $labels['CONV_PCT'] }}</td>
                @foreach($renderRows['CONV_PCT'] as $val)
                    <td class="pct">{{ number_format((float)$val, 1) }}%</td>
                @endforeach
            </tr>

        @empty
            <tr>
                <td colspan="15" style="text-align:center;color:#6b7280;padding:8px;">No data available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

</div>


{{-- ============================================================
   PAGE 5: Estimators + Totals
============================================================ --}}
<div class="page-break"></div>
<div class="page">

{{--    <div class="section-title">Total Inquiries — Month-wise (Overall)</div>--}}
{{--    <div class="section-sub">Total quotation value for all inquiries in the year, month-wise.</div>--}}

{{--    @php--}}
{{--        $row = $totalInquiriesByMonth ?? array_fill(0,13,0);--}}
{{--        $row = is_array($row) ? array_values($row) : [];--}}
{{--        $row = array_pad($row, 13, 0);--}}
{{--        $row = array_slice($row, 0, 13);--}}
{{--    @endphp--}}

{{--    <table class="data">--}}
{{--        <thead>--}}
{{--        <tr>--}}
{{--            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)--}}
{{--                <th class="num">{{ strtoupper($m) }}</th>--}}
{{--            @endforeach--}}
{{--        </tr>--}}
{{--        </thead>--}}
{{--        <tbody>--}}
{{--        <tr>--}}
{{--            @foreach($row as $val)--}}
{{--                <td class="num">{{ $sar($val) }}</td>--}}
{{--            @endforeach--}}
{{--        </tr>--}}
{{--        </tbody>--}}
{{--    </table>--}}

    <div class="section-title" style="margin-top:14px;">Inquiries — by Estimator (Monthly)</div>
    <div class="section-sub">Values by month based on quotation date (projects). Shows estimation workload & output.</div>

    @php $inquiriesByEstimator = $inquiriesByEstimator ?? []; @endphp

    <table class="data">
        <thead>
        <tr>
            <th>Estimator</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse($inquiriesByEstimator as $estimator => $row)
            @php
                $row = is_array($row) ? array_values($row) : [];
                $row = array_pad($row, 13, 0);
                $row = array_slice($row, 0, 13);
            @endphp
            <tr>
                <td><span class="salesman-badge other">{{ strtoupper($estimator) }}</span></td>
                @foreach($row as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="14" style="text-align:center;color:#6b7280;padding:8px;">No estimator data available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title" style="margin-top:14px;">Estimator Product Matrix — Inquiries (Projects)</div>
    <div class="section-sub">Rows: Estimator → Product. Columns: Month-wise sums (SAR).</div>

    <table class="matrix">
        <thead>
        <tr>
            <th rowspan="2">Estimator</th>
            <th rowspan="2">Product</th>
            <th colspan="13" class="num">Inquiries (SAR)</th>
        </tr>
        <tr>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse(($estimatorProductMatrix ?? []) as $estimator => $products)
            @php $rowspan = max(1, count($products)); $first = true; @endphp
            @foreach($products as $product => $row)
                <tr>
                    @if($first)
                        <td class="salesmanCell" rowspan="{{ $rowspan }}">
                            <span class="salesman-badge other">{{ strtoupper($estimator) }}</span>
                        </td>
                        @php $first = false; @endphp
                    @endif

                    <td class="rowLabelCell">{{ strtoupper($product) }}</td>
                    @foreach($row as $val)
                        <td class="num">{{ $sar($val) }}</td>
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr><td colspan="15" style="text-align:center;color:#6b7280;padding:8px;">No data available.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title" style="margin-top:14px;">Total Inquiries — by Project Type (Monthly)</div>
    <div class="section-sub">Breakdown of total quotation value by project type (Bidding / In-Hand / Lost).</div>

    @php $byType = $totalInquiriesByMonthByType ?? []; @endphp

    <table class="data">
        <colgroup>
            <col style="width:140px;">
            <col span="12" style="width:55px;">
            <col style="width:75px;">
        </colgroup>
        <thead>
        <tr>
            <th>Type</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($byType as $type => $row)
            @php
                $row = is_array($row) ? array_values($row) : [];
                $row = array_pad($row, 13, 0);
                $row = array_slice($row, 0, 13);
            @endphp
            <tr>
                <td><span class="salesman-badge other">{{ strtoupper($type) }}</span></td>
                @foreach($row as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="14" style="text-align:center;color:#6b7280;padding:8px;">No project type data available.</td></tr>
        @endforelse
        </tbody>
    </table>

{{--    <div class="section-title" style="margin-top:14px;">Total Inquiries — by Product (Monthly)</div>--}}
{{--    <div class="section-sub">Overall inquiries split by product family (month-wise).</div>--}}

{{--    @php $totalInquiriesByProduct = $totalInquiriesByProduct ?? []; @endphp--}}

{{--    <table class="data">--}}
{{--        <thead>--}}
{{--        <tr>--}}
{{--            <th>Product</th>--}}
{{--            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)--}}
{{--                <th class="num">{{ strtoupper($m) }}</th>--}}
{{--            @endforeach--}}
{{--        </tr>--}}
{{--        </thead>--}}
{{--        <tbody>--}}
{{--        @forelse($totalInquiriesByProduct as $product => $row)--}}
{{--            @php--}}
{{--                $row = is_array($row) ? array_values($row) : [];--}}
{{--                $row = array_pad($row, 13, 0);--}}
{{--                $row = array_slice($row, 0, 13);--}}
{{--            @endphp--}}
{{--            <tr>--}}
{{--                <td><span class="salesman-badge other">{{ strtoupper($product) }}</span></td>--}}
{{--                @foreach($row as $val)--}}
{{--                    <td class="num">{{ $sar($val) }}</td>--}}
{{--                @endforeach--}}
{{--            </tr>--}}
{{--        @empty--}}
{{--            <tr><td colspan="14" style="text-align:center;color:#6b7280;padding:8px;">No product data available.</td></tr>--}}
{{--        @endforelse--}}
{{--        </tbody>--}}
{{--    </table>--}}

</div>

</body>
</html>
