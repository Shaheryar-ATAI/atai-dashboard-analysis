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
        $poReceivedList = "'po-received','po received','po_recieved','po-recieved','po recieved','po','po_received'";
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

        // === PO JOIN (robust, uses EXACT column names from your screenshot) ===
        // Normalize quotation numbers on BOTH sides the same way (trim + remove spaces, dots, dashes)
        // Portable normalization: TRIM → UPPER → strip spaces, dashes, dots, slashes
        $normProjects = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(projects.quotation_no)), ' ', ''), '-', ''), '.', ''), '/', '')";
        $normSales    = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(s.`Quote No.`)),           ' ', ''), '-', ''), '.', ''), '/', '')";


        $poSub = DB::table('salesorderlog as s')
            ->selectRaw("$normSales AS q_key")
            // exact column name per your schema: `PO. No.` (dot after PO and after No)
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

        // ===== Status logic requested: In-Hand/Bidding from project_type; Lost/PO-Received from status
        $computedStatus = "
CASE
    WHEN COALESCE(so.po_count, 0) > 0 THEN 'PO-Received'    -- hard override if any PO exists
    WHEN $st IN ($lostList)           THEN 'Lost'
    WHEN $pt IN ($inHandList)         THEN 'In-Hand'
    WHEN $pt IN ($biddingList)        THEN 'Bidding'
    ELSE 'Other'
END
";

        // Tab param -> normalize
        $statusNorm = $req->input('status_norm');
        if (!$statusNorm && $req->filled('status')) {
            $map = [
                'bidding' => 'Bidding',
                'inhand' => 'In-Hand', 'in-hand' => 'In-Hand', 'in hand' => 'In-Hand',
                'po' => 'PO-Received', 'po-received' => 'PO-Received', 'po_received' => 'PO-Received',
                'po-recieved' => 'PO-Received', 'po recieved' => 'PO-Received', 'po_recieved' => 'PO-Received',
                'lost' => 'Lost',
            ];
            $statusNorm = $map[strtolower(trim($req->input('status')))] ?? null;
        }

         if ($statusNorm === 'PO-Received') {
            $base->whereRaw('COALESCE(so.po_count, 0) > 0');
         } elseif ($statusNorm === 'Lost') {
             $base->whereRaw("$st IN ($lostList)");
         } elseif ($statusNorm === 'In-Hand') {
             $base->whereRaw("$pt IN ($inHandList)");
         } elseif ($statusNorm === 'Bidding') {
             $base->whereRaw("$pt IN ($biddingList)");
         }

        // Area filter
        if ($req->filled('area')) {
            $base->where('area', $req->input('area'));
        }

        // Dates
        $from = $req->input('date_from');
        $to   = $req->input('date_to');
        $y    = $req->integer('year');
        $m    = $req->integer('month');

        if ($from || $to) {
            $from = $from ?: '1900-01-01';
            $to   = $to   ?: '2999-12-31';
            $base->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
        } elseif ($m) {
            $yyyy = $y ?: date('Y');
            $startMonth = sprintf('%04d-%02d-01', $yyyy, $m);
            $base->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$startMonth, $startMonth]);
        } elseif ($y) {
            $base->whereRaw("YEAR($dateExpr) = ?", [$y]);
        }

        // Family chips
        if ($req->filled('family') && strtolower($req->input('family')) !== '') {
            $fam = strtolower(trim($req->input('family')));
            $base->where(function ($q) use ($fam) {
                if ($fam === 'ductwork') {
                    $q->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
                } elseif ($fam === 'dampers') {
                    $q->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
                } elseif (in_array($fam, ['sound','sound_attenuators','attenuators','attenuator'], true)) {
                    $q->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
                } elseif ($fam === 'accessories') {
                    $q->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
                } else {
                    $q->whereRaw('LOWER(atai_products) LIKE ?', ['%'.$fam.'%']);
                }
            });
        }

        // Totals
        $recordsTotal    = Project::query()->forUserRegion($user)->count();
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
        $a ? '<span class="badge area-badge area-'.e($a).'" style="color:black">'.e(strtoupper($a)).'</span>' : '—';

        $statusBadge = fn(?string $s) =>
        $s ? '<span class="badge bg-warning-subtle text-dark fw-bold">'.e(strtoupper($s)).'</span>' : '—';

        $fmtSar = fn($n) => 'SAR '.number_format((float) $n, 0);

        // Map
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
                'po_nos'  => $p->po_nos ?: '',
                'po_date' => $p->po_date ?: '',
                'atai_products'       => $p->atai_products,
                'quotation_value_fmt' => $fmtSar($value),
                'total_po_value_fmt'  => $fmtSar($p->total_po_value ?? 0),
                'status_badge'        => $statusBadge($p->status_display),
                'po_flag'             => $poFlag,
                'po_count'            => $poCount,
                'actions'             => '<button class="btn btn-sm btn-outline-success" data-action="view" data-id="'.$p->id.'">View</button>',
            ];
        });

        if ($statusNorm === 'PO-Received') {
            $sum = (clone $base)
                ->selectRaw('SUM(COALESCE(so.total_po_value, 0)) AS t')   // ← sum PO values
                ->value('t') ?: 0;
        } else {
            $sum = (clone $base)
                ->selectRaw('SUM(COALESCE(projects.quotation_value, projects.price, 0)) AS t')
                ->value('t') ?: 0;
        }

        return response()->json([
            'draw'                    => $draw,
            'recordsTotal'            => $recordsTotal,
            'recordsFiltered'         => $recordsFiltered,
            'data'                    => $data,
            'sum_quotation_value'     => (float) $sum,
            'sum_quotation_value_fmt' => $fmtSar($sum),
        ]);
    }

}
