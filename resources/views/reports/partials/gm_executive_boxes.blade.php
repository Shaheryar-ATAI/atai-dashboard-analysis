@php
    use Illuminate\Support\Str;

    $ins  = $insights ?? [];
    $meta = $ins['meta'] ?? [];

    $yearLocal = (int)($meta['year'] ?? (isset($year) ? $year : now()->year));
    $areaLocal = (string)($meta['area'] ?? (isset($area) ? $area : 'All'));
    $areaNorm  = strtoupper(trim($areaLocal));

    $isAll = ($areaNorm === 'ALL' || $areaNorm === '');

    $topN = (int)($meta['priority_top_n'] ?? 10);
    $showDebug = (bool)($meta['show_debug'] ?? false);

    $confColor = function ($c) {
        $c = strtoupper(trim((string)$c));
        return match(true) {
            str_contains($c, 'HIGH') => 'c-high',
            str_contains($c, 'MED')  => 'c-med',
            str_contains($c, 'LOW')  => 'c-low',
            default                  => 'c-med',
        };
    };

    $normalizeAction = function ($action) use ($topN) {
        $action = (string)$action;
        if ($action === '') return '';
        return preg_replace('/\btop\s+\d+\b/i', 'top ' . $topN, $action);
    };

    /* ✅ FIX DUCT ISSUE (without touching confidence)
       Example: "DUCTWORK (under DUCTWORK)" -> "DUCTWORK"
    */
    $cleanTitle = function(string $title){
        $t = trim($title);
        if ($t === '') return $t;

        if (preg_match('/\(\s*under\s+([^)]+)\)/i', $t, $m)) {
            $parent = trim($m[1] ?? '');
            // if title already contains the same parent name, drop "(under ...)"
            if ($parent !== '' && stripos($t, $parent) !== false) {
                $t = trim(preg_replace('/\s*\(\s*under\s+[^)]+\)\s*/i', ' ', $t));
                $t = preg_replace('/\s{2,}/', ' ', $t);
            }
        }
        return $t;
    };
@endphp

