<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RegionScope;

class ForecastApiController extends Controller
{
    /**
     * GET /forecast/kpis?year=&month=&date_from=&date_to=&area=&salesman=
     * Returns:
     *  - area: [{area, sum_value}]     // bar chart (SAR by area)
     *  - salesman: [{salesman, sum_value}] // optional, if GM/Admin filtered to a region
     *  - total_value
     */
    public function kpis(Request $r)
    {
        $qb = $this->baseQuery($r);

        // Bar by area
        $byArea = (clone $qb)
            ->selectRaw('area, SUM(COALESCE(value_sar,0)) AS sum_value')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        // If a GM/Admin selected a specific region, show breakdown by salesman too
        $salesmanAgg = collect();
        if ($r->filled('area')) {
            $salesmanAgg = (clone $qb)
                ->selectRaw('COALESCE(salesman,"—") AS salesman, SUM(COALESCE(value_sar,0)) AS sum_value')
                ->groupBy('salesman')
                ->orderBy('sum_value','desc')
                ->limit(12)
                ->get();
        }

        $total = (clone $qb)->selectRaw('SUM(COALESCE(value_sar,0)) AS t')->value('t') ?? 0;

        return response()->json([
            'area'        => $byArea,
            'salesman'    => $salesmanAgg,
            'total_value' => (float) $total,
            'region_scope'=> $r->attributes->get('region_scope', 'ALL'),
        ]);
    }

    /**
     * GET /forecast/totals?...
     */
    public function totals(Request $r)
    {
        $qb = $this->baseQuery($r);

        return response()->json([
            'sum_value' => (float) ((clone $qb)->selectRaw('SUM(COALESCE(value_sar,0)) AS t')->value('t') ?? 0),
            'count'     => (int)   ((clone $qb)->count()),
        ]);
    }

    // ----------------- helpers -----------------
    private function baseQuery(Request $r)
    {
        // RBAC region (sales → fixed; gm/admin → null)
        $effectiveRegion = RegionScope::apply($r); // "Eastern"/"Central"/"Western" or null

        // Forecast table uses DATE column 'month'
        $qb = DB::table('forecast');

        // Date filters (priority: date range > month(+year) > year)
        $dateFrom = $r->query('date_from');
        $dateTo   = $r->query('date_to');
        $year     = $r->integer('year');
        $month    = $r->integer('month'); // 1..12

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ?: '1900-01-01';
            $to   = $dateTo   ?: '2999-12-31';
            $qb->whereBetween('month', [$from, $to]);
        } elseif ($month) {
            if ($year) {
                $qb->whereRaw('YEAR(month)=? AND MONTH(month)=?', [$year, $month]);
            } else {
                $qb->whereRaw('MONTH(month)=?', [$month]);
            }
        } elseif ($year) {
            $qb->whereRaw('YEAR(month)=?', [$year]);
        }

        // Family / Type (optional – mirror your UI if needed)
        if ($r->filled('family') && strtolower($r->query('family')) !== 'all') {
            $fam = strtolower($r->query('family'));
            $qb->where(function ($q) use ($fam) {
                if ($fam === 'ductwork') $q->whereRaw('LOWER(products) LIKE ?', ['%duct%']);
                elseif ($fam === 'dampers') $q->whereRaw('LOWER(products) LIKE ?', ['%damper%']);
                elseif (in_array($fam, ['sound','sound_attenuators','attenuators','attenuator'])) $q->whereRaw('LOWER(products) LIKE ?', ['%attenuator%']);
                elseif ($fam === 'accessories') $q->whereRaw('LOWER(products) LIKE ?', ['%accessor%']);
                else $q->whereRaw('LOWER(products) LIKE ?', ['%'.$fam.'%']);
            });
        }
        if ($r->filled('type')) {
            $qb->whereRaw('LOWER(type)=?', [strtolower($r->query('type'))]); // bidding|inhand|lost
        }

        // Salesman filter (text) — GM/Admin can type a name; for sales we’ll just show their own name in UI badge
        if ($r->filled('salesman')) {
            $qb->where('salesman', 'like', '%'.$r->query('salesman').'%');
        }

        // Region enforcement (like Projects)
        if (!empty($effectiveRegion)) {
            $qb->where('area', $effectiveRegion);
        } elseif ($r->filled('area')) {
            $qb->where('area', $r->query('area'));
        }

        // expose who we scoped to (for debugging in Network tab)
        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return $qb;
    }
}
