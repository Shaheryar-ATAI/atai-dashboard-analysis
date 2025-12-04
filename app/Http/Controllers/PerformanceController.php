<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
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
    private function productNormExprProjects(string $alias = 'p'): string
    {
        // ✅ correct column name from your screenshot
        $col = "{$alias}.atai_products";

        // use LOWER+TRIM so LIKE checks are reliable
        return "
        CASE
            WHEN $col IS NULL OR TRIM($col) = '' THEN 'Unspecified'

            -- Ductwork
            WHEN LOWER($col) LIKE '%duct%' THEN 'Ductwork'

            -- Dampers
            WHEN LOWER($col) LIKE '%damper%' THEN 'Dampers'

            -- Louvers
            WHEN LOWER($col) LIKE '%louver%' OR LOWER($col) LIKE '%louvre%' THEN 'Louvers'

            -- Sound attenuators / cross-talk attenuators
            WHEN LOWER($col) LIKE '%attenuator%' OR LOWER($col) LIKE '%cross talk%' THEN 'Sound Attenuators'

            -- Everything else → Accessories
            ELSE 'Accessories'
        END
    ";
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
        return "CAST(NULLIF(REPLACE(REPLACE(`PO Value`, ',', ''), ' ', ''), '') AS DECIMAL(18,2))";
    }

    // Normalized area for salesorderlog: prefer Region (normalized), else region
    private function poAreaExpr(string $alias = 's'): string
    {
        $norm = $this->qual($alias, 'region'); // -> `s`.`Region (normalized)`
        $raw = $this->qual($alias, 'region');               // -> `s`.`region`

        return "LOWER(TRIM(CONVERT(COALESCE(NULLIF($norm,''), $raw, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
    }
    /**
     * Build monthly pivot rows for area summary (for a given year & kind).
     * kind = 'inquiries' | 'pos'
     */
    private function buildAreaSummaryRows(int $year, string $kind): array
    {
        if ($kind === 'pos') {
            $table   = 'salesorderlog';
            $dateCol = 'date_rec';
            $value   = $this->poAmountExpr();      // numeric expression from helper
            $areaCol = $this->qid('region');       // `region`
        } else {
            $table   = 'projects';
            $dateCol = 'quotation_date';
            $value   = 'COALESCE(quotation_value,0)';
            $areaCol = 'area';
        }

        $rows = DB::table($table)
            ->selectRaw("COALESCE($areaCol, 'Not Mentioned') AS area")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=1  THEN $value ELSE 0 END) AS jan")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=2  THEN $value ELSE 0 END) AS feb")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=3  THEN $value ELSE 0 END) AS mar")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=4  THEN $value ELSE 0 END) AS apr")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=5  THEN $value ELSE 0 END) AS may")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=6  THEN $value ELSE 0 END) AS jun")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=7  THEN $value ELSE 0 END) AS jul")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=8  THEN $value ELSE 0 END) AS aug")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=9  THEN $value ELSE 0 END) AS sep")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=10 THEN $value ELSE 0 END) AS oct")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=11 THEN $value ELSE 0 END) AS nov")
            ->selectRaw("SUM(CASE WHEN MONTH($dateCol)=12 THEN $value ELSE 0 END) AS december")
            ->selectRaw("SUM($value) AS total")
            ->whereYear($dateCol, $year)
            ->groupBy('area')
            ->orderBy('area', 'asc')
            ->get();

        // Convert to simple PHP array: area => [jan, ..., total]
        return $rows->mapWithKeys(function ($r) {
            return [
                $r->area => [
                    'jan'      => (float) $r->jan,
                    'feb'     => (float) $r->feb,
                    'mar'     => (float) $r->mar,
                    'apr'     => (float) $r->apr,
                    'may'     => (float) $r->may,
                    'jun'     => (float) $r->jun,
                    'jul'     => (float) $r->jul,
                    'aug'     => (float) $r->aug,
                    'sep'     => (float) $r->sep,
                    'oct'     => (float) $r->oct,
                    'nov'     => (float) $r->nov,
                    'december'=> (float) $r->december,
                    'total'   => (float) $r->total,
                ],
            ];
        })->toArray();
    }

    /**
     * Load all data needed for the Area Summary PDF:
     * - top KPIs
     * - inquiries by area
     * - POs by area
     */
    private function loadAreaSummaryData(int $year): array
    {
        // KPIs (totals)
        $inquiriesTotal = (float) DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) s')
            ->value('s');

        $posTotal = (float) DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') s')
            ->value('s');

        $gapValue    = $inquiriesTotal - $posTotal;
        $gapPercent  = $inquiriesTotal > 0
            ? round(($posTotal / $inquiriesTotal) * 100, 1)
            : null;

        $kpis = [
            'inquiries_total' => $inquiriesTotal,
            'pos_total'       => $posTotal,
            'gap_value'       => $gapValue,
            'gap_percent'     => $gapPercent,
        ];

        // Detailed tables
        $inquiriesByArea = $this->buildAreaSummaryRows($year, 'inquiries');
        $posByArea       = $this->buildAreaSummaryRows($year, 'pos');

        return [$kpis, $inquiriesByArea, $posByArea];
    }

    /* -----------------------------------------------------------
     * Pages
     * --------------------------------------------------------- */

    /** Performance landing page */
    public function index()
    {
        $year = (int)request('year', now()->year);

        // Inquiries total
        $quotationTotal = (float)DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) s')->value('s');

        // POs total (cast text → DECIMAL)
        $poTotal = (float)DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') s')->value('s');

        // By Area (projects vs POs)
        $areaExprProj = "LOWER(TRIM(CONVERT(COALESCE(p.area, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
        $areaExprPO = $this->poAreaExpr('s');

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
        $year = (int)($request->get('year') ?: now()->year);

        $quotationTotal = (float)DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) s')->value('s');

        $poTotal = (float)DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') s')->value('s');

        $areaExprProj = "LOWER(TRIM(CONVERT(COALESCE(p.area, 'Not Mentioned') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
        $areaExprPO = $this->poAreaExpr('s');

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
        $year = (int)$request->get('year', now()->year);
        $kind = $request->get('kind', 'inquiries');

        if ($kind === 'pos') {
            $table = 'salesorderlog';
            $date = 'date_rec';
            $value = $this->poAmountExpr();     // numeric expression
            $areaCol = $this->qid('region');      // `region`
        } else {
            $table = 'projects';
            $date = 'quotation_date';
            $value = 'COALESCE(quotation_value,0)';
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

        $badgeTotal = (float)DB::table($table)
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
        $year = (int)$request->query('year', now()->year);
        $month = (int)$request->query('month', now()->month);

        // Inquiries (projects)
        $inqMonth = DB::table('projects')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('COALESCE(SUM(quotation_value),0) AS actual')
            ->whereYear('quotation_date', $year)
            ->whereMonth('quotation_date', $month)
            ->groupBy('bucket')->pluck('actual', 'bucket');

        $inqYtd = DB::table('projects')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('COALESCE(SUM(quotation_value),0) AS actual')
            ->whereYear('quotation_date', $year)
            ->whereMonth('quotation_date', '<=', $month)
            ->groupBy('bucket')->pluck('actual', 'bucket');

        // POs (salesorderlog)
        $bucketExpr = $this->isSaudiAreaExpr('region'); // pass plain name; helper will quote
        $amount = $this->poAmountExpr();

        $poMonth = DB::table('salesorderlog')
            ->selectRaw("$bucketExpr AS bucket")
            ->selectRaw("COALESCE(SUM($amount),0) AS actual")
            ->whereYear('date_rec', $year)
            ->whereMonth('date_rec', $month)
            ->groupBy('bucket')->pluck('actual', 'bucket');

        $poYtd = DB::table('salesorderlog')
            ->selectRaw("$bucketExpr AS bucket")
            ->selectRaw("COALESCE(SUM($amount),0) AS actual")
            ->whereYear('date_rec', $year)
            ->whereMonth('date_rec', '<=', $month)
            ->groupBy('bucket')->pluck('actual', 'bucket');

        // Budgets (area_targets)
        $targets = DB::table('area_targets')
            ->select('metric')
            ->selectRaw($this->isSaudiAreaExpr('area') . ' AS bucket')
            ->selectRaw('SUM(CASE WHEN month = ? THEN amount ELSE 0 END) AS month_budget', [$month])
            ->selectRaw('SUM(CASE WHEN month <= ? THEN amount ELSE 0 END) AS ytd_budget', [$month])
            ->where('year', $year)
            ->whereIn('metric', ['inquiries', 'pos'])
            ->groupBy('metric', 'bucket')
            ->get()->groupBy(fn($r) => $r->metric);

        $pack = function (float $actual, float $budget): array {
            $variance = $actual - $budget;
            $percent = $budget > 0 ? round(($actual / $budget) * 100, 2) : null;
            return ['actual' => $actual, 'budget' => $budget, 'variance' => $variance, 'percent' => $percent];
        };

        $buckets = ['Saudi Arabia', 'Export'];
        $response = [
            'year' => $year,
            'month' => $month,
            'inquiries' => ['month' => [], 'ytd' => [], 'month_total' => null, 'ytd_total' => null],
            'pos' => ['month' => [], 'ytd' => [], 'month_total' => null, 'ytd_total' => null],
        ];

        foreach (['inquiries', 'pos'] as $metric) {
            $rows = $targets->get($metric, collect());
            $monthBudgetByBucket = $rows->pluck('month_budget', 'bucket');
            $ytdBudgetByBucket = $rows->pluck('ytd_budget', 'bucket');

            $monthActuals = $metric === 'inquiries' ? $inqMonth : $poMonth;
            $ytdActuals = $metric === 'inquiries' ? $inqYtd : $poYtd;

            $mtA = $mtB = $ytA = $ytB = 0;
            foreach ($buckets as $b) {
                $ma = (float)($monthActuals[$b] ?? 0);
                $ya = (float)($ytdActuals[$b] ?? 0);
                $mb = (float)($monthBudgetByBucket[$b] ?? 0);
                $yb = (float)($ytdBudgetByBucket[$b] ?? 0);

                $response[$metric]['month'][$b] = $pack($ma, $mb);
                $response[$metric]['ytd'][$b] = $pack($ya, $yb);

                $mtA += $ma;
                $mtB += $mb;
                $ytA += $ya;
                $ytB += $yb;
            }
            $response[$metric]['month_total'] = $pack($mtA, $mtB);
            $response[$metric]['ytd_total'] = $pack($ytA, $ytB);
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
        $year = (int) $request->get('year', now()->year);

        if ($kind === 'po') {
            // ---------- POs from salesorderlog ----------
            $amount = $this->poAmountExpr(); // uses `PO Value`

            $rows = DB::table('salesorderlog as s')
                ->selectRaw("
                COALESCE(NULLIF(TRIM(s.Products),''),'Unspecified') AS product,
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
            // ---------- Inquiries from PROJECTS (using atai_products) ----------
            $prodExpr = $this->productNormExprProjects('p');

            $rows = DB::table('projects as p')
                ->selectRaw("
                $prodExpr AS product,
                SUM(CASE WHEN MONTH(p.quotation_date)=1  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS jan,
                SUM(CASE WHEN MONTH(p.quotation_date)=2  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS feb,
                SUM(CASE WHEN MONTH(p.quotation_date)=3  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS mar,
                SUM(CASE WHEN MONTH(p.quotation_date)=4  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS apr,
                SUM(CASE WHEN MONTH(p.quotation_date)=5  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS may,
                SUM(CASE WHEN MONTH(p.quotation_date)=6  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS jun,
                SUM(CASE WHEN MONTH(p.quotation_date)=7  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS jul,
                SUM(CASE WHEN MONTH(p.quotation_date)=8  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS aug,
                SUM(CASE WHEN MONTH(p.quotation_date)=9  THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS sep,
                SUM(CASE WHEN MONTH(p.quotation_date)=10 THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS oct,
                SUM(CASE WHEN MONTH(p.quotation_date)=11 THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS nov,
                SUM(CASE WHEN MONTH(p.quotation_date)=12 THEN COALESCE(p.quotation_value,0) ELSE 0 END) AS december,
                SUM(COALESCE(p.quotation_value,0)) AS total
            ")
                ->whereYear('p.quotation_date', $year)
                ->groupBy('product')
                ->orderByDesc('total')
                ->get();
        }

        // ----- DataTables plumbing -----
        $draw   = (int) $request->get('draw', 1);
        $search = trim((string) ($request->input('search.value') ?? ''));

        $data = $rows->toArray();

        if ($search !== '') {
            $data = array_values(array_filter(
                $data,
                fn ($r) => stripos($r->product ?? 'Unspecified', $search) !== false
            ));
        }

        $recordsTotal    = count($rows);
        $recordsFiltered = count($data);
        $start           = (int) $request->get('start', 0);
        $length          = (int) $request->get('length', 25);
        $page            = array_slice($data, $start, $length);
        $sum_total       = array_reduce($data, fn ($c, $r) => $c + (float) $r->total, 0.0);

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $page,
            'sum_total'       => $sum_total,
        ]);
    }



    /** KPIs for products page */
    public function productsKpis(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $prodExpr = $this->productNormExprProjects('p');

        // Inquiries from projects
        $inq = DB::table('projects as p')
            ->selectRaw("$prodExpr AS product, SUM(COALESCE(p.quotation_value,0)) AS val")
            ->whereYear('p.quotation_date', $year)
            ->groupBy('product')
            ->orderByDesc('val')
            ->get();

        // POs from salesorderlog
        $amount = $this->poAmountExpr();
        $po = DB::table('salesorderlog as s')
            ->selectRaw("
            COALESCE(NULLIF(TRIM(s.Products),''),'Unspecified') AS product,
            SUM($amount) AS val
        ")
            ->whereYear('s.date_rec', $year)
            ->groupBy('product')
            ->orderByDesc('val')
            ->get();

        $cats = array_values(array_unique(array_merge(
            $inq->pluck('product')->take(12)->toArray(),
            $po->pluck('product')->take(12)->toArray()
        )));
        $cats = array_slice($cats, 0, 12);

        $mapInq = $inq->pluck('val', 'product');
        $mapPo  = $po->pluck('val', 'product');

        $inquiries = array_map(fn ($p) => (float) ($mapInq[$p] ?? 0), $cats);
        $pos       = array_map(fn ($p) => (float) ($mapPo[$p] ?? 0), $cats);

        return response()->json([
            'categories'    => $cats,
            'inquiries'     => $inquiries,
            'pos'           => $pos,
            'sum_inquiries' => array_sum($inquiries),
            'sum_pos'       => array_sum($pos),
        ]);
    }

    public function saveAreaChart(Request $request)
    {
        $year  = (int) $request->input('year', now()->year);
        $image = $request->input('image'); // data:image/png;base64,...

        if (!$image || !str_starts_with($image, 'data:image/png;base64,')) {
            return response()->json(['ok' => false, 'message' => 'Invalid image'], 422);
        }

        // strip prefix and decode
        $prefix = 'data:image/png;base64,';
        $base64 = substr($image, strlen($prefix));
        $binary = base64_decode($base64);

        if ($binary === false) {
            return response()->json(['ok' => false, 'message' => 'Decode failed'], 422);
        }

        $relativePath = "reports/area_chart_{$year}.png";

        Storage::disk('public')->put($relativePath, $binary);

        return response()->json([
            'ok'   => true,
            'path' => $relativePath,
        ]);
    }



    public function pdf(Request $request)
    {
        $year = (int) ($request->input('year') ?: now()->year);

        /* ============================
         * 1) TOP KPIs (totals + gap)
         * ============================ */

        // Total quotations (inquiries) from projects
        $quotationTotal = (float) DB::table('projects')
            ->whereYear('quotation_date', $year)
            ->selectRaw('SUM(COALESCE(quotation_value,0)) AS s')
            ->value('s');

        // Total POs from salesorderlog (using your money expression)
        $poTotal = (float) DB::table('salesorderlog')
            ->whereYear('date_rec', $year)
            ->selectRaw('SUM(' . $this->poAmountExpr() . ') AS s')
            ->value('s');

        // Coverage (POs vs quotations) and gap – same logic as dashboard
        $coveragePercent = $quotationTotal > 0
            ? round(($poTotal / $quotationTotal) * 100)
            : 0;

        $gapValue = abs($quotationTotal - $poTotal);

        $kpis = [
            'inquiries_total' => $quotationTotal,
            'pos_total'       => $poTotal,
            'gap_value'       => $gapValue,
            'gap_percent'     => $coveragePercent,
        ];

        /* ======================================
         * 2) MONTHLY BY AREA – INQUIRIES TABLE
         * ====================================== */

        $inqRows = DB::table('projects')
            ->selectRaw("COALESCE(area, 'Not Mentioned') AS area")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=1  THEN COALESCE(quotation_value,0) ELSE 0 END) AS jan")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=2  THEN COALESCE(quotation_value,0) ELSE 0 END) AS feb")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=3  THEN COALESCE(quotation_value,0) ELSE 0 END) AS mar")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=4  THEN COALESCE(quotation_value,0) ELSE 0 END) AS apr")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=5  THEN COALESCE(quotation_value,0) ELSE 0 END) AS may")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=6  THEN COALESCE(quotation_value,0) ELSE 0 END) AS jun")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=7  THEN COALESCE(quotation_value,0) ELSE 0 END) AS jul")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=8  THEN COALESCE(quotation_value,0) ELSE 0 END) AS aug")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=9  THEN COALESCE(quotation_value,0) ELSE 0 END) AS sep")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=10 THEN COALESCE(quotation_value,0) ELSE 0 END) AS oct")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=11 THEN COALESCE(quotation_value,0) ELSE 0 END) AS nov")
            ->selectRaw("SUM(CASE WHEN MONTH(quotation_date)=12 THEN COALESCE(quotation_value,0) ELSE 0 END) AS december")
            ->selectRaw("SUM(COALESCE(quotation_value,0)) AS total")
            ->whereYear('quotation_date', $year)
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        $inquiriesByArea = [];
        foreach ($inqRows as $r) {
            $inquiriesByArea[$r->area] = [
                (float) $r->jan,
                (float) $r->feb,
                (float) $r->mar,
                (float) $r->apr,
                (float) $r->may,
                (float) $r->jun,
                (float) $r->jul,
                (float) $r->aug,
                (float) $r->sep,
                (float) $r->oct,
                (float) $r->nov,
                (float) $r->december,
                (float) $r->total,
            ];
        }

        /* ==================================
         * 3) MONTHLY BY AREA – POs TABLE
         * ================================== */

        $amountExpr = $this->poAmountExpr(); // uses `PO Value` etc

        $poRows = DB::table('salesorderlog')
            ->selectRaw("COALESCE(region, 'Not Mentioned') AS area")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=1  THEN $amountExpr ELSE 0 END) AS jan")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=2  THEN $amountExpr ELSE 0 END) AS feb")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=3  THEN $amountExpr ELSE 0 END) AS mar")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=4  THEN $amountExpr ELSE 0 END) AS apr")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=5  THEN $amountExpr ELSE 0 END) AS may")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=6  THEN $amountExpr ELSE 0 END) AS jun")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=7  THEN $amountExpr ELSE 0 END) AS jul")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=8  THEN $amountExpr ELSE 0 END) AS aug")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=9  THEN $amountExpr ELSE 0 END) AS sep")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=10 THEN $amountExpr ELSE 0 END) AS oct")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=11 THEN $amountExpr ELSE 0 END) AS nov")
            ->selectRaw("SUM(CASE WHEN MONTH(date_rec)=12 THEN $amountExpr ELSE 0 END) AS december")
            ->selectRaw("SUM($amountExpr) AS total")
            ->whereYear('date_rec', $year)
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        $posByArea = [];
        foreach ($poRows as $r) {
            $posByArea[$r->area] = [
                (float) $r->jan,
                (float) $r->feb,
                (float) $r->mar,
                (float) $r->apr,
                (float) $r->may,
                (float) $r->jun,
                (float) $r->jul,
                (float) $r->aug,
                (float) $r->sep,
                (float) $r->oct,
                (float) $r->nov,
                (float) $r->december,
                (float) $r->total,
            ];
        }

        /* ==================================
         * 4) CHART IMAGE FOR PDF  (FIXED)
         * ================================== */

        $today = now()->format('d-m-Y');

        $relative   = "reports/area_chart_{$year}.png";
        $publicPath = public_path("storage/{$relative}");

// DomPDF chroot is public/, so absolute public path works
        $chartImagePath = file_exists($publicPath) ? $publicPath : null;

        /* ==================================
         * 5) GENERATE PDF
         * ================================== */

        $pdf = Pdf::loadView('reports.area-summary-pdf', [
            'year'            => $year,
            'today'           => $today,
            'kpis'            => $kpis,
            'inquiriesByArea' => $inquiriesByArea,
            'posByArea'       => $posByArea,
            'chartImagePath'  => $chartImagePath,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("ATAI_Area_Summary_{$year}_{$today}.pdf");
    }



}