<style>
    /* compact + PDF safe */
    .bi {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10px;                 /* was 10.2 */
        color:#111;
    }

    /* ====== HEADER: make smaller so content fits on 1 page ====== */
    .bi-title {
        font-size: 13px;                 /* was 16 */
        font-weight: 900;
        margin: 0 0 4px 0;               /* was 0 0 8px 0 */
    }

    .bi-meta  {
        font-size: 8px;                  /* was 9 */
        color:#6b7280;
        text-align:right;
        margin: 0 0 6px 0;               /* was 0 0 10px 0 */
    }


    .bi-debug { font-size: 9px; color:#b14a3a; margin: 0 0 8px 0; }

    .bi {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10px;                 /* was 10.2 */
        color:#111;
    }

    /* ====== HEADER: make smaller so content fits on 1 page ====== */
    .bi-title {
        font-size: 13px;                 /* was 16 */
        font-weight: 900;
        margin: 0 0 4px 0;               /* was 0 0 8px 0 */
    }

    .bi-meta  {
        font-size: 8px;                  /* was 9 */
        color:#6b7280;
        text-align:right;
        margin: 0 0 6px 0;               /* was 0 0 10px 0 */
    }


    ol { margin: 3px 0 6px 16px; padding: 0; }
    ol li { margin: 6px 0; line-height: 1.25; page-break-inside: avoid; }

    .sec-high { color:#1e8e3e; font-weight: 900; }
    .sec-low  { color:#b06000; font-weight: 900; }
    .sec-attn { color:#b00020; font-weight: 900; }

    .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:7px; position:relative; top:1px; }
    .dot-green { background:#1e8e3e; }
    .dot-amber { background:#f29900; }
    .dot-red   { background:#d93025; }

    .ins-title { font-weight: 800; }

    .badge {
        display:inline-block;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
        border: 1px solid #ddd;
        margin-left: 6px;
        background: #fafafa;
        white-space: nowrap;
    }
    .c-high { color:#1e8e3e; border-color:#cfe8d6; background:#eef8f1; }
    .c-med  { color:#b26a00; border-color:#f5deb8; background:#fff6e8; }
    .c-low  { color:#b00020; border-color:#f2c2c9; background:#fff0f2; }

    .action { margin-top: 2px; }
    .action strong { font-weight: 900; }

    /* One-line management summary (prominent but compact) */
    .one-line-summary{
        border-left: 4px solid #0ea5e9;
        background:#f8fafc;
        padding: 6px 10px;               /* was 8px 12px */
        margin-top: 6px;                 /* was 8px */
        margin-bottom: 6px;              /* was 8px */
        border-radius: 6px;
    }
    .summary-label {
        display: inline-block;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: 0.06em;
        color: #0369a1;
        background: #e0f2fe;
        padding: 2px 6px;
        border-radius: 4px;
        margin-bottom: 4px;
    }
    .summary-text{
        margin: 3px 0 0 0;
        font-size: 10.2px;               /* was 10.8 */
        font-weight: 900;
        line-height: 1.2;
    }

    /* ====== WRAPPER + GRID spacing tighter but no clipping ====== */
    .gm-wrap {
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:10px;                    /* was 14px */
        background:#ffffff;
    }

    /* KPI row tighter */
    .gm-kpis {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
        margin: 4px 0 8px 0;             /* was 6px 0 12px 0 */
    }
    .gm-kpis td { padding:0 6px 0 0; } /* was 0 8px 0 0 */

    /* KPI cards slightly shorter (still readable) */
    .gm-kpi{
        border:1px solid #e5e7eb;
        border-radius:10px;
        padding:8px 10px;                /* was 10px 12px */
        background:#f3f4f6;
    }
    .gm-kpi { min-height: 46px; }      /* was height: 54px */

    /* grid spacing tighter */
    .gm-grid td { padding:0 6px 0 0; } /* was 0 8px 0 0 */

    /* cards: KEEP auto height + visible overflow (no cut text) */
    .gm-card{
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:10px 12px;               /* was 12px 14px */
        background:#f3f4f6;

        height: auto;
        min-height: 150px;               /* was 175px */
        overflow: visible;

        page-break-inside: avoid;
        break-inside: avoid;
    }

    .gm-card ul { margin: 3px 0 0 14px; }
    .gm-card li { margin: 1px 0; line-height:1.22; }
</style>

<div class="bi">
    @if($isAll)
        {{-- ================= GM BOX VIEW (Area = ALL) ================= --}}
        @php
            // Try to pull totals from snapshot lines if you already embed them there.
            // If not available, these will just show the text blocks; layout still works.
        @endphp

        <div class="gm-wrap">
            <table class="gm-top">
                <tr>
                    <td style="width:70%;">
                        <div class="bi-title">ATAI – Executive Insights (AI Appendix)</div>
                    </td>
                    <td style="width:30%; text-align:right;">
                        <div class="bi-meta">
                            Report Date: {{ $today ?? '' }} &nbsp; | &nbsp; Year: {{ $yearLocal }} &nbsp; | &nbsp; Area: ALL
                        </div>
                    </td>
                </tr>
            </table>

            {{-- KPI row (optional values if you pass them in meta later; safe if empty) --}}
            <table class="gm-kpis">
                <tr>
                    <td>
                        <div class="gm-kpi">
                            <div class="lbl">Inquiries Total (YTD)</div>
                            <div class="val">{{ $meta['kpi_inquiries'] ?? '—' }}</div>
                            <div class="sub">{{ $meta['kpi_inquiries_sub'] ?? '' }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="gm-kpi">
                            <div class="lbl">POs Total (YTD)</div>
                            <div class="val">{{ $meta['kpi_pos'] ?? '—' }}</div>
                            <div class="sub">{{ $meta['kpi_pos_sub'] ?? '' }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="gm-kpi">
                            <div class="lbl">Achievement (PO / Quote)</div>
                            <div class="val">{{ $meta['kpi_conversion'] ?? '—' }}</div>
                            <div class="sub">{{ $meta['kpi_conversion_sub'] ?? '' }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="gm-grid">
                <tr>
                    <td style="width:50%;">
                        <div class="gm-card">
                            <h4>Executive Summary (GM-ready)</h4>
                            <ul>
                                @foreach(($ins['overall_analysis']['snapshot'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
                                @foreach(($ins['overall_analysis']['regional_key_points'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
                            </ul>
                        </div>
                    </td>
                    <td style="width:50%;">
                        <div class="gm-card">
                            <h4>Low Insights / Risks (What needs action)</h4>
                            <ul>
                                @foreach(($ins['what_needs_attention'] ?? []) as $it)
                                    @php
                                        $title = $cleanTitle((string)($it['title'] ?? ''));
                                        $text  = (string)($it['text'] ?? '');
                                        $conf  = (string)($it['confidence'] ?? '');
                                    @endphp
                                    <li>
                                        <strong>{{ $title }}</strong>
                                        @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                                        <div>{{ $text }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="width:50%; padding-top:10px;">
                        <div class="gm-card">
                            <h4>High Insights (Wins / Opportunities)</h4>
                            <ul>
                                @foreach(($ins['high_insights'] ?? []) as $it)
                                    @php
                                        $title = $cleanTitle((string)($it['title'] ?? ''));
                                        $text  = (string)($it['text'] ?? '');
                                        $conf  = (string)($it['confidence'] ?? '');
                                    @endphp
                                    <li>
                                        <strong>{{ $title }}</strong>
                                        @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                                        <div>{{ $text }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </td>

                    <td style="width:50%; padding-top:10px;">
                        <div class="gm-card">
                            <h4>14-Day Action Plan (Owners + measurable)</h4>
                            <ul>
                                @foreach(($ins['low_insights'] ?? []) as $it)
                                    @php
                                        $title  = $cleanTitle((string)($it['title'] ?? ''));
                                        $act    = $normalizeAction($it['gm_interpretation'] ?? '');
                                        $conf   = (string)($it['confidence'] ?? '');
                                    @endphp
                                    @if($act !== '')
                                        <li>
                                            <strong>{{ $title }}</strong>
                                            @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                                            <div>{{ $act }}</div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="one-line-summary" style="margin-top:12px;">
                <span class="summary-label">EXECUTIVE TAKEAWAY</span>
                <p class="summary-text">{{ $ins['one_line_summary'] ?? '' }}</p>
            </div>
        </div>

    @else
        {{-- ================= REGION / SALESMAN LIST VIEW ================= --}}
        <div class="bi-title">
            Business Intelligence Summary ({{ $yearLocal }} • {{ Str::title(strtolower($areaLocal)) }})
        </div>

        @if($showDebug)
            <div class="bi-debug">
                DEBUG meta: {"engine":"{{ $meta['engine'] ?? 'rules' }}","generated_at":"{{ $meta['generated_at'] ?? '' }}","area":"{{ $meta['area'] ?? '' }}","year":{{ $meta['year'] ?? '' }}}
            </div>
        @endif

        <h3>Overall Analysis</h3>
        <ul>
            @foreach(($ins['overall_analysis']['snapshot'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
            @foreach(($ins['overall_analysis']['regional_key_points'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
            @foreach(($ins['overall_analysis']['salesman_key_points'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
            @foreach(($ins['overall_analysis']['product_key_points'] ?? []) as $t) <li>{{ $t }}</li> @endforeach
        </ul>

        <h3><span class="sec-high">High Insights (High-Confidence)</span></h3>
        <ol>
            @foreach(($ins['high_insights'] ?? []) as $it)
                @php
                    $title = $cleanTitle((string)($it['title'] ?? ''));
                    $text  = (string)($it['text'] ?? '');
                    $conf  = (string)($it['confidence'] ?? '');
                    $act   = $normalizeAction($it['gm_action'] ?? '');
                @endphp
                <li>
                    <span class="dot dot-green"></span>
                    <span class="ins-title">{{ $title }}</span>
                    @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                    <div>{{ $text }}</div>
                    @if($act !== '') <div class="action"><strong>Action:</strong> {{ $act }}</div> @endif
                </li>
            @endforeach
        </ol>

        <h3><span class="sec-low">Low Insights (Low-Confidence)</span></h3>
        <ol>
            @foreach(($ins['low_insights'] ?? []) as $it)
                @php
                    $title  = $cleanTitle((string)($it['title'] ?? ''));
                    $text   = (string)($it['text'] ?? '');
                    $conf   = (string)($it['confidence'] ?? '');
                    $interp = (string)($it['gm_interpretation'] ?? '');
                @endphp
                <li>
                    <span class="dot dot-amber"></span>
                    <span class="ins-title">{{ $title }}</span>
                    @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                    <div>{{ $text }}</div>
                    @if($interp !== '') <div class="action"><strong>Interpretation:</strong> {{ $interp }}</div> @endif
                </li>
            @endforeach
        </ol>

        <h3><span class="sec-attn">What needs attention</span></h3>
        <ol>
            @foreach(($ins['what_needs_attention'] ?? []) as $it)
                @php
                    $title = $cleanTitle((string)($it['title'] ?? ''));
                    $text  = (string)($it['text'] ?? '');
                    $conf  = (string)($it['confidence'] ?? '');
                @endphp
                <li>
                    <span class="dot dot-red"></span>
                    <span class="ins-title">{{ $title }}</span>
                    @if($conf !== '') <span class="badge {{ $confColor($conf) }}">Confidence: {{ $conf }}</span> @endif
                    <div>{{ $text }}</div>
                </li>
            @endforeach
        </ol>

        <h3>One-Line Management Summary</h3>
        <div class="one-line-summary">
            <span class="summary-label">EXECUTIVE TAKEAWAY</span>
            <p class="summary-text">{{ $ins['one_line_summary'] ?? '' }}</p>
        </div>
    @endif
</div>
