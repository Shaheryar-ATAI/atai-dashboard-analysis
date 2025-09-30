<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderController extends Controller
{
    public function index()
    {
        return view('sales_orders.index');
    }

    /* -----------------------------------------------------------
     * Helpers: schema detection + column/expression dictionary
     * ---------------------------------------------------------*/

    /** Check if a physical column exists on salesorderlog (Excel header). */
    private function hasColumn(string $column): bool
    {
        $db = DB::getDatabaseName();
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'salesorderlog')
            ->where('COLUMN_NAME', $column)
            ->exists();
    }

    /** Region expression: prefer normalized if the column exists, else Count./Region. */
    private function regionExpr(): string
    {
        if ($this->hasColumn('Region (normalized)')) {
            return "COALESCE(NULLIF(`Region (normalized)`,'') , `region`)";
        }
        return "`region`";
    }

    /**
     * Central map of SQL expressions (as strings) pointing to your Excel headers.
     * We cast money to DECIMAL so sorting/aggregation works.
     */
    private function colMap(): array
    {
        return [
            // text fields (Excel headers with quoting)
            'date_rec_d'       => "`Date Rec'd`",
            'po_no'            => "`PO. No.`",
            'client_name'      => "`Client Name`",
            'project_name'     => "`Project Name`",
            'region_name'      => $this->regionExpr(),
            'project_location' => "`Project Location`",
            'cur'              => "`Cur`",
            'status'           => "`Status`",

            // numeric casts (strip commas/spaces then DECIMAL)
            'po_value'         => "CAST(NULLIF(REPLACE(REPLACE(`PO Value`,       ',', ''), ' ', ''), '') AS DECIMAL(18,2))",
            'value_with_vat'   => "CAST(NULLIF(REPLACE(REPLACE(`value_with_vat`, ',', ''), ' ', ''), '') AS DECIMAL(18,2))",
        ];
    }

    /* -----------------------------------------------------------
     * DataTables grid
     * ---------------------------------------------------------*/

    /** Base SELECT with aliases that your front-end expects (snake_case). */
    private function baseSelect()
    {
        $m = $this->colMap();

        return DB::table('salesorderlog')->select([
             DB::raw('id'),
             DB::raw("DATE_FORMAT(`date_rec`, '%d-%m-%Y') AS date_rec_d"), // ← from DATE
            DB::raw($m['po_no']            . ' AS po_no'),
            DB::raw($m['client_name']      . ' AS client_name'),
            DB::raw($m['project_name']     . ' AS project_name'),
            DB::raw($m['region_name']      . ' AS region_name'),
            DB::raw($m['project_location'] . ' AS project_location'),
            DB::raw($m['cur']              . ' AS cur'),
            DB::raw($m['po_value']         . ' AS po_value'),
            DB::raw($m['value_with_vat']   . ' AS value_with_vat'),
            DB::raw($m['status']           . ' AS status'),
        ]);
    }

    /**
     * Apply DataTables per-column & global filters.
     * We use whereRaw with bindings since many “columns” are expressions.
     */
    private function applyDtFilters($qb, Request $request)
    {
        $m = $this->colMap();

        // Per-column filters (DataTables sends columns[i].name & columns[i].search.value)
        foreach ((array) $request->get('columns', []) as $c) {
            $name  = $c['name'] ?? null;
            $value = trim($c['search']['value'] ?? '');
            if (!$name || $value === '' || !isset($m[$name])) continue;

            if (in_array($name, ['po_value','value_with_vat'], true)) {
                // numeric min..max or >= min
                if (preg_match('/^\s*([\d.]+)\s*\.\.\s*([\d.]+)\s*$/', $value, $mm)) {
                    $qb->whereRaw($m[$name] . ' BETWEEN ? AND ?', [(float)$mm[1], (float)$mm[2]]);
                } else {
                    $qb->whereRaw($m[$name] . ' >= ?', [(float)$value]);
                }
            }
            if ($request->filled('year')) {
                $qb->whereYear('date_rec', (int)$request->input('year'));
            } else {
                $qb->whereRaw($m[$name] . ' LIKE ?', ["%{$value}%"]);
            }
        }

        // Global search
        $s = trim($request->input('search.value', ''));
        if ($s !== '') {
            $qb->where(function($q) use ($s, $m) {
                $like = "%{$s}%";
                $q->whereRaw($m['po_no']             . ' LIKE ?', [$like])
                  ->orWhereRaw($m['client_name']     . ' LIKE ?', [$like])
                  ->orWhereRaw($m['project_name']    . ' LIKE ?', [$like])
                  ->orWhereRaw($m['region_name']     . ' LIKE ?', [$like])
                  ->orWhereRaw($m['project_location'].' LIKE ?', [$like])
                  ->orWhereRaw($m['cur']             . ' LIKE ?', [$like])
                  ->orWhereRaw($m['status']          . ' LIKE ?', [$like])
                  ->orWhereRaw($m['date_rec_d']      . ' LIKE ?', ["{$s}%"]);
            });
        }

        return $qb;
    }

    /** DataTables endpoint with filtered totals. */
    public function datatableLog(Request $request)
{
    $m        = $this->colMap();

    // rows query already has ALL filters we want
    $tableQ   = $this->applyDtFilters($this->baseSelect(), $request);

    // totals from same filters
    $sumBase  = $this->applyDtFilters(DB::table('salesorderlog'), $request);
    $sumPo    = (float) (clone $sumBase)->selectRaw('SUM(' . $m['po_value']       . ') AS s')->value('s');
    $sumVat   = (float) (clone $sumBase)->selectRaw('SUM(' . $m['value_with_vat'] . ') AS s')->value('s');

    return DataTables::of($tableQ)
        // ⬇️ disable Yajra’s default global/column search (we did it already)
        ->filter(function ($query) use ($request) {
            // no-op — all filtering was applied in $tableQ via applyDtFilters()
        }, true)

        // numeric render & ordering kept as-is
        ->editColumn('po_value',       fn($r) => (float)$r->po_value)
        ->editColumn('value_with_vat', fn($r) => (float)$r->value_with_vat)
        ->orderColumn('po_value',       $m['po_value']       . ' $1')
        ->orderColumn('value_with_vat', $m['value_with_vat'] . ' $1')
        ->orderColumn('date_rec_d', 'date_rec $1')
        ->with([
            'sum_po_value'       => $sumPo,
            'sum_value_with_vat' => $sumVat,
        ])
        ->make(true);
}

    /* -----------------------------------------------------------
     * KPIs (cards + charts)
     *   — Use subqueries to materialize aliases, then group by aliases.
     *   — Avoid ONLY_FULL_GROUP_BY issues.
     * ---------------------------------------------------------*/
