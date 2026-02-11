<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class SalesmanSummaryInsightService
{ /**
 * Region -> canonical salesmen (scope).
 * Keep in ONE place to avoid scoping mismatches.
 */
    private array $regionSalesmen = [
    'Eastern' => ['SOHAIB', 'SOAHIB'],
    'Central' => ['TARIQ', 'TAREQ', 'JAMAL'],
    'Western' => ['ABDO', 'AHMED'],
];

    /**
     * Region focus families (must show even if not top).
     * Western requirement.
     */
    private array $regionFocusFamilies = [
    'Western' => ['PRE-INSULATED DUCTWORK', 'SPIRAL DUCTWORK'],
];

    /**
     * Public entrypoint.
     * Input payload is the controller facts payload (existing buildSalesmanSummaryPayload output).
     * Must return NEW schema always.
     */
    public function generate(array $payload): array
{
    $area = (string)($payload['area'] ?? ($payload['scope']['area'] ?? 'All'));
    $year = (int)($payload['year'] ?? ($payload['scope']['year'] ?? now()->year));

    // 1) Facts (scoped + totals)
    $facts = $this->extractFacts($payload, $area, $year);

    $isGmView = (strtoupper(trim((string)($facts['scope']['area'] ?? $area))) === 'ALL');
    $facts['meta_flags'] = [
        'is_gm_view' => $isGmView,
    ];

    // 2) Build NEW schema skeleton FIRST
    $activeMonths = (int)($facts['month_coverage']['active_month_count'] ?? 0);
    $ins = $this->newSchemaSkeleton($area, $year, $activeMonths);

    // 3) GM KPI cards (Blade reads: meta.kpi_inquiries / meta.kpi_pos / meta.kpi_conversion)
    $q = (float)($facts['snapshot']['quoted_total'] ?? 0);        // Quoted / Inquiries value
    $p = (float)($facts['snapshot']['po_total'] ?? 0);            // PO value
    $c = (float)($facts['snapshot']['conversion_pct'] ?? 0);      // PO ÷ Quote %

    $ins['meta']['kpi_inquiries']  = 'SAR ' . number_format($q, 0, '.', ',');
    $ins['meta']['kpi_pos']        = 'SAR ' . number_format($p, 0, '.', ',');
    $ins['meta']['kpi_conversion'] = number_format($c, 1) . '%';

    // Optional sub-labels
    $ins['meta']['kpi_inquiries_sub']  = 'Quoted Value (YTD)';
    $ins['meta']['kpi_pos_sub']        = 'PO Value (YTD)';
    $ins['meta']['kpi_conversion_sub'] = 'Execution Rate';

    // 4) Fill overall analysis blocks
    $this->buildOverallAnalysis($ins, $facts);

    // 5) Fill insights lists
    $this->buildInsights($ins, $facts);

    // 6) One line summary
    $ins['one_line_summary'] = $this->oneLineSummary($facts);

    // 7) Meta rag/confidence (overall)
    $ins['meta']['rag'] = $this->overallRag($facts);
    $ins['meta']['confidence'] = $this->overallConfidence($facts);

    return $ins;
}

    /* ============================================================
     | FACTS (single source of truth)
     * ============================================================ */

    private function extractFacts(array $payload, string $area, int $year): array
{
    // Existing pivots
    $inqBySalesman = (array)($payload['inquiriesBySalesman'] ?? []);
    $poBySalesman  = (array)($payload['posBySalesman'] ?? []);

    $inqByRegion = (array)($payload['inqByRegion'] ?? []);
    $poByRegion  = (array)($payload['poByRegion'] ?? []);

    $inqProductMatrix = (array)($payload['inqProductMatrix'] ?? []);
    $poProductMatrix  = (array)($payload['poProductMatrix'] ?? []);
    $salesmanKpiMatrix = (array)($payload['salesmanKpiMatrix'] ?? []);

    $targets = (array)($payload['targets'] ?? []);
    $conversionModel = (array)($payload['conversion_model'] ?? []);

    // Normalize scope flags
    $areaU = strtoupper(trim($area));
    $isGmView = ($areaU === 'ALL');
    $isWesternScope = ($areaU === 'WESTERN');

    // Scope salesmen for area
    $scopeSalesmen = $this->salesmenForArea($area);
    $inqBySalesmanScoped = $this->filterByAllowedKeys($inqBySalesman, $scopeSalesmen);
    $poBySalesmanScoped  = $this->filterByAllowedKeys($poBySalesman,  $scopeSalesmen);
    $salesmanKpiScoped   = $this->filterByAllowedKeys($salesmanKpiMatrix, $scopeSalesmen);

    // Totals
    $quotedTotal = $this->sumPivotTotals($inqBySalesmanScoped);
    $poTotal     = $this->sumPivotTotals($poBySalesmanScoped);

    $conversionPct = ($quotedTotal > 0) ? round(($poTotal / $quotedTotal) * 100, 1) : 0.0;
    $gapValue = max(0, $quotedTotal - $poTotal);

    // GM dashboard measures (used for Business Insight in all scopes)
    $forecastTotal = $this->sumKpiMeasureTotals($salesmanKpiScoped, 'FORECAST');
    $targetTotal   = $this->sumKpiMeasureTotals($salesmanKpiScoped, 'TARGET');
    $inqKpiTotal   = $this->sumKpiMeasureTotals($salesmanKpiScoped, 'INQUIRIES');
    $poKpiTotal    = $this->sumKpiMeasureTotals($salesmanKpiScoped, 'POS');

    $achievementVsTargetPct = ($targetTotal > 0) ? round(($poKpiTotal / $targetTotal) * 100, 1) : 0.0;
    $pipelineVsTargetPct    = ($targetTotal > 0) ? round(($inqKpiTotal / $targetTotal) * 100, 1) : 0.0;
    $poVsForecastPct        = ($forecastTotal > 0) ? round(($poKpiTotal / $forecastTotal) * 100, 1) : 0.0;

    // Region totals (GM vs single region)
    $regions = $this->buildRegionRows($inqByRegion, $poByRegion, $area);
    [$bestRegion, $worstRegion] = $this->bestWorstByConversion($regions);

    // Salesman rows
    $salesmen = $this->buildSalesmanRows($inqBySalesmanScoped, $poBySalesmanScoped);
    $bestCloser = $this->bestCloser($salesmen);
    $weakClosers = $this->weakClosers($salesmen);

    // Product totals (scoped by area salesmen)
    $inqProdScoped = $this->filterByAllowedKeys($inqProductMatrix, $scopeSalesmen);
    $poProdScoped  = $this->filterByAllowedKeys($poProductMatrix,  $scopeSalesmen);

    $inqProdTotals = $this->sumProductMatrix($inqProdScoped);
    $poProdTotals  = $this->sumProductMatrix($poProdScoped);

    $topPoCategory = $this->topKey($poProdTotals);
    $topPoValue    = $topPoCategory ? (float)($poProdTotals[$topPoCategory] ?? 0) : 0.0;

    $poShares = $this->toShares($poProdTotals);
    $dominancePct = $topPoCategory ? round((float)($poShares[$topPoCategory] ?? 0), 1) : 0.0;

    $zeroPoCategories = array_values(array_keys(array_filter($poProdTotals, fn($v)=> (float)$v <= 0)));

    // Month coverage (use PO monthly totals)
    $poMonthlyTotals = $this->sumMonthlyFromSalesmanPivot($poBySalesmanScoped);
    $activeMonths = array_values(array_keys(array_filter($poMonthlyTotals, fn($v)=> (float)$v > 0)));
    $activeMonthCount = count($activeMonths);

    // Western ductwork split focus (PI + SP)
    // - Western report: use scoped matrices (already ABDO/AHMED)
    // - GM report: compute PI/SP only for ABDO/AHMED (Western subset)
    $westernFocus = null;

    if ($isWesternScope) {
        $westernFocus = $this->computeWesternFocusFromMatrices($inqProdScoped, $poProdScoped);
    } elseif ($isGmView) {
        $focusSalesmen = ['ABDO','AHMED'];
        $inqWestOnly = $this->filterByAllowedKeys($inqProductMatrix, $focusSalesmen);
        $poWestOnly  = $this->filterByAllowedKeys($poProductMatrix,  $focusSalesmen);
        $westernFocus = $this->computeWesternFocusFromMatrices($inqWestOnly, $poWestOnly);
    }

    return [
        'scope' => [
            'area' => $area,
            'year' => $year,
            'selected_salesmen' => empty($scopeSalesmen) ? ['ALL'] : array_values($scopeSalesmen),
        ],
        'meta_flags' => [
            'is_gm_view' => $isGmView,
        ],
        'snapshot' => [
            'quoted_total' => $quotedTotal,
            'po_total' => $poTotal,
            'conversion_pct' => $conversionPct,
            'gap_value' => $gapValue,
        ],
        'gm_measures' => [
            'forecast_total' => $forecastTotal,
            'target_total' => $targetTotal,
            'inquiries_total' => $inqKpiTotal,
            'po_total' => $poKpiTotal,
            'achievement_vs_target_pct' => $achievementVsTargetPct,
            'pipeline_vs_target_pct' => $pipelineVsTargetPct,
            'po_vs_forecast_pct' => $poVsForecastPct,
        ],
        'month_coverage' => [
            'active_month_count' => $activeMonthCount,
            'active_months' => $activeMonths,
            'po_monthly_totals' => $poMonthlyTotals,
        ],
        'regions' => [
            'rows' => $regions,
            'best_region' => $bestRegion,
            'worst_region' => $worstRegion,
        ],
        'salesmen' => [
            'rows' => $salesmen,
            'best_closer' => $bestCloser,
            'weak_closers' => $weakClosers,
        ],
        'product_mix' => [
            'inq_totals' => $inqProdTotals,
            'po_totals' => $poProdTotals,
            'top_po_category' => $topPoCategory,
            'top_po_value' => $topPoValue,
            'dominance_pct' => $dominancePct,
            'zero_po_categories' => $zeroPoCategories,
            'western_focus' => $westernFocus,
        ],
        'targets' => $targets,
        'conversion_model' => $conversionModel,
    ];
}

    /**
     * ✅ FINAL FIX:
     * - Exact match first
     * - Token match second (but NEVER reverse-match)
     * - Prevents "DUCTWORK" accidentally satisfying PI/SP.
     */
    private function computeWesternFocusFromMatrices(array $inqMatrix, array $poMatrix): ?array
{
    $inqTotals = $this->sumProductMatrix($inqMatrix);
    $poTotals  = $this->sumProductMatrix($poMatrix);

    $norm = fn($s) => strtoupper(trim((string)$s));

    // Only PI tokens (must include PRE + INSUL)
    $isPi = function(string $name): bool {
        $u = strtoupper($name);
        return (str_contains($u, 'PRE') && str_contains($u, 'INSUL'));
    };

    // Only SP tokens (must include SPIRAL)
    $isSp = function(string $name): bool {
        $u = strtoupper($name);
        return str_contains($u, 'SPIRAL');
    };

    $sumBy = function(array $totals, callable $predicate): float {
        $sum = 0.0;
        foreach ($totals as $name => $v) {
            if ($predicate((string)$name)) {
                $sum += (float)$v;
            }
        }
        return $sum;
    };

    // Exact-row preference: if exact exists, use it; else sum by predicate
    $exactOrSum = function(array $totals, string $exactKey, callable $predicate) use ($norm, $sumBy): float {
        $exactKeyU = $norm($exactKey);
        foreach ($totals as $name => $v) {
            if ($norm($name) === $exactKeyU) return (float)$v;
        }
        return $sumBy($totals, $predicate);
    };

    $piQ = $exactOrSum($inqTotals, 'PRE-INSULATED DUCTWORK', $isPi);
    $piP = $exactOrSum($poTotals,  'PRE-INSULATED DUCTWORK', $isPi);

    $spQ = $exactOrSum($inqTotals, 'SPIRAL DUCTWORK', $isSp);
    $spP = $exactOrSum($poTotals,  'SPIRAL DUCTWORK', $isSp);

    if (($piQ + $piP + $spQ + $spP) <= 0) return null;

    $piC = ($piQ > 0) ? round(($piP / $piQ) * 100, 1) : 0.0;
    $spC = ($spQ > 0) ? round(($spP / $spQ) * 100, 1) : 0.0;

    return [
        'PI' => ['quoted' => $piQ, 'po' => $piP, 'execution_pct' => $piC],
        'SP' => ['quoted' => $spQ, 'po' => $spP, 'execution_pct' => $spC],
        'scope_label' => 'PRE-INSULATED & SPIRAL (under DUCTWORK)',
    ];
}

    /* ============================================================
     | NEW SCHEMA
     * ============================================================ */

    private function newSchemaSkeleton(string $area, int $year, int $activeMonthCount): array
{
    return [
        'overall_analysis' => [
            'snapshot' => [],
            'regional_key_points' => [],
            'salesman_key_points' => [],
            'product_key_points' => [],
        ],
        'high_insights' => [],
        'low_insights' => [],
        'what_needs_attention' => [],
        'one_line_summary' => '',
        'meta' => [
            'engine' => 'rules',
            'generated_at' => Carbon::now()->toDateTimeString(),
            'area' => $area,
            'year' => $year,
            'active_month_count' => $activeMonthCount,
            'rag' => 'AMBER',
            'confidence' => 'MEDIUM',
        ],
    ];
}

    private function buildOverallAnalysis(array &$ins, array $facts): void
{
    $q = (float)$facts['snapshot']['quoted_total'];
    $p = (float)$facts['snapshot']['po_total'];
    $c = (float)$facts['snapshot']['conversion_pct'];
    $gap = (float)$facts['snapshot']['gap_value'];

    $ins['overall_analysis']['snapshot'][] =
        "Quoted SAR " . $this->fmt($q) . " vs PO SAR " . $this->fmt($p) .
        " (Execution {$c}%). Gap SAR " . $this->fmt($gap) . ".";

    $gm = (array)($facts['gm_measures'] ?? []);
    $fTot = (float)($gm['forecast_total'] ?? 0);
    $tTot = (float)($gm['target_total'] ?? 0);
    $iTot = (float)($gm['inquiries_total'] ?? 0);
    $pTot = (float)($gm['po_total'] ?? 0);
    $achPct = (float)($gm['achievement_vs_target_pct'] ?? 0);
    $pipePct = (float)($gm['pipeline_vs_target_pct'] ?? 0);

    if ($fTot > 0 || $tTot > 0 || $iTot > 0 || $pTot > 0) {
        $ins['overall_analysis']['snapshot'][] =
            "GM measures: Forecast SAR " . $this->fmt($fTot) .
            " | Target SAR " . $this->fmt($tTot) .
            " | Inquiries SAR " . $this->fmt($iTot) .
            " | POs SAR " . $this->fmt($pTot) .
            " | Achievement vs Target {$achPct}% | Pipeline vs Target {$pipePct}%.";
    }

    $mCount = (int)$facts['month_coverage']['active_month_count'];
    if ($mCount <= 1) {
        $ins['overall_analysis']['snapshot'][] =
            "Dataset coverage is {$mCount} active month → treat trend insights as directional only until 3+ active months.";
    }

    $regions = $facts['regions']['rows'] ?? [];
    $isGmView = (bool)($facts['meta_flags']['is_gm_view'] ?? false);

    if ($isGmView) {
        $topRegions = $this->topNAssocByMetric($regions, 3, 'po');
        foreach ($topRegions as $r => $row) {
            $rq = (float)($row['quoted'] ?? 0);
            $rp = (float)($row['po'] ?? 0);
            $rc = (float)($row['conversion_pct'] ?? 0);
            $ins['overall_analysis']['regional_key_points'][] =
                "{$r}: Quoted SAR " . $this->fmt($rq) . " | PO SAR " . $this->fmt($rp) . " | Execution {$rc}%";
        }
    } else {
        $area = (string)($facts['scope']['area'] ?? '');
        $row = $regions[$area] ?? null;
        if (is_array($row)) {
            $rq = (float)($row['quoted'] ?? 0);
            $rp = (float)($row['po'] ?? 0);
            $rc = (float)($row['conversion_pct'] ?? 0);
            $ins['overall_analysis']['regional_key_points'][] =
                "{$area} Portfolio: Quoted SAR " . $this->fmt($rq) . " | PO SAR " . $this->fmt($rp) . " | Execution {$rc}%";
        }
    }

    $salesmen = $facts['salesmen']['rows'] ?? [];

    if ($isGmView) {
        $topSalesmen = $this->topNAssocByMetric($salesmen, 5, 'quoted');
        foreach ($topSalesmen as $s => $row) {
            $sq = (float)($row['quoted'] ?? 0);
            $sp = (float)($row['po'] ?? 0);
            $sc = (float)($row['conversion_pct'] ?? 0);
            $ins['overall_analysis']['salesman_key_points'][] =
                "{$s}: Quoted SAR " . $this->fmt($sq) . " | PO SAR " . $this->fmt($sp) . " | Execution {$sc}%";
        }
    } else {
        $ordered = $this->topNAssocByMetric($salesmen, 99, 'quoted');
        foreach ($ordered as $s => $row) {
            $sq = (float)($row['quoted'] ?? 0);
            $sp = (float)($row['po'] ?? 0);
            $sc = (float)($row['conversion_pct'] ?? 0);
            $ins['overall_analysis']['salesman_key_points'][] =
                "{$s} Contribution: Quoted SAR " . $this->fmt($sq) . " | PO SAR " . $this->fmt($sp) . " | Execution {$sc}%";
        }
    }

    $topCat = (string)($facts['product_mix']['top_po_category'] ?? '');
    $topVal = (float)($facts['product_mix']['top_po_value'] ?? 0);
    $domPct = (float)($facts['product_mix']['dominance_pct'] ?? 0);

    if ($topCat !== '') {
        $ins['overall_analysis']['product_key_points'][] =
            "Top PO category: {$topCat} = SAR " . $this->fmt($topVal) . " (" . $domPct . "% of PO mix).";
    }

    $zeros = (array)($facts['product_mix']['zero_po_categories'] ?? []);
    if (!empty($zeros)) {
        $ins['overall_analysis']['product_key_points'][] =
            "Zero / near-zero PO categories: " . implode(', ', array_slice($zeros, 0, 6)) . ".";
    }

    $this->appendRegionFocusFamilies($ins, $facts);
}

    private function buildInsights(array &$ins, array $facts): void
    {
        $isGmView = (bool)($facts['meta_flags']['is_gm_view'] ?? false);

        $q   = (float)($facts['snapshot']['quoted_total'] ?? 0);
        $p   = (float)($facts['snapshot']['po_total'] ?? 0);
        $c   = (float)($facts['snapshot']['conversion_pct'] ?? 0);
        $gap = (float)($facts['snapshot']['gap_value'] ?? max(0, $q - $p));

        $mCount = (int)($facts['month_coverage']['active_month_count'] ?? 0);
        $gm = (array)($facts['gm_measures'] ?? []);

        // Confidence rules (sales-friendly)
        $trendConf = ($mCount <= 1) ? 'LOW' : (($mCount <= 2) ? 'MEDIUM' : 'HIGH');

        // ---------------------------------------------------------
        // (A) GM dashboard measure analysis (all scopes)
        // ---------------------------------------------------------
        $fTot = (float)($gm['forecast_total'] ?? 0);
        $tTot = (float)($gm['target_total'] ?? 0);
        $iTot = (float)($gm['inquiries_total'] ?? 0);
        $pTot = (float)($gm['po_total'] ?? 0);
        $achPct = (float)($gm['achievement_vs_target_pct'] ?? 0);
        $pipePct = (float)($gm['pipeline_vs_target_pct'] ?? 0);
        $poVsFcPct = (float)($gm['po_vs_forecast_pct'] ?? 0);

        if ($fTot > 0 || $tTot > 0 || $iTot > 0 || $pTot > 0) {
            $gmRag = ($achPct >= 100) ? 'GREEN' : (($achPct >= 75) ? 'AMBER' : 'RED');

            $ins['high_insights'][] = [
                'title' => 'GM dashboard measures alignment',
                'rag' => $gmRag,
                'confidence' => 'HIGH',
                'text' =>
                    "Forecast SAR " . $this->fmt($fTot) .
                    ", Target SAR " . $this->fmt($tTot) .
                    ", Inquiries SAR " . $this->fmt($iTot) .
                    ", and POs SAR " . $this->fmt($pTot) .
                    ". Achievement vs Target is {$achPct}% with Pipeline vs Target at {$pipePct}% and PO vs Forecast at {$poVsFcPct}%.",
                'gm_action' =>
                    ($gmRag === 'GREEN')
                        ? 'Keep weekly closure rhythm on top-value quotations and protect execution quality.'
                        : 'Run weekly GM dashboard review to close the target gap: accelerate top quotes, remove approval blockers, and tighten follow-up ownership.',
            ];

            if ($gmRag !== 'GREEN') {
                $ins['what_needs_attention'][] = [
                    'title' => 'GM measure gap vs target',
                    'rag' => $gmRag,
                    'confidence' => 'HIGH',
                    'text' =>
                        "Current achievement vs target is {$achPct}%. Priority is to convert existing quotations faster and raise qualified pipeline to protect monthly delivery.",
                ];
            }
        }

        // ---------------------------------------------------------
        // (B) Target Coverage (pipeline requirement) — Business framing
        // ---------------------------------------------------------
        $benchmarkPct  = $this->getBenchmarkConversionPct($facts);     // default 10
        $monthlyTarget = $this->getMonthlyTargetForScope($facts);      // may be 0 if not provided

        if ($monthlyTarget > 0 && $benchmarkPct > 0) {
            $requiredPipeline = $monthlyTarget / ($benchmarkPct / 100.0);
            $coveragePct = ($requiredPipeline > 0) ? round(($q / $requiredPipeline) * 100, 1) : 0.0;

            // RAG for coverage
            $covRag = ($coveragePct >= 100) ? 'GREEN' : (($coveragePct >= 75) ? 'AMBER' : 'RED');

            $ins['high_insights'][] = [
                'title' => 'Target coverage (pipeline requirement)',
                'rag' => $covRag,
                'confidence' => 'HIGH',
                'text' =>
                    "To deliver the monthly target of SAR " . $this->fmt($monthlyTarget) .
                    " at a benchmark conversion of {$benchmarkPct}%, the required quotation pipeline is ≈ SAR " . $this->fmt($requiredPipeline) .
                    ". Current quoted pipeline is SAR " . $this->fmt($q) . " (" . $coveragePct . "% coverage).",
                'gm_action' =>
                    ($covRag === 'GREEN')
                        ? 'Pipeline volume is sufficient; focus execution on closing high-value opportunities and removing blockers.'
                        : 'Pipeline volume is below requirement; increase qualified RFQs and protect quote quality (pricing, approvals, delivery commitments).',
            ];

            if ($covRag !== 'GREEN') {
                $ins['what_needs_attention'][] = [
                    'title' => 'Pipeline coverage gap vs target requirement',
                    'rag' => $covRag,
                    'confidence' => 'HIGH',
                    'text' =>
                        "Current coverage is {$coveragePct}% of the pipeline required to reliably hit target. Priority: increase RFQ inflow and upgrade qualification (right projects, right customers, right margins).",
                ];
            }
        }

        // ---------------------------------------------------------
        // (C) Closing performance — Business language (no “simple math tone”)
        // ---------------------------------------------------------
        if ($q > 0) {
            $closeRag = ($c >= 15) ? 'GREEN' : (($c >= 10) ? 'AMBER' : 'RED');

            $ins['high_insights'][] = [
                'title' => 'Conversion health (Quoted → PO)',
                'rag' => $closeRag,
                'confidence' => 'HIGH',
                'text' =>
                    "PO realization is SAR " . $this->fmt($p) . " against quoted SAR " . $this->fmt($q) .
                    " (conversion {$c}%). The open commercial gap is SAR " . $this->fmt($gap) . " still sitting in quotations.",
                'gm_action' =>
                    ($closeRag === 'GREEN')
                        ? 'Maintain closure discipline: weekly follow-up, approvals, and delivery commitment alignment.'
                        : 'Run a weekly “Top Quotations” closure review: next action per quote, blocker, approval owner, and target close date.',
            ];

            if ($closeRag !== 'GREEN') {
                $ins['what_needs_attention'][] = [
                    'title' => 'Closure discipline needs tightening',
                    'rag' => $closeRag,
                    'confidence' => 'HIGH',
                    'text' =>
                        "The constraint is not quoting activity — it is conversion execution (approvals, pricing alignment, follow-up cadence, and decision-maker access).",
                ];
            }
        }

        // ---------------------------------------------------------
        // (D) GM view only — region comparison (neutral wording)
        // ---------------------------------------------------------
        if ($isGmView) {
            $bestRegion = $facts['regions']['best_region'] ?? null;
            $worstRegion = $facts['regions']['worst_region'] ?? null;

            if ($bestRegion && isset($facts['regions']['rows'][$bestRegion])) {
                $r = $facts['regions']['rows'][$bestRegion];
                $ins['high_insights'][] = [
                    'title' => "Regional comparison: highest conversion this period ({$bestRegion})",
                    'rag' => 'GREEN',
                    'confidence' => 'MEDIUM',
                    'text' =>
                        "{$bestRegion} is currently converting more effectively: Quoted SAR " . $this->fmt($r['quoted'] ?? 0) .
                        " → PO SAR " . $this->fmt($r['po'] ?? 0) . " (" . (float)($r['conversion_pct'] ?? 0) . "%).",
                    'gm_action' =>
                        'Translate the operational habits: quote qualification + closure governance + escalation path for approvals.',
                ];
            }

            if ($worstRegion && isset($facts['regions']['rows'][$worstRegion])) {
                $r = $facts['regions']['rows'][$worstRegion];
                $ins['what_needs_attention'][] = [
                    'title' => "Regional risk: weakest conversion this period ({$worstRegion})",
                    'rag' => 'AMBER',
                    'confidence' => 'MEDIUM',
                    'text' =>
                        "{$worstRegion} has lower PO realization vs quoted volume. Focus should be: identify blockers, pricing gaps, competitor pressure, and approval delays on top-value accounts.",
                ];
            }
        }

        // ---------------------------------------------------------
        // (E) Product mix — FIX Western requirement (GM + Western):
        // - Keep PI/SP under DUCTWORK in the matrix
        // - But ALWAYS include PI/SP Execution visibility in insights:
        //   * Western report (ABDO/AHMED scope): show PI/SP
        //   * GM report (ALL): show PI/SP only for Western (ABDO+AHMED) using facts.product_mix.western_focus
        // ---------------------------------------------------------
        $mix = $facts['product_mix'] ?? [];
        $rollup = $this->rollupProductFamilies($mix); // families + subsegments

        if (!empty($rollup['families'])) {
            $topFamily = array_key_first($rollup['families']);
            $topFamRow = $rollup['families'][$topFamily];

            $ins['high_insights'][] = [
                'title' => 'Product engine (where POs are coming from)',
                'rag' => 'GREEN',
                'confidence' => 'MEDIUM',
                'text' =>
                    "{$topFamily} is the primary PO driver with PO SAR " . $this->fmt($topFamRow['po']) .
                    " from quoted SAR " . $this->fmt($topFamRow['quoted']) .
                    " (conversion " . $topFamRow['conversion_pct'] . "%).",
                'gm_action' => 'Protect this engine (quality, delivery commitments) while fixing weak sub-segments under the same family.',
            ];

            // ✅ NEW: Western PI/SP visibility (works in Western view + GM view)
            // facts.product_mix.western_focus is:
            // - Western report: computed from scoped salesmen (ABDO+AHMED)
            // - GM report: computed only for ABDO+AHMED (Western subset)
            $wf = $facts['product_mix']['western_focus'] ?? null;
            if (is_array($wf)) {
                $piQ = (float)($wf['PI']['quoted'] ?? 0);
                $piP = (float)($wf['PI']['po'] ?? 0);
                $piE = (float)($wf['PI']['execution_pct'] ?? 0);

                $spQ = (float)($wf['SP']['quoted'] ?? 0);
                $spP = (float)($wf['SP']['po'] ?? 0);
                $spE = (float)($wf['SP']['execution_pct'] ?? 0);

                // RAG based on Execution only (simple + clear)
                $ragForExec = function (float $execPct): string {
                    if ($execPct >= 10) return 'GREEN';
                    if ($execPct >= 2)  return 'AMBER';
                    return 'RED';
                };

                $piRag = $ragForExec($piE);
                $spRag = $ragForExec($spE);

                // ✅ keep "Execution" word exactly (as you requested)
                $scopeLabel = $isGmView ? 'Western (ABDO+AHMED)' : 'Western';
                $ins['high_insights'][] = [
                    'title' => "Ductwork split visibility — {$scopeLabel}",
                    'rag' => 'AMBER',
                    'confidence' => 'HIGH',
                    'text' =>
                        "Within Ductwork ({$scopeLabel}), Pre-Insulated: Quoted SAR " . $this->fmt($piQ) .
                        " | PO SAR " . $this->fmt($piP) .
                        " | Execution {$piE}%. Spiral: Quoted SAR " . $this->fmt($spQ) .
                        " | PO SAR " . $this->fmt($spP) .
                        " | Execution {$spE}%.",
                    'gm_action' =>
                        'Use this split to identify which sub-segment needs pricing/approval escalation and which sub-segment is converting normally.',
                ];

                // Add attention items if any is weak
                if ($piQ > 0 && $piRag !== 'GREEN') {
                    $ins['what_needs_attention'][] = [
                        'title' => "Pre-Insulated under Ductwork needs focus ({$scopeLabel})",
                        'rag' => $piRag,
                        'confidence' => 'HIGH',
                        'text' =>
                            "Pre-Insulated is not converting efficiently: Quoted SAR " . $this->fmt($piQ) .
                            " vs PO SAR " . $this->fmt($piP) . " (Execution {$piE}%). Priority: review pricing, approvals, spec compliance, and competitor pressure on top quotes.",
                    ];
                }

                if ($spQ > 0 && $spRag !== 'GREEN') {
                    $ins['what_needs_attention'][] = [
                        'title' => "Spiral under Ductwork needs focus ({$scopeLabel})",
                        'rag' => $spRag,
                        'confidence' => 'HIGH',
                        'text' =>
                            "Spiral is not converting efficiently: Quoted SAR " . $this->fmt($spQ) .
                            " vs PO SAR " . $this->fmt($spP) . " (Execution {$spE}%). Priority: review pricing, approvals, spec compliance, and competitor pressure on top quotes.",
                    ];
                }
            }

            // Identify weak subsegments (high quoted but low/no PO)
            $weakSubs = $this->findWeakSubsegments($rollup, 2); // top 2 weak
            foreach ($weakSubs as $w) {
                $ins['what_needs_attention'][] = [
                    'title' => "Sub-segment not converting: {$w['name']} (under {$w['family']})",
                    'rag' => ($w['conversion_pct'] <= 1 ? 'RED' : 'AMBER'),
                    'confidence' => 'HIGH',
                    'text' =>
                        "{$w['name']} has quoted SAR " . $this->fmt($w['quoted']) .
                        " but PO SAR " . $this->fmt($w['po']) .
                        " (conversion {$w['conversion_pct']}%). Action: review pricing, approvals, spec compliance, and competitor mapping on top quotes.",
                ];
            }
        }

        // ---------------------------------------------------------
        // (F) Data coverage note (sales-friendly definition)
        // ---------------------------------------------------------
        $ins['low_insights'][] = [
            'title' => 'Trend confidence depends on month coverage',
            'rag' => 'AMBER',
            'confidence' => $trendConf,
            'text' =>
                ($mCount <= 1)
                    ? "Only {$mCount} active month has meaningful PO activity. Treat this report as a snapshot, not a momentum statement."
                    : "With {$mCount} active months, trends are improving but still stabilize best after 3+ months.",
            'gm_interpretation' =>
                'Use for immediate actions; avoid strong trend judgments until month coverage improves.',
        ];

        // Keep limits
        $ins['high_insights'] = array_slice($ins['high_insights'], 0, 6);
        $ins['low_insights'] = array_slice($ins['low_insights'], 0, 6);
        $ins['what_needs_attention'] = array_slice($ins['what_needs_attention'], 0, 5);

        // Ensure minimum attention items
        if (count($ins['what_needs_attention']) < 2) {
            $ins['what_needs_attention'][] = [
                'title' => 'Weekly closure governance (minimum standard)',
                'rag' => 'AMBER',
                'confidence' => 'MEDIUM',
                'text' => 'Track top quotations by value with aging, owner, blocker, and next action until PO or Lost.',
            ];
        }
    }
    private function getBenchmarkConversionPct(array $facts): float
    {
        // Try multiple possible keys (future-proof)
        $v =
            $facts['targets']['benchmark_conversion_pct'] ??
            $facts['conversion_model']['benchmark_conversion_pct'] ??
            $facts['conversion_model']['benchmark_pct'] ??
            10.0;

        $v = (float)$v;
        if ($v <= 0) $v = 10.0;
        return round($v, 1);
    }
    private function getMonthlyTargetForScope(array $facts): float
    {
        // Expected: $facts['targets']['monthly_target'] already set for current scope (region or salesman)
        $area = (string)($facts['scope']['area'] ?? '');

        $v =
            $facts['targets']['monthly_target'] ??
            ($facts['targets']['monthly_targets'][$area] ?? null) ??
            0;

        return (float)$v;
    }

    private function oneLineSummary(array $facts): string
{
    $area = (string)$facts['scope']['area'];
    $q = (float)$facts['snapshot']['quoted_total'];
    $p = (float)$facts['snapshot']['po_total'];
    $c = (float)$facts['snapshot']['conversion_pct'];

    $isGmView = (bool)($facts['meta_flags']['is_gm_view'] ?? false);

    if ($isGmView) {
        return "{$area} has a strong pipeline (SAR " . $this->fmt($q) . ") with Successfully Achieved at {$c}% (PO SAR " .
            $this->fmt($p) . "). Focus: increase closure on top-value RFQs and remove blockers.";
    }

    return "{$area} portfolio snapshot: Quoted SAR " . $this->fmt($q) . " with Successfully Achieved at {$c}% (PO SAR " .
        $this->fmt($p) . "). Next step: concentrate follow-up on top-value RFQs and close pending approvals.";
}

    private function overallRag(array $facts): string
{
    $c = (float)$facts['snapshot']['conversion_pct'];
    if ($c >= 25) return 'GREEN';
    if ($c >= 10) return 'AMBER';
    return 'RED';
}

    private function overallConfidence(array $facts): string
{
    $m = (int)$facts['month_coverage']['active_month_count'];
    if ($m <= 1) return 'LOW';
    if ($m <= 2) return 'MEDIUM';
    return 'HIGH';
}

    private function productShortLabel(string $name): string
{
    $u = strtoupper(trim($name));
    if (str_contains($u, 'PRE') && str_contains($u, 'INSUL')) return 'PI';
    if (str_contains($u, 'SPIRAL')) return 'SP';
    return $name;
}

    /**
     * ✅ FINAL FIX:
     * - For Western PI/SP: use western_focus (best truth)
     * - For non-Western: use strict findFamilyTotals
     * - Prevents inflated 29M/28M bug.
     */
    private function appendRegionFocusFamilies(array &$ins, array $facts): void
{
    $area = (string)($facts['scope']['area'] ?? 'All');
    $areaU = strtoupper(trim($area));
    $isGmView = (bool)($facts['meta_flags']['is_gm_view'] ?? false);

    // If Western focus exists, ALWAYS use it (Western report or GM report)
    $wf = $facts['product_mix']['western_focus'] ?? null;

    // Western report: show PI/SP from western_focus (scoped)
    if ($areaU === 'WESTERN' && is_array($wf)) {
        $pi = $wf['PI'] ?? null;
        $sp = $wf['SP'] ?? null;

        if (is_array($pi)) {
            $ins['overall_analysis']['product_key_points'][] =
                "PI focus: Quoted SAR " . $this->fmt($pi['quoted'] ?? 0) .
                " | PO SAR " . $this->fmt($pi['po'] ?? 0) .
                " | Execution " . (float)($pi['execution_pct'] ?? 0) . "%";
        }
        if (is_array($sp)) {
            $ins['overall_analysis']['product_key_points'][] =
                "SP focus: Quoted SAR " . $this->fmt($sp['quoted'] ?? 0) .
                " | PO SAR " . $this->fmt($sp['po'] ?? 0) .
                " | Execution " . (float)($sp['execution_pct'] ?? 0) . "%";
        }
        return;
    }

    // GM report: add Western subset PI/SP (ABDO+AHMED) if available
    if ($isGmView && is_array($wf)) {
        $pi = $wf['PI'] ?? null;
        $sp = $wf['SP'] ?? null;

        if (is_array($pi)) {
            $ins['overall_analysis']['product_key_points'][] =
                "PI (Western: ABDO+AHMED) under Ductwork: Quoted SAR " . $this->fmt($pi['quoted'] ?? 0) .
                " | PO SAR " . $this->fmt($pi['po'] ?? 0) .
                " | Execution " . (float)($pi['execution_pct'] ?? 0) . "%";
        }
        if (is_array($sp)) {
            $ins['overall_analysis']['product_key_points'][] =
                "SP (Western: ABDO+AHMED) under Ductwork: Quoted SAR " . $this->fmt($sp['quoted'] ?? 0) .
                " | PO SAR " . $this->fmt($sp['po'] ?? 0) .
                " | Execution " . (float)($sp['execution_pct'] ?? 0) . "%";
        }
        return;
    }

    // Other regions: keep your original region focus families logic (strict matching)
    $areaKey = match (strtoupper(trim($area))) {
        'EASTERN' => 'Eastern',
        'CENTRAL' => 'Central',
        'WESTERN' => 'Western',
        default => (string)$area,
    };

    $focus = $this->regionFocusFamilies[$areaKey] ?? [];
    if ($focus) {
        foreach ($focus as $family) {
            $row = $this->findFamilyTotals($facts['product_mix'] ?? [], $family);
            if (!$row) continue;

            $q = (float)($row['quoted'] ?? 0);
            $p = (float)($row['po'] ?? 0);
            $c = ($q > 0) ? round(($p / $q) * 100, 1) : 0.0;

            $label = $this->productShortLabel($family);
            $ins['overall_analysis']['product_key_points'][] =
                "{$label} focus: Quoted SAR " . $this->fmt($q) . " | PO SAR " . $this->fmt($p) . " | Execution {$c}%";
        }
    }
}
    /**
     * Roll product categories into business families + subsegments.
     * Pre-Insulated / Spiral stays UNDER DUCTWORK (family) but still visible for analysis.
     */
    private function rollupProductFamilies(array $productMix): array
    {
        $inqTotals = (array)($productMix['inq_totals'] ?? []);
        $poTotals  = (array)($productMix['po_totals'] ?? []);

        $map = [
            'DUCTWORK' => [
                'DUCTWORK',
                'PRE-INSULATED',
                'PRE INSULATED',
                'SPIRAL',
                'ROUND DUCT',
                'FLAT OVAL',
            ],
            'DAMPERS' => ['DAMPER'],
            'LOUVERS' => ['LOUVER'],
            'SOUND ATTENUATORS' => ['ATTENUATOR', 'SOUND'],
            'ACCESSORIES' => ['ACCESS', 'PLENUM', 'FLEXIBLE', 'VAV', 'CAV'],
        ];

        $families = [];
        $subsegments = []; // [family => [subName => row]]

        foreach ($inqTotals as $cat => $val) {
            $catU = strtoupper(trim((string)$cat));
            $fam = $this->matchFamily($catU, $map) ?? 'OTHER';
            $families[$fam]['quoted'] = ($families[$fam]['quoted'] ?? 0) + (float)$val;

            // keep subsegment row
            $subsegments[$fam][$catU]['quoted'] = ($subsegments[$fam][$catU]['quoted'] ?? 0) + (float)$val;
        }

        foreach ($poTotals as $cat => $val) {
            $catU = strtoupper(trim((string)$cat));
            $fam = $this->matchFamily($catU, $map) ?? 'OTHER';
            $families[$fam]['po'] = ($families[$fam]['po'] ?? 0) + (float)$val;

            $subsegments[$fam][$catU]['po'] = ($subsegments[$fam][$catU]['po'] ?? 0) + (float)$val;
        }

        // finalize conversion for families
        foreach ($families as $fam => $row) {
            $fq = (float)($row['quoted'] ?? 0);
            $fp = (float)($row['po'] ?? 0);
            $families[$fam]['quoted'] = $fq;
            $families[$fam]['po'] = $fp;
            $families[$fam]['conversion_pct'] = ($fq > 0) ? round(($fp / $fq) * 100, 1) : 0.0;
        }

        // finalize conversion for subsegments
        foreach ($subsegments as $fam => $subs) {
            foreach ($subs as $name => $row) {
                $sq = (float)($row['quoted'] ?? 0);
                $sp = (float)($row['po'] ?? 0);
                $subsegments[$fam][$name] = [
                    'quoted' => $sq,
                    'po' => $sp,
                    'conversion_pct' => ($sq > 0) ? round(($sp / $sq) * 100, 1) : 0.0,
                ];
            }
        }

        // sort families by PO desc
        uasort($families, fn($a,$b) => ((float)$b['po']) <=> ((float)$a['po']));

        return ['families' => $families, 'subsegments' => $subsegments];
    }

    private function matchFamily(string $catUpper, array $map): ?string
    {
        foreach ($map as $family => $tokens) {
            foreach ($tokens as $t) {
                $t = strtoupper($t);
                if ($t !== '' && str_contains($catUpper, $t)) return $family;
            }
        }
        return null;
    }

    /**
     * ✅ FINAL FIX:
     * Strict matcher (prevents reverse contains that caused PI/SP to swallow DUCTWORK).
     * - exact match
     * - or "all required tokens exist in name"
     */
    private function findFamilyTotals(array $productMix, string $familyName): ?array
{
    $inqTotals = (array)($productMix['inq_totals'] ?? []);
    $poTotals  = (array)($productMix['po_totals'] ?? []);

    $wantedU = strtoupper(trim($familyName));
    $wantedTokens = array_values(array_filter(preg_split('/[\s\-]+/', $wantedU) ?: [], fn($t) => $t !== ''));

    $match = function(string $k) use ($wantedU, $wantedTokens): bool {
        $kU = strtoupper(trim($k));
        if ($kU === $wantedU) return true;

        // Require all tokens (AND match). Example:
        // "PRE-INSULATED DUCTWORK" matches "PRE INSULATED DUCTWORK"
        foreach ($wantedTokens as $t) {
            if (!str_contains($kU, $t)) return false;
        }
        return true;
    };

    $inq = 0.0; $po = 0.0; $found = false;

    foreach ($inqTotals as $k => $v) {
        if ($match((string)$k)) { $inq += (float)$v; $found = true; }
    }
    foreach ($poTotals as $k => $v) {
        if ($match((string)$k)) { $po += (float)$v; $found = true; }
    }

    return $found ? ['quoted' => $inq, 'po' => $po] : null;
}
    /**
     * Find weak subsegments: high quoted, low PO conversion.
     * Produces “which product is not working” narrative.
     */
    private function findWeakSubsegments(array $rollup, int $limit = 2): array
    {
        $out = [];

        $subsByFam = (array)($rollup['subsegments'] ?? []);
        foreach ($subsByFam as $fam => $subs) {
            foreach ((array)$subs as $name => $row) {
                $q = (float)($row['quoted'] ?? 0);
                $p = (float)($row['po'] ?? 0);
                $c = (float)($row['conversion_pct'] ?? 0);

                // Only flag meaningful subsegments
                if ($q < 500000) continue;   // avoid noise
                if ($c > 5) continue;        // not weak

                $out[] = [
                    'family' => (string)$fam,
                    'name' => (string)$name,
                    'quoted' => $q,
                    'po' => $p,
                    'conversion_pct' => $c,
                ];
            }
        }

        // sort by quoted desc (biggest value at risk)
        usort($out, fn($a,$b) => ((float)$b['quoted']) <=> ((float)$a['quoted']));

        return array_slice($out, 0, $limit);
    }
    /* ============================================================
     | Helpers
     * ============================================================ */

    private function salesmenForArea(string $area): array
{
    if ($area === 'All') return [];
    $list = $this->regionSalesmen[$area] ?? [];
    $out = [];
    foreach ($list as $s) $out[strtoupper($s)] = true;
    return array_keys($out);
}

    private function filterByAllowedKeys(array $map, array $allowedKeys): array
{
    if (empty($allowedKeys)) return $map;
    $allowed = array_fill_keys($allowedKeys, true);

    $out = [];
    foreach ($map as $k => $v) {
        $kk = strtoupper((string)$k);
        if (isset($allowed[$kk])) $out[$kk] = $v;
    }
    return $out;
}

    private function pivotTotal($row): float
{
    if (!is_array($row) || empty($row)) return 0.0;
    if (array_key_exists('total', $row)) return (float)($row['total'] ?: 0);
    $vals = array_values($row);
    $v = end($vals);
    return (float)($v ?: 0);
}

    private function sumPivotTotals(array $pivot): float
{
    $sum = 0.0;
    foreach ($pivot as $row) $sum += (float)$this->pivotTotal($row);
    return $sum;
}

    private function sumKpiMeasureTotals(array $kpiMatrix, string $measure): float
{
    $sum = 0.0;
    foreach ($kpiMatrix as $salesman => $rows) {
        if (!is_array($rows)) continue;
        $sum += (float)$this->pivotTotal($rows[$measure] ?? []);
    }
    return $sum;
}

    private function buildRegionRows(array $inqByRegion, array $poByRegion, string $area): array
{
    if ($area !== 'All') {
        $inqByRegion = array_intersect_key($inqByRegion, [$area => true]);
        $poByRegion  = array_intersect_key($poByRegion,  [$area => true]);
    }

    $names = array_unique(array_merge(array_keys($inqByRegion), array_keys($poByRegion)));
    $rows = [];

    foreach ($names as $r) {
        $q = (float)$this->pivotTotal($inqByRegion[$r] ?? []);
        $p = (float)$this->pivotTotal($poByRegion[$r] ?? []);
        $c = ($q > 0) ? round(($p / $q) * 100, 1) : 0.0;

        $rows[$r] = ['quoted' => $q, 'po' => $p, 'conversion_pct' => $c];
    }
    return $rows;
}

    private function buildSalesmanRows(array $inqPivot, array $poPivot): array
{
    $names = array_unique(array_merge(array_keys($inqPivot), array_keys($poPivot)));
    $rows = [];

    foreach ($names as $s) {
        $q = (float)$this->pivotTotal($inqPivot[$s] ?? []);
        $p = (float)$this->pivotTotal($poPivot[$s] ?? []);
        $c = ($q > 0) ? round(($p / $q) * 100, 1) : 0.0;

        $rows[$s] = ['quoted' => $q, 'po' => $p, 'conversion_pct' => $c];
    }
    return $rows;
}

    private function bestWorstByConversion(array $rows): array
{
    $best = null; $bestC = -1;
    $worst = null; $worstC = 999;

    foreach ($rows as $k => $r) {
        $q = (float)($r['quoted'] ?? 0);
        if ($q <= 0) continue;
        $c = (float)($r['conversion_pct'] ?? 0);
        if ($c > $bestC) { $bestC = $c; $best = $k; }
        if ($c < $worstC) { $worstC = $c; $worst = $k; }
    }
    return [$best, $worst];
}

    private function bestCloser(array $salesmen): ?string
{
    $best = null; $bestC = -1;
    foreach ($salesmen as $s => $r) {
        $q = (float)($r['quoted'] ?? 0);
        if ($q < 1_000_000) continue;
        $c = (float)($r['conversion_pct'] ?? 0);
        if ($c > $bestC) { $bestC = $c; $best = $s; }
    }
    return $best;
}

    private function weakClosers(array $salesmen): array
{
    $out = [];
    foreach ($salesmen as $s => $r) {
        $q = (float)($r['quoted'] ?? 0);
        $c = (float)($r['conversion_pct'] ?? 0);
        if ($q >= 1_000_000 && $c < 1.0) $out[] = $s;
    }
    return $out;
}

    private function sumProductMatrix(array $matrix): array
{
    $totals = [];
    foreach ($matrix as $salesman => $products) {
        if (!is_array($products)) continue;
        foreach ($products as $prod => $row) {
            $totals[$prod] = ($totals[$prod] ?? 0) + (float)$this->pivotTotal($row);
        }
    }
    arsort($totals);
    return $totals;
}

    private function toShares(array $totals): array
{
    $sum = array_sum(array_map('floatval', $totals));
    if ($sum <= 0) return [];
    $shares = [];
    foreach ($totals as $k => $v) $shares[$k] = ((float)$v / $sum) * 100.0;
    arsort($shares);
    return $shares;
}

    private function topKey(array $arr): ?string
{
    foreach ($arr as $k => $v) return (string)$k;
    return null;
}

    private function sumMonthlyFromSalesmanPivot(array $pivot): array
{
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $sum = array_fill_keys($months, 0.0);

    foreach ($pivot as $salesman => $row) {
        if (!is_array($row)) continue;
        $vals = array_values($row);
        for ($i=0; $i<12; $i++) {
            $sum[$months[$i]] += (float)($vals[$i] ?? 0);
        }
    }
    return $sum;
}

    private function topNAssocByMetric(array $rows, int $n, string $key): array
{
    uasort($rows, function($a,$b) use ($key) {
        $av = (float)($a[$key] ?? 0);
        $bv = (float)($b[$key] ?? 0);
        return $bv <=> $av;
    });
    return array_slice($rows, 0, $n, true);
}

    private function fmt($num): string
{
    return number_format((float)$num, 0, '.', ',');
}
}
