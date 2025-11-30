<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesmanPerformanceController extends Controller
{
    private array $salesmanAliasMap = [
        // Sohaib
        'sohaib'        => 'SOHAIB',
        'soahib'        => 'SOHAIB',

        // Tariq
        'tariq'         => 'TARIQ',
        'tareq'         => 'TARIQ',

        // Abdo
        'abdo'          => 'ABDO',
        'abdo yousef'   => 'ABDO',
        'abdoyousef'    => 'ABDO',

        // Mohammed / Merhi
        'mohammed'      => 'MOHAMMED',
        'mohamad'       => 'MOHAMMED',
        'm. merhi'      => 'MOHAMMED',
        'm.abu merhi'   => 'MOHAMMED',
        'm abu merhi'   => 'MOHAMMED',
        'merhi'         => 'MOHAMMED',
        'm.merhi'       => 'MOHAMMED',
        'm. abu merhi'  => 'MOHAMMED',

        // Group everything internal as “ATAI”
        'atai'          => 'ATAI',
        'waseem'        => 'ATAI',
        'faisal'        => 'ATAI',
        'export'        => 'ATAI',
        'admin'         => 'ATAI',
        'client'        => 'ATAI',
    ];


    private function normalizeSalesman(?string $name): string
    {
        if (! $name) {
            return 'NOT MENTIONED';
        }

        // trim + collapse spaces + lowercase
        $key = strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key);

        // if in alias map, return canonical value, else just uppercase original
        return $this->salesmanAliasMap[$key] ?? strtoupper(trim($name));
    }

    private array $monthAliases = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'december'
    ];

    private function monthlySums(string $dateCol, string $valExpr): string
    {
        $parts = [];
        foreach ($this->monthAliases as $m => $a) {
            $parts[] = "SUM(CASE WHEN MONTH($dateCol)=$m THEN $valExpr ELSE 0 END) AS $a";
        }
        $parts[] = "SUM($valExpr) AS total";
        return implode(",", $parts);
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
        $year = (int) ($r->query('year') ?? now()->year);
        return view('performance.salesman', ['year' => $year]);
    }

    public function data(Request $r)
    {
        $kind = $r->query('kind', 'inq');
        $year = (int) $r->query('year', now()->year);

        if ($kind === 'po') {
            // ---------- Sales Order Log ----------
            $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
            $amount    = $this->poAmountExpr('s');
            $monthly   = $this->monthlySums('s.date_rec', $amount);

            // base rows: one row per *raw* label
            $baseQuery = DB::table('salesorderlog as s')
                ->selectRaw("$labelExpr AS salesman,$monthly")
                ->whereYear('s.date_rec', $year)
                ->groupByRaw($labelExpr);

            $sum = (float) DB::table('salesorderlog as s')
                ->whereYear('s.date_rec', $year)
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

            $sum = (float) DB::table('projects as p')
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
                $agg[$alias] = (object) array_merge(
                    ['salesman' => $alias],
                    array_fill_keys($months, 0.0),
                    ['total' => 0.0]
                );
            }

            // accumulate month values + total
            foreach ($months as $m) {
                $agg[$alias]->$m += (float) $row->$m;
            }
            $agg[$alias]->total += (float) $row->total;
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
        $year = (int) $r->query('year', now()->year);

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
            ->groupByRaw("$poNorm,$poLabel")
            ->get();

        // --- Merge by alias (SOHAIB, SOAHIB -> SOHAIB etc.) ---
        $inqAgg = [];
        $poAgg  = [];

        foreach ($inq as $row) {
            $label = $this->normalizeSalesman($row->label);
            $inqAgg[$label] = ($inqAgg[$label] ?? 0) + (float) $row->total;
        }

        foreach ($po as $row) {
            $label = $this->normalizeSalesman($row->label);
            $poAgg[$label] = ($poAgg[$label] ?? 0) + (float) $row->total;
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
}
