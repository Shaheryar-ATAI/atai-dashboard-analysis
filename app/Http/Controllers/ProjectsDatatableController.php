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

        $statusCase = "
      CASE
        WHEN LOWER(TRIM(status)) IN ('in-hand','in hand','inhand','accepted','won','order','order in hand','ih') THEN 'In-Hand'
        WHEN LOWER(TRIM(status)) IN ('bidding','open','submitted','pending','quote','quoted','rfq','inquiry','enquiry') THEN 'Bidding'
        WHEN LOWER(TRIM(status)) IN ('lost','rejected','cancelled','canceled','closed lost','declined','not awarded') THEN 'Lost'
        ELSE 'Other'
      END
    ";

        $dateExpr = "COALESCE(
        STR_TO_DATE(quotation_date, '%Y-%m-%d'),
        STR_TO_DATE(quotation_date, '%d-%m-%Y'),
        STR_TO_DATE(quotation_date, '%d/%m/%Y'),
        DATE(created_at)
    )";

        // Base query (RBAC scoping & search scope if you have it)
        $base = Project::query()
            ->forUserRegion($user)
            ->search(data_get($req->input('search'), 'value', ''));

        // ---- Status filter: expect status_norm from the client ----
        $statusNorm = $req->input('status_norm');
        if (!$statusNorm && $req->filled('status')) {
            $map = ['bidding'=>'Bidding','inhand'=>'In-Hand','in-hand'=>'In-Hand','lost'=>'Lost'];
            $statusNorm = $map[strtolower(trim($req->input('status')))] ?? null;
        }
        if ($statusNorm) {
            $base->whereRaw("($statusCase) = ?", [$statusNorm]);
        }

        // ---- Area filter (param name is area)
        if ($req->filled('area')) {
            $base->where('area', $req->input('area'));
        }

        // ---- Family filter (THIS WAS MISSING) ----
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

        // ---- Date filters (year/month or from/to)
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

        // Counts
        $recordsTotal    = Project::query()->forUserRegion($user)->count();
        $recordsFiltered = (clone $base)->count();

        // Ordering (match your columns)
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
            8 => DB::raw($statusCase),
            // 9 => actions (not orderable)
        ];
        $orderCol = $orderable[$orderColIndex] ?? 'id';

        $rows = (clone $base)
            ->when($orderColIndex !== 9, fn($q) => $q->orderBy($orderCol, $orderDir))
            ->skip($start)->take($length)
            ->get();

        // Row builder
        $areaBadge = fn(?string $a) =>
        $a ? '<span class="badge area-badge area-'.e($a).'">'.e(strtoupper($a)).'</span>' : '—';

        $statusBadge = fn(?string $s) =>
        $s ? '<span class="badge bg-warning-subtle text-dark fw-bold">'.e(strtoupper($s)).'</span>' : '—';

        $fmtSar = fn($n) => 'SAR '.number_format((float) $n, 0);

        $data = $rows->map(function (Project $p) use ($areaBadge, $statusBadge, $fmtSar) {
            return [
                'id'                  => $p->id,
                'name'                => $p->canonical_name,
                'client'              => $p->canonical_client,
                'location'            => $p->canonical_location,
                'area_badge'          => $areaBadge($p->area),
                'quotation_no'        => $p->quotation_no,
                'atai_products'       => $p->atai_products,
                'quotation_date'      => $p->quotation_date_ymd,
                'quotation_value_fmt' => $fmtSar($p->canonical_value),
                'status_badge'        => $statusBadge($p->status),
                'actions'             => '<button class="btn btn-sm btn-outline-success" data-action="view" data-id="'.$p->id.'">View</button>',
            ];
        });

        $sum = (clone $base)->selectRaw('SUM(COALESCE(quotation_value, price, 0)) AS t')->value('t') ?: 0;
        $val = $p->canonical_value ?? ($p->quotation_value ?? $p->price ?? 0);
        return response()->json([
            'draw'                => $draw,
            'recordsTotal'        => $recordsTotal,
            'recordsFiltered'     => $recordsFiltered,
            'data'                => $data,
            'sum_quotation_value' => (float) $sum,
            'quotation_value_fmt' => $fmtSar($val),
        ]);
    }

}
