<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderManagerController extends Controller
{
    public function index()
    {
        return view('sales_orders.manager.manager_log');
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    /** Region → salesperson aliases (adjust to your spellings) */
    protected function salesAliasesForRegion(?string $regionNorm): array
    {
        return match ($regionNorm) {
            'eastern' => ['SOHAIB', 'SOAHIB'],
            'central' => ['TARIQ', 'TAREQ', 'JAMAL','ABUMERHI', 'MMERHI', 'MERHI', 'MABUMERHI','M.MERHI','M.Abu Merhi','M.Abu'],                 // ← add JAMAL
            'western' => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],         // ← add AHMED
            default => [],
        };
    }

    public function salesmen(Request $r)
    {
        $u = $r->user();

        // ✅ only GM/Admin can access this list
        if (!$this->isManagerUser($u)) {
            return response()->json([], 403);
        }

        // ✅ hardcoded canonical list (temporary)
        return response()->json([
            'SOHAIB',
            'TAREQ',
            'ABU MERHI',// (covers TAREQ alias in your resolver)
            'JAMAL',
            'ABDO',
            'AHMED',
        ]);
    }

    /** Map canonical salesperson → home region (lowercase) */
    protected function homeRegionBySalesperson(): array
    {
        return [
            // Eastern
            'SOHAIB' => 'eastern',
            'SOAHIB' => 'eastern',

            // Central
            'TARIQ' => 'central',
            'TAREQ' => 'central',
            'JAMAL' => 'central',          // ← add JAMAL
            'ABU MERHI' => 'central',
            'ABUMERHI'  => 'central',
            'MMERHI'    => 'central',
            'MERHI'     => 'central',
            'MABUMERHI' => 'central',
            'M.Abu Merhi'     => 'central',
            'M.Abu' => 'central',

            // Western
            'ABDO' => 'western',
            'ABDUL' => 'western',
            'ABDOU' => 'western',
            'AHMED' => 'western',          // ← add AHMED
        ];
    }

    /** Return the canonical key we use to match names (UPPER + no spaces) */
    protected function canonSalesKey(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$name));
    }





    /** Is user GM/Admin? */
    protected function isManagerUser($u): bool
    {
        return $u && method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['gm', 'admin']);
    }

    /**
     * Decide the "effective aliases" for this request:
     * - Sales user: their own aliases only
     * - GM/Admin: if salesman query provided => that salesman's aliases
     *            else => [] meaning "no salesperson filter" (see all)
     */
    protected function effectiveAliases(Request $r): array
    {
        $u = $r->user();
        $isAdmin = $this->isManagerUser($u);

        // Sales user: always restricted to self
        if (!$isAdmin) {
            $userKey = $this->resolveSalespersonCanonical($u->name ?? null);
            return $this->aliasesForCanonical($userKey);
        }

        // GM/Admin: optional salesman filter
        $sel = trim((string)$r->query('salesman', ''));
        if ($sel === '') return []; // no filter => see all

        $selCanon = $this->resolveSalespersonCanonical($sel);
        if (!$selCanon) return [];  // unknown => treat as all (or change to ['__none__'] if you want zero results)

        return $this->aliasesForCanonical($selCanon);
    }


    /** Apply salesperson aliases to Sales Source column */
    protected function applySalesAliasesToSol($q, array $aliases): void
    {
        if (empty($aliases)) return;

        $q->where(function ($qq) use ($aliases) {
            foreach ($aliases as $a) {
                $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$a]);
            }
        });
    }

    /** Apply salesperson aliases to Projects.salesperson column */
    protected function applySalesAliasesToProjects($q, array $aliases): void
    {
        if (empty($aliases)) return;

        $q->where(function ($qq) use ($aliases) {
            foreach ($aliases as $a) {
                $qq->orWhereRaw("REPLACE(UPPER(TRIM(p.salesperson)),' ','') = ?", [$a]);
            }
        });
    }
    /** NEW: normalize any login/display name to a canonical salesperson key (handles aliases) */
    protected function resolveSalespersonCanonical(?string $name): ?string
    {
        if (!$name) return null;

        // First token and full key (no spaces)
        $first = strtoupper(trim(explode(' ', $name)[0] ?? ''));
        $fullKey = $this->canonSalesKey($name);

        $canonMap = [
            'SOHAIB' => 'SOHAIB',
            'SOAHIB' => 'SOHAIB',
            'TARIQ' => 'TARIQ',
            'TAREQ' => 'TARIQ',
            'JAMAL' => 'JAMAL',
            'ABDO' => 'ABDO',
            'ABDUL' => 'ABDO',
            'ABDOU' => 'ABDO',
            'AHMED' => 'AHMED',
            'ABUMERHI'  => 'ABU MERHI',
            'MABUMERHI' => 'ABU MERHI',
            'MMERHI'    => 'ABU MERHI',
            'MERHI'     => 'ABU MERHI',
            'M.MERHI'    => 'ABU MERHI',
            'M.Abu Merhi'     => 'ABU MERHI',
        ];

        if (isset($canonMap[$first])) return $canonMap[$first];
        if (isset($canonMap[$fullKey])) return $canonMap[$fullKey];

        foreach (array_keys($canonMap) as $k) {
            if (str_starts_with($fullKey, $k)) return $canonMap[$k];
        }
        return null;
    }


    /** Aliases for a canonical salesperson, using existing helpers */
    /** Aliases for a canonical salesperson, using existing helpers */
    protected function aliasesForCanonical(?string $canon): array
    {
        if (!$canon) return [];

        // Find their home region from your existing map
        $homeMap = $this->homeRegionBySalesperson();
        $home = $homeMap[$canon] ?? null;

        // Return region aliases
        $aliases = $home ? $this->salesAliasesForRegion($home) : [];

        // Ensure canonical is included
        $aliases[] = $canon;

        // Unique
        $aliases = array_values(array_unique(array_filter($aliases)));

        return $aliases;
    }


    /** Family filter mapping (LIKEs; case-insensitive; robust) */
    protected function applyFamilyFilter($q, string $family): void
    {
        $norm = "LOWER(REPLACE(REPLACE(TRIM(s.`Products`), ' ', ''), '/', ''))";
        $f = strtolower(trim($family));
        $needle = str_replace([' ', '/'], '', $f);

        $likeAny = function ($patterns) use ($q, $norm) {
            $q->where(function ($qq) use ($norm, $patterns) {
                foreach ((array)$patterns as $p) {
                    $qq->orWhereRaw("$norm LIKE ?", ['%' . $p . '%']);
                }
            });
        };

        switch (true) {
            case in_array($f, ['access doors', 'access door']):
                $likeAny(['accessdoor', 'accessdoors']);
                break;
            case in_array($f, ['actuators', 'actuator']):
                $likeAny(['actuator', 'actuators']);
                break;
            case in_array($f, ['louvers', 'louver']):
                $likeAny(['louver', 'louvers']);
                break;
            case in_array($f, ['round duct', 'round ducts', 'spiral duct', 'spiral']):
                $likeAny(['roundduct', 'roundducts', 'spiralduct', 'spiral']);
                break;
            case in_array($f, ['semi', 'semi flexible', 'semi-flexible', 'semiflexible', 'semiflex']):
                $likeAny(['semi', 'semiflex', 'semiflexible']);
                break;
            case in_array($f, ['volume dampers', 'volume damper', 'vd']):
                $likeAny(['volumedamper', 'volumedampers', 'vd']);
                break;
            case in_array($f, [
                'fire / smoke dampers', 'fire/smoke dampers', 'fire&smoke dampers', 'fire smoke dampers',
                'fire damper', 'smoke damper', 'fire dampers', 'smoke dampers'
            ]):
                $q->where(function ($qq) use ($norm) {
                    $qq->whereRaw("$norm LIKE ?", ['%firedamper%'])
                        ->orWhereRaw("$norm LIKE ?", ['%smokedamper%'])
                        ->orWhereRaw("$norm LIKE ?", ['%firesmokedamper%'])
                        ->orWhereRaw("$norm LIKE ?", ['%fireandsmokedamper%']);
                });
                break;
            case in_array($f, ['sound attenuator', 'sound attenuators', 'attenuator', 'attenuators', 'silencer', 'silencers', 'sound']):
                $likeAny(['attenuator', 'attenuators', 'silencer', 'silencers']);
                break;
            case in_array($f, ['dampers', 'damper']):
                $likeAny(['damper', 'dampers']);
                break;
            case in_array($f, ['ductwork', 'ductworks', 'duct', 'ducts', 'rectangular duct', 'rect duct']):
                $likeAny(['duct', 'ducts', 'rectangularduct', 'rectduct', 'ductwork', 'roundduct']);
                break;
            case in_array($f, ['accessories', 'accessory']):
                $likeAny(['accessory', 'accessories']);
                break;
            default:
                if ($needle !== '') $q->whereRaw("$norm LIKE ?", ['%' . $needle . '%']);
        }
    }

    /**
     * Shared SOL base (region + date filters only).
     * Returns [$q, $dateExprSql, $valExprSql, $appliedRegionForReturn, $family, $status, $userRegionNorm]
     */
    protected function base(Request $r)
    {
        $u = $r->user();
        $isAdmin = $u && method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['gm', 'admin']);

        // Canonical salesperson for the logged-in user
        $userKey = $this->resolveSalespersonCanonical($u->name ?? null);

        // Build alias list from your existing helpers (no new maps)
        $loggedUserAliases = !$isAdmin ? $this->aliasesForCanonical($userKey) : [];

        // Region chip: apply ONLY if explicitly chosen
        $regionParam = strtolower(trim((string)$r->query('region', '')));
        $applyRegion = in_array($regionParam, ['eastern', 'central', 'western'], true) ? $regionParam : null;

        $q = DB::table('salesorderlog as s');

        // Region filter (optional)
        if ($applyRegion) {
            $q->whereRaw('LOWER(TRIM(s.region)) = ?', [$applyRegion]);
        }

        // Default salesperson scope for NON-ADMINS
        if (!$isAdmin && !empty($loggedUserAliases)) {
            $q->where(function ($qq) use ($loggedUserAliases) {
                foreach ($loggedUserAliases as $alias) {
                    $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                }
            });
        }

        // Dates
        $dateExprSql = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";
        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->query('from') ?: '1900-01-01';
            $to = $r->query('to') ?: '2999-12-31';
            $q->whereRaw("$dateExprSql BETWEEN ? AND ?", [$from, $to]);
        } else {
            if ($r->filled('year')) $q->whereRaw("YEAR($dateExprSql)  = ?", [(int)$r->query('year')]);
            if ($r->filled('month')) $q->whereRaw("MONTH($dateExprSql) = ?", [(int)$r->query('month')]);
        }

        $valExprSql = "COALESCE(s.`PO Value`, 0)";
        $family = trim((string)$r->query('family', ''));
        $status = trim((string)$r->query('status', ''));

        // Return tuple includes aliases so other endpoints don’t rebuild anything
        return [$q, $dateExprSql, $valExprSql, $applyRegion, $family, $status, $loggedUserAliases, $isAdmin];
    }


    /* =========================================================
     * DataTable
     * ========================================================= */
    /* =========================================================
     * DataTable  — salesperson-first, region chip on project_region
     * ========================================================= */
    public function datatable(Request $r)
    {
        // Build a clean SOL base (no selects yet)
        $soDateExpr = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";

        // Get aliases the same way as in kpis()
        [$tmpBase, , , $applyRegion, $family, $status, $loggedUserAliases] = $this->base($r);
        $user = $r->user();
        $userKey = $this->resolveSalespersonCanonical($user->name ?? null);
//        $aliases = !empty($loggedUserAliases) ? $loggedUserAliases : ($userKey ? [$userKey] : []);
        $aliases = $this->effectiveAliases($r);
        // Region chip → project_region
        $regionChip = strtolower(trim((string)$r->query('region', '')));
        $regionFilter = in_array($regionChip, ['eastern', 'central', 'western'], true) ? $regionChip : '';

        // Family / Status chips
        $familySel = strtolower(trim((string)$r->query('family', $family ?? '')));
        $selectedStatus = strtolower(trim((string)$r->query('status', $status ?? '')));
        $filterByStatus = $selectedStatus !== '';

        // Base conditions ONLY
        $base = DB::table('salesorderlog as s')
            ->whereNull('s.deleted_at')
            ->tap(function ($q) use ($aliases) {
                $this->applySalesAliasesToSol($q, $aliases);
            })
            ->when($regionFilter !== '', fn($q) => $q->whereRaw('LOWER(TRIM(s.project_region)) = ?', [$regionFilter]))
            ->when($familySel !== '', function ($q) use ($familySel) {
                // ✅ use your robust helper
                $this->applyFamilyFilter($q, $familySel);
            })
            ->when($filterByStatus, fn($q) => $q->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]))
            // date filters
            ->when($r->filled('year'), fn($q) => $q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q) => $q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'), fn($q) => $q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'), fn($q) => $q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]));

        // Selects for DataTables
        $q = $base->select([
            's.id',
            DB::raw('s.`PO. No.`          AS po_no'),
            's.date_rec',
            's.region',
            DB::raw('s.`Client Name`      AS client_name'),
            DB::raw('s.`project_region`   AS project_region'),
            DB::raw('s.`Project Name`     AS project_name'),
            DB::raw('s.`Project Location` AS project_location'),
            DB::raw('s.`Products`         AS product_family'),
            's.value_with_vat',
            DB::raw('s.`PO Value`         AS po_value'),
            DB::raw('s.`Status`           AS status'),
            DB::raw('s.`Remarks`          AS remarks'),
            DB::raw('s.`Sales Source`     AS salesperson'),
        ]);

        $dt = DataTables::of($q)
            ->addIndexColumn()

            // Global search
            ->filter(function ($query) use ($r) {
                $search = strtolower((string)$r->input('search.value', ''));
                if ($search === '') return;

                $query->where(function ($qq) use ($search) {
                    $qq->orWhereRaw('LOWER(s.`PO. No.`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Client Name`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`project_region`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Project Location`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Project Name`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Products`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Status`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Sales Source`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`Remarks`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(s.`region`) LIKE ?', ["%$search%"])
                        ->orWhereRaw('DATE_FORMAT(s.`date_rec`,"%Y-%m-%d") LIKE ?', ["%$search%"]);
                });
            })

            // Order mapping
            ->orderColumn('po_no', 's.`PO. No.` $1')
            ->orderColumn('date_rec', 's.`date_rec` $1')
            ->orderColumn('region', 's.`region` $1')
            ->orderColumn('client_name', 's.`Client Name` $1')
            ->orderColumn('project_region', 's.`project_region` $1')
            ->orderColumn('project_name', 's.`Project Name` $1')
            ->orderColumn('project_location', 's.`Project Location` $1')
            ->orderColumn('product_family', 's.`Products` $1')
            ->orderColumn('value_with_vat', 's.`value_with_vat` $1')
            ->orderColumn('po_value', 's.`PO Value` $1')
            ->orderColumn('status', 's.`Status` $1')
            ->orderColumn('remarks', 's.`Remarks` $1')
            ->orderColumn('salesperson', 's.`Sales Source` $1')

            // Column-specific filters
            ->filterColumn('po_no', fn($q, $k) => $q->whereRaw('s.`PO. No.` LIKE ?', ["%$k%"]))
            ->filterColumn('client_name', fn($q, $k) => $q->whereRaw('s.`Client Name` LIKE ?', ["%$k%"]))
            ->filterColumn('project_region', fn($q, $k) => $q->whereRaw('s.`project_region` LIKE ?', ["%$k%"]))
            ->filterColumn('project_location', fn($q, $k) => $q->whereRaw('s.`Project Location` LIKE ?', ["%$k%"]))
            ->filterColumn('project_name', fn($q, $k) => $q->whereRaw('s.`Project Name` LIKE ?', ["%$k%"]))
            ->filterColumn('product_family', fn($q, $k) => $q->whereRaw('s.`Products` LIKE ?', ["%$k%"]))
            ->filterColumn('status', fn($q, $k) => $q->whereRaw('s.`Status` LIKE ?', ["%$k%"]))
            ->filterColumn('salesperson', fn($q, $k) => $q->whereRaw('s.`Sales Source` LIKE ?', ["%$k%"]))
            ->filterColumn('remarks', fn($q, $k) => $q->whereRaw('s.`Remarks` LIKE ?', ["%$k%"]))
            ->filterColumn('region', fn($q, $k) => $q->whereRaw('s.`region` LIKE ?', ["%$k%"]))
            ->filterColumn('date_rec', fn($q, $k) => $q->whereRaw('DATE_FORMAT(s.`date_rec`,"%Y-%m-%d") LIKE ?', ["%$k%"]))

            // numeric cleanup
            ->editColumn('value_with_vat', fn($row) => (int)($row->value_with_vat ?? 0))
            ->editColumn('po_value', fn($row) => (int)($row->po_value ?? 0));

        return $dt->toJson();
    }


    /* =========================================================
     * KPIs / Charts JSON
     * ========================================================= */

    /* =========================================================
     * KPIs / Charts JSON
     * ========================================================= */
    /* =========================================================
 * KPIs / Charts JSON  —  Salesperson-first, Region chip filter
 * ========================================================= */
    public function kpis(Request $r)
    {
        /* ---------- user & salesperson aliases ---------- */
        $user = $r->user();
        $isAdmin = $this->isManagerUser($user);
        $userKey = $this->resolveSalespersonCanonical($user->name ?? null);

        // Pull base() just for shared helpers, but we’ll drive scoping ourselves
        [$base, $dateExprSql, $valExprSql, $applyRegion, $family, $status, $loggedUserAliases] = $this->base($r);
//        $aliases = $loggedUserAliases ?: ($userKey ? [$userKey] : []);
        $aliases = $this->effectiveAliases($r);
        /* ---------- region from UI chip ONLY ---------- */
        $regionChip = strtolower(trim((string)$r->query('region', '')));   // '', 'all', 'eastern', 'central', 'western'
        $regionFilter = in_array($regionChip, ['eastern', 'central', 'western'], true) ? $regionChip : '';

        /* ---------- other filters ---------- */
        $familySel = strtolower(trim((string)$family));
        $selectedStatus = strtolower(trim((string)$status));
        $filterByStatus = $selectedStatus !== '';

        /* ---------- normalized numeric & quote expressions ---------- */
        $poNumExpr = "CAST(REPLACE(REPLACE(REPLACE(s.`PO Value`, 'SAR',''), ',', ''), ' ', '') AS DECIMAL(15,2))";
        // For “unique quotations” we normalize to base (drop .MH / .R*)
        $qnoBaseExpr = "UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(TRIM(s.`Quote No.`), '.MH', 1), '.R', 1))";

        /* =========================================================
         * A) SALES ORDER LOG (PO world) — salesperson + region(project_region)
         * ========================================================= */

//
//        $sum = DB::table('salesorderlog as s')
//            ->whereIn(DB::raw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','')"), ['SOHAIB', 'SOAHIB'])
//            ->selectRaw("SUM(CAST(REPLACE(REPLACE(REPLACE(s.`PO Value`, 'SAR',''), ',', ''), ' ', '') AS DECIMAL(15,2))) AS t")
//            ->value('t');
        $soDateExpr = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";


        $solBase = DB::table('salesorderlog as s')
            ->whereNull('s.deleted_at')
            ->tap(function ($q) use ($aliases) {
                $this->applySalesAliasesToSol($q, $aliases);
            })
            ->when($regionFilter !== '', fn($q) => $q->whereRaw('LOWER(TRIM(s.project_region)) = ?', [$regionFilter]))
            ->when($familySel !== '', function ($q) use ($familySel) {
                $this->applyFamilyFilter($q, $familySel);
            })
            ->when($filterByStatus, fn($q) => $q->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]))
            // Date filters
            ->when($r->filled('year'), fn($q) => $q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q) => $q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'), fn($q) => $q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'), fn($q) => $q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]));

        // ------ PO top cards: sum ALL receipts + count unique quotations ------
        $soTotalsScoped = (clone $solBase)
            ->selectRaw("COUNT(*) AS receipts_cnt")                            // every receipt row
            ->selectRaw("COUNT(DISTINCT $qnoBaseExpr) AS unique_quotes_cnt")   // unique quotations (base)
            ->selectRaw("COALESCE(SUM($poNumExpr),0) AS orders_sum")           // total SAR (adds split POs)
            ->first();

        // ------ PO monthly (sum all receipts by month) ------
        $poByMonthRows = (clone $solBase)
            ->selectRaw("DATE_FORMAT($soDateExpr,'%Y-%m') AS ym, COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('ym')->orderBy('ym')->get();
        $monthsP = $poByMonthRows->pluck('ym')->values()->all();
        $poByMonth = $poByMonthRows->pluck('val', 'ym')->all();
        $labels = array_values(array_unique($monthsP));
        $values = array_map(fn($ym) => (float)($poByMonth[$ym] ?? 0.0), $labels);

        // ------ Top products (PO) ------
        $topProductsKpi = (clone $solBase)
            ->selectRaw("COALESCE(s.`Products`,'Unknown') AS product, COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(10)->get();

        // ------ Status pie (PO) ------
        $statusPieRows = (clone $solBase)
            ->selectRaw("TRIM(COALESCE(s.`Status`,'Unknown')) AS status, COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('status')->get();
        $statusPie = $statusPieRows->map(fn($r2) => ['name' => $r2->status, 'y' => (float)$r2->val])->values()->toArray();

        // ------ Projects region pie (% by project_region) ------
        $projRegionRows = (clone $solBase)
            ->selectRaw("TRIM(COALESCE(s.`project_region`,'Unknown')) AS projects_region")
            ->selectRaw("COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('project_region')->get();
        $totalProjectsRegionVal = max(1.0, (float)$projRegionRows->sum('val'));
        $projectsRegionPie = $projRegionRows->map(function ($r) use ($totalProjectsRegionVal) {
            $v = (float)$r->val;
            return ['name' => (string)$r->projects_region, 'y' => round(($v / $totalProjectsRegionVal) * 100, 2), 'value' => $v];
        })->values()->toArray();

        /* =========================================================
         * B) PROJECTS (Quotation world) — salesperson + region(area)
         * ========================================================= */
        $projDateExpr = "COALESCE(
        STR_TO_DATE(p.quotation_date,'%Y-%m-%d'),
        STR_TO_DATE(p.quotation_date,'%d-%m-%Y'),
        STR_TO_DATE(p.quotation_date,'%d/%m/%Y'),
        DATE(p.created_at)
    )";

        // Conditions ONLY (no selects) — safe to add SUM/COUNT repeatedly
        $projBaseCond = DB::table('projects as p')
            ->whereNull('p.deleted_at')
            ->tap(function ($q) use ($aliases) {
                $this->applySalesAliasesToProjects($q, $aliases);
            })
            ->when($regionFilter !== '', fn($q) => $q->whereRaw('LOWER(TRIM(p.area)) = ?', [$regionFilter]))
            ->when($familySel !== '', fn($q) => $q->whereRaw('LOWER(p.atai_products) LIKE ?', ['%' . strtolower($familySel) . '%']))
            ->when($r->filled('year'), fn($q) => $q->whereRaw("YEAR($projDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q) => $q->whereRaw("MONTH($projDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'), fn($q) => $q->whereRaw("$projDateExpr >= ?", [$r->input('from')]))
            ->when($r->filled('to'), fn($q) => $q->whereRaw("$projDateExpr <= ?", [$r->input('to')]));


        // Salesperson totals (all project types)
        $projTotalsForUser = (clone $projBaseCond)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(COALESCE(p.quotation_value,p.price,0)),0) AS total_quotation_value')
            ->first();

        // In-Hand denominator (conditions only)
        $inCond = (clone $projBaseCond)
            ->whereRaw("p.quotation_no IS NOT NULL AND TRIM(p.quotation_no) <> ''")
            ->whereRaw("LOWER(TRIM(REPLACE(REPLACE(p.project_type,'-',' '),'_',' '))) REGEXP '^in[[:space:]]*hand$'");
        $projInHandSum = (float)(clone $inCond)->selectRaw('COALESCE(SUM(COALESCE(p.quotation_value,p.price,0)),0) AS t')->value('t');
        $projInHandCnt = (int)(clone $inCond)->count();

        // Bidding denominator (conditions only)
        $bdCond = (clone $projBaseCond)
            ->whereRaw("p.quotation_no IS NOT NULL AND TRIM(p.quotation_no) <> ''")
            ->whereRaw("LOWER(TRIM(p.project_type)) = 'bidding'");
        $projBiddingSum = (float)(clone $bdCond)->selectRaw('COALESCE(SUM(COALESCE(p.quotation_value,p.price,0)),0) AS t')->value('t');
        $projBiddingCnt = (int)(clone $bdCond)->count();

        $projectsSums = [
            'inhand' => ['count' => $projInHandCnt, 'sum_sar' => $projInHandSum],
            'bidding' => ['count' => $projBiddingCnt, 'sum_sar' => $projBiddingSum],
        ];

        /* ===== Scopes for JOIN (need selected columns now) ===== */
        $projQuoteCol = 'quotation_no';

        $projInHandScope = (clone $inCond)
            ->selectRaw("p.id, p.client_name, p.project_name, p.area, p.salesperson")
            ->selectRaw("TRIM(p.$projQuoteCol) AS quotation_no")
            ->selectRaw("COALESCE(CAST(p.quotation_value AS DECIMAL(18,2)),0) AS quotation_value")
            ->selectRaw("
            UPPER(
              SUBSTRING_INDEX(
                SUBSTRING_INDEX(TRIM(p.$projQuoteCol), '.MH', 1), '.R', 1
              )
            ) AS p_base
        ");

        $projBiddingScope = (clone $bdCond)
            ->selectRaw("p.id, p.client_name, p.project_name, p.area, p.salesperson")
            ->selectRaw("TRIM(p.$projQuoteCol) AS quotation_no")
            ->selectRaw("COALESCE(CAST(p.quotation_value AS DECIMAL(18,2)),0) AS quotation_value")
            ->selectRaw("
            UPPER(
              SUBSTRING_INDEX(
                SUBSTRING_INDEX(TRIM(p.$projQuoteCol), '.MH', 1), '.R', 1
              )
            ) AS p_base
        ");

        /* =========================================================
         * C) JOIN PO ↔ Projects (prefix match) for per-gauge PO sums
         * ========================================================= */
        $solScope = (clone $solBase)
            ->selectRaw("s.id AS sol_id")
            ->selectRaw("s.`Quote No.` AS sol_quote_no")
            ->selectRaw("s.project_region AS sol_region")
            ->selectRaw("s.`Sales Source` AS sol_sales_source")
            ->selectRaw("$poNumExpr AS po_value")              // numeric PO value (each receipt)
            ->selectRaw("UPPER(TRIM(s.`Quote No.`)) AS s_qno");

        // In-Hand join
        $projInSub = DB::query()->fromSub($projInHandScope, 'p');
        $solSub = DB::query()->fromSub($solScope, 's');
        $inJoinBase = DB::query()
            ->fromSub($projInSub, 'p')
            ->joinSub($solSub, 's', function ($j) {
                $j->on(DB::raw('1'), '=', DB::raw('1'))
                    ->whereRaw("s.s_qno LIKE CONCAT(p.p_base, '%') OR p.p_base LIKE CONCAT(s.s_qno, '%')");
            });
        $inhandTotals = (clone $inJoinBase)
            ->selectRaw("COUNT(*) AS matched_rows, COALESCE(SUM(s.po_value),0) AS total_po_value")
            ->first();

        // Bidding join
        $projBidSub = DB::query()->fromSub($projBiddingScope, 'p');
        $bdJoinBase = DB::query()
            ->fromSub($projBidSub, 'p')
            ->joinSub($solSub, 's', function ($j) {
                $j->on(DB::raw('1'), '=', DB::raw('1'))
                    ->whereRaw("s.s_qno LIKE CONCAT(p.p_base, '%') OR p.p_base LIKE CONCAT(s.s_qno, '%')");
            });
        $biddingTotals = (clone $bdJoinBase)
            ->selectRaw("COUNT(*) AS matched_rows, COALESCE(SUM(s.po_value),0) AS total_po_value")
            ->first();

        $inhandJoinResult = [
            'rows' => [],
            'matched_rows' => (int)($inhandTotals->matched_rows ?? 0),
            'po_sum_sar' => (float)($inhandTotals->total_po_value ?? 0.0),
        ];
        $biddingJoinResult = [
            'rows' => [],
            'matched_rows' => (int)($biddingTotals->matched_rows ?? 0),
            'po_sum_sar' => (float)($biddingTotals->total_po_value ?? 0.0),
        ];

        $conversion_gauge_inhand_bidding = [
            'inhand' => [
                'quotes_total_sar' => (float)$projectsSums['inhand']['sum_sar'],
                'po_total_sar' => (float)$inhandJoinResult['po_sum_sar'],
                'pct' => 0.0,
                'rows' => [],
            ],
            'bidding' => [
                'quotes_total_sar' => (float)$projectsSums['bidding']['sum_sar'],
                'po_total_sar' => (float)$biddingJoinResult['po_sum_sar'],
                'pct' => 0.0,
                'rows' => [],
            ],
        ];
        foreach (['inhand', 'bidding'] as $k) {
            $qv = $conversion_gauge_inhand_bidding[$k]['quotes_total_sar'];
            $pv = $conversion_gauge_inhand_bidding[$k]['po_total_sar'];
            $conversion_gauge_inhand_bidding[$k]['pct'] = $qv > 0 ? round(100 * $pv / $qv, 2) : 0.0;
        }

        /* =========================================================
         * D) Product-wise monthly (stacked) + total spline (PO)
         * ========================================================= */
        $prodRows = (clone $solBase)
            ->selectRaw("DATE_FORMAT($soDateExpr,'%Y-%m') AS ym")
            ->selectRaw("COALESCE(s.`Products`,'Unknown') AS product")
            ->selectRaw("COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('ym', 'product')->orderBy('ym')->get();

        $months = $prodRows->pluck('ym')->unique()->sort()->values()->all();
        $totalsByProduct = $prodRows->groupBy('product')->map(fn($g) => (float)$g->sum('val'));
        $topN = 8;
        $topProductsForMonthlyValue = $totalsByProduct->sortDesc()->keys()->take($topN)->values();
        $otherNames = $totalsByProduct->keys()->diff($topProductsForMonthlyValue);

        $idx = [];
        foreach ($prodRows as $r3) {
            $isTop = in_array($r3->product, $topProductsForMonthlyValue->all(), true);
            $pName = $isTop ? $r3->product : ($otherNames->isNotEmpty() ? 'Others' : $r3->product);
            $idx[$r3->ym][$pName] = ($idx[$r3->ym][$pName] ?? 0.0) + (float)$r3->val;
        }

        $productsForSeries = $topProductsForMonthlyValue->all();
        if ($otherNames->isNotEmpty() && !in_array('Others', $productsForSeries, true)) $productsForSeries[] = 'Others';

        $productSeriesMonthly = [];
        foreach ($productsForSeries as $pn) {
            $data = [];
            foreach ($months as $ym) $data[] = (float)($idx[$ym][$pn] ?? 0.0);
            $productSeriesMonthly[] = ['type' => 'column', 'name' => $pn, 'stack' => 'Products', 'data' => $data];
        }

        $totalPoints = [];
        $prev = null;
        foreach ($months as $ym) {
            $total = 0.0;
            foreach ($productsForSeries as $pn) $total += (float)($idx[$ym][$pn] ?? 0.0);
            $mom = ($prev && $prev > 0) ? round((($total - $prev) / $prev) * 100, 1) : 0.0;
            $totalPoints[] = ['y' => $total, 'mom' => $mom];
            $prev = $total;
        }
        $monthlyProductValue = [
            'categories' => $months,
            'series' => array_merge($productSeriesMonthly, [[
                'type' => 'spline', 'name' => 'Total', 'yAxis' => 1, 'data' => $totalPoints
            ]]),
        ];

        /* =========================================================
         * E) Product clusters (horizontal) + monthly by product (PO)
         * ========================================================= */
        $prodTotalsCluster = (clone $solBase)
            ->selectRaw("TRIM(COALESCE(s.`Products`,'Unknown')) AS product, COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(12)->get();
        $productCats = $prodTotalsCluster->pluck('product')->values()->all();

        $rowsCluster = (clone $solBase)
            ->when(!empty($productCats), fn($q) => $q->whereIn(DB::raw("TRIM(COALESCE(s.`Products`,'Unknown'))"), $productCats))
            ->selectRaw("TRIM(COALESCE(s.`Products`,'Unknown')) AS product,
                     TRIM(COALESCE(s.`Status`,'Unknown'))   AS status,
                     COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('product', 'status')->get();

        $statusOrder = ['Accepted', 'Pre-Acceptance', 'Waiting', 'Rejected', 'Cancelled', 'Unknown'];
        $matrix = [];
        foreach ($statusOrder as $st) $matrix[$st] = array_fill(0, count($productCats), 0.0);
        foreach ($rowsCluster as $r4) {
            $st = in_array($r4->status, $statusOrder, true) ? $r4->status : 'Unknown';
            $i = array_search($r4->product, $productCats, true);
            if ($i !== false) $matrix[$st][$i] = (float)$r4->val;
        }
        $productClusterSeries = [];
        foreach ($statusOrder as $st) {
            $productClusterSeries[] = ['type' => 'bar', 'name' => $st, 'data' => $matrix[$st]];
        }

        // Monthly by status (PO)
        $monthlyStatus = (clone $solBase)
            ->selectRaw("DATE_FORMAT($soDateExpr, '%Y-%m') AS ym,
                     TRIM(COALESCE(s.`Status`,'Unknown')) AS status,
                     COALESCE(SUM($poNumExpr),0) AS val")
            ->groupBy('ym', 'status')->orderBy('ym')->get();
        $monthsCol = $monthlyStatus->pluck('ym')->unique()->sort()->values();
        $monthsMM = array_values(array_filter(
            array_map(fn($x) => is_scalar($x) ? (string)$x : null, $monthsCol->all()),
            fn($x) => $x !== null && $x !== ''
        ));
        $bySt = [];
        foreach ($statusOrder as $st) {
            $bySt[$st] = array_fill_keys($monthsMM, 0.0);
        }
        foreach ($monthlyStatus as $r2) {
            $st = in_array($r2->status, $statusOrder, true) ? $r2->status : 'Unknown';
            if (!isset($bySt[$st])) $bySt[$st] = array_fill_keys($monthsMM, 0.0);
            $bySt[$st][$r2->ym] = (float)$r2->val;
        }
        $barSeries = [];
        foreach ($statusOrder as $st) $barSeries[] = ['type' => 'column', 'name' => $st, 'data' => array_values($bySt[$st])];

        /* =========================================================
         * F) PO vs Forecast vs Target (PO)
         * ========================================================= */

        // Forecast for the logged-in salesperson (aliases), no region filter
        $forecastByMonth = [];
        $fcBase = DB::table('forecast as f');

        if (!empty($aliases)) {
            $fcBase->where(function ($qq) use ($aliases) {
                foreach ($aliases as $alias) {
                    $qq->orWhereRaw("REPLACE(UPPER(TRIM(f.`salesman`)),' ','') = ?", [$alias]);
                }
            });
        }
        if ($familySel !== '') $fcBase->whereRaw('LOWER(f.product_family) LIKE ?', ['%' . strtolower($familySel) . '%']);
        if ($r->filled('year')) $fcBase->where('f.year', (int)$r->input('year'));
        if ($r->filled('month')) $fcBase->where('f.month_no', (int)$r->input('month'));
        $fcDateExpr = "DATE(CONCAT(f.year,'-',LPAD(f.month_no,2,'0'),'-01'))";
        if ($r->filled('from')) $fcBase->whereRaw("$fcDateExpr >= ?", [$r->input('from')]);
        if ($r->filled('to')) $fcBase->whereRaw("$fcDateExpr <= ?", [$r->input('to')]);

        $forecastRows = $fcBase
            ->selectRaw("CONCAT(f.year,'-',LPAD(f.month_no,2,'0')) AS ym")
            ->selectRaw("COALESCE(SUM(f.value_sar),0) AS val")
            ->groupBy('ym')->orderBy('ym')->get();
        foreach ($forecastRows as $fr) $forecastByMonth[$fr->ym] = (float)$fr->val;

        // Union months from PO and Forecast
        $monthsAll = collect($monthsP)->merge(array_keys($forecastByMonth))
            ->unique()->sort()->values()->all();

        // Target tied to the LOGGED-IN user’s region (not the chip)
        $annualTargets = [
            'eastern' => 35_000_000.0,
            'central' => 37_000_000.0,
            'western' => 30_000_000.0,
        ];
        $homeMap = $this->homeRegionBySalesperson();
        $userKey = $this->resolveSalespersonCanonical($user->name ?? null);
        $userRegionNorm = strtolower(trim((string)($user->region ?? '')));
        $userRegionNorm = in_array($userRegionNorm, ['eastern', 'central', 'western'], true) ? $userRegionNorm : null;
        $userRegionForTarget = $userRegionNorm ?: ($userKey && isset($homeMap[$userKey]) ? $homeMap[$userKey] : 'eastern');

        $targetPerMonth = $r->filled('monthly_target')
            ? (float)$r->input('monthly_target')
            : (($annualTargets[$userRegionForTarget] ?? 0.0) / 12.0);

        $seriesPo = $seriesFc = $seriesTgt = [];
        foreach ($monthsAll as $ym) {
            $seriesPo[] = (float)($poByMonth[$ym] ?? 0.0);
            $seriesFc[] = (float)($forecastByMonth[$ym] ?? 0.0);
            $seriesTgt[] = (float)$targetPerMonth;
        }

        $poFcTarget = [
            'categories' => $monthsAll,
            'series' => [
                ['type' => 'column', 'name' => 'PO (SAR)', 'data' => $seriesPo],
                ['type' => 'column', 'name' => 'Forecast (SAR)', 'data' => $seriesFc],
                ['type' => 'column', 'name' => 'Target (SAR)', 'data' => $seriesTgt],
            ],
            'monthly_target' => $targetPerMonth,
            'target_meta' => [
                'user_region_used' => $userRegionForTarget,
                'annual_target' => (float)($annualTargets[$userRegionForTarget] ?? 0.0),
                'override' => $r->filled('monthly_target'),
            ],
            'forecast_meta' => [
                'salesperson_aliases_used' => $loggedUserAliases,
                'scoped_by_region' => false,
            ],
        ];

        /* =========================================================
         * G) Shared PO pool conversion vs quotations (salesperson-first)
         * ========================================================= */
        $po_pool_shared = (float)($soTotalsScoped->orders_sum ?? 0.0);
        $q_inhand = (float)($projectsSums['inhand']['sum_sar'] ?? 0.0);
        $q_bidding = (float)($projectsSums['bidding']['sum_sar'] ?? 0.0);
        $q_total = $q_inhand + $q_bidding;

        $conversion_shared_pool = [
            'pool_sar' => $po_pool_shared,
            'denoms' => [
                'total_sar' => $q_total,
                'inhand_sar' => $q_inhand,
                'bidding_sar' => $q_bidding,
            ],
            'pct' => [
                'overall' => $q_total > 0 ? round(100 * $po_pool_shared / $q_total, 2) : 0.0,
                'inhand' => $q_inhand > 0 ? round(100 * $po_pool_shared / $q_inhand, 2) : 0.0,
                'bidding' => $q_bidding > 0 ? round(100 * $po_pool_shared / $q_bidding, 2) : 0.0,
            ],
            'balance' => [
                'overall' => max($q_total - $po_pool_shared, 0.0),
                'inhand' => max($q_inhand - $po_pool_shared, 0.0),
                'bidding' => max($q_bidding - $po_pool_shared, 0.0),
            ],
        ];

        /* ---------- response ---------- */
        return response()->json([
            'totals' => [
                // show receipts count (all rows), plus unique quotation count
                'count' => (int)($soTotalsScoped->receipts_cnt ?? 0),
                'unique_quotes' => (int)($soTotalsScoped->unique_quotes_cnt ?? 0),
                'value' => (int)($soTotalsScoped->orders_sum ?? 0.0),
                'region' => $regionFilter ?: 'all',
            ],
            'projects_totals_for_user' => [
                'cnt' => (int)($projTotalsForUser->cnt ?? 0),
                'value' => (float)($projTotalsForUser->total_quotation_value ?? 0.0),
            ],
            'conversion_gauge_inhand_bidding' => $conversion_gauge_inhand_bidding,
            'project_sum_inhand_biddign' => $projectsSums,   // keep original key
            'project_sum_inhand_bidding' => $projectsSums,   // alias
            'po_forecast_target' => $poFcTarget,
            'monthly' => ['categories' => $labels, 'values' => $values],
            'productSeries' => [
                'categories' => $topProductsKpi->pluck('product')->toArray(),
                'values' => $topProductsKpi->pluck('val')->map(fn($v) => (float)$v)->toArray(),
            ],
            'statusPie' => $statusPie,
            'projectsRegionPie' => $projectsRegionPie,
            'monthly_product_value' => $monthlyProductValue,
            'productCluster' => ['categories' => $productCats, 'series' => $productClusterSeries],
            'multiMonthly' => ['categories' => $monthsMM, 'bars' => $barSeries, 'lines' => []],
            'conversion_shared_pool' => $conversion_shared_pool,
        ]);
    }


}
