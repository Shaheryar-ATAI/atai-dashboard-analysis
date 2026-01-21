<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

use Carbon\Carbon;
class SalesmanPerformanceController extends Controller
{
    /**
     * Canonical salesman name  =>  list of accepted aliases (uppercased).
     */
    private array $salesmanAliasMap = [
        'SOHAIB' => ['SOHAIB', 'SOAHIB'],
        'TARIQ'  => ['TARIQ', 'TAREQ'],
        'JAMAL'  => ['JAMAL'],
        'ABDO'   => ['ABDO','ABDO YOUSEF'],
        'AHMED'  => ['AHMED'],
        'ABU MERHI'   => ['M.ABU MERHI','M.MERHI','MERHI','MOHAMMED','ABU MERHI','M. ABU MERHI'],
        'ATAI'  => ['AHMED','CLIENT','EXPORT','WASEEM','FAISAL','MAEN'],
    ];

    /**
     * Normalize any salesman string into a canonical display label.
     * - Null/empty → "NOT MENTIONED"
     * - Match alias → canonical key (SOHAIB, TARIQ, ...)
     * - Else → uppercase trimmed original.
     */
//    private function normalizeSalesman($name): string
//    {
//        $n = strtoupper(trim((string)$name));
//        $n = preg_replace('/\s+/', ' ', $n);
//
//        // common cleanup
//        $n = str_replace(['.', ',', '-', '_'], ' ', $n);
//        $n = preg_replace('/\s+/', ' ', $n);
//
//        // ✅ HARD ALIAS FIXES
//        if (preg_match('/^AHMED(\s+AMIN)?$/', $n)) return 'AHMED';
//        if (preg_match('/^TA(RI|RE)Q$/', $n)) return 'TARIQ';
//        if (preg_match('/^SOHAIB$/', $n)) return 'SOHAIB';
//        if (preg_match('/^JAMAL$/', $n)) return 'JAMAL';
//        if (preg_match('/^ABDO$/', $n)) return 'ABDO';
//
//        // fallback (keep cleaned value)
//        return $n ?: 'NOT MENTIONED';
//    }


    private array $monthAliases = [
        1  => 'jan',
        2  => 'feb',
        3  => 'mar',
        4  => 'apr',
        5  => 'may',
        6  => 'jun',
        7  => 'jul',
        8  => 'aug',
        9  => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'december',
    ];


    private array $regionMap = [
        'Eastern' => ['SOHAIB'],
        'Central' => ['TARIQ', 'JAMAL'],
        'Western' => ['ABDO', 'AHMED'],
    ];

    private function salesmanToRegion(string $canonSalesman): string
    {
        foreach ($this->regionMap as $region => $salesmen) {
            if (in_array($canonSalesman, $salesmen, true)) return $region;
        }
        return 'Other';
    }

    private function areaPasses(string $canonSalesman, string $area): bool
    {
        $area = ucfirst(strtolower(trim((string)$area)));
        if ($area === '' || $area === 'All') return true;

        return $this->salesmanToRegion($canonSalesman) === $area;
    }
    private function monthlySums(string $dateCol, string $valExpr): string
    {
        $parts = [];
        foreach ($this->monthAliases as $m => $a) {
            $parts[] = "SUM(CASE WHEN MONTH($dateCol)=$m THEN $valExpr ELSE 0 END) AS $a";
        }
        $parts[] = "SUM($valExpr) AS total";
        return implode(',', $parts);
    }

    private function norm(string $expr): string
    {
        return "LOWER(TRIM($expr))";
    }

