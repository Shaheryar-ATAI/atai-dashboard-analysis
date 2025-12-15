<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectApiController extends Controller
{
    /* =========================================================
     * SALESPERSON REGION ALIAS HELPERS
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
            'SOHAIB' => 'eastern',
            'SOAHIB' => 'eastern',
            'TARIQ'  => 'central',
            'TAREQ'  => 'central',
            'JAMAL'  => 'central',
            'ABDO'   => 'western',
            'ABDUL'  => 'western',
            'ABDOU'  => 'western',
            'AHMED'  => 'western',
        ];
    }

    /** Canonicalize salesperson name (UPPERCASE, remove spaces) */
    protected function canonSalesKey(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$name));
    }

    /**
     * KPI payload (QUOTE PHASE ONLY).
     * GET /api/kpis?family=ductwork&area=Eastern&year=2025&month=1..12
     */
    public function kpis(Request $req)
    {
        /* ===================== 0) Auth & RBAC ===================== */
        $user = $req->user();
        $isAdmin = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm', 'admin']);
        $effectiveArea = null;

        /* ===================== 1) Base query (Projects only) ===================== */
        $q = Project::query()->whereNull('deleted_at');

        /* ===================== Area filter ===================== */
        if ($req->filled('area') && strcasecmp($req->input('area'), 'all') !== 0) {
            $area = $req->input('area');
            $q->where('area', $area);
            $effectiveArea = $area;
        } else {
            // No area filter -> do not restrict dataset; just remember for targets
            $effectiveArea = ($user && !empty($user->region)) ? $user->region : null;
        }

        /* ===================== Salesman (alias-aware) ===================== */
        $buildAliasSet = function (?string $name) {
            $key = $this->canonSalesKey($name);
            if ($key === '') return [];

            foreach (['eastern', 'central', 'western'] as $region) {
                $set = $this->salesAliasesForRegion($region);
                if (in_array($key, $set, true)) {
                    return $set; // region alias set
                }
            }
            return [$key];
        };

        // robust input gate (supports multiple frontend param names)
        $salesmanRaw =
            $req->input('salesman')
            ?? $req->input('salesman_name')
            ?? $req->input('salesperson')
            ?? $req->input('salesmanFilter')
            ?? null;

        $salesmanRaw = is_string($salesmanRaw) ? trim($salesmanRaw) : '';

        $isAllSalesman = ($salesmanRaw === '')
            || in_array(strtolower($salesmanRaw), ['all', 'all salesmen', 'all regions'], true);

        if (!$isAllSalesman) {
            $aliases = $buildAliasSet($salesmanRaw);

            if (!empty($aliases)) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $a) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$a]);
                    }
                });
            }
        } elseif (!$isAdmin) {
            // no salesman param: for non-admins, infer from logged-in user
            $first = strtoupper(trim(explode(' ', (string)($user->name ?? ''))[0] ?? ''));
            $home = $this->homeRegionBySalesperson();
            $userHomeRegion = $home[$first] ?? null;

            $regionForAliases = $userHomeRegion ?: (!empty($user->region) ? strtolower($user->region) : null);
            $aliases = $regionForAliases ? $this->salesAliasesForRegion($regionForAliases) : [];

            if (!empty($aliases)) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $a) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$a]);
                    }
                });
            } elseif (!empty($first)) {
                $q->whereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$first]);
            }
        }

        // ===== Stale bidding count (uses same scope; ignores date filters later by cloning now) =====
        $staleBiddingCount = (clone $q)->staleBidding()->count();

        /* ===================== Dates & Filters ===================== */
        $dateExprSql = "STR_TO_DATE(quotation_date,'%Y-%m-%d')";
        $defaultYear = 2025;

        $familyPctMap = [
            'ductwork'          => 0.60,
            'accessories'       => 0.15,
            'sound'             => 0.10,
            'sound_attenuators' => 0.10,
            'attenuators'       => 0.10,
            'dampers'           => 0.15,
        ];
        $familyParam = strtolower(trim((string)$req->query('family', 'all')));

        $hasExplicitRange = $req->filled('date_from') || $req->filled('date_to') || $req->filled('month') || $req->filled('year');

        $y = $req->integer('year') ?: ($hasExplicitRange ? null : $defaultYear);

        $monthRaw = $req->input('month')
            ?? $req->input('month_no')
            ?? $req->input('monthName')
            ?? $req->input('month_name')
            ?? null;

        $monthRaw = trim((string)$monthRaw);
        $m = null;

        if ($monthRaw !== '') {
            if (is_numeric($monthRaw)) {
                $m = (int)$monthRaw;
            } else {
                try {
                    $m = \Carbon\Carbon::parse('1 ' . $monthRaw)->month;
                } catch (\Throwable $e) {
                    $m = null;
                }
            }
        }

        $df = $req->input('date_from') ?: null;
        $dt = $req->input('date_to') ?: null;

        if ($y)  $q->whereRaw("YEAR($dateExprSql) = ?", [$y]);
        if ($m)  $q->whereRaw("MONTH($dateExprSql) = ?", [$m]);
        if ($df) $q->whereRaw("$dateExprSql >= ?", [$df]);
        if ($dt) $q->whereRaw("$dateExprSql <= ?", [$dt]);

        // clone BEFORE family filter (for base totals / target shares)
        $qNoFamily = clone $q;

        // Family filter (ignore all)
        if ($req->filled('family')) {
            $fam = strtolower(trim($req->input('family')));
            if ($fam !== 'all') {
                $q->whereRaw('LOWER(atai_products) LIKE ?', ['%' . $fam . '%']);
            }
        }

        /* ===================== Status buckets ===================== */
        $pt = "LOWER(TRIM(projects.project_type))";
        $st = "LOWER(TRIM(projects.status))";

        $inHandList  = "'in-hand','in hand','inhand','accepted','won','order','order in hand','ih'";
        $biddingList = "'bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry'";
        $lostList    = "'lost','rejected','cancelled','canceled','closed lost','declined','not awarded'";

        $computedStatus = "
            CASE
              WHEN $st IN ($lostList) OR $pt IN ($lostList) THEN 'Lost'
              WHEN $st IN ($inHandList) OR $pt IN ($inHandList) THEN 'In-Hand'
              WHEN $st IN ($biddingList) OR $pt IN ($biddingList) THEN 'Bidding'
              ELSE 'Other'
            END
        ";

        // Optional status_norm filter (fix: map input -> computed bucket labels)
        $statusNorm = strtolower(trim((string)$req->query('status_norm', '')));
        if ($statusNorm !== '' && $statusNorm !== 'po-received') {
            $map = [
                'bidding' => 'Bidding',
                'in-hand' => 'In-Hand',
                'inhand'  => 'In-Hand',
                'lost'    => 'Lost',
                'other'   => 'Other',
            ];
            $bucket = $map[$statusNorm] ?? null;
            if ($bucket) {
                $q->whereRaw("($computedStatus) = ?", [$bucket]);
            }
        }

        /* ===================== Value expression ===================== */
        $valExprSql = "COALESCE(quotation_value, 0)";

        /* ===================== Totals ===================== */
        $baseTotalQuoted = (float)((clone $qNoFamily)->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);

        $totalQuotedValue  = (float)((clone $q)->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $inhandQuotedValue = (float)((clone $q)->whereRaw("( $computedStatus )='In-Hand'")->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $biddingQuotedValue= (float)((clone $q)->whereRaw("( $computedStatus )='Bidding'")->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);
        $lostQuotedValue   = (float)((clone $q)->whereRaw("( $computedStatus )='Lost'")->selectRaw("SUM($valExprSql) AS t")->value('t') ?? 0);

        $totalCount = (clone $q)->distinct('quotation_no')->count('quotation_no');

        /* ===================== Area breakdown (for Highcharts) ===================== */
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
        $extraAreas = $mapArea->keys()->diff($catsPreferred);
        $categoriesArea = $catsPreferred->merge($extraAreas)->values()->all();

        $seriesInhand = $seriesBidding = $seriesLost = [];
        foreach ($categoriesArea as $a) {
            $r = $mapArea->get($a);
            $seriesInhand[]  = ['y' => (int)($r->inhand_cnt ?? 0),  'sar' => (float)($r->inhand_val ?? 0)];
            $seriesBidding[] = ['y' => (int)($r->bidding_cnt ?? 0), 'sar' => (float)($r->bidding_val ?? 0)];
            $seriesLost[]    = ['y' => (int)($r->lost_cnt ?? 0),    'sar' => (float)($r->lost_val ?? 0)];
        }

        /* ===================== Monthly window (for charts) ===================== */
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfMonth();
            $end   = \Carbon\Carbon::parse($dt)->endOfMonth();
        } elseif ($y) {
            $start = \Carbon\Carbon::create($y, 1, 1)->startOfMonth();
            $end   = \Carbon\Carbon::create($y, 12, 1)->endOfMonth();
        } else {
            $start = \Carbon\Carbon::create($defaultYear, 1, 1)->startOfMonth();
            $end   = \Carbon\Carbon::create($defaultYear, 12, 1)->endOfMonth();
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

        // Monthly value by status
        $valRows = (clone $q)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, ($computedStatus) AS status_norm, SUM($valExprSql) AS amt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym', 'status_norm')->orderBy('ym')->get();

        $idxVal = [];
        foreach ($valRows as $r) $idxVal[$r->ym][$r->status_norm] = (float)$r->amt;

        $colInHand = $colBidding = $colLost = [];
        foreach ($months as $ym) {
            $colInHand[]  = (float)($idxVal[$ym]['In-Hand'] ?? 0);
            $colBidding[] = (float)($idxVal[$ym]['Bidding'] ?? 0);
            $colLost[]    = (float)($idxVal[$ym]['Lost'] ?? 0);
        }

        /* ===================== Targets (keep your behaviour; fix monthly derivation) ===================== */
        $annualTargets = [
            'eastern' => 35_000_000,
            'central' => 37_000_000,
            'western' => 30_000_000,
        ];

        // Determine region for target selection (same intent as your code)
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

        $annualTarget = (float)($regionForTarget ? ($annualTargets[$regionForTarget] ?? 0) : 0);

        // ✅ monthly quote target:
        // - if user passes monthly_quote_target => use it
        // - else derive from annualTarget / 12
        $targetPerMonth = $req->filled('monthly_quote_target')
            ? (float)$req->input('monthly_quote_target')
            : ($annualTarget > 0 ? ($annualTarget / 12.0) : 0.0);

        $monthlyQuoteTarget = (int)round($targetPerMonth);

        // Family mode
        $familyPct = $familyPctMap[$familyParam] ?? null;
        $useFamilyTarget = $familyPct !== null;
        $familyAnnualTarget = $useFamilyTarget ? round($annualTarget * $familyPct, 2) : 0.0;

        $targetMeta = [
            'region_used'   => $regionForTarget,
            'annual_target' => $annualTarget,
            'override'      => $req->filled('monthly_quote_target'),
            'mode'          => $useFamilyTarget ? 'family_share' : 'region',
            'family'        => $useFamilyTarget ? $familyParam : null,
            'family_pct'    => $useFamilyTarget ? $familyPct : null,
            'base_total_sar'=> $baseTotalQuoted,
        ];

        $monthsElapsed = (int)now()->month;

        // line % (keep your existing style: per-month attainment vs annual)
        $linePct = [];
        foreach ($months as $ym) {
            $ih = (float)($idxVal[$ym]['In-Hand'] ?? 0);
            $bd = (float)($idxVal[$ym]['Bidding'] ?? 0);
            $lt = (float)($idxVal[$ym]['Lost'] ?? 0);
            $total = $ih + $bd + $lt;

            $den = $useFamilyTarget ? $familyAnnualTarget : $annualTarget;
            $linePct[] = $den > 0 ? round(($total / $den) * 100, 2) : 0.0;
        }

        // YTD target value (for chart label/box)
        $annualForYtd = $useFamilyTarget ? $familyAnnualTarget : $annualTarget;
        $ytdTargetValue = (int)round($annualForYtd);

        $monthlyValueWithTarget = [
            'categories'   => $months,
            'target_value' => ($annualForYtd > 0 ? round(($annualForYtd / 12.0) * $monthsElapsed, 2) : 0.0),
            'target_meta'  => $targetMeta,
            'series' => [
                ['type' => 'column', 'name' => 'In-Hand (SAR)',  'stack' => 'Value', 'data' => $colInHand],
                ['type' => 'column', 'name' => 'Bidding (SAR)',  'stack' => 'Value', 'data' => $colBidding],
                ['type' => 'column', 'name' => 'Lost (SAR)',     'stack' => 'Value', 'data' => $colLost],
                ['type' => 'spline', 'name' => 'Target Attainment %', 'yAxis' => 1, 'data' => $linePct],
            ],
        ];

        /* ===================== Funnel values (PO received / cancelled) ===================== */
        $normProjExpr = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(projects.quotation_no),' ',''),'.',''),'-',''),'/',''))";
        $normSoExpr   = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`Quote No.`),' ',''),'.',''),'-',''),'/',''))";

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
                ['name' => 'Total Quotation Value',        'value' => round($totalQuotedValue, 2)],
                ['name' => 'In Hand Quotation Value',      'value' => round($inhandQuotedValue, 2)],
                ['name' => 'Bidding Quotation Value',      'value' => round($biddingQuotedValue, 2)],
                ['name' => 'Sales Order Value Recieved',   'value' => round($poReceivedSum, 2)],
                ['name' => 'PO Cancelled',                 'value' => round($poCancelledSum, 2)],
            ]
        ];

        /* ===================== Gauges ===================== */
        $targetForGauge = $useFamilyTarget ? (float)$familyAnnualTarget : (float)$annualTarget;
        $quotedForGauge = (float)$totalQuotedValue;

        $pctRaw  = $targetForGauge > 0 ? round(($quotedForGauge / $targetForGauge) * 100, 1) : 0.0;
        $pctDial = min(100.0, $pctRaw);
        $diffValue = round($quotedForGauge - $targetForGauge, 2);

        $conversionPct = $totalQuotedValue > 0 ? round(100.0 * $inhandQuotedValue / $totalQuotedValue, 1) : 0.0;
        $targetAchievedPct = $ytdTargetValue > 0 ? round(100.0 * $inhandQuotedValue / $ytdTargetValue, 1) : 0.0;

        /* ===================== Product target meta (kept) ===================== */
        $productTargetPct = [
            'ductwork'          => 60.0,
            'accessories'       => 15.0,
            'sound_attenuators' => 10.0,
            'dampers'           => 15.0,
        ];

        $currentFamilyKey = array_key_exists($familyParam, $productTargetPct) ? $familyParam : null;
        $currentFamilyPct = $currentFamilyKey ? $productTargetPct[$currentFamilyKey] : null;

        $productTargetMeta = [
            'selected_family' => $currentFamilyKey,
            'target_pct'      => $currentFamilyPct,
            'target_value'    => $currentFamilyPct !== null ? round(($currentFamilyPct / 100.0) * $annualTarget, 2) : null,
            'basis'           => 'percentage_of_annual_target',
            'mapping'         => $productTargetPct,
            'annual_target'   => $annualTarget,
            'monthly_target'  => round($annualTarget / 12.0),
        ];

        /* ===================== Response ===================== */
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
                    ['name' => 'Lost',    'data' => $seriesLost],
                ],
            ],

            'monthly_area' => [
                'categories' => $months,
                'series' => $seriesMonthly,
            ],

            'monthly_value_status_with_target' => $monthlyValueWithTarget,

            'conversion_totals' => [
                'total_inquiries'   => (int)$totalCount,
                'total_quote_value' => (float)$totalQuotedValue,
            ],

            'debug' => [
                'salesman_param' => $req->input('salesman'),
                'salesman_all_params_keys' => array_keys($req->all()),
                'salesman_querystring' => $req->query('salesman'),
                'salesman_post' => $req->post('salesman'),
                'month_raw' => $monthRaw,
                'month_int' => $m,
                'year' => $y,
                'sql_count' => (clone $q)->count(),
                'distinct_quotation_no' => (clone $q)->distinct('quotation_no')->count('quotation_no'),
                'effective_area' => $effectiveArea,
            ],

            'gauges' => [
                'target_achieved' => [
                    'pct'     => (float)$pctDial,
                    'pct_raw' => (float)$pctRaw,
                    'quoted'  => (float)$quotedForGauge,
                    'target'  => (float)$targetForGauge,
                    'diff'    => (float)$diffValue,
                    'unit'    => 'SAR',
                    'mode'    => $useFamilyTarget ? 'family_share' : 'annual_region',
                    'family'  => $useFamilyTarget ? $familyParam : null,
                ],
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
                'total_quoted_value'  => (float)$totalQuotedValue,
                'inhand_quoted_value' => (float)$inhandQuotedValue,
                'bidding_quoted_value'=> (float)$biddingQuotedValue,
                'lost_quoted_value'   => (float)$lostQuotedValue,

                'monthly_quote_target' => (int)$monthlyQuoteTarget,
                'ytd_target_value'     => (int)$ytdTargetValue,
                'conversion_pct'       => (float)$conversionPct,
                'target_achieved_pct'  => (float)$targetAchievedPct,
                'target_meta'          => $targetMeta,
            ],

            'product_target_meta' => $productTargetMeta,

            'funnel_value' => $funnelValue,

            'monthly_product_value' => $this->buildMonthlyProductSeries($q, $dateExprSql, $valExprSql, $months),

            'region_scope' => $isAdmin ? 'ALL' : 'LOCKED',
            'effective_area' => $effectiveArea,
            'user_region' => $user ? $user->region : null,
            'user_roles' => ($user && method_exists($user, 'getRoleNames')) ? $user->getRoleNames() : [],
            'annual_target_salesman' => (float)$annualTarget,
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

        // MoM% spline
        $splineData = [];
        $prev = null;
        foreach ($months as $ym) {
            $tot = round((float)($monthlyTotals[$ym] ?? 0.0), 2);
            $mom = ($prev !== null && $prev != 0.0) ? round((($tot - $prev) / $prev) * 100, 1) : 0.0;
            $splineData[] = ['y' => $mom, 'sar' => $tot];
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
     * Totals endpoint (kept as-is, uses baseQuery)
     * GET /api/totals?family=…&area=…&status=…&year=…
     */
    public function totals(Request $r)
    {
        [$base] = $this->baseQuery($r);

        if ($r->filled('status')) {
            $base->where('status', $r->query('status'));
        }

        $sum = (float)(clone $base)->selectRaw('SUM(COALESCE(quotation_value, price, 0)) as sum_price')->value('sum_price');
        $cnt = (clone $base)->count();

        return response()->json([
            'count' => (int)$cnt,
            'sum_price' => (float)$sum,
        ]);
    }

    /* ============================ Helpers (totals only) ============================ */

    private function baseQuery(Request $r): array
    {
        $effectiveRegion = \App\Support\RegionScope::apply($r);

        $u = $r->user();
        if (!$effectiveRegion && $u) {
            $isManagerial = method_exists($u, 'hasAnyRole')
                ? $u->hasAnyRole(['gm', 'admin', 'manager'])
                : false;

            if (!$isManagerial && !empty($u->region)) {
                $effectiveRegion = $u->region;
            }
        }

        $norm = fn($s) => strtolower(trim((string)$s));

        $dateExpr = "COALESCE(
            STR_TO_DATE(quotation_date, '%Y-%m-%d'),
            STR_TO_DATE(quotation_date, '%d-%m-%Y'),
            DATE(created_at)
        )";

        $qb = DB::table('projects');

        if ($r->integer('year')) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$r->integer('year')]);
        }

        $dateFrom = $r->query('date_from');
        $dateTo   = $r->query('date_to');
        $year     = $r->integer('year');
        $month    = $r->integer('month');

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ?: '1900-01-01';
            $to   = $dateTo ?: '2999-12-31';
            $qb->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
        } elseif ($month) {
            $yyyy = $year ?: date('Y');
            $start = sprintf('%04d-%02d-01', $yyyy, $month);
            $qb->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$start, $start]);
        } elseif ($year) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$year]);
        }

        if ($r->filled('family') && strtolower($r->query('family')) !== 'all') {
            $this->applyFamilyFilterQB($qb, strtolower($r->query('family')));
        }

        if (!empty($effectiveRegion)) {
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($effectiveRegion)]);
        } elseif ($r->filled('area')) {
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($r->query('area'))]);
        }

        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return [$qb];
    }

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
