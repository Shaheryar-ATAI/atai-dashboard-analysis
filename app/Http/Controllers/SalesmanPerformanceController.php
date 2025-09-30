<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesmanPerformanceController extends Controller
{
    /**
     * Month aliases (safe, readable — we avoid using 'dec' because it can collide
     * with DEC type token on some MariaDB builds).
     */
    private array $monthAliases = [
        1 => 'jan',  2 => 'feb',  3 => 'mar',  4 => 'apr',
        5 => 'may',  6 => 'jun',  7 => 'jul',  8 => 'aug',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'december',
    ];

    /** Build SUM(CASE WHEN MONTH(...) = n THEN value ELSE 0 END) columns + total. */
    private function monthlySums(string $dateCol, string $valueCol): string
    {
        $parts = [];
        foreach ($this->monthAliases as $num => $alias) {
            $parts[] = "SUM(CASE WHEN MONTH($dateCol) = $num THEN COALESCE($valueCol,0) ELSE 0 END) AS $alias";
        }
        $parts[] = "SUM(COALESCE($valueCol,0)) AS total";
        return implode(",\n", $parts);
    }

    /**
     * Normalize a string column for grouping (lower + trim + fallback).
     * Keep it simple to avoid "illegal mix of collations" across servers.
     */
    private function salesmanNormExpr(string $col): string
    {
        return "LOWER(TRIM(COALESCE($col, 'Not Mentioned')))";
    }

    /** Page */
    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?? now()->year);

        return view('performance.salesman', [
            'year' => $year,
        ]);
    }

    /**
     * DataTables source
     * GET /performance/salesman/data?kind=inq|po&year=2025
     */
    public function data(Request $request)
    {
        $kind = (string) $request->query('kind', 'inq');             // "inq" or "po"
        $year = (int) $request->query('year', now()->year);

        if ($kind === 'po') {
            // ---- POs by salesman (sales_orders) ----
            $normExpr      = $this->salesmanNormExpr('s.sales_man');                         // <— change here if different
            $labelExpr     = "COALESCE(s.sales_man, 'Not Mentioned')";                       // <— change here if different
            $monthlySelect = $this->monthlySums('s.date_rec', 's.value_with_vat');                    // <— change here if different

            $q = DB::table('salesorderlog as s')
                ->selectRaw("$normExpr AS norm, $labelExpr AS salesman, $monthlySelect")
                ->whereYear('s.date_rec', $year)
                ->groupBy('norm', 'salesman');

            $sum = (float) DB::table('salesorderlog as s')
                ->whereYear('s.date_rec', $year)
                ->sum(DB::raw('COALESCE(s.value_with_vat,0)'));

        } else {
            // ---- Inquiries by salesman (projects) ----
            $normExpr      = $this->salesmanNormExpr('p.salesman');                          // <— change here if different
            $labelExpr     = "COALESCE(p.salesman, 'Not Mentioned')";                        // <— change here if different
            $monthlySelect = $this->monthlySums('p.quotation_date', 'p.quotation_value');    // <— change here if different

            $q = DB::table('projects as p')
                ->selectRaw("$normExpr AS norm, $labelExpr AS salesman, $monthlySelect")
                ->whereYear('p.quotation_date', $year)
                ->groupBy('norm', 'salesman');

            $sum = (float) DB::table('projects as p')
                ->whereYear('p.quotation_date', $year)
                ->sum(DB::raw('COALESCE(p.quotation_value,0)'));
        }

        return DataTables::of($q)
            // Unify the HTML badge for the salesman column
            ->editColumn('salesman', function ($row) {
                $txt = strtoupper($row->salesman ?? 'Not Mentioned');
                return '<span class="badge text-bg-secondary">'.$txt.'</span>';
            })
            ->rawColumns(['salesman'])
            ->with(['sum_total' => $sum])
            ->make(true);
    }

    /**
     * KPIs / Chart data:
     * GET /performance/salesman/kpis?year=2025
     *
     * Returns:
     *  - categories: [ "Aamer", "Tareq", ... ]
     *  - inquiries:  [ 1000, 500, ... ]   (sum of quotation_value)
     *  - pos:        [ 600, 800, ... ]    (sum of PO amount)
     *  - sum_inquiries, sum_pos
     */
    public function kpis(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        // Aggregate inquiries per salesman (use projects.salesman)
        $inq = DB::table('projects as p')
            ->selectRaw($this->salesmanNormExpr('p.salesman') . " AS norm")
            ->selectRaw("COALESCE(p.salesman, 'Not Mentioned') AS label")
            ->selectRaw("SUM(COALESCE(p.quotation_value,0)) AS total")
            ->whereYear('p.quotation_date', $year)
            ->groupBy('norm', 'label')
            ->get();

        // Aggregate POs per salesman (use salesorderlog.sales_man — backfilled)
        $po = DB::table('salesorderlog as s')
            ->selectRaw($this->salesmanNormExpr('s.sales_man') . " AS norm")
            ->selectRaw("COALESCE(s.sales_man, 'Not Mentioned') AS label")
            ->selectRaw("SUM(COALESCE(s.value_with_vat,0)) AS total")
            ->whereYear('s.date_rec', $year)
            ->where('Status', 'Accepted')
            ->groupBy('norm', 'label')
            ->get();

        // Index by 'norm' to avoid label/collation mismatches across tables
        $inqMap = []; $labelMap = [];
        foreach ($inq as $r) {
            $inqMap[$r->norm]   = (float) $r->total;
            $labelMap[$r->norm] = $r->label;
        }

        $poMap = [];
        foreach ($po as $r) {
            $poMap[$r->norm]    = (float) $r->total;
            $labelMap[$r->norm] = $r->label;
        }

        // Merge category keys
        $norms = array_keys($labelMap);
        sort($norms, SORT_NATURAL);

        $categories = [];
        $inqSeries  = [];
        $poSeries   = [];
        foreach ($norms as $k) {
            $categories[] = $labelMap[$k];
            $inqSeries[]  = $inqMap[$k] ?? 0;
            $poSeries[]   = $poMap[$k]  ?? 0;
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
