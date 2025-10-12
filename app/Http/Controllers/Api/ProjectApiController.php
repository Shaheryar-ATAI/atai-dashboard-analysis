<?php

namespace App\Http\Controllers\Api;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RegionScope;   // ⬅️ same helper used in your DT controller
use App\Models\Project;

class ProjectApiController extends Controller
{
    /**
     * Highcharts data (and total) used on the dashboard.
     * GET /api/kpis?family=ductwork&area=Eastern&year=2025
     */


    public function kpis(Request $req)
    {
        $user    = $req->user();
        $isAdmin = $user && $user->hasAnyRole(['gm','admin']);
        $effectiveArea = null;

        // -----------------------------
        // Base query (PROJECTS) + RBAC
        // -----------------------------
        $q = Project::query();

        if (!$isAdmin) {
            if (!empty($user->region)) {
                $q->where('area', $user->region);
                $effectiveArea = $user->region;
            }
        } else {
            if ($req->filled('area') && strtoupper($req->input('area')) !== 'ALL') {
                $q->where('area', $req->input('area'));
                $effectiveArea = $req->input('area');
            }
        }

        // One consistent date expression (projects side)
        $dateExprSql = "COALESCE(
        STR_TO_DATE(quotation_date,'%Y-%m-%d'),
        STR_TO_DATE(quotation_date,'%d-%m-%Y'),
        STR_TO_DATE(quotation_date,'%d/%m/%Y'),
        DATE(created_at)
    )";

        // -----------------------------
        // Filters
        // -----------------------------
        $y  = $req->integer('year') ?: null;
        $m  = $req->integer('month') ?: null;
        $df = $req->input('date_from') ?: null;
        $dt = $req->input('date_to')   ?: null;

        if ($y)  $q->whereRaw("YEAR($dateExprSql)=?", [$y]);
        if ($m)  $q->whereRaw("MONTH($dateExprSql)=?", [$m]);
        if ($df) $q->whereRaw("$dateExprSql>=?", [$df]);
        if ($dt) $q->whereRaw("$dateExprSql<=?", [$dt]);

        if ($fam = (string) $req->input('family')) {
            $q->where('atai_products', 'like', "%{$fam}%");
        }

        $base = (clone $q); // use this everywhere below

        // -----------------------------
        // Status normalization
        // -----------------------------
        $statusCase = "
      CASE
        WHEN LOWER(TRIM(project_type)) IN ('in-hand','in hand','inhand','accepted','won','order','order in hand','ih') THEN 'In-Hand'
        WHEN LOWER(TRIM(project_type)) IN ('bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry') THEN 'Bidding'
        WHEN LOWER(TRIM(status)) IN ('lost','rejected','cancelled','canceled','closed lost','declined','not awarded') THEN 'Lost'

        ELSE 'Other'
      END
    ";

        // -----------------------------
        // KPIs you already show
        // -----------------------------
        $byStatus = (clone $base)
            ->selectRaw("$statusCase AS status_norm, SUM(COALESCE(quotation_value, price, 0)) AS sum_value")
            ->groupBy('status_norm')
            ->get();

