<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RegionScope;
use App\Models\Project;

class ProjectApiController extends Controller
{
    /* =========================================================
     * SALESPERSON REGION ALIAS HELPERS (same as SalesOrderManagerController)
     * ========================================================= */

    /** Region → salesperson aliases */
    protected function salesAliasesForRegion(?string $regionNorm): array
    {
        return match ($regionNorm) {
            'eastern' => ['SOHAIB', 'SOAHIB'],
            'central' => ['TARIQ', 'TAREQ', 'JAMAL'],
            'western' => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],
            default => [],
        };
    }

    /** Map canonical salesperson → home region (lowercase) */
    protected function homeRegionBySalesperson(): array
    {
        return [
            // Eastern
            'SOHAIB' => 'eastern',
            'SOAHIB' => 'eastern',

            // Central
            'TARIQ' => 'central',
            'TAREQ' => 'central',
            'JAMAL' => 'central',

            // Western
            'ABDO' => 'western',
            'ABDUL' => 'western',
            'ABDOU' => 'western',
            'AHMED' => 'western',
        ];
    }

    /** Canonicalize salesperson name (UPPERCASE, remove spaces) */
    protected function canonSalesKey(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$name));
    }

    /**
     * KPI payload (QUOTE PHASE ONLY).
     * Frontend computes:
     *   - Conversion %  = InHand / TotalQuoted * 100
     *   - Target(YTD)   = monthly_quote_target * months_elapsed
     *   - Target %      = TotalQuoted / Target(YTD) * 100
     *
     * GET /api/kpis?family=ductwork&area=Eastern&year=2025&month=1..12
     */
    public function kpis(Request $req)
    {
        /* ===================== 0) Auth & RBAC ===================== */
        $user = $req->user();
        $isAdmin = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm', 'admin']);
        $effectiveArea = null;

        /* ===================== 1) Base query (Projects only) ===================== */
        $q = Project::query()->whereNull('deleted_at'); // exclude soft-deleted

        /* ===================== Region ===================== */
        /* ===================== Region ===================== */
        if ($req->filled('area') && strcasecmp($req->input('area'), 'all') !== 0) {
            // User explicitly chose an area → apply as a filter
            $area = $req->input('area');
            $q->where('area', $area);
            $effectiveArea = $area;
        } else {
            // No area filter → don't restrict by area, just remember user's home region for targets
            $effectiveArea = ($user && !empty($user->region)) ? $user->region : null;
        }

        /* ===================== Salesman (dynamic, alias-aware) ===================== */
        $buildAliasSet = function (?string $name) {
            if ($name === null || $name === '') return [];
            $key = strtoupper(preg_replace('/\s+/', '', $name)); // remove spaces, uppercase
            foreach (['eastern', 'central', 'western'] as $region) {
                $set = $this->salesAliasesForRegion($region);
                if (in_array($key, $set, true)) {
                    return $set; // full alias set for that region (e.g., SOHAIB/SOAHIB)
                }
            }
            return [$key];
        };

        if ($req->filled('salesman')) {
            // Explicit salesman filter (admins & non-admins)
            $aliases = $buildAliasSet($req->input('salesman'));
            if (!empty($aliases)) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $a) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$a]);
                    }
                });
            }
        } else if (!$isAdmin) {
            // No salesman param: for non-admins, infer from logged-in user
            $first = strtoupper(trim(explode(' ', (string)($user->name ?? ''))[0] ?? ''));
            $home = $this->homeRegionBySalesperson();
            $userHomeRegion = $home[$first] ?? null;

            $regionForAliases = $userHomeRegion ?: (isset($user->region) ? strtolower($user->region) : null);
            $aliases = $regionForAliases ? $this->salesAliasesForRegion($regionForAliases) : [];

            if (!empty($aliases)) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $a) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$a]);
                    }
                });
            } elseif (!empty($first)) {
                // last-resort: exact canonicalized first token
                $q->whereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$first]);
            }
        }


        // ===== Stale bidding count (Bidding + no status + >3 months old) =====
        // Uses the same region + salesman filters as the KPI, but ignores year/date filters
        $staleBiddingCount = (clone $q)
            ->staleBidding()
            ->count();


        /* ===================== Dates & Filters ===================== */
        // Use normalized quotation_date only (loader outputs YYYY-MM-DD)


        $dateExprSql = "STR_TO_DATE(quotation_date,'%Y-%m-%d')";
        $defaultYear = 2025;

        // Family % shares (used for “family target” mode)
        $familyPctMap = [
            'ductwork' => 0.60,
            'accessories' => 0.15,
            'sound' => 0.10,               // in case chip sends 'sound'
            'sound_attenuators' => 0.10,
            'attenuators' => 0.10,
            'dampers' => 0.15,
        ];
        $familyParam = strtolower(trim((string)$req->query('family', 'all')));

        // detect if caller explicitly sent any date constraint
        $hasExplicitRange = $req->filled('date_from') || $req->filled('date_to')
            || $req->filled('month') || $req->filled('year');

        // if no explicit constraints, default to 2025; else honor caller's year (or null)
        $y = $req->integer('year') ?: ($hasExplicitRange ? null : $defaultYear);
        $m = $req->integer('month') ?: null;
        $df = $req->input('date_from') ?: null;
        $dt = $req->input('date_to') ?: null;

        if ($y) $q->whereRaw("YEAR($dateExprSql) = ?", [$y]);
        if ($m) $q->whereRaw("MONTH($dateExprSql) = ?", [$m]);
        if ($df) $q->whereRaw("$dateExprSql >= ?", [$df]);
        if ($dt) $q->whereRaw("$dateExprSql <= ?", [$dt]);

        // ⭐ NEW — keep a base clone with all filters EXCEPT family (for target share)
        $qNoFamily = clone $q;

        // Family filter – ignore "All"
        if ($req->filled('family')) {
            $fam = strtolower(trim($req->input('family')));
            if ($fam !== 'all') {
                $q->whereRaw('LOWER(atai_products) LIKE ?', ['%' . $fam . '%']);
            }
        }

        /* ===================== 2) Status buckets ===================== */
        $pt = "LOWER(TRIM(projects.project_type))";
        $st = "LOWER(TRIM(projects.status))";

        $inHandList = "'in-hand','in hand','inhand','accepted','won','order','order in hand','ih'";
        $biddingList = "'bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry'";
        $lostList = "'lost','rejected','cancelled','canceled','closed lost','declined','not awarded'";

        $computedStatus = "
        CASE
          WHEN $st IN ($lostList) OR $pt IN ($lostList) THEN 'Lost'
          WHEN $st IN ($inHandList) OR $pt IN ($inHandList) THEN 'In-Hand'
          WHEN $st IN ($biddingList) OR $pt IN ($biddingList) THEN 'Bidding'
          ELSE 'Other'
        END
        ";


        /* ===================== 2a) Optional status_norm filter (Bidding / In-Hand / Lost) ===================== */
        $statusNorm = strtolower(trim((string)$req->query('status_norm', '')));

        if ($statusNorm !== '') {
            if ($statusNorm === 'po-received') {
                // For now: ignore or handle separately if you later want PO-based KPI
                // (Quotation Log usually cares only about Bidding/In-Hand/Lost)
            } else {
                // Filter the main query by the computed bucket
                $q->whereRaw("($computedStatus) = ?", [$req->query('status_norm')]);
            }
        }

        /* ===================== 3) Value expression ===================== */
        // quotation_value only (matches your cleaned CSV/dashboard)
        $valExprSql = "COALESCE(quotation_value, 0)";

        /* ===================== 4) Quote-phase totals ===================== */
        // Base total for scope *without* family filter (for target shares)
        $baseTotalQuoted = (float)((clone $qNoFamily)
            ->selectRaw("SUM($valExprSql) AS t")
            ->value('t') ?? 0);

        // Current-scope totals
        $totalQuotedValue = (float)((clone $q)->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $inhandQuotedValue = (float)((clone $q)->whereRaw("( $computedStatus )='In-Hand'")
            ->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $biddingQuotedValue = (float)((clone $q)->whereRaw("( $computedStatus )='Bidding'")
            ->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $lostQuotedValue = (float)((clone $q)->whereRaw("( $computedStatus )='Lost'")
            ->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);

        $totalCount = (clone $q)->count();

        /* ===================== 5) Area & status breakdowns ===================== */
        $byStatus = (clone $q)
            ->selectRaw("($computedStatus) AS status_norm, SUM($valExprSql) AS sum_value")
            ->groupBy('status_norm')
            ->get();

        $rowsArea = (clone $q)->selectRaw("
            COALESCE(area,'—') AS area,
            SUM(CASE WHEN (($computedStatus)='In-Hand') THEN 1 ELSE 0 END) AS inhand_cnt,
            SUM(CASE WHEN (($computedStatus)='Bidding') THEN 1 ELSE 0 END) AS bidding_cnt,
            SUM(CASE WHEN (($computedStatus)='Lost')    THEN 1 ELSE 0 END) AS lost_cnt,
            SUM(CASE WHEN (($computedStatus)='In-Hand') THEN $valExprSql ELSE 0 END) AS inhand_val,
            SUM(CASE WHEN (($computedStatus)='Bidding') THEN $valExprSql ELSE 0 END) AS bidding_val,
            SUM(CASE WHEN (($computedStatus)='Lost')    THEN $valExprSql ELSE 0 END) AS lost_val
        ")->groupBy('area')->orderBy('area')->get();

        $preferredOrder = ['Eastern', 'Central', 'Western'];
        $mapArea = collect($rowsArea)->keyBy('area');
        $catsPreferred = collect($preferredOrder);
        $the_extra_areas = $mapArea->keys()->diff($catsPreferred);
        $categoriesArea = $catsPreferred->merge($the_extra_areas)->values()->all();

        $seriesInhand = $seriesBidding = $seriesLost = [];
        foreach ($categoriesArea as $a) {
            $r = $mapArea->get($a);
            $seriesInhand[] = ['y' => (int)($r->inhand_cnt ?? 0), 'sar' => (float)($r->inhand_val ?? 0)];
            $seriesBidding[] = ['y' => (int)($r->bidding_cnt ?? 0), 'sar' => (float)($r->bidding_val ?? 0)];
            $seriesLost[] = ['y' => (int)($r->lost_cnt ?? 0), 'sar' => (float)($r->lost_val ?? 0)];
        }

        /* ===================== 6) Monthly window (for charts) ===================== */
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfMonth();
            $end = \Carbon\Carbon::parse($dt)->endOfMonth();
        } elseif ($y) {
            $start = \Carbon\Carbon::create($y, 1, 1)->startOfMonth();
            $end = \Carbon\Carbon::create($y, 12, 1)->endOfMonth();
        } else {
            $start = \Carbon\Carbon::create($defaultYear, 1, 1)->startOfMonth();
            $end = \Carbon\Carbon::create($defaultYear, 12, 1)->endOfMonth();
        }

        $months = [];
        for ($c = $start->copy(); $c <= $end; $c->addMonth()) $months[] = $c->format('Y-m');

        // Monthly counts per area
        $monthlyRows = (clone $q)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, COALESCE(area,'—') AS area, COUNT(*) AS cnt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym', 'area')->orderBy('ym')->get();

        $baseAreas = ['Eastern', 'Central', 'Western'];
        $extraFromData = $monthlyRows->pluck('area')->unique()->diff($baseAreas)->values()->all();
        $areasAll = array_values(array_unique(array_merge($baseAreas, $extraFromData)));

        $idxMonthly = [];
        foreach ($monthlyRows as $r) $idxMonthly[$r->ym][$r->area] = (int)$r->cnt;

        $seriesMonthly = [];
        foreach ($areasAll as $a) {
            $data = [];
            foreach ($months as $ym) $data[] = $idxMonthly[$ym][$a] ?? 0;
            $seriesMonthly[] = ['name' => $a, 'data' => $data];
        }

        // Monthly value by status + target % line
        $valRows = (clone $q)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, ($computedStatus) AS status_norm, SUM($valExprSql) AS amt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym', 'status_norm')->orderBy('ym')->get();

        $idxVal = [];
        foreach ($valRows as $r) $idxVal[$r->ym][$r->status_norm] = (float)$r->amt;

        $colInHand = $colBidding = $colLost = [];
        $linePct = [];

        /* ===== Region annual targets (fallbacks) ===== */
        $annualTargets = [
            'eastern' => 35_000_000,
            'central' => 37_000_000,
            'western' => 30_000_000,
        ];

        // Determine region for target derivation (for per-month target)
        $regionForTarget = null;
        if ($req->filled('monthly_quote_target')) {
            $regionForTarget = null; // manual override wins
        } else {
            if (!$isAdmin && !empty($user?->region)) {
                $regionForTarget = strtolower(trim($user->region));
            } else {
                $areaParam = strtolower(trim((string)$req->query('area', '')));
                if (in_array($areaParam, ['eastern', 'central', 'western'], true)) {
                    $regionForTarget = $areaParam;
                } elseif (!empty($effectiveArea) && in_array(strtolower($effectiveArea), ['eastern', 'central', 'western'], true)) {
                    $regionForTarget = strtolower($effectiveArea);
                }
            }
        }

        $derivedMonthlyTarget = ($regionForTarget && isset($annualTargets[$regionForTarget]))
            ? ($annualTargets[$regionForTarget])
            : 0.0;

        $targetPerMonth = $req->filled('monthly_quote_target')
            ? (float)$req->input('monthly_quote_target')
            : $derivedMonthlyTarget;

        // ✅ make it available for the response
        $monthlyQuoteTarget = (int)$targetPerMonth;

        /* ===== Salesman annual target (primary) with region fallback ===== */
        $yearParam = $y ?: $defaultYear;
        $salesmanParam = trim((string)$req->input('salesman', $user->name ?? ''));

        $annualTargetSalesman = null;
//        if ($salesmanParam !== '') {
//            $annualTargetSalesman = DB::table('sales_targets')
//                ->whereRaw('LOWER(TRIM(salesman)) = ?', [strtolower($salesmanParam)])
//                ->where('year', $yearParam)
//                ->value('target_sar');
//        }

        $regionAnnualFallback = [
            'eastern' => 35_000_000,
            'central' => 37_000_000,
            'western' => 30_000_000,
        ];

        // Region used for *annual* target selection
        $regionForTarget = null;
        if (!$isAdmin && !empty($user?->region)) {
            $regionForTarget = strtolower(trim($user->region));
        } else {
            $areaParam = strtolower(trim((string)$req->query('area', '')));
            if (in_array($areaParam, ['eastern', 'central', 'western'], true)) {
                $regionForTarget = $areaParam;
            } elseif (!empty($effectiveArea) && in_array(strtolower($effectiveArea), ['eastern', 'central', 'western'], true)) {
                $regionForTarget = strtolower($effectiveArea);
            }
        }

        $annualTarget = (float)(
            $annualTargetSalesman
            ?? ($regionForTarget ? ($regionAnnualFallback[$regionForTarget] ?? 0) : 0)
        );
        $outAnnualTarget = $annualTarget; // expose later

        $targetMeta = [
            'region_used' => $regionForTarget,
            'annual_target' => $regionForTarget ? ($annualTargets[$regionForTarget] ?? 0.0) : 0.0,
            'override' => $req->filled('monthly_quote_target'),
        ];

        // Family-based annual target (share of base annual target)
        $familyPct = $familyPctMap[$familyParam] ?? null;
        $useFamilyTarget = $familyPct !== null;
        $familyAnnualTarget = $useFamilyTarget ? round($annualTarget * $familyPct, 2) : 0.0;

        if ($useFamilyTarget) {
            $targetMeta['mode'] = 'family_share';
            $targetMeta['family'] = $familyParam;
            $targetMeta['family_pct'] = $familyPct;
            $targetMeta['base_total_sar'] = $baseTotalQuoted;
        }

        foreach ($months as $ym) {
            $ih = (float)($idxVal[$ym]['In-Hand'] ?? 0);
            $bd = (float)($idxVal[$ym]['Bidding'] ?? 0);
            $lt = (float)($idxVal[$ym]['Lost'] ?? 0);
            $total = $ih + $bd + $lt;

            $colInHand[] = $ih;
            $colBidding[] = $bd;
            $colLost[] = $lt;

            // Denominator: family annual target if active; else region annual (targetPerMonth*12)
            $ytdAnnualTarget = $useFamilyTarget ? $familyAnnualTarget : ($targetPerMonth * 12);
            $linePct[] = $ytdAnnualTarget > 0 ? round(($total / $ytdAnnualTarget) * 100, 2) : 0.0;
        }

        // === Product-specific target mapping (share to frontend)
        $productTargetPct = [
            'ductwork' => 60.0,
            'accessories' => 15.0,
            'sound_attenuators' => 10.0,
            'dampers' => 15.0,
        ];

        $currentTotalQuoted = (float)$totalQuotedValue; // available if you need it later

        $currentFamilyKey = array_key_exists($familyParam, $productTargetPct) ? $familyParam : null;
        $currentFamilyPct = $currentFamilyKey ? $productTargetPct[$currentFamilyKey] : null;
        $currentFamilyTargetValue = $currentFamilyPct !== null
            ? round(($currentFamilyPct / 100.0) * $annualTarget, 2)
            : null;

        $productTargetMeta = [
            'selected_family' => $currentFamilyKey,
            'target_pct' => $currentFamilyPct,           // e.g., 60.0 for ductwork
            'target_value' => $currentFamilyTargetValue,   // = pct × annualTarget
            'basis' => 'percentage_of_annual_target',
            'mapping' => $productTargetPct,
            'annual_target' => $annualTarget,
            'monthly_target' => round($annualTarget / 12)// expose base so frontend can reuse
            // If you prefer “% of TOTAL QUOTED (all families)” on the frontend,
            // pass `total_quoted_all` here and use it instead:
            // 'total_quoted_all' => $baseTotalQuoted,
        ];

        $monthlyValueWithTarget = [
            'categories' => $months,
            'target_value' => $useFamilyTarget ? $familyAnnualTarget : ($targetPerMonth * ((int)now()->month)),
            'target_meta' => $targetMeta,
            'series' => [
                ['type' => 'column', 'name' => 'In-Hand (SAR)', 'stack' => 'Value', 'data' => $colInHand],
                ['type' => 'column', 'name' => 'Bidding (SAR)', 'stack' => 'Value', 'data' => $colBidding],
                ['type' => 'column', 'name' => 'Lost (SAR)', 'stack' => 'Value', 'data' => $colLost],
                ['type' => 'spline', 'name' => 'Target Attainment %', 'yAxis' => 1, 'data' => $linePct],
            ],
        ];

        /* ===================== 7) Funnel value ===================== */
        $normProjExpr = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(projects.quotation_no),' ',''),'.',''),'-',''),'/',''))";
        $normSoExpr = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`Quote No.`),' ',''),'.',''),'-',''),'/',''))";

        $poRecvAgg = DB::table('salesorderlog as s')
            ->selectRaw("$normSoExpr AS q_key")
            ->selectRaw("SUM(COALESCE(`PO Value`,0)) AS po_sum")
            ->whereRaw("`Quote No.` IS NOT NULL AND TRIM(`Quote No.`) <> ''")
            ->groupBy('q_key');

        $poCancelAgg = DB::table('salesorderlog as s')
            ->selectRaw("$normSoExpr AS q_key")
            ->selectRaw("SUM(COALESCE(`PO Value`,0)) AS po_sum")
            ->whereRaw("`Quote No.` IS NOT NULL AND TRIM(`Quote No.`) <> ''")
            ->whereRaw("LOWER(COALESCE(`Status`,'')) IN ('cancelled','canceled')")
            ->groupBy('q_key');

        $filteredIds = (clone $q)->pluck('projects.id');

        $poReceivedSum = DB::table('projects')
            ->whereIn('projects.id', $filteredIds)
            ->joinSub($poRecvAgg, 'po', function ($j) use ($normProjExpr) {
                $j->on(DB::raw($normProjExpr), '=', DB::raw('po.q_key'));
            })
            ->sum('po.po_sum');

        $poCancelledSum = DB::table('projects')
            ->whereIn('projects.id', $filteredIds)
            ->joinSub($poCancelAgg, 'pc', function ($j) use ($normProjExpr) {
                $j->on(DB::raw($normProjExpr), '=', DB::raw('pc.q_key'));
            })
            ->sum('pc.po_sum');

        $funnelValue = [
            'stages' => [
                ['name' => 'Total Quotation Value', 'value' => round($totalQuotedValue, 2)],
                ['name' => 'In Hand Quotation Value', 'value' => round($inhandQuotedValue, 2)],
                ['name' => 'Bidding Quotation Value', 'value' => round($biddingQuotedValue, 2)],
                ['name' => 'Sales Order Value Recieved', 'value' => round($poReceivedSum, 2)],
                ['name' => 'PO Cancelled', 'value' => round($poCancelledSum, 2)],
            ]
        ];

        /* ===================== 8) Gauges & cards ===================== */
        $monthsElapsed = (int)now()->month;

        // Annual target for card/badge (family mode shows share of annual)
        $annualTargetForCard = $useFamilyTarget ? (float)$familyAnnualTarget : (float)$annualTarget;

        // YTD “target achieved” denominator: for card we keep it annual (as per your design)
        $ytdTargetValue = (int)round($annualTargetForCard);

        $conversionPct = $totalQuotedValue > 0 ? round(100.0 * $inhandQuotedValue / $totalQuotedValue, 1) : 0.0;
        $targetAchievedPct = $ytdTargetValue > 0
            ? round(100.0 * $inhandQuotedValue / $ytdTargetValue, 1)
            : 0.0;


        // ---------- Target Achieved (family-aware) ----------
        $targetForGauge = $useFamilyTarget ? (float)$familyAnnualTarget : (float)$annualTarget;
        $quotedForGauge = (float)$totalQuotedValue;   // already family-scoped if chip is active
        $pctRaw = $targetForGauge > 0 ? round(($quotedForGauge / $targetForGauge) * 100, 1) : 0.0;
        $pctDial = min(100.0, $pctRaw);         // cap dial at 100%, keep raw for tooltip
        $diffValue = round($quotedForGauge - $targetForGauge, 2);


        /* ===================== 9) Response ===================== */
        return response()->json([
            'total_count' => (int)$totalCount,
            'total_value' => (float)$totalQuotedValue,
            'stale_bidding_count' => (int)$staleBiddingCount,
            'status' => $byStatus,
            'area_status' => [
                'categories' => $categoriesArea,
                'series' => [
                    ['name' => 'In-Hand', 'data' => $seriesInhand],
                    ['name' => 'Bidding', 'data' => $seriesBidding],
                    ['name' => 'Lost', 'data' => $seriesLost],
                ],
            ],
            'monthly_area' => [
                'categories' => $months,
                'series' => $seriesMonthly,
            ],
            'monthly_value_status_with_target' => $monthlyValueWithTarget,

            'conversion_totals' => [
                'total_inquiries' => (int)$totalCount,
                'total_quote_value' => (float)$totalQuotedValue,
            ],

            'gauges' => [
                'target_achieved' => [
                    'pct' => (float)$pctDial,        // for the dial (0..100)
                    'pct_raw' => (float)$pctRaw,         // e.g., 1000.0 for 210M / 21M
                    'quoted' => (float)$quotedForGauge, // e.g., 210_003_858
                    'target' => (float)$targetForGauge, // e.g., 21_000_000 for ductwork
                    'diff' => (float)$diffValue,      // quoted - target
                    'unit' => 'SAR',
                    'mode' => $useFamilyTarget ? 'family_share' : 'annual_region',
                    'family' => $useFamilyTarget ? $familyParam : null,
                ],

                // keep the other two gauges as they are (in-hand & bidding)
                'inhand' => [
                    'display_value' => (float)$inhandQuotedValue,
                    'pct' => $totalQuotedValue > 0 ? round(100.0 * $inhandQuotedValue / $totalQuotedValue, 1) : 0.0,
                    'unit' => 'SAR',
                ],
                'bidding' => [
                    'display_value' => (float)$biddingQuotedValue,
                    'pct' => $totalQuotedValue > 0 ? round(100.0 * $biddingQuotedValue / $totalQuotedValue, 1) : 0.0,
                    'unit' => 'SAR',
                ],
            ],

            'quote_phase' => [
                'total_quoted_value' => (float)$totalQuotedValue,
                'inhand_quoted_value' => (float)$inhandQuotedValue,
                'bidding_quoted_value' => (float)$biddingQuotedValue,
                'lost_quoted_value' => (float)$lostQuotedValue,

                'monthly_quote_target' => (int)$monthlyQuoteTarget, // ✅ defined
                'ytd_target_value' => (int)$ytdTargetValue,
                'conversion_pct' => (float)$conversionPct,
                'target_achieved_pct' => (float)$targetAchievedPct,
                'target_meta' => $targetMeta,
            ],

            'product_target_meta' => $productTargetMeta,
            'funnel_value' => $funnelValue,
            'monthly_product_value' => $this->buildMonthlyProductSeries($q, $dateExprSql, $valExprSql, $months),

            'region_scope' => $isAdmin ? 'ALL' : 'LOCKED',
            'effective_area' => $effectiveArea,
            'user_region' => $user ? $user->region : null,
            'user_roles' => ($user && method_exists($user, 'getRoleNames')) ? $user->getRoleNames() : [],
            'annual_target_salesman' => (float)$outAnnualTarget,
        ]);
    }

    /**
     * Helper to build product-wise series (unchanged behaviour).
     */
    private function buildMonthlyProductSeries($q, string $dateExprSql, string $valExprSql, array $months): array
    {
        $prodExprCase = "
        CASE
          WHEN atai_products IS NULL OR TRIM(atai_products) = '' THEN 'Unspecified'
          WHEN LOWER(TRIM(atai_products)) REGEXP '^access[[:space:]]door(s)?$'         THEN 'Access Doors'
          WHEN LOWER(TRIM(atai_products)) REGEXP '^sound[[:space:]]attenuator(s)?$'   THEN 'Sound Attenuators'
          WHEN LOWER(TRIM(atai_products)) REGEXP 'damper'                              THEN 'Dampers'
          WHEN LOWER(TRIM(atai_products)) REGEXP 'duct|ductwork'                       THEN 'Ductwork'
          WHEN LOWER(TRIM(atai_products)) REGEXP 'louver'                              THEN 'Louvers'
          WHEN LOWER(TRIM(atai_products)) REGEXP 'grille|diffuser|register|\\bgrd\\b'  THEN 'GRD'
          ELSE 'Accessories'
        END
        ";

        $productRows = (clone $q)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym")
            ->selectRaw("$prodExprCase AS product_group")
            ->selectRaw("SUM($valExprSql) AS amt")
            ->groupBy('ym', 'product_group')
            ->orderBy('ym')
            ->get();

        $totalsByProduct = $productRows->groupBy('product_group')->map(fn($grp) => (float)$grp->sum('amt'));
        $topN = 8;
        $topProducts = $totalsByProduct->sortDesc()->keys()->take($topN)->values();
        $otherProducts = $totalsByProduct->keys()->diff($topProducts);

        $idxProd = [];
        foreach ($productRows as $r) {
            $g = in_array($r->product_group, $topProducts->all(), true)
                ? $r->product_group
                : ($otherProducts->isNotEmpty() ? 'Others' : $r->product_group);
            $idxProd[$r->ym][$g] = ($idxProd[$r->ym][$g] ?? 0.0) + (float)$r->amt;
        }

        $productsForSeries = $topProducts->all();
        if ($otherProducts->isNotEmpty() && !in_array('Others', $productsForSeries, true)) {
            $productsForSeries[] = 'Others';
        }

        // Column series + compute monthly totals
        $productSeries = [];
        $monthlyTotals = [];
        foreach ($productsForSeries as $pn) {
            $data = [];
            foreach ($months as $ym) {
                $val = round((float)($idxProd[$ym][$pn] ?? 0.0), 2);
                $data[] = $val;
                $monthlyTotals[$ym] = ($monthlyTotals[$ym] ?? 0.0) + $val;
            }
            $productSeries[] = ['type' => 'column', 'name' => $pn, 'stack' => 'Products', 'data' => $data];
        }

        // Build MoM% line: y = mom%, carry SAR total in "sar"
        $splineData = [];
        $prev = null;
        foreach ($months as $ym) {
            $tot = round((float)($monthlyTotals[$ym] ?? 0.0), 2);
            $mom = ($prev !== null && $prev != 0.0) ? round((($tot - $prev) / $prev) * 100, 1) : 0.0;
            $splineData[] = ['y' => $mom, 'sar' => $tot]; // y is percent, sar for tooltip
            $prev = $tot;
        }
        $productSeries[] = ['type' => 'spline', 'name' => 'Total', 'yAxis' => 1, 'data' => $splineData];

        return [
            'categories' => $months,
            'series' => $productSeries,
        ];
    }

    /**
     * Single project for the modal.
     * GET /api/inquiries/{id}
     */
    public function show($id)
    {
        $p = Project::query()->findOrFail($id);

        return response()->json([
            'id' => $p->id,
            'name' => $p->name,
            'client' => $p->client,
            'location' => $p->location,
            'area' => $p->area,
            'price' => (float)($p->quotation_value ?? $p->price ?? 0),
            'currency' => 'SAR',
            'status' => $p->status,
            'comments' => $p->remark,
            'checklist' => (object)[],
            'quotation_no' => $p->quotation_no,
            'quotation_date' => $p->quotation_date,
            'atai_products' => $p->atai_products,
        ]);
    }

    /**
     * Totals for the "Total (SAR)" box (still raw sum + count).
     * GET /api/totals?family=…&area=…&status=…&year=…
     */
    public function totals(Request $r)
    {
        [$base] = $this->baseQuery($r);

        if ($r->filled('status')) {
            $base->where('status', $r->query('status'));
        }

        $sum = (float)(clone $base)
            ->selectRaw('SUM(COALESCE(quotation_value, price, 0)) as sum_price')
            ->value('sum_price');

        $cnt = (clone $base)->count();

        return response()->json([
            'count' => (int)$cnt,
            'sum_price' => (float)$sum,
        ]);
    }

    /* ============================ Helpers ============================ */

    /**
     * Base Query Builder with RBAC & filters (used by totals()).
     * NOTE: this helper also returns raw data only; no derived KPIs here.
     */
    private function baseQuery(Request $r): array
    {
        // RegionScope helper (keep if you use it)
        $effectiveRegion = \App\Support\RegionScope::apply($r); // may be null

        $u = $r->user();
        if (!$effectiveRegion && $u) {
            $isManagerial = method_exists($u, 'hasAnyRole')
                ? $u->hasAnyRole(['gm', 'admin', 'manager'])
                : false;

            if (!$isManagerial && !empty($u->region)) {
                $effectiveRegion = $u->region; // e.g., "Eastern"
            }
        }

        $norm = fn($s) => strtolower(trim((string)$s));

        $dateExpr = "COALESCE(
            STR_TO_DATE(quotation_date, '%Y-%m-%d'),
            STR_TO_DATE(quotation_date, '%d-%m-%Y'),
            DATE(created_at)
        )";

        $qb = DB::table('projects');

        // Year
        if ($r->integer('year')) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$r->integer('year')]);
        }

        // date_from / date_to / month
        $dateFrom = $r->query('date_from');
        $dateTo = $r->query('date_to');
        $year = $r->integer('year');
        $month = $r->integer('month');

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ?: '1900-01-01';
            $to = $dateTo ?: '2999-12-31';
            $qb->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
        } elseif ($month) {
            $yyyy = $year ?: date('Y');
            $start = sprintf('%04d-%02d-01', $yyyy, $month);
            $qb->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$start, $start]);
        } elseif ($year) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$year]);
        }

        // Family filter
        if ($r->filled('family') && strtolower($r->query('family')) !== 'all') {
            $this->applyFamilyFilterQB($qb, strtolower($r->query('family')));
        }

        // Region enforcement
        if (!empty($effectiveRegion)) {
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($effectiveRegion)]);
        } elseif ($r->filled('area')) {
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($r->query('area'))]);
        }

        // Expose scope for debugging
        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return [$qb];
    }

    /**
     * Family filter mapping for Query Builder
     */
    private function applyFamilyFilterQB($qb, string $family): void
    {
        $fam = trim($family);
        $qb->where(function ($qq) use ($fam) {
            if ($fam === 'ductwork') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
            } elseif ($fam === 'dampers') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
            } elseif (in_array($fam, ['sound_attenuators', 'attenuators', 'attenuator'], true)) {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
            } elseif ($fam === 'accessories') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
            } else {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%' . $fam . '%']);
            }
        });
    }
}
