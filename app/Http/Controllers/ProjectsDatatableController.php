<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectsDatatableController extends Controller
{
    /* =========================================================
        * SALESPERSON REGION ALIAS HELPERS (same as SalesOrderManagerController)
        * ========================================================= */

    /** Region → salesperson aliases */
    protected function salesAliasesForRegion(?string $regionNorm): array
    {
        return match ($regionNorm) {
            'eastern' => ['SOHAIB', 'SOAHIB'],
            'central' => ['TARIQ', 'TAREQ', 'JAMAL'],
            'western' => ['ABDO', 'ABDUL', 'ABDOU', 'AHMED', 'AHMEDAMIN'],
            default => [],
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
            'TARIQ' => 'central',
            'TAREQ' => 'central',
            'JAMAL' => 'central',

            // Western
            'ABDO' => 'western',
            'AHMED' => 'western',
        ];
    }

    /** Canonicalize salesperson name (UPPERCASE, remove spaces) */
    protected function canonSalesKey(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string)$name));
    }


    public function data(Request $req)
    {
        /** @var \App\Models\User|null $user */
        $user = $req->user();
        $draw = (int)$req->input('draw', 1);
        $start = (int)$req->input('start', 0);
        $length = (int)$req->input('length', 10);

        $inHandList = "'in-hand','in hand','inhand','accepted','won','order','order in hand','ih'";
        $biddingList = "'bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry'";
        $lostList = "'lost','rejected','cancelled','canceled','closed lost','declined','not awarded'";

        $sc = "LOWER(TRIM(COALESCE(projects.status_current, projects.status)))";
        $pt = "LOWER(TRIM(projects.project_type))";

        $dateExpr = "COALESCE(
        STR_TO_DATE(quotation_date, '%Y-%m-%d'),
        STR_TO_DATE(quotation_date, '%d-%m-%Y'),
        STR_TO_DATE(quotation_date, '%d/%m/%Y'),
        STR_TO_DATE(quotation_date, '%d.%m.%Y'),
        DATE(created_at)
    )";

        $searchVal = data_get($req->input('search'), 'value', '');
        $statusNorm = (string)$req->input('status_norm', '');
        $statusNormLc = strtolower($statusNorm);

        $base = Project::query();

        $computedStatus = "
      CASE
        WHEN ($sc LIKE 'po-received%' OR $sc LIKE 'po received%' OR $sc = 'po') THEN 'PO-Received'
        WHEN $sc IN ($lostList)                                   THEN 'Lost'
        WHEN ($pt IN ($inHandList) OR $sc IN ($inHandList))       THEN 'In-Hand'
        WHEN ($pt IN ($biddingList) OR $sc IN ($biddingList))     THEN 'Bidding'
        ELSE 'Other'
      END
    ";

        $canViewAll = $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['gm', 'admin']);

        /**
         * Build alias set for a given name:
         *  - normalizes (UPPER, remove spaces)
         *  - if it matches any region alias set, return that whole set
         *  - otherwise return just the canonical key
         */
        $buildAliasSet = function (?string $name): array {
            if ($name === null || $name === '') {
                return [];
            }

            $keyFull = strtoupper(preg_replace('/\s+/', '', $name));
            $first = strtoupper(preg_replace('/\s+/', '', explode(' ', $name)[0] ?? ''));

            foreach (['eastern', 'central', 'western'] as $region) {
                $set = $this->salesAliasesForRegion($region);
                if (in_array($keyFull, $set, true) || in_array($first, $set, true)) {
                    return $set; // full alias set for that salesperson's region
                }
            }

            // Fallback – at least filter by the canonicalized first token
            return [$first !== '' ? $first : $keyFull];
        };

        // ---------- Common filters (date/family/salesman/area) ----------
        $applyCommonFilters = function ($q) use ($req, $dateExpr, $user, $canViewAll, $buildAliasSet) {

            // ---- SALESMAN filter ----
            if ($req->filled('salesman')) {
                // Explicit salesman filter from chips/dropdown
                $aliases = $buildAliasSet($req->input('salesman'));
                if (!empty($aliases)) {
                    $q->where(function ($qq) use ($aliases) {
                        foreach ($aliases as $a) {
                            $qq->orWhereRaw("REPLACE(UPPER(TRIM(projects.salesperson)),' ','')) = ?", [$a]);
                        }
                    });
                }
            } elseif ($user && !$canViewAll) {
                // Logged-in non-GM/Admin: show all records under THEIR name (with aliases)
                $aliases = $buildAliasSet($user->name);
                if (!empty($aliases)) {
                    $q->where(function ($qq) use ($aliases) {
                        foreach ($aliases as $a) {
                            $qq->orWhereRaw("REPLACE(UPPER(TRIM(salesman)),' ','') = ?", [$a]);
                        }
                    });
                }
            }
            // GM/Admin with no salesman filter → no salesman condition (see all)

            // ---- Area dropdown (optional, for all roles) ----
            if ($req->filled('area')) {
                $area = trim((string)$req->input('area'));
                if ($area !== '' && strcasecmp($area, 'All') !== 0) {
                    $q->where('projects.area', $area);
                }
            }

            // ---- Dates ----
            $from = $req->input('date_from');
            $to = $req->input('date_to');
            $y = $req->integer('year');
            $m = $req->integer('month');

            if ($from || $to) {
                $from = $from ?: '1900-01-01';
                $to = $to ?: '2999-12-31';
                $q->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
            } elseif ($m) {
                $yyyy = $y ?: date('Y');
                $startMonth = sprintf('%04d-%02d-01', $yyyy, $m);
                $q->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$startMonth, $startMonth]);
            } elseif ($y) {
                $q->whereRaw("YEAR($dateExpr) = ?", [$y]);
            }

            // ---- Family chips (ignore "all") ----
            if ($req->filled('family')) {
                $fam = strtolower(trim($req->input('family')));
                if ($fam !== '' && $fam !== 'all') {
                    $q->where(function ($qq) use ($fam) {
                        if ($fam === 'ductwork') {
                            $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
                        } elseif ($fam === 'dampers') {
                            $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
                        } elseif (in_array($fam, ['sound', 'sound_attenuators', 'attenuators', 'attenuator'], true)) {
                            $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
                        } elseif ($fam === 'accessories') {
                            $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
                        } else {
                            $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%' . $fam . '%']);
                        }
                    });
                }
            }
        };

        /* ---------- Header totals (respect status_norm + chips) ---------- */
        $headerBase = (clone $base);

        if ($statusNorm !== '') {
            if ($statusNormLc === 'po-received') {
                $headerBase->whereRaw("($sc LIKE 'po-received%' OR $sc LIKE 'po received%' OR $sc = 'po')");
            } else {
                $headerBase->whereRaw("($computedStatus) = ?", [$statusNorm]);
            }
        }

        $applyCommonFilters($headerBase);

        $recordsTotal = (clone $headerBase)->count();
        $headerSum = (clone $headerBase)
            ->selectRaw('SUM(COALESCE(projects.quotation_value, projects.price, 0)) AS t')
            ->value('t') ?: 0;

        // ---------- Table query ----------
        $tableQ = (clone $base);

        if ($statusNorm !== '') {
            if ($statusNormLc === 'po-received') {
                $tableQ->whereRaw("($sc LIKE 'po-received%' OR $sc LIKE 'po received%' OR $sc = 'po')");
            } else {
                $tableQ->whereRaw("($computedStatus) = ?", [$statusNorm]);
            }
        }

        $applyCommonFilters($tableQ);

        if (strlen($searchVal)) {
            $needle = preg_replace('/[\s.\-\/]/', '', strtoupper($searchVal));
            $tableQ->where(function ($qq) use ($searchVal, $needle) {
                $qq->where('projects.project_name', 'like', "%{$searchVal}%")
                    ->orWhere('projects.client_name', 'like', "%{$searchVal}%")
                    ->orWhere('projects.atai_products', 'like', "%{$searchVal}%")
                    ->orWhere('projects.quotation_no', 'like', "%{$searchVal}%")
                    ->orWhere('projects.salesperson', 'like', "%{$searchVal}%")
                    ->orWhereRaw("
                    REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(projects.quotation_no)),' ',''),'-',''),'.',''),'/','')
                    LIKE ?
               ", ["%{$needle}%"]);
            });
        }

        $recordsFiltered = (clone $tableQ)->count();

        $phaseForProgress = ($statusNorm === 'In-Hand') ? 'INHAND' : (($statusNorm === 'Bidding') ? 'BIDDING' : null);
        $progressExpr = $phaseForProgress
            ? "(SELECT MAX(pcs.progress) FROM project_checklist_states pcs
            WHERE pcs.project_id = projects.id AND pcs.phase = '{$phaseForProgress}')"
            : "(SELECT MAX(pcs.progress) FROM project_checklist_states pcs
            WHERE pcs.project_id = projects.id)";

        $statusSelectSql = "($computedStatus)";

        $orderColIndex = (int)data_get($req->input('order'), '0.column', 0);
        $orderDir = data_get($req->input('order'), '0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderable = [
            0 => 'id',
            1 => 'project_name',
            2 => 'client_name',
            3 => 'project_location',
            4 => 'area',
            5 => 'quotation_no',
            6 => 'atai_products',
            7 => 'quotation_value',
            8 => DB::raw($statusSelectSql),
            9 => DB::raw("($progressExpr)"),
        ];
        $orderCol = $orderable[$orderColIndex] ?? 'id';

        $rowsQ = (clone $tableQ);
        if ($orderColIndex !== 9) {
            $rowsQ->orderBy($orderCol, $orderDir);
        } else {
            $rowsQ->orderByRaw("($progressExpr) $orderDir");
        }

        $rows = $rowsQ->skip($start)->take($length)->get([
            'projects.*',
            DB::raw("$statusSelectSql AS status_display"),
            DB::raw("($progressExpr) AS progress_pct"),
        ]);

        $areaBadge = fn(?string $a) => $a ? '<span class="badge area-badge area-' . e($a) . '">' . e(strtoupper($a)) . '</span>' : '—';
        $statusBadge = fn(?string $s) => $s ? '<span class="badge bg-warning-subtle text-dark fw-bold">' . e(strtoupper($s)) . '</span>' : '—';
        $fmtSar = fn($n) => 'SAR ' . number_format((float)$n, 0);

        $data = $rows->map(function (Project $p) use ($areaBadge, $statusBadge, $fmtSar) {
            $value = $p->canonical_value ?? ($p->quotation_value ?? $p->price ?? 0);
            $statusDisplay = $p->status_display ?? $p->status;

            return [
                'id' => $p->id,
                'name' => $p->canonical_name ?? $p->project_name,
                'client' => $p->canonical_client ?? $p->client_name,
                'salesperson' => $p->salesperson,
                'location' => $p->canonical_location ?? $p->project_location,
                'area_badge' => $areaBadge($p->area),
                'quotation_no' => $p->quotation_no,
                'quotation_date' => $p->quotation_date_ymd ?? $p->quotation_date,
                'atai_products' => $p->atai_products,
                'quotation_value_fmt' => $fmtSar($value),
                'status' => $statusDisplay,
                'status_display' => $statusDisplay,
                'status_badge' => $statusBadge($statusDisplay),
                'progress_pct' => isset($p->progress_pct) ? (int)$p->progress_pct : null,
                'actions' => '<button class="btn btn-sm btn-outline-success" data-action="view" data-id="' . $p->id . '">View</button>',
            ];
        });

        $tabSum = (clone $tableQ)
            ->selectRaw('SUM(COALESCE(projects.quotation_value, projects.price, 0)) AS t')
            ->value('t') ?: 0;

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'header_sum_value' => (float)$headerSum,
            'header_sum_value_fmt' => 'SAR ' . number_format((float)$headerSum, 0),
            'sum_quotation_value' => (float)$tabSum,
            'sum_quotation_value_fmt' => 'SAR ' . number_format((float)$tabSum, 0),
            'data' => $data,
        ]);
    }


}
