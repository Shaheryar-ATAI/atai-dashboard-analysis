<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PerformanceController extends Controller
{
    /* -----------------------------------------------------------
     * Helpers
     * --------------------------------------------------------- */

    // Quote a single identifier (no dots)
    private function qid(string $id): string
    {
        if (str_contains($id, '`')) return $id; // already quoted
        return '`' . str_replace('`', '``', $id) . '`';
    }

    // Quote a qualified identifier: `alias`.`column`
    private function qual(string $alias, string $column): string
    {
        return $this->qid($alias) . '.' . $this->qid($column);
    }

    // Bucket SA vs Export for any column
    private function isSaudiAreaExpr(string $col = 'area'): string
    {
        $c = $this->qid($col); // simple column name only
        return "CASE WHEN $c IN ('Central','Eastern','Western') THEN 'Saudi Arabia' ELSE 'Export' END";
    }

    // Cast text money → DECIMAL for correct math
    private function poAmountExpr(): string
    {
        return "CAST(NULLIF(REPLACE(REPLACE(`value_with_vat`, ',', ''), ' ', ''), '') AS DECIMAL(18,2))";
    }

    // Normalized area for salesorderlog: prefer Region (normalized), else region
    private function poAreaExpr(string $alias = 's'): string
    {
        $norm = $this->qual($alias, 'region'); // -> `s`.`Region (normalized)`
        $raw  = $this->qual($alias, 'region');               // -> `s`.`region`

        return "LOWER(TRIM(CONVERT(COALESCE(NULLIF($norm,''), $raw, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
    }

    /* -----------------------------------------------------------
     * Pages
     * --------------------------------------------------------- */

    /** Performance landing page */
    public function index()
    {
        $year = (int) request('year', now()->year);

        // Inquiries total
        $quotationTotal = (float) DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) s')->value('s');

        // POs total (cast text → DECIMAL)
        $poTotal = (float) DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') s')->value('s');

        // By Area (projects vs POs)
        $areaExprProj = "LOWER(TRIM(CONVERT(COALESCE(p.area, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
        $areaExprPO   = $this->poAreaExpr('s');

        $inqSub = DB::table('projects as p')
            ->selectRaw("$areaExprProj AS area_norm")
            ->selectRaw("COALESCE(p.area, 'Not Mentioned') AS area")
            ->selectRaw("SUM(COALESCE(p.quotation_value,0)) AS quotations")
            ->whereYear('p.quotation_date', $year)
            ->groupBy('area_norm', 'area');

        $poSub = DB::table('salesorderlog as s')
            ->selectRaw("$areaExprPO AS area_norm")
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') AS pos')
            ->whereYear('s.date_rec', $year)
            ->groupBy('area_norm');

        $byArea = DB::query()
            ->fromSub($inqSub, 'q')
            ->leftJoinSub($poSub, 's', 's.area_norm', '=', 'q.area_norm')
            ->selectRaw('q.area, q.quotations, COALESCE(s.pos,0) AS pos')
            ->orderBy('q.area')
            ->get();

        return view('performance.index', compact('year', 'quotationTotal', 'poTotal', 'byArea'));
    }

    /** Area summary page */
    public function area(Request $request)
    {
        $year = (int) ($request->get('year') ?: now()->year);

        $quotationTotal = (float) DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) s')->value('s');

        $poTotal = (float) DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') s')->value('s');

        $areaExprProj = "LOWER(TRIM(CONVERT(COALESCE(p.area, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
        $areaExprPO   = $this->poAreaExpr('s');

        $inqSub = DB::table('projects as p')
            ->selectRaw("$areaExprProj AS area_norm")
            ->selectRaw("COALESCE(p.area, 'Not Mentioned') AS area")
            ->selectRaw("SUM(COALESCE(p.quotation_value,0)) AS quotations")
            ->whereYear('p.quotation_date', $year)
            ->groupBy('area_norm', 'area');

        $poSub = DB::table('salesorderlog as s')
            ->selectRaw("$areaExprPO AS area_norm")
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') AS pos')
            ->whereYear('s.date_rec', $year)
            ->groupBy('area_norm');

        $byArea = DB::query()
            ->fromSub($inqSub, 'q')
            ->leftJoinSub($poSub, 's', 's.area_norm', '=', 'q.area_norm')
            ->selectRaw('q.area, q.quotations, COALESCE(s.pos,0) AS pos')
            ->orderBy('q.area')
            ->get();

        return view('performance.area', compact('year', 'quotationTotal', 'poTotal', 'byArea'));
    }

    /* -----------------------------------------------------------
     * Data endpoints
     * --------------------------------------------------------- */

    /** DataTables source for area pivots (inquiries & pos) */
    public function areaData(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $kind = $request->get('kind', 'inquiries');

        if ($kind === 'pos') {
            $table   = 'salesorderlog';
            $date    = 'date_rec';
            $value   = $this->poAmountExpr();     // numeric expression
            $areaCol = $this->qid('region');      // `region`
        } else {
            $table   = 'projects';
            $date    = 'quotation_date';
            $value   = 'COALESCE(quotation_value,0)';
            $areaCol = 'area';
        }

        $q = DB::table($table)
            ->selectRaw("COALESCE($areaCol, 'Not Mentioned') AS area")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=1  THEN $value ELSE 0 END) AS jan")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=2  THEN $value ELSE 0 END) AS feb")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=3  THEN $value ELSE 0 END) AS mar")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=4  THEN $value ELSE 0 END) AS apr")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=5  THEN $value ELSE 0 END) AS may")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=6  THEN $value ELSE 0 END) AS jun")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=7  THEN $value ELSE 0 END) AS jul")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=8  THEN $value ELSE 0 END) AS aug")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=9  THEN $value ELSE 0 END) AS sep")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=10 THEN $value ELSE 0 END) AS oct")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=11 THEN $value ELSE 0 END) AS nov")
            ->selectRaw("SUM(CASE WHEN MONTH($date)=12 THEN $value ELSE 0 END) AS december")
            ->selectRaw("SUM($value) AS total")
            ->whereYear($date, $year)
            ->groupBy('area')
            ->orderBy('area', 'asc');

        $badgeTotal = (float) DB::table($table)
            ->whereYear($date, $year)
            ->selectRaw("SUM($value) s")->value('s');

        return DataTables::of($q)
            ->with(['sum_total' => $badgeTotal])
            ->editColumn('area', function ($row) {
                $txt = strtoupper($row->area);
                $badge = 'secondary';
                if ($txt === 'CENTRAL') $badge = 'success';
                elseif ($txt === 'EASTERN') $badge = 'info';
                elseif ($txt === 'WESTERN') $badge = 'warning';
                return '<span class="badge text-bg-' . $badge . '">' . e($txt) . '</span>';
            })
            ->rawColumns(['area'])
            ->make(true);
    }

    /** KPI JSON for SA vs Export (month + YTD) */
    public function areaKpis(Request $request)
    {
        $year  = (int) $request->query('year',  now()->year);
        $month = (int) $request->query('month', now()->month);

        // Inquiries (projects)
        $inqMonth = DB::table('projects')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('COALESCE(SUM(quotation_value),0) AS actual')
            ->whereYear('quotation_date', $year)
            ->whereMonth('quotation_date', $month)
            ->groupBy('bucket')->pluck('actual','bucket');

        $inqYtd = DB::table('projects')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('COALESCE(SUM(quotation_value),0) AS actual')
            ->whereYear('quotation_date', $year)
            ->whereMonth('quotation_date', '<=', $month)
            ->groupBy('bucket')->pluck('actual','bucket');

        // POs (salesorderlog)
        $bucketExpr = $this->isSaudiAreaExpr('region'); // pass plain name; helper will quote
        $amount     = $this->poAmountExpr();

        $poMonth = DB::table('salesorderlog')
            ->selectRaw("$bucketExpr AS bucket")
            ->selectRaw("COALESCE(SUM($amount),0) AS actual")
            ->whereYear('date_rec', $year)
            ->whereMonth('date_rec', $month)
            ->groupBy('bucket')->pluck('actual','bucket');

        $poYtd = DB::table('salesorderlog')
            ->selectRaw("$bucketExpr AS bucket")
            ->selectRaw("COALESCE(SUM($amount),0) AS actual")
            ->whereYear('date_rec', $year)
            ->whereMonth('date_rec', '<=', $month)
            ->groupBy('bucket')->pluck('actual','bucket');

        // Budgets (area_targets)
        $targets = DB::table('area_targets')
            ->select('metric')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('SUM(CASE WHEN month = ? THEN amount ELSE 0 END) AS month_budget', [$month])
            ->selectRaw('SUM(CASE WHEN month <= ? THEN amount ELSE 0 END) AS ytd_budget', [$month])
            ->where('year', $year)
            ->whereIn('metric', ['inquiries','pos'])
            ->groupBy('metric','bucket')
            ->get()->groupBy(fn($r) => $r->metric);

        $pack = function(float $actual, float $budget): array {
            $variance = $actual - $budget;
            $percent  = $budget > 0 ? round(($actual / $budget) * 100, 2) : null;
            return ['actual'=>$actual, 'budget'=>$budget, 'variance'=>$variance, 'percent'=>$percent];
        };

        $buckets = ['Saudi Arabia','Export'];
        $response = [
            'year'  => $year,
            'month' => $month,
            'inquiries' => ['month' => [], 'ytd' => [], 'month_total'=>null, 'ytd_total'=>null],
            'pos'       => ['month' => [], 'ytd' => [], 'month_total'=>null, 'ytd_total'=>null],
        ];

        foreach (['inquiries','pos'] as $metric) {
            $rows = $targets->get($metric, collect());
            $monthBudgetByBucket = $rows->pluck('month_budget', 'bucket');
            $ytdBudgetByBucket   = $rows->pluck('ytd_budget', 'bucket');

            $monthActuals = $metric === 'inquiries' ? $inqMonth : $poMonth;
            $ytdActuals   = $metric === 'inquiries' ? $inqYtd   : $poYtd;

            $mtA=$mtB=$ytA=$ytB=0;
            foreach ($buckets as $b) {
                $ma = (float)($monthActuals[$b] ?? 0);
                $ya = (float)($ytdActuals[$b]   ?? 0);
                $mb = (float)($monthBudgetByBucket[$b] ?? 0);
                $yb = (float)($ytdBudgetByBucket[$b]   ?? 0);

                $response[$metric]['month'][$b] = $pack($ma,$mb);
                $response[$metric]['ytd'][$b]   = $pack($ya,$yb);

                $mtA += $ma; $mtB += $mb;
                $ytA += $ya; $ytB += $yb;
            }
            $response[$metric]['month_total'] = $pack($mtA,$mtB);
            $response[$metric]['ytd_total']   = $pack($ytA,$ytB);
        }

        return response()->json($response);
    }

    /** Products page */
    public function products(Request $request)
    {
        $year = (int)($request->query('year') ?? now()->year);
        return view('performance.products', ['year' => $year]);
    }

    /** DataTables source for Products summary (inq vs po) */
    public function productsData(Request $request)
    {
        $kind = $request->get('kind', 'inq');
        $year = (int)$request->get('year', now()->year);

        if ($kind === 'po') {
            // POs from salesorderlog (use DECIMAL cast)
            $amount = $this->poAmountExpr();

            $rows = DB::table('salesorderlog as s')
                ->selectRaw("
                    COALESCE(NULLIF(TRIM(s.products),''),'Unspecified') AS product,
                    SUM(CASE WHEN MONTH(s.date_rec)=1  THEN $amount ELSE 0 END) AS jan,
                    SUM(CASE WHEN MONTH(s.date_rec)=2  THEN $amount ELSE 0 END) AS feb,
                    SUM(CASE WHEN MONTH(s.date_rec)=3  THEN $amount ELSE 0 END) AS mar,
                    SUM(CASE WHEN MONTH(s.date_rec)=4  THEN $amount ELSE 0 END) AS apr,
                    SUM(CASE WHEN MONTH(s.date_rec)=5  THEN $amount ELSE 0 END) AS may,
                    SUM(CASE WHEN MONTH(s.date_rec)=6  THEN $amount ELSE 0 END) AS jun,
                    SUM(CASE WHEN MONTH(s.date_rec)=7  THEN $amount ELSE 0 END) AS jul,
                    SUM(CASE WHEN MONTH(s.date_rec)=8  THEN $amount ELSE 0 END) AS aug,
                    SUM(CASE WHEN MONTH(s.date_rec)=9  THEN $amount ELSE 0 END) AS sep,
                    SUM(CASE WHEN MONTH(s.date_rec)=10 THEN $amount ELSE 0 END) AS oct,
                    SUM(CASE WHEN MONTH(s.date_rec)=11 THEN $amount ELSE 0 END) AS nov,
                    SUM(CASE WHEN MONTH(s.date_rec)=12 THEN $amount ELSE 0 END) AS december,
                    SUM($amount) AS total
                ")
                ->whereYear('s.date_rec', $year)
                ->groupBy('product')
                ->orderByDesc('total')
                ->get();
        } else {
            // Inquiries from normalized projects
            $rows = DB::table('vw_projects_normalized as p')
                ->selectRaw("
                    product_norm as product,
                    SUM(CASE WHEN MONTH(quotation_date)=1  THEN quotation_value ELSE 0 END) AS jan,
                    SUM(CASE WHEN MONTH(quotation_date)=2  THEN quotation_value ELSE 0 END) AS feb,
                    SUM(CASE WHEN MONTH(quotation_date)=3  THEN quotation_value ELSE 0 END) AS mar,
                    SUM(CASE WHEN MONTH(quotation_date)=4  THEN quotation_value ELSE 0 END) AS apr,
                    SUM(CASE WHEN MONTH(quotation_date)=5  THEN quotation_value ELSE 0 END) AS may,
                    SUM(CASE WHEN MONTH(quotation_date)=6  THEN quotation_value ELSE 0 END) AS jun,
                    SUM(CASE WHEN MONTH(quotation_date)=7  THEN quotation_value ELSE 0 END) AS jul,
                    SUM(CASE WHEN MONTH(quotation_date)=8  THEN quotation_value ELSE 0 END) AS aug,
                    SUM(CASE WHEN MONTH(quotation_date)=9  THEN quotation_value ELSE 0 END) AS sep,
                    SUM(CASE WHEN MONTH(quotation_date)=10 THEN quotation_value ELSE 0 END) AS oct,
                    SUM(CASE WHEN MONTH(quotation_date)=11 THEN quotation_value ELSE 0 END) AS nov,
                    SUM(CASE WHEN MONTH(quotation_date)=12 THEN quotation_value ELSE 0 END) AS december,
                    SUM(quotation_value) AS total
                ")
                ->whereYear('quotation_date', $year)
                ->groupBy('product_norm')
                ->orderByDesc('total')
                ->get();
        }

        // DataTables plumbing
        $draw   = (int)$request->get('draw', 1);
        $search = trim((string)($request->input('search.value') ?? ''));
        $data   = $rows->toArray();

        if ($search !== '') {
            $data = array_values(array_filter($data, fn($r) =>
                stripos($r->product ?? 'Unspecified', $search) !== false
            ));
        }

        $recordsTotal    = count($rows);
        $recordsFiltered = count($data);
        $start  = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 25);
        $page   = array_slice($data, $start, $length);
        $sum_total = array_reduce($data, fn($c,$r)=>$c + (float)$r->total, 0.0);

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $page,
            'sum_total' => $sum_total,
        ]);
    }

    /** KPIs for products page */
    public function productsKpis(Request $request)
    {
        $year = (int)$request->query('year', now()->year);

        $inq = DB::table('vw_projects_normalized as p')
            ->selectRaw('product_norm as product, SUM(quotation_value) as val')
            ->whereYear('quotation_date', $year)
            ->groupBy('product_norm')
            ->orderByDesc('val')
            ->get();

        $amount = $this->poAmountExpr();
        $po  = DB::table('salesorderlog as s')
            ->selectRaw("COALESCE(NULLIF(TRIM(s.products),''),'Unspecified') AS product,
                         SUM($amount) AS val")
            ->whereYear('s.date_rec', $year)
            ->where('Status', 'Accepted')
            ->groupBy('product')
            ->orderByDesc('val')
            ->get();

        $cats = array_values(array_unique(array_merge(
            $inq->pluck('product')->take(12)->toArray(),
            $po->pluck('product')->take(12)->toArray()
        )));
        $cats = array_slice($cats, 0, 12);

        $mapInq = $inq->pluck('val','product');
        $mapPo  = $po->pluck('val','product');

        $inquiries = array_map(fn($p)=> (float)($mapInq[$p] ?? 0), $cats);
        $pos       = array_map(fn($p)=> (float)($mapPo[$p]  ?? 0), $cats);

        return response()->json([
            'categories'     => $cats,
            'inquiries'      => $inquiries,
            'pos'            => $pos,
            'sum_inquiries'  => array_sum($inquiries),
            'sum_pos'        => array_sum($pos),
        ]);
    }
}
