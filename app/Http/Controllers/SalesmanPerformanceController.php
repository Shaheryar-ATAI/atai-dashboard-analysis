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
    private function normalizeSalesman($name): string
    {
        $n = strtoupper(trim((string)$name));
        $n = preg_replace('/\s+/', ' ', $n);

        // common cleanup
        $n = str_replace(['.', ',', '-', '_'], ' ', $n);
        $n = preg_replace('/\s+/', ' ', $n);

        // ✅ HARD ALIAS FIXES
        if (preg_match('/^AHMED(\s+AMIN)?$/', $n)) return 'AHMED';
        if (preg_match('/^TA(RI|RE)Q$/', $n)) return 'TARIQ';
        if (preg_match('/^SOHAIB$/', $n)) return 'SOHAIB';
        if (preg_match('/^JAMAL$/', $n)) return 'JAMAL';
        if (preg_match('/^ABDO$/', $n)) return 'ABDO';

        // fallback (keep cleaned value)
        return $n ?: 'NOT MENTIONED';
    }


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

        // convert to collection for Yajra
        $collection = collect(array_values($agg));

        return DataTables::of($collection)
            ->editColumn('salesman', fn($row) =>
                '<span class="badge text-bg-secondary">' . e($row->salesman) . '</span>'
            )
            ->rawColumns(['salesman'])
            ->with(['sum_total' => $sum])
            ->make(true);
    }

    public function kpis(Request $r)
    {
        $year = (int)$r->query('year', now()->year);

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
            $inqAgg[$label] = ($inqAgg[$label] ?? 0) + (float)$row->total;
        }

        foreach ($po as $row) {
            $label = $this->normalizeSalesman($row->label);
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



    /**
     * ✅ PDF Export: Salesman Summary (same template style as Area Summary)
     * - One PDF
     * - One page per salesman
     * - Uses same alias normalization
     */
    public function pdf(Request $r)
    {
        $year  = (int)($r->query('year') ?? now()->year);
        $today = Carbon::now()->format('d-m-Y');

        // Disable strict grouping (same as your existing approach)
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        // Build pivot arrays (Jan..Dec + Total) using your existing alias normalization
        $inquiriesBySalesman = $this->buildSalesmanPivotInquiries($year);
        $posBySalesman       = $this->buildSalesmanPivotPOs($year);
// ✅ Region grouping based on fixed salesmen mapping
        $regionMap = [
            'Eastern' => ['SOHAIB'],
            'Central' => ['TARIQ', 'JAMAL'],
            'Western' => ['ABDO', 'AHMED'],
        ];

// Build pivot arrays by region (same shape as other pivots: Jan..Dec + Total)
        $inquiriesByRegion = $this->buildRegionPivotFromSalesmanPivot($inquiriesBySalesman, $regionMap);
        $posByRegion       = $this->buildRegionPivotFromSalesmanPivot($posBySalesman, $regionMap);
        $inqTotal = array_sum(array_map(fn($row) => (float) end($row), $inquiriesBySalesman));
        $poTotal  = array_sum(array_map(fn($row) => (float) end($row), $posBySalesman));

        $gapVal = $inqTotal - $poTotal;
        $gapPct = ($inqTotal > 0) ? round(($poTotal / $inqTotal) * 100, 1) : 0;

        $payload = [
            'year' => $year,
            'today' => $today,
            'kpis' => [
                'inquiries_total' => $inqTotal,
                'pos_total'       => $poTotal,
                'gap_value'       => $gapVal,
                'gap_percent'     => $gapPct,
            ],
            'inquiriesBySalesman' => $inquiriesBySalesman,
            'posBySalesman'       => $posBySalesman,
            'inqByRegion' => $inquiriesByRegion,
            'poByRegion'  => $posByRegion,
        ];

        return Pdf::loadView('reports.salesman-summary', $payload)
            ->setPaper('a4', 'portrait')
            ->download("ATAI_Salesman_Summary_{$year}.pdf");
    }

    /**
     * Build inquiries pivot: [SALESMAN => [jan,feb,...,december,total]]
     */
    private function buildSalesmanPivotInquiries(int $year): array
    {
        // base grouped by raw label + month
        $labelExpr = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $valExpr   = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$labelExpr AS salesman, MONTH(p.quotation_date) AS m, SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$labelExpr, MONTH(p.quotation_date)")
            ->get();

        return $this->pivotNormalizeSalesmanRows($rows);
    }

    /**
     * Build POs pivot: [SALESMAN => [jan,feb,...,december,total]]
     */
    private function buildSalesmanPivotPOs(int $year): array
    {
        $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
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

    /**
     * Convert rows(salesman, m, s) into your standard pivot array:
     * [CANONICAL => [jan..december,total]]
     */
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

        // compute totals and convert to numeric indexed array order [jan..december,total]
        $final = [];
        ksort($out, SORT_NATURAL);

        foreach ($out as $salesman => $arr) {
            $total = 0.0;
            foreach ($months as $k) $total += (float)$arr[$k];
            $arr['total'] = $total;

            $final[$salesman] = [];
            foreach ($months as $k) $final[$salesman][] = (float)$arr[$k];
            $final[$salesman][] = (float)$arr['total']; // last col TOTAL
        }

        return $final;
    }
    /**
     * Build Region pivot from an existing pivot (Salesman => [jan..dec,total])
     * using a mapping like:
     *  Eastern => [SOHAIB], Central => [TARIQ,JAMAL], Western => [ABDO,AHMED]
     */
    private function buildRegionPivotFromSalesmanPivot(array $pivot, array $regionMap): array
    {
        $out = [];

        foreach ($regionMap as $region => $salesmen) {
            // init row with 13 cols (Jan..Dec + Total)
            $row = array_fill(0, 13, 0.0);

            foreach ($salesmen as $s) {
                if (!isset($pivot[$s])) continue;

                // pivot[$s] is numeric array: [jan..dec,total]
                foreach ($pivot[$s] as $i => $val) {
                    $row[$i] += (float)$val;
                }
            }

            $out[$region] = $row;
        }

        return $out;
    }
}
