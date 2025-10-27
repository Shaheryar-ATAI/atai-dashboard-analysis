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
            'central' => ['TARIQ', 'TAREQ', 'JAMAL'],                 // ← add JAMAL
            'western' => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],         // ← add AHMED
            default   => [],
        };
    }

    /** Map canonical salesperson → home region (lowercase) */
    protected function homeRegionBySalesperson(): array
    {
        return [
            // Eastern
            'SOHAIB' => 'eastern',
            'SOAHIB' => 'eastern',

            // Central
            'TARIQ'  => 'central',
            'TAREQ'  => 'central',
            'JAMAL'  => 'central',          // ← add JAMAL

            // Western
            'ABDO'   => 'western',
            'ABDUL'  => 'western',
            'ABDOU'  => 'western',
            'AHMED'  => 'western',          // ← add AHMED
        ];
    }

    /** Return the canonical key we use to match names (UPPER + no spaces) */
    protected function canonSalesKey(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$name));
    }

    /** NEW: normalize any login/display name to a canonical salesperson key (handles aliases) */
    protected function resolveSalespersonCanonical(?string $name): ?string
    {
        if (!$name) return null;

        // First token and full key (no spaces)
        $first   = strtoupper(trim(explode(' ', $name)[0] ?? ''));
        $fullKey = $this->canonSalesKey($name);

        $canonMap = [
            'SOHAIB' => 'SOHAIB',
            'SOAHIB' => 'SOHAIB',
            'TARIQ'  => 'TARIQ',
            'TAREQ'  => 'TARIQ',
            'JAMAL'  => 'JAMAL',
            'ABDO'   => 'ABDO',
            'ABDUL'  => 'ABDO',
            'ABDOU'  => 'ABDO',
            'AHMED'  => 'AHMED',
        ];

        if (isset($canonMap[$first]))   return $canonMap[$first];
        if (isset($canonMap[$fullKey])) return $canonMap[$fullKey];

        foreach (array_keys($canonMap) as $k) {
            if (str_starts_with($fullKey, $k)) return $canonMap[$k];
        }
        return null;
    }

    /** Family filter mapping (LIKEs; case-insensitive; robust) */
    protected function applyFamilyFilter($q, string $family): void
    {
        $norm   = "LOWER(REPLACE(REPLACE(TRIM(s.`Products`), ' ', ''), '/', ''))";
        $f      = strtolower(trim($family));
        $needle = str_replace([' ', '/'], '', $f);

        $likeAny = function ($patterns) use ($q, $norm) {
            $q->where(function ($qq) use ($norm, $patterns) {
                foreach ((array)$patterns as $p) {
                    $qq->orWhereRaw("$norm LIKE ?", ['%' . $p . '%']);
                }
            });
        };

        switch (true) {
            case in_array($f, ['access doors', 'access door']):                 $likeAny(['accessdoor', 'accessdoors']); break;
            case in_array($f, ['actuators', 'actuator']):                       $likeAny(['actuator', 'actuators']);     break;
            case in_array($f, ['louvers', 'louver']):                           $likeAny(['louver', 'louvers']);         break;
            case in_array($f, ['round duct', 'round ducts', 'spiral duct', 'spiral']):
                $likeAny(['roundduct', 'roundducts', 'spiralduct', 'spiral']); break;
            case in_array($f, ['semi', 'semi flexible', 'semi-flexible', 'semiflexible', 'semiflex']):
                $likeAny(['semi', 'semiflex', 'semiflexible']); break;
            case in_array($f, ['volume dampers', 'volume damper', 'vd']):        $likeAny(['volumedamper', 'volumedampers', 'vd']); break;
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
                $likeAny(['attenuator', 'attenuators', 'silencer', 'silencers']); break;
            case in_array($f, ['dampers', 'damper']):                           $likeAny(['damper', 'dampers']);         break;
            case in_array($f, ['ductwork', 'ductworks', 'duct', 'ducts', 'rectangular duct', 'rect duct']):
                $likeAny(['duct', 'ducts', 'rectangularduct', 'rectduct', 'ductwork', 'roundduct']); break;
            case in_array($f, ['accessories', 'accessory']):                    $likeAny(['accessory', 'accessories']);  break;
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
        $u       = $r->user();
        $isAdmin = $u && method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['gm', 'admin']);

        // User profile region (normalized)
        $userRegionNorm = $u?->region ? strtolower(trim($u->region)) : null;

        // Optional override (?region=all or ?region=eastern/central/western)
        $regionParam = strtolower(trim((string)$r->query('region', '')));

        // Decide which region (if any) to apply
        $applyRegion = null;
        if ($regionParam === 'all') {
            $applyRegion = null; // no pin
        } elseif ($regionParam !== '') {
            $applyRegion = $regionParam; // explicit override
        } elseif (!$isAdmin && !empty($userRegionNorm)) {
            $applyRegion = $userRegionNorm; // non-admin defaults to own region
        }
        // Admin w/o query => see all

        $q = DB::table('salesorderlog as s');

        if (!empty($applyRegion)) {
            $q->whereRaw('LOWER(TRIM(s.region)) = ?', [$applyRegion]);
        }

        $dateExprSql = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";

        // Date filtering
        if ($r->filled('from') || $r->filled('to')) {
            $from = $r->query('from') ?: '1900-01-01';
            $to   = $r->query('to')   ?: '2999-12-31';
            $q->whereRaw("$dateExprSql BETWEEN ? AND ?", [$from, $to]);
        } else {
            if ($r->filled('year'))  $q->whereRaw("YEAR($dateExprSql)  = ?", [(int)$r->query('year')]);
            if ($r->filled('month')) $q->whereRaw("MONTH($dateExprSql) = ?", [(int)$r->query('month')]);
        }

        $valExprSql = "COALESCE(s.`PO Value`, 0)";
        $family     = trim((string)$r->query('family', ''));
        $status     = trim((string)$r->query('status', ''));

        // Return the region actually applied (handy in headers / debug)
        $appliedRegionForReturn = $applyRegion;

        return [$q, $dateExprSql, $valExprSql, $appliedRegionForReturn, $family, $status, $userRegionNorm];
    }

    /* =========================================================
     * DataTable
     * ========================================================= */
    public function datatable(Request $r)
    {
        [$base] = $this->base($r);

        // Family chip
        if ($fam = trim((string)$r->query('family', ''))) {
            $this->applyFamilyFilter($base, $fam);
        }
        // Status chip (normalized)
        if ($st = trim((string)$r->query('status', ''))) {
            $base->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [strtolower($st)]);
        }

        $q = $base->select([
            's.id',
            DB::raw('s.`PO. No.`          AS po_no'),
            's.date_rec',
            's.region',
            DB::raw('s.`Client Name`      AS client_name'),
            DB::raw('s.`project_region`   AS project_region'),
            DB::raw('s.`Project Name`     AS project_name'),
            DB::raw('s.`Project Location` AS project_location'), // ← use underscore in alias
            DB::raw('s.`Products`         AS product_family'),
            's.value_with_vat',
            DB::raw('s.`PO Value`         AS po_value'),
            DB::raw('s.`Status`           AS status'),
            DB::raw('s.`Remarks`          AS remarks'),
            DB::raw('s.`Sales Source`     AS salesperson'),
        ]);

        $dt = DataTables::of($q)
            ->addIndexColumn()
            ->filter(function ($query) use ($r) {
                $search = strtolower($r->input('search.value', ''));
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
            ->orderColumn('po_no',             's.`PO. No.` $1')
            ->orderColumn('date_rec',          's.`date_rec` $1')
            ->orderColumn('region',            's.`region` $1')
            ->orderColumn('client_name',       's.`Client Name` $1')
            ->orderColumn('project_region',    's.`project_region` $1')
            ->orderColumn('project_name',      's.`Project Name` $1')
            ->orderColumn('project_location',  's.`Project Location` $1')
            ->orderColumn('product_family',    's.`Products` $1')
            ->orderColumn('value_with_vat',    's.`value_with_vat` $1')
            ->orderColumn('po_value',          's.`PO Value` $1')
            ->orderColumn('status',            's.`Status` $1')
            ->orderColumn('remarks',           's.`Remarks` $1')
            ->orderColumn('salesperson',       's.`Sales Source` $1')

            // Column-specific filters (match aliases above)
            ->filterColumn('po_no',            fn($q,$k)=>$q->whereRaw('s.`PO. No.` LIKE ?', ["%$k%"]))
            ->filterColumn('client_name',      fn($q,$k)=>$q->whereRaw('s.`Client Name` LIKE ?', ["%$k%"]))
            ->filterColumn('project_region',   fn($q,$k)=>$q->whereRaw('s.`project_region` LIKE ?', ["%$k%"]))
            ->filterColumn('project_location', fn($q,$k)=>$q->whereRaw('s.`Project Location` LIKE ?', ["%$k%"]))
            ->filterColumn('project_name',     fn($q,$k)=>$q->whereRaw('s.`Project Name` LIKE ?', ["%$k%"]))
            ->filterColumn('product_family',   fn($q,$k)=>$q->whereRaw('s.`Products` LIKE ?', ["%$k%"]))
            ->filterColumn('status',           fn($q,$k)=>$q->whereRaw('s.`Status` LIKE ?', ["%$k%"]))
            ->filterColumn('salesperson',      fn($q,$k)=>$q->whereRaw('s.`Sales Source` LIKE ?', ["%$k%"]))
            ->filterColumn('remarks',          fn($q,$k)=>$q->whereRaw('s.`Remarks` LIKE ?', ["%$k%"]))
            ->filterColumn('region',           fn($q,$k)=>$q->whereRaw('s.`region` LIKE ?', ["%$k%"]))
            ->filterColumn('date_rec',         fn($q,$k)=>$q->whereRaw('DATE_FORMAT(s.`date_rec`,"%Y-%m-%d") LIKE ?', ["%$k%"]))

            ->editColumn('value_with_vat', fn($row)=>(int)($row->value_with_vat ?? 0))
            ->editColumn('po_value',       fn($row)=>(int)($row->po_value ?? 0));

        return $dt->toJson();
    }

    /* =========================================================
     * KPIs / Charts JSON
     * ========================================================= */
    public function kpis(Request $r)
    {
        /* ---------- user / role ---------- */
        $user       = $r->user();
        $isAdmin    = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm', 'admin']);
        $region     = $user->region ?? null;
        $regionNorm = $region ? strtolower(trim($region)) : '';

        // Use canonical key (handles aliases and names with surnames)
        $userKey    = $this->resolveSalespersonCanonical($user->name ?? null);

        // Salesman alias map (adjust as needed)
        $aliasMap = [
            // Eastern
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'SOAHIB' => ['SOHAIB', 'SOAHIB'],

            // Central
            'TARIQ'  => ['TARIQ', 'TAREQ', 'JAMAL'],
            'TAREQ'  => ['TARIQ', 'TAREQ', 'JAMAL'],
            'JAMAL'  => ['TARIQ', 'TAREQ', 'JAMAL'],

            // Western
            'ABDO'   => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],
            'ABDUL'  => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],
            'ABDOU'  => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],
            'AHMED'  => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED'],
        ];
        $loggedUserAliases = $aliasMap[$userKey] ?? ($userKey ? [$userKey] : []);

        /* ---------- base scope ---------- */
        [$base, $dateExprSql, $valExprSql, $regionFromBase, $family, $status, $regionNormBase] = $this->base($r);

        $familySel      = trim((string)$family);
        $selectedStatus = strtolower(trim((string)$status));
        $filterByStatus = $selectedStatus !== '';

        /* ---------- lists: families / statuses ---------- */
        $familiesBase = clone $base;
        $allFamilies = $familiesBase
            ->selectRaw("TRIM(s.`Products`) AS family")
            ->whereNotNull('s.Products')
            ->whereRaw("TRIM(s.`Products`) <> ''")
            ->distinct()->orderBy('family')
            ->pluck('family')->values()->toArray();

        $statusBase = clone $base;
        if ($familySel !== '') $this->applyFamilyFilter($statusBase, $familySel);
        $allStatuses = $statusBase
            ->selectRaw("TRIM(COALESCE(s.`Status`,'Unknown')) AS status")
            ->distinct()->orderBy('status')
            ->pluck('status')->values()->toArray();

        /* ---------- filtered scope ---------- */
        $filtered = clone $base;
        if ($familySel !== '')  $this->applyFamilyFilter($filtered, $familySel);
        if ($filterByStatus)    $filtered->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]);

        /* ---------- top cards ---------- */
        $filteredTotals = clone $filtered;
        if (!$isAdmin) {
            if (!empty($loggedUserAliases)) {
                $filteredTotals->where(function ($qq) use ($loggedUserAliases) {
                    foreach ($loggedUserAliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                    }
                });
            }
            if ($regionNorm !== '' && $regionNorm !== 'all') {
                $filteredTotals->whereRaw('LOWER(TRIM(s.`Region`)) = ?', [$regionNorm]);
            }
        }

        $soTotalsScoped = (clone $filteredTotals)
            ->selectRaw("COUNT(*) AS orders_cnt, COALESCE(SUM($valExprSql), 0) AS orders_sum")
            ->first();

        /* ---------- monthly (accepted) vs forecast vs target ---------- */
        $monthsP = (clone $filtered)
           // ->whereRaw("LOWER(TRIM(s.`Status`)) = 'accepted'")
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym")
            ->groupBy('ym')->orderBy('ym')
            ->pluck('ym')->values()->all();

        $poByMonth = (clone $filtered)
           // ->whereRaw("LOWER(TRIM(s.`Status`)) = 'accepted'")
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym')->orderBy('ym')->pluck('val','ym')->all();

        $fcBase = DB::table('forecast as f');
        if (!empty($region))            $fcBase->where('f.region', $region);
        if ($familySel !== '')          $fcBase->whereRaw('LOWER(f.product_family) LIKE ?', ['%'.strtolower($familySel).'%']);
        if ($r->filled('year'))         $fcBase->where('f.year', (int)$r->input('year'));
        if ($r->filled('month'))        $fcBase->where('f.month_no', (int)$r->input('month'));
        $fcDateExpr = "DATE(CONCAT(f.year,'-',LPAD(f.month_no,2,'0'),'-01'))";
        if ($r->filled('from'))         $fcBase->whereRaw("$fcDateExpr >= ?", [$r->input('from')]);
        if ($r->filled('to'))           $fcBase->whereRaw("$fcDateExpr <= ?", [$r->input('to')]);

        $forecastRows = $fcBase
            ->selectRaw("CONCAT(f.year,'-',LPAD(f.month_no,2,'0')) AS ym")
            ->selectRaw("COALESCE(SUM(f.value_sar),0) AS val")
            ->groupBy('ym')->orderBy('ym')->get();

        $forecastByMonth = [];
        foreach ($forecastRows as $fr) $forecastByMonth[$fr->ym] = (int)$fr->val;

        //$monthsAll      = collect($monthsP)->merge(array_keys($forecastByMonth))->unique()->sort()->values()->all();
        $monthsAll = collect($monthsP)->merge(array_keys($forecastByMonth))
            ->unique()->sort()->values()->all();

        /* =========================
         * Dynamic Targets (by region)
         * - Eastern: 35M / year
         * - Central: 37M / year
         * - Western: 30M / year
         * Resolution order:
         *   1) If ?monthly_target given → use it (manual override)
         *   2) Else pick region:
         *        - If admin can view all: use ?region=eastern|central|western if provided
         *        - Else use logged-in user's region
         *        - If user's region empty, infer from canonical salesperson (homeRegionBySalesperson)
         * ========================= */
        $annualTargets = [
            'eastern' => 35_000_000.0,
            'central' => 37_000_000.0,
            'western' => 30_000_000.0,
        ];

