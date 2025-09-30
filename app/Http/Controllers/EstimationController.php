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
    protected function dateExpr()
    {
        // Use date_rec when present, otherwise created_at.
        return DB::raw("COALESCE(p.date_rec, p.created_at)");
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
        $dateCol = $this->dateExpr();

        $q = DB::table('projects as p')
            ->whereIn('p.status', ['Bidding', 'inhand']); // "estimation phase"

        // Estimator filter (action1)
        if ($estimator = trim((string) $request->query('estimator', ''))) {
            $q->where('p.action1', $estimator);
        }

        // Year / Month filters
        if ($year = $request->query('year')) {
            $q->whereYear($dateCol, (int) $year);

            // Month only makes sense with a year
            if ($month = $request->query('month')) {
                $q->whereMonth($dateCol, (int) $month);
            }
        }

        // Date range filters (can be used for week selections etc.)
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
    // Base filtered query (by estimator, year, month, from, to) and status in Estimation phase
    $base = $this->base($request);

    // Total value for whatever is selected right now
    $totals = (clone $base)
        ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS value')
        ->first();

    $estimator = trim($request->query('estimator', ''));

    if ($estimator === '') {
        // ---- ALL mode: show share by estimator (value)
        $estimatorRows = (clone $base)
            ->selectRaw('COALESCE(p.action1,"Unknown") AS estimator, COALESCE(SUM(p.quotation_value),0) AS val')
            ->groupBy('p.action1')
            ->orderByDesc('val')
            ->get();

        $estimatorPie = $estimatorRows->map(fn($r) => [
            'name' => $r->estimator,
            'y'    => (float) $r->val,
        ]);

        // Region & Product (counts)
        $regionRows = (clone $base)
            ->selectRaw('COALESCE(p.area,"Unknown") AS region, COUNT(*) AS cnt')
            ->groupBy('p.area')
            ->orderByDesc('cnt')
            ->get();

        $productRows = (clone $base)
            ->selectRaw('COALESCE(p.atai_products,"Unknown") AS product, COUNT(*) AS cnt')
            ->groupBy('p.atai_products')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return response()->json([
            'mode'          => 'all',
            'totals'        => ['value' => (float) ($totals->value ?? 0)],
            'estimatorPie'  => $estimatorPie,
            'regionSeries'  => [
                'categories' => $regionRows->pluck('region'),
                'data'       => $regionRows->pluck('cnt')->map(fn($v)=>(int)$v),
            ],
            'productSeries' => [
                'categories' => $productRows->pluck('product'),
                'data'       => $productRows->pluck('cnt')->map(fn($v)=>(int)$v),
            ],
        ]);
    }

    // ---- SINGLE mode: show status split (Bidding vs In-Hand) for that estimator
    $rows = (clone $base)
        ->selectRaw("
            CASE
                WHEN UPPER(p.status) IN ('IN HAND','IN-HAND','INHAND') THEN 'In-Hand'
                ELSE 'Bidding'
            END AS status,
            COUNT(*) AS cnt,
            COALESCE(SUM(p.quotation_value),0) AS val
        ")
        ->groupBy('status')
        ->get()
        ->keyBy('status');

    $biddingCnt = (int) ($rows['Bidding']->cnt ?? 0);
    $biddingVal = (float) ($rows['Bidding']->val ?? 0);

    $inhandCnt  = (int) ($rows['In-Hand']->cnt ?? 0);
    $inhandVal  = (float) ($rows['In-Hand']->val ?? 0);

    $statusPie = [
        ['name' => 'Bidding', 'y' => $biddingCnt, 'value' => $biddingVal],
        ['name' => 'In-Hand', 'y' => $inhandCnt,  'value' => $inhandVal],
    ];

    // Region & Product (counts) with same filters
    $regionRows = (clone $base)
        ->selectRaw('COALESCE(p.area,"Unknown") AS region, COUNT(*) AS cnt')
        ->groupBy('p.area')
        ->orderByDesc('cnt')
        ->get();

    $productRows = (clone $base)
        ->selectRaw('COALESCE(p.atai_products,"Unknown") AS product, COUNT(*) AS cnt')
        ->groupBy('p.atai_products')
        ->orderByDesc('cnt')
        ->limit(10)
        ->get();

    return response()->json([
        'mode'          => 'single',
        'totals'        => ['value' => (float) ($totals->value ?? 0)],
        'statusPie'     => $statusPie,
        'regionSeries'  => [
            'categories' => $regionRows->pluck('region'),
            'data'       => $regionRows->pluck('cnt')->map(fn($v)=>(int)$v),
        ],
        'productSeries' => [
            'categories' => $productRows->pluck('product'),
            'data'       => $productRows->pluck('cnt')->map(fn($v)=>(int)$v),
        ],
    ]);
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
