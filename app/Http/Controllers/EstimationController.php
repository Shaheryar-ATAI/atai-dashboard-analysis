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
        $dateCol = $this->dateExprRaw();  // <— keep Expression here

        $q = DB::table('projects as p')
            ->whereRaw("
            CASE
              WHEN UPPER(TRIM(p.project_type)) IN ('BIDDING','OPEN','SUBMITTED','PENDING','QUOTE','QUOTED','RFQ','INQUIRY','ENQUIRY') THEN 'Bidding'
              WHEN UPPER(TRIM(p.project_type)) IN ('IN HAND','IN-HAND','INHAND','ACCEPTED','WON','ORDER','ORDER IN HAND','IH') THEN 'In-Hand'
              ELSE 'Other'
            END <> 'Other'
        ");

        if ($estimator = trim((string) $request->query('estimator', ''))) {
            $q->where('p.action1', $estimator);
        }

        if ($year = $request->query('year')) {
            $q->whereYear($dateCol, (int) $year);
            if ($month = $request->query('month')) {
                $q->whereMonth($dateCol, (int) $month);
            }
        }

        if ($from = $request->query('from')) {
            $q->whereDate($dateCol, '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate($dateCol, '<=', $to);
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
        $base = $this->base($request);

        // Total value for whatever is selected right now
        $totals = (clone $base)
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS value')
            ->first();

        $estimator = trim((string)$request->query('estimator', ''));

        /* ------------------------ Month window ------------------------ */
        // We will use a *SQL string* for the date expression everywhere below
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
        $ymIndex = array_flip($months);  // '2025-01' => 0, ...

        /* ---------------- Monthly Value by Area (3 series) ---------------- */
        // Prepare arrays for each region (all zeros initially)
        $dataEastern = array_fill(0, count($months), 0.0);
        $dataCentral = array_fill(0, count($months), 0.0);
        $dataWestern = array_fill(0, count($months), 0.0);

        $monthlyRows = (clone $base)
            ->selectRaw("DATE_FORMAT($dateColSql, '%Y-%m') AS ym")
            ->selectRaw("LOWER(TRIM(p.area)) AS area_l")
            ->selectRaw("COALESCE(SUM(p.quotation_value),0) AS val")
            ->whereRaw("$dateColSql BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area_l')
            ->orderBy('ym')
            ->get();

        foreach ($monthlyRows as $r) {
            if (!isset($ymIndex[$r->ym])) continue;
            $i = $ymIndex[$r->ym];
            $v = (float)$r->val;

            switch ($r->area_l) {
                case 'eastern': $dataEastern[$i] = $v; break;
                case 'central': $dataCentral[$i] = $v; break;
                case 'western': $dataWestern[$i] = $v; break;
                // ignore other/unknown regions in this 3-bar view
            }
        }
        $totalsTrend = [];
        for ($i = 0; $i < count($months); $i++) {
            $totalsTrend[$i] = (float)$dataEastern[$i] + (float)$dataCentral[$i] + (float)$dataWestern[$i];
        }

        // Columns (bars) + per-region trends (splines)
        $monthlyRegion = [
            'categories' => $labels,   // ["Jan 25", "Feb 25", ...]
            'series' => [
                // --- Bars (keep legend visible) ---
                [
                    'name' => 'Eastern',
                    'type' => 'column',
                    'data' => $dataEastern,
                    'zIndex' => 1,
                ],
                [
                    'name' => 'Central',
                    'type' => 'column',
                    'data' => $dataCentral,
                    'zIndex' => 1,
                ],
                [
                    'name' => 'Western',
                    'type' => 'column',
                    'data' => $dataWestern,
                    'zIndex' => 1,
                ],

                // --- Splines (one per region), linked to the bar just before it ---
                // Eastern trend
                [
                    'type'         => 'spline',
                    'data'         => $dataEastern,
                    'linkedTo'     => ':previous',    // links to Eastern bar (legend stays single)
                    'showInLegend' => false,
                    'marker'       => ['enabled' => false],
                    'zIndex'       => 5,
                    // Optional: make the line stand out a bit
                    'lineWidth'    => 2,
                    'dashStyle'    => 'ShortDot',
                ],
                // Central trend
                [
                    'type'         => 'spline',
                    'data'         => $dataCentral,
                    'linkedTo'     => ':previous',
                    'showInLegend' => false,
                    'marker'       => ['enabled' => false],
                    'zIndex'       => 5,
                    'lineWidth'    => 2,
                    'dashStyle'    => 'ShortDot',
                ],
                // Western trend
                [
                    'type'         => 'spline',
                    'data'         => $dataWestern,
                    'linkedTo'     => ':previous',
                    'showInLegend' => false,
                    'marker'       => ['enabled' => false],
                    'zIndex'       => 5,
                    'lineWidth'    => 2,
                    'dashStyle'    => 'ShortDot',
                ],
            ],
        ];

        /* ---------------- Region/Product (value, not count) ---------------- */
        $regionRows = (clone $base)
            ->selectRaw('COALESCE(p.area,"Unknown") AS region')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->groupBy('p.area')
            ->orderByDesc('val')
            ->get();

        $productRows = (clone $base)
            ->selectRaw('COALESCE(p.atai_products,"Unknown") AS product')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->groupBy('p.atai_products')
            ->orderByDesc('val')
            ->limit(10)
            ->get();

        // Shared parts of response
        $common = [
            'totals'        => ['value' => (float) ($totals->value ?? 0)],
            'monthlyRegion' => $monthlyRegion,
            'regionSeries'  => [
                'categories' => $regionRows->pluck('region'),
                'values'     => $regionRows->pluck('val')->map(fn($v)=>(float)$v),
            ],
            'productSeries' => [
                'categories' => $productRows->pluck('product'),
                'values'     => $productRows->pluck('val')->map(fn($v)=>(float)$v),
            ],
        ];

        /* -------------------- Mode-specific (pie) -------------------- */
        if ($estimator === '') {
            // ALL mode: share by estimator (value)
            $estimatorRows = (clone $base)
                ->selectRaw('COALESCE(p.action1,"Unknown") AS estimator')
                ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
                ->groupBy('p.action1')
                ->orderByDesc('val')
                ->get();

            $estimatorPie = $estimatorRows->map(fn($r) => [
                'name' => $r->estimator,
                'y'    => (float) $r->val,
            ]);

            return response()->json(array_merge($common, [
                'mode'         => 'all',
                'estimatorPie' => $estimatorPie,
            ]));
        }

        // SINGLE mode: Bidding vs In-Hand for that estimator
        $rows = (clone $base)
            ->selectRaw("
            CASE
                WHEN UPPER(p.status) IN ('IN HAND','IN-HAND','INHAND') THEN 'In-Hand'
                ELSE 'Bidding'
            END AS status
        ")
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS val')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusPie = [
            ['name' => 'Bidding', 'y' => (int) ($rows['Bidding']->cnt ?? 0), 'value' => (float) ($rows['Bidding']->val ?? 0)],
            ['name' => 'In-Hand', 'y' => (int) ($rows['In-Hand']->cnt ?? 0), 'value' => (float) ($rows['In-Hand']->val ?? 0)],
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
