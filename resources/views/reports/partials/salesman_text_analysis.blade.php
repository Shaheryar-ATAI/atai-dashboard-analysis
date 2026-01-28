@php
    use Illuminate\Support\Str;

    $ins  = $insights ?? [];
    $meta = $ins['meta'] ?? [];

    $yearLocal = (int)($meta['year'] ?? ($year ?? now()->year));
    $areaLocal = (string)($meta['area'] ?? ($area ?? 'All'));
    $areaNorm  = strtoupper(trim($areaLocal));

    $showRegionHeading = ($areaNorm !== 'ALL' && $areaNorm !== '');

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
@endphp

<style>
    /* tighter typography to avoid extra page */
    .bi { font-family: DejaVu Sans, Arial, sans-serif; font-size:11px; color:#111; }

    .bi-title { font-size: 16px; font-weight: 900; margin: 0 0 6px 0; }
    .bi-debug { font-size: 9px; color:#b14a3a; margin: 0 0 8px 0; }

    h3 { font-size: 12px; margin: 8px 0 5px 0; font-weight: 900; }
    h4 { font-size: 11px; margin: 6px 0 3px 0; font-weight: 900; }

    ul { margin: 2px 0 6px 16px; padding: 0; }
    ul li { margin: 2px 0; line-height: 1.25; }

    ol { margin: 3px 0 6px 16px; padding: 0; }
    ol li { margin: 6px 0; line-height: 1.25; page-break-inside: avoid; }

    .sec-high { color:#1e8e3e; font-weight: 900; }
    .sec-low  { color:#b06000; font-weight: 900; }
    .sec-attn { color:#b00020; font-weight: 900; }

    .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:7px; position:relative; top:1px; }
    .dot-green { background:#1e8e3e; }
    .dot-amber { background:#f29900; }
    .dot-red   { background:#d93025; }

    .ins-title { font-weight: 700; }

    .badge {
        display:inline-block;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
        border: 1px solid #ddd;
        margin-left: 6px;
        background: #fafafa;
    }
    .c-high { color:#1e8e3e; border-color:#cfe8d6; background:#eef8f1; }
    .c-med  { color:#b26a00; border-color:#f5deb8; background:#fff6e8; }
    .c-low  { color:#b00020; border-color:#f2c2c9; background:#fff0f2; }

    .action { margin-top: 2px; }
    .action strong { font-weight: 900; }

    /* One-line management summary (prominent but compact) */
    .one-line-summary {
        border-left: 4px solid #0ea5e9;
        background: #f8fafc;
        padding: 8px 12px;
        margin-top: 8px;
        margin-bottom: 8px;
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
    .summary-text {
        margin: 4px 0 0 0;
        font-size: 10.8px;
        font-weight: 900;
        line-height: 1.25;
        color: #111827;
    }
</style>

<div class="bi">

    @if($showRegionHeading)
        <div class="bi-title">
            Business Intelligence Summary ({{ $yearLocal }} • {{ Str::title(strtolower($areaLocal)) }})
        </div>
    @endif

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
                $title = (string)($it['title'] ?? '');
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
                $title  = (string)($it['title'] ?? '');
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
                $title = (string)($it['title'] ?? '');
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

</div>
