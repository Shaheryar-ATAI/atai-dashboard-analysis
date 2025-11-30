<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Estimation dashboard controller
 *
 * Data model assumptions (projects table):
 * - status             : phase of inquiry ('Bidding', 'Estimate', etc.)
 * - quotation_value    : numeric (value in SAR)
 * - area               : region name (Eastern / Central / Western / ...)
 * - atai_products      : product/category name
 * - action1            : estimator name (e.g., Aamer / Haseeb / Jaffer)
 * - date_rec           : business date (nullable) — use this when present
 * - created_at         : fallback date when date_rec is null
 */
class EstimationController extends Controller
{
    /**
     * Render the Estimation page (Blade only, data is loaded via AJAX).
     */
    public function index()
    {
        return view('estimation.index');
    }

    /**
     * Return the list of distinct estimator names from projects.action1
     * (trimmed, non-empty), used to build the "Estimator" pills.
     */
    public function estimators()
    {
        $rows = DB::table('projects')
            ->select(DB::raw('TRIM(action1) as estimator'))
            ->whereNotNull('action1')
            ->whereRaw("TRIM(action1) <> ''")
            ->groupBy('action1')
            ->orderBy('action1')
            ->pluck('estimator');

        return response()->json($rows);
    }

    /**
     * Helper: returns the COALESCE(date_rec, created_at) expression for date filtering.
     */
    protected function dateExprRaw()
    {
        return DB::raw("COALESCE(p.date_rec, p.created_at)");
    }

// Plain SQL string (use inside selectRaw/whereRaw string templates)
    protected function dateExprSql(): string
    {
        return "COALESCE(p.date_rec, p.created_at)";
    }
    /**
     * Build the base query with all the current filters applied.
     *
     * Query-string filters supported:
     * - estimator : exact match on projects.action1 (trimmed)
     * - year      : YYYY (applied on COALESCE(date_rec, created_at))
     * - month     : 1..12 (only applied if year is present)
     * - from      : YYYY-MM-DD (inclusive lower bound on date)
     * - to        : YYYY-MM-DD (inclusive upper bound on date)
     *
     * NOTE: All metrics and tables MUST use this base so that filters are consistent everywhere.
     */
    protected function base(Request $request)
    {
        $dateCol   = $this->dateExprRaw();              // COALESCE(p.date_rec, p.created_at)
        $estimator = trim((string) $request->query('estimator', ''));
        $year      = $request->query('year');           // e.g., "2025" or ""
        $month     = $request->query('month');          // 1..12 or ""
        $from      = $request->query('from');           // YYYY-MM-DD or ""
        $to        = $request->query('to');             // YYYY-MM-DD or ""

        $q = DB::table('projects as p')
            ->whereRaw("
            CASE
              WHEN UPPER(TRIM(p.project_type)) IN ('BIDDING','OPEN','SUBMITTED','PENDING','QUOTE','QUOTED','RFQ','INQUIRY','ENQUIRY') THEN 'Bidding'
              WHEN UPPER(TRIM(p.project_type)) IN ('IN HAND','IN-HAND','INHAND','ACCEPTED','WON','ORDER','ORDER IN HAND','IH') THEN 'In-Hand'
              ELSE 'Other'
            END <> 'Other'
        ");

        if ($estimator !== '') {
            $q->where('p.action1', $estimator);
        }

        // Apply From/To range (independent)
        if (!empty($from)) {
            $q->whereDate($dateCol, '>=', $from);
        }
        if (!empty($to)) {
            $q->whereDate($dateCol, '<=', $to);
        }

        // Apply Year and Month independently
        if (!empty($year)) {
            $q->whereYear($dateCol, (int) $year);
        }
        if (!empty($month)) {
            $q->whereMonth($dateCol, (int) $month);
        }

        return $q;
    }


    /* --------------------------------------------------------------------
     | KPIs (cards & charts)
     |---------------------------------------------------------------------*/

    /**
     * KPIs & chart data for the dashboard.
     * All results respect the filters provided (via base()).
     *
     * Returns JSON:
     * - totals.value          : total SAR value (sum quotation_value)
     * - estimatorPie[]        : [{name: <estimator>, y: <sum_value>}]
     * - statusPie[]           : [{name: <status>, y: <count>}]
     * - regionSeries{categories[], data[]}  : region count series
     * - productSeries{categories[], data[]} : product count series (top 10)
     */
    public function kpis(Request $request)
    {
        // Base filtered query (estimator/year/month/from/to; statuses in estimation phase)
        $base    = $this->base($request);
        $metric  = strtolower((string) $request->query('metric', 'value'));
        $isCount = $metric === 'count';

        // Totals (both)
        $totRow = (clone $base)
            ->selectRaw('COALESCE(SUM(p.quotation_value), 0) AS value')
            ->selectRaw('COUNT(*) AS count')
            ->first();

        $estimator = trim((string)$request->query('estimator', ''));

        /* ------------------------ Month window ------------------------ */
        $dateColSql = "COALESCE(p.date_rec, p.created_at)";

        $y  = (int) ($request->query('year') ?: 0);
        $m  = (int) ($request->query('month') ?: 0);
        $df = $request->query('from');
        $dt = $request->query('to');

        if ($df || $dt) {
            $start = \Carbon\Carbon::parse($df ?: now()->startOfYear())->startOfMonth();
            $end   = \Carbon\Carbon::parse($dt ?: now()->endOfYear())->endOfMonth();
        } elseif ($m && $y) {
            $start = \Carbon\Carbon::create($y, $m, 1)->startOfMonth();
            $end   = (clone $start)->endOfMonth();
        } elseif ($y) {
            $start = \Carbon\Carbon::create($y, 1, 1)->startOfMonth();
            $end   = \Carbon\Carbon::create($y, 12, 1)->endOfMonth();
        } else {
            $start = now()->startOfYear();
            $end   = now()->endOfYear();
        }

        // Build YYYY-MM keys + pretty labels
        $months = [];
        $labels = [];
        for ($cur = $start->copy(); $cur <= $end; $cur->addMonth()) {
            $months[] = $cur->format('Y-m');
            $labels[] = $cur->format('M y'); // e.g., "Jan 25"
        }
        $ymIndex = array_flip($months);

        /* ---------------- Monthly by Area — VALUE ---------------- */
        $dataEasternVal = array_fill(0, count($months), 0.0);
        $dataCentralVal = array_fill(0, count($months), 0.0);
        $dataWesternVal = array_fill(0, count($months), 0.0);

        $monthlyValRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateColSql, '%Y-%m') AS ym")
            ->selectRaw("LOWER(TRIM(p.area)) AS area_l")
            ->selectRaw("COALESCE(SUM(p.quotation_value),0) AS val")
            ->whereRaw("$dateColSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area_l')
            ->orderBy('ym')
            ->get();

        foreach ($monthlyValRows as $r) {
            if (!isset($ymIndex[$r->ym])) continue;
            $i = $ymIndex[$r->ym];
            $v = (float)$r->val;
            switch ($r->area_l) {
                case 'eastern': $dataEasternVal[$i] = $v; break;
                case 'central': $dataCentralVal[$i] = $v; break;
                case 'western': $dataWesternVal[$i] = $v; break;
            }
        }

        /* ---------------- Monthly by Area — COUNT ---------------- */
        $dataEasternCnt = array_fill(0, count($months), 0);
        $dataCentralCnt = array_fill(0, count($months), 0);
        $dataWesternCnt = array_fill(0, count($months), 0);

        $monthlyCntRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateColSql, '%Y-%m') AS ym")
            ->selectRaw("LOWER(TRIM(p.area)) AS area_l")
            ->selectRaw("COUNT(*) AS cnt")
            ->whereRaw("$dateColSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area_l')
            ->orderBy('ym')
            ->get();

        foreach ($monthlyCntRows as $r) {
            if (!isset($ymIndex[$r->ym])) continue;
            $i = $ymIndex[$r->ym];
            $c = (int)$r->cnt;
            switch ($r->area_l) {
                case 'eastern': $dataEasternCnt[$i] = $c; break;
                case 'central': $dataCentralCnt[$i] = $c; break;
                case 'western': $dataWesternCnt[$i] = $c; break;
            }
        }

        $monthlyRegion = [
            'categories'   => $labels,
            // Preferred new keys (frontend auto-falls back to 'series' if missing)
            'series_value' => [
                ['name' => 'Eastern', 'type' => 'column', 'data' => $dataEasternVal, 'zIndex' => 1],
                ['name' => 'Central', 'type' => 'column', 'data' => $dataCentralVal, 'zIndex' => 1],
                ['name' => 'Western', 'type' => 'column', 'data' => $dataWesternVal, 'zIndex' => 1],
            ],
            'series_count' => [
                ['name' => 'Eastern', 'type' => 'column', 'data' => $dataEasternCnt, 'zIndex' => 1],
                ['name' => 'Central', 'type' => 'column', 'data' => $dataCentralCnt, 'zIndex' => 1],
                ['name' => 'Western', 'type' => 'column', 'data' => $dataWesternCnt, 'zIndex' => 1],
            ],
            // keep a legacy 'series' for older frontends (value)
            'series'       => [
                ['name' => 'Eastern', 'type' => 'column', 'data' => $dataEasternVal, 'zIndex' => 1],
                ['name' => 'Central', 'type' => 'column', 'data' => $dataCentralVal, 'zIndex' => 1],
                ['name' => 'Western', 'type' => 'column', 'data' => $dataWesternVal, 'zIndex' => 1],
            ],
            'totals' => [
                'value' => (float) ($totRow->value ?? 0),
                'count' => (int)   ($totRow->count ?? 0),
            ],
        ];

        /* ---------------- Region aggregation (value only; table uses both) ---------------- */
        $regionAgg = (clone $base)
            ->selectRaw('COALESCE(p.area,"Unknown") AS region')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->selectRaw('COUNT(*) AS cnt')
            ->groupBy('p.area')
            ->orderByDesc($isCount ? 'cnt' : 'val')
            ->get();

        /* ---------------- Product Top-10 (both arrays, order by selected metric) ---------------- */
        $productAgg = (clone $base)
            ->selectRaw('COALESCE(p.atai_products, "Unknown") AS product')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->selectRaw('COUNT(*) AS cnt')
            ->groupBy('p.atai_products')
            ->orderByDesc($isCount ? 'cnt' : 'val')
            ->limit(10)
            ->get();

        $productSeries = [
            'categories' => $productAgg->pluck('product'),
            'value'      => $productAgg->pluck('val')->map(fn($v)=>(float)$v)->values(),
            'count'      => $productAgg->pluck('cnt')->map(fn($v)=>(int)$v)->values(),
            // legacy fallback field for older frontends
            'values'     => $productAgg->pluck('val')->map(fn($v)=>(float)$v)->values(),
        ];

        // Shared payload core
        $common = [
            'totals'        => [
                'value' => (float) ($totRow->value ?? 0),
                'count' => (int)   ($totRow->count ?? 0),
            ],
            'monthlyRegion' => $monthlyRegion,
            // not used by the current frontend, but kept if needed elsewhere
            'regionSeries'  => [
                'categories' => $regionAgg->pluck('region'),
                'values'     => $regionAgg->pluck('val')->map(fn($v)=>(float)$v),
                'counts'     => $regionAgg->pluck('cnt')->map(fn($v)=>(int)$v),
            ],
            'productSeries' => $productSeries,
        ];

        /* -------------------- Mode-specific pies -------------------- */
        if ($estimator === '') {
            // ALL mode: share by estimator (value & count)
            $estimatorRows = (clone $base)
                ->selectRaw('COALESCE(p.action1,"Unknown") AS estimator')
                ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
                ->selectRaw('COUNT(*) AS cnt')
                ->groupBy('p.action1')
                ->orderByDesc($isCount ? 'cnt' : 'val')
                ->get();

            $estimatorPie = $estimatorRows->map(fn($r) => [
                'name'  => $r->estimator,
                'value' => (float) $r->val,
                'count' => (int)   $r->cnt,
            ])->values();

            return response()->json(array_merge($common, [
                'mode'         => 'all',
                'estimatorPie' => $estimatorPie,
            ]));
        }

        // SINGLE mode: Bidding vs In-Hand (use the SAME mapping as base() for consistency)
        $rows = (clone $base)
            ->selectRaw("
            CASE
              WHEN UPPER(TRIM(p.project_type)) IN ('BIDDING','OPEN','SUBMITTED','PENDING','QUOTE','QUOTED','RFQ','INQUIRY','ENQUIRY') THEN 'Bidding'
              WHEN UPPER(TRIM(p.project_type)) IN ('IN HAND','IN-HAND','INHAND','ACCEPTED','WON','ORDER','ORDER IN HAND','IH') THEN 'In-Hand'
              ELSE 'Other'
            END AS bucket
        ")
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $statusPie = [
            [
                'name'  => 'Bidding',
                'value' => (float) ($rows['Bidding']->val ?? 0),
                'count' => (int)   ($rows['Bidding']->cnt ?? 0),
            ],
            [
                'name'  => 'In-Hand',
                'value' => (float) ($rows['In-Hand']->val ?? 0),
                'count' => (int)   ($rows['In-Hand']->cnt ?? 0),
            ],
        ];

        return response()->json(array_merge($common, [
            'mode'      => 'single',
            'statusPie' => $statusPie,
        ]));
    }


    /* --------------------------------------------------------------------
     | DataTables (server-side)
     |---------------------------------------------------------------------*/

    /**
     * DataTable: All records list.
     * Columns match the JS in index.blade.php.
     */
    public function datatableAll(Request $request)
    {
        $q = $this->base($request)
            ->select([
                'p.id',
                'p.project_name',
                'p.client_name',
                'p.area',
                'p.atai_products',
                'p.quotation_value',
                'p.status',
                'p.action1 as estimator',
                'p.created_at',
            ]);

        return DataTables::of($q)->toJson();
    }

    /**
     * DataTable: Aggregated by region (area).
     * Returns region, count, and total value.
     */
    public function datatableRegion(Request $request)
    {
        $q = $this->base($request)
            ->select([
                DB::raw('COALESCE(p.area, "Unknown") as region'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(p.quotation_value), 0) as val'),
            ])
            ->groupBy('p.area')
            ->orderByDesc('cnt');

        return DataTables::of($q)->toJson();
    }

    /**
     * DataTable: Aggregated by product (atai_products).
     * Returns product, count, and total value.
     */
    public function datatableProduct(Request $request)
    {
        $q = $this->base($request)
            ->select([
                DB::raw('COALESCE(p.atai_products, "Unknown") as product'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(SUM(p.quotation_value), 0) as val'),
            ])
            ->groupBy('p.atai_products')
            ->orderByDesc('cnt');

        return DataTables::of($q)->toJson();
    }
}