    private function poAmountExpr(string $alias = 's'): string
    {
        // Properly quote the column with a space: `s`.`PO Value`
        $col = "`$alias`.`PO Value`";

        return "CAST(
                NULLIF(
                    REPLACE(REPLACE($col, ',', ''), ' ', ''),
                    ''
                ) AS DECIMAL(18,2)
            )";
    }

    public function index(Request $r)
    {
        $year = (int)($r->query('year') ?? now()->year);

        return view('performance.salesman', ['year' => $year]);
    }

    public function data(Request $r)
    {
        $kind = $r->query('kind', 'inq');
        $year = (int)$r->query('year', now()->year);
        $area = (string) $r->query('area', 'All');
        if ($kind === 'po') {
            // ---------- Sales Order Log ----------
            $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
            $amount    = $this->poAmountExpr('s');
            $monthly   = $this->monthlySums('s.date_rec', $amount);

            // base rows: one row per *raw* label
            $baseQuery = DB::table('salesorderlog as s')
                ->selectRaw("$labelExpr AS salesman,$monthly")
                ->whereYear('s.date_rec', $year)
                ->whereNull('s.deleted_at')
                ->whereRaw('`s`.`PO. No.` IS NOT NULL')
                ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
                ->whereRaw($amount . ' > 0')
                ->groupByRaw($labelExpr);

            $sum = (float) DB::table('salesorderlog as s')
                ->whereYear('s.date_rec', $year)
                ->whereNull('s.deleted_at')
                ->whereRaw('`s`.`PO. No.` IS NOT NULL')
                ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
                ->whereRaw($amount . ' > 0')
                ->selectRaw("SUM($amount) AS s")
                ->value('s');
        } else {
            // ---------- Projects / Inquiries ----------
            $labelExpr = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
            $val       = 'COALESCE(p.quotation_value,0)';
            $monthly   = $this->monthlySums('p.quotation_date', $val);

            $baseQuery = DB::table('projects as p')
                ->selectRaw("$labelExpr AS salesman,$monthly")
                ->whereYear('p.quotation_date', $year)
                ->groupByRaw($labelExpr);

            $sum = (float)DB::table('projects as p')
                ->whereYear('p.quotation_date', $year)
                ->selectRaw("SUM($val) AS s")
                ->value('s');
        }

        // (optional) loosen ONLY_FULL_GROUP_BY, safe to keep
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        // --------- PHP-side aggregation by alias ----------
        $rows   = $baseQuery->get();
        $agg    = [];
        $months = array_values($this->monthAliases);   // ['jan','feb',...,'december']

        foreach ($rows as $row) {
            $alias = $this->normalizeSalesman($row->salesman);
            // ✅ APPLY AREA FILTER HERE
            if (!$this->areaPasses($alias, $area)) {
                continue;
            }
            if (!isset($agg[$alias])) {
                // initialise row for this alias
                $agg[$alias] = (object)array_merge(
                    ['salesman' => $alias],
                    array_fill_keys($months, 0.0),
                    ['total' => 0.0]
                );
            }

            // accumulate month values + total
            foreach ($months as $m) {
                $agg[$alias]->$m += (float)$row->$m;
            }
            $agg[$alias]->total += (float)$row->total;
        }
        $sumFiltered = 0.0;
        foreach ($agg as $r) {
            $sumFiltered += (float)$r->total;
        }
        // convert to collection for Yajra
        $collection = collect(array_values($agg));

        return DataTables::of($collection)
            ->editColumn('salesman', fn($row) =>
                '<span class="badge text-bg-secondary">' . e($row->salesman) . '</span>'
            )
            ->rawColumns(['salesman'])
            ->with(['sum_total' => $sumFiltered])
            ->make(true);
    }

    public function kpis(Request $r)
    {
        $year = (int)$r->query('year', now()->year);
        $area = (string) $r->query('area', 'All');
        // disable strict grouping
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        // --- Inquiries ---
        $projLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $projNorm  = $this->norm($projLabel);

        $inq = DB::table('projects as p')
            ->selectRaw("$projNorm AS norm,$projLabel AS label,SUM(COALESCE(p.quotation_value,0)) AS total")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$projNorm,$projLabel")
            ->get();

        // --- POs (using PO Value, no Status filter) ---
        $poLabel = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $poNorm  = $this->norm($poLabel);
        $amt     = $this->poAmountExpr('s');

        $po = DB::table('salesorderlog as s')
            ->selectRaw("$poNorm AS norm,$poLabel AS label,SUM($amt) AS total")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amt . ' > 0')
            ->groupByRaw("$poNorm,$poLabel")
            ->get();

        // --- Merge by alias (SOHAIB, SOAHIB -> SOHAIB etc.) ---
        $inqAgg = [];
        $poAgg  = [];

        foreach ($inq as $row) {
            $label = $this->normalizeSalesman($row->label);

            if (!$this->areaPasses($label, $area)) continue;
            $inqAgg[$label] = ($inqAgg[$label] ?? 0) + (float)$row->total;
        }

        foreach ($po as $row) {
            $label = $this->normalizeSalesman($row->label);
            if (!$this->areaPasses($label, $area)) continue;
            $poAgg[$label] = ($poAgg[$label] ?? 0) + (float)$row->total;
        }

        // Build final categories and series in the same order
        $allLabels = array_keys($inqAgg + $poAgg);   // union of keys
        sort($allLabels, SORT_NATURAL);

        $categories = [];
        $inqSeries  = [];
        $poSeries   = [];

        foreach ($allLabels as $label) {
            $categories[] = $label;
            $inqSeries[]  = $inqAgg[$label] ?? 0;
            $poSeries[]   = $poAgg[$label] ?? 0;
        }

        return response()->json([
            'categories'    => $categories,
            'inquiries'     => $inqSeries,
            'pos'           => $poSeries,
            'sum_inquiries' => array_sum($inqSeries),
            'sum_pos'       => array_sum($poSeries),
        ]);
    }





    /* ============================================================
   NEW: Salesman -> Project Region -> Month pivot (POs)
   - Area filter applies to SALESMAN REGION (assigned regionMap)
   - But we still split the salesman's POs by project_region
============================================================ */

    private function normalizeProjectRegion(?string $val): string
    {
        $v = ucfirst(strtolower(trim((string)$val)));
        if (in_array($v, ['Eastern','Central','Western'], true)) return $v;
        return 'Other';
    }

    private function buildSalesmanRegionMatrixPOs(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        // ✅ salesman label (same as your PO pivots)
        $salesLabel = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";

        // ✅ project_region column (per your note)
        // If your actual column name differs, change it here only.
        $projRegionCol = "COALESCE(NULLIF(`s`.`project_region`,''),'')";

        $amtExpr = $this->poAmountExpr('s');

        $rows = DB::table('salesorderlog as s')
            ->selectRaw("$salesLabel AS salesman,
                    MONTH(s.date_rec) AS m,
                    $projRegionCol AS project_region,
                    SUM($amtExpr) AS v")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0')
            ->groupByRaw("$salesLabel, MONTH(s.date_rec), $projRegionCol")
            ->get();

        $regions = ['Eastern','Central','Western','Other'];
        $out = [];

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            // ✅ Area filter is based on salesman assigned region
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($s, $allowed, true)) continue;
            }

            $pr = $this->normalizeProjectRegion($r->project_region);

            if (!isset($out[$s])) {
                foreach ($regions as $rg) $out[$s][$rg] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $val = (float)$r->v;

            $out[$s][$pr][$idx] += $val;
            $out[$s][$pr][12]   += $val; // total
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }
    public function matrix(Request $r)
    {
        $year = (int)($r->query('year') ?? now()->year);
        $area = (string)($r->query('area') ?? 'All');
        $areaNorm = $this->normalizeArea($area);
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        $areaNorm = $this->normalizeArea($area);

        // Core pivots (filtered)
        $inquiriesBySalesman = $this->filterSalesmanPivotByArea(
            $this->buildSalesmanPivotInquiries($year),
            $areaNorm,
            $this->regionMap
        );

        $posBySalesman = $this->filterSalesmanPivotByArea(
            $this->buildSalesmanPivotPOs($year),
            $areaNorm,
            $this->regionMap
        );

        // Products
        $inqProductMatrix = $this->buildSalesmanProductMatrixInquiries($year, $areaNorm, $this->regionMap);
        $poProductMatrix  = $this->buildSalesmanProductMatrixPOs($year, $areaNorm, $this->regionMap);

        // Forecast + Targets
        $forecastBySalesman = $this->buildSalesmanPivotForecast($year, $areaNorm, $this->regionMap);
        $targetBySalesman = $this->buildSalesmanPivotTargets($year, $areaNorm, $this->regionMap);

        // Performance Matrix
        $salesmanKpiMatrix = $this->buildSalesmanKpiMatrix(
            $forecastBySalesman,
            $targetBySalesman,
            $inquiriesBySalesman,
            $posBySalesman
        );
        $poRegionMatrix = $this->buildSalesmanRegionMatrixPOs($year, $areaNorm, $this->regionMap);
        // Estimators + totals
        $inquiriesByEstimator = $this->buildEstimatorPivotInquiries($year, $areaNorm, $this->regionMap);

        $totalInquiriesByMonth   = $this->buildTotalInquiriesByMonth($inquiriesBySalesman);
        $totalInquiriesByProduct = $this->buildTotalInquiriesByProductFromSalesmanMatrix($inqProductMatrix);
        $estimatorProductMatrix = $this->buildEstimatorProductMatrixInquiries($year, $areaNorm, $this->regionMap);

        return response()->json([
            'year' => $year,
            'area' => $areaNorm,

            'inquiriesBySalesman' => $inquiriesBySalesman,
            'posBySalesman'       => $posBySalesman,

            'salesmanKpiMatrix' => $salesmanKpiMatrix,
            'inqProductMatrix'  => $inqProductMatrix,
            'poProductMatrix'   => $poProductMatrix,
            'inquiriesByEstimator' => $inquiriesByEstimator,
            'totalInquiriesByMonth' => $totalInquiriesByMonth,
            'totalInquiriesByProduct' => $totalInquiriesByProduct,
            'estimatorProductMatrix' => $estimatorProductMatrix,
            'poRegionMatrix' => $poRegionMatrix,
        ]);
    }









    /**
     * ✅ PDF Export: Salesman Summary
     * - One PDF
     * - Filters by area (Eastern/Central/Western/All)
     * - Includes region summary, product matrices, performance matrix, estimators, totals
     */
    public function pdf(Request $r)
    {
        $year  = (int)($r->query('year') ?? now()->year);
        $area  = (string)($r->query('area') ?? 'All'); // ✅ NEW
        $today = Carbon::now()->format('d-m-Y');
        $areaNorm = $this->normalizeArea($area);
        // Disable strict grouping (your existing approach)
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        // Region mapping (fixed salesmen mapping)
        $regionMap = [
            'Eastern' => ['SOHAIB'],
            'Central' => ['TARIQ', 'JAMAL'],
            'Western' => ['ABDO', 'AHMED'],
        ];

        // -------------------------------
        // 1) Core pivots (Salesman)
        // -------------------------------
        $inquiriesBySalesman = $this->buildSalesmanPivotInquiries($year);
        $posBySalesman       = $this->buildSalesmanPivotPOs($year);
        $poRegionMatrix = $this->buildSalesmanRegionMatrixPOs($year, $areaNorm, $this->regionMap);
        // ✅ Filter by selected area (PDF prints only selected region salesmen)
        $inquiriesBySalesman = $this->filterSalesmanPivotByArea($inquiriesBySalesman, $area, $regionMap);
        $posBySalesman       = $this->filterSalesmanPivotByArea($posBySalesman, $area, $regionMap);

        // -------------------------------
        // 2) Region pivots (derived from salesmen pivots)
        // -------------------------------
        $inqByRegion = $this->buildRegionPivotFromSalesmanPivot($inquiriesBySalesman, $regionMap);
        $poByRegion  = $this->buildRegionPivotFromSalesmanPivot($posBySalesman, $regionMap);

        // ✅ If area != All, keep only that region row in region tables
        $areaNorm = $this->normalizeArea($area);
        if ($areaNorm !== 'All') {
            $inqByRegion = array_intersect_key($inqByRegion, [$areaNorm => true]);
            $poByRegion  = array_intersect_key($poByRegion,  [$areaNorm => true]);
        }

        // -------------------------------
        // 3) KPI totals (based on filtered data)
        // -------------------------------
        $inqTotal = array_sum(array_map(fn($row) => (float) end($row), $inquiriesBySalesman));
        $poTotal  = array_sum(array_map(fn($row) => (float) end($row), $posBySalesman));

        $gapVal = $inqTotal - $poTotal;
        $gapPct = ($inqTotal > 0) ? round(($poTotal / $inqTotal) * 100, 1) : 0;

        // -------------------------------
        // 4) Product matrices (Salesman -> Product family -> months)
        // -------------------------------
        $inqProductMatrix = $this->buildSalesmanProductMatrixInquiries($year, $area, $regionMap);
        $poProductMatrix  = $this->buildSalesmanProductMatrixPOs($year, $area, $regionMap);

        // -------------------------------
        // 5) Forecast pivots (Salesman) from forecast table
        // -------------------------------
        $forecastBySalesman = $this->buildSalesmanPivotForecast($year, $area, $regionMap);

        // -------------------------------
        // 6) Targets (placeholder for now)
        // NOTE: If you already have targets table or logic, replace this.
        // -------------------------------
        $targetBySalesman = $this->buildSalesmanPivotTargets($year, $areaNorm, $regionMap);

        // -------------------------------
        // 7) Performance matrix (Forecast/Target/Inquiries/PO/Conversion)
        // Shape required by Blade: [salesman][FORECAST|TARGET|INQUIRIES|POS|CONV_PCT] => [13]
        // -------------------------------
        $salesmanKpiMatrix = $this->buildSalesmanKpiMatrix(
            $forecastBySalesman,
            $targetBySalesman,
            $inquiriesBySalesman,
            $posBySalesman
        );

        // -------------------------------
        // 8) Estimator pivots + totals (placeholders + safe arrays)
        // Replace estimator column name later when we finalize.
        // -------------------------------
        $inquiriesByEstimator    = $this->buildEstimatorPivotInquiries($year, $areaNorm, $regionMap);

        $totalInquiriesByMonth   = $this->buildTotalInquiriesByMonth($inquiriesBySalesman);
        $totalInquiriesByProduct = $this->buildTotalInquiriesByProductFromSalesmanMatrix($inqProductMatrix);
        $estimatorProductMatrix = $this->buildEstimatorProductMatrixInquiries($year, $areaNorm, $this->regionMap);
        $totalInquiriesByMonthByType = $this->buildTotalInquiriesByMonthByType($year, $areaNorm, $regionMap);

        // ✅ Build payload required by your Blade
        $payload = [
            'year' => $year,
            'area' => $areaNorm,
            'today' => $today,
            'kpis' => [
                'inquiries_total' => $inqTotal,
                'pos_total'       => $poTotal,
                'gap_value'       => $gapVal,
                'gap_percent'     => $gapPct,
            ],

            // existing tables
            'inquiriesBySalesman' => $inquiriesBySalesman,
            'posBySalesman'       => $posBySalesman,
            'inqByRegion'         => $inqByRegion,
            'poByRegion'          => $poByRegion,

            // new sections used by Blade
            'inqProductMatrix'    => $inqProductMatrix,
            'poProductMatrix'     => $poProductMatrix,
            'salesmanKpiMatrix'   => $salesmanKpiMatrix,

            // estimator + totals used by Blade
            'inquiriesByEstimator'   => $inquiriesByEstimator,
            'totalInquiriesByMonth'  => $totalInquiriesByMonth,
            'totalInquiriesByProduct'=> $totalInquiriesByProduct,
            'estimatorProductMatrix' => $estimatorProductMatrix,
            'totalInquiriesByMonthByType' => $totalInquiriesByMonthByType,
            'poRegionMatrix' => $poRegionMatrix,

        ];

        return Pdf::loadView('reports.salesman-summary', $payload)
            ->setPaper('a4', 'portrait')
            ->download("ATAI_Salesman_Summary_{$year}_{$areaNorm}.pdf");
    }

    /* ============================================================
       Helpers: normalize area + salesman + PO amount
    ============================================================ */

    private function normalizeArea(string $area): string
    {
        $a = ucfirst(strtolower(trim($area)));
        return in_array($a, ['Eastern','Central','Western'], true) ? $a : 'All';
    }

    /**
     * Your canonical normalization (keep same logic you already have in project).
     * Adjust aliases as needed.
     */
    private function normalizeSalesman(?string $name): string
    {
        $n = strtoupper(trim((string)$name));
        if ($n === '' || $n === 'NOT MENTIONED') return 'NOT MENTIONED';

        // alias normalization examples:
        if (str_contains($n, 'SOHAIB') || str_contains($n, 'SOAHIB')) return 'SOHAIB';
        if (str_contains($n, 'TARIQ') || str_contains($n, 'TAREQ')) return 'TARIQ';
        if (str_contains($n, 'JAMAL')) return 'JAMAL';
        if (str_contains($n, 'ABDO')) return 'ABDO';
        if (str_contains($n, 'AHMED')) return 'AHMED';

        return $n;
    }

    /**
     * PO amount expression (keep your existing implementation if you already have it).
     * This is a safe fallback: you should replace with your real one.
     */