        $rowsArea = (clone $base)->selectRaw("
        COALESCE(area,'—') AS area,
        SUM(CASE WHEN ($statusCase)='In-Hand' THEN 1 ELSE 0 END) AS inhand_cnt,
        SUM(CASE WHEN ($statusCase)='Bidding' THEN 1 ELSE 0 END) AS bidding_cnt,
        SUM(CASE WHEN ($statusCase)='Lost'    THEN 1 ELSE 0 END) AS lost_cnt,
        SUM(CASE WHEN ($statusCase)='In-Hand' THEN COALESCE(quotation_value, price, 0) ELSE 0 END) AS inhand_val,
        SUM(CASE WHEN ($statusCase)='Bidding' THEN COALESCE(quotation_value, price, 0) ELSE 0 END) AS bidding_val,
        SUM(CASE WHEN ($statusCase)='Lost'    THEN COALESCE(quotation_value, price, 0) ELSE 0 END) AS lost_val
    ")
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        $preferredOrder = ['Eastern','Central','Western'];
        $mapArea        = collect($rowsArea)->keyBy('area');
        $catsPreferred  = collect($preferredOrder);
        $extraAreasAsc  = $mapArea->keys()->diff($catsPreferred);
        $categoriesArea = $catsPreferred->merge($extraAreasAsc)->values()->all();

        $seriesInhand = $seriesBidding = $seriesLost = [];
        foreach ($categoriesArea as $a) {
            $r = $mapArea->get($a);
            $seriesInhand[]  = ['y' => (int)($r->inhand_cnt  ?? 0), 'sar' => (float)($r->inhand_val ?? 0)];
            $seriesBidding[] = ['y' => (int)($r->bidding_cnt ?? 0), 'sar' => (float)($r->bidding_val ?? 0)];
            $seriesLost[]    = ['y' => (int)($r->lost_cnt    ?? 0), 'sar' => (float)($r->lost_val ?? 0)];
        }

        // Month window for monthly charts
        if ($df && $dt) {
            $start = \Carbon\Carbon::parse($df)->startOfMonth();
            $end   = \Carbon\Carbon::parse($dt)->endOfMonth();
        } elseif ($y) {
            $start = \Carbon\Carbon::create($y,1,1)->startOfMonth();
            $end   = \Carbon\Carbon::create($y,12,1)->endOfMonth();
        } else {
            $start = now()->startOfYear();
            $end   = now()->endOfYear();
        }
        $months = [];
        for ($c = $start->copy(); $c <= $end; $c->addMonth()) $months[] = $c->format('Y-m');

        $monthlyRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, COALESCE(area,'—') AS area, COUNT(*) AS cnt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area')->orderBy('ym')->get();

        $baseAreas     = ['Eastern','Central','Western'];
        $extraFromData = $monthlyRows->pluck('area')->unique()->diff($baseAreas)->values()->all();
        $areasAll      = array_values(array_unique(array_merge($baseAreas,$extraFromData)));

        $idxMonthly = [];
        foreach ($monthlyRows as $r) $idxMonthly[$r->ym][$r->area] = (int)$r->cnt;

        $seriesMonthly = [];
        foreach ($areasAll as $a) {
            $data = [];
            foreach ($months as $ym) $data[] = $idxMonthly[$ym][$a] ?? 0;
            $seriesMonthly[] = ['name'=>$a,'data'=>$data];
        }

        // Value-by-status with target line
        $valRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, ($statusCase) AS status_norm, SUM(COALESCE(quotation_value, price, 0)) AS amt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','status_norm')->orderBy('ym')->get();

        $idxVal = [];
        foreach ($valRows as $r) $idxVal[$r->ym][$r->status_norm] = (float)$r->amt;

        $colInHand=[]; $colBidding=[]; $colLost=[]; $linePct=[];
        $target = (float) $req->input('monthly_target', 20000000);
        foreach ($months as $ym) {
            $ih = (float) ($idxVal[$ym]['In-Hand'] ?? 0);
            $bd = (float) ($idxVal[$ym]['Bidding'] ?? 0);
            $lt = (float) ($idxVal[$ym]['Lost'] ?? 0);
            $total = $ih + $bd + $lt;
            $colInHand[]  = $ih;
            $colBidding[] = $bd;
            $colLost[]    = $lt;
            $linePct[]    = $target > 0 ? round(($total / $target) * 100, 2) : 0.0;
        }

        $monthlyValueWithTarget = [
            'categories' => $months,
            'target_value' => 20000000,
            'series' => [
                ['type'=>'column','name'=>'In-Hand (SAR)','stack'=>'Value','data'=>$colInHand],
                ['type'=>'column','name'=>'Bidding (SAR)','stack'=>'Value','data'=>$colBidding],
                ['type'=>'column','name'=>'Lost (SAR)','stack'=>'Value','data'=>$colLost],
                ['type'=>'spline','name'=>'Target Attainment %','yAxis'=>1,'tooltip'=>['valueSuffix'=>'%'],'data'=>$linePct]
            ],
        ];

        // Totals
        $totalCount = (clone $base)->count();
        $totalValue = (float) ((clone $base)->selectRaw("SUM(COALESCE(quotation_value,price,0)) AS t")->value('t') ?? 0);

        // -----------------------------------------------
        // VALUE CONVERSION % (PO value / Quoted value)
        // -----------------------------------------------
        $normProj = "UPPER(REPLACE(REPLACE(REPLACE(projects.quotation_no,' ',''),'.',''),'-',''))";

        $poAggForValue = DB::table('salesorderlog as s')
            ->selectRaw("UPPER(REPLACE(REPLACE(REPLACE(`Quote No.`,' ',''),'.',''),'-','')) AS q_key")
            ->selectRaw("SUM(COALESCE(`PO Value`,0)) AS po_sum")
            ->whereNotNull(DB::raw('`Quote No.`'))
            ->whereRaw("TRIM(`Quote No.`) <> ''")
            ->groupBy('q_key');

        $filteredIds = (clone $base)->pluck('id');

        $poValueSum = DB::table('projects')
            ->whereIn('projects.id', $filteredIds)
            ->joinSub($poAggForValue, 'so', function ($j) use ($normProj) {
                $j->on(DB::raw($normProj), '=', DB::raw('so.q_key')); // expression vs expression
            })
            ->sum('so.po_sum');

        $valueConvPct = $totalValue > 0 ? round(($poValueSum / $totalValue) * 100, 1) : 0.0;

        // -----------------------------------------------
        // COUNT & VALUE conversion over matched quotations
        // -----------------------------------------------
        $poAggForCounts = DB::table('salesorderlog as s')
            ->selectRaw("UPPER(REPLACE(REPLACE(REPLACE(`Quote No.`,' ',''),'.',''),'-','')) AS q_key")
            ->selectRaw("SUM(COALESCE(`PO Value`,0)) AS po_total")
            ->selectRaw("COUNT(DISTINCT `PO. No.`)   AS po_cnt")
            ->whereNotNull(DB::raw('`Quote No.`'))
            ->whereRaw("TRIM(`Quote No.`) <> ''")
            ->groupBy('q_key');

        $eligible = (clone $base);

        $totalInquiries  = (clone $eligible)->count();
        $totalQuoteValue = (float) ((clone $eligible)
            ->selectRaw('SUM(COALESCE(quotation_value, price, 0)) AS t')->value('t') ?? 0);

        $eligibleWithPo = (clone $eligible)
            ->joinSub($poAggForCounts, 'po', function ($j) use ($normProj) {
                $j->on(DB::raw($normProj), '=', DB::raw('po.q_key')); // correct join
            });

        $projectsWithPo = (int) (clone $eligibleWithPo)
            ->distinct('projects.id')->count('projects.id');

        $totalPoValueMatched = (float) ((clone $eligibleWithPo)
            ->selectRaw('SUM(po.po_total) AS s')->value('s') ?? 0);

        $valueConversionPct = $totalQuoteValue > 0 ? (100.0 * $totalPoValueMatched / $totalQuoteValue) : 0.0;
        $countConversionPct = $totalInquiries  > 0 ? (100.0 * $projectsWithPo   / $totalInquiries)  : 0.0;

        // -----------------------------
        // Response
        // -----------------------------
        return response()->json([
            'total_count' => $totalCount,
            'total_value' => $totalValue,

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
                'categories'=>$months,
                'series'=>$seriesMonthly,
            ],
            'monthly_value_status_with_target' => $monthlyValueWithTarget,

            'region_scope'   => $isAdmin ? 'ALL' : 'LOCKED',
            'effective_area' => $effectiveArea,
            'user_region'    => $user->region ?? null,
            'user_roles'     => $user->getRoleNames() ?? [],

            // NEW badge numbers
            'value_conversion' => [
                'quote_value_sum'  => (float) $totalValue,
                'po_value_sum'     => (float) $poValueSum,
                'value_pct'        => round($valueConvPct, 2),
            ],
            'conversion_totals' => [
                'total_inquiries'        => (int) $totalInquiries,
                'projects_with_po'       => (int) $projectsWithPo,
                'total_quote_value'      => (float) $totalQuoteValue,
                'total_po_value_matched' => (float) $totalPoValueMatched,
                'value_conversion_pct'   => round($valueConversionPct, 2),
                'count_conversion_pct'   => round($countConversionPct, 2),
            ],
        ]);
    }







    /**
     * Single project for the modal.
     * GET /api/inquiries/{id}
     */
    public function show($id)
    {
        $p = Project::query()->findOrFail($id);

        return response()->json([
            'id'             => $p->id,
            'name'           => $p->name,
            'client'         => $p->client,
            'location'       => $p->location,
            'area'           => $p->area,
            'price'          => (float) ($p->quotation_value ?? $p->price ?? 0),
            'currency'       => 'SAR',
            'status'         => $p->status,
            'comments'       => $p->remark,
            'checklist'      => (object) [],
            'quotation_no'   => $p->quotation_no,
            'quotation_date' => $p->quotation_date,
            'atai_products'  => $p->atai_products,
        ]);
    }

    /**
     * Total line (for the "Total (SAR)" box).
     * GET /api/totals?family=…&area=…&status=…&year=…
     */
    public function totals(Request $r)
    {
        [$base] = $this->baseQuery($r);

        if ($r->filled('status')) {
            $base->where('status', $r->query('status'));
        }

        $sum = (float) (clone $base)
            ->selectRaw('SUM(COALESCE(quotation_value, price, 0)) as sum_price')
            ->value('sum_price');

        $cnt = (clone $base)->count();

        return response()->json([
            'count'     => (int) $cnt,
            'sum_price' => (float) $sum,
        ]);
    }

    /* ============================ Helpers ============================ */

    /**
     * Build the base query with RBAC region scoping + filters.
     * Returns: [$qb]
     */
    private function baseQuery(Request $r): array
    {
        // Try helper first (if you have middleware populating it)
        $effectiveRegion = \App\Support\RegionScope::apply($r); // may be null

        // Fallback from logged-in user (bullet-proof)
        $u = $r->user();
        if (!$effectiveRegion && $u) {
            $isManagerial = method_exists($u, 'hasAnyRole')
                ? $u->hasAnyRole(['gm','admin','manager'])
                : false;

            if (! $isManagerial && !empty($u->region)) {
                $effectiveRegion = $u->region;   // e.g. "Eastern"
            }
        }

        // Normalize for robust match
        $norm = fn($s) => strtolower(trim((string)$s));

        // Handle string quotation_date; fallback to created_at
        $dateExpr = "COALESCE(
        STR_TO_DATE(quotation_date, '%Y-%m-%d'),
        STR_TO_DATE(quotation_date, '%d-%m-%Y'),
        DATE(created_at)
    )";


        $qb = DB::table('projects');

        // Year filter
        if ($r->integer('year')) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$r->integer('year')]);
        }
        // ----- DATE FILTERS -----
        $dateFrom = $r->query('date_from');
        $dateTo   = $r->query('date_to');
        $year     = $r->integer('year');
        $month    = $r->integer('month'); // 1..12

        if ($dateFrom || $dateTo) {
            // inclusive between
            $from = $dateFrom ?: '1900-01-01';
            $to   = $dateTo   ?: '2999-12-31';
            $qb->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
        } elseif ($month) {
            // month (optionally with year)
            $yyyy = $year ?: date('Y');
            // first and last day of that month
            $start = sprintf('%04d-%02d-01', $yyyy, $month);
            // LAST_DAY() keeps MySQL doing the month-end calc
            $qb->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$start, $start]);
        } elseif ($year) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$year]);
        }
        // Family filter
        if ($r->filled('family') && strtolower($r->query('family')) !== 'all') {
            $this->applyFamilyFilterQB($qb, strtolower($r->query('family')));
        }

        // 🔐 REGION ENFORCEMENT (mirror DataTable)
        if (!empty($effectiveRegion)) {
            // Sales: force own area; ignore ?area
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($effectiveRegion)]);
        } elseif ($r->filled('area')) {
            // GM/Admin: can narrow with ?area
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($r->query('area'))]);
        }

        // Expose for Network tab sanity check
        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return [$qb];
    }

    /**
     * Family → WHERE mapping for Query Builder (case-insensitive).
     */
    private function applyFamilyFilterQB($qb, string $family): void
    {
        $fam = trim($family);
        $qb->where(function ($qq) use ($fam) {
            if ($fam === 'ductwork') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
            } elseif ($fam === 'dampers') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
            } elseif (in_array($fam, ['sound_attenuators','attenuators','attenuator'], true)) {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
            } elseif ($fam === 'accessories') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
            } else {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%'.$fam.'%']);
            }
        });
    }
}
