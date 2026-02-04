<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\Reports\SalesmanSummaryInsightService;
use App\Services\Reports\SalesmanSummaryLlmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class SalesmanPerformanceController extends Controller
{
    /**
     * Canonical salesman name  =>  list of accepted aliases (uppercased).
     */
    private array $salesmanAliasMap = [
        'SOHAIB' => ['SOHAIB', 'SOAHIB'],
        'TARIQ' => ['TARIQ', 'TAREQ'],
        'JAMAL' => ['JAMAL'],
        'ABDO' => ['ABDO', 'ABDO YOUSEF'],
        'AHMED' => ['AHMED'],
        'ABU MERHI' => ['M.ABU MERHI', 'M.MERHI', 'MERHI', 'MOHAMMED', 'ABU MERHI', 'M. ABU MERHI'],
        'ATAI' => ['AHMED', 'CLIENT', 'EXPORT', 'WASEEM', 'FAISAL', 'MAEN'],
    ];

    /**
     * Normalize any salesman string into a canonical display label.
     * - Null/empty → "NOT MENTIONED"
     * - Match alias → canonical key (SOHAIB, TARIQ, ...)
     * - Else → uppercase trimmed original.
     */
//    private function normalizeSalesman($name): string
//    {
//        $n = strtoupper(trim((string)$name));
//        $n = preg_replace('/\s+/', ' ', $n);
//
//        // common cleanup
//        $n = str_replace(['.', ',', '-', '_'], ' ', $n);
//        $n = preg_replace('/\s+/', ' ', $n);
//
//        // ✅ HARD ALIAS FIXES
//        if (preg_match('/^AHMED(\s+AMIN)?$/', $n)) return 'AHMED';
//        if (preg_match('/^TA(RI|RE)Q$/', $n)) return 'TARIQ';
//        if (preg_match('/^SOHAIB$/', $n)) return 'SOHAIB';
//        if (preg_match('/^JAMAL$/', $n)) return 'JAMAL';
//        if (preg_match('/^ABDO$/', $n)) return 'ABDO';
//
//        // fallback (keep cleaned value)
//        return $n ?: 'NOT MENTIONED';
//    }


    private array $monthAliases = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec',
    ];


    private array $regionMap = [
        'Eastern' => ['SOHAIB'],
        'Central' => ['TARIQ', 'JAMAL'],
        'Western' => ['ABDO', 'AHMED'],
    ];

    private function salesmanToRegion(string $canonSalesman): string
    {
        foreach ($this->regionMap as $region => $salesmen) {
            if (in_array($canonSalesman, $salesmen, true)) return $region;
        }
        return 'Other';
    }

    private function areaPasses(string $canonSalesman, string $area): bool
    {
        $area = ucfirst(strtolower(trim((string)$area)));
        if ($area === '' || $area === 'All') return true;

        return $this->salesmanToRegion($canonSalesman) === $area;
    }

    private function monthlySums(string $dateCol, string $valExpr): string
    {
        $parts = [];
        foreach ($this->monthAliases as $m => $a) {
            $parts[] = "SUM(CASE WHEN MONTH($dateCol)=$m THEN $valExpr ELSE 0 END) AS $a";
        }
        $parts[] = "SUM($valExpr) AS total";
        return implode(',', $parts);
    }

    private function norm(string $expr): string
    {
        return "LOWER(TRIM($expr))";
    }

    private function poAmountExpr(string $alias = 's'): string
    {
        // Properly quote the column with a space: `s`.`PO Value`
        $col = "`$alias`.`PO Value`";

        return "CAST(
                NULLIF(
                    REPLACE(REPLACE($col, ',', ''), ' ', ''),
                    ''
                ) AS DECIMAL(18,2)
            )";
    }

    public function index(Request $r)
    {
        $year = (int)($r->query('year') ?? now()->year);

        return view('performance.salesman', ['year' => $year]);
    }

    private function relaxGroupBy(): void
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

    public function data(Request $r)
    {
        $kind = $r->query('kind', 'inq');
        $year = (int)$r->query('year', now()->year);
        $area = (string)$r->query('area', 'All');

        if ($kind === 'po') {
            // ---------- Sales Order Log ----------
            $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
            $amount = $this->poAmountExpr('s');
            $monthly = $this->monthlySums('s.date_rec', $amount);

            $baseQuery = DB::table('salesorderlog as s')
                ->selectRaw("$labelExpr AS salesman,$monthly")
                ->whereYear('s.date_rec', $year)
                ->whereNull('s.deleted_at')
                ->whereRaw('`s`.`PO. No.` IS NOT NULL')
                ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
                ->whereRaw($amount . ' > 0');

            // ✅ EXCLUDE rejected/cancelled using Status OAA
            $baseQuery = $this->applyPoStatusOaaAcceptedFilter($baseQuery, 's');

            $baseQuery = $baseQuery->groupByRaw($labelExpr);

            $sumQ = DB::table('salesorderlog as s')
                ->whereYear('s.date_rec', $year)
                ->whereNull('s.deleted_at')
                ->whereRaw('`s`.`PO. No.` IS NOT NULL')
                ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
                ->whereRaw($amount . ' > 0');

            // ✅ EXCLUDE rejected/cancelled using Status OAA
            $sumQ = $this->applyPoStatusOaaAcceptedFilter($sumQ, 's');

            $sum = (float)$sumQ
                ->selectRaw("SUM($amount) AS s")
                ->value('s');
        } else {
            // ---------- Projects / Inquiries ----------
            $labelExpr = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
            $val = 'COALESCE(p.quotation_value,0)';
            $monthly = $this->monthlySums('p.quotation_date', $val);

            $baseQuery = DB::table('projects as p')
                ->selectRaw("$labelExpr AS salesman,$monthly")
                ->whereYear('p.quotation_date', $year)
                ->groupByRaw($labelExpr);

            $sum = (float)DB::table('projects as p')
                ->whereYear('p.quotation_date', $year)
                ->selectRaw("SUM($val) AS s")
                ->value('s');
        }

        $this->relaxGroupBy();

        $rows = $baseQuery->get();
        $agg = [];
        $months = array_values($this->monthAliases);

        foreach ($rows as $row) {
            $alias = $this->normalizeSalesman($row->salesman);

            if (!$this->areaPasses($alias, $area)) {
                continue;
            }

            if (!isset($agg[$alias])) {
                $agg[$alias] = (object)array_merge(
                    ['salesman' => $alias],
                    array_fill_keys($months, 0.0),
                    ['total' => 0.0]
                );
            }

            foreach ($months as $m) {
                $agg[$alias]->$m += (float)$row->$m;
            }
            $agg[$alias]->total += (float)$row->total;
        }

        $sumFiltered = 0.0;
        foreach ($agg as $r2) {
            $sumFiltered += (float)$r2->total;
        }

        $collection = collect(array_values($agg));

        return DataTables::of($collection)
            ->editColumn('salesman', fn($row) =>
                '<span class="badge text-bg-secondary">' . e($row->salesman) . '</span>'
            )
            ->rawColumns(['salesman'])
            ->with(['sum_total' => $sumFiltered])
            ->make(true);
    }


    public function kpis(Request $r)
    {
        $year = (int)$r->query('year', now()->year);
        $area = (string)$r->query('area', 'All');

        $this->relaxGroupBy();

        // --- Inquiries ---
        $projLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $projNorm = $this->norm($projLabel);

        $inq = DB::table('projects as p')
            ->selectRaw("$projNorm AS norm,$projLabel AS label,SUM(COALESCE(p.quotation_value,0)) AS total")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$projNorm,$projLabel")
            ->get();

        // --- POs ---
        $poLabel = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $poNorm = $this->norm($poLabel);
        $amt = $this->poAmountExpr('s');

        $poQ = DB::table('salesorderlog as s')
            ->selectRaw("$poNorm AS norm,$poLabel AS label,SUM($amt) AS total")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amt . ' > 0');

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $poQ = $this->applyPoStatusOaaAcceptedFilter($poQ, 's');

        $po = $poQ->groupByRaw("$poNorm,$poLabel")->get();

        // --- Merge by alias ---
        $inqAgg = [];
        $poAgg = [];

        foreach ($inq as $row) {
            $label = $this->normalizeSalesman($row->label);
            if (!$this->areaPasses($label, $area)) continue;
            $inqAgg[$label] = ($inqAgg[$label] ?? 0) + (float)$row->total;
        }

        foreach ($po as $row) {
            $label = $this->normalizeSalesman($row->label);
            if (!$this->areaPasses($label, $area)) continue;
            $poAgg[$label] = ($poAgg[$label] ?? 0) + (float)$row->total;
        }

        $allLabels = array_keys($inqAgg + $poAgg);
        sort($allLabels, SORT_NATURAL);

        $categories = [];
        $inqSeries = [];
        $poSeries = [];

        foreach ($allLabels as $label) {
            $categories[] = $label;
            $inqSeries[] = $inqAgg[$label] ?? 0;
            $poSeries[] = $poAgg[$label] ?? 0;
        }

        return response()->json([
            'categories' => $categories,
            'inquiries' => $inqSeries,
            'pos' => $poSeries,
            'sum_inquiries' => array_sum($inqSeries),
            'sum_pos' => array_sum($poSeries),
        ]);
    }



    /* ============================================================
   NEW: Salesman -> Project Region -> Month pivot (POs)
   - Area filter applies to SALESMAN REGION (assigned regionMap)
   - But we still split the salesman's POs by project_region
============================================================ */

    private function normalizeProjectRegion(?string $val): string
    {
        $v = ucfirst(strtolower(trim((string)$val)));
        if (in_array($v, ['Eastern', 'Central', 'Western'], true)) return $v;
        return 'Other';
    }

    private function buildSalesmanRegionMatrixPOs(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        $salesLabel = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $projRegionCol = "COALESCE(NULLIF(`s`.`project_region`,''),'')";

        $amtExpr = $this->poAmountExpr('s');

        $q = DB::table('salesorderlog as s')
            ->selectRaw("$salesLabel AS salesman,
                MONTH(s.date_rec) AS m,
                $projRegionCol AS project_region,
                SUM($amtExpr) AS v")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0');

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $q = $this->applyPoStatusOaaAcceptedFilter($q, 's');

        $rows = $q->groupByRaw("$salesLabel, MONTH(s.date_rec), $projRegionCol")
            ->get();

        $regions = ['Eastern', 'Central', 'Western', 'Other'];
        $out = [];

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($s, $allowed, true)) continue;
            }

            $pr = $this->normalizeProjectRegion($r->project_region);

            if (!isset($out[$s])) {
                foreach ($regions as $rg) $out[$s][$rg] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $val = (float)$r->v;

            $out[$s][$pr][$idx] += $val;
            $out[$s][$pr][12] += $val;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }


    public function matrix(Request $r)
    {
        $year = (int)($r->query('year') ?? now()->year);
        $area = (string)($r->query('area') ?? 'All');
        $areaNorm = $this->normalizeArea($area);
        $this->relaxGroupBy();


        // Core pivots (filtered)
        $inquiriesBySalesman = $this->filterSalesmanPivotByArea(
            $this->buildSalesmanPivotInquiries($year),
            $areaNorm,
            $this->regionMap
        );

        $posBySalesman = $this->filterSalesmanPivotByArea(
            $this->buildSalesmanPivotPOs($year),
            $areaNorm,
            $this->regionMap
        );

        // Products
        $inqProductMatrix = $this->buildSalesmanProductMatrixInquiries($year, $areaNorm, $this->regionMap);
        $poProductMatrix = $this->buildSalesmanProductMatrixPOs($year, $areaNorm, $this->regionMap);

        // Forecast + Targets
        $forecastBySalesman = $this->buildSalesmanPivotForecast($year, $areaNorm, $this->regionMap);
        $targetBySalesman = $this->buildSalesmanPivotTargets($year, $areaNorm, $this->regionMap);

        // Performance Matrix
        $salesmanKpiMatrix = $this->buildSalesmanKpiMatrix(
            $forecastBySalesman,
            $targetBySalesman,
            $inquiriesBySalesman,
            $posBySalesman
        );
        $poRegionMatrix = $this->buildSalesmanRegionMatrixPOs($year, $areaNorm, $this->regionMap);
        // Estimators + totals
        $inquiriesByEstimator = $this->buildEstimatorPivotInquiries($year, $areaNorm, $this->regionMap);

        $totalInquiriesByMonth = $this->buildTotalInquiriesByMonth($inquiriesBySalesman);
        $totalInquiriesByProduct = $this->buildTotalInquiriesByProductFromSalesmanMatrix($inqProductMatrix);
        $estimatorProductMatrix = $this->buildEstimatorProductMatrixInquiries($year, $areaNorm, $this->regionMap);

        return response()->json([
            'year' => $year,
            'area' => $areaNorm,

            'inquiriesBySalesman' => $inquiriesBySalesman,
            'posBySalesman' => $posBySalesman,

            'salesmanKpiMatrix' => $salesmanKpiMatrix,
            'inqProductMatrix' => $inqProductMatrix,
            'poProductMatrix' => $poProductMatrix,
            'inquiriesByEstimator' => $inquiriesByEstimator,
            'totalInquiriesByMonth' => $totalInquiriesByMonth,
            'totalInquiriesByProduct' => $totalInquiriesByProduct,
            'estimatorProductMatrix' => $estimatorProductMatrix,
            'poRegionMatrix' => $poRegionMatrix,
        ]);
    }


    /**
     * Pick the “primary” salesman for charts when area filter is used.
     * Eastern -> SOHAIB
     * Central -> TARIQ (you can switch to JAMAL if you want)
     * Western -> ABDO (or AHMED)
     */
    private function primarySalesmanForArea(string $areaNorm): ?string
    {
        $areaNorm = $this->normalizeArea($areaNorm);

        return match ($areaNorm) {
            'Eastern' => 'SOHAIB',
            'Central' => 'TARIQ',
            'Western' => 'ABDO',
            default => null, // for "All" we’ll build an overall view (optional)
        };
    }

    /**
     * Safe 13-length row (Jan..Dec, Total)
     */
    private function pad13Row($row): array
    {
        $row = is_array($row) ? array_values($row) : [];
        $row = array_pad($row, 13, 0);
        return array_slice($row, 0, 13);
    }

    /**
     * ✅ Product Mix (PO VALUE) by Month — clustered bars (Jan..cutoff)
     * - Executive colors
     * - Rotated value labels WITH background pill (readable for GM)
     * - Labels only shown up to last non-zero month (within cutoff)
     * - Labels clamped inside plot area (never overlap title/legend)
     * Output: data:image/svg+xml;base64,...
     */
    private function makeProductMixByMonthClusteredBarChartBase64(
        array  $salesmanProducts,
        string $title = 'PO PRODUCT MIX — MONTHLY (SAR)',
        ?int   $year = null
    ): string
    {
        $year = $year ?? (int) now()->year;

        $productOrder = ['DUCTWORK', 'ACCESSORIES', 'SOUND ATTENUATORS', 'DAMPERS', 'LOUVERS'];

        $short = [
            'DUCTWORK'          => 'DUCT',
            'ACCESSORIES'       => 'ACCE',
            'SOUND ATTENUATORS' => 'SOUN',
            'DAMPERS'           => 'DAMP',
            'LOUVERS'           => 'LOUV',
        ];

        // ✅ YTD cutoff: current year => current month, else full year
        $cutoff = ($year === (int) now()->year) ? (int) now()->month : 12;
        $cutoff = max(1, min(12, $cutoff));
        $visibleMonths = $cutoff;

        // Normalize series (Jan..Dec)
        $series = [];
        foreach ($productOrder as $p) {
            $row = $salesmanProducts[$p] ?? null;

            if ($row === null) {
                foreach ($salesmanProducts as $k => $v) {
                    if (strtoupper(trim((string) $k)) === $p) {
                        $row = $v;
                        break;
                    }
                }
            }

            $row = $this->pad13Row($row ?? []);
            $series[$p] = array_map('floatval', array_slice($row, 0, 12));
        }

        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        // ✅ Only consider visible months for "last non-zero month"
        $lastNonZeroMonth = -1;
        for ($m = 0; $m < $visibleMonths; $m++) {
            $sum = 0.0;
            foreach ($productOrder as $p) $sum += ($series[$p][$m] ?? 0.0);
            if ($sum > 0) $lastNonZeroMonth = $m;
        }

        // ✅ Show labels only up to last non-zero month
        $labelMonthMax = ($lastNonZeroMonth >= 0) ? $lastNonZeroMonth : 0;
        $labelMonthMax = min($labelMonthMax, $visibleMonths - 1);

        // Scale (only visible months)
        $maxV = 1.0;
        foreach ($series as $vals) {
            $maxV = max($maxV, max(array_slice($vals, 0, $visibleMonths)));
        }

        $unit = 1000000.0; // Millions
        $maxVScaled = max(1.0, $maxV / $unit);
        $maxVScaled = (float) ceil($maxVScaled);

        // Layout
        $w = 760;
        $h = 260;

        $headerH = 36;
        $padL = 70;
        $padR = 16;
        $padT = $headerH + 12;
        $padB = 46;

        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $baseY = $padT + $plotH;

        $y = function (float $vScaled) use ($padT, $plotH, $maxVScaled): float {
            return $padT + ($plotH * (1 - ($vScaled / $maxVScaled)));
        };

        $colors = [
            'DUCTWORK'          => '#2563eb',
            'ACCESSORIES'       => '#16a34a',
            'SOUND ATTENUATORS' => '#f59e0b',
            'DAMPERS'           => '#ef4444',
            'LOUVERS'           => '#6b7280',
        ];

        $titleSvg = '<text x="'.$padL.'" y="20" font-size="13" font-weight="800" fill="#111827">'.htmlspecialchars($title, ENT_QUOTES).'</text>';

        // Legend right
        $legend = '';
        $legendGap = 74;
        $lx = $w - $padR - ($legendGap * count($productOrder));
        $ly = 10;
        foreach ($productOrder as $idx => $p) {
            $xL = $lx + ($idx * $legendGap);
            $legend .= '<rect x="'.$xL.'" y="'.$ly.'" width="12" height="12" fill="'.$colors[$p].'" />';
            $legend .= '<text x="'.($xL + 16).'" y="'.($ly + 11).'" font-size="9" fill="#374151">'.$short[$p].'</text>';
        }

        // Grid
        $grid = '';
        for ($g = 0; $g <= 4; $g++) {
            $val = $maxVScaled * (1 - ($g / 4));
            $yyG = $padT + ($plotH * ($g / 4));
            $grid .= '<line x1="'.$padL.'" y1="'.round($yyG,2).'" x2="'.($w - $padR).'" y2="'.round($yyG,2).'" stroke="#e5e7eb" stroke-width="1"/>';
            $grid .= '<text x="'.($padL - 8).'" y="'.round($yyG + 3,2).'" text-anchor="end" font-size="7" fill="#6b7280">'.number_format($val, 0).'M</text>';
        }

        $axes = '
      <line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.$baseY.'" stroke="#d1d5db" stroke-width="1"/>
      <line x1="'.$padL.'" y1="'.$baseY.'" x2="'.($w - $padR).'" y2="'.$baseY.'" stroke="#d1d5db" stroke-width="1"/>
    ';

        $fmt = function (float $val): string {
            if ($val <= 0) return '0';
            if ($val >= 1000000) return number_format($val / 1000000, 2) . 'M';
            if ($val >= 1000) return number_format($val / 1000, 0) . 'K';
            return (string) number_format($val, 0);
        };

        // ✅ Rotated label with background pill (for GM readability)
        $rotLabel = function (float $tx, float $ty, string $text, int $fontSize = 9): string {
            $charW = $fontSize * 0.58;
            $padX  = 4;
            $padY  = 2;

            $w2 = max(18, (strlen($text) * $charW) + ($padX * 2));
            $h2 = $fontSize + ($padY * 2);

            $x2 = $tx - ($w2 / 2);
            $y2 = $ty - ($h2 / 2);

            return '
          <g transform="rotate(-90 '.$tx.' '.$ty.')">
            <rect x="'.round($x2,2).'" y="'.round($y2,2).'"
                  width="'.round($w2,2).'" height="'.round($h2,2).'"
                  rx="3" ry="3"
                  fill="#ffffff" opacity="0.92"
                  stroke="#e5e7eb" stroke-width="1"/>
            <text x="'.$tx.'" y="'.$ty.'" text-anchor="middle"
                  font-size="'.$fontSize.'" font-weight="800" fill="#111827"
                  dominant-baseline="middle">'.$text.'</text>
          </g>
        ';
        };

        // Bars: only Jan..cutoff
        $nMonths = $visibleMonths;
        $nSeries = count($productOrder);

        $monthGap = 18; // separation between month groups
        $usableW = $plotW - ($monthGap * ($nMonths - 1));
        $groupW = $usableW / max(1, $nMonths);

        $barW = min(11, $groupW * 0.17);
        $gap  = max(1, $barW * 0.25);

        $totalBarsW = ($nSeries * $barW) + (($nSeries - 1) * $gap);

        // ✅ Clamp labels strictly inside plot area
        $minLabelY = $padT + 8;     // below plot top (never in header)
        $maxLabelY = $baseY - 10;   // above x-axis

        $bars = '';
        for ($m = 0; $m < $nMonths; $m++) {

            $groupLeft = $padL + ($m * ($groupW + $monthGap));
            $startX = $groupLeft + ($groupW / 2) - ($totalBarsW / 2);

            foreach ($productOrder as $sIdx => $p) {
                $val = (float) ($series[$p][$m] ?? 0.0);
                $vScaled = $val / $unit;

                $x = $startX + ($sIdx * ($barW + $gap));

                // bar
                if ($val <= 0) {
                    $bars .= '<rect x="'.round($x,2).'" y="'.round($baseY - 2,2).'" width="'.round($barW,2).'" height="2" fill="'.$colors[$p].'" opacity="0.35" />';
                } else {
                    $yy = $y($vScaled);
                    $hh = max(0, $baseY - $yy);
                    $bars .= '<rect x="'.round($x,2).'" y="'.round($yy,2).'" width="'.round($barW,2).'" height="'.round($hh,2).'" fill="'.$colors[$p].'" />';
                }

                // ✅ labels (only until last non-zero month)
                if ($m <= $labelMonthMax && $val > 0) {
                    $label = $fmt($val);

                    $yyTop  = $y($vScaled);
                    $labelY = $yyTop - 12; // above bar

                    // clamp inside plot
                    if ($labelY < $minLabelY) $labelY = $minLabelY;
                    if ($labelY > $maxLabelY) $labelY = $maxLabelY;

                    $tx = (float) round($x + ($barW / 2), 2);
                    $ty = (float) round($labelY, 2);

                    $bars .= $rotLabel($tx, $ty, $label, 9);
                }
            }

            // month label
            $cx = $groupLeft + ($groupW / 2);
            $bars .= '<text x="'.round($cx,2).'" y="'.($h - 18).'" text-anchor="middle" font-size="9" fill="#6b7280">'.$months[$m].'</text>';
        }

        $svg = '
<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">
  <rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#ffffff"/>
  '.$titleSvg.'
  '.$legend.'
  '.$grid.'
  '.$axes.'
  '.$bars.'
</svg>';

        return $this->svgToDataUri($svg);
    }



    /**
     * ✅ Monthly Clustered Bars: Forecast vs Target vs Inquiries vs POs
     * - Shows only months up to current month for current year (YTD)
     * - Rotated value labels WITH background (pill) for readability
     * - Zero values draw a tiny baseline bar (no "0" label)
     * - Labels are clamped to stay inside plot area (never overlap title/legend)
     * Output: data:image/svg+xml;base64,...
     */
    private function makeMonthly4SeriesClusteredBarChartBase64(
        array  $forecastRow,
        array  $targetRow,
        array  $inqRow,
        array  $poRow,
        string $title = 'MONTHLY PERFORMANCE — FORECAST vs TARGET vs INQUIRIES vs POs',
        ?int   $year = null
    ): string
    {
        $year = $year ?? (int) now()->year;

        $forecast12 = array_slice($this->pad13Row($forecastRow), 0, 12);
        $target12   = array_slice($this->pad13Row($targetRow),   0, 12);
        $inq12      = array_slice($this->pad13Row($inqRow),      0, 12);
        $po12       = array_slice($this->pad13Row($poRow),       0, 12);

        // ✅ YTD cutoff
        $cutoff = ($year === (int) now()->year) ? (int) now()->month : 12;
        $cutoff = max(1, min(12, $cutoff));

        $forecast = array_map('floatval', array_slice($forecast12, 0, $cutoff));
        $target   = array_map('floatval', array_slice($target12,   0, $cutoff));
        $inq      = array_map('floatval', array_slice($inq12,      0, $cutoff));
        $po       = array_map('floatval', array_slice($po12,       0, $cutoff));

        // ✅ Colors (Executive)
        $COL_PO       = '#1D4ED8'; // deep blue
        $COL_INQ      = '#0F766E'; // teal
        $COL_TARGET   = '#F59E0B'; // amber
        $COL_FORECAST = '#CBD5E1'; // light slate

        // Canvas
        $w = 760;
        $h = 260;

        $headerH = 34;
        $padL = 62;
        $padR = 16;
        $padT = $headerH + 12;
        $padB = 50;

        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $baseY = $padT + $plotH;

        $monthsAll = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $months = array_slice($monthsAll, 0, $cutoff);

        $n = $cutoff;
        $groupW = $plotW / max(1, $n);

        $barW = min(11, $groupW * 0.16);
        $gap  = max(2, $barW * 0.45);

        // Scale
        $maxV = 1.0;
        $maxV = max($maxV, max($forecast ?: [0]));
        $maxV = max($maxV, max($target   ?: [0]));
        $maxV = max($maxV, max($inq      ?: [0]));
        $maxV = max($maxV, max($po       ?: [0]));
        $maxV = (float) ceil($maxV / 1000000) * 1000000;
        if ($maxV <= 0) $maxV = 1.0;

        $y = function (float $v) use ($padT, $plotH, $maxV): float {
            return $padT + ($plotH * (1 - ($v / $maxV)));
        };

        $fmt = function (float $v): string {
            if ($v <= 0) return '0';
            if ($v >= 1000000) return number_format($v / 1000000, 2) . 'M';
            if ($v >= 1000) return number_format($v / 1000, 0) . 'K';
            return (string) number_format($v, 0);
        };

        // Grid + y labels
        $grid = '';
        for ($g = 0; $g <= 4; $g++) {
            $val = $maxV * (1 - ($g / 4));
            $yy  = $padT + ($plotH * ($g / 4));
            $grid .= '<line x1="'.$padL.'" y1="'.round($yy,2).'" x2="'.($w - $padR).'" y2="'.round($yy,2).'" stroke="#e5e7eb" stroke-width="1"/>';
            $grid .= '<text x="'.($padL - 8).'" y="'.round($yy + 3,2).'" text-anchor="end" font-size="9" fill="#6b7280">'
                . number_format($val / 1000000, 0) . 'M</text>';
        }

        // Title + Legend
        $titleSvg = '<text x="'.$padL.'" y="18" font-size="12" font-weight="800" fill="#111827">'.htmlspecialchars($title, ENT_QUOTES).'</text>';

        $legendX = $w - $padR - 390;
        $legend = '
      <rect x="'.($legendX + 0).'"   y="10" width="10" height="10" fill="'.$COL_PO.'" /><text x="'.($legendX + 16).'"  y="19" font-size="10" fill="#374151">POs</text>
      <rect x="'.($legendX + 70).'"  y="10" width="10" height="10" fill="'.$COL_INQ.'" /><text x="'.($legendX + 86).'" y="19" font-size="10" fill="#374151">Inquiries</text>
      <rect x="'.($legendX + 160).'" y="10" width="10" height="10" fill="'.$COL_TARGET.'" /><text x="'.($legendX + 176).'" y="19" font-size="10" fill="#374151">Target</text>
      <rect x="'.($legendX + 250).'" y="10" width="10" height="10" fill="'.$COL_FORECAST.'" stroke="#94A3B8" stroke-width="1" /><text x="'.($legendX + 266).'" y="19" font-size="10" fill="#374151">Forecast</text>
    ';

        $axes = '
      <line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.$baseY.'" stroke="#d1d5db" stroke-width="1"/>
      <line x1="'.$padL.'" y1="'.$baseY.'" x2="'.($w - $padR).'" y2="'.$baseY.'" stroke="#d1d5db" stroke-width="1"/>
    ';

        // ✅ Label helper (rotated with background)
        $rotLabel = function (float $tx, float $ty, string $text, int $fontSize = 9): string {
            // Rough text width estimate (good enough for 0.00M / 123K etc.)
            $charW = $fontSize * 0.58;
            $padX  = 4;
            $padY  = 2;

            $w = max(18, (strlen($text) * $charW) + ($padX * 2));
            $h = $fontSize + ($padY * 2);

            $x = $tx - ($w / 2);
            $y = $ty - ($h / 2);

            return '
          <g transform="rotate(-90 '.$tx.' '.$ty.')">
            <rect x="'.round($x,2).'" y="'.round($y,2).'"
                  width="'.round($w,2).'" height="'.round($h,2).'"
                  rx="3" ry="3"
                  fill="#ffffff" opacity="0.92"
                  stroke="#e5e7eb" stroke-width="1"/>
            <text x="'.$tx.'" y="'.$ty.'" text-anchor="middle"
                  font-size="'.$fontSize.'" font-weight="800" fill="#111827"
                  dominant-baseline="middle">'.$text.'</text>
          </g>
        ';
        };

        // ✅ Clamp labels strictly inside plot area (never in header)
        $minLabelY = $padT + 8;     // below plot top
        $maxLabelY = $baseY - 10;   // above x-axis

        // Draw one bar
        $drawBar = function (float $val, float $x, string $fill, string $stroke = '') use (
            $y, $baseY, $barW, $fmt, $rotLabel, $minLabelY, $maxLabelY
        ): string {
            // baseline bar for zero
            if ($val <= 0) {
                return '<rect x="'.round($x,2).'" y="'.round($baseY - 2,2).'" width="'.round($barW,2).'" height="2" fill="'.$fill.'" opacity="0.35" '
                    . ($stroke ? 'stroke="'.$stroke.'" stroke-width="1"' : '')
                    . ' />';
            }

            $yy = $y($val);
            $hh = max(0, $baseY - $yy);

            $tx = (float) round($x + ($barW / 2), 2);

            // place label just above bar, but clamp within plot
            $ty = (float) ($yy - 12);
            if ($ty < $minLabelY) $ty = $minLabelY;
            if ($ty > $maxLabelY) $ty = $maxLabelY;
            $ty = (float) round($ty, 2);

            $label = $fmt($val);

            return
                '<rect x="'.round($x,2).'" y="'.round($yy,2).'" width="'.round($barW,2).'" height="'.round($hh,2).'" fill="'.$fill.'" '
                . ($stroke ? 'stroke="'.$stroke.'" stroke-width="1"' : '')
                . ' />'
                // ✅ wrapped label background (rotated)
                . $rotLabel($tx, $ty, $label, 9);
        };

        // Bars
        $bars = '';
        for ($i = 0; $i < $n; $i++) {
            $cx = $padL + ($groupW * $i) + ($groupW / 2);

            $xF = $cx - (1.5 * ($barW + $gap));
            $xT = $cx - (0.5 * ($barW + $gap));
            $xI = $cx + (0.5 * ($barW + $gap));
            $xP = $cx + (1.5 * ($barW + $gap));

            $bars .= $drawBar((float) $forecast[$i], $xF, $COL_FORECAST, '#94A3B8');
            $bars .= $drawBar((float) $target[$i],   $xT, $COL_TARGET);
            $bars .= $drawBar((float) $inq[$i],      $xI, $COL_INQ);
            $bars .= $drawBar((float) $po[$i],       $xP, $COL_PO);

            $bars .= '<text x="'.round($cx,2).'" y="'.($h - 18).'" text-anchor="middle" font-size="9" fill="#6b7280">'.$months[$i].'</text>';
        }

        $svg = '
<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">
  <rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#ffffff"/>
  '.$titleSvg.'
  '.$legend.'
  '.$grid.'
  '.$axes.'
  '.$bars.'
</svg>';

        return $this->svgToDataUri($svg);
    }




    /**
     * Build a DOMPDF-safe chart as inline SVG (base64 data URI).
     * Returns: data:image/svg+xml;base64,....
     */
    private function svgToDataUri(string $svg): string
    {
        // Important: keep SVG UTF-8 and avoid line breaks issues
        $svg = trim($svg);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function salesmenForArea(string $areaNorm): array
    {
        $areaNorm = $this->normalizeArea($areaNorm);

        return match ($areaNorm) {
            'Eastern' => ['SOHAIB'],
            'Central' => ['TARIQ', 'JAMAL'],
            'Western' => ['ABDO', 'AHMED'],
            'All'     => [],   // ✅ No charts for ALL (GM requirement)
            default   => [],   // ✅ safe: unknown/invalid area => no charts
        };
    }

    private function buildSalesmanSummaryPayload(int $year, string $areaNorm): array
    {
        $today = Carbon::now()->format('d-m-Y');

        $this->relaxGroupBy();
        $canViewAll = auth()->user()?->hasRole('GM') || auth()->user()?->hasRole('Admin');
        $regionMap = [
            'Eastern' => ['SOHAIB'],
            'Central' => ['TARIQ', 'JAMAL'],
            'Western' => ['ABDO', 'AHMEDAMIN', 'AHMED AMIN', 'AHMED'],
        ];

        // ✅ targets
        $annualTargets = ['Eastern' => 50000000, 'Central' => 50000000, 'Western' => 36000000];
        $annualTarget = ($areaNorm !== 'All') ? (float)($annualTargets[$areaNorm] ?? 0) : 0.0;
        $monthlyTarget = ($areaNorm !== 'All' && $annualTarget > 0) ? ($annualTarget / 12.0) : 0.0;

        // ---------- pivots ----------
        $inquiriesBySalesman = $this->buildSalesmanPivotInquiries($year);
        $posBySalesman = $this->buildSalesmanPivotPOs($year);
        $poRegionMatrix = $this->buildSalesmanRegionMatrixPOs($year, $areaNorm, $regionMap);

        $inquiriesBySalesman = $this->filterSalesmanPivotByArea($inquiriesBySalesman, $areaNorm, $regionMap);
        $posBySalesman = $this->filterSalesmanPivotByArea($posBySalesman, $areaNorm, $regionMap);

        // ---------- region pivots ----------
        $inqByRegion = $this->buildRegionPivotFromSalesmanPivot($inquiriesBySalesman, $regionMap);
        $poByRegion = $this->buildRegionPivotFromSalesmanPivot($posBySalesman, $regionMap);

        if ($areaNorm !== 'All') {
            $inqByRegion = array_intersect_key($inqByRegion, [$areaNorm => true]);
            $poByRegion = array_intersect_key($poByRegion, [$areaNorm => true]);
        }

        // ✅ safer totals (index 12 is TOTAL in your pivots)
        $inqTotal = array_sum(array_map(fn($row) => (float)($row[12] ?? 0), $inquiriesBySalesman));
        $poTotal = array_sum(array_map(fn($row) => (float)($row[12] ?? 0), $posBySalesman));

        $gapVal = $inqTotal - $poTotal;
        $gapPct = ($inqTotal > 0) ? round(($poTotal / $inqTotal) * 100, 1) : 0;

        // ---------- matrices ----------
        $inqProductMatrix = $this->buildSalesmanProductMatrixInquiries($year, $areaNorm, $regionMap);
        $poProductMatrix = $this->buildSalesmanProductMatrixPOs($year, $areaNorm, $regionMap);
        $forecastBySalesman = $this->buildSalesmanPivotForecast($year, $areaNorm, $regionMap);
        $targetBySalesman = $this->buildSalesmanPivotTargets($year, $areaNorm, $regionMap);

        $salesmanKpiMatrix = $this->buildSalesmanKpiMatrix(
            $forecastBySalesman,
            $targetBySalesman,
            $inquiriesBySalesman,
            $posBySalesman
        );

        $inquiriesByEstimator = $this->buildEstimatorPivotInquiries($year, $areaNorm, $regionMap);
        $totalInquiriesByMonth = $this->buildTotalInquiriesByMonth($inquiriesBySalesman);
        $totalInquiriesByProduct = $this->buildTotalInquiriesByProductFromSalesmanMatrix($inqProductMatrix);
        $estimatorProductMatrix = $this->buildEstimatorProductMatrixInquiries($year, $areaNorm, $regionMap);
        $totalInquiriesByMonthByType = $this->buildTotalInquiriesByMonthByType($year, $areaNorm, $regionMap);

        // ---------- charts ----------
        $salesmen = $this->salesmenForArea($areaNorm);
        $focusSalesman = ($areaNorm !== 'All') ? implode(', ', $salesmen) : null;

        $charts = [];

        foreach ($salesmen as $s) {

            // product mix chart (PO product monthly)
            if (isset($poProductMatrix[$s])) {
                $charts[$s]['product_mix'] = $this->makeProductMixByMonthClusteredBarChartBase64(
                    $poProductMatrix[$s],
                    'PRODUCT MIX — ' . $s . ' (MONTHLY)',
                    $year
                );
            } else {
                $charts[$s]['product_mix'] = null;
            }

            // monthly performance 4-series chart
            if (isset($salesmanKpiMatrix[$s])) {
                $m = $salesmanKpiMatrix[$s];

                $charts[$s]['monthly_perf'] = $this->makeMonthly4SeriesClusteredBarChartBase64(
                    $m['FORECAST'] ?? [],
                    $m['TARGET'] ?? [],
                    $m['INQUIRIES'] ?? [],
                    $m['POS'] ?? [],
                    'MONTHLY PERFORMANCE — ' . $s,
                    $year
                );
            } else {
                $charts[$s]['monthly_perf'] = null;
            }
        }
        // ✅ conversion model (your “10% logic”)
        $expectedConvPct = 10.0;
        $expectedConv = max(0.01, $expectedConvPct / 100.0);
        $requiredQuotes = ($monthlyTarget > 0) ? ($monthlyTarget / $expectedConv) : 0.0;
        $actualConvPct = ($inqTotal > 0) ? round(($poTotal / $inqTotal) * 100, 1) : 0.0;
        $quoteGap = $requiredQuotes - $inqTotal;

        return [
            'year' => $year,
            'area' => $areaNorm,
            'today' => $today,
            'focus_salesman' => $focusSalesman,

            // ✅ expose targets to PDF + insights
            'targets' => [
                'annual_target' => $annualTarget,
                'monthly_target' => $monthlyTarget,
            ],

            // ✅ expose conversion expectation block
            'conversion_model' => [
                'expected_conversion_pct' => $expectedConvPct,
                'target_sales_monthly' => $monthlyTarget,
                'required_quotations' => $requiredQuotes,
                'actual_quotations' => $inqTotal,
                'quotation_gap' => $quoteGap,
                'actual_conversion_pct' => $actualConvPct,
            ],

            'kpis' => [
                'inquiries_total' => $inqTotal,
                'pos_total' => $poTotal,
                'gap_value' => $gapVal,
                'gap_percent' => $gapPct,
            ],

            'inquiriesBySalesman' => $inquiriesBySalesman,
            'posBySalesman' => $posBySalesman,
            'inqByRegion' => $inqByRegion,
            'poByRegion' => $poByRegion,

            'inqProductMatrix' => $inqProductMatrix,
            'poProductMatrix' => $poProductMatrix,
            'salesmanKpiMatrix' => $salesmanKpiMatrix,
            'poRegionMatrix' => $poRegionMatrix,

            'inquiriesByEstimator' => $inquiriesByEstimator,
            'totalInquiriesByMonth' => $totalInquiriesByMonth,
            'totalInquiriesByProduct' => $totalInquiriesByProduct,
            'estimatorProductMatrix' => $estimatorProductMatrix,
            'totalInquiriesByMonthByType' => $totalInquiriesByMonthByType,

            'charts' => $charts,
            'salesmen' => $salesmen,
            'insights' => null,
            'gm_controls' => $this->buildGmControls($year, $areaNorm),
        ];
    }


    public function insights(Request $r)
    {
        $year = (int)($r->input('year') ?? now()->year);
        $area = (string)($r->input('area') ?? 'All');
        $areaNorm = $this->normalizeArea($area);

        // robust ai parsing (ai=1/true/on/yes/10)
        $aiRaw = $r->input('ai', 0);
        $aiEnabled = filter_var($aiRaw, FILTER_VALIDATE_BOOLEAN)
            || (is_numeric($aiRaw) && (int)$aiRaw > 0);

        // ✅ Build the SAME payload you build inside pdf()
        $payload = $this->buildSalesmanSummaryPayload($year, $areaNorm);
        // ^ You should extract your big "payload building" logic into this helper
        // so you don't duplicate code.

        // RULES always
        $ruleService = app(SalesmanSummaryInsightService::class);
        $baseInsights = $ruleService->generate($payload);
        $baseInsights = $this->ensureInsightsSkeletonNew($baseInsights);
        $ins = $this->addLowConfidenceFillers($baseInsights);

        $ins['meta'] ??= [];
        $ins['meta']['mode'] = 'RULES';
        $ins['meta']['engine'] = 'rules';
        $ins['meta']['area'] = $areaNorm;
        $ins['meta']['year'] = $year;
        $ins['meta']['ai'] = $aiEnabled ? 1 : 0;
        $ins['meta']['generated_at'] = now()->toDateTimeString();

        // AI merge (optional)
        if ($aiEnabled) {
            try {
                $llmService = app(\App\Services\Reports\SalesmanSummaryLlmService::class);
                $aiFacts = $this->buildAiFactsFromPayload($payload);
                $aiMerged = $llmService->enhance($aiFacts, $ins);

                $aiMerged = $this->ensureInsightsSkeletonNew($aiMerged);
                $aiMerged = $this->addLowConfidenceFillers($aiMerged);

                $aiMerged['meta'] ??= [];
                $aiMerged['meta']['engine'] = 'rules+ai';
                $aiMerged['meta']['ai_ran'] = 1;
                $aiMerged['meta']['ai'] = 1;
                $aiMerged['meta']['mode'] = 'LIVE';

                $ins = $aiMerged;
            } catch (\Throwable $e) {
                $ins['meta']['ai_ran'] = 0;
                $ins['meta']['ai_error'] = $e->getMessage();
            }
        }

        // ✅ store insights for PDF using token
        $token = (string)Str::uuid();
        $cacheKey = "pdf:insights:salesmanSummary:token:$token";

        Cache::put(
            "pdf:insights:salesmanSummary:token:$token",
            [
                'insights' => $ins,       // store only insights
                'year' => $year,
                'area' => $areaNorm,
                'created_at' => now()->toDateTimeString(),
            ],
            now()->addMinutes(30)
        );


        return response()->json(['ok' => true, 'token' => $token]);
    }



    /**
     * ✅ PDF Export: Salesman Summary
     * - One PDF
     * - Filters by area (Eastern/Central/Western/All)
     * - Includes region summary, product matrices, performance matrix, estimators, totals
     * - ✅ Adds charts (DOMPDF) as Base64 images (monthly trend + region share)
     */
    /* ===========================
   1) CONTROLLER (pdf method)
   Copy-paste this WHOLE method
   =========================== */


    public function pdf(Request $r)
    {
        $year = (int)($r->query('year') ?? now()->year);
        $area = (string)($r->query('area') ?? 'All');
        $areaNorm = $this->normalizeArea($area);

        $token = (string)$r->query('token', '');
        $debug = (int)$r->query('debug', 0) === 1;

        // ✅ single source of truth
        $payload = $this->buildSalesmanSummaryPayload($year, $areaNorm);

        // ✅ fallback rules
        $ruleService = app(SalesmanSummaryInsightService::class);
        $rules = $ruleService->generate($payload);
        $rules = $this->ensureInsightsSkeletonNew($rules);
        $rules = $this->addLowConfidenceFillers($rules);

        $rules['meta'] ??= [];
        $rules['meta']['engine'] = 'rules';
        $rules['meta']['mode'] = 'RULES';
        $rules['meta']['area'] = $areaNorm;
        $rules['meta']['year'] = $year;
        $rules['meta']['ai_ran'] = 0;

        $ins = $rules;

        if ($token) {
            $cached = Cache::get("pdf:insights:salesmanSummary:token:$token");
            if ($cached && isset($cached['insights'])) {
                $ins = $cached['insights'];
                $ins = $this->ensureInsightsSkeletonNew($ins);
                $ins = $this->addLowConfidenceFillers($ins);
                $ins['meta'] ??= [];
                $ins['meta']['ai_ran'] = (($ins['meta']['engine'] ?? '') === 'rules+ai') ? 1 : 0;
            } else {
                $ins = $rules;
                $ins['meta']['note'] = 'AI token not found/expired; using rules.';
            }
        }

        $payload['insights'] = $ins;

        if ($debug) {
            return response()->json([
                'ok' => true,
                'token' => $token,
                'meta' => $ins['meta'] ?? [],
                'engine' => $ins['meta']['engine'] ?? null,
                'conversion_model' => $payload['conversion_model'] ?? null,
            ]);
        }

        return Pdf::loadView('reports.salesman-summary', $payload)
            ->setPaper('a4', 'landscape')
            ->download("ATAI_Salesman_Summary_{$year}_{$areaNorm}.pdf");
    }


    private function ensureInsightsSkeletonNew(array $ins): array
    {
        $ins['overall_analysis'] ??= [
            'snapshot' => [],
            'regional_key_points' => [],
            'salesman_key_points' => [],
            'product_key_points' => [],
        ];

        $ins['high_insights'] ??= [];
        $ins['low_insights'] ??= [];
        $ins['what_needs_attention'] ??= [];
        $ins['one_line_summary'] ??= '';

        $ins['meta'] ??= [];
        $ins['meta']['engine'] ??= 'rules';
        $ins['meta']['generated_at'] ??= now()->toDateTimeString();

        return $ins;
    }


    private function addLowConfidenceFillers(array $ins): array
    {
        $ins = $this->ensureInsightsSkeletonNew($ins);

        $mkHigh = function ($title, $text, $gmAction = 'Review and validate with the team.', $rag = 'Amber', $confidence = 'Low') {
            return [
                'title' => $title,
                'rag' => $rag,
                'confidence' => $confidence,
                'text' => $text,
                'gm_action' => $gmAction,
            ];
        };

        $mkLow = function ($title, $text, $gmInterp = 'Treat as directional until more data accumulates.', $rag = 'Amber', $confidence = 'Low') {
            return [
                'title' => $title,
                'rag' => $rag,
                'confidence' => $confidence,
                'text' => $text,
                'gm_interpretation' => $gmInterp,
            ];
        };

        $mkAttn = function ($title, $text, $rag = 'Red', $confidence = 'Low') {
            return [
                'title' => $title,
                'rag' => $rag,
                'confidence' => $confidence,
                'text' => $text,
            ];
        };

        // --- Overall analysis fillers ---
        if (empty($ins['overall_analysis']['snapshot'])) {
            $ins['overall_analysis']['snapshot'] = [
                'Limited data available for this scope; treat insights as directional until more months/records accumulate.',
            ];
        }
        if (empty($ins['overall_analysis']['regional_key_points'])) {
            $ins['overall_analysis']['regional_key_points'] = [
                'Regional comparisons require stronger month coverage; validate region mapping and ensure all quotations/POs are logged.',
            ];
        }
        if (empty($ins['overall_analysis']['salesman_key_points'])) {
            $ins['overall_analysis']['salesman_key_points'] = [
                'Salesman-level performance signals are limited; confirm top quotations and follow-up status updates are up to date.',
            ];
        }
        if (empty($ins['overall_analysis']['product_key_points'])) {
            $ins['overall_analysis']['product_key_points'] = [
                'Product mix insights need consistent product tagging; verify family/category completeness in inquiry and PO logs.',
            ];
        }

        // --- High / Low / Attention fillers ---
        if (empty($ins['high_insights'])) {
            $ins['high_insights'] = [
                $mkHigh(
                    'Baseline performance signal (limited)',
                    'Current dataset is not strong enough for high-confidence wins; continue weekly logging to strengthen signals.',
                    'Focus on logging discipline and follow up on top-value open quotations.'
                )
            ];
        }

        if (empty($ins['low_insights'])) {
            $ins['low_insights'] = [
                $mkLow(
                    'Directional trend only',
                    'Month coverage is limited; trends may change as more data comes in.',
                    'Do not treat trend direction as final until we have 3+ active months.'
                )
            ];
        }

        if (empty($ins['what_needs_attention'])) {
            $ins['what_needs_attention'] = [
                $mkAttn(
                    'Data completeness / logging discipline',
                    'Ensure all quotations, PO values, product family, and dates are logged consistently to avoid hidden gaps.',
                    'Red',
                    'Low'
                )
            ];
        }

        if (empty($ins['one_line_summary'])) {
            $ins['one_line_summary'] =
                'Insights are directional due to limited coverage—focus on logging discipline and follow-up on top open quotations.';
        }

        $ins['meta'] ??= [];
        $ins['meta']['engine'] = $ins['meta']['engine'] ?? 'rules';

        return $ins;
    }


    private function buildAiFactsFromPayload(array $payload): array
    {
        $year = (int)($payload['year'] ?? now()->year);
        $area = (string)($payload['area'] ?? 'All');

        $kpis = $payload['kpis'] ?? [];
        $inqTotal = (float)($kpis['inquiries_total'] ?? 0);
        $poTotal = (float)($kpis['pos_total'] ?? 0);

        $inqBySalesman = $payload['inquiriesBySalesman'] ?? [];
        $poBySalesman = $payload['posBySalesman'] ?? [];
        $forecast = $payload['salesmanKpiMatrix'] ?? []; // has FORECAST/TARGET/INQUIRIES/POS/CONV

        $salesmen = array_unique(array_merge(
            array_keys($inqBySalesman),
            array_keys($poBySalesman),
            array_keys($forecast)
        ));
        sort($salesmen, SORT_NATURAL);

        $salesmanFacts = [];
        foreach ($salesmen as $s) {
            $inq = (float)($inqBySalesman[$s][12] ?? 0);
            $po = (float)($poBySalesman[$s][12] ?? 0);

            $conv = 0.0;
            $fc = 0.0;
            $tgt = 0.0;

            if (isset($forecast[$s])) {
                $conv = (float)($forecast[$s]['CONV_PCT'][12] ?? 0);
                $fc = (float)($forecast[$s]['FORECAST'][12] ?? 0);
                $tgt = (float)($forecast[$s]['TARGET'][12] ?? 0);
            }

            $salesmanFacts[] = [
                'salesman' => $s,
                'inquiries_total' => $inq,
                'pos_total' => $po,
                'conversion_pct' => $conv,
                'forecast_total' => $fc,
                'target_total' => $tgt,
            ];
        }

        // simple confidence flag: active months (any non-zero in total row)
        $activeMonths = 0;
        $totalInqRow = $payload['totalInquiriesByMonth'] ?? array_fill(0, 13, 0);
        for ($i = 0; $i < 12; $i++) {
            if (((float)($totalInqRow[$i] ?? 0)) > 0) $activeMonths++;
        }
        $confidence = ($activeMonths >= 3) ? 'Medium/High' : 'Low';

        return [
            'scope' => [
                'year' => $year,
                'area' => $area,
                'focus_salesman' => $payload['focus_salesman'] ?? null,
            ],
            'kpis' => [
                'inquiries_total' => $inqTotal,
                'pos_total' => $poTotal,
                'gap_value' => (float)($kpis['gap_value'] ?? 0),
                'gap_percent' => (float)($kpis['gap_percent'] ?? 0),
            ],
            'salesmen' => $salesmanFacts,
            'meta' => [
                'active_months_inquiries' => $activeMonths,
                'confidence' => $confidence,
                'note' => 'Facts-only payload for AI wording. No monthly arrays/matrices included.',
            ],
        ];
    }

    /* Gm controls for portal */


    private function buildGmControls(int $year, string $areaNorm): array
    {
        $areaNorm = strtoupper(trim($areaNorm));
        $today = Carbon::now();

        /* ============================================================
         | A) Week range (KSA workweek: SUN → THU)
         | We always check LAST COMPLETED week ending on THURSDAY
         ============================================================ */
        $thisWeekEnd = $today->copy()->endOfWeek(Carbon::THURSDAY);   // Thu of current KSA week
        // If today is before/at Thu, last completed is previous Thu
        if ($today->lte($thisWeekEnd)) {
            $weekEnd = $thisWeekEnd->copy()->subWeek();               // last Thu
        } else {
            $weekEnd = $thisWeekEnd->copy();                          // (rare case) after Thu
        }
        $weekStart = $weekEnd->copy()->subDays(4);                    // Sun (Thu-4)

        /* ============================================================
         | B) Required salesmen by region
         ============================================================ */
        $required = match ($areaNorm) {
            'EASTERN' => ['SOHAIB'],
            'CENTRAL' => ['TARIQ', 'JAMAL'],
            'WESTERN' => ['ABDO', 'AHMED'],
            default => ['SOHAIB', 'TARIQ', 'JAMAL', 'ABDO', 'AHMED'],
        };

        /* ============================================================
    | 1) WEEKLY REPORT SUBMITTED (CREATED/SAVED)
    | We treat: draft/submitted/approved as "done"
    ============================================================ */
        $today = now(); // optionally ->timezone('Asia/Riyadh')

// Current week Sun–Thu
        $curStart = $today->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $curEnd   = $curStart->copy()->addDays(4);

// Last completed week Sun–Thu
        $prevStart = $curStart->copy()->subWeek();
        $prevEnd   = $prevStart->copy()->addDays(4);

        $validStatuses = ['draft','submitted','approved'];

        $submitted = DB::table('weekly_reports')
            ->selectRaw("UPPER(TRIM(engineer_name)) AS salesman")
            ->whereIn(DB::raw("LOWER(TRIM(status))"), $validStatuses)
            ->where(function ($q) use ($prevStart, $prevEnd, $curStart, $curEnd) {
                $q->where(function ($q2) use ($prevStart, $prevEnd) {
                    $q2->whereDate('week_start', $prevStart->toDateString())
                        ->whereDate('week_end',   $prevEnd->toDateString());
                })->orWhere(function ($q2) use ($curStart, $curEnd) {
                    $q2->whereDate('week_start', $curStart->toDateString())
                        ->whereDate('week_end',   $curEnd->toDateString());
                });
            })
            ->pluck('salesman')
            ->toArray();

        $submittedSet = array_fill_keys($submitted, true);

        $missing = [];
        foreach ($required as $s) {
            $sU = strtoupper(trim($s));
            if (!isset($submittedSet[$sU])) $missing[] = $sU;
        }

        $weekly_report = [
            'title'  => 'Weekly report submitted',
            'ok'     => empty($missing),
            'status' => empty($missing) ? 'YES' : 'NO',
            'detail' => empty($missing)
                ? "Saved for week {$prevStart->format('d-M')} → {$prevEnd->format('d-M-Y')} ."
                : "Missing ({$prevStart->format('d-M')} → {$prevEnd->format('d-M-Y')} : " . implode(', ', $missing),
        ];



        /* ============================================================
         | 2) QUOTATION → PO NOT UPDATED (14+ days, >= 500k, no PO)
         | Your real columns:
         | - projects: quotation_no, quotation_date, quotation_value, area
         | - salesorderlog: Quote No. (column has dot + space)
         | NOTE: backticks are REQUIRED for `Quote No.`
         ============================================================ */
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();
        $cut = $today->copy()->subDays(14)->toDateString();

        $stale = DB::table('projects as p')
            ->selectRaw("
            COUNT(*) as cnt,
            COALESCE(SUM(p.quotation_value),0) as quoted_sum
        ")
            ->whereBetween('p.quotation_date', [$from, $to])
            ->whereNotNull('p.quotation_no')
            ->whereRaw("TRIM(p.quotation_no) <> ''")
            ->whereRaw("p.quotation_value >= 500000")
            ->whereRaw("p.quotation_date <= ?", [$cut])
            ->whereNull('p.deleted_at')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('salesorderlog as s')
                    ->whereNull('s.deleted_at')
                    // match projects.quotation_no with salesorderlog.`Quote No.`
                    ->whereRaw("UPPER(TRIM(`s`.`Quote No.`)) = UPPER(TRIM(p.quotation_no))");
            })
            ->when($areaNorm !== 'ALL', function ($q) use ($areaNorm) {
                // projects table uses "area" in your screenshot, not "region"
                $q->whereRaw("UPPER(TRIM(p.area)) = ?", [$areaNorm]);
            })
            ->first();

        $cnt = (int)($stale->cnt ?? 0);
        $sum = (float)($stale->quoted_sum ?? 0);

        $quotation_po = [
            'title' => 'Quotation → PO not updated',
            'ok' => ($cnt === 0),
            'status' => ($cnt === 0) ? 'YES' : 'NO',
            'detail' => ($cnt === 0)
                ? "No pending high-value quotations older than 14 days."
                : "{$cnt} quotation(s) ≥ 500k SAR are 14+ days old with no PO (Total: SAR " . number_format($sum, 0) . ").",
        ];

        /* ============================================================
         | 3) BNC update → leads generation (NO upload-check)
         | GM wants: did the REGION’s BNC leads get UPDATED/WORKED ON
         | (comment/update) recently?
         | We'll use bnc_projects.updated_at (and optionally last_comment)
         ============================================================ */
        $bncCut = $today->copy()->subDays(7);

        $bncStats = DB::table('bnc_projects as b')
            ->selectRaw("
            MAX(b.updated_at) as last_updated,
            SUM(CASE WHEN b.updated_at >= ? THEN 1 ELSE 0 END) as updated_7d,
            COUNT(*) as total_leads
        ", [$bncCut->toDateTimeString()])
            ->when($areaNorm !== 'ALL', function ($q) use ($areaNorm) {
                $q->whereRaw("UPPER(TRIM(b.region)) = ?", [$areaNorm]);
            })
            ->first();

        $lastUpd = $bncStats->last_updated ? Carbon::parse($bncStats->last_updated) : null;
        $updated7d = (int)($bncStats->updated_7d ?? 0);
        $totalLeads = (int)($bncStats->total_leads ?? 0);

        $bncOk = ($updated7d > 0);

        $bnc_update = [
            'title' => 'BNC update → leads generation',
            'ok' => $bncOk,
            'status' => $bncOk ? 'YES' : 'NO',
            'detail' => $totalLeads === 0
                ? "No BNC leads found for this region."
                : ($bncOk
                    ? "{$updated7d} lead(s) updated in last 7 days. Last activity: " . ($lastUpd ? $lastUpd->format('d-M-Y') : '—')
                    : "No lead activity in last 7 days. Last activity: " . ($lastUpd ? $lastUpd->format('d-M-Y') : '—')),
        ];

        return [
            'quotation_po' => $quotation_po,
            'weekly_report' => $weekly_report,
            'bnc_update' => $bnc_update,
            'meta' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],
        ];
    }


    /* ============================================================
       Helpers: normalize area + salesman + PO amount
    ============================================================ */

    private function normalizeArea(string $area): string
    {
        $a = ucfirst(strtolower(trim($area)));
        return in_array($a, ['Eastern', 'Central', 'Western'], true) ? $a : 'All';
    }

    /**
     * Your canonical normalization (keep same logic you already have in project).
     * Adjust aliases as needed.
     */
    private function normalizeSalesman(?string $name): string
    {
        $n = strtoupper(trim((string)$name));
        if ($n === '' || $n === 'NOT MENTIONED') return 'NOT MENTIONED';

        // alias normalization examples:
        if (str_contains($n, 'SOHAIB') || str_contains($n, 'SOAHIB')) return 'SOHAIB';
        if (str_contains($n, 'TARIQ') || str_contains($n, 'TAREQ')) return 'TARIQ';
        if (str_contains($n, 'JAMAL')) return 'JAMAL';
        if (str_contains($n, 'ABDO')) return 'ABDO';
        if (str_contains($n, 'AHMED')) return 'AHMED';

        return $n;
    }


    /* ============================================================
       Core pivots: salesman inquiries / POs
    ============================================================ */

    private function buildSalesmanPivotInquiries(int $year): array
    {
        $labelExpr = "COALESCE(NULLIF(p.salesman,''), NULLIF(p.salesperson,''), 'Not Mentioned')";
        $valExpr = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$labelExpr AS salesman, MONTH(p.quotation_date) AS m, SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$labelExpr, MONTH(p.quotation_date)")
            ->get();

        return $this->pivotNormalizeSalesmanRows($rows);
    }

    private function buildSalesmanPivotPOs(int $year): array
    {
        $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''), 'Not Mentioned')";
        $amtExpr = $this->poAmountExpr('s');

        $q = DB::table('salesorderlog as s')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, SUM($amtExpr) AS s")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0');

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $q = $this->applyPoStatusOaaAcceptedFilter($q, 's');

        $rows = $q->groupByRaw("$labelExpr, MONTH(s.date_rec)")
            ->get();

        return $this->pivotNormalizeSalesmanRows($rows);
    }

    private function pivotNormalizeSalesmanRows($rows): array
    {
        $months = array_values($this->monthAliases); // jan..december
        $out = [];

        foreach ($rows as $r) {
            $canon = $this->normalizeSalesman($r->salesman);

            if (!isset($out[$canon])) {
                $out[$canon] = array_fill_keys($months, 0.0);
                $out[$canon]['total'] = 0.0;
            }

            $m = (int)$r->m;
            if ($m >= 1 && $m <= 12) {
                $key = $this->monthAliases[$m];
                $out[$canon][$key] += (float)$r->s;
            }
        }

        // compute totals and convert to numeric array [jan..dec,total]
        $final = [];
        ksort($out, SORT_NATURAL);

        foreach ($out as $salesman => $arr) {
            $total = 0.0;
            foreach ($months as $k) $total += (float)$arr[$k];

            $final[$salesman] = [];
            foreach ($months as $k) $final[$salesman][] = (float)$arr[$k];
            $final[$salesman][] = (float)$total;
        }

        return $final;
    }

    private function filterSalesmanPivotByArea(array $pivot, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);
        if ($areaNorm === 'All') return $pivot;

        $allowed = $regionMap[$areaNorm] ?? [];
        if (!$allowed) return [];

        return array_intersect_key($pivot, array_flip($allowed));
    }

    private function buildRegionPivotFromSalesmanPivot(array $pivot, array $regionMap): array
    {
        $out = [];
        foreach ($regionMap as $region => $salesmen) {
            $row = array_fill(0, 13, 0.0);
            foreach ($salesmen as $s) {
                if (!isset($pivot[$s])) continue;
                foreach ($pivot[$s] as $i => $val) $row[$i] += (float)$val;
            }
            $out[$region] = $row;
        }
        return $out;
    }

    /* ============================================================
       Product matrices (Salesman -> Product Family -> [13])
    ============================================================ */

    private function normalizeProductFamily(string $txt): ?string
    {
        $t = strtolower(trim($txt));
        if ($t === '') return null;

        $t = preg_replace('/[\.\,\/\-\_]+/', ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        // ductwork
        if (preg_match('/\bducts?\b|\bductwork\b|\bgi\b|\bgalv\b|\bgalvan(i|iz)ed\b|\bpre\s*insulated\b|\bpre-?insulated\b|\bpir\b|\bphenolic\b|\bpanel\b|\bspiral\b|\bround\b|\bflat\s*oval\b|\boval\b|\bul\b|\bfire\s*rated\b|\bflamebar\b|\bstainless\b|\bss\b|\b304\b|\b316l\b|\balumin(um|ium)\b|\bblack\s*steel\b|\bbs\b|\bvoid\s*former\b|\bpost\s*tension\b/i', $t)) {
            return 'DUCTWORK';
        }

        // ✅ dampers (plural-safe)
        if (preg_match('/\bdamper(s)?\b|\bfd\b|\bf\s*&\s*s\b|\bfire\s*damper(s)?\b|\bsmoke\b/', $t)) {
            return 'DAMPERS';
        }

        // ✅ louvers (plural-safe)
        if (preg_match('/\blouver(s)?\b|\bacoustic\s*louver(s)?\b|\bsand\s*trap\b/', $t)) {
            return 'LOUVERS';
        }

        // ✅ sound attenuators (plural-safe)
        if (preg_match('/\battenuator(s)?\b|\bsound\b|\bcrosstalk\b|\bcross\s*talk\b/', $t)) {
            return 'SOUND ATTENUATORS';
        }

        // accessories
        if (
            preg_match('/\baccessor(y|ies)\b|\bflex\b|\bplenum\b|\bairstack\b|\bheater(s)?\b|\bduct\s*heater(s)?\b/', $t)
            || preg_match('/\bvav\b|\bcav\b|\bbtu(s)?\b|\bvav\s*box(es)?\b/', $t)
        ) {
            return 'ACCESSORIES';
        }

        return null;
    }


    private function buildConversionModel(float $target, float $actualInquiries, float $actualPO, float $expectedConvPct = 10.0): array
    {
        $expectedConv = max(0.01, $expectedConvPct / 100.0);

        $requiredQuotes = $target / $expectedConv; // e.g., 4.17M / 0.10 = 41.7M
        $actualConvPct = ($actualInquiries > 0) ? round(($actualPO / $actualInquiries) * 100, 1) : 0.0;

        $gapQuotes = $requiredQuotes - $actualInquiries;

        return [
            'expected_conversion_pct' => $expectedConvPct,
            'actual_conversion_pct' => $actualConvPct,
            'target_sales' => $target,
            'required_quotations' => $requiredQuotes,
            'actual_quotations' => $actualInquiries,
            'quotation_gap' => $gapQuotes,
        ];
    }

    private function buildSalesmanProductMatrixInquiries(int $year, string $area, array $regionMap): array
    {
        // ✅ add PRE-INSULATED & SPIRAL as separate buckets (for Abdo/Ahmed only in UI)
        $families = ['DUCTWORK', 'PRE-INSULATED', 'SPIRAL', 'DAMPERS', 'LOUVERS', 'SOUND ATTENUATORS', 'ACCESSORIES'];

        $labelExpr = "COALESCE(NULLIF(p.salesman,''), NULLIF(p.salesperson,''), 'Not Mentioned')";
        $prodCol   = "COALESCE(NULLIF(p.atai_products,''),'')";
        $valExpr   = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$labelExpr AS salesman, MONTH(p.quotation_date) AS m, $prodCol AS prod, SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$labelExpr, MONTH(p.quotation_date), $prodCol")
            ->get();

        $out = [];
        $areaNorm = $this->normalizeArea($area);

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($s, $allowed, true)) continue;
            }

            $prodRaw = strtoupper(trim((string)$r->prod));

            // ✅ detect ductwork subtype for matrix buckets
            $bucket = null;

            if ($prodRaw === '') {
                $bucket = null;
            } elseif (str_contains($prodRaw, 'PRE') && str_contains($prodRaw, 'INSUL')) {
                $bucket = 'PRE-INSULATED';
            } elseif (str_contains($prodRaw, 'SPIRAL')) {
                $bucket = 'SPIRAL';
            } else {
                // fallback to your family normalizer
                $bucket = $this->normalizeProductFamily((string)$r->prod); // returns DUCTWORK/DAMPERS/...
            }

            if (!$bucket) continue;

            // only buckets we support
            if (!in_array($bucket, $families, true)) continue;

            if (!isset($out[$s])) {
                foreach ($families as $f) $out[$s][$f] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;

            $out[$s][$bucket][$idx] += (float)$r->s;
            $out[$s][$bucket][12]   += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }






    private function buildSalesmanProductMatrixPOs(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        $westernSalesmen = ['ABDO','AHMED'];

        $westernFamilies = [
            'DUCTWORK',
            'PRE-INSULATED DUCTWORK',
            'SPIRAL DUCTWORK',
            'DAMPERS',
            'LOUVERS',
            'SOUND ATTENUATORS',
            'ACCESSORIES',
        ];

        $defaultFamilies = [
            'DUCTWORK',
            'DAMPERS',
            'LOUVERS',
            'SOUND ATTENUATORS',
            'ACCESSORIES',
        ];

        $labelExpr = "COALESCE(NULLIF(`s`.`Sales Source`,''),'Not Mentioned')";
        $amtExpr   = $this->poAmountExpr('s');

        $allowedForArea = null;
        if ($areaNorm !== 'All') {
            $allowedForArea = $regionMap[$areaNorm] ?? [];
        }

        $isWesternSalesman = function (string $salesman) use ($westernSalesmen): bool {
            return in_array($salesman, $westernSalesmen, true);
        };

        $ductSubtypeKind = function (?string $subtype): string {
            $x = strtoupper(trim((string)$subtype));
            if ($x === '') return 'BASE';
            if (str_contains($x, 'PRE') || str_contains($x, 'INSUL')) return 'PRE';
            if (str_contains($x, 'SPIRAL')) return 'SPIRAL';
            return 'BASE';
        };

        $out = [];
        $init = function (string $salesman, bool $useWesternRows) use (&$out, $westernFamilies, $defaultFamilies) {
            if (isset($out[$salesman])) return;

            $families = $useWesternRows ? $westernFamilies : $defaultFamilies;
            foreach ($families as $f) {
                $out[$salesman][$f] = array_fill(0, 13, 0.0);
            }
        };

        // ============================================================
        // A) FAMILY TOTALS (FULL PO VALUE) for rows that HAVE breakdown
        // ============================================================
        $qA = DB::table('salesorderlog as s')
            ->join('salesorderlog_product_breakdowns as b', 'b.salesorderlog_id', '=', 's.id')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, b.family AS family, SUM($amtExpr) AS sum_po")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0');

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $qA = $this->applyPoStatusOaaAcceptedFilter($qA, 's');

        $breakdownFamilyTotals = $qA
            ->groupByRaw("$labelExpr, MONTH(s.date_rec), b.family")
            ->get();

        // ============================================================
        // B) SUBTYPE AMOUNTS (ONLY for Western ductwork children)
        // ============================================================
        $qB = DB::table('salesorderlog as s')
            ->join('salesorderlog_product_breakdowns as b', 'b.salesorderlog_id', '=', 's.id')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, b.family AS family, b.subtype AS subtype, SUM(b.amount) AS sum_amt")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw('b.amount > 0');

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $qB = $this->applyPoStatusOaaAcceptedFilter($qB, 's');

        $breakdownSubtypeAmounts = $qB
            ->groupByRaw("$labelExpr, MONTH(s.date_rec), b.family, b.subtype")
            ->get();

        // ============================================================
        // C) FALLBACK TOTALS (NO breakdown exists) -> classify by s.Products
        // ============================================================
        $qC = DB::table('salesorderlog as s')
            ->selectRaw("$labelExpr AS salesman, MONTH(s.date_rec) AS m, COALESCE(NULLIF(`s`.`Products`,''),'') AS prod, SUM($amtExpr) AS sum_po")
            ->whereYear('s.date_rec', $year)
            ->whereNull('s.deleted_at')
            ->whereRaw('`s`.`PO. No.` IS NOT NULL')
            ->whereRaw('TRIM(`s`.`PO. No.`) <> ""')
            ->whereRaw($amtExpr . ' > 0')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('salesorderlog_product_breakdowns as b')
                    ->whereColumn('b.salesorderlog_id', 's.id');
            });

        // ✅ EXCLUDE rejected/cancelled using Status OAA
        $qC = $this->applyPoStatusOaaAcceptedFilter($qC, 's');

        $fallbackFamilyTotals = $qC
            ->groupByRaw("$labelExpr, MONTH(s.date_rec), COALESCE(NULLIF(`s`.`Products`,''),'')")
            ->get();

        $totalDuctPO = [];
        $preAmt      = [];
        $spiralAmt   = [];

        $addTo13 = function (&$arr, string $salesman, int $idx, float $val) {
            if (!isset($arr[$salesman])) $arr[$salesman] = array_fill(0, 13, 0.0);
            $arr[$salesman][$idx] += $val;
            $arr[$salesman][12]   += $val;
        };

        foreach ($breakdownFamilyTotals as $r) {
            $salesman = $this->normalizeSalesman($r->salesman);

            if ($allowedForArea !== null && !in_array($salesman, $allowedForArea, true)) continue;

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;
            $idx = $m - 1;

            $famRaw = strtoupper(trim((string)$r->family));
            $fam    = $this->normalizeProductFamily($famRaw);
            if (!$fam) continue;

            $useWesternRowsForThisSalesman = ($areaNorm === 'Western') || ($areaNorm === 'All' && $isWesternSalesman($salesman));
            $init($salesman, $useWesternRowsForThisSalesman);

            $val = (float)$r->sum_po;

            if ($fam === 'DUCTWORK' && $useWesternRowsForThisSalesman) {
                $addTo13($totalDuctPO, $salesman, $idx, $val);
                continue;
            }

            if (!isset($out[$salesman][$fam])) continue;
            $out[$salesman][$fam][$idx] += $val;
            $out[$salesman][$fam][12]   += $val;
        }

        foreach ($fallbackFamilyTotals as $r) {
            $salesman = $this->normalizeSalesman($r->salesman);

            if ($allowedForArea !== null && !in_array($salesman, $allowedForArea, true)) continue;

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;
            $idx = $m - 1;

            $fam = $this->normalizeProductFamily((string)$r->prod);
            if (!$fam) continue;

            $useWesternRowsForThisSalesman = ($areaNorm === 'Western') || ($areaNorm === 'All' && $isWesternSalesman($salesman));
            $init($salesman, $useWesternRowsForThisSalesman);

            $val = (float)$r->sum_po;

            if ($fam === 'DUCTWORK' && $useWesternRowsForThisSalesman) {
                $addTo13($totalDuctPO, $salesman, $idx, $val);
                continue;
            }

            if (!isset($out[$salesman][$fam])) continue;
            $out[$salesman][$fam][$idx] += $val;
            $out[$salesman][$fam][12]   += $val;
        }

        foreach ($breakdownSubtypeAmounts as $r) {
            $salesman = $this->normalizeSalesman($r->salesman);

            if ($allowedForArea !== null && !in_array($salesman, $allowedForArea, true)) continue;

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;
            $idx = $m - 1;

            $famRaw = strtoupper(trim((string)$r->family));
            $fam    = $this->normalizeProductFamily($famRaw);
            if ($fam !== 'DUCTWORK') continue;

            $useWesternRowsForThisSalesman = ($areaNorm === 'Western') || ($areaNorm === 'All' && $isWesternSalesman($salesman));
            if (!$useWesternRowsForThisSalesman) continue;

            $init($salesman, true);

            $kind = $ductSubtypeKind($r->subtype);
            $val  = (float)$r->sum_amt;

            if ($kind === 'PRE') {
                $addTo13($preAmt, $salesman, $idx, $val);
            } elseif ($kind === 'SPIRAL') {
                $addTo13($spiralAmt, $salesman, $idx, $val);
            }
        }

        foreach ($out as $salesman => $_rows) {
            $useWesternRowsForThisSalesman = ($areaNorm === 'Western') || ($areaNorm === 'All' && $isWesternSalesman($salesman));
            if (!$useWesternRowsForThisSalesman) continue;

            for ($idx = 0; $idx < 12; $idx++) {
                $tot = (float)($totalDuctPO[$salesman][$idx] ?? 0.0);
                $pre = (float)($preAmt[$salesman][$idx] ?? 0.0);
                $spi = (float)($spiralAmt[$salesman][$idx] ?? 0.0);

                $base = $tot - $pre - $spi;
                if ($base < 0) $base = 0.0;

                $out[$salesman]['DUCTWORK'][$idx] = $base;
                $out[$salesman]['PRE-INSULATED DUCTWORK'][$idx] = $pre;
                $out[$salesman]['SPIRAL DUCTWORK'][$idx] = $spi;
            }

            $totT = (float)($totalDuctPO[$salesman][12] ?? 0.0);
            $preT = (float)($preAmt[$salesman][12] ?? 0.0);
            $spiT = (float)($spiralAmt[$salesman][12] ?? 0.0);

            $baseT = $totT - $preT - $spiT;
            if ($baseT < 0) $baseT = 0.0;

            $out[$salesman]['DUCTWORK'][12] = $baseT;
            $out[$salesman]['PRE-INSULATED DUCTWORK'][12] = $preT;
            $out[$salesman]['SPIRAL DUCTWORK'][12] = $spiT;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }







    /* ============================================================
       Forecast pivot (from forecast table)
       Columns you showed: value_sar, salesman, region, month_no/year
    ============================================================ */
    private function buildEstimatorProductMatrixInquiries(int $year, string $area, array $regionMap): array
    {
        $families = ['DUCTWORK', 'DAMPERS', 'LOUVERS', 'SOUND ATTENUATORS', 'ACCESSORIES'];
        $areaNorm = $this->normalizeArea($area);

        $estLabel = "COALESCE(NULLIF(p.estimator_name,''),'Not Mentioned')";
        $salesLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $prodCol = "COALESCE(NULLIF(p.atai_products,''),'')";
        $valExpr = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$estLabel AS estimator,
                    $salesLabel AS salesman,
                    MONTH(p.quotation_date) AS m,
                    $prodCol AS prod,
                    SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$estLabel, $salesLabel, MONTH(p.quotation_date), $prodCol")
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $canonSalesman = $this->normalizeSalesman($r->salesman);

            // ✅ area filter (same as your other pivots)
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canonSalesman, $allowed, true)) continue;
            }

            $est = strtoupper(trim((string)$r->estimator));
            if ($est === '') $est = 'NOT MENTIONED';

            $fam = $this->normalizeProductFamily((string)$r->prod);
            if (!$fam) continue;

            if (!isset($out[$est])) {
                // init all families so PDF always prints consistent 5 rows
                foreach ($families as $f) $out[$est][$f] = array_fill(0, 13, 0.0);
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$est][$fam][$idx] += (float)$r->s;
            $out[$est][$fam][12] += (float)$r->s; // total
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    private function buildSalesmanPivotForecast(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        $rows = DB::table('forecast as f')
            ->selectRaw("COALESCE(NULLIF(f.salesman,''), NULLIF(f.sales_source,''), 'Not Mentioned') AS salesman,
                        COALESCE(NULLIF(f.month_no,0), MONTH(f.created_at)) AS m,
                        SUM(COALESCE(f.value_sar,0)) AS s,
                        COALESCE(NULLIF(f.region,''),'') AS region")
            ->where('f.year', $year)
            ->groupByRaw("COALESCE(NULLIF(f.salesman,''), NULLIF(f.sales_source,''), 'Not Mentioned'),
                         COALESCE(NULLIF(f.month_no,0), MONTH(f.created_at)),
                         COALESCE(NULLIF(f.region,''),'')")
            ->get();

        // pivot to [SALESMAN => [13]]
        $months = array_values($this->monthAliases);
        $out = [];

        foreach ($rows as $r) {
            $s = $this->normalizeSalesman($r->salesman);

            // Area filter: prefer forecast.region if present, else fallback to salesman mapping
            if ($areaNorm !== 'All') {
                $rgn = ucfirst(strtolower(trim((string)$r->region)));
                if ($rgn !== '' && $rgn !== $areaNorm) {
                    // region on forecast says different area
                    continue;
                }
                // if region blank, use salesman mapping
                if ($rgn === '') {
                    $allowed = $regionMap[$areaNorm] ?? [];
                    if (!in_array($s, $allowed, true)) continue;
                }
            }

            if (!isset($out[$s])) $out[$s] = array_fill(0, 13, 0.0);

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$s][$idx] += (float)$r->s;
            $out[$s][12] += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    /* ============================================================
       Targets + Estimators (placeholders so PDF never breaks)
       We'll implement properly after PDF is approved.
    ============================================================ */

    private function buildSalesmanPivotTargets(int $year, string $area, array $regionMap): array
    {
        $areaNorm = $this->normalizeArea($area);

        // yearly targets (same as Blade)
        $yearlyTargets = [
            'Eastern' => 50000000,
            'Central' => 50000000,
            'Western' => 36000000,
        ];

        // salesman weights inside region
        // change weights if needed
        $weights = [
            'Eastern' => ['SOHAIB' => 1.0],
            'Central' => ['TARIQ' => 1.0, 'JAMAL' => 1.0],
            'Western' => ['ABDO' =>1.0, 'AHMED' => 1.0],
        ];

        $out = [];

        foreach ($weights as $region => $salesmenWeights) {
            if ($areaNorm !== 'All' && $areaNorm !== $region) continue;

            $regionTarget = (float)($yearlyTargets[$region] ?? 0);
            foreach ($salesmenWeights as $salesman => $w) {
                $annual = $regionTarget * (float)$w;
                $monthly = $annual / 12;

                $row = array_fill(0, 13, 0.0);
                for ($i = 0; $i < 12; $i++) $row[$i] = $monthly;
                $row[12] = $annual;

                $out[$salesman] = $row;
            }
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }
    /**
     * Exclude rejected/cancelled POs.
     * Priority:
     * 1) If rejected_at exists -> exclude rejected_at NOT NULL
     * 2) Else, use OAA status column (Sales OAA / Status OAA) if present
     * 3) Else, do nothing (fail-safe)
     */
    private function applyPoStatusOaaAcceptedFilter($query, string $alias = 's')
    {
        // ✅ Best signal: rejected_at
        if (Schema::hasColumn('salesorderlog', 'rejected_at')) {
            $query->whereNull("$alias.rejected_at");
        }

        // ✅ OAA column name differs across databases (Sales OAA vs Status OAA)
        $oaaColName = null;

        if (Schema::hasColumn('salesorderlog', 'Sales OAA')) {
            $oaaColName = 'Sales OAA';
        } elseif (Schema::hasColumn('salesorderlog', 'Status OAA')) {
            $oaaColName = 'Status OAA';
        }

        // If neither exists, don't filter by OAA (avoid SQL 500)
        if (!$oaaColName) {
            return $query;
        }

        // Column has space -> must be backticked
        $col = "COALESCE(NULLIF(TRIM(`$alias`.`$oaaColName`),''),'')";

        // Allow blank, exclude reject/cancel keywords
        return $query->whereRaw("
        (
            $col = ''
            OR (
                LOWER($col) NOT LIKE '%reject%'
                AND LOWER($col) NOT LIKE '%rejected%'
                AND LOWER($col) NOT LIKE '%cancel%'
                AND LOWER($col) NOT LIKE '%cancell%'
            )
        )
    ");
    }


    private function buildEstimatorPivotInquiries(int $year, string $areaNorm, array $regionMap): array
    {
        // Normalize area like the rest of your controller
        $areaNorm = $this->normalizeArea($areaNorm);

        $estLabel = "COALESCE(NULLIF(p.estimator_name,''),'Not Mentioned')";
        $salesLabel = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $valExpr = "COALESCE(p.quotation_value,0)";

        $rows = DB::table('projects as p')
            ->selectRaw("$estLabel AS estimator,
                    $salesLabel AS salesman,
                    MONTH(p.quotation_date) AS m,
                    SUM($valExpr) AS s")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("$estLabel, $salesLabel, MONTH(p.quotation_date)")
            ->get();

        $months = array_values($this->monthAliases); // jan..december
        $out = [];

        foreach ($rows as $r) {
            $canonSalesman = $this->normalizeSalesman($r->salesman);

            // ✅ Apply area filter (same logic as rest of report)
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canonSalesman, $allowed, true)) continue;
            }

            $est = strtoupper(trim((string)$r->estimator));
            if ($est === '') $est = 'NOT MENTIONED';

            if (!isset($out[$est])) {
                $out[$est] = array_fill(0, 13, 0.0); // 12 months + total
            }

            $m = (int)$r->m;
            if ($m < 1 || $m > 12) continue;

            $idx = $m - 1;
            $out[$est][$idx] += (float)$r->s;
            $out[$est][12] += (float)$r->s;
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }


    /* ============================================================
       KPI Matrix builder (Forecast/Target/Inquiries/PO/Conversion)
    ============================================================ */

    private function buildSalesmanKpiMatrix(array $forecast, array $targets, array $inq, array $po): array
    {
        // union of salesmen keys
        $keys = array_unique(array_merge(
            array_keys($forecast),
            array_keys($targets),
            array_keys($inq),
            array_keys($po)
        ));

        $out = [];
        foreach ($keys as $s) {
            $f = $forecast[$s] ?? array_fill(0, 13, 0.0);
            $t = $targets[$s] ?? array_fill(0, 13, 0.0);
            $i = $inq[$s] ?? array_fill(0, 13, 0.0);
            $p = $po[$s] ?? array_fill(0, 13, 0.0);

            // conversion: month-wise and total (total uses overall totals)
            $conv = array_fill(0, 13, 0.0);
            for ($m = 0; $m < 12; $m++) {
                $conv[$m] = ($i[$m] > 0) ? round(($p[$m] / $i[$m]) * 100, 1) : 0.0;
            }
            $conv[12] = ($i[12] > 0) ? round(($p[12] / $i[12]) * 100, 1) : 0.0;

            $out[$s] = [
                'FORECAST' => $f,
                'TARGET' => $t,
                'INQUIRIES' => $i,
                'POS' => $p,
                'CONV_PCT' => $conv,
            ];
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

    /* ============================================================
       Totals (overall inquiries by month + by product)
    ============================================================ */

    private function buildTotalInquiriesByMonth(array $inquiriesBySalesman): array
    {
        $row = array_fill(0, 13, 0.0);
        foreach ($inquiriesBySalesman as $salesman => $arr) {
            for ($i = 0; $i < 13; $i++) $row[$i] += (float)$arr[$i];
        }
        return $row;
    }

    private function buildTotalInquiriesByProductFromSalesmanMatrix(array $inqProductMatrix): array
    {
        // Output required by Blade: [PRODUCT => [13]]
        $out = [];
        foreach ($inqProductMatrix as $salesman => $products) {
            foreach ($products as $product => $arr) {
                if (!isset($out[$product])) $out[$product] = array_fill(0, 13, 0.0);
                for ($i = 0; $i < 13; $i++) $out[$product][$i] += (float)$arr[$i];
            }
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    /**
     * Build month-wise totals grouped by project_type.
     * Returns: [ 'BIDDING' => [13], 'INHAND' => [13], ... ]
     */
    private function buildTotalInquiriesByMonthByType(int $year, string $areaNorm, array $regionMap): array
    {
        $typeExpr = "
        CASE
            WHEN UPPER(TRIM(project_type)) IN ('INHAND','IN-HAND','IN HAND') THEN 'INHAND'
            WHEN UPPER(TRIM(project_type)) IN ('BIDDING') THEN 'BIDDING'
            WHEN UPPER(TRIM(project_type)) IN ('LOST') THEN 'LOST'
            ELSE 'OTHER'
        END
    ";

        $labelExpr = "COALESCE(NULLIF(p.salesman,''),NULLIF(p.salesperson,''),'Not Mentioned')";
        $valExpr = "COALESCE(p.quotation_value,0)";

        $q = DB::table('projects as p')
            ->selectRaw("$typeExpr AS ptype, MONTH(p.quotation_date) AS m, SUM($valExpr) AS v, $labelExpr AS salesman")
            ->whereYear('p.quotation_date', $year)
            ->groupByRaw("ptype, m, $labelExpr");

        $rows = $q->get();

        $out = [];
        foreach ($rows as $r) {
            $canon = $this->normalizeSalesman($r->salesman);

            // ✅ Apply area filter using your same region rules
            if ($areaNorm !== 'All') {
                $allowed = $regionMap[$areaNorm] ?? [];
                if (!in_array($canon, $allowed, true)) continue;
            }

            $t = strtoupper($r->ptype ?? 'OTHER');
            if (!isset($out[$t])) $out[$t] = array_fill(0, 13, 0.0);

            $idx = ((int)$r->m) - 1;
            if ($idx >= 0 && $idx < 12) {
                $out[$t][$idx] += (float)$r->v;
            }
        }

        // total column
        foreach ($out as $t => $arr) {
            $out[$t][12] = array_sum(array_slice($arr, 0, 12));
        }

        ksort($out, SORT_NATURAL);
        return $out;
    }

}
