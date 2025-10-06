<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
class SalesOrderManagerController extends Controller
{
    public function index()
    {
        // Manager LOG page (DataTable)
        return view('sales_orders.manager.manager_log');
    }

    /**
     * Shared base query with role/region and UI filters applied.
     * Returns [$q, $dateExprSql, $valExprSql, $region]
     */
    protected function base(Request $r)
    {
        $u      = $r->user();
        $region = $u?->region ? trim($u->region) : null;

        $q = DB::table('salesorderlog as s');

        if (!empty($region)) {
            $q->whereRaw('LOWER(TRIM(s.region)) = ?', [strtolower($region)]);
        }

        $dateExprSql = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";

        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->query('from') ?: '1900-01-01';
            $to   = $r->query('to')   ?: '2999-12-31';
            $q->whereRaw("$dateExprSql BETWEEN ? AND ?", [$from, $to]);
        } else {
            if ($r->filled('year'))  $q->whereRaw("YEAR($dateExprSql) = ?",  [(int)$r->query('year')]);
            if ($r->filled('month')) $q->whereRaw("MONTH($dateExprSql) = ?", [(int)$r->query('month')]);
        }

        $valExprSql   = "COALESCE(s.value_with_vat, s.`PO Value`, 0)";
        $family       = trim((string) $r->query('family', ''));
        $status       = trim((string) $r->query('status', ''));   // NEW