// Resolve region for target
        $regionQuery = strtolower(trim((string) $r->query('region', '')));     // may be 'all' or specific
        $regionForTarget = null;

        if ($r->filled('monthly_target')) {
            // explicit override will be used below, region selection is irrelevant
            $regionForTarget = $regionNorm ?: null;
        } else {
            if ($isAdmin) {
                // Admin: honor explicit region if valid; ignore 'all'
                if (in_array($regionQuery, ['eastern','central','western'], true)) {
                    $regionForTarget = $regionQuery;
                } else {
                    // fallback to user’s own region if available
                    $regionForTarget = in_array($regionNorm, ['eastern','central','western'], true) ? $regionNorm : null;
                }
            } else {
                // Non-admin: user pin
                if (in_array($regionNorm, ['eastern','central','western'], true)) {
                    $regionForTarget = $regionNorm;
                } else {
                    // try infer from salesperson canonical name
                    $canon = $this->resolveSalespersonCanonical($user->name ?? null);
                    $homeMap = $this->homeRegionBySalesperson();
                    $regionForTarget = $canon && isset($homeMap[$canon]) ? $homeMap[$canon] : null;
                }
            }
        }


        // Derive monthly target
        $derivedMonthly = $regionForTarget && isset($annualTargets[$regionForTarget])
            ? ($annualTargets[$regionForTarget] / 12.0)
            : 0.0;
        //$targetPerMonth = (int) ($r->input('monthly_target', 3_000_000));
        $targetPerMonth = $r->filled('monthly_target')
            ? (int) $r->input('monthly_target')
            : $derivedMonthly;
        $seriesPo = $seriesFc = $seriesTgt = [];
        foreach ($monthsAll as $ym) {
            $seriesPo[]  = (int)($poByMonth[$ym]       ?? 0);
            $seriesFc[]  = (int)($forecastByMonth[$ym] ?? 0);
            $seriesTgt[] = $targetPerMonth;
        }