//    private function poAmountExpr(string $alias = 's'): string
//    {
//        // Example column names used in your DB: `Total Amount` might differ
//        // Replace with your already working expression.
//        return "COALESCE(`{$alias}`.`Total Amount`,0)";
//    }

    /* ============================================================
       Core pivots: salesman inquiries / POs
    ============================================================ */

    private function buildSalesmanPivotInquiries(int $year): array
    {
        $labelExpr = "COALESCE(NULLIF(p.salesman,''), NULLIF(p.salesperson,''), 'Not Mentioned')";
        $valExpr   = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$labelExpr AS salesman, MONTH(p.quotation_date) AS m, SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$labelExpr, MONTH(p.quotation_date)")
            ->get();

        return $this->pivotNormalizeSalesmanRows($rows);
    }

    private function buildSalesmanPivotPOs(int $year): array
    {
        $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''), 'Not Mentioned')";
        $amtExpr   = $this->poAmountExpr('s');

        $rows = DB::table('salesorderlog as s')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, SUM($amtExpr) AS s")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0')
            ->groupByRaw("$labelExpr, MONTH(s.date_rec)")
            ->get();

        return $this->pivotNormalizeSalesmanRows($rows);
    }

    private function pivotNormalizeSalesmanRows($rows): array
    {
        $months = array_values($this->monthAliases); // jan..december
        $out = [];

        foreach ($rows as $r) {
            $canon = $this->normalizeSalesman($r->salesman);

            if (!isset($out[$canon])) {
                $out[$canon] = array_fill_keys($months, 0.0);
                $out[$canon]['total'] = 0.0;
            }

            $m = (int)$r->m;
            if ($m >= 1 && $m <= 12) {
                $key = $this->monthAliases[$m];
                $out[$canon][$key] += (float)$r->s;
            }
        }

        // compute totals and convert to numeric array [jan..dec,total]
        $final = [];
        ksort($out, SORT_NATURAL);

        foreach ($out as $salesman => $arr) {
            $total = 0.0;
            foreach ($months as $k) $total += (float)$arr[$k];

            $final[$salesman] = [];
            foreach ($months as $k) $final[$salesman][] = (float)$arr[$k];
            $final[$salesman][] = (float)$total;
        }

        return $final;
    }

    private function filterSalesmanPivotByArea(array $pivot, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);
        if ($areaNorm === 'All') return $pivot;

        $allowed = $regionMap[$areaNorm] ?? [];
        if (!$allowed) return [];

        return array_intersect_key($pivot, array_flip($allowed));
    }

    private function buildRegionPivotFromSalesmanPivot(array $pivot, array $regionMap): array
    {
        $out = [];
        foreach ($regionMap as $region => $salesmen) {
            $row = array_fill(0, 13, 0.0);
            foreach ($salesmen as $s) {
                if (!isset($pivot[$s])) continue;
                foreach ($pivot[$s] as $i => $val) $row[$i] += (float)$val;
            }
            $out[$region] = $row;
        }
        return $out;
    }

    /* ============================================================
       Product matrices (Salesman -> Product Family -> [13])
    ============================================================ */

    private function normalizeProductFamily(string $txt): ?string
    {
        $t = strtolower(trim($txt));
        if ($t === '') return null;

        if (str_contains($t, 'duct')) return 'DUCTWORK';
        if (str_contains($t, 'damper')) return 'DAMPERS';

        // ✅ Separate bucket for Louvers
        if (str_contains($t, 'louver')) return 'LOUVERS';

        if (str_contains($t, 'attenuat') || str_contains($t, 'sound')) return 'SOUND ATTENUATORS';

        // ✅ Accessories (exclude louver now)
        if (str_contains($t, 'access')
            || str_contains($t, 'flex')
            || str_contains($t, 'plenum')
            || str_contains($t, 'airstack')
            || str_contains($t, 'vav')
            || str_contains($t, 'cav')
            || str_contains($t, 'btus')
            || str_contains($t, 'heater')
        ) return 'ACCESSORIES';

        return null;
    }

    private function buildSalesmanProductMatrixInquiries(int $year, string $area, array $regionMap): array
    {
        $families = ['DUCTWORK','DAMPERS','LOUVERS','SOUND ATTENUATORS','ACCESSORIES'];

        $labelExpr = "COALESCE(NULLIF(p.salesman,''), NULLIF(p.salesperson,''), 'Not Mentioned')";
        $prodCol   = "COALESCE(NULLIF(p.atai_products,''),'')";
        $valExpr   = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$labelExpr AS salesman, MONTH(p.quotation_date) AS m, $prodCol AS prod, SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$labelExpr, MONTH(p.quotation_date), $prodCol")
            ->get();

        $out = [];
        $areaNorm = $this->normalizeArea($area);

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($s, $allowed, true)) continue;
            }

            $fam = $this->normalizeProductFamily((string)$r->prod);
            if (!$fam) continue;

            if (!isset($out[$s])) {
                foreach ($families as $f) $out[$s][$f] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$s][$fam][$idx] += (float)$r->s;
            $out[$s][$fam][12]   += (float)$r->s; // total
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    private function buildSalesmanProductMatrixPOs(int $year, string $area, array $regionMap): array
    {
        $families = ['DUCTWORK','DAMPERS','LOUVERS','SOUND ATTENUATORS','ACCESSORIES'];

        $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $prodCol   = "COALESCE(NULLIF(`s`.`Products`,''),'')";
        $amtExpr   = $this->poAmountExpr('s');

        $rows = DB::table('salesorderlog as s')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, $prodCol AS prod, SUM($amtExpr) AS s")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0')
            ->groupByRaw("$labelExpr, MONTH(s.date_rec), $prodCol")
            ->get();

        $out = [];
        $areaNorm = $this->normalizeArea($area);

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($s, $allowed, true)) continue;
            }

            $fam = $this->normalizeProductFamily((string)$r->prod);
            if (!$fam) continue;

            if (!isset($out[$s])) {
                foreach ($families as $f) $out[$s][$f] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$s][$fam][$idx] += (float)$r->s;
            $out[$s][$fam][12]   += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    /* ============================================================
       Forecast pivot (from forecast table)
       Columns you showed: value_sar, salesman, region, month_no/year
    ============================================================ */
    private function buildEstimatorProductMatrixInquiries(int $year, string $area, array $regionMap): array
    {
        $families = ['DUCTWORK','DAMPERS','LOUVERS','SOUND ATTENUATORS','ACCESSORIES'];
        $areaNorm = $this->normalizeArea($area);

        $estLabel   = "COALESCE(NULLIF(p.estimator_name,''),'Not Mentioned')";
        $salesLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $prodCol    = "COALESCE(NULLIF(p.atai_products,''),'')";
        $valExpr    = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$estLabel AS estimator,
                    $salesLabel AS salesman,
                    MONTH(p.quotation_date) AS m,
                    $prodCol AS prod,
                    SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$estLabel, $salesLabel, MONTH(p.quotation_date), $prodCol")
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $canonSalesman = $this->normalizeSalesman($r->salesman);

            // ✅ area filter (same as your other pivots)
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canonSalesman, $allowed, true)) continue;
            }

            $est = strtoupper(trim((string)$r->estimator));
            if ($est === '') $est = 'NOT MENTIONED';

            $fam = $this->normalizeProductFamily((string)$r->prod);
            if (!$fam) continue;

            if (!isset($out[$est])) {
                // init all families so PDF always prints consistent 5 rows
                foreach ($families as $f) $out[$est][$f] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$est][$fam][$idx] += (float)$r->s;
            $out[$est][$fam][12]   += (float)$r->s; // total
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    private function buildSalesmanPivotForecast(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        $rows = DB::table('forecast as f')
            ->selectRaw("COALESCE(NULLIF(f.salesman,''), NULLIF(f.sales_source,''), 'Not Mentioned') AS salesman,
                        COALESCE(NULLIF(f.month_no,0), MONTH(f.created_at)) AS m,
                        SUM(COALESCE(f.value_sar,0)) AS s,
                        COALESCE(NULLIF(f.region,''),'') AS region")
            ->where('f.year', $year)
            ->groupByRaw("COALESCE(NULLIF(f.salesman,''), NULLIF(f.sales_source,''), 'Not Mentioned'),
                         COALESCE(NULLIF(f.month_no,0), MONTH(f.created_at)),
                         COALESCE(NULLIF(f.region,''),'')")
            ->get();

        // pivot to [SALESMAN => [13]]
        $months = array_values($this->monthAliases);
        $out = [];

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            // Area filter: prefer forecast.region if present, else fallback to salesman mapping
            if ($areaNorm !== 'All') {
                $rgn = ucfirst(strtolower(trim((string)$r->region)));
                if ($rgn !== '' && $rgn !== $areaNorm) {
                    // region on forecast says different area
                    continue;
                }
                // if region blank, use salesman mapping
                if ($rgn === '') {
                    $allowed = $regionMap[$areaNorm] ?? [];
                    if (!in_array($s, $allowed, true)) continue;
                }
            }

            if (!isset($out[$s])) $out[$s] = array_fill(0, 13, 0.0);

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$s][$idx] += (float)$r->s;
            $out[$s][12]   += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    /* ============================================================
       Targets + Estimators (placeholders so PDF never breaks)
       We'll implement properly after PDF is approved.
    ============================================================ */

    private function buildSalesmanPivotTargets(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        // yearly targets (same as Blade)
        $yearlyTargets = [
            'Eastern' => 50000000,
            'Central' => 50000000,
            'Western' => 36000000,
        ];

        // salesman weights inside region
        // change weights if needed
        $weights = [
            'Eastern' => ['SOHAIB' => 1.0],
            'Central' => ['TARIQ' => 0.5, 'JAMAL' => 0.5],
            'Western' => ['ABDO' => 0.5, 'AHMED' => 0.5],
        ];

        $out = [];

        foreach ($weights as $region => $salesmenWeights) {
            if ($areaNorm !== 'All' && $areaNorm !== $region) continue;

            $regionTarget = (float)($yearlyTargets[$region] ?? 0);
            foreach ($salesmenWeights as $salesman => $w) {
                $annual = $regionTarget * (float)$w;
                $monthly = $annual / 12;

                $row = array_fill(0, 13, 0.0);
                for ($i=0; $i<12; $i++) $row[$i] = $monthly;
                $row[12] = $annual;

                $out[$salesman] = $row;
            }
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }


    private function buildEstimatorPivotInquiries(int $year, string $areaNorm, array $regionMap): array
    {
        // Normalize area like the rest of your controller
        $areaNorm = $this->normalizeArea($areaNorm);

        $estLabel = "COALESCE(NULLIF(p.estimator_name,''),'Not Mentioned')";
        $salesLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $valExpr = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$estLabel AS estimator,
                    $salesLabel AS salesman,
                    MONTH(p.quotation_date) AS m,
                    SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$estLabel, $salesLabel, MONTH(p.quotation_date)")
            ->get();

        $months = array_values($this->monthAliases); // jan..december
        $out = [];

        foreach ($rows as $r) {
            $canonSalesman = $this->normalizeSalesman($r->salesman);

            // ✅ Apply area filter (same logic as rest of report)
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canonSalesman, $allowed, true)) continue;
            }

            $est = strtoupper(trim((string)$r->estimator));
            if ($est === '') $est = 'NOT MENTIONED';

            if (!isset($out[$est])) {
                $out[$est] = array_fill(0, 13, 0.0); // 12 months + total
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$est][$idx] += (float)$r->s;
            $out[$est][12]   += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }


    /* ============================================================
       KPI Matrix builder (Forecast/Target/Inquiries/PO/Conversion)
    ============================================================ */

    private function buildSalesmanKpiMatrix(array $forecast, array $targets, array $inq, array $po): array
    {
        // union of salesmen keys
        $keys = array_unique(array_merge(
            array_keys($forecast),
            array_keys($targets),
            array_keys($inq),
            array_keys($po)
        ));

        $out = [];
        foreach ($keys as $s) {
            $f = $forecast[$s] ?? array_fill(0, 13, 0.0);
            $t = $targets[$s]  ?? array_fill(0, 13, 0.0);
            $i = $inq[$s]      ?? array_fill(0, 13, 0.0);
            $p = $po[$s]       ?? array_fill(0, 13, 0.0);

            // conversion: month-wise and total (total uses overall totals)
            $conv = array_fill(0, 13, 0.0);
            for ($m = 0; $m < 12; $m++) {
                $conv[$m] = ($i[$m] > 0) ? round(($p[$m] / $i[$m]) * 100, 1) : 0.0;
            }
            $conv[12] = ($i[12] > 0) ? round(($p[12] / $i[12]) * 100, 1) : 0.0;

            $out[$s] = [
                'FORECAST'  => $f,
                'TARGET'    => $t,
                'INQUIRIES' => $i,
                'POS'       => $p,
                'CONV_PCT'  => $conv,
            ];
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    /* ============================================================
       Totals (overall inquiries by month + by product)
    ============================================================ */

    private function buildTotalInquiriesByMonth(array $inquiriesBySalesman): array
    {
        $row = array_fill(0, 13, 0.0);
        foreach ($inquiriesBySalesman as $salesman => $arr) {
            for ($i = 0; $i < 13; $i++) $row[$i] += (float)$arr[$i];
        }
        return $row;
    }

    private function buildTotalInquiriesByProductFromSalesmanMatrix(array $inqProductMatrix): array
    {
        // Output required by Blade: [PRODUCT => [13]]
        $out = [];
        foreach ($inqProductMatrix as $salesman => $products) {
            foreach ($products as $product => $arr) {
                if (!isset($out[$product])) $out[$product] = array_fill(0, 13, 0.0);
                for ($i = 0; $i < 13; $i++) $out[$product][$i] += (float)$arr[$i];
            }
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    /**
     * Build month-wise totals grouped by project_type.
     * Returns: [ 'BIDDING' => [13], 'INHAND' => [13], ... ]
     */
    private function buildTotalInquiriesByMonthByType(int $year, string $areaNorm, array $regionMap): array
    {
        $typeExpr = "
        CASE
            WHEN UPPER(TRIM(project_type)) IN ('INHAND','IN-HAND','IN HAND') THEN 'INHAND'
            WHEN UPPER(TRIM(project_type)) IN ('BIDDING') THEN 'BIDDING'
            WHEN UPPER(TRIM(project_type)) IN ('LOST') THEN 'LOST'
            ELSE 'OTHER'
        END
    ";

        $labelExpr = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $valExpr   = "COALESCE(p.quotation_value,0)";

        $q = DB::table('projects as p')
            ->selectRaw("$typeExpr AS ptype, MONTH(p.quotation_date) AS m, SUM($valExpr) AS v, $labelExpr AS salesman")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("ptype, m, $labelExpr");

        $rows = $q->get();

        $out = [];
        foreach ($rows as $r) {
            $canon = $this->normalizeSalesman($r->salesman);

            // ✅ Apply area filter using your same region rules
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canon, $allowed, true)) continue;
            }

            $t = strtoupper($r->ptype ?? 'OTHER');
            if (!isset($out[$t])) $out[$t] = array_fill(0, 13, 0.0);

            $idx = ((int)$r->m) - 1;
            if ($idx >= 0 && $idx < 12) {
                $out[$t][$idx] += (float)$r->v;
            }
        }

        // total column
        foreach ($out as $t => $arr) {
            $out[$t][12] = array_sum(array_slice($arr, 0, 12));
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

}