        return [$q, $dateExprSql, $valExprSql, $region, $family, $status];
    }

    /**
     * DataTable JSON for the log.
     */
    public function datatable(Request $r)
    {
        // base already applies region/date; we’ll add chips here
        [$base] = $this->base($r);

        if ($fam = trim((string) $r->query('family', ''))) {
            $base->where('s.Products', $fam);
        }
        if ($st = trim((string) $r->query('status', ''))) {
            $base->where('s.Status', $st);
        }

        // Select with aliases (so JSON keys are clean)
        $q = $base->select([
            's.id',
            DB::raw('s.`PO. No.`      AS po_no'),
            's.date_rec',
            's.region',
            DB::raw('s.`Client Name`  AS client_name'),
            DB::raw('s.`Project Name` AS project_name'),
            DB::raw('s.`Products`     AS product_family'),
            's.value_with_vat',
            DB::raw('s.`PO Value`     AS po_value'),
            DB::raw('s.`Status`       AS status'),
            DB::raw('s.`Remarks`      AS remarks'),
            DB::raw('s.`Sales Source`    AS salesperson'),
        ]);

        $dt = DataTables::of($q)
            ->addIndexColumn() // DT_RowIndex (1-based serial #)

            // ---------- GLOBAL SEARCH (maps to real DB cols) ----------
            ->filter(function ($query) use ($r) {
                $search = strtolower($r->input('search.value', ''));
                if ($search === '') return;

                $query->where(function ($qq) use ($search) {
                    $qq->orWhereRaw('LOWER(s.`PO. No.`) LIKE ?',        ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Client Name`) LIKE ?',    ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Project Name`) LIKE ?',   ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Products`) LIKE ?',       ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Status`) LIKE ?',         ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Sales Source`) LIKE ?',      ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Remarks`) LIKE ?',        ["%$search%"])
                        ->orWhereRaw('LOWER(s.`region`) LIKE ?',         ["%$search%"])
                        ->orWhereRaw('DATE_FORMAT(s.`date_rec`,"%Y-%m-%d") LIKE ?', ["%$search%"]);
                });
            })

            // ---------- COLUMN ORDER MAP (UI alias -> real DB col) ----------
            ->orderColumn('po_no',          's.`PO. No.` $1')
            ->orderColumn('date_rec',       's.`date_rec` $1')
            ->orderColumn('region',         's.`region` $1')
            ->orderColumn('client_name',    's.`Client Name` $1')
            ->orderColumn('project_name',   's.`Project Name` $1')
            ->orderColumn('product_family', 's.`Products` $1')
            ->orderColumn('value_with_vat', 's.`value_with_vat` $1')
            ->orderColumn('po_value',       's.`PO Value` $1')
            ->orderColumn('status',         's.`Status` $1')
            ->orderColumn('remarks',        's.`Remarks` $1')
            ->orderColumn('salesperson',    's.`Sales Source` $1')

            // ---------- OPTIONAL: column-specific filters (search boxes in header) ----------
            ->filterColumn('po_no',          fn($q,$k)=> $q->whereRaw('s.`PO. No.` LIKE ?', ["%$k%"]))
            ->filterColumn('client_name',    fn($q,$k)=> $q->whereRaw('s.`Client Name` LIKE ?', ["%$k%"]))
            ->filterColumn('project_name',   fn($q,$k)=> $q->whereRaw('s.`Project Name` LIKE ?', ["%$k%"]))
            ->filterColumn('product_family', fn($q,$k)=> $q->whereRaw('s.`Products` LIKE ?', ["%$k%"]))
            ->filterColumn('status',         fn($q,$k)=> $q->whereRaw('s.`Status` LIKE ?', ["%$k%"]))
            ->filterColumn('salesperson',    fn($q,$k)=> $q->whereRaw('s.`Sales Source` LIKE ?', ["%$k%"]))
            ->filterColumn('remarks',        fn($q,$k)=> $q->whereRaw('s.`Remarks` LIKE ?', ["%$k%"]))
            ->filterColumn('region',         fn($q,$k)=> $q->whereRaw('s.`region` LIKE ?', ["%$k%"]))
            ->filterColumn('date_rec',       fn($q,$k)=> $q->whereRaw('DATE_FORMAT(s.`date_rec`,"%Y-%m-%d") LIKE ?', ["%$k%"]))

            // ---------- cast numbers cleanly ----------
            ->editColumn('value_with_vat', fn($row) => (float)($row->value_with_vat ?? 0))
            ->editColumn('po_value',       fn($row) => (float)($row->po_value ?? 0));

        return $dt->toJson();
    }

    /**
     * KPIs JSON for charts & badges.
     *
     * Response shape:
     * {
     *   totals: { count, value, region },
     *   monthly: { categories: [YYYY-MM...], values: [float...] },
     *   productSeries: { categories: [family...], values: [float...] },
     *   families: [family...]
     * }
     */
    public function kpis(Request $r)
    {
        [$base, $dateExprSql, $valExprSql, $region, $family, $status] = $this->base($r);

        // ---- chip lists
        $familiesBase = clone $base; // region+date only
        $allFamilies = $familiesBase->select(DB::raw("TRIM(s.`Products`) AS family"))
            ->whereNotNull(DB::raw("s.`Products`"))
            ->whereRaw("TRIM(s.`Products`) <> ''")
            ->distinct()->orderBy('family')->pluck('family')->values();

        // status list should depend on family (if chosen), plus region/date
        $statusBase = clone $base;
        if ($family !== '') $statusBase->where('s.Products', $family);
        $allStatuses = $statusBase->select(DB::raw("TRIM(COALESCE(s.`Status`,'Unknown')) AS status"))
            ->distinct()->orderBy('status')->pluck('status')->values();

        // ---- filtered KPIs (apply family & status)
        $filtered = clone $base;
        if ($family !== '') $filtered->where('s.Products', $family);
        if ($status !== '') $filtered->where('s.Status', $status);

        // totals
        $totals = (clone $filtered)->selectRaw("COUNT(*) AS cnt, COALESCE(SUM($valExprSql),0) AS val")->first();

        // monthly total (for your existing simple monthly card)
        $monthlyRows = (clone $filtered)
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy(DB::raw('ym'))->orderBy(DB::raw('ym'))->get();
        $labels = []; $values = [];
        foreach ($monthlyRows as $row) { $labels[] = $row->ym; $values[] = (float)$row->val; }

        // top products
        $topProducts = (clone $filtered)
            ->selectRaw("COALESCE(s.`Products`, 'Unknown') AS product, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(10)->get();

        // pie: value by status (respects family+status filter)
        $byStatus = (clone $filtered)
            ->selectRaw("COALESCE(s.`Status`,'Unknown') AS status, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('status')->get();
        $statusPie = $byStatus->map(fn($r) => ['name'=>$r->status, 'y'=>(float)$r->val])->values();

        // grouped bars by status per month (respects family+status filter)
        $monthlyStatus = (clone $filtered)
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym,
                     COALESCE(s.`Status`, 'Unknown') AS status,
                     COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym','status')->orderBy('ym')->get();

        $statusOrder = ['Accepted','Pre-Acceptance','Waiting','Rejected','Cancelled','Unknown'];
        $months = $monthlyStatus->pluck('ym')->unique()->sort()->values()->all();

        $bySt = [];
        foreach ($statusOrder as $st) $bySt[$st] = array_fill_keys($months, 0.0);
        foreach ($monthlyStatus as $r2) {
            $st = $r2->status;
            if (!isset($bySt[$st])) $bySt[$st] = array_fill_keys($months, 0.0);
            $bySt[$st][$r2->ym] = (float)$r2->val;
        }

        $barSeries = [];
        foreach ($statusOrder as $st) {
            if (!isset($bySt[$st])) continue;
            $barSeries[] = ['type'=>'column','name'=>$st,'data'=>array_values($bySt[$st])];
        }

        // lines: keep variance visible even when a status chip is selected
        $linesRespectStatusFilter = false;
        $lineScope = $linesRespectStatusFilter ? $filtered : (function() use ($base,$family){
            $q = clone $base;
            if ($family !== '') $q->where('s.Products', $family); // lines still follow product if set
            return $q;
        })();

        $monthlyAccepted = (clone $lineScope)
            ->where('s.Status','Accepted')
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym')->orderBy('ym')->pluck('val','ym')->all();

        $monthlyPreAcc = (clone $lineScope)
            ->where('s.Status','Pre-Acceptance')
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym')->orderBy('ym')->pluck('val','ym')->all();


        $monthlyCancelled = (clone $lineScope)
            ->where('s.Status','Cancelled')
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym')->orderBy('ym')->pluck('val','ym')->all();


        $acceptedLine  = array_map(fn($m)=> (float) ($monthlyAccepted[$m] ?? 0), $months);
        $preAcceptLine = array_map(fn($m)=> (float) ($monthlyPreAcc[$m] ?? 0), $months);
        $cancelledLine = array_map(fn($m)=> (float) ($monthlyCancelled[$m] ?? 0), $months);

        $lineSeries = [
            [
                'type' => 'spline',
                'name' => 'Accepted (line)',
                'data' => $acceptedLine,
                'dashStyle' => 'ShortDot',
                'color' => '#007bff', // blue
                'marker' => ['enabled' => false],
            ],
            [
                'type' => 'spline',
                'name' => 'Pre-Acceptance (line)',
                'data' => $cancelledLine,
                'dashStyle' => 'ShortDash',
                'color' => '#ff9900', // orange
                'marker' => ['enabled' => false],
            ],
        ];

        return response()->json([
            'totals' => [
                'count'  => (int) ($totals->cnt ?? 0),
                'value'  => (float) ($totals->val ?? 0),
                'region' => $region,
            ],

            // simple monthly
            'monthly' => ['categories'=>$labels, 'values'=>$values],

            // top products
            'productSeries' => [
                'categories' => $topProducts->pluck('product'),
                'values'     => $topProducts->pluck('val')->map(fn($v)=>(float)$v),
            ],

            // chips (and which are active)
            'allFamilies'  => $allFamilies,
            'activeFamily' => $family,
            'allStatuses'  => $allStatuses,
            'activeStatus' => $status,

            // main charts
            'statusPie' => $statusPie,
            'multiMonthly' => [
                'categories' => $months,
                'bars'       => $barSeries,
                'lines'      => $lineSeries,
            ],
        ]);
    }



}
