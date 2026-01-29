@php
    use Carbon\Carbon;
@endphp
    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATAI – Salesman Summary {{ $year }}</title>

    <style>
        /* ======================================================================
           ATAI – DOMPDF PROFESSIONAL REPORT (A4 LANDSCAPE)
           ----------------------------------------------------------------------
           Goals
           - DOMPDF-safe layout (tables > flex; fixed col widths)
           - Executive readability (consistent typography, zebra rows, badges)
           - Keep existing class names + behavior (only formatting/comments)
           ====================================================================== */

        /* =========================
           PAGE SETUP
           ========================= */
        @page {
            margin: 14px 14px;
            size: A4 landscape;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
            background: #ffffff;
        }

        .page {
            background: #ffffff;
            padding: 12px 12px;
        }

        .page-break { page-break-before: always; }

        /* =========================
           SECTION TITLES
           ========================= */
        .section-title {
            text-align: center;
            margin-top: 18px;
            margin-bottom: 8px;
            font-size: 17px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: .02em;
        }

        .section-sub {
            text-align: center;
            font-size: 9px;
            color: #64748b;
            margin-bottom: 2px;
        }

        /* =========================
           HEADER (DOMPDF-safe table)
           ========================= */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .header-table td {
            vertical-align: top;
            padding: 0;
            border: 0 !important;
        }

        .header-title { width: 55%; }
        .header-meta  { width: 45%; text-align: right; }

        .h1 {
            font-size: 16px;
            font-weight: 800;
            margin: 0;
            line-height: 1.15;
            color: #0f172a;
        }

        .report-date {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Pills (Year + Area) */
        .report-context { margin-top: 4px; white-space: nowrap; }

        .context-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 800;
            line-height: 1.1;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #0f172a;
            margin-left: 6px;
        }

        .context-pill.year {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #1e3a8a;
        }

        .context-pill.area.all {
            background: #ede9fe;
            border-color: #ddd6fe;
            color: #4c1d95;
        }

        .context-pill.area.eastern {
            background: #e0f2fe;
            border-color: #bae6fd;
            color: #075985;
        }

        .context-pill.area.central {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }

        .context-pill.area.western {
            background: #ffedd5;
            border-color: #fed7aa;
            color: #9a3412;
        }

        /* =========================
           KPI STRIP
           ========================= */
        .kpi-strip { margin-top: 10px; margin-bottom: 10px; width: 100%; }

        .kpi-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .kpi-row td {
            width: 33.333%;
            vertical-align: top;
            padding-right: 8px;
            border: 0 !important;
        }

        .kpi-row td:last-child { padding-right: 0; }

        .kpi-card {
            width: 80%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .kpi-label { font-size: 8px; color: #64748b; }

        .kpi-value {
            font-size: 13px;
            font-weight: 900;
            margin-top: 3px;
            color: #0f172a;
        }

        .kpi-footnote {
            font-size: 8px;
            color: #64748b;
            margin-top: 3px;
            line-height: 1.2;
        }

        /* =========================
           SUMMARY CARDS
           ========================= */
        .summary-card {
            margin-top: 8px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 1px solid #dbeafe;
            background: #ffffff;
            padding: 8px 10px;
        }

        .summary-title {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 6px;
            color: #0f172a;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 9px;
            text-align: center;
        }

        .summary-table th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 900;
        }

        .summary-table td.num {
            text-align: center;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            font-weight: 800;
        }

        /* =========================
           DOMPDF TABLE PAGING RULES
           ========================= */
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr    { page-break-inside: auto; }

        .keep-together { page-break-inside: avoid; }

        /* =========================
           DATA TABLES (Region/Salesman monthly)
           ========================= */
        table.data {
            table-layout: fixed;
            margin-top: 6px;
            border: 1px solid #e2e8f0;
        }

        table.data th,
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 9px;
            line-height: 1.15;
        }

        table.data th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 900;
        }

        table.data td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: #0f172a;
        }

        table.data tr:nth-child(even) td { background: #f8fafc; }

        /* =========================
           MATRIX TABLES (Product + Performance)
           ========================= */
        table.matrix {
            table-layout: fixed;
            margin-top: 6px;
            border: 1px solid #e2e8f0;
        }

        table.matrix th,
        table.matrix td {
            border: 1px solid #e2e8f0;
            padding: 3px 4px;
            vertical-align: middle;
            font-size: 8px;
            line-height: 1.15;
        }

        table.matrix th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 900;
            font-size: 8px;
        }

        table.matrix tr:nth-child(even) td { background: #f8fafc; }

        .matrix .salesmanCell,
        .matrix .rowLabelCell {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .matrix .salesmanCell {
            background: #f1f5f9;
            font-weight: 900;
            vertical-align: top;
            padding-top: 6px;
        }

        .matrix .rowLabelCell {
            background: #ffffff;
            font-weight: 900;
        }

        .matrix td.num,
        .matrix td.pct {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: #0f172a;
        }

        .matrix td.dash { text-align: center; color: #94a3b8; }

        /* =========================
           BADGES (Salesman/Region Pills)
           ========================= */
        .salesman-badge,
        .area-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
            line-height: 1.1;
            white-space: nowrap;
        }

        /* Salesmen */
        .sohaib { background: #0ea5e9; color: #083344; }
        .tariq  { background: #a78bfa; color: #312e81; }
        .jamal  { background: #22c55e; color: #064e3b; }
        .abdo   { background: #f97316; color: #7c2d12; }
        .ahmed  { background: #facc15; color: #78350f; }
        .other  { background: #e2e8f0; color: #334155; }

        /* Regions */
        .central { background: #22c55e; color: #064e3b; }
        .eastern { background: #0ea5e9; color: #083344; }
        .western { background: #f97316; color: #7c2d12; }

        /* =========================
           PRODUCT MIX FLAGS (PILLS)
           ========================= */
        .flag-pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 7px;
            font-weight: 900;
            margin-left: 6px;
            white-space: nowrap;
            border: 1px solid #e2e8f0;
        }

        .flag-high { background: #fee2e2; color: #991b1b; border-color: #fecaca; } /* red */
        .flag-low  { background: #ffedd5; color: #9a3412; border-color: #fed7aa; } /* orange */
        .flag-ok   { background: #dcfce7; color: #166534; border-color: #bbf7d0; } /* green */

        .small-muted {
            font-size: 7px;
            color: #64748b;
            margin-left: 6px;
            white-space: nowrap;
        }

        /* =========================
           PERFORMANCE FLAGS
           ========================= */
        .flag {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 900;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .flag-excellent { background: #dcfce7; color: #14532d; border: 1px solid #86efac; }
        .flag-good      { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        .flag-acceptable{ background: #fef9c3; color: #713f12; border: 1px solid #fde047; }
        .flag-attn      { background: #ffedd5; color: #7c2d12; border: 1px solid #fdba74; }
        .flag-danger    { background: #fee2e2; color: #7f1d1d; border: 1px solid #fca5a5; }
        .flag-na        { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        /* ======================================================================
           REGION DISTRIBUTION MATRIX
           ====================================================================== */
        table.region-matrix {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
        }

        table.region-matrix th,
        table.region-matrix td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 9px;
            line-height: 1.15;
        }

        table.region-matrix th {
            background: #0f172a;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 900;
        }

        table.region-matrix tr:nth-child(even) td { background: #f8fafc; }

        .region-matrix td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .region-matrix .salesmanCell {
            background: #f1f5f9;
            font-weight: 900;
            vertical-align: top;
        }

        .region-matrix .regionCell {
            color: #475569;
            font-weight: 800;
        }

        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 900;
            white-space: nowrap;
            border: 1px solid #e2e8f0;
            margin-left: 6px;
        }

        .pill-flag { background: #fef3c7; color: #78350f; border-color: #fde68a; } /* FLAG */
        .pill-dom  { background: #fee2e2; color: #7f1d1d; border-color: #fecaca; } /* DOMINANT */

        /* =========================
           INSIGHTS (Text-only)
           ========================= */
        .bi { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11.5px; color: #111; }
        .bi-title { font-size: 18px; font-weight: 900; margin: 0 0 6px 0; }
        .bi-debug { font-size: 10px; color: #b14a3a; margin: 0 0 10px 0; }

        h3 { font-size: 14px; margin: 10px 0 6px 0; font-weight: 900; }
        h4 { font-size: 12px; margin: 8px 0 3px 0; font-weight: 900; }

        ul { margin: 2px 0 6px 18px; padding: 0; }
        ul li { margin: 2px 0; line-height: 1.35; }

        ol { margin: 4px 0 6px 18px; padding: 0; }
        ol li { margin: 8px 0; line-height: 1.35; page-break-inside: avoid; }

        .muted { color: #666; font-size: 10px; }

        .sec-high { color: #1e8e3e; font-weight: 900; }
        .sec-low  { color: #b06000; font-weight: 900; }
        .sec-attn { color: #b00020; font-weight: 900; }

        .dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 8px; position: relative; top: 1px; }
        .dot-green { background: #1e8e3e; }
        .dot-amber { background: #f29900; }
        .dot-red   { background: #d93025; }

        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
            border: 1px solid #ddd;
            margin-left: 6px;
            background: #fafafa;
        }

        .c-high { color: #1e8e3e; border-color: #cfe8d6; background: #eef8f1; }
        .c-med  { color: #b26a00; border-color: #f5deb8; background: #fff6e8; }
        .c-low  { color: #b00020; border-color: #f2c2c9; background: #fff0f2; }

        /* ======================================================================
           GM CONTROL BLOCK (DOMPDF SAFE)
           - No emoji/check characters (DOMPDF tofu)
           - Uses border-drawn checkmark
           ====================================================================== */
        .gm3-wrap {
            margin-top: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }

        .gm3-title {
            font-weight: 900;
            font-size: 12px;
            margin: 0 0 8px 0;
            color: #111827;
        }

        table.gm3-row {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.gm3-row td {
            width: 33.333%;
            vertical-align: top;
            padding: 0 10px 0 0;
            border: 0 !important;
        }

        table.gm3-row td:last-child { padding-right: 0; }

        .gm3-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f9fafb;
            height: 78px;         /* stable card height */
            overflow: hidden;      /* prevent spill */
        }

        .gm3-titleline {
            font-size: 10.5px;
            font-weight: 900;
            color: #111827;
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gm3-status { margin: 0 0 6px 0; white-space: nowrap; }

        .gm3-check {
            display: inline-block;
            margin-right: 14px;
            font-size: 9px;
            font-weight: 800;
            color: #111827;
        }

        .gm3-check .box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 2px solid #111827;
            margin-right: 6px;
            vertical-align: -2px;
            position: relative;
            background: #fff;
        }

        /* Checked state: fill + draw check mark with borders (no "✓" char) */
        .gm3-check.checked .box { background: #111827; }

        .gm3-check.checked .box:after {
            content: "";
            position: absolute;
            left: 2px;
            top: 1px;
            width: 4px;
            height: 7px;
            border-right: 2px solid #ffffff;
            border-bottom: 2px solid #ffffff;
            transform: rotate(45deg);
        }

        .gm3-check.muted { opacity: 0.45; }

        .gm3-s {
            font-size: 9.2px;
            line-height: 1.25;
            color: #374151;
        }

        /* =========================
           Sub-product row styling
           ========================= */
        .subproduct-row td { font-size: 10px; }
        .subproduct-label { padding-left: 12px; color: #0f172a; }

        .flag-pill.pending {
            background: #334155;
            color: #e2e8f0;
            border: 1px solid #475569;
        }
    </style>
</head>

<body>
@php
    /* ======================================================================
       VIEW HELPERS (Formatting + defensive fallbacks)
       ====================================================================== */

    /** Format SAR values. Keep "-" for zero (your current UI behavior). */
    $sar = function($v){
        $v = (float)$v;
        return $v == 0 ? '-' : 'SAR ' . number_format($v, 0);
    };

    // Headline KPI inputs
    $inqTotal = (float)($kpis['inquiries_total'] ?? 0);
    $poTotal  = (float)($kpis['pos_total'] ?? 0);

    // Gap metrics: safe defaults if backend didn't pass them
    $gapVal = (float)($kpis['gap_value'] ?? ($inqTotal - $poTotal));
    $gapPct = (float)($kpis['gap_percent'] ?? ($inqTotal > 0 ? round($poTotal / $inqTotal * 100, 1) : 0));

    /** Map salesman name to badge class (supports partial matches). */
    $badgeClass = function($name) {
        $k = strtolower(trim((string)$name));
        if (str_contains($k, 'sohaib')) return 'sohaib';
        if (str_contains($k, 'tariq'))  return 'tariq';
        if (str_contains($k, 'jamal'))  return 'jamal';
        if (str_contains($k, 'abdo'))   return 'abdo';
        if (str_contains($k, 'ahmed'))  return 'ahmed';
        return 'other';
    };

    /* ======================================================================
       TARGETS + Business rule: Salesman -> Region mapping
       - Used for injecting TARGET rows in Performance Matrix.
       - Also used for expectations in Region Concentration matrix.
       ====================================================================== */
    $yearlyTargets = [
        'Eastern' => 50000000,
        'Central' => 50000000,
        'Western' => 36000000,
    ];

    // Expected region per salesman (business rule)
    $salesmanToRegion = [
        'SOHAIB' => 'Eastern',
        'TARIQ'  => 'Central',
        'JAMAL'  => 'Central',
        'ABDO'   => 'Western',
        'AHMED'  => 'Western',
    ];

    // Count how many salesmen share each region target (Central = 2)
    $regionSalesmenCount = [];
    foreach ($salesmanToRegion as $s => $r) {
        $regionSalesmenCount[$r] = ($regionSalesmenCount[$r] ?? 0) + 1;
    }

    /* ======================================================================
       Inject TARGET rows into $salesmanKpiMatrix
       - TARGET is monthly (region yearly / 12 / salesman count in region)
       - Adds 13 cells (Jan..Dec + Total)
       ====================================================================== */
    foreach (($salesmanKpiMatrix ?? []) as $salesman => &$metrics) {
        $sKey   = strtoupper(trim($salesman));
        $region = $salesmanToRegion[$sKey] ?? null;

        $yearTarget    = $region ? (float)($yearlyTargets[$region] ?? 0) : 0.0;
        $monthlyTarget = $yearTarget > 0 ? ($yearTarget / 12) : 0.0;

        // Split if multiple salesmen share region target (e.g., Central)
        $div = $region ? max(1, (int)($regionSalesmenCount[$region] ?? 1)) : 1;
        $salesmanMonthlyTarget = $monthlyTarget / $div;

        $targetRow = array_fill(0, 12, $salesmanMonthlyTarget);
        $targetRow[] = $salesmanMonthlyTarget * 12;

        $metrics['TARGET'] = $targetRow;
    }
    unset($metrics);
@endphp

{{-- ======================================================================
   PAGE 1: Header + KPI strip + Summary blocks + Charts + GM Controls
   ====================================================================== --}}
<div class="page">

    {{-- =========================
       HEADER
       ========================= --}}
    <table class="header-table">
        <tr>
            <td class="header-title">
                <div class="h1">ATAI Sales Performance Snapshot</div>
            </td>

            <td class="header-meta">
                <div class="report-date">
                    Report Date<br>
                    <strong>{{ $today }}</strong>
                </div>

                @php
                    // Normalize area pill (only allow known values)
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

    {{-- =========================
       KPI STRIP
       ========================= --}}
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

    {{-- =========================
       YEARLY TARGETS SUMMARY
       - If area != All, show only that region
       ========================= --}}
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
                <th class="num">Target (Monthly)</th>
                <th>Salesmen</th>
            </tr>
            </thead>

            <tbody>
            @foreach($targetRows as $region => $info)
                <tr>
                    <td>
                        <span class="area-badge {{ $info['badge'] }}">{{ strtoupper($region) }}</span>
                    </td>
                    <td class="num">SAR {{ number_format((float)$info['target'], 0) }}</td>
                    <td class="num">SAR {{ number_format((float)$info['target']/12, 0) }}</td>
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

    {{-- =========================
       SUMMARY: Quotations vs POs BY SALESMAN (Year totals)
       ========================= --}}
    @php
        $salesmanSummary = [];
        foreach (($inquiriesBySalesman ?? []) as $salesman => $row) {
            $inqT  = (float) end($row);
            $poRow = ($posBySalesman[$salesman] ?? array_fill(0, count($row), 0));
            $poT   = (float) end($poRow);

            $salesmanSummary[] = [
                'salesman' => $salesman,
                'inq'      => $inqT,
                'pos'      => $poT,
            ];
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
                    <td>
                        <span class="salesman-badge {{ $badgeClass($row['salesman']) }}">
                            {{ strtoupper($row['salesman']) }}
                        </span>
                    </td>
                    <td class="num">{{ $sar($row['inq']) }}</td>
                    <td class="num">{{ $sar($row['pos']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- =========================
       SUMMARY: Quotations vs POs BY REGION (Year totals)
       ========================= --}}
    @php
        $regionSummary = [];
        foreach (($inqByRegion ?? []) as $region => $row) {
            $inqTot = (float) end($row);
            $poRow  = ($poByRegion[$region] ?? array_fill(0, count($row), 0));
            $poTot  = (float) end($poRow);

            $regionSummary[] = [
                'region' => $region,
                'inq'    => $inqTot,
                'pos'    => $poTot,
            ];
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
                    <td>
                        <span class="area-badge {{ $aLower }}">{{ strtoupper($r['region']) }}</span>
                    </td>
                    <td class="num">{{ $sar($r['inq']) }}</td>
                    <td class="num">{{ $sar($r['pos']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- =========================
       CHARTS (Product mix + Monthly performance)
       NOTE: DOMPDF likes <table> layouts for image grids.
       ========================= --}}
    <table style="width:100%; border-collapse:collapse; margin-top:10px;">
        @foreach(($salesmen ?? []) as $s)
            <tr>
                <td style="width:50%; padding:6px 10px 6px 0; vertical-align:top;">
                    @if(!empty($charts[$s]['product_mix']))
                        <img
                            src="{{ $charts[$s]['product_mix'] }}"
                            style="width:100%; height:auto; display:block;"
                            alt="Product Mix {{ $s }}"
                        >
                    @endif
                </td>

                <td style="width:50%; padding:6px 0 6px 10px; vertical-align:top;">
                    @if(!empty($charts[$s]['monthly_perf']))
                        <img
                            src="{{ $charts[$s]['monthly_perf'] }}"
                            style="width:100%; height:auto; display:block;"
                            alt="Monthly Performance {{ $s }}"
                        >
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    {{-- =========================
       GM CONTROLS
       - Uses $gm_controls payload
       - Strips emoji to avoid DOMPDF tofu blocks
       ========================= --}}
    @php $gm = $gm_controls ?? []; @endphp

    <div class="gm3-wrap">
        <div class="gm3-title">Dashboard Update (Management Controls)</div>

        <table class="gm3-row" role="presentation" cellspacing="0" cellpadding="0">
            <tr>
                @foreach(['quotation_po','weekly_report','bnc_update'] as $k)
                    @php
                        $it = $gm[$k] ?? ['title'=>'—','status'=>'—','ok'=>false,'detail'=>''];
                        $ok = (bool)($it['ok'] ?? false);

                        // Safety: strip emoji/icon chars that cause black tofu in DOMPDF
                        $title  = is_string($it['title'] ?? null)
                            ? preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', '', (string)$it['title'])
                            : '—';

                        $detail = is_string($it['detail'] ?? null)
                            ? preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', '', (string)$it['detail'])
                            : '';
                    @endphp

                    <td>
                        <div class="gm3-card">
                            <div class="gm3-titleline">{{ $title !== '' ? $title : '—' }}</div>

                            <div class="gm3-status">
                                <span class="gm3-check {{ $ok ? 'checked' : 'muted' }}">
                                    <span class="box"></span><span class="lbl">YES</span>
                                </span>

                                <span class="gm3-check {{ !$ok ? 'checked' : 'muted' }}">
                                    <span class="box"></span><span class="lbl">NO</span>
                                </span>
                            </div>

                            <div class="gm3-s">{{ $detail }}</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</div>

{{-- ======================================================================
   PAGE 2: POs by Region + PO Regional Concentration Matrix
   ====================================================================== --}}
<div class="page-break"></div>
<div class="page">

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
            @php $aLower = strtolower($region); @endphp
            <tr>
                <td><span class="area-badge {{ $aLower }}">{{ strtoupper($region) }}</span></td>
                @foreach($row as $val)
                    <td class="num">{{ $sar($val) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- =========================
       PO REGIONAL CONCENTRATION
       Rules (Blade-only):
       - ignore low totals (noise) under MIN_GRAND_FOR_FLAG
       - FLAG salesman if expected region share < 50% and NOT top region
       - DOMINANT if any non-expected region share >= 50%
       ========================= --}}
    @php
        $months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'];
        $regions = ['Eastern','Central','Western'];
        $fmt     = fn($v) => number_format((float)$v, 0);

        $expectedRegionOf = function(string $salesman){
            $s = strtoupper(trim($salesman));
            if ($s === 'SOHAIB') return 'Eastern';
            if (in_array($s, ['TARIQ','JAMAL'], true)) return 'Central';
            if (in_array($s, ['ABDO','AHMED'], true)) return 'Western';
            return 'Other';
        };
    @endphp

    <div class="section-title" style="margin-top:12px;">PO Regional Concentration</div>

    <table class="region-matrix">
        <colgroup>
            <col style="width:150px;">
            <col style="width:140px;">
            @for($i=0;$i<12;$i++)
                <col style="width:55px;">
            @endfor
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

                $totE  = (float)($byRegion['Eastern'][12] ?? 0);
                $totC  = (float)($byRegion['Central'][12] ?? 0);
                $totW  = (float)($byRegion['Western'][12] ?? 0);
                $grand = $totE + $totC + $totW;

                // Thresholds
                $MIN_GRAND_FOR_FLAG = 250000; // ignore low volume
                $EXPECTED_MIN_SHARE = 0.50;
                $DOMINANT_MIN_SHARE = 0.50;

                $share = function(string $rg) use ($totE,$totC,$totW,$grand){
                    if ($grand <= 0) return 0.0;
                    $v = ($rg === 'Eastern') ? $totE : (($rg === 'Central') ? $totC : (($rg === 'Western') ? $totW : 0));
                    return $v / $grand;
                };

                $shares = [
                    'Eastern' => $share('Eastern'),
                    'Central' => $share('Central'),
                    'Western' => $share('Western'),
                ];

                $expectedShare = $shares[$expected] ?? 0.0;
                $topRegion     = array_keys($shares, max($shares))[0] ?? null;

                $allowFlags   = ($grand >= $MIN_GRAND_FOR_FLAG) && ($expected !== 'Other');
                $flagSalesman = ($allowFlags && $expectedShare < $EXPECTED_MIN_SHARE && $topRegion !== $expected);
            @endphp

            @foreach($regions as $i => $rg)
                @php
                    $row       = $byRegion[$rg] ?? array_fill(0, 13, 0);
                    $rgShare   = $share($rg);
                    $flagRegion = ($allowFlags && $rg !== $expected && $rgShare >= $DOMINANT_MIN_SHARE);
                @endphp

                <tr>
                    @if($i === 0)
                        <td rowspan="{{ count($regions) }}" class="salesmanCell">
                            <span class="salesman-badge {{ $badgeClass($salesman) }}">{{ strtoupper($salesman) }}</span>
                            @if($flagSalesman)
                                <span class="pill pill-flag">FLAG</span>
                            @endif
                        </td>
                    @endif

                    <td class="regionCell">
                        {{ $rg }}
                        @if($grand > 0)
                            ({{ round($rgShare*100, 1) }}%)
                        @endif

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

{{-- ======================================================================
   PAGE 3: Product matrices (Inquiries + POs) + mix flags
   ====================================================================== --}}
@php
    /* =========================
       Product mix targets (% of TOTAL per salesman)
       ========================= */
    $mixTargets = [
        'DUCTWORK'          => 60,
        'ACCESSORIES'       => 10,
        'SOUND ATTENUATORS' => 10,
        'DAMPERS'           => 10,
        'LOUVERS'           => 10,
    ];

    // Tolerance (+/-)
    $tolHigh = 8;
    $tolLow  = 8;

    /** Returns [flagText, flagClass] */
    $mixFlag = function(string $product, float $actualPct) use ($mixTargets, $tolHigh, $tolLow) {
        $p = strtoupper(trim($product));
        $t = $mixTargets[$p] ?? null;
        if ($t === null) return ['', ''];

        // Hard rule: 0% is critical
        if ($actualPct == 0.0) return ['Critical', 'flag-high'];

        if ($actualPct > ($t + $tolHigh)) return ['HIGH', 'flag-high'];
        if ($actualPct < ($t - $tolLow))  return ['LOW',  'flag-low'];

        return ['', ''];
    };
@endphp

@if(($area ?? 'All') === 'All')
    <div class="page-break"></div>
@endif

<div class="page">
    <div class="section-title">Product Matrix — Inquiries (Projects)</div>
    <div class="section-sub">Rows: Salesman → Product. Columns: Month-wise sums (SAR).</div>

    @php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'];

        /** Always return exactly 13 numeric cells. */
        $pad13row = function($row){
            $row = is_array($row) ? array_values($row) : [];
            $row = array_pad($row, 13, 0);
            return array_slice($row, 0, 13);
        };

        /** Month-wise add for 13 cols (safe numeric). */
        $sum13 = function(array $a, array $b){
            $o = [];
            for ($i=0; $i<13; $i++) $o[$i] = (float)($a[$i] ?? 0) + (float)($b[$i] ?? 0);
            return $o;
        };

        // Western split rule (ABDO/AHMED) for GM ALL bug fix
        $specialSplitSalesmen = ['ABDO','AHMED'];

        /**
         * GM "ALL" mode issue:
         * - sometimes $canViewAll isn't passed into PDF view
         * - we resolve it from multiple known keys, default false
         */
        $canViewAllResolved = (bool)(
            ($canViewAll ?? null)
            ?? ($meta['canViewAll'] ?? null)
            ?? ($meta['can_view_all'] ?? null)
            ?? false
        );

        /** Get first matching key from uppercase product map. */
        $pickRow = function(array $productsU, array $keys){
            foreach ($keys as $k) {
                $ku = strtoupper(trim((string)$k));
                if (array_key_exists($ku, $productsU)) return $productsU[$ku];
            }
            return [];
        };
    @endphp

    <table class="matrix">
        <colgroup>
            <col style="width:70px;">   {{-- Salesman --}}
            <col style="width:145px;">  {{-- Product --}}
            @for($i=0;$i<12;$i++)
                <col style="width:42px;">
            @endfor
            <col style="width:55px;">
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
                $products    = is_array($products) ? $products : [];
                $salesmanU   = strtoupper(trim((string)$salesman));
                $areaNormPdf = strtoupper(trim((string)($area ?? 'ALL')));
                $isAllArea   = ($areaNormPdf === 'ALL');

                // Normalize product keys to UPPERCASE so we never miss due to case/spacing
                $productsU = [];
                foreach ($products as $k => $v) {
                    $productsU[strtoupper(trim((string)$k))] = $v;
                }

                // Pull ductwork family rows with flexible matching
                $rowDuct = $pad13row($pickRow($productsU, ['DUCTWORK','DUCT WORK']));
                $rowPre  = $pad13row($pickRow($productsU, [
                    'PRE-INSULATED DUCTWORK','PRE INSULATED DUCTWORK','PRE-INSULATED','PRE INSULATED','PREINSULATED',
                ]));
                $rowSpiral = $pad13row($pickRow($productsU, ['SPIRAL DUCTWORK','SPIRAL DUCT','SPIRAL']));

                // Parent DUCTWORK(TOTAL) = duct + pre + spiral
                $ductTotalRow = $sum13($sum13($rowDuct, $rowPre), $rowSpiral);

                // GM FIX: show split for ABDO/AHMED in ALL area too
                $showSplit = in_array($salesmanU, $specialSplitSalesmen, true)
                    && (
                        !$isAllArea
                        || $canViewAllResolved
                        || ($isAllArea && !isset($canViewAll)) // GM PDF often doesn't pass it
                    );

                // Build rows to render (no double counting children)
                $displayProducts = [];
                $displayProducts[] = ['DUCTWORK (TOTAL)', $ductTotalRow, 'parent'];

                if ($showSplit) {
                    $displayProducts[] = ['PRE-INSULATED DUCTWORK', $rowPre, 'child'];
                    $displayProducts[] = ['SPIRAL DUCTWORK', $rowSpiral, 'child'];
                }

                // Other families (use normalized keys)
                foreach (['DAMPERS','LOUVERS','SOUND ATTENUATORS','ACCESSORIES'] as $k) {
                    if (isset($productsU[$k])) {
                        $displayProducts[] = [$k, $pad13row($productsU[$k]), 'normal'];
                    }
                }

                // Salesman total MUST NOT include child rows (avoid double count)
                $salesmanTotal = 0.0;
                foreach ($displayProducts as [$pname, $prow, $ptype]) {
                    if ($ptype === 'child') continue;
                    $salesmanTotal += (float)($prow[12] ?? 0);
                }
            @endphp

            {{-- Salesman group header --}}
            <tr class="salesman-group">
                <td class="salesmanCell">
                    <span class="salesman-badge {{ $badgeClass($salesmanU) }}">{{ $salesmanU }}</span>
                </td>
                <td colspan="14" class="group-fill">Products Breakdown</td>
            </tr>

            {{-- Product rows --}}
            @foreach($displayProducts as [$productName, $row, $ptype])
                @php
                    $prodTotal = (float)($row[12] ?? 0);

                    // Parent/Normal share of salesman total
                    $actualPct = $salesmanTotal > 0 ? round(($prodTotal / $salesmanTotal) * 100, 1) : 0.0;

                    // Flags: use base family key (strip " (TOTAL)")
                    $flagKey = strtoupper(trim(str_replace(' (TOTAL)', '', $productName)));
                    [$flagTxt, $flagCls] = $mixFlag($flagKey, $actualPct);
                    $target = $mixTargets[$flagKey] ?? null;
                @endphp

                <tr class="product-row {{ $ptype === 'child' ? 'duct-child' : '' }} {{ $ptype === 'parent' ? 'duct-parent' : '' }}">
                    <td class="salesmanSpacer">&nbsp;</td>

                    <td class="rowLabelCell">
                        {{ strtoupper($productName) }}
                        <span class="small-muted">
                            {{ $actualPct }}%{{ $target !== null ? ' (T:' . $target . '%)' : '' }}
                        </span>

                        {{-- Do NOT show flags on child lines --}}
                        @if($flagTxt !== '' && $ptype !== 'child')
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
                <td colspan="15" style="text-align:center;color:#c53636;padding:8px;">No data available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if(($area ?? 'All') === 'All')
        <div class="page-break"></div>
    @endif

    {{-- ==================================================================
       Product Matrix — POs Received (Sales Orders)
       - GM "ALL" fix + robust key matching + NO double counting
       ================================================================== --}}
    @php
        $areaNorm  = strtoupper(trim($area ?? ($meta['area'] ?? 'ALL')));
        $isWestern = ($areaNorm === 'WESTERN');
        $isAll     = ($areaNorm === 'ALL');

        // Western salesmen (must show ductwork split even in ALL for GM)
        $westernSalesmen = ['ABDO','AHMED'];

        $westernRowOrder = [
            'DUCTWORK',
            'PRE-INSULATED DUCTWORK',
            'SPIRAL DUCTWORK',
            'DAMPERS',
            'LOUVERS',
            'SOUND ATTENUATORS',
            'ACCESSORIES',
        ];

        $defaultRowOrder = [
            'DUCTWORK',
            'DAMPERS',
            'LOUVERS',
            'SOUND ATTENUATORS',
            'ACCESSORIES',
        ];

        $sumRow13 = function(array $a, array $b) use ($pad13row) {
            $a = $pad13row($a);
            $b = $pad13row($b);
            $out = [];
            for ($i=0; $i<13; $i++) $out[$i] = (float)$a[$i] + (float)$b[$i];
            return $out;
        };

        $pickP = function(array $map, array $keys) {
            foreach ($keys as $k) {
                $ku = strtoupper(trim((string)$k));
                if (array_key_exists($ku, $map)) return $map[$ku];
            }
            return [];
        };
    @endphp

    <div class="section-title" style="margin-top:14px;">Product Matrix — POs Received (Sales Orders)</div>
    <div class="section-sub">Rows: Salesman → Product. Columns: Month-wise sums (SAR).</div>

    <table class="matrix">
        <colgroup>
            <col style="width:70px;">
            <col style="width:145px;">
            @for($i=0;$i<12;$i++)
                <col style="width:42px;">
            @endfor
            <col style="width:55px;">
        </colgroup>

        <thead>
        <tr>
            <th rowspan="2">Salesman</th>
            <th rowspan="2">Product</th>
            <th colspan="13" class="num">POs (SAR)</th>
        </tr>
        <tr>
            @foreach($months as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>

        <tbody>
        @forelse(($poProductMatrix ?? []) as $salesman => $products)
            @php
                $salesmanU = strtoupper(trim((string)$salesman));

                // GM ALL: still apply Western split for ABDO/AHMED
                $useWesternRowsForThisSalesman = $isWestern || ($isAll && in_array($salesmanU, $westernSalesmen, true));
                $order = $useWesternRowsForThisSalesman ? $westernRowOrder : $defaultRowOrder;

                $products = is_array($products) ? $products : [];

                // Normalize product keys to uppercase
                $productsU = [];
                foreach ($products as $k => $v) {
                    $productsU[strtoupper(trim((string)$k))] = $v;
                }

                // Pre-fill ordered map (missing => [])
                $orderedProducts = [];
                foreach ($order as $p) {
                    $orderedProducts[$p] = $productsU[$p] ?? [];
                }

                $displayRows = [];

                if ($useWesternRowsForThisSalesman) {
                    // Robust duct picking (handles backend variants)
                    $ductBase = $pickP($productsU, [
                        'DUCTWORK','DUCT WORK','RECTANGULAR DUCTWORK','GI DUCTWORK','GALVANIZED DUCTWORK',
                        'NORMAL BLACK STEEL DUCTWORK','BLACK STEEL DUCTWORK',
                    ]);

                    $ductPre = $pickP($productsU, [
                        'PRE-INSULATED DUCTWORK','PRE INSULATED DUCTWORK','PRE-INSULATED','PRE INSULATED',
                        'PREINSULATED','PRE INSULATED DUCT','PRE-INSULATED DUCT','PID',
                    ]);

                    $ductSpiral = $pickP($productsU, ['SPIRAL DUCTWORK','SPIRAL DUCT','SPIRAL','SPIRAL DUCTS']);

                    $ductTotalRow = $sumRow13($ductBase, $sumRow13($ductPre, $ductSpiral));

                    // Parent
                    $displayRows[] = [
                        'key'   => 'DUCTWORK',
                        'label' => 'DUCTWORK',
                        'row'   => $ductTotalRow,
                        'type'  => 'parent',
                    ];

                    // Children (shown only for Western rows set)
                    $displayRows[] = [
                        'key'   => 'PRE-INSULATED DUCTWORK',
                        'label' => '— PRE-INSULATED DUCTWORK',
                        'row'   => $ductPre,
                        'type'  => 'child',
                        'parent_total' => (float)($ductTotalRow[12] ?? 0),
                    ];

                    $displayRows[] = [
                        'key'   => 'SPIRAL DUCTWORK',
                        'label' => '— SPIRAL DUCTWORK',
                        'row'   => $ductSpiral,
                        'type'  => 'child',
                        'parent_total' => (float)($ductTotalRow[12] ?? 0),
                    ];

                    // Remaining families in stable order
                    foreach ($order as $p) {
                        if (in_array($p, ['DUCTWORK','PRE-INSULATED DUCTWORK','SPIRAL DUCTWORK'], true)) continue;
                        $displayRows[] = [
                            'key'   => $p,
                            'label' => $p,
                            'row'   => $orderedProducts[$p] ?? [],
                            'type'  => 'normal',
                        ];
                    }
                } else {
                    foreach ($order as $p) {
                        $displayRows[] = [
                            'key'   => $p,
                            'label' => $p,
                            'row'   => $orderedProducts[$p] ?? [],
                            'type'  => 'normal',
                        ];
                    }
                }

                // Salesman total = parent + normal only (no double counting children)
                $salesmanTotal = 0.0;
                foreach ($displayRows as $dr) {
                    if (($dr['type'] ?? '') === 'child') continue;
                    $tmp = $pad13row($dr['row'] ?? []);
                    $salesmanTotal += (float)($tmp[12] ?? 0);
                }
            @endphp

            <tr class="salesman-group">
                <td class="salesmanCell">
                    <span class="salesman-badge {{ $badgeClass($salesmanU) }}">{{ $salesmanU }}</span>
                </td>
                <td colspan="14" class="group-fill">Products Breakdown</td>
            </tr>

            @foreach($displayRows as $dr)
                @php
                    $row = $pad13row($dr['row'] ?? []);
                    $prodTotal = (float)($row[12] ?? 0);

                    // Parent/Normal: share of salesman total
                    $actualPct = $salesmanTotal > 0 ? round(($prodTotal / $salesmanTotal) * 100, 1) : 0.0;

                    // Child: share of ductwork total
                    $childPct = null;
                    if (($dr['type'] ?? '') === 'child') {
                        $pt = (float)($dr['parent_total'] ?? 0);
                        $childPct = $pt > 0 ? round(($prodTotal / $pt) * 100, 1) : 0.0;
                    }

                    // Flags only for parent/normal
                    $flagTxt = '';
                    $flagCls = '';
                    $target  = null;

                    if (($dr['type'] ?? '') !== 'child') {
                        $k = strtoupper(trim((string)$dr['key']));
                        [$flagTxt, $flagCls] = $mixFlag($k, $actualPct);
                        $target = $mixTargets[$k] ?? null;
                    }
                @endphp

                <tr class="{{ ($dr['type'] ?? '') === 'child' ? 'subproduct-row' : 'product-row' }}">
                    <td class="salesmanSpacer">&nbsp;</td>

                    <td class="rowLabelCell {{ ($dr['type'] ?? '') === 'child' ? 'subproduct-label' : '' }}">
                        {{ strtoupper($dr['label']) }}

                        @if(($dr['type'] ?? '') !== 'child')
                            <span class="small-muted">
                                {{ $actualPct }}%{{ $target !== null ? ' (T:' . $target . '%)' : '' }}
                            </span>
                            @if($flagTxt !== '')
                                <span class="flag-pill {{ $flagCls }}">{{ $flagTxt }}</span>
                            @endif
                        @else
                            <span class="small-muted">{{ $childPct }}% of Ductwork</span>
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

{{-- ======================================================================
   PAGE 4: Performance Matrix (Forecast / Target / Achievement / Inquiries / POs / Conversion)
   - Only show month achievement for current month (others "-")
   - Total achievement uses Total POs / Annual Target
   ====================================================================== --}}
<div class="page-break"></div>
<div class="page">

    <div class="section-title">Performance Matrix — Forecast / Target / Inquiries / POs / Conversion</div>
    <div class="section-sub">Conversion% = POs ÷ Inquiries. Total conversion is calculated from totals (not sum of %).</div>

    @php
        $salesmanKpiMatrix = $salesmanKpiMatrix ?? [];

        $labels = [
            'FORECAST'  => 'Forecast',
            'TARGET'    => 'Target',
            'PERF'      => 'Target Achievement',
            'INQUIRIES' => 'Inquiries',
            'POS'       => 'Sales Orders',
            'CONV_PCT'  => 'Conversion rate %',
        ];

        $pad13 = function($arr){
            $arr = is_array($arr) ? array_values($arr) : [];
            $arr = array_pad($arr, 13, 0);
            return array_slice($arr, 0, 13);
        };

        // Build conversion % row (month-wise) + total conversion from totals
        $buildConvRow = function($inqRow, $poRow) use ($pad13){
            $inqRow = $pad13($inqRow);
            $poRow  = $pad13($poRow);

            $out = [];
            for ($i=0; $i<12; $i++){
                $inq = (float)$inqRow[$i];
                $po  = (float)$poRow[$i];
                $out[$i] = $inq > 0 ? round(($po / $inq) * 100, 1) : 0.0;
            }

            $tInq = (float)$inqRow[12];
            $tPo  = (float)$poRow[12];
            $out[12] = $tInq > 0 ? round(($tPo / $tInq) * 100, 1) : 0.0;

            return $out;
        };

        // Determine report date + current month (for the achievement-only-current-month rule)
        $reportDate = null;
        try {
            $reportDate = Carbon::createFromFormat('d-m-Y', (string)$today);
        } catch (Throwable $e) {
            $reportDate = Carbon::now();
        }

        $currentMonth = (int)$reportDate->month;

        // Render performance cell (flag badges)
        $perfCell = function(float $actual, float $expected, bool $isFuture) {
            if ($isFuture) return ['html' => '<span class="flag flag-na">N/A</span>'];

            if ($expected <= 0) {
                if ($actual <= 0) return ['html' => '<span class="flag flag-danger">0% • DANGER</span>'];
                return ['html' => '<span class="flag flag-na">NO TARGET</span>'];
            }

            if ($actual <= 0) return ['html' => '<span class="flag flag-danger">0% • DANGER</span>'];

            $pct = ($actual / $expected) * 100.0;

            if ($pct >= 100) return ['html' => '<span class="flag flag-excellent">' . number_format($pct,0) . '%</span>'];
            if ($pct >= 60)  return ['html' => '<span class="flag flag-good">' . number_format($pct,0) . '%</span>'];
            if ($pct >= 50)  return ['html' => '<span class="flag flag-acceptable">' . number_format($pct,0) . '%</span>'];
            if ($pct >= 20)  return ['html' => '<span class="flag flag-attn">' . number_format($pct,0) . '%</span>'];

            return ['html' => '<span class="flag flag-danger">' . number_format($pct,0) . '%</span>'];
        };

        // Build PERF row:
        // - Month-wise: ONLY current month shows achievement, others "-"
        // - Total: Total POs / Annual Target
        $buildPerfRow = function($poRow, $targetRow) use ($pad13, $perfCell, $currentMonth) {
            $poRow     = $pad13($poRow);
            $targetRow = $pad13($targetRow);

            $out = [];

            $currentIdx = max(0, min(11, (int)$currentMonth - 1));

            for ($i=0; $i<12; $i++) {
                if ($i !== $currentIdx) {
                    $out[$i] = ['html' => '<span class="flag flag-na">-</span>'];
                    continue;
                }

                $actual   = (float)$poRow[$i];
                $expected = (float)$targetRow[$i];

                if ($expected <= 0) {
                    $out[$i] = ['html' => '<span class="flag flag-na">-</span>'];
                    continue;
                }

                $out[$i] = $perfCell($actual, $expected, false);
            }

            $actualTotal = (float)$poRow[12];
            $annualTarget = (float)$targetRow[12];

            $out[12] = ($annualTarget <= 0)
                ? ['html' => '<span class="flag flag-na">-</span>']
                : $perfCell($actualTotal, $annualTarget, false);

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

{{-- ======================================================================
   PAGE 5: Business Intelligence Summary (NEW schema)
   - If area=ALL => GM executive boxes
   - Else => salesman text analysis
   ====================================================================== --}}
<div class="page-break"></div>

@php
    $ins       = $insights ?? [];
    $meta      = $ins['meta'] ?? [];
    $areaLocal = (string)($meta['area'] ?? ($area ?? 'All'));
    $isAll     = (strtoupper(trim($areaLocal)) === 'ALL');
@endphp

@if($isAll)
    @include('reports.partials.gm_executive_boxes', [
        'insights' => $ins,
        'kpis'     => $kpis ?? [],
        'today'    => $today ?? null,
        'year'     => $year ?? null,
        'area'     => $areaLocal,
    ])
@else
    @include('reports.partials.salesman_text_analysis', [
        'insights' => $ins,
        'today'    => $today ?? null,
        'year'     => $year ?? null,
        'area'     => $areaLocal,
    ])
@endif

</body>
</html>
