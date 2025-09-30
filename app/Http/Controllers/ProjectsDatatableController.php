<?php

namespace App\Http\Controllers;
use App\Support\RegionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Yajra endpoint for Projects tables (Bidding / In-Hand / Lost).
 * Accepts the following query params:
 *  - status: 'bidding' | 'inhand' | 'lost'   (per tab)
 *  - family: '', 'ductwork', 'dampers', 'sound', 'accessories' (chip filter)
 *  - year:   YYYY                               (top-left filter)
 *  - region: 'Eastern'|'Central'|'Western'      (top-left filter)
 *
 * Assumed columns in `projects` table:
 *  id, name, client, location, area, quotation_no, atai_products, quotation_value, status,
 *  quotation_date (or created_at for date fallback).
 */
class ProjectsDatatableController extends Controller
{

   public function data(Request $r)
{
    $status = strtolower((string) $r->input('status'));   // bidding|inhand|lost
    $family = (string) $r->input('family');               // '', 'ductwork','dampers','sound','accessories'
    $year   = $r->integer('year');
    $dateFrom = $r->query('date_from');
    $dateTo   = $r->query('date_to');
    $month    = $r->integer('month');
    $regionParam = (string) $r->input('region');          // from UI (only used for GM/Admin)

    // 🔐 Get effective region from RBAC (sales => fixed; gm/admin => null)
    $effectiveRegion = RegionScope::apply($r);

    $tbl = DB::table('projects');

    $dateExpr = "COALESCE(STR_TO_DATE(quotation_date, '%Y-%m-%d'), STR_TO_DATE(quotation_date, '%d-%m-%Y'), created_at)";

    // ----- Base filters (status/year)
    if ($status === 'bidding') {
        $tbl->where('status', 'bidding');
    } elseif ($status === 'inhand' || $status === 'in-hand') {
        $tbl->where('status', 'inhand');
    } elseif ($status === 'lost') {
        $tbl->where('status', 'lost');
    }

    // Replace the existing year filter with this:
    if ($dateFrom || $dateTo) {
        $from = $dateFrom ?: '1900-01-01';
        $to   = $dateTo   ?: '2999-12-31';
        $tbl->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
    } elseif ($month) {
        $yyyy = $year ?: date('Y');
        $start = sprintf('%04d-%02d-01', $yyyy, $month);
        $tbl->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$start, $start]);
    } elseif ($year) {
        $tbl->whereRaw("YEAR($dateExpr) = ?", [$year]);
    }

    // ✅ REGION ENFORCEMENT (area column in your DB)
    if (!empty($effectiveRegion)) {
        // Sales users: force their own area (ignore any ?region from UI)
        $tbl->where('area', $effectiveRegion);
    } elseif (!empty($regionParam)) {
        // GM/Admin: allow UI region filter if provided
        $tbl->where('area', $regionParam);
    }

    if ($family) {
        $tbl->where('atai_products', 'like', '%' . $family . '%');
    }

    // Precompute sum AFTER all filters applied
    $sumFiltered = (clone $tbl)->sum('quotation_value');

    $q = $tbl->select([
        'id',
        'name',
        'client',
        'location',
        'area',               // <— area is the region field in your DB
        'quotation_no',
        'atai_products',
        'quotation_value',
        'status',
    ]);

    return DataTables::of($q)
        ->addColumn('area_badge', function ($row) {
            $area = $row->area ?: '-';
            $cls  = 'area-' . preg_replace('/[^A-Za-z]/', '', $area);
            return '<span class="badge area-badge ' . e($cls) . '">' . e(strtoupper($area)) . '</span>';
        })
        ->addColumn('quotation_value_fmt', function ($row) {
            $v = (float) ($row->quotation_value ?? 0);
            return 'SAR ' . number_format($v, 0);
        })
        ->addColumn('status_badge', function ($row) {
            $s    = strtolower((string) $row->status);
            $text = strtoupper($s ?: '-');
            $cls  = match ($s) {
                'bidding' => 'bg-warning text-dark',
                'inhand', 'in-hand' => 'bg-success',
                'lost'    => 'bg-danger',
                default   => 'bg-secondary',
            };
            return '<span class="badge ' . $cls . '">' . e($text) . '</span>';
        })
        ->addColumn('actions', function ($row) {
            return '<button type="button" class="btn btn-sm btn-outline-primary" data-action="view" data-id="' .
                   e($row->id) . '">View</button>';
        })
        ->rawColumns(['area_badge', 'status_badge', 'actions'])
        ->with('sum_quotation_value', (float) $sumFiltered)
        ->make(true);
}

}

