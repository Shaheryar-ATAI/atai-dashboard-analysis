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

        // Base (scoped)
        $base = Project::query()
            ->forUserRegion($user)                                   // <— scope
            ->status($req->input('status'))                          // <— scope
            ->search(data_get($req->input('search'), 'value', ''));  // <— scope

        // Optional filter params your JS already sends
        if ($region = $req->input('region')) {
            $base->where('area', $region);
        }
        if ($from = $req->input('date_from')) {
            $base->whereDate('quotation_date', '>=', $from);
        }
        if ($to = $req->input('date_to')) {
            $base->whereDate('quotation_date', '<=', $to);
        }
        if (!$from && !$to) {
            if ($y = $req->input('year'))  $base->whereYear('quotation_date', $y);
            if ($m = $req->input('month')) $base->whereMonth('quotation_date', $m);
        }

        // Counts
        $recordsTotal    = Project::query()->forUserRegion($user)->count();
        $recordsFiltered = (clone $base)->count();

        // Ordering map (index -> DB column)
        $orderColIndex = (int) data_get($req->input('order'), '0.column', 0);
        $orderDir      = data_get($req->input('order'), '0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderable = [
            0  => 'id',
            1  => 'project_name',
            2  => 'client_name',
            3  => 'project_location',
            4  => 'area',
            5  => 'quotation_no',
            6  => 'atai_products',
            7  => 'quotation_date',
            8  => 'action1',
            9  => 'quotation_value',
            10 => 'status',
        ];
        $orderCol = $orderable[$orderColIndex] ?? 'id';

        $rows = (clone $base)
            ->orderBy($orderCol, $orderDir)
            ->skip($start)->take($length)
            ->get();

        // Small HTML helpers (your JS expects these HTML fields)
        $areaBadge = fn(?string $a) =>
        $a ? '<span class="badge area-badge area-'.e($a).'">'.e(strtoupper($a)).'</span>' : '—';

        $statusBadge = fn(?string $s) =>
        $s ? '<span class="badge bg-warning-subtle text-dark fw-bold">'.e(strtoupper($s)).'</span>' : '—';

        $fmtSar = fn($n) => 'SAR '.number_format((float) $n, 0);

        $data = $rows->map(function (Project $p) use ($areaBadge, $statusBadge, $fmtSar) {
            return [
                'id'                 => $p->id,
                'name'               => $p->canonical_name,
                'client'             => $p->canonical_client,
                'location'           => $p->canonical_location,
                'area_badge'         => $areaBadge($p->area),
                'quotation_no'       => $p->quotation_no,
                'atai_products'      => $p->atai_products,
                'quotation_date'     => $p->quotation_date_ymd,
                'action1'            => $p->action1,
                'quotation_value_fmt'=> $fmtSar($p->canonical_value),
                'status_badge'       => $statusBadge($p->status),
                'actions'            => '<button class="btn btn-sm btn-outline-success" data-action="view" data-id="'.$p->id.'">View</button>',
            ];
        });
        $sum = (clone $base)->sum(DB::raw('COALESCE(quotation_value, price)'));

        return response()->json([
            'draw'                 => $draw,
            'recordsTotal'         => $recordsTotal,
            'recordsFiltered'      => $recordsFiltered,
            'data'                 => $data,
            'sum_quotation_value'  => (float) $sum,
        ]);
    }
}
