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
        $user = $req->user();
        $isAdmin = $user && $user->hasAnyRole(['gm','admin']);
        $effectiveArea = null;

        // Base query (Projects)
        $q = \App\Models\Project::query();

        // Non-GM/Admin → always force to their region (ignore query param)
        if (!$isAdmin) {
            $eff = trim((string)($user->region ?? ''));
            if ($eff !== '') {
                $q->where('area', $eff);
                $effectiveArea = $eff;
            }
        } else {
            // GM/Admin → optional ?area=Central/Eastern/Western (ignore 'ALL'/'')
            if ($req->filled('area') && strtoupper($req->input('area')) !== 'ALL') {
                $q->where('area', $req->input('area'));
                $effectiveArea = $req->input('area');
            }
        }
        // ---------- base query (Projects) ----------
        $q = \App\Models\Project::query();

        // Region scoping:
        // - GM/Admin: can see all or filter by ?area=
        // - Others: always locked to their own $user->region
        if ($user && !$user->hasAnyRole(['gm','admin'])) {
            if (!empty($user->region)) {
                $q->where('area', $user->region);
            }
        } else {
            if ($req->filled('area')) {
                $q->where('area', $req->input('area'));
            }
        }

        // ---------- date expression (use the same everywhere) ----------
        $dateExprSql = "COALESCE(quotation_date, date_rec, created_at)";

        // ---------- filters ----------
        $y  = $req->integer('year')   ?: null;
        $m  = $req->integer('month')  ?: null;
        $df = $req->input('date_from') ?: null;
        $dt = $req->input('date_to')   ?: null;

        if ($y)  $q->whereRaw("YEAR($dateExprSql)=?", [$y]);
        if ($m)  $q->whereRaw("MONTH($dateExprSql)=?", [$m]);
        if ($df) $q->whereRaw("$dateExprSql>=?", [$df]);
        if ($dt) $q->whereRaw("$dateExprSql<=?", [$dt]);

        if ($fam = (string) $req->input('family')) {
            $q->where('atai_products','like',"%{$fam}%");
        }

        $base = (clone $q);

        // ---------- status normalization ----------
        $statusCase = "
  CASE
    WHEN LOWER(TRIM(status)) IN ('in-hand','in hand','inhand','accepted','won','order','order in hand','ih') THEN 'In-Hand'
    WHEN LOWER(TRIM(status)) IN ('bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry') THEN 'Bidding'
    WHEN LOWER(TRIM(status)) IN ('lost','rejected','cancelled','canceled','closed lost','declined','not awarded') THEN 'Lost'
    ELSE 'Other'
  END
