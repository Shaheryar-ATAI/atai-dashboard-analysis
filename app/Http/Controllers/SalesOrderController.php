<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderController extends Controller
{
    private ?array $salesOrderColumns = null;

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
        if ($this->salesOrderColumns === null) {
            $db = DB::getDatabaseName();
            $this->salesOrderColumns = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', 'salesorderlog')
                ->pluck('COLUMN_NAME')
                ->all();
        }

        return in_array($column, $this->salesOrderColumns, true);
    }


    /** Region expression: prefer normalized if the column exists, else fall back. */
    private function regionExpr(): string
    {
        if ($this->hasColumn('Region (normalized)')) {
            return "COALESCE(NULLIF(`Region (normalized)`,'') , `region`)";
        }
        return "`region`";
    }

    /**
     * Central map of SQL expressions (as strings) pointing to your Excel headers.
     * We cast money to DECIMAL so sorting/aggregation works reliably.
     */
    private function colMap(): array
    {
        return [
            'date_rec_d' => "`date_rec`", // normalized DATE col
            'po_no' => "`PO. No.`",
            'client_name' => "`Client Name`",
            'project_name' => "`Project Name`",
            'region_name' => $this->regionExpr(),
            'project_location' => "`Project Location`",
            'cur' => "`Cur`",
            'status' => "`Status`",

            'po_value' => "COALESCE(`PO Value`, 0)",
            'value_with_vat' => "COALESCE(`value_with_vat`, 0)",
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
            DB::raw("DATE_FORMAT(`date_rec`, '%d-%m-%Y') AS date_rec_d"),
            DB::raw($m['po_no'] . ' AS po_no'),
            DB::raw($m['client_name'] . ' AS client_name'),
            DB::raw($m['project_name'] . ' AS project_name'),
            DB::raw($m['region_name'] . ' AS region_name'),
            DB::raw($m['project_location'] . ' AS project_location'),
            DB::raw($m['cur'] . ' AS cur'),
            DB::raw($m['po_value'] . ' AS po_value'),
            DB::raw($m['value_with_vat'] . ' AS value_with_vat'),
            DB::raw($m['status'] . ' AS status'),
        ]);
    }


    /**
     * Apply DataTables per-column & global filters.
     * We use whereRaw with bindings since many “columns” are expressions.
     */
    private function applyDtFilters($qb, Request $request)
    {
        $m = $this->colMap();

        // 1) Page filters so the grid follows the top filters
        if ($request->filled('year')) {
            $qb->whereYear('date_rec', (int)$request->input('year'));
        }
        // region select (ignore “All Region” if that’s your option text)
        if ($request->filled('region') && strtolower($request->input('region')) !== 'all region') {
            $qb->whereRaw($m['region_name'] . ' = ?', [$request->input('region')]);
        }

        // 2) Per-column filters (DataTables columns[i].search.value)
        foreach ((array)$request->get('columns', []) as $c) {
            $name = $c['name'] ?? null;
            $value = trim($c['search']['value'] ?? '');
            if (!$name || $value === '') continue;

            // use only mapped expressions; ignore unknown names to avoid SQL errors
            if (!isset($m[$name]) && $name !== 'date_rec_d') continue;

            if ($name === 'date_rec_d') {
                // your grid displays DATE_FORMAT(date_rec,'%d-%m-%Y')
                $qb->whereRaw("DATE_FORMAT(date_rec, '%d-%m-%Y') LIKE ?", [$value . '%']);
                continue;
            }

            // numeric ranges for money columns
            if (in_array($name, ['po_value', 'value_with_vat'], true)) {
                if (preg_match('/^\s*([\d.]+)\s*\.\.\s*([\d.]+)\s*$/', $value, $m2)) {
                    $qb->whereRaw($m[$name] . ' BETWEEN ? AND ?', [(float)$m2[1], (float)$m2[2]]);
                } else {
                    $qb->whereRaw($m[$name] . ' >= ?', [(float)$value]);
                }
            } else {
                $qb->whereRaw($m[$name] . ' LIKE ?', ['%' . $value . '%']);
            }
        }

        // 3) Global search (search box, request->input('search.value'))
        $s = trim($request->input('search.value', ''));
        if ($s !== '') {
            $like = '%' . $s . '%';
            $qb->where(function ($q) use ($like, $m) {
                $q->whereRaw($m['po_no'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['client_name'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['project_name'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['region_name'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['project_location'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['cur'] . ' LIKE ?', [$like])
                    ->orWhereRaw($m['status'] . ' LIKE ?', [$like])
                    // search the formatted date the grid shows
                    ->orWhereRaw("DATE_FORMAT(date_rec, '%d-%m-%Y') LIKE ?", [$like]);
            });
        }

        return $qb;
    }


    /** DataTables endpoint with filtered totals. */
    public function datatableLog(Request $request)
    {
        $m = $this->colMap();

        // Base queries
        $rowsQ = $this->applyDtFilters($this->baseSelect(), $request);
        $sumQ = $this->applyDtFilters(DB::table('salesorderlog'), $request);

        // Totals using the same filters
        $sumPo = (float)(clone $sumQ)->selectRaw('SUM(' . $m['po_value'] . ') AS s')->value('s');
        $sumVat = (float)(clone $sumQ)->selectRaw('SUM(' . $m['value_with_vat'] . ') AS s')->value('s');

        return DataTables::of($rowsQ)
            ->filter(function () {
            }, true)
            ->editColumn('po_value', fn($r) => (float)$r->po_value)
            ->editColumn('value_with_vat', fn($r) => (float)$r->value_with_vat)
            ->orderColumn('po_value', $m['po_value'] . ' $1')
            ->orderColumn('value_with_vat', $m['value_with_vat'] . ' $1')
            ->orderColumn('date_rec_d', 'date_rec $1')
            ->with([
                'sum_po_value' => $sumPo,
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
        $year = $r->input('year');
        $region = $r->input('region');        // UI sends "region"
        $m = $this->colMap();            // expects: region_name, value_with_vat, po_value, status, client_name, cur

        // --- Base (no accidental soft-delete filter)
        $base = DB::table('salesorderlog');
        if ($year) $base->whereYear('date_rec', $year); // uses DATE column
        if ($region) $base->whereRaw($m['region_name'] . ' = ?', [$region]);

        // Helper to re-use the same WHEREs
        $mk = fn() => (clone $base);

        // Stored as DECIMAL in current schema; keep expressions simple for faster aggregation.
        $vatExpr = "COALESCE({$m['value_with_vat']}, 0)";
        $poExpr = "COALESCE({$m['po_value']}, 0)";

        // --- Normalize statuses (align pie + bars)
        $statusCase = "
          CASE
            WHEN LOWER({$m['status']}) IN ('accepted','in-hand','in hand','won')     THEN 'Accepted'
            WHEN LOWER({$m['status']}) IN ('pre-acceptance','pre acceptance','pre')  THEN 'Pre-Acceptance'
            WHEN LOWER({$m['status']}) IN ('hold','on hold','on-hold')               THEN 'HOLD'
            WHEN LOWER({$m['status']}) IN ('cancelled','canceled')                   THEN 'Cancelled'
            WHEN LOWER({$m['status']}) IN ('rejected','lost')                        THEN 'Rejected'
            ELSE 'Other'
          END
        ";

        // ---------- Totals (Accepted only)
        $totals = [
            'value_with_vat' => (float)$mk()
                ->selectRaw("SUM($vatExpr) AS t")
                ->whereRaw("LOWER({$m['status']}) = 'accepted'")
                ->value('t'),
            'po_value' => (float)$mk()
                ->selectRaw("SUM($poExpr) AS t")
                ->whereRaw("LOWER({$m['status']}) = 'accepted'")
                ->value('t'),
        ];

        // ---------- BY REGION (totals)
        $regionSub = $mk()->selectRaw("{$m['region_name']} AS region, $vatExpr AS vat");
        $byRegion = DB::query()
            ->fromSub($regionSub, 't')
            ->selectRaw('region, SUM(vat) AS total, COUNT(*) AS orders')
            ->groupBy('region')
            ->get();

        // ---------- BY STATUS (pie buckets)
        $statusSub = $mk()->selectRaw("($statusCase) AS status, $vatExpr AS vat");
        $byStatus = DB::query()
            ->fromSub($statusSub, 't')
            ->selectRaw('status, SUM(vat) AS total')
            ->groupBy('status')
            ->get();

        // ---------- MONTHLY (totals)
        $monthSub = $mk()->selectRaw("DATE_FORMAT(date_rec, '%Y-%m') AS ym, $vatExpr AS vat");
        $monthly = DB::query()
            ->fromSub($monthSub, 't')
            ->selectRaw('ym, SUM(vat) AS total')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // ---------- TOP CLIENTS
        $clientSub = $mk()->selectRaw("{$m['client_name']} AS client, $vatExpr AS vat");
        $topClients = DB::query()
            ->fromSub($clientSub, 't')
            ->selectRaw('client, SUM(vat) AS total, COUNT(*) AS orders')
            ->groupBy('client')
            ->orderByDesc(DB::raw('SUM(vat)'))
            ->limit(10)
            ->get();

        // ---------- BY CURRENCY
        $curSub = $mk()->selectRaw("{$m['cur']} AS currency, $vatExpr AS vat");
        $byCurrency = DB::query()
            ->fromSub($curSub, 't')
            ->selectRaw('currency, SUM(vat) AS total')
            ->groupBy('currency')
            ->get();

        /* ==========================================================
         * NEW 1) REGION × STATUS (VAT) → stacked per region
         * ========================================================== */
        $rsSub = $mk()->selectRaw("{$m['region_name']} AS region, ($statusCase) AS status_norm, $vatExpr AS vat");
        $rowsRS = DB::query()
            ->fromSub($rsSub, 't')
            ->selectRaw('region, status_norm, SUM(vat) AS total')
            ->groupBy('region', 'status_norm')
            ->orderBy('region')
            ->get();

        $statusOrder = ['Accepted', 'Pre-Acceptance', 'HOLD', 'Cancelled', 'Rejected'];
        $regionsPref = ['Central', 'Eastern', 'Eastern Jubail', 'Western'];

        $regionsAll = $rowsRS->pluck('region')->unique()->values();
        $regions = collect($regionsPref)->merge($regionsAll->diff($regionsPref))->values()->all();

        $idxRS = [];
        foreach ($rowsRS as $r) {
            $idxRS[$r->region][$r->status_norm] = (float)$r->total;
        }

        $seriesRegionStatus = [];
        foreach ($statusOrder as $st) {
            $data = [];
            foreach ($regions as $rg) {
                $data[] = (float)($idxRS[$rg][$st] ?? 0);
            }
            $seriesRegionStatus[] = ['name' => $st, 'data' => $data, 'stack' => 'VAT'];
        }

        /* ==========================================================
         * NEW 2) MONTHLY × STATUS (VAT) → stacked per month
         * ========================================================== */
        $msSub = $mk()->selectRaw("DATE_FORMAT(date_rec, '%Y-%m') AS ym, ($statusCase) AS status_norm, $vatExpr AS vat");
        $rowsMS = DB::query()
            ->fromSub($msSub, 't')
            ->selectRaw('ym, status_norm, SUM(vat) AS total')
            ->groupBy('ym', 'status_norm')
            ->orderBy('ym')
            ->get();

        $months = $monthly->pluck('ym')->unique()->values()->all();
        if (empty($months)) {
            $months = $rowsMS->pluck('ym')->unique()->sort()->values()->all();
        }

        $idxMS = [];
        foreach ($rowsMS as $r) {
            $idxMS[$r->ym][$r->status_norm] = (float)$r->total;
        }

        $seriesMonthlyStatus = [];
        foreach ($statusOrder as $st) {
            $data = [];
            foreach ($months as $ym) {
                $data[] = (float)($idxMS[$ym][$st] ?? 0);
            }
            $seriesMonthlyStatus[] = ['name' => $st, 'data' => $data, 'stack' => 'VAT'];
        }

        /* ================================================================================
         * NEW 3) MONTHLY × REGION × STATUS (VAT) → grouped+stacked
         * ================================================================================ */
        $mrsSub = $mk()->selectRaw("DATE_FORMAT(date_rec, '%Y-%m') AS ym, {$m['region_name']} AS region, ($statusCase) AS status_norm, $vatExpr AS vat");
        $rowsMRS = DB::query()
            ->fromSub($mrsSub, 't')
            ->selectRaw('ym, region, status_norm, SUM(vat) AS total')
            ->groupBy('ym', 'region', 'status_norm')
            ->orderBy('ym')
            ->get();

        $regionsAllM = $rowsMRS->pluck('region')->unique()->values();
        $regionsM = collect($regions)->merge($regionsAllM->diff($regions))->values()->all();

        $seriesMonthlyRegionStatus = [];
        $seriesIndex = [];
        foreach ($regionsM as $rg) {
            foreach ($statusOrder as $st) {
                $key = "$rg – $st";
                $seriesMonthlyRegionStatus[] = [
                    'name' => $key,
                    'stack' => $rg,
                    'data' => array_fill(0, count($months), 0.0)
                ];
                $seriesIndex[$key] = count($seriesMonthlyRegionStatus) - 1;
            }
        }

        foreach ($rowsMRS as $r) {
            $ymIdx = array_search($r->ym, $months, true);
            if ($ymIdx === false) continue;
            $key = "{$r->region} – {$r->status_norm}";
            if (!isset($seriesIndex[$key])) continue;
            $seriesMonthlyRegionStatus[$seriesIndex[$key]]['data'][$ymIdx] = (float)$r->total;
        }

        // ---------- Response
        return response()->json([
            'totals' => $totals,
            'by_region' => $byRegion,
            'by_status' => $byStatus,
            'monthly' => $monthly,
            'top_clients' => $topClients,
            'by_currency' => $byCurrency,

            'by_region_status' => [
                'categories' => $regions,
                'series' => $seriesRegionStatus,
            ],
            'monthly_status' => [
                'categories' => $months,
                'series' => $seriesMonthlyStatus,
            ],
            'monthly_region_status' => [
                'categories' => $months,
                'series' => $seriesMonthlyRegionStatus,
            ],
        ]);
    }

    public function territorySales(Request $r)
    {
        // Optional filters (year/region same as your page)
        $year = $r->integer('year');
        $region = $r->input('region'); // Eastern / Central / Western

        // Map sales_man -> assigned_region (adjust names exactly as stored)
        $assigned = [
            'SOHAIB' => 'EASTERN',
            'ABDO' => 'WESTERN',
            'TAREQ' => 'CENTRAL',
        ];

        $qb = DB::table('salesorderlog')
            ->select([
                DB::raw('TRIM(UPPER(sales_man)) as sales_man'),
                DB::raw('TRIM(UPPER(region))    as region'),
            ])
            ->whereRaw("LOWER(`Status`) = 'accepted'");

        if ($year) $qb->whereYear('date_rec', $year);
        if ($region) $qb->whereRaw('TRIM(UPPER(region)) = ?', [strtoupper($region)]); // optional, usually you don’t filter here

        $rows = $qb->get();

        // Aggregate
        $stats = [];
        foreach ($rows as $r0) {
            $sm = $r0->sales_man ?: 'UNKNOWN';
            $rg = $r0->region ?: 'UNKNOWN';
            $asg = $assigned[$sm] ?? 'UNKNOWN';

            if (!isset($stats[$sm])) {
                $stats[$sm] = [
                    'sales_man' => $sm,
                    'assigned_region' => $asg,
                    'total_projects' => 0,
                    'outside_projects' => 0,
                ];
            }
            $stats[$sm]['total_projects']++;

            if ($asg !== 'UNKNOWN' && $rg !== $asg) {
                $stats[$sm]['outside_projects']++;
            }
        }

        // Compute outside_percent + flag
        $out = [];
        foreach ($stats as $sm => $s) {
            $pct = $s['total_projects'] ? round(100 * $s['outside_projects'] / $s['total_projects'], 2) : 0;
            $flag = $pct >= 50 ? 'FLAG' : ($pct >= 35 ? 'WATCH' : 'OK');
            $out[] = [
                'sales_man' => $sm,
                'assigned_region' => $s['assigned_region'],
                'total_projects' => $s['total_projects'],
                'outside_projects' => $s['outside_projects'],
                'outside_percent' => $pct,
                'flag' => $flag,
            ];
        }

        // Sort by risk (highest outside%)
        usort($out, fn($a, $b) => $b['outside_percent'] <=> $a['outside_percent']);

        return response()->json($out);
    }


    public function territoryInquiries(Request $r)
    {
        $year = $r->integer('year');
        $region = $r->input('region'); // optional filter, usually leave blank

        // Map salesman to assigned region — adjust names as you use them
        $assigned = [
            'SOHAIB' => 'EASTERN',
            'ABDO' => 'WESTERN',
            'TAREQ' => 'CENTRAL',
            // add others if needed …
        ];

        // Choose a date column (projects has date_rec; if you use quotation_date prioritize it):
        // $dateExpr = DB::raw("COALESCE(quotation_date, date_rec)");
        $dateExpr = DB::raw('date_rec');

        $qb = DB::table('projects')
            ->select([
                DB::raw('TRIM(UPPER(salesman)) as sales_man'),
                DB::raw('TRIM(UPPER(area))     as region'),
            ]);

        if ($year) $qb->whereYear($dateExpr, $year);
        if ($region) $qb->whereRaw('TRIM(UPPER(area)) = ?', [strtoupper($region)]);

        $rows = $qb->get();

        // Aggregate
        $stats = [];
        foreach ($rows as $r0) {
            $sm = $r0->sales_man ?: 'UNKNOWN';
            $rg = $r0->region ?: 'UNKNOWN';
            $asg = $assigned[$sm] ?? 'UNKNOWN';

            if (!isset($stats[$sm])) {
                $stats[$sm] = [
                    'sales_man' => $sm,
                    'assigned_region' => $asg,
                    'total_projects' => 0,
                    'outside_projects' => 0,
                ];
            }
            $stats[$sm]['total_projects']++;

            if ($asg !== 'UNKNOWN' && $rg !== $asg) {
                $stats[$sm]['outside_projects']++;
            }
        }

        // Compute %
        $out = [];
        foreach ($stats as $s) {
            $pct = $s['total_projects'] ? round(100 * $s['outside_projects'] / $s['total_projects'], 2) : 0.0;
            $flag = $pct >= 50 ? 'FLAG' : ($pct >= 35 ? 'WATCH' : 'OK');
            $out[] = $s + ['outside_percent' => $pct, 'flag' => $flag];
        }

        usort($out, fn($a, $b) => $b['outside_percent'] <=> $a['outside_percent']);

        return response()->json($out);
    }
}
