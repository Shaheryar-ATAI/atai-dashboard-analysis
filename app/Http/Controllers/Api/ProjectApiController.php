<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RegionScope;   // ⬅️ same helper used in your DT controller
use App\Models\Project;

class ProjectApiController extends Controller
{
    /**
     * Highcharts data (and total) used on the dashboard.
     * GET /api/kpis?family=ductwork&area=Eastern&year=2025
     */
    public function kpis(Request $r)
    {
        [$base] = $this->baseQuery($r);
        $regionParam = (string) $r->input('region');
        // Aggregate by area (bar)
        $areaAgg = (clone $base)
            ->selectRaw('area,
                     COUNT(*) AS cnt,
                     SUM(COALESCE(quotation_value, price, 0)) AS sum_value')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        // Aggregate by status (pie)
        $statusAgg = (clone $base)
            ->select('status', DB::raw('SUM(COALESCE(quotation_value, price, 0)) AS sum_value'))
            ->groupBy('status')
            ->get();

        // Totals
        $totalCount = (clone $base)->count();
        $totalValue = (float) (clone $base)
            ->selectRaw('SUM(COALESCE(quotation_value, price, 0)) AS t')
            ->value('t');

        return response()->json([
            'area'         => $areaAgg,
            'status'       => $statusAgg,
            'total_count'  => (int) $totalCount,
            'total_value'  => (float) $totalValue,
            'region_scope' => $r->attributes->get('region_scope', 'ALL'),
        ]);
    }

    /**
     * Single project for the modal.
     * GET /api/inquiries/{id}
     */
    public function show($id)
    {
        $p = Project::query()->findOrFail($id);

        return response()->json([
            'id'             => $p->id,
            'name'           => $p->name,
            'client'         => $p->client,
            'location'       => $p->location,
            'area'           => $p->area,
            'price'          => (float) ($p->quotation_value ?? $p->price ?? 0),
            'currency'       => 'SAR',
            'status'         => $p->status,
            'comments'       => $p->remark,
            'checklist'      => (object) [],
            'quotation_no'   => $p->quotation_no,
            'quotation_date' => $p->quotation_date,
            'atai_products'  => $p->atai_products,
        ]);
    }

    /**
     * Total line (for the "Total (SAR)" box).
     * GET /api/totals?family=…&area=…&status=…&year=…
     */
    public function totals(Request $r)
    {
        [$base] = $this->baseQuery($r);

        if ($r->filled('status')) {
            $base->where('status', $r->query('status'));
        }

        $sum = (float) (clone $base)
            ->selectRaw('SUM(COALESCE(quotation_value, price, 0)) as sum_price')
            ->value('sum_price');

        $cnt = (clone $base)->count();

        return response()->json([
            'count'     => (int) $cnt,
            'sum_price' => (float) $sum,
        ]);
    }

    /* ============================ Helpers ============================ */

    /**
     * Build the base query with RBAC region scoping + filters.
     * Returns: [$qb]
     */
    private function baseQuery(Request $r): array
    {
        // Try helper first (if you have middleware populating it)
        $effectiveRegion = \App\Support\RegionScope::apply($r); // may be null

        // Fallback from logged-in user (bullet-proof)
        $u = $r->user();
        if (!$effectiveRegion && $u) {
            $isManagerial = method_exists($u, 'hasAnyRole')
                ? $u->hasAnyRole(['gm','admin','manager'])
                : false;

            if (! $isManagerial && !empty($u->region)) {
                $effectiveRegion = $u->region;   // e.g. "Eastern"
            }
        }

        // Normalize for robust match
        $norm = fn($s) => strtolower(trim((string)$s));

        // Handle string quotation_date; fallback to created_at
        $dateExpr = "COALESCE(
        STR_TO_DATE(quotation_date, '%Y-%m-%d'),
        STR_TO_DATE(quotation_date, '%d-%m-%Y'),
        DATE(created_at)
    )";


        $qb = DB::table('projects');

        // Year filter
        if ($r->integer('year')) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$r->integer('year')]);
        }
        // ----- DATE FILTERS -----
        $dateFrom = $r->query('date_from');
        $dateTo   = $r->query('date_to');
        $year     = $r->integer('year');
        $month    = $r->integer('month'); // 1..12

        if ($dateFrom || $dateTo) {
            // inclusive between
            $from = $dateFrom ?: '1900-01-01';
            $to   = $dateTo   ?: '2999-12-31';
            $qb->whereRaw("$dateExpr BETWEEN ? AND ?", [$from, $to]);
        } elseif ($month) {
            // month (optionally with year)
            $yyyy = $year ?: date('Y');
            // first and last day of that month
            $start = sprintf('%04d-%02d-01', $yyyy, $month);
            // LAST_DAY() keeps MySQL doing the month-end calc
            $qb->whereRaw("$dateExpr BETWEEN ? AND LAST_DAY(?)", [$start, $start]);
        } elseif ($year) {
            $qb->whereRaw("YEAR($dateExpr) = ?", [$year]);
        }
        // Family filter
        if ($r->filled('family') && strtolower($r->query('family')) !== 'all') {
            $this->applyFamilyFilterQB($qb, strtolower($r->query('family')));
        }

        // 🔐 REGION ENFORCEMENT (mirror DataTable)
        if (!empty($effectiveRegion)) {
            // Sales: force own area; ignore ?area
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($effectiveRegion)]);
        } elseif ($r->filled('area')) {
            // GM/Admin: can narrow with ?area
            $qb->whereRaw('LOWER(TRIM(area)) = ?', [$norm($r->query('area'))]);
        }

        // Expose for Network tab sanity check
        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return [$qb];
    }

    /**
     * Family → WHERE mapping for Query Builder (case-insensitive).
     */
    private function applyFamilyFilterQB($qb, string $family): void
    {
        $fam = trim($family);
        $qb->where(function ($qq) use ($fam) {
            if ($fam === 'ductwork') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%duct%']);
            } elseif ($fam === 'dampers') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%damper%']);
            } elseif (in_array($fam, ['sound_attenuators','attenuators','attenuator'], true)) {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%attenuator%']);
            } elseif ($fam === 'accessories') {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%accessor%']);
            } else {
                $qq->whereRaw('LOWER(atai_products) LIKE ?', ['%'.$fam.'%']);
            }
        });
    }
}
