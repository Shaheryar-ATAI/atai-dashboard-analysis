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
     */
    public function kpis(Request $r)
    {
        // ─────────────────────────────────────────────────────────────
        // FORECAST via baseQuery (must expose: area, value_sar, month)
        // ─────────────────────────────────────────────────────────────
        $qb = $this->baseQuery($r);

        $regionScope = $r->attributes->get('region_scope', 'ALL');
        // Area totals
        $byArea = (clone $qb)
            ->selectRaw('area, SUM(COALESCE(value_sar,0)) AS sum_value')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        // Salesman totals (only when a region is selected)
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

        // ─────────────────────────────────────────────────────────────
        // Month window (year => full year; else last 12 months)
        // ─────────────────────────────────────────────────────────────
        if ($r->integer('year')) {
            $y     = $r->integer('year');
            $start = \Carbon\Carbon::create($y, 1, 1)->startOfMonth();
            $end   = \Carbon\Carbon::create($y, 12, 1)->endOfMonth();
        } else {
            $end   = now()->endOfMonth();
            $start = (clone $end)->subMonthsNoOverflow(11)->startOfMonth();
        }

        $months = [];
        for ($d = $start->copy(); $d <= $end; $d->addMonth()) {
            $months[] = $d->format('Y-m');
        }

        // Forecast date column (schema shows DATE `month`)
        $fcDate = 'month';

        // helper: cast string/number safely
        $cast = fn ($col) => "CAST(NULLIF(REPLACE(REPLACE($col, ',', ''), ' ', ''), '') AS DECIMAL(18,2))";

        // Preferred region order
        $regionsPref = ['Central','Eastern','Western'];

        // Forecast rows per month/area
        $rowsF = (clone $qb)
            ->selectRaw("DATE_FORMAT($fcDate, '%Y-%m') AS ym, COALESCE(area,'—') AS area, SUM(COALESCE(value_sar,0)) AS total")
            ->whereBetween(DB::raw($fcDate), [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area')
            ->orderBy('ym')
            ->get();

        // ─────────────────────────────────────────────────────────────
        // AUTO-DETECT column names on other sources
        // ─────────────────────────────────────────────────────────────
        $detect = function (string $table, array $candidates, ?string $default = null) {
            foreach ($candidates as $c) {
                try {
                    DB::table($table)->selectRaw($c)->limit(1)->first();
                    return $c;
                } catch (\Throwable $e) { /* try next */ }
            }
            return $default ?? $candidates[0];
        };

        // Inquiries (projects)
        $P_TBL  = 'projects';
        $P_DATE = "COALESCE(quotation_date, date_rec, created_at)";
        $P_VAL  = "COALESCE(quotation_value, price, 0)";
        $P_AREA = $detect($P_TBL, ['area','region','Region','region_name']);   // area/region column

        // Sales Orders (accepted)
        $S_TBL    = 'salesorderlog';
        $S_DATE   = $detect($S_TBL, ['date_rec','Date','date'], 'date_rec');
        $S_VAL    = $detect($S_TBL, ['value_with_vat','ValueWithVAT','value'], 'value_with_vat');
        $S_AREA   = $detect($S_TBL, ['region_name','region','Region','area']); // area/region column
        $S_STATUS = $detect($S_TBL, ['status','Status'], 'status');

        $areaFilter = $r->input('area');

        // Inquiries by month/area
        $qI = DB::table($P_TBL)
            ->selectRaw("DATE_FORMAT($P_DATE, '%Y-%m') AS ym, COALESCE($P_AREA,'—') AS area, SUM(".$cast($P_VAL).") AS total")
            ->whereBetween(DB::raw($P_DATE), [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym','area');
        if (!empty($areaFilter)) $qI->where($P_AREA, $areaFilter);
        $rowsI = $qI->get();

        // Sales (Accepted) by month/area
        $qS = DB::table($S_TBL)
            ->selectRaw("DATE_FORMAT($S_DATE, '%Y-%m') AS ym, COALESCE($S_AREA,'—') AS area, SUM(".$cast($S_VAL).") AS total")
            ->whereBetween(DB::raw($S_DATE), [$start->toDateString(), $end->toDateString()])
            ->whereRaw("LOWER($S_STATUS) = 'accepted'")
            ->groupBy('ym','area');
        if (!empty($areaFilter)) $qS->where($S_AREA, $areaFilter);
        $rowsS = $qS->get();

        // Regions seen (stable ordering)
        $regionsAll = collect([$rowsF->pluck('area'), $rowsI->pluck('area'), $rowsS->pluck('area')])
            ->flatten()->unique()->values();
        $regions = collect($regionsPref)->merge($regionsAll->diff($regionsPref))->values()->all();

        // Build index: idx[Metric][ym][area] = total
        $idx = ['Forecast'=>[], 'Inquiries'=>[], 'Sales'=>[]];
        foreach ($rowsF as $rF) $idx['Forecast'][$rF->ym][$rF->area]  = (float) $rF->total;
        foreach ($rowsI as $rI) $idx['Inquiries'][$rI->ym][$rI->area] = (float) $rI->total;
        foreach ($rowsS as $rS) $idx['Sales'][$rS->ym][$rS->area]     = (float) $rS->total;
        // Monthly × Region (stack) × Metric
        $metrics  = ['Forecast','Inquiries','Sales'];
        $mrSeries = [];
        foreach ($regions as $rg) {
            foreach ($metrics as $mName) {
                $data = [];
                foreach ($months as $ym) $data[] = (float) ($idx[$mName][$ym][$rg] ?? 0);
                $mrSeries[] = ['name' => "$rg – $mName", 'stack' => $rg, 'data' => $data];
            }
        }

        // Region summary totals (sum across months)
        $summarySeries = [];
        foreach ($metrics as $mName) {
            $summarySeries[] = [
                'name' => $mName,
                'data' => array_map(
                    fn($rg) => array_sum(array_map(fn($ym) => (float) ($idx[$mName][$ym][$rg] ?? 0), $months)),
                    $regions
                ),
            ];
        }

        // Simple monthly totals (summed across regions)
        $monthlyForecast  = [];
        $monthlyInquiries = [];
        $monthlySales     = [];
        foreach ($months as $ym) {
            $monthlyForecast[]  = array_sum($idx['Forecast'][$ym]  ?? []);
            $monthlyInquiries[] = array_sum($idx['Inquiries'][$ym] ?? []);
            $monthlySales[]     = array_sum($idx['Sales'][$ym]     ?? []);
        }

        // Legacy: expose forecast rows as "monthly"
        $monthly = $rowsF;

        return response()->json([
            'area'         => $byArea,
            'salesman'     => $salesmanAgg,
            'total_value'  => (float) $total,
            'region_scope' => $regionScope,

            // NEW comparison payloads
            'monthly_region_metrics' => [
                'categories' => $months,
                'series'     => $mrSeries,
            ],
            'region_summary' => [
                'categories' => $regions,
                'series'     => $summarySeries,
            ],
            'monthly_forecast'  => [ 'categories' => $months, 'series' => $monthlyForecast ],
            'monthly_inquiries' => [ 'categories' => $months, 'series' => $monthlyInquiries ],
            'monthly_sales'     => [ 'categories' => $months, 'series' => $monthlySales ],
            'monthly' => $monthly,
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
        // Region scope (RBAC)
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

        // Family (optional – very light heuristics)
        if ($r->filled('family') && strtolower((string)$r->query('family')) !== 'all') {
            $fam = strtolower((string)$r->query('family'));
            $qb->where(function ($q) use ($fam) {
                if ($fam === 'ductwork') {
                    $q->whereRaw('LOWER(products) LIKE ?', ['%duct%']);
                } elseif ($fam === 'dampers') {
                    $q->whereRaw('LOWER(products) LIKE ?', ['%damper%']);
                } elseif (in_array($fam, ['sound','sound_attenuators','attenuators','attenuator'])) {
                    $q->whereRaw('LOWER(products) LIKE ?', ['%attenuator%']);
                } elseif ($fam === 'accessories') {
                    $q->whereRaw('LOWER(products) LIKE ?', ['%accessor%']);
                } else {
                    $q->whereRaw('LOWER(products) LIKE ?', ['%'.$fam.'%']);
                }
            });
        }

        // Type filter (bidding|inhand|lost) if you use it in forecast
        if ($r->filled('type')) {
            $qb->whereRaw('LOWER(type)=?', [strtolower((string)$r->query('type'))]);
        }

        // Salesman filter
        if ($r->filled('salesman')) {
            $qb->where('salesman', 'like', '%'.$r->query('salesman').'%');
        }

        // Region enforcement (RBAC then UI)
        if (!empty($effectiveRegion)) {
            $qb->where('area', $effectiveRegion);
        } elseif ($r->filled('area')) {
            $qb->where('area', $r->query('area'));
        }

        // Put scope info back into the request (useful in UI)
        $r->attributes->set('region_scope', $effectiveRegion ?: 'ALL');

        return $qb;
    }
}
