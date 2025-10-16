<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectsDatatableController extends Controller
{



    public function data(Request $req)
    {
        $user   = $req->user();
        $draw   = (int) $req->input('draw', 1);
        $start  = (int) $req->input('start', 0);
        $length = (int) $req->input('length', 10);

        // Buckets
        $inHandList     = "'in-hand','in hand','inhand','accepted','won','order','order in hand','ih'";
        $biddingList    = "'bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry'";
        $lostList       = "'lost','rejected','cancelled','canceled','closed lost','declined','not awarded'";

        // Column shortcuts
        $pt = "LOWER(TRIM(projects.project_type))";
        $st = "LOWER(TRIM(projects.status))";

        // Date expr
        $dateExpr = "COALESCE(
            STR_TO_DATE(quotation_date, '%Y-%m-%d'),
            STR_TO_DATE(quotation_date, '%d-%m-%Y'),
            STR_TO_DATE(quotation_date, '%d/%m/%Y'),
            STR_TO_DATE(quotation_date, '%d.%m.%Y'),
            DATE(created_at)
        )";

        // Base scope + global search
        $base = Project::query()
            ->forUserRegion($user)
            ->search(data_get($req->input('search'), 'value', ''));

        // === PO JOIN ===
        $normProjects = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(projects.quotation_no)), ' ', ''), '-', ''), '.', ''), '/', '')";
        $normSales    = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(s.`Quote No.`)),           ' ', ''), '-', ''), '.', ''), '/', '')";

        $poSub = DB::table('salesorderlog as s')
            ->selectRaw("$normSales AS q_key")
            ->selectRaw("COUNT(DISTINCT s.`PO. No.`) AS po_count")
            ->selectRaw("
                GROUP_CONCAT(
                    DISTINCT s.`PO. No.`
                    ORDER BY s.`date_rec` IS NULL, s.`date_rec` ASC
                    SEPARATOR ', '
                ) AS po_nos
            ")
            ->selectRaw("DATE_FORMAT(MAX(COALESCE(s.`date_rec`, s.`created_at`)), '%Y-%m-%d') AS po_date")
            ->selectRaw("SUM(COALESCE(s.`PO Value`,0)) AS total_po_value")
            ->whereNotNull(DB::raw('s.`Quote No.`'))
            ->whereRaw("TRIM(s.`Quote No.`) <> ''")
            ->groupBy('q_key');

        $base = $base->leftJoinSub($poSub, 'so', function ($join) use ($normProjects) {
            $join->on(DB::raw($normProjects), '=', DB::raw('so.q_key'));
        });

        // ===== Computed status =====
        $computedStatus = "
            CASE
              WHEN COALESCE(so.po_count, 0) > 0 THEN 'PO-Received'
              WHEN $st IN ($lostList)           THEN 'Lost'
              WHEN $pt IN ($inHandList)         THEN 'In-Hand'
              WHEN $pt IN ($biddingList)        THEN 'Bidding'
              ELSE 'Other'
            END
        ";

        // ========== Common filters (date/family/salesman) – applied to both global & tab queries ==========
        $applyCommonFilters = function ($q) use ($req, $dateExpr, $user) {
            // Salesman (GM/Admin can see all unless salesman is provided)
            if ($req->filled('salesman')) {
                $q->where('salesman', $req->input('salesman'));
            } elseif (!$user->hasAnyRole(['gm','admin'])) {
                $q->where('salesman', $user->name);
            }

            // Dates
            $from = $req->input('date_from');
            $to   = $req->input('date_to');
            $y    = $req->integer('year');
            $m    = $req->integer('month');

            if ($from || $to) {
                $from = $from ?: '1900-01-01';
                $to   = $to   ?: '2999-12-31';
                $q->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
            } elseif ($m) {
                $yyyy = $y ?: date('Y');
                $startMonth = sprintf('%04d-%02d-01', $yyyy, $m);
                $q->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$startMonth, $startMonth]);
            } elseif ($y) {
                $q->whereRaw("YEAR($dateExpr) = ?", [$y]);
            }

            // Family
            if ($req->filled('family') && strtolower($req->input('family')) !== '') {
                $fam = strtolower(trim($req->input('family')));
                $q->where(function ($qq) use ($fam) {
                    if ($fam === 'ductwork') {
                        $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
                    } elseif ($fam === 'dampers') {
                        $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
                    } elseif (in_array($fam, ['sound','sound_attenuators','attenuators','attenuator'], true)) {
                        $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
                    } elseif ($fam === 'accessories') {
                        $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
                    } else {
                        $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%'.$fam.'%']);
                    }
                });
            }
        };

        // Clone BEFORE status filter so we can compute global header sum immediately
        $globalBase = (clone $base);
        $applyCommonFilters($globalBase);

        // Global totals (count for header + value for header)
        $recordsTotal = (clone $globalBase)->count();
        $headerSum = (clone $globalBase)
            ->selectRaw('SUM(COALESCE(projects.quotation_value, projects.price, 0)) AS t')
            ->value('t') ?: 0;

        // ====== TAB filter (status_norm) – only for the table data and tab sum ======
        $statusNorm = $req->input('status_norm');
        if (!empty($statusNorm)) {
            $base->whereRaw("($computedStatus) = ?", [$statusNorm]);
        }

        // Apply common filters to the tab query too
        $applyCommonFilters($base);

        // Filtered count (for table)
        $recordsFiltered = (clone $base)->count();

        // Ordering
        $orderColIndex = (int) data_get($req->input('order'), '0.column', 0);
        $orderDir      = data_get($req->input('order'), '0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderable = [
            0 => 'id',
            1 => 'project_name',
            2 => 'client_name',
            3 => 'project_location',
            4 => 'area',
            5 => 'quotation_no',
            6 => 'atai_products',
            7 => 'quotation_value',
            8 => DB::raw("($computedStatus)"),
        ];
        $orderCol = $orderable[$orderColIndex] ?? 'id';

        // Rows
        $rows = (clone $base)
            ->when($orderColIndex !== 9, fn($q) => $q->orderBy($orderCol, $orderDir))
            ->skip($start)->take($length)
            ->get([
                'projects.*',
                DB::raw("($computedStatus) AS status_display"),
                DB::raw("COALESCE(so.po_count, 0)        AS po_count"),
                DB::raw("so.po_nos                        AS po_nos"),
                DB::raw("so.po_date                       AS po_date"),
                DB::raw("COALESCE(so.total_po_value, 0)  AS total_po_value"),
            ]);

        // Helpers
        $areaBadge = fn(?string $a) =>
        $a ? '<span class="badge area-badge area-'.e($a).'" style="color:#ff0000">'.e(strtoupper($a)).'</span>' : '—';

        $statusBadge = fn(?string $s) =>
        $s ? '<span class="badge bg-warning-subtle text-dark fw-bold">'.e(strtoupper($s)).'</span>' : '—';

        $fmtSar = fn($n) => 'SAR '.number_format((float) $n, 0);

        // Map to DT rows
        $data = $rows->map(function (Project $p) use ($areaBadge, $statusBadge, $fmtSar) {
            $poCount = (int) ($p->po_count ?? 0);
            $poFlag  = $poCount > 0
                ? '<span class="badge text-bg-success">PO</span>'
                : '<span class="badge text-bg-secondary">No PO</span>';

            $value = $p->canonical_value ?? ($p->quotation_value ?? $p->price ?? 0);

            return [
                'id'                  => $p->id,
                'name'                => $p->canonical_name ?? $p->project_name,
                'client'              => $p->canonical_client ?? $p->client_name,
                'location'            => $p->canonical_location ?? $p->project_location,
                'area_badge'          => $areaBadge($p->area),
                'quotation_no'        => $p->quotation_no,
                'quotation_date'      => $p->quotation_date_ymd ?? $p->quotation_date,
                'po_nos'              => $p->po_nos ?: '',
                'po_date'             => $p->po_date ?: '',
                'atai_products'       => $p->atai_products,
                'quotation_value_fmt' => $fmtSar($value),
                'total_po_value_fmt'  => $fmtSar($p->total_po_value ?? 0),
                'status_badge'        => $statusBadge($p->status_display),
                'po_flag'             => $poFlag,
                'po_count'            => $poCount,
                'actions'             => '<button class="btn btn-sm btn-outline-success" data-action="view" data-id="'.$p->id.'">View</button>',
            ];
        });

        // Tab sum (respecting status_norm if present)
        if ($statusNorm === 'PO-Received') {
            $tabSum = (clone $base)
                ->selectRaw('SUM(COALESCE(so.total_po_value, 0)) AS t')
                ->value('t') ?: 0;
        } else {
            $tabSum = (clone $base)
                ->selectRaw('SUM(COALESCE(projects.quotation_value, projects.price, 0)) AS t')
                ->value('t') ?: 0;
        }

        return response()->json([
            'draw'                            => $draw,
            // header totals (global – no status filter)
            'recordsTotal'                    => $recordsTotal,
            'header_sum_value'                => (float) $headerSum,
            'header_sum_value_fmt'            => $fmtSar($headerSum),

            // table totals (filtered – status_norm applied)
            'recordsFiltered'                 => $recordsFiltered,
            'sum_quotation_value'             => (float) $tabSum,
            'sum_quotation_value_fmt'         => $fmtSar($tabSum),

            'data'                            => $data,
        ]);
    }


}