public function kpis(Request $r)
{
    $year   = $r->input('year');
    $region = $r->input('region');
    $m      = $this->colMap();

    // ---- Base filter (no selects yet) ----
    $base = DB::table('salesorderlog');
    if ($year)   $base->whereYear('date_rec', $year);     // ✅ use new DATE column
    if ($region) $base->whereRaw($m['region_name'] . ' = ?', [$region]);

    // helper to clone the WHEREs into a subquery
    $mk = fn() => (clone $base);

    // ---------- totals ----------
    $totals = [
        'value_with_vat' => (float) $mk()->selectRaw('SUM(' . $m['value_with_vat'] . ') AS t')->where('Status', 'Accepted')->value('t'),
        'po_value'       => (float) $mk()->selectRaw('SUM(' . $m['po_value']       . ') AS t')->where('Status', 'Accepted')->value('t'),
    ];

    // ---------- BY REGION ----------
    $regionSub = $mk()->selectRaw(
        $m['region_name'] . ' AS region, ' .
        $m['value_with_vat'] . ' AS vat'
    );
    $byRegion = DB::query()
        ->fromSub($regionSub, 't')
        ->selectRaw('region, SUM(vat) AS total, COUNT(*) AS orders')
        ->groupBy('region')
        ->get();

    // ---------- BY STATUS ----------
    $statusSub = $mk()->selectRaw(
        $m['status'] . ' AS status, ' .
        $m['value_with_vat'] . ' AS vat'
    );
    $byStatus = DB::query()
        ->fromSub($statusSub, 't')
        ->selectRaw('status, SUM(vat) AS total')
        ->groupBy('status')
        ->get();

    // ---------- MONTHLY ----------
    $monthSub = $mk()->selectRaw(
        "DATE_FORMAT(`date_rec`, '%Y-%m') AS ym, " .      // ✅ use new DATE column
        $m['value_with_vat'] . ' AS vat'
    );
    $monthly = DB::query()
        ->fromSub($monthSub, 't')
        ->selectRaw('ym, SUM(vat) AS total')
        ->groupBy('ym')
        ->get();

    // ---------- TOP CLIENTS ----------
    $clientSub = $mk()->selectRaw(
        $m['client_name'] . ' AS client, ' .
        $m['value_with_vat'] . ' AS vat'
    );
    $topClients = DB::query()
        ->fromSub($clientSub, 't')
        ->selectRaw('client, SUM(vat) AS total, COUNT(*) AS orders')
        ->groupBy('client')
        ->orderByDesc(DB::raw('SUM(vat)'))
        ->limit(10)
        ->get();

    // ---------- BY CURRENCY ----------
    $curSub = $mk()->selectRaw(
        $m['cur'] . ' AS currency, ' .
        $m['value_with_vat'] . ' AS vat'
    );
    $byCurrency = DB::query()
        ->fromSub($curSub, 't')
        ->selectRaw('currency, SUM(vat) AS total')
        ->groupBy('currency')
        ->get();

    return response()->json([
        'totals'      => $totals,
        'by_region'   => $byRegion,
        'by_status'   => $byStatus,
        'monthly'     => $monthly,
        'top_clients' => $topClients,
        'by_currency' => $byCurrency,
    ]);
}
}