//        $poFcTarget = [
//            'categories'     => $monthsAll,
//            'series'         => [
//                ['type'=>'column','name'=>'PO (SAR)','data'=>$seriesPo],
//                ['type'=>'column','name'=>'Forecast (SAR)','data'=>$seriesFc],
//                ['type'=>'column','name'=>'Target (SAR)','data'=>$seriesTgt],
//            ],
//            'monthly_target' => $targetPerMonth,
//        ];

        // Include helpful metadata (no UI breakage)
        $poFcTarget = [
            'categories'     => $monthsAll,
            'series'         => [
                ['type'=>'column','name'=>'PO (SAR)','data'=>$seriesPo],
                ['type'=>'column','name'=>'Forecast (SAR)','data'=>$seriesFc],
                ['type'=>'column','name'=>'Target (SAR)','data'=>$seriesTgt],
            ],
            'monthly_target' => $targetPerMonth,
            'target_meta'    => [
                'region_used'   => $regionForTarget,
                'annual_target' => $regionForTarget ? ($annualTargets[$regionForTarget] ?? 0.0) : 0.0,
                'override'      => $r->filled('monthly_target'),
            ],
        ];
        /* ---------- simple monthly (all statuses) ---------- */
        $monthlyRows = (clone $filtered)
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy(DB::raw('ym'))->orderBy(DB::raw('ym'))->get();

        $labels = []; $values = [];
        foreach ($monthlyRows as $row) { $labels[] = $row->ym; $values[] = (int)$row->val; }

        /* ---------- top products ---------- */
        $topProductsKpi = (clone $filtered)
            ->selectRaw("COALESCE(s.`Products`, 'Unknown') AS product, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(10)->get();

        /* ---------- status pie ---------- */
        $byStatus = (clone $filtered)
            ->selectRaw("TRIM(COALESCE(s.`Status`,'Unknown')) AS status, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('status')->get();
        $statusPie = $byStatus->map(fn($r2)=>['name'=>$r2->status, 'y'=>(int)$r2->val])->values()->toArray();

        /* ---------- projects_region pie ---------- */
        $byProjectsRegion = (clone $filteredTotals)
            ->selectRaw("TRIM(COALESCE(s.`project_region`,'Unknown')) AS projects_region")
            ->selectRaw("COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('project_region')
            ->get();

        $totalProjectsRegionVal = max(1.0, (int)$byProjectsRegion->sum('val')); // guard div/0
        $projectsRegionPie = $byProjectsRegion->map(function($r) use ($totalProjectsRegionVal){
            $v = (int)$r->val;
            return [
                'name'  => (string)$r->projects_region,
                'y'     => round(($v / $totalProjectsRegionVal) * 100, 2),
                'value' => $v,
            ];
        })->values()->toArray();

        /* ---------- conversion (legacy simple + latest) ---------- */
        $projDateExpr = "COALESCE(
            STR_TO_DATE(quotation_date,'%Y-%m-%d'),
            STR_TO_DATE(quotation_date,'%d-%m-%Y'),
            STR_TO_DATE(quotation_date,'%d/%m/%Y'),
            DATE(created_at)
        )";
        $projSimpleScope = DB::table('projects')
            ->when($regionNorm !== '',  fn($q)=>$q->whereRaw('LOWER(TRIM(area)) = ?', [$regionNorm]))
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($projDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($projDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("$projDateExpr >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("$projDateExpr <= ?", [$r->input('to')]))
            ->when($familySel !== '',   fn($q)=>$q->whereRaw('LOWER(atai_products) LIKE ?', ['%'.strtolower($familySel).'%']));
        $quotationSum_simple = (int) (clone $projSimpleScope)
            ->selectRaw('COALESCE(SUM(COALESCE(quotation_value,price,0)),0) AS t')
            ->value('t');

        $soDateExpr = "COALESCE(NULLIF(s.date_rec,'0000-00-00'), DATE(s.created_at))";
        $solValExpr = "COALESCE(s.`PO Value`,0)";
        $soSimpleScope = DB::table('salesorderlog as s')
            ->when(!$isAdmin && $regionNorm !== '', fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Region`)) = ?', [$regionNorm]))
            ->when(!$isAdmin && !empty($loggedUserAliases), function ($q) use ($loggedUserAliases) {
                $q->where(function ($qq) use ($loggedUserAliases) {
                    foreach ($loggedUserAliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                    }
                });
            })
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]))
            ->when($familySel !== '',   fn($q)=>$this->applyFamilyFilter($q, $familySel))
            ->when($filterByStatus,     fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]));

        $poSum_simple = (int) (clone $soSimpleScope)
            ->selectRaw("COALESCE(SUM($solValExpr),0) AS t")
            ->value('t');

        // latest-only per quotation
        $solBaseKeySql = "
            SUBSTRING_INDEX(
              SUBSTRING_INDEX(TRIM(s.`Quote No.`), '.R', 1), '.',
              (LENGTH(SUBSTRING_INDEX(TRIM(s.`Quote No.`), '.R', 1))
               - LENGTH(REPLACE(SUBSTRING_INDEX(TRIM(s.`Quote No.`), '.R', 1), '.', '')) )
            )";
        $solRevSql = "CAST(SUBSTRING_INDEX(TRIM(s.`Quote No.`), '.R', -1) AS UNSIGNED)";

        $scoped = DB::table('salesorderlog as s')
            ->selectRaw("$solBaseKeySql AS base_key")
            ->selectRaw("$solRevSql     AS rev_n")
            ->selectRaw("COALESCE(s.`PO Value`,0) AS po_value")
            ->whereRaw("s.`Quote No.` IS NOT NULL AND TRIM(s.`Quote No.`) <> ''")
            ->when(!$isAdmin && $regionNorm !== '', fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Region`)) = ?', [$regionNorm]))
            ->when(!$isAdmin && !empty($loggedUserAliases), function ($q) use ($loggedUserAliases) {
                $q->where(function ($qq) use ($loggedUserAliases) {
                    foreach ($loggedUserAliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                    }
                });
            })
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]))
            ->when($familySel !== '',   fn($q)=>$this->applyFamilyFilter($q, $familySel))
            ->when($filterByStatus,     fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]));

        $latest = DB::table('salesorderlog as s')
            ->selectRaw("$solBaseKeySql AS base_key")
            ->selectRaw("MAX($solRevSql)  AS max_rev")
            ->whereRaw("s.`Quote No.` IS NOT NULL AND TRIM(s.`Quote No.`) <> ''")
            ->when(!$isAdmin && $regionNorm !== '', fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Region`)) = ?', [$regionNorm]))
            ->when(!$isAdmin && !empty($loggedUserAliases), function ($q) use ($loggedUserAliases) {
                $q->where(function ($qq) use ($loggedUserAliases) {
                    foreach ($loggedUserAliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                    }
                });
            })
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]))
            ->when($familySel !== '',   fn($q)=>$this->applyFamilyFilter($q, $familySel))
            ->when($filterByStatus,     fn($q)=>$q->whereRaw('LOWER(TRIM(s.`Status`)) = ?', [$selectedStatus]))
            ->groupBy('base_key');

        $poSum_latestOnly = (int) DB::query()
            ->fromSub($scoped, 'n')
            ->joinSub($latest, 'm', fn($j)=>$j->on('n.base_key','=','m.base_key')->on('n.rev_n','=','m.max_rev'))
            ->selectRaw('COALESCE(SUM(n.po_value),0) AS t')
            ->value('t');

        $convPct_latest = $quotationSum_simple > 0 ? round(100.0 * ($poSum_latestOnly / $quotationSum_simple), 1) : 0.0;
        $convPct_simple = $quotationSum_simple > 0 ? round(100.0 * ($poSum_simple    / $quotationSum_simple), 1) : 0.0;

        $conversionGauge = [
            'quotes_region_sar'   => $quotationSum_simple,
            'po_user_region_raw'  => $poSum_simple,
            'po_user_region_last' => $poSum_latestOnly,
            'pct'                 => $convPct_latest,
            'pct_raw'             => $convPct_simple,
        ];

        /* ---------- in-hand & bidding joins (prefix match) ---------- */
        $projQuoteCol = 'quotation_no';
        $aliases      = $loggedUserAliases ?? [];
        if (empty($aliases)) $aliases = ['SOHAIB','SOAHIB']; // fallback demo

        $baseProjects = DB::table('projects as p')
            ->whereNull('p.deleted_at')
            ->when($regionNorm !== '' && $regionNorm !== 'all',
                fn($q)=>$q->whereRaw('LOWER(TRIM(p.area)) = ?', [$regionNorm])
            )
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($projDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($projDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("$projDateExpr >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("$projDateExpr <= ?", [$r->input('to')]))
            ->when($familySel !== '',   fn($q)=>$q->whereRaw('LOWER(p.atai_products) LIKE ?', ['%'.strtolower($familySel).'%']))
            ->when(!$isAdmin && !empty($aliases), function ($q) use ($aliases) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(p.salesperson)),' ','') = ?", [$alias]);
                    }
                });
            })
            ->whereRaw("p.$projQuoteCol IS NOT NULL AND TRIM(p.$projQuoteCol) <> ''");

        // In-Hand variants: in hand / in-hand / in_hand / inhand
        $projInHandScope = (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(REPLACE(REPLACE(p.project_type,'-',' '),'_',' '))) REGEXP '^in[[:space:]]*hand$'")
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

        $projInHandSum = (int) (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(REPLACE(REPLACE(p.project_type,'-',' '),'_',' '))) REGEXP '^in[[:space:]]*hand$'")
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS t')
            ->value('t');
        $projInHandCnt = (int) (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(REPLACE(REPLACE(p.project_type,'-',' '),'_',' '))) REGEXP '^in[[:space:]]*hand$'")
            ->count();

        // Bidding
        $projBiddingScope = (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(p.project_type)) = 'bidding'")
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

        $projBiddingSum = (int) (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(p.project_type)) = 'bidding'")
            ->selectRaw('COALESCE(SUM(p.quotation_value),0) AS t')
            ->value('t');
        $projBiddingCnt = (int) (clone $baseProjects)
            ->whereRaw("LOWER(TRIM(p.project_type)) = 'bidding'")
            ->count();

        $out['projects_sums'] = [
            'inhand'  => ['count' => $projInHandCnt,  'sum_sar' => $projInHandSum],
            'bidding' => ['count' => $projBiddingCnt, 'sum_sar' => $projBiddingSum],
        ];

        $solScope = DB::table('salesorderlog as s')
            ->whereNull('s.deleted_at')
            ->whereRaw("s.`Quote No.` IS NOT NULL AND TRIM(s.`Quote No.`) <> ''")
            ->when(!$isAdmin && $regionNorm !== '' && $regionNorm !== 'all',
                fn($q)=>$q->whereRaw('LOWER(TRIM(s.region)) = ?', [$regionNorm])
            )
            ->when(!$isAdmin && !empty($aliases), function ($q) use ($aliases) {
                $q->where(function ($qq) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                    }
                });
            })
            ->when($r->filled('year'),  fn($q)=>$q->whereRaw("YEAR($soDateExpr)  = ?", [(int)$r->input('year')]))
            ->when($r->filled('month'), fn($q)=>$q->whereRaw("MONTH($soDateExpr) = ?", [(int)$r->input('month')]))
            ->when($r->filled('from'),  fn($q)=>$q->whereRaw("DATE($soDateExpr) >= ?", [$r->input('from')]))
            ->when($r->filled('to'),    fn($q)=>$q->whereRaw("DATE($soDateExpr) <= ?", [$r->input('to')]))
            ->selectRaw("s.id AS sol_id")
            ->selectRaw("s.`Quote No.` AS sol_quote_no")
            ->selectRaw("s.region AS sol_region")
            ->selectRaw("s.`Sales Source` AS sol_sales_source")
            ->selectRaw("COALESCE(s.`PO Value`,0) AS po_value")
            ->selectRaw("UPPER(TRIM(s.`Quote No.`)) AS s_qno");

        // In-Hand join
        $projInSub = DB::query()->fromSub($projInHandScope, 'p');
        $solSub    = DB::query()->fromSub($solScope,       's');

        $inJoinBase = DB::query()
            ->fromSub($projInSub, 'p')
            ->joinSub($solSub, 's', function($j){
                $j->on(DB::raw('1'), '=', DB::raw('1'))
                    ->whereRaw("s.s_qno LIKE CONCAT(p.p_base, '%') OR p.p_base LIKE CONCAT(s.s_qno, '%')");
            });

        $inhandMatchedRows = (clone $inJoinBase)
            ->selectRaw("
                p.id AS project_id, p.client_name, p.project_name, p.area, p.salesperson,
                p.quotation_no, p.quotation_value,
                s.sol_id, s.sol_quote_no, s.sol_region, s.sol_sales_source, s.po_value
            ")
            ->orderBy('p.quotation_no')->orderBy('s.sol_quote_no')
            ->get();

        $inhandTotals = (clone $inJoinBase)
            ->selectRaw("COUNT(*) AS matched_rows, COALESCE(SUM(s.po_value),0) AS total_po_value")
            ->first();

        $inhandJoinResult = [
            'rows'          => $inhandMatchedRows,
            'matched_rows'  => (int)   ($inhandTotals->matched_rows   ?? 0),
            'po_sum_sar'    => (int) ($inhandTotals->total_po_value ?? 0),
        ];

        // Bidding join
        $projBidSub = DB::query()->fromSub($projBiddingScope, 'p');

        $bdJoinBase = DB::query()
            ->fromSub($projBidSub, 'p')
            ->joinSub($solSub, 's', function($j){
                $j->on(DB::raw('1'), '=', DB::raw('1'))
                    ->whereRaw("s.s_qno LIKE CONCAT(p.p_base, '%') OR p.p_base LIKE CONCAT(s.s_qno, '%')");
            });

        $biddingMatchedRows = (clone $bdJoinBase)
            ->selectRaw("
                p.id AS project_id, p.client_name, p.project_name, p.area, p.salesperson,
                p.quotation_no, p.quotation_value,
                s.sol_id, s.sol_quote_no, s.sol_region, s.sol_sales_source, s.po_value
            ")
            ->orderBy('p.quotation_no')->orderBy('s.sol_quote_no')
            ->get();

        $biddingTotals = (clone $bdJoinBase)
            ->selectRaw("COUNT(*) AS matched_rows, COALESCE(SUM(s.po_value),0) AS total_po_value")
            ->first();

        $biddingJoinResult = [
            'rows'          => $biddingMatchedRows,
            'matched_rows'  => (int)   ($biddingTotals->matched_rows   ?? 0),
            'po_sum_sar'    => (int) ($biddingTotals->total_po_value ?? 0),
        ];

        $out['conversion_gauge_inhand_bidding'] = [
            'inhand' => [
                'quotes_total_sar' => (int) ($out['projects_sums']['inhand']['sum_sar']  ?? 0),
                'po_total_sar'     => (int) ($inhandJoinResult['po_sum_sar']            ?? 0),
                'pct'              => 0.0,
                'rows'             => $inhandJoinResult['rows'] ?? [],
            ],
            'bidding' => [
                'quotes_total_sar' => (int) ($out['projects_sums']['bidding']['sum_sar'] ?? 0),
                'po_total_sar'     => (int) ($biddingJoinResult['po_sum_sar']           ?? 0),
                'pct'              => 0.0,
                'rows'             => $biddingJoinResult['rows'] ?? [],
            ],
        ];
        foreach (['inhand','bidding'] as $k) {
            $qv = $out['conversion_gauge_inhand_bidding'][$k]['quotes_total_sar'];
            $pv = $out['conversion_gauge_inhand_bidding'][$k]['po_total_sar'];
            $out['conversion_gauge_inhand_bidding'][$k]['pct'] = $qv > 0 ? round(100 * $pv / $qv, 2) : 0.0;
        }

        /* ---------- product-wise monthly (stacked + total spline) ---------- */
        $prodRows = (clone $filtered)
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym")
            ->selectRaw("COALESCE(s.`Products`,'Unknown') AS product")
            ->selectRaw("COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym','product')->orderBy('ym')->get();

        $months = $prodRows->pluck('ym')->unique()->sort()->values()->all();

        $totalsByProduct = $prodRows->groupBy('product')->map(fn($g)=>(int)$g->sum('val'));
        $topN = 8;
        $topProductsForMonthlyValue = $totalsByProduct->sortDesc()->keys()->take($topN)->values();
        $otherNames = $totalsByProduct->keys()->diff($topProductsForMonthlyValue);

        $idx = [];
        foreach ($prodRows as $r3) {
            $isTop = in_array($r3->product, $topProductsForMonthlyValue->all(), true);
            $pName = $isTop ? $r3->product : ($otherNames->isNotEmpty() ? 'Others' : $r3->product);
            $idx[$r3->ym][$pName] = ($idx[$r3->ym][$pName] ?? 0.0) + (int)$r3->val;
        }

        $productsForSeries = $topProductsForMonthlyValue->all();
        if ($otherNames->isNotEmpty() && !in_array('Others',$productsForSeries,true)) $productsForSeries[]='Others';

        $productSeriesMonthly = [];
        foreach ($productsForSeries as $pn) {
            $data=[]; foreach ($months as $ym) $data[] = round((int)($idx[$ym][$pn] ?? 0.0), 2);
            $productSeriesMonthly[] = ['type'=>'column','name'=>$pn,'stack'=>'Products','data'=>$data];
        }

        $totalPoints=[]; $prev=null;
        foreach ($months as $ym) {
            $total=0.0;
            foreach ($productsForSeries as $pn) $total += (int)($idx[$ym][$pn] ?? 0.0);
            $total = round($total,2);
            $mom = ($prev && $prev>0) ? round((($total-$prev)/$prev)*100,1) : 0.0;
            $totalPoints[]=['y'=>$total,'mom'=>$mom];
            $prev=$total;
        }
        $monthlyProductValue = [
            'categories'=>$months,
            'series'=>array_merge($productSeriesMonthly, [[
                'type'=>'spline','name'=>'Total','yAxis'=>1,'data'=>$totalPoints
            ]]),
        ];

        /* ---------- product clusters (horizontal) + monthly by product ---------- */
        $prodTotalsCluster = (clone $filtered)
            ->selectRaw("TRIM(COALESCE(s.`Products`,'Unknown')) AS product, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(12)->get();


        $prodTotalsCluster = (clone $filtered)
            ->selectRaw("TRIM(COALESCE(s.`Products_raw`,'Unknown')) AS product, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit(12)->get();





        $productCats = $prodTotalsCluster->pluck('product')->values()->all();

        $rowsCluster = (clone $filtered)
            ->when(!empty($productCats), fn($q)=>$q->whereIn(DB::raw("TRIM(COALESCE(s.`Products`,'Unknown'))"), $productCats))
            ->selectRaw("TRIM(COALESCE(s.`Products`,'Unknown')) AS product,
                         TRIM(COALESCE(s.`Status`,'Unknown'))   AS status,
                         COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product','status')->get();

        $statusOrder = ['Accepted','Pre-Acceptance','Waiting','Rejected','Cancelled','Unknown'];
        $matrix=[]; foreach ($statusOrder as $st) $matrix[$st]=array_fill(0,count($productCats),0.0);
        foreach ($rowsCluster as $r4) {
            $st = in_array($r4->status,$statusOrder,true) ? $r4->status : 'Unknown';
            $i  = array_search($r4->product,$productCats,true);
            if ($i!==false) $matrix[$st][$i]=(int)$r4->val;
        }
        $productClusterSeries=[]; foreach ($statusOrder as $st) {
        $productClusterSeries[]=['type'=>'bar','name'=>$st,'data'=>$matrix[$st]];
    }

        /* ---------- FIX: build monthsArr safely (no array_flip on non-scalar) ---------- */
        $monthsArr = (clone $filtered)
            ->whereRaw("$dateExprSql IS NOT NULL")
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym")
            ->groupBy('ym')->orderBy('ym')
            ->pluck('ym')->values()->toArray();

        // sanitize months (strings, unique, non-empty)
        $monthsArr = array_values(array_filter(
            array_unique(array_map(
                fn($x) => is_scalar($x) ? (string)$x : null,
                $monthsArr
            )),
            fn($x) => $x !== null && $x !== ''
        ));

        // manual index map (instead of array_flip which throws on non-scalar)
        $pos = [];
        foreach ($monthsArr as $i => $ym) {
            $pos[$ym] = $i;
        }

        $TOP_N = 6;
        $topProductsMonthly = (clone $filtered)
            ->selectRaw("TRIM(COALESCE(s.`Products`,'Unknown')) AS product, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('product')->orderByDesc('val')->limit($TOP_N)->pluck('product')->values()->toArray();

        $rowsMonthly = (clone $filtered)
            ->when(!empty($topProductsMonthly), fn($q)=>$q->whereIn(DB::raw("TRIM(COALESCE(s.`Products`,'Unknown'))"), $topProductsMonthly))
            ->selectRaw("DATE_FORMAT($dateExprSql,'%Y-%m') AS ym,
                         TRIM(COALESCE(s.`Products`,'Unknown')) AS product,
                         COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym','product')->orderBy('ym')->get();

        $seriesOut=[]; foreach ($topProductsMonthly as $pName) $seriesOut[$pName]=array_fill(0,count($monthsArr),0.0);
        foreach ($rowsMonthly as $r5) {
            if (!isset($seriesOut[$r5->product])) continue;
            $i = $pos[$r5->ym] ?? null;
            if ($i!==null) $seriesOut[$r5->product][$i]=(int)$r5->val;
        }
        $monthlyProductCluster = [
            'categories'=>$monthsArr,
            'series'=>array_map(fn($name)=>[
                'type'=>'column','name'=>$name,'data'=>array_values($seriesOut[$name] ?? []),
            ], $topProductsMonthly),
        ];

        /* ---------- grouped bars by status per month ---------- */
        $monthlyStatus = (clone $filtered)
            ->selectRaw("DATE_FORMAT($dateExprSql, '%Y-%m') AS ym,
                         TRIM(COALESCE(s.`Status`,'Unknown')) AS status,
                         COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('ym','status')
            ->orderBy('ym')
            ->get();

        $monthsCol = $monthlyStatus->pluck('ym')->unique()->sort()->values();
        $monthsMM  = array_values(array_filter(
            array_map(fn($x)=> is_scalar($x) ? (string)$x : null, $monthsCol->all()),
            fn($x)=> $x !== null && $x !== ''
        ));

        $bySt = [];
        foreach ($statusOrder as $st) { $bySt[$st] = array_fill_keys($monthsMM, 0.0); }
        foreach ($monthlyStatus as $r2) {
            $st = in_array($r2->status, $statusOrder, true) ? $r2->status : 'Unknown';
            if (!isset($bySt[$st])) $bySt[$st] = array_fill_keys($monthsMM, 0.0);
            $bySt[$st][$r2->ym] = (int)$r2->val;
        }
        $barSeries = [];
        foreach ($statusOrder as $st) {
            $barSeries[] = ['type'=>'column','name'=>$st,'data'=>array_values($bySt[$st])];
        }

        // overall conversion (legacy, inhand + bidding sums)
        $in_q = (int)($out['conversion_gauge_inhand_bidding']['inhand']['quotes_total_sar']  ?? 0);
        $in_p = (int)($out['conversion_gauge_inhand_bidding']['inhand']['po_total_sar']      ?? 0);
        $bd_q = (int)($out['conversion_gauge_inhand_bidding']['bidding']['quotes_total_sar'] ?? 0);
        $bd_p = (int)($out['conversion_gauge_inhand_bidding']['bidding']['po_total_sar']     ?? 0);

        $all_q = $in_q + $bd_q;
        $all_p = $in_p + $bd_p;

        $conversionGaugeInhandBiddingTotal = [
            'quotes_total_sar' => $all_q,
            'po_total_sar'     => $all_p,
            'pct'              => $all_q > 0 ? round(($all_p / $all_q) * 100, 2) : 0.0,
        ];

        /* ==============================
         * NEW: Shared PO Pool Conversion
         * ============================== */
        $po_pool_shared = (int) ($soTotalsScoped->orders_sum ?? 0);

        $q_inhand  = (int) ($out['projects_sums']['inhand']['sum_sar']  ?? 0.0);
        $q_bidding = (int) ($out['projects_sums']['bidding']['sum_sar'] ?? 0.0);
        $q_total   = $q_inhand + $q_bidding;

        $overall_pct_shared = $q_total   > 0 ? round(100 * $po_pool_shared / $q_total,   2) : 0.0;
        $inhand_pct_shared  = $q_inhand  > 0 ? round(100 * $po_pool_shared / $q_inhand,  2) : 0.0;
        $bidding_pct_shared = $q_bidding > 0 ? round(100 * $po_pool_shared / $q_bidding, 2) : 0.0;

        $conversion_shared_pool = [
            'pool_sar' => $po_pool_shared,
            'denoms'   => [
                'total_sar'   => $q_total,
                'inhand_sar'  => $q_inhand,
                'bidding_sar' => $q_bidding,
            ],
            'pct' => [
                'overall' => $overall_pct_shared,
                'inhand'  => $inhand_pct_shared,
                'bidding' => $bidding_pct_shared,
            ],
            'balance' => [
                'overall' => max($q_total   - $po_pool_shared, 0),
                'inhand'  => max($q_inhand  - $po_pool_shared, 0),
                'bidding' => max($q_bidding - $po_pool_shared, 0),
            ],
        ];

        /* ---------- Salesperson region mix (for alert) ---------- */
        $homeMap = $this->homeRegionBySalesperson();

        // raw rows grouped by salesperson & region (same base filters + salesperson scope for non-admins)
        $mixBase = clone $base;
        if (!$isAdmin && !empty($loggedUserAliases)) {
            $mixBase->where(function ($qq) use ($loggedUserAliases) {
                foreach ($loggedUserAliases as $alias) {
                    $qq->orWhereRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') = ?", [$alias]);
                }
            });
        }

        $mixRows = $mixBase
            ->selectRaw("REPLACE(UPPER(TRIM(s.`Sales Source`)),' ','') AS saleskey")
            ->selectRaw("LOWER(TRIM(s.`project_region`)) AS reg")
            ->selectRaw("COUNT(*) AS cnt, COALESCE(SUM($valExprSql),0) AS val")
            ->groupBy('saleskey','reg')
            ->get();

        $salespersonRegionMix = [];
        $tmp = [];
        foreach ($mixRows as $r) {
            $k   = $r->saleskey ?: 'UNKNOWN';
            $reg = in_array($r->reg, ['eastern','central','western'], true) ? $r->reg : 'unknown';
            if (!isset($tmp[$k])) {
                $tmp[$k] = [
                    'salesperson' => $k,
                    'home_region' => $homeMap[$k] ?? null,
                    'counts'      => ['eastern'=>0,'central'=>0,'western'=>0,'unknown'=>0],
                    'values'      => ['eastern'=>0,'central'=>0,'western'=>0,'unknown'=>0],
                    'total'       => 0.0,
                ];
            }
            $tmp[$k]['counts'][$reg] += (int)$r->cnt;
            $tmp[$k]['values'][$reg] += (int)$r->val;
            $tmp[$k]['total']        += (int)$r->val;   // using value as weight
        }

        $THRESH = 50.0; // warn if home share < 50%
        foreach ($tmp as $k => $row) {
            $tot = max(1.0, (int)$row['total']); // guard div/0
            $pct = [
                'eastern' => round(($row['values']['eastern'] / $tot) * 100, 2),
                'central' => round(($row['values']['central'] / $tot) * 100, 2),
                'western' => round(($row['values']['western'] / $tot) * 100, 2),
            ];
            $home    = $row['home_region'];
            $homePct = $home && isset($pct[$home]) ? (int)$pct[$home] : 0.0;

            $salespersonRegionMix[] = [
                'salesperson' => $row['salesperson'],
                'home_region' => $home,
                'pct'         => $pct,
                'home_pct'    => $homePct,
                'warn'        => $home ? ($homePct < $THRESH) : false,
                'reason'      => $home ? "Home region share is {$homePct}% (threshold {$THRESH}%)." : "No mapped home region.",
            ];
        }

        // Also compute a quick warning for the LOGGED-IN user (handy banner)
        $userHome = $userKey ? ($homeMap[$userKey] ?? null) : null;
        $userMix  = null;
        if ($userKey && $userHome) {
            foreach ($salespersonRegionMix as $row) {
                if ($row['salesperson'] === $userKey) { $userMix = $row; break; }
            }
        }

        // Always return a structured object so the UI can rely on it
        $userRegionWarning = [
            'show'     => false,
            'home'     => $userHome,
            'home_pct' => $userMix['home_pct'] ?? 0,
            'reason'   => $userHome
                ? ($userMix['reason'] ?? 'No activity found for your user in the selected filters.')
                : 'No mapped home region.',
        ];
        if ($userMix) {
            $userRegionWarning['show']     = (bool)$userMix['warn'];
            $userRegionWarning['home_pct'] = $userMix['home_pct'];
            $userRegionWarning['reason']   = $userMix['reason'];
        }

        return response()->json([
            'totals' => [
                'count'  => (int)($soTotalsScoped->orders_cnt ?? 0),
                'value'  => (int)($soTotalsScoped->orders_sum ?? 0),
                'region' => $region,
            ],
            'monthly' => ['categories' => $labels, 'values' => $values],
            'productSeries' => [
                'categories' => $topProductsKpi->pluck('product')->toArray(),
                'values'     => $topProductsKpi->pluck('val')->map(fn($v)=>(int)$v)->toArray(),
            ],
            'allFamilies'  => $allFamilies,
            'activeFamily' => $familySel,
            'allStatuses'  => $allStatuses,
            'activeStatus' => $status,
            'statusPie'    => $statusPie,

            'monthly_product_value' => $monthlyProductValue,
            'productCluster'        => ['categories' => $productCats, 'series' => $productClusterSeries],
            'monthlyProductCluster' => $monthlyProductCluster,

            'po_forecast_target'    => $poFcTarget,

            // ===== NEW shared-PO-pool conversion (use these in UI) =====
            'conversion_shared_pool' => $conversion_shared_pool,

            // projects sums (denominators you built)
            'project_sum_inhand_biddign' => $out['projects_sums'],   // keep original key
            'project_sum_inhand_bidding' => $out['projects_sums'],   // corrected alias

            // projects region pie
            'projectsRegionPie' => $projectsRegionPie,

            'multiMonthly' => [
                'categories' => $monthsMM,
                'bars'       => $barSeries,
                'lines'      => [], // reserved
            ],

            // MIX + USER BANNER
            'salesperson_region_mix' => $salespersonRegionMix ?? [],
            'user_region_warning'    => $userRegionWarning,
        ]);
    }
}
