<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ATAI – Area Summary {{ $year }}</title>
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
        .header-left {
            float: left;
        }
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
        .kpi-strip {
            margin-top: 10px;
            margin-bottom: 10px;
            width: 100%;
        }
        .kpi-card {
            display: inline-block;
            vertical-align: top;
            padding: 8px 10px;
            margin-right: 6px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            min-width: 200px;
        }
        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
        }
        .kpi-value {
            font-size: 13px;
            font-weight: 700;
            margin-top: 3px;
        }
        .kpi-footnote {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }

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

        /* --------- Area badges ---------- */
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
            <h1>ATAI – Area Summary</h1>
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
    @endphp

    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-label">Inquiries Total</div>
            <div class="kpi-value">SAR {{ number_format($inqTotal, 0) }}</div>
            <div class="kpi-footnote">Sum of enquiry values (projects)</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">POs Total</div>
            <div class="kpi-value">SAR {{ number_format($poTotal, 0) }}</div>
            <div class="kpi-footnote">Sum of PO values (sales orders)</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Gap Coverage (POs vs Quotations)</div>
            <div class="kpi-value">{{ $gapPct }}%</div>
            <div class="kpi-footnote">
                Gap: SAR {{ number_format($gapVal, 0) }} –
                @if($poTotal < $inqTotal)
                    More quoted than POs
                @else
                    More POs than quoted
                @endif
            </div>
        </div>
    </div>

    @if($chartImagePath)
        <div class="chart-wrapper" style="margin: 12px 0;">
            <img src="{{ $chartImagePath }}" style="width:100%; max-height:260px;">
        </div>
    @endif

    <div id="poVsQuoteArea"></div>
    {{-- ========= TOTAL SUMMARY (replaces main Highchart total) ========= --}}
{{--    <div class="summary-card">--}}
{{--        <div class="summary-title">--}}
{{--            Quotations vs POs (Total) — Year {{ $year }}--}}
{{--        </div>--}}
{{--        <table class="summary-table">--}}
{{--            <thead>--}}
{{--            <tr>--}}
{{--                <th>Metric</th>--}}
{{--                <th class="num">Amount (SAR)</th>--}}
{{--            </tr>--}}
{{--            </thead>--}}
{{--            <tbody>--}}
{{--            <tr>--}}
{{--                <td>Quotations (Inquiries)</td>--}}
{{--                <td class="num">SAR {{ number_format($inqTotal, 0) }}</td>--}}
{{--            </tr>--}}
{{--            <tr>--}}
{{--                <td>POs Received</td>--}}
{{--                <td class="num">SAR {{ number_format($poTotal, 0) }}</td>--}}
{{--            </tr>--}}
{{--            <tr>--}}
{{--                <td>Gap (Quotations – POs)</td>--}}
{{--                <td class="num">SAR {{ number_format($gapVal, 0) }}</td>--}}
{{--            </tr>--}}
{{--            </tbody>--}}
{{--        </table>--}}
{{--    </div>--}}

    {{-- ========= AREA SUMMARY (this mimics the "Quotations vs POs by Area" chart) ========= --}}
    @php
        // Build summary by area using the totals from the two pivot arrays
        $areaSummary = [];

        foreach ($inquiriesByArea as $area => $row) {
            // last value in the row = yearly total
            $inqAreaTotal = (float) end($row);

            $posRow       = $posByArea[$area] ?? array_fill(0, count($row), 0);
            $posAreaTotal = (float) end($posRow);

            $areaSummary[] = [
                'area' => $area,
                'inq'  => $inqAreaTotal,
                'pos'  => $posAreaTotal,
            ];
        }
    @endphp

    <div class="summary-card">
        <div class="summary-title">
            Quotations vs POs by Area — Year {{ $year }}
        </div>
        <table class="summary-table">
            <thead>
            <tr>
                <th>Area</th>
                <th class="num">Quotations (SAR)</th>
                <th class="num">POs Received (SAR)</th>
            </tr>
            </thead>
            <tbody>
            @foreach($areaSummary as $row)
                @php $aLower = strtolower($row['area']); @endphp
                <tr>
                    <td>
                        <span class="area-badge {{ $aLower }}">{{ strtoupper($row['area']) }}</span>
                    </td>
                    <td class="num">SAR {{ number_format($row['inq'], 0) }}</td>
                    <td class="num">SAR {{ number_format($row['pos'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- ========= INQUIRIES TABLE ========= --}}
    <div class="section-title">Inquiries (Estimations) — sums by area</div>
    <div class="section-sub">Values by month based on quotation date (projects table).</div>

    <table class="data">
        <thead>
        <tr>
            <th>Area</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($inquiriesByArea as $area => $row)
            <tr>
                <td>
                    @php $aLower = strtolower($area); @endphp
                    <span class="area-badge {{ $aLower }}">{{ strtoupper($area) }}</span>
                </td>
                @foreach($row as $val)
                    <td class="num">SAR {{ number_format($val, 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- ========= POS TABLE ========= --}}
    <div class="section-title" style="margin-top:12px;">
        POs (Sales Orders Received) — sums by area
    </div>
    <div class="section-sub">Values by month based on PO received date (salesorderlog table).</div>

    <table class="data">
        <thead>
        <tr>
            <th>Area</th>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Total'] as $m)
                <th class="num">{{ strtoupper($m) }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($posByArea as $area => $row)
            <tr>
                <td>
                    @php $aLower = strtolower($area); @endphp
                    <span class="area-badge {{ $aLower }}">{{ strtoupper($area) }}</span>
                </td>
                @foreach($row as $val)
                    <td class="num">SAR {{ number_format($val, 0) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

</div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
    const CURRENT_YEAR = {{ $year }};
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    function saveAreaChartImage() {
        const container = document.getElementById('poVsQuoteArea');
        if (!container) return;

        html2canvas(container, {
            backgroundColor: '#111827',  // or your card background
            scale: 2                     // higher resolution
        }).then(canvas => {
            const dataUrl = canvas.toDataURL('image/png');

            return fetch(@json(route('performance.area-chart.save')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify({
                    year: CURRENT_YEAR,
                    image: dataUrl,
                }),
            });
        }).then(r => r.json())
            .then(resp => {
                console.log('Chart saved:', resp);
            }).catch(err => {
            console.error('Error saving chart image', err);
        });
    }
</script>
</body>
</html>