";

        // ---------- existing KPIs ----------
        $byStatus = (clone $base)
            ->selectRaw("$statusCase AS status_norm, SUM(COALESCE(quotation_value, price, 0)) AS sum_value")
            ->groupBy('status_norm')
            ->get();

        $rowsArea = (clone $base)->selectRaw("
    COALESCE(area,'—') AS area,

    /* counts */
    SUM(CASE WHEN ($statusCase)='In-Hand' THEN 1 ELSE 0 END) AS inhand_cnt,
    SUM(CASE WHEN ($statusCase)='Bidding' THEN 1 ELSE 0 END) AS bidding_cnt,
    SUM(CASE WHEN ($statusCase)='Lost'    THEN 1 ELSE 0 END) AS lost_cnt,

    /* values (SAR) */
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


        $seriesInhand  = [];
        $seriesBidding = [];
        $seriesLost    = [];

        foreach ($categoriesArea as $a) {
            $r = $mapArea->get($a);

            $inCnt = (int)   ($r->inhand_cnt  ?? 0);
            $bdCnt = (int)   ($r->bidding_cnt ?? 0);
            $lsCnt = (int)   ($r->lost_cnt    ?? 0);

            $inVal = (float) ($r->inhand_val  ?? 0);
            $bdVal = (float) ($r->bidding_val ?? 0);
            $lsVal = (float) ($r->lost_val    ?? 0);

            $seriesInhand[]  = ['y' => $inCnt, 'sar' => $inVal];
            $seriesBidding[] = ['y' => $bdCnt, 'sar' => $bdVal];
            $seriesLost[]    = ['y' => $lsCnt, 'sar' => $lsVal];
        }


        // ---------- month window ----------
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
        for ($c = $start->copy(); $c <= $end; $c->addMonth()) {
            $months[] = $c->format('Y-m');
        }

        // ---------- simple monthly area counts (kept) ----------
        $monthlyRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, COALESCE(area,'—') AS area, COUNT(*) AS cnt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area')
            ->orderBy('ym')
            ->get();

        $baseAreas     = ['Eastern','Central','Western'];
        $extraFromData = $monthlyRows->pluck('area')->unique()->diff($baseAreas)->values()->all();
        $areasAll      = array_values(array_unique(array_merge($baseAreas,$extraFromData)));

        $idxMonthly = [];
        foreach ($monthlyRows as $r) $idxMonthly[$r->ym][$r->area] = (int) $r->cnt;

        $seriesMonthly = [];
        foreach ($areasAll as $a) {
            $data = [];
            foreach ($months as $ym) $data[] = $idxMonthly[$ym][$a] ?? 0;
            $seriesMonthly[] = ['name'=>$a,'data'=>$data];
        }

        // ---------- grouped+stacked (counts) ----------
        $monthlyStatusRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, COALESCE(area,'—') AS area, ($statusCase) AS status_norm, COUNT(*) AS cnt")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area','status_norm')
            ->orderBy('ym')
            ->get();



        // ---------- Month × Area × Status (VALUE in SAR) ----------
        $monthlyStatusValueRows = (clone $base)
            ->selectRaw("
      DATE_FORMAT($dateExprSql,'%Y-%m') AS ym,
      COALESCE(area,'—')                AS area,
      ($statusCase)                     AS status_norm,
      SUM(COALESCE(quotation_value, price, 0)) AS amt
  ")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area','status_norm')
            ->orderBy('ym')
            ->get();

// Force exactly 3 area columns in this order (missing ones will appear as 0)
        $fixedAreas = ['Eastern','Central','Western'];

// Build series with legend = 3 statuses; each status has per-area linked series
        $statuses = ['In-Hand','Bidding','Lost'];
        $seriesValue = [];
        $masterIds = [];
        foreach ($statuses as $st) {
            $mid = "masterval-$st";
            $masterIds[$st] = $mid;
            // master carries legend; has no data
            $seriesValue[] = [
                'name'             => $st,
                'id'               => $mid,
                'type'             => 'column',
                'data'             => array_fill(0, count($months), 0),
                'showInLegend'     => true,
                'enableMouseTracking' => false,
            ];
        }

// index values
        $idxValArea = [];
        foreach ($monthlyStatusValueRows as $r) {
            $idxValArea[$r->ym][$r->area][$r->status_norm] = (float) $r->amt;
        }

// linked per-area series for each status; stack by AREA
        foreach ($fixedAreas as $areaName) {
            foreach ($statuses as $st) {
                $data = [];
                foreach ($months as $ym) {
                    $data[] = (float) ($idxValArea[$ym][$areaName][$st] ?? 0.0);
                }
                $seriesValue[] = [
                    'name'         => "{$areaName} – {$st}",
                    'type'         => 'column',
                    'stack'        => $areaName,            // ← groups columns by AREA
                    'linkedTo'     => $masterIds[$st],      // ← legend = 3 statuses
                    'showInLegend' => false,
                    'data'         => $data,
                ];
            }
        }

        $responseValuePayload = [
            'categories' => $months,     // ["2025-01", ...]
            'areas'      => $fixedAreas, // always Eastern, Central, Western
            'series'     => $seriesValue
        ];


        $statuses = ['In-Hand','Bidding','Lost'];
        // Build master series (own the legend) + linked per-area series
        $seriesGroupedStacked = [];
        $masterIds = [];
        foreach ($statuses as $st) {
            $mid = "master-$st";
            $masterIds[$st] = $mid;
            $seriesGroupedStacked[] = [
                'name'            => $st,
                'id'              => $mid,
                'type'            => 'column',
                'data'            => array_fill(0, count($months), 0), // dummies
                'showInLegend'    => true,
                'enableMouseTracking' => false,
                'pointPadding'    => 0.05,
                'groupPadding'    => 0.18,
                // OPTIONAL fixed colors per status (uncomment if you want fixed palette)
                // 'color'        => $st === 'In-Hand' ? '#4e79a7' : ($st === 'Bidding' ? '#f28e2b' : '#59a14f'),
            ];
        }

// Pre-allocate linked series: one per (area,status)
        $linked = [];
        foreach ($areasAll as $areaName) {
            foreach ($statuses as $st) {
                $key = "{$areaName} – {$st}";
                $linked[$key] = [
                    'name'         => $key,
                    'type'         => 'column',
                    'stack'        => $areaName,          // group by AREA
                    'linkedTo'     => $masterIds[$st],    // legend toggles by STATUS
                    'showInLegend' => false,              // hide per-area items
                    'data'         => array_fill(0, count($months), 0),
                    'pointPadding' => 0.05,
                    'groupPadding' => 0.18,
                ];
                // OPTIONAL: inherit color from master automatically (default Highcharts behavior)
            }
        }

        // Fill data
        foreach ($monthlyStatusRows as $r) {
            $ymIdx = array_search($r->ym, $months, true);
            if ($ymIdx === false) continue;
            $area = $r->area ?? '—';
            if (!in_array($area, $areasAll, true)) continue;
            if (!in_array($r->status_norm, $statuses, true)) continue;

            $key = "{$area} – {$r->status_norm}";
            if (isset($linked[$key])) {
                $linked[$key]['data'][$ymIdx] = (int) $r->cnt;
            }
        }

// Merge master + linked into the final series
        $seriesGroupedStacked = array_values(array_merge($seriesGroupedStacked, $linked));
        // =====================================================================
        // NEW: Monthly stacked VALUES by Status + Target Attainment % (line)
        // =====================================================================
        $target = (float) ($req->input('monthly_target', 20000000)); // 20M default$target = (float) ($req->input('monthly_target', 20000000)); // 20M default

        // Sum VALUE not count
        $valRows = (clone $base)
            ->selectRaw("
            DATE_FORMAT($dateExprSql,'%Y-%m') AS ym,
            ($statusCase) AS status_norm,
            SUM(COALESCE(quotation_value, price, 0)) AS amt
        ")
            ->whereRaw("$dateExprSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','status_norm')
            ->orderBy('ym')
            ->get();

        // index: [ym]['In-Hand'|'Bidding'|'Lost'] => amount
        $idxVal = [];
        foreach ($valRows as $r) {
            $idxVal[$r->ym][$r->status_norm] = (float) $r->amt;
        }

        $colInHand  = [];
        $colBidding = [];
        $colLost    = [];
        $linePct    = [];

        foreach ($months as $ym) {
            $ih = (float) ($idxVal[$ym]['In-Hand'] ?? 0);
            $bd = (float) ($idxVal[$ym]['Bidding'] ?? 0);
            $lt = (float) ($idxVal[$ym]['Lost'] ?? 0);

            $total = $ih + $bd + $lt;

            $colInHand[]  = $ih;
            $colBidding[] = $bd;
            $colLost[]    = $lt;

            // FIX: calculate correctly per month
            $linePct[] = $target > 0 ? round(($total / $target) * 100, 2) : 0.0;
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
            // Suggest dual y-axes in the front-end:
            // yAxis[0] = SAR (columns), yAxis[1] = % (line, max ~ 200)
        ];

        // ---------- totals ----------
        $totalCount = (clone $base)->count();
        $totalValue = (float) ((clone $base)->selectRaw("SUM(COALESCE(quotation_value,price,0)) AS t")->value('t') ?? 0);

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

            'monthly_area_status' => [
                'categories' => $months,
                'series'     => $seriesGroupedStacked,
            ],

            // NEW payload for your line+stacked column chart
            'monthly_value_status_with_target' => $monthlyValueWithTarget,
            'region_scope'   => $isAdmin ? 'ALL' : 'LOCKED',
            'effective_area' => $effectiveArea,   // null for ALL
            'user_region'    => $user->region ?? null,
            'user_roles'     => $user->getRoleNames() ?? [],
            'monthly_area_status_value' => $responseValuePayload,
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
