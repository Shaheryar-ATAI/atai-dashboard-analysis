<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesmanPerformanceController extends Controller
{
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
        return "CAST(NULLIF(REPLACE(REPLACE($alias.value_with_vat,',',''),' ',''),'') AS DECIMAL(18,2))";
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
            $label = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
            $norm = $this->norm($label);
            $amount = $this->poAmountExpr('s');
            $monthly = $this->monthlySums('s.date_rec', $amount);

            $query = DB::table('salesorderlog as s')
                ->selectRaw("$norm AS norm,$label AS salesman,$monthly")
                ->whereYear('s.date_rec', $year)
                ->groupByRaw("$norm,$label");

            $sum = (float)DB::table('salesorderlog as s')
                ->whereYear('s.date_rec', $year)
                ->selectRaw("SUM($amount) AS s")->value('s');
        } else {
            // ---------- Projects / Inquiries ----------
            $label = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
            $norm = $this->norm($label);
            $val = 'COALESCE(p.quotation_value,0)';
            $monthly = $this->monthlySums('p.quotation_date', $val);

            $query = DB::table('projects as p')
                ->selectRaw("$norm AS norm,$label AS salesman,$monthly")
                ->whereYear('p.quotation_date', $year)
                ->groupByRaw("$norm,$label");

            $sum = (float)DB::table('projects as p')
                ->whereYear('p.quotation_date', $year)
                ->selectRaw("SUM($val) AS s")->value('s');
        }

        // force-disable strict mode in session for this query
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        return DataTables::of($query)
            ->editColumn('salesman', fn($r) => '<span class="badge text-bg-secondary">' . strtoupper($r->salesman ?? 'Not Mentioned') . '</span>')
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
        $projNorm = $this->norm($projLabel);

        $inq = DB::table('projects as p')
            ->selectRaw("$projNorm AS norm,$projLabel AS label,SUM(COALESCE(p.quotation_value,0)) AS total")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$projNorm,$projLabel")
            ->get();

        // --- POs ---
        $poLabel = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $poNorm = $this->norm($poLabel);
        $amt = $this->poAmountExpr('s');

        $po = DB::table('salesorderlog as s')
            ->selectRaw("$poNorm AS norm,$poLabel AS label,SUM($amt) AS total")
            ->whereYear('s.date_rec', $year)
            ->where('Status', 'Accepted')
            ->groupByRaw("$poNorm,$poLabel")
            ->get();

        $inqMap = $poMap = $labelMap = [];
        foreach ($inq as $r) {
            $inqMap[$r->norm] = (float)$r->total;
            $labelMap[$r->norm] = $r->label;
        }
        foreach ($po as $r) {
            $poMap[$r->norm] = (float)$r->total;
            $labelMap[$r->norm] = $r->label;
        }

        $keys = array_keys($labelMap);
        sort($keys, SORT_NATURAL);
        $cats = $inqSeries = $poSeries = [];
        foreach ($keys as $k) {
            $cats[] = $labelMap[$k];
            $inqSeries[] = $inqMap[$k] ?? 0;
            $poSeries[] = $poMap[$k] ?? 0;
        }

        return response()->json([
            'categories' => $cats,
            'inquiries' => $inqSeries,
            'pos' => $poSeries,
            'sum_inquiries' => array_sum($inqSeries),
            'sum_pos' => array_sum($poSeries)
        ]);
    }
}
