<?php

namespace App\Http\Controllers;

use App\Exports\Coordinator\SalesOrdersMonthExport;
use App\Exports\Coordinator\SalesOrdersYearExport;
use App\Models\Project;
use App\Models\SalesOrderAttachment;
use App\Models\SalesOrderLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectCoordinatorController extends Controller
{
    /* ============================================================
     | SCOPES (RBAC)
     | ============================================================
     |
     | Goal:
     | - Keep current working behavior unchanged.
     | - Enforce RBAC consistently in:
     |   index / show / delete / export / attachments / viewAttachment
     | - Fix common issue:
     |   grouped sales order query misses spaced columns
     |   (Payment Terms / Sales OAA / Remarks) => modal shows blank.
     |
     */

    /**
     * Allowed salesman aliases for the logged-in user.
     * Empty array = NO restriction (GM/Admin/Eastern coordinator).
     */
    private function coordinatorSalesmenScope($user): array
    {
        // GM/Admin (or permission) => all
        if (
            (method_exists($user, 'can') && $user->can('viewAllRegions')) ||
            (method_exists($user, 'hasRole') && $user->hasRole('gm|admin'))
        ) {
            return [];
        }

        // Eastern coordinator: ALL salesmen (no restriction)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_eastern')) {
            // Eastern coordinator can see Eastern + Western + Central salesmen
            return array_values(array_unique(array_merge(
                $this->salesmenForRegionSelection('eastern'),
                $this->salesmenForRegionSelection('western'),
                $this->salesmenForRegionSelection('central')
            )));
        }

        // Western coordinator: ONLY ABDO + AHMED (with aliases)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western')) {
            return [
                'ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF', 'ABDO YOUSIF',
                'AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'AHMED AMIN ', 'Ahmed Amin',
            ];
        }

        // Central coordinator: TAREQ + JAMAL + ABU MERHI (with aliases)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_central')) {
            return [
                'TAREQ', 'TARIQ', 'TAREQ ',
                'JAMAL',
                'M.ABU MERHI', 'M. ABU MERHI', 'M.MERHI', 'MERHI',
                'ABU MERHI', 'M ABU MERHI', 'MOHAMMED',
            ];
        }

        return [];
    }

    /**
     * Allowed region keys for logged-in user.
     * NOTE: As per your rule, project coordinators can see ALL regions.
     */
    private function coordinatorRegionScope($user): array
    {
        // GM/Admin or permission => all
        if (
            (method_exists($user, 'can') && $user->can('viewAllRegions')) ||
            (method_exists($user, 'hasRole') && $user->hasRole('gm|admin'))
        ) {
            return ['eastern', 'central', 'western'];
        }

        // ✅ Coordinators: ALL REGIONS (per your rule)
        if (
            method_exists($user, 'hasRole') &&
            $user->hasRole('project_coordinator_western|project_coordinator_eastern|project_coordinator_central')
        ) {
            return ['eastern', 'central', 'western'];
        }

        // Fallback: use user.region if present
        $r = strtolower((string)($user->region ?? ''));
        if (in_array($r, ['eastern', 'central', 'western'], true)) {
            return [$r];
        }

        return [];
    }

    /* ============================================================
     | HELPERS (Filters + Aliases)
     | ============================================================ */

    /**
     * UI filter: sales-team region dropdown (eastern/central/western/all)
     */
    private function selectedSalesTeamRegion(Request $request): string
    {
        $r = strtolower(trim((string)$request->input('region', 'all')));
        return in_array($r, ['eastern', 'central', 'western', 'all'], true) ? $r : 'all';
    }

    /**
     * File-safe region slug for export names.
     */
    private function regionSlugFromKey(?string $regionKey): string
    {
        $key = strtolower(trim((string)$regionKey));
        if (in_array($key, ['eastern', 'central', 'western'], true)) {
            return $key . '_region';
        }
        return 'all_regions';
    }

    /**
     * Canonical salesman => aliases list.
     * Used for normalization + region selection filtering.
     */
    private function salesmanAliasMap(): array
    {
        return [
            // Eastern: ALL related names map to SOHAIB
            'SOHAIB'    => [
                'SOHAIB', 'SOAHIB', 'SOAIB', 'SOHIB', 'SOHAI',
                'RAVINDER', 'WASEEM', 'FAISAL', 'CLIENT', 'EXPORT',
            ],
            'TAREQ'     => ['TARIQ', 'TAREQ', 'TAREQ '],
            'JAMAL'     => ['JAMAL'],
            'ABU_MERHI' => ['M.ABU MERHI', 'M. ABU MERHI', 'M.MERHI', 'MERHI', 'ABU MERHI', 'M ABU MERHI', 'MOHAMMED'],
            'ABDO'      => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF', 'ABDO YOUSIF'],
            'AHMED'     => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'AHMED AMIN ', 'AHMED AMIN.', 'AHMED AMEEN.', 'Ahmed Amin'],
        ];
    }

    /**
     * Canonical salesman code from any alias (UPPER).
     */
    private function canonicalSalesmanCode(?string $raw): string
    {
        $v = strtoupper(trim((string)$raw));
        if ($v === '') return '';

        foreach ($this->salesmanAliasMap() as $canon => $aliases) {
            foreach ($aliases as $a) {
                if ($v === strtoupper(trim((string)$a))) {
                    return $canon;
                }
            }
        }

        return $v;
    }

    /**
     * Resolve region (Eastern/Central/Western) from salesman or area text.
     */
    private function resolveRegionForRow(?string $salesmanRaw, ?string $areaRaw): ?string
    {
        $canon = $this->canonicalSalesmanCode($salesmanRaw);
        $regionMap = $this->regionSalesmenCanonicalMap();

        foreach ($regionMap as $regionKey => $canonList) {
            if (in_array($canon, $canonList, true)) {
                return ucfirst($regionKey);
            }
        }

        $area = strtoupper(trim((string)$areaRaw));
        if ($area !== '') {
            if (str_contains($area, 'EAST')) return 'Eastern';
            if (str_contains($area, 'CENT')) return 'Central';
            if (str_contains($area, 'WEST')) return 'Western';
            if (str_contains($area, 'EXPORT') || str_contains($area, 'QATAR') || str_contains($area, 'BAHRAIN') || str_contains($area, 'KUWAIT')) {
                return 'Eastern';
            }
        }

        return null;
    }

    /**
     * Region => canonical salesmen list
     * Used when UI "Region" filter is selected.
     */
    private function regionSalesmenCanonicalMap(): array
    {
        return [
            // Eastern is now canonicalized under SOHAIB only (aliases handled in salesmanAliasMap)
            'eastern' => ['SOHAIB'],
            'central' => ['TAREQ', 'JAMAL'],
            'western' => ['ABDO', 'AHMED'],
        ];
    }

    /**
     * Normalize incoming list of salesmen to uppercase trimmed unique values.
     */
    private function normalizeSalesmenScope(array $salesmenScope): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn ($v) => strtoupper(trim((string)$v)), $salesmenScope))
        ));
    }

    /**
     * Build allowed salesmen list based on UI region selection.
     * If UI = "all" => return [] (meaning no UI restriction).
     */
    private function salesmenForRegionSelection(string $regionKey): array
    {
        if ($regionKey === 'all') {
            return [];
        }

        $regionMap = $this->regionSalesmenCanonicalMap();
        $aliasMap  = $this->salesmanAliasMap();

        $canonicals = $regionMap[$regionKey] ?? [];

        $names = [];
        foreach ($canonicals as $c) {
            $c = strtoupper(trim($c));
            foreach (($aliasMap[$c] ?? [$c]) as $a) {
                $names[] = strtoupper(trim((string)$a));
            }
        }

        return array_values(array_unique(array_filter($names)));
    }
    /**
     * Normalize OAA text (Sales OAA) for comparison.
     */
    private function normOaa(?string $v): string
    {
        return strtoupper(trim((string)$v));
    }

    /**
     * Decide if OAA is considered "Rejected".
     */
    private function isRejectedOaa(?string $oaa): bool
    {
        $o = $this->normOaa($oaa);

        // Keep this list aligned with your business terms
        return in_array($o, [
            'REJECTED',
            'REJECT',
            'CANCELLED',
            'CANCELED',
            'CANCEL',
            'DECLINED',
            'CLOSED LOST',
        ], true);
    }

    /**
     * Normalize status for coordinator PDF summary.
     */
    private function extractCoordinatorStatus(?string $oaa, ?string $status): ?string
    {
        $raw = '';

        if ($oaa !== null && trim((string)$oaa) !== '') {
            $raw = (string)$oaa;
        } elseif ($status !== null && trim((string)$status) !== '') {
            $raw = (string)$status;
        }

        if ($raw === '') return null;

        $rawUpper = strtoupper(trim($raw));

        if (str_contains($rawUpper, 'PRE'))    return 'PRE-ACCEPTANCE';
        if (str_contains($rawUpper, 'ACCEPT')) return 'ACCEPTANCE';
        if (str_contains($rawUpper, 'REJECT')) return 'REJECTED';
        if (str_contains($rawUpper, 'CANCEL')) return 'CANCELLED';

        return null;
    }

    /**
     * Build DOMPDF-safe SVG data URI.
     */
    private function svgToDataUri(string $svg): string
    {
        $svg = trim($svg);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Simple server-side bar chart (SVG) for coordinator PDF.
     */
    private function makeCoordinatorRegionChartBase64(array $regionTotals, string $yearLabel): string
    {
        $labels = ['KSA Eastern', 'KSA Western', 'KSA Central'];
        $values = [
            (float)($regionTotals['Eastern'] ?? 0),
            (float)($regionTotals['Western'] ?? 0),
            (float)($regionTotals['Central'] ?? 0),
        ];

        $w = 760;
        $h = 260;
        $padL = 60;
        $padR = 20;
        $padT = 30;
        $padB = 30;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;

        $maxVal = max(1, max($values));
        $slot = $plotW / 3;
        $barW = $slot * 0.6;

        $grid = '';
        $ticks = 4;
        for ($i = 0; $i <= $ticks; $i++) {
            $y = $padT + ($plotH - ($plotH * $i / $ticks));
            $grid .= '<line x1="'.$padL.'" y1="'.$y.'" x2="'.($w-$padR).'" y2="'.$y.'" stroke="#2a2a2a" stroke-width="1"/>';
        }

        $bars = '';
        for ($i = 0; $i < 3; $i++) {
            $val = $values[$i];
            $barH = ($val / $maxVal) * $plotH;
            $x = $padL + ($slot * $i) + (($slot - $barW) / 2);
            $y = $padT + ($plotH - $barH);

            $bars .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barW.'" height="'.$barH.'" fill="#79b74a" stroke="#5b8f33" stroke-width="1"/>';
            $bars .= '<text x="'.($x + $barW/2).'" y="'.($y - 6).'" text-anchor="middle" font-size="9" fill="#e5e7eb">'.number_format($val, 0).'</text>';
            $bars .= '<text x="'.($x + $barW/2).'" y="'.($h - 10).'" text-anchor="middle" font-size="9" fill="#e5e7eb">'.$labels[$i].'</text>';
        }

        $title = 'Sales Order Log Overview ' . $yearLabel;

        $svg = '
<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">
  <rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#3e3e3e" stroke="#111" stroke-width="2"/>
  <text x="'.($w/2).'" y="18" text-anchor="middle" font-size="12" fill="#ffffff" font-weight="700">'.$title.'</text>
  '.$grid.'
  '.$bars.'
</svg>';

        return $this->svgToDataUri($svg);
    }

    /**
     * Province summary for Sales Order Log exports (PDF).
     * Totals exclude rejected values (match Excel rules).
     */
    private function buildSalesOrdersProvinceSummary($rows): array
    {
        $provinces = [
            'EASTERN' => 'KSA Eastern Province',
            'WESTERN' => 'KSA Western Province',
            'CENTRAL' => 'KSA Central Province',
            'EXPORT'  => 'Export ( Qatar, Bahrain & Kuwait)',
        ];

        $summary = [];
        foreach ($provinces as $key => $label) {
            $summary[$key] = [
                'label'     => $label,
                'pre'       => 0.0,
                'accepted'  => 0.0,
                'rejected'  => 0.0,
                'cancelled' => 0.0,
                'total'     => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $regionRaw = strtoupper(trim((string) ($row->area ?? $row->region ?? '')));

            if (str_contains($regionRaw, 'EAST')) {
                $provKey = 'EASTERN';
            } elseif (str_contains($regionRaw, 'WEST')) {
                $provKey = 'WESTERN';
            } elseif (str_contains($regionRaw, 'CENT')) {
                $provKey = 'CENTRAL';
            } elseif (
                str_contains($regionRaw, 'EXPORT') ||
                str_contains($regionRaw, 'QATAR') ||
                str_contains($regionRaw, 'BAHRAIN') ||
                str_contains($regionRaw, 'KUWAIT')
            ) {
                $provKey = 'EXPORT';
            } else {
                continue;
            }

            $po = (float) ($row->po_value ?? $row->total_po_value ?? 0);
            if ($po <= 0) continue;

            $status = $this->extractCoordinatorStatus($row->oaa ?? null, $row->status ?? null);
            if (!empty($row->rejected_at)) {
                $status = 'REJECTED';
            }

            switch ($status) {
                case 'PRE-ACCEPTANCE':
                    $summary[$provKey]['pre'] += $po;
                    break;
                case 'ACCEPTANCE':
                    $summary[$provKey]['accepted'] += $po;
                    break;
                case 'REJECTED':
                    $summary[$provKey]['rejected'] += $po;
                    break;
                case 'CANCELLED':
                    $summary[$provKey]['cancelled'] += $po;
                    break;
                default:
                    break;
            }

            if ($status !== 'REJECTED') {
                $summary[$provKey]['total'] += $po;
            }
        }

        return $summary;
    }

    /**
     * Combine RBAC salesman scope + UI region selection scope:
     * - If UI region selected -> apply that region list
     * - If RBAC list exists too -> INTERSECT
     * - If UI = all -> RBAC only
     */
    private function buildEffectiveSalesmenScope(Request $request, array $rbacSalesmenScope): array
    {
        $rbac = $this->normalizeSalesmenScope($rbacSalesmenScope);

        $regionKey = $this->selectedSalesTeamRegion($request);
        $byRegion  = $this->salesmenForRegionSelection($regionKey); // [] if all

        // UI region restriction exists
        if (!empty($byRegion)) {
            // RBAC exists => intersect
            if (!empty($rbac)) {
                $set = array_flip($rbac);
                return array_values(array_filter($byRegion, fn ($x) => isset($set[$x])));
            }

            // UI region only
            return $byRegion;
        }

        // UI = all => RBAC only
        return $rbac;
    }

    /**
     * Apply salesman scope to SalesOrderLog query (Sales Source column).
     */
    private function applySalesmenScopeToSalesOrderQuery($query, array $salesmenScope)
    {
        $allowed = $this->normalizeSalesmenScope($salesmenScope);
        if (!empty($allowed)) {
            $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $allowed);
        }
        return $query;
    }

    /**
     * Apply salesman scope to Projects query (salesman/salesperson columns).
     */
    private function applySalesmenScopeToProjectsQuery($query, array $salesmenScope)
    {
        $allowed = $this->normalizeSalesmenScope($salesmenScope);
        if (!empty($allowed)) {
            $query->whereIn(DB::raw('UPPER(TRIM(COALESCE(`salesman`,`salesperson`)))'), $allowed);
        }
        return $query;
    }

    /**
     * Hard enforcement for single Sales Order access (show/attachments/view/delete).
     */
    private function enforceSalesOrderScopeOr403(Request $request, SalesOrderLog $salesorder): void
    {
        $user         = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        // Region check
        $soRegion = strtolower(trim((string)($salesorder->project_region ?? $salesorder->area ?? '')));
        if ($soRegion !== '' && !in_array($soRegion, $regionsScope, true)) {
            abort(403, 'Not allowed (region).');
        }

        // Salesman check (only when restricted)
        if (!empty($salesmenScope)) {
            $sm = strtoupper(trim((string)($salesorder->{'Sales Source'} ?? $salesorder->salesman ?? '')));
            if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                abort(403, 'Not allowed (salesman).');
            }
        }
    }

    /**
     * Duplicate PO No check (case/space-insensitive).
     * If $ignoreId provided, it is excluded (for update).
     */
    private function poNumberExists(string $poNo, ?int $ignoreId = null): bool
    {
        $poNo = trim($poNo);
        if ($poNo === '') return false;

        return DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->whereRaw('UPPER(TRIM(`PO. No.`)) = UPPER(?)', [$poNo])
            ->exists();
    }

    /**
     * Fix: ensure Sales Orders list contains fields needed by modal.
     * Strategy:
     * 1) Use existing working grouped query
     * 2) Try to add missing spaced columns
     * 3) If grouped query fails, fallback to robust DB::table query
     */
    private function fetchSalesOrdersForIndex(
        array $regionsScope,
        array $salesmenScope,
        ?int $year,
        ?int $month,
        ?string $from,
        ?string $to
    ) {
        // 1) Keep your existing working grouped query
        $q = SalesOrderLog::coordinatorGroupedQuery($regionsScope);
        $this->applySalesmenScopeToSalesOrderQuery($q, $salesmenScope);

        // ✅ Apply SAME UI filters (From/To overrides Year/Month)
        if ($from) $q->whereDate('date_rec', '>=', $from);
        if ($to)   $q->whereDate('date_rec', '<=', $to);

        if (!$from && !$to) {
            if ($year) {
                $q->whereYear('date_rec', $year);
            }
            if ($month) {
                $q->whereMonth('date_rec', $month);
            }
        }

        // ✅ IMPORTANT: grouped query -> rejected_at must be aggregated
        $q->addSelect([
            DB::raw("MAX(`salesorderlog`.`Payment Terms`) AS payment_terms"),
            DB::raw("MAX(`salesorderlog`.`Sales OAA`)     AS oaa"),
            DB::raw("MAX(`salesorderlog`.`Remarks`)       AS remarks"),
            DB::raw("MAX(`salesorderlog`.`Status`)        AS status"),
            DB::raw("MAX(`salesorderlog`.`Job No.`)       AS job_no"),
            DB::raw("MAX(`salesorderlog`.`rejected_at`)   AS rejected_at"),
        ]);

        try {
            return $q->orderByDesc('po_date')->get();
        } catch (\Throwable $e) {

            // 2) Fallback: explicit DB query (also apply SAME UI filters)
            $fallback = DB::table('salesorderlog')
                ->whereNull('deleted_at')
                ->selectRaw("
                id,
                `Client Name`      AS client,
                `Project Name`     AS project,
                `Sales Source`     AS salesman,
                `Location`         AS location,
                `project_region`   AS area,
                `Products`         AS atai_products,
                `Quote No.`        AS quotation_no,
                `PO. No.`          AS po_no,
                `date_rec`         AS po_date,
                `PO Value`         AS total_po_value,
                `value_with_vat`   AS value_with_vat,
                `Payment Terms`    AS payment_terms,
                `Sales OAA`        AS oaa,
                `Job No.`          AS job_no,
                `Remarks`          AS remarks,
                `Status`           AS status,
                `rejected_at`      AS rejected_at,
                created_by_id,
                created_at
            ");

            if (!empty($salesmenScope)) {
                $fallback->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
            }

            if (!empty($regionsScope)) {
                $fallback->whereIn(DB::raw('LOWER(TRIM(project_region))'), $regionsScope);
            }

            // ✅ Apply SAME UI filters (From/To overrides Year/Month)
            if ($from) $fallback->whereDate('date_rec', '>=', $from);
            if ($to)   $fallback->whereDate('date_rec', '<=', $to);

            if (!$from && !$to) {
                if ($year) {
                    $fallback->whereYear('date_rec', $year);
                }
                if ($month) {
                    $fallback->whereMonth('date_rec', $month);
                }
            }

            return $fallback->orderByDesc('po_date')->get();
        }
    }

    /**
     * Export rows: use SAME grouped query as the portal table
     * (so Excel/PDF match the UI record counts).
     */
    private function fetchSalesOrdersForExport(
        array $regionsScope,
        array $salesmenScope,
        ?int $year,
        ?int $month,
        ?string $from,
        ?string $to
    ) {
        $q = SalesOrderLog::coordinatorGroupedQuery($regionsScope);
        $this->applySalesmenScopeToSalesOrderQuery($q, $salesmenScope);

        // Apply SAME UI filters (From/To overrides Year/Month)
        if ($from) $q->whereDate('date_rec', '>=', $from);
        if ($to)   $q->whereDate('date_rec', '<=', $to);

        if (!$from && !$to) {
            if ($year) {
                $q->whereYear('date_rec', $year);
            }
            if ($month) {
                $q->whereMonth('date_rec', $month);
            }
        }

        // Add export fields (group-safe)
        $q->addSelect([
            DB::raw("GROUP_CONCAT(DISTINCT s.`Ref.No.` ORDER BY s.`Ref.No.` SEPARATOR ', ') AS ref_no"),
            DB::raw("MAX(s.`Cur`) AS cur"),
            DB::raw("MAX(s.`Location`) AS location"),
            DB::raw("MAX(s.`Project Location`) AS project_location"),
            DB::raw("MAX(s.`Payment Terms`) AS payment_terms"),
            DB::raw("MAX(s.`Sales OAA`) AS oaa"),
            DB::raw("MAX(s.`Status`) AS status"),
            DB::raw("MAX(s.`Remarks`) AS remarks"),
            DB::raw("MAX(s.`rejected_at`) AS rejected_at"),
        ]);

        return $q->orderByDesc('po_date')->get();
    }



    /* ============================================================
     | INDEX
     | ============================================================ */

    public function index(Request $request)
    {
        $user       = $request->user();
        $userRegion = strtolower((string)($user->region ?? ''));

        // Hard RBAC region scope (coordinators: all)
        $regionsScope = $this->coordinatorRegionScope($user);

        // Effective salesmen scope = RBAC + UI region selection (sales team filter)
        $rbacSalesmenScope = $this->coordinatorSalesmenScope($user);
        $salesmenScope     = $this->buildEffectiveSalesmenScope($request, $rbacSalesmenScope);

        /**
         * Dropdown canonicalization map:
         * - used only to show clean canonical labels in UI
         * - does not change any DB logic
         */
        $dropdownCanonicalMap = [
            // Eastern: all related names show as SOHAIB
            'SOHAIB' => ['SOHAIB', 'SOAHIB', 'SOAIB', 'SOHIB', 'SOHAI', 'RAVINDER', 'WASEEM', 'FAISAL', 'CLIENT', 'EXPORT'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF'],
            'AHMED'  => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin'],
        ];

        /* ------------------------------------------------------------
         | Salesmen dropdown options (scoped)
         | ------------------------------------------------------------ */

        $projectsSalesmenQ = Project::query()->whereNull('deleted_at');
        $this->applySalesmenScopeToProjectsQuery($projectsSalesmenQ, $salesmenScope);

        $salesOrdersSalesmenQ = DB::table('salesorderlog')->whereNull('deleted_at');
        $this->applySalesmenScopeToSalesOrderQuery($salesOrdersSalesmenQ, $salesmenScope);

        $salesmenRaw = collect()
            ->merge($projectsSalesmenQ->pluck('salesperson'))
            ->merge($projectsSalesmenQ->pluck('salesman'))
            ->merge($salesOrdersSalesmenQ->pluck('Sales Source'))
            ->filter()
            ->map(fn ($v) => strtoupper(trim((string)$v)))
            ->unique()
            ->values();

        $salesmenFilterOptions = [];
        foreach ($salesmenRaw as $s) {
            foreach ($dropdownCanonicalMap as $canonical => $aliases) {
                if (in_array($s, $aliases, true)) {
                    $salesmenFilterOptions[$canonical] = $canonical;
                    break;
                }
            }
        }

        if (empty($salesmenFilterOptions)) {
            $salesmenFilterOptions = !empty($salesmenScope)
                ? $salesmenScope
                : array_keys($dropdownCanonicalMap);
        } else {
            $salesmenFilterOptions = array_values($salesmenFilterOptions);
        }

        /* ------------------------------------------------------------
         | Projects + Sales Orders (scoped)
         | ------------------------------------------------------------ */

        // Your existing base query (model decides what appears)
        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $projects      = (clone $projectsQuery)->orderByDesc('quotation_date')->get();

        $selectedYear = (int)($request->input('year') ?: now()->year); // UI default
        $year  = $request->filled('year') ? (int)$request->input('year') : null; // filter only if provided
        $month = $request->filled('month') ? (int)$request->input('month') : null;
        $from  = $request->input('from');
        $to    = $request->input('to');
        // ✅ Ensure required fields available for modals/tables
        $salesOrders = $this->fetchSalesOrdersForIndex(
            $regionsScope,
            $salesmenScope,
            $year,
            $month,
            $from,
            $to
        );

        /* ------------------------------------------------------------
         | KPIs (scoped)
         | ------------------------------------------------------------ */

        $kpiProjectsCount     = (clone $projectsQuery)->count();
        $kpiSalesOrdersCount = $salesOrders->filter(fn($r) => blank($r->rejected_at))->count();
        $kpiSalesOrdersValue = (float) $salesOrders->filter(fn($r) => blank($r->rejected_at))->sum('total_po_value');
        /* ------------------------------------------------------------
         | Chart data (clean aggregates)
         | ------------------------------------------------------------ */

        $projectByRegion = DB::table('projects')
            ->whereNull('deleted_at')
            ->when(!empty($salesmenScope), function ($q) use ($salesmenScope) {
                $q->whereIn(DB::raw('UPPER(TRIM(COALESCE(`salesman`,`salesperson`)))'), $salesmenScope);
            })
            ->selectRaw('LOWER(area) as region_key, SUM(quotation_value) as total')
            ->groupBy('region_key')
            ->pluck('total', 'region_key')
            ->toArray();

        $poByRegion = DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->whereNull('rejected_at') // ✅ exclude rejected from totals
            ->when(!empty($salesmenScope), function ($q) use ($salesmenScope) {
                $q->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
            })
            ->selectRaw('LOWER(project_region) as region_key, SUM(`PO Value`) as total')
            ->groupBy('region_key')
            ->pluck('total', 'region_key')
            ->toArray();

        $regions         = ['eastern', 'central', 'western'];
        $chartCategories = [];
        $chartProjects   = [];
        $chartPOs        = [];

        foreach ($regions as $r) {
            if (!in_array($r, $regionsScope, true)) continue;
            $chartCategories[] = ucfirst($r);
            $chartProjects[]   = (float)($projectByRegion[$r] ?? 0);
            $chartPOs[]        = (float)($poByRegion[$r] ?? 0);
        }

/*
debugging format

        dd([
        'total_rows' => $salesOrders->count(),
        'rejected_rows' => $salesOrders->filter(fn($r) => !blank($r->rejected_at))->count(),
        'rejected_po_values' => $salesOrders->filter(fn($r) => !blank($r->rejected_at))->sum('total_po_value'),
            'totalsum includingrejected'=>(float) $salesOrders->sum('total_po_value'),
            'totalsum excludingrejected'=> (float) $salesOrders->filter(fn($r) => blank($r->rejected_at))->sum('total_po_value')
    ]);

        */

        return view('coordinator.index', [
            'userRegion'            => $userRegion,
            'regionsScope'          => $regionsScope,
            'salesmenScope'         => $salesmenScope,
            'salesmenFilterOptions' => $salesmenFilterOptions,
            'selectedYear'          => $selectedYear,

            'kpiProjectsCount'     => $kpiProjectsCount,
            'kpiSalesOrdersCount'  => $kpiSalesOrdersCount,
            'kpiSalesOrdersValue'  => $kpiSalesOrdersValue,

            'projects'    => $projects,
            'salesOrders' => $salesOrders,

            'chartCategories' => $chartCategories,
            'chartProjects'   => $chartProjects,
            'chartPOs'        => $chartPOs,
        ]);
    }

    /* ============================================================
     | STORE PO (Create/Update + Attachments + Breakdown)
     | ============================================================ */

    /**
     * Sync PO breakdown row(s) into salesorderlog_product_breakdowns.
     *
     * Rules:
     * - If no subtype selected => remove breakdown rows (optionally by family).
     * - If subtype amount is blank => assume full row PO value.
     * - If family = DUCTWORK => add remainder into base bucket as "Ductwork".
     */
    private function syncPoBreakdownRow(
        int $salesorderlogId,
        ?string $family,
        ?string $subtype,
        ?float $subtypeAmount,
        float $defaultAmount,
        ?string $quotationNo = null
    ): void {
        $familyRaw  = trim((string)$family);
        $subtypeRaw = trim((string)$subtype);

        $familyU  = strtoupper($familyRaw);
        $subtype  = $subtypeRaw; // optional: keep original casing for display

        if ($familyU === '') $familyU = 'UNKNOWN';

        // ✅ If no subtype => delete breakdown rows (safe cleanup)
        if ($subtypeRaw === '') {
            $q = DB::table('salesorderlog_product_breakdowns')
                ->where('salesorderlog_id', $salesorderlogId);

            if ($familyU !== 'UNKNOWN') {
                $q->whereRaw('UPPER(TRIM(family)) = ?', [$familyU]);
            }

            $q->delete();
            return;
        }

        // If subtype amount blank => use full PO value
        $amount = ($subtypeAmount === null) ? (float)$defaultAmount : (float)$subtypeAmount;

        // Guardrails
        if ($amount < 0) $amount = 0.0;
        if ($defaultAmount < 0) $defaultAmount = 0.0;

        // Clamp: never allow subtype amount > row PO value
        if ($amount > $defaultAmount) $amount = $defaultAmount;

        // Delete existing rows for this PO + family (prevent duplicates)
        DB::table('salesorderlog_product_breakdowns')
            ->where('salesorderlog_id', $salesorderlogId)
            ->whereRaw('UPPER(TRIM(family)) = ?', [$familyU])
            ->delete();

        $rows = [];

        // 1) Insert selected subtype row
        $rows[] = [
            'salesorderlog_id' => $salesorderlogId,
            'family'           => $familyU,
            'subtype'          => $subtypeRaw,
            'amount'           => round($amount, 2),
            'quotation_no'     => $quotationNo,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        // 2) Special rule: DUCTWORK family remainder
        if ($familyU === 'DUCTWORK') {
            $remainder = round(((float)$defaultAmount - (float)$amount), 2);

            if ($remainder > 0.01) {
                $rows[] = [
                    'salesorderlog_id' => $salesorderlogId,
                    'family'           => $familyU,
                    'subtype'          => 'Ductwork',
                    'amount'           => $remainder,
                    'quotation_no'     => $quotationNo,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
        }

        DB::table('salesorderlog_product_breakdowns')->insert($rows);
    }

    public function storePo(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'source'    => ['required', 'in:project,salesorder'],
            'record_id' => ['required', 'integer'],

            // LEFT SIDE (optional)
            'project'         => ['nullable', 'string', 'max:255'],
            'client'          => ['nullable', 'string', 'max:255'],
            'salesman'        => ['nullable', 'string', 'max:255'],
            'location'        => ['nullable', 'string', 'max:255'],
            'area'            => ['nullable', 'string', 'max:255'],
            'quotation_no'    => ['nullable', 'string', 'max:255'],
            'quotation_date'  => ['nullable', 'date'],
            'date_received'   => ['nullable', 'date'],
            'atai_products'   => ['nullable', 'string', 'max:255'],
            'quotation_value' => ['nullable', 'numeric', 'min:0'],

            // RIGHT SIDE (PO)
            'job_no'        => ['nullable', 'string', 'max:255'],
            'po_no'         => ['required', 'string', 'max:255'],
            'po_date'       => ['required', 'date'],
            'po_value'      => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'remarks'       => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:51200'],
            'oaa'           => ['nullable', 'string', 'max:50'],

            // Niyas-only subtype capture (optional)
            'atai_products_family'     => ['nullable', 'string', 'max:50'],
            'products_subtype'         => ['nullable', 'string', 'max:255'],
            'products_subtype_amount'  => ['nullable', 'numeric', 'min:0'],
        ]);

        // ✅ Detect Niyas (keep your rule)
        $isNiyas = $user && (
                strtolower(trim((string)$user->name)) === 'niyas'
                || (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western'))
            );

        // ✅ Non-Niyas => ignore subtype inputs fully
        if (!$isNiyas) {
            $data['atai_products_family']    = null;
            $data['products_subtype']        = null;
            $data['products_subtype_amount'] = null;
        }

        // ✅ Normalize subtype early
        if (isset($data['products_subtype'])) {
            $data['products_subtype'] = trim((string)$data['products_subtype']);
            if ($data['products_subtype'] === '') {
                $data['products_subtype'] = null;
            }
        }

        try {
            return DB::transaction(function () use ($data, $request, $user, $isNiyas) {

                $regionsScope   = $this->coordinatorRegionScope($user);
                $salesmenScope  = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));
                $poNo           = trim((string)$data['po_no']);
                $newOaa         = $data['oaa'] ?? null;

                if ($poNo === '') {
                    return response()->json(['ok' => false, 'message' => 'PO number is required.'], 422);
                }

                // Attachments input
                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $files = $request->file('attachments');
                    $attachments = is_array($files) ? $files : [$files];
                }

                /* ========================================================
                 | UPDATE EXISTING SALES ORDER
                 | ======================================================== */
                if ($data['source'] === 'salesorder') {

                    /** @var SalesOrderLog $so */
                    $so = SalesOrderLog::findOrFail($data['record_id']);

                    // Duplicate check (except current)
                    if ($this->poNumberExists($poNo, (int)$so->id)) {
                        return response()->json([
                            'ok' => false,
                            'message' => "This PO number already exists in Sales Order Log.",
                        ], 422);
                    }

                    // Scope enforcement
                    $soRegion = strtolower(trim((string)($so->project_region ?? $so->area ?? '')));
                    if ($soRegion !== '' && !in_array($soRegion, $regionsScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'Not allowed (region).'], 403);
                    }

                    if (!empty($salesmenScope)) {
                        $sm = strtoupper(trim((string)($so->{'Sales Source'} ?? $so->salesman ?? '')));
                        if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                            return response()->json(['ok' => false, 'message' => 'Not allowed (salesman).'], 403);
                        }
                    }

                    $poDate         = Carbon::parse($data['po_date'])->format('Y-m-d');
                    $poValueWithVat = round(((float)$data['po_value']) * 1.15, 2);

                    $client      = $data['client']   ?? ($so->client ?? ($so->{'Client Name'} ?? null));
                    $projectName = $data['project']  ?? ($so->project ?? ($so->{'Project Name'} ?? null));
                    $salesSource = $data['salesman'] ?? ($so->salesman ?? ($so->{'Sales Source'} ?? null));
                    $location    = $data['location'] ?? ($so->location ?? ($so->{'Location'} ?? null));

                    $area     = $data['area'] ?? ($so->project_region ?? $so->area ?? null);
                    $quoteNo  = $data['quotation_no'] ?? ($so->quotation_no ?? ($so->{'Quote No.'} ?? null));
                    $products = $data['atai_products'] ?? ($so->atai_products ?? ($so->{'Products'} ?? null));

                    // rejected_at rule
                    $oldOaa       = $so->oaa ?? ($so->{'Sales OAA'} ?? null);
                    $wasRejected  = $this->isRejectedOaa($oldOaa);
                    $isRejected   = $this->isRejectedOaa($newOaa);

                    if (!$wasRejected && $isRejected) {
                        $rejectedAtSql = now()->format('Y-m-d H:i:s');
                    } elseif ($wasRejected && !$isRejected) {
                        $rejectedAtSql = null;
                    } else {
                        $rejectedAtSql = $so->rejected_at ? Carbon::parse($so->rejected_at)->format('Y-m-d H:i:s') : null;
                    }

                    // Raw SQL update (KEEP)
                    DB::update("
                    UPDATE `salesorderlog`
                    SET
                        `Client Name`      = ?,
                        `Project Name`     = ?,
                        `Sales Source`     = ?,
                        `Location`         = ?,
                        `project_region`   = ?,
                        `region`           = ?,
                        `Products`         = ?,
                        `Products_raw`     = ?,
                        `Quote No.`        = ?,
                        `date_rec`         = ?,
                        `PO. No.`          = ?,
                        `PO Value`         = ?,
                        `value_with_vat`   = ?,
                        `Payment Terms`    = ?,
                        `Sales OAA`        = ?,
                        `Job No.`          = ?,
                        `Factory Loc`      = ?,
                        `Remarks`          = ?,
                        `rejected_at`      = ?,
                        created_by_id      = ?,
                        `updated_at`       = NOW()
                    WHERE `id` = ?
                ", [
                        $client,
                        $projectName,
                        $salesSource,
                        $location,
                        $area,
                        $area,
                        $products,
                        $products,
                        $quoteNo,
                        $poDate,
                        $poNo,
                        (float)$data['po_value'],
                        $poValueWithVat,
                        $data['payment_terms'] ?? null,
                        $newOaa,
                        $data['job_no'] ?? null,
                        $area,
                        $data['remarks'] ?? null,
                        $rejectedAtSql,
                        $user->id,
                        $so->id,
                    ]);

                    // Breakdown sync (update)
                    $this->syncPoBreakdownRow(
                        (int)$so->id,
                        $data['atai_products_family'] ?? null,
                        $data['products_subtype'] ?? null,
                        array_key_exists('products_subtype_amount', $data) && $data['products_subtype_amount'] !== null
                            ? (float)$data['products_subtype_amount']
                            : null,
                        (float)$data['po_value'],
                        $quoteNo
                    );

                    // Attachments upload (update)
                    foreach ($attachments as $file) {
                        if (!$file || !$file->isValid()) continue;

                        $path = $file->store("salesorders/{$so->id}", 'public');

                        SalesOrderAttachment::create([
                            'salesorderlog_id' => $so->id,
                            'disk'             => 'public',
                            'path'             => $path,
                            'original_name'    => $file->getClientOriginalName(),
                            'size_bytes'       => $file->getSize(),
                            'mime_type'        => $file->getClientMimeType(),
                            'uploaded_by'      => $user->id,
                        ]);
                    }

                    return response()->json(['ok' => true, 'message' => 'PO updated successfully.']);
                }

                /* ========================================================
                 | CREATE NEW PO (FROM PROJECTS)
                 | ======================================================== */

                // Duplicate PO No (global)
                if ($this->poNumberExists($poNo)) {
                    return response()->json([
                        'ok' => false,
                        'message' => "This PO number already exists in Sales Order Log.",
                    ], 422);
                }

                $mainProject = Project::findOrFail($data['record_id']);

                // Security: region + salesman
                $mainArea = strtolower((string)($mainProject->area ?? ''));
                if ($mainArea !== '' && !in_array($mainArea, $regionsScope, true)) {
                    return response()->json(['ok' => false, 'message' => 'Not allowed (region).'], 403);
                }

                if (!empty($salesmenScope)) {
                    $pSm = strtoupper(trim((string)($mainProject->salesman ?? $mainProject->salesperson ?? '')));
                    if ($pSm !== '' && !in_array($pSm, $salesmenScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'Not allowed (salesman).'], 403);
                    }
                }

                // Multi-quotation split
                $extraIds   = $request->input('extra_project_ids', []);
                $projectIds = array_unique(array_filter(array_merge([$mainProject->id], (array)$extraIds)));

                $projects = Project::query()->whereIn('id', $projectIds)->get();
                if ($projects->isEmpty()) {
                    return response()->json(['ok' => false, 'message' => 'No projects found.'], 422);
                }

                // Ensure all selected projects are within scope
                foreach ($projects as $p) {
                    $a = strtolower((string)($p->area ?? ''));
                    if ($a !== '' && !in_array($a, $regionsScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'One of selected quotations is out of your region scope.'], 403);
                    }

                    if (!empty($salesmenScope)) {
                        $pSm = strtoupper(trim((string)($p->salesman ?? $p->salesperson ?? '')));
                        if ($pSm !== '' && !in_array($pSm, $salesmenScope, true)) {
                            return response()->json(['ok' => false, 'message' => 'One of selected quotations is out of your salesman scope.'], 403);
                        }
                    }
                }

                $totalQuoted = (float)$projects->sum('quotation_value');
                if ($totalQuoted < 0) $totalQuoted = 0;

                // If OAA rejected during create -> rejected_at NOW else NULL
                $createRejectedAtSql = $this->isRejectedOaa($newOaa) ? now()->format('Y-m-d H:i:s') : null;

                $createdSoIds = [];

                foreach ($projects as $project) {

                    $ratio = ($totalQuoted > 0)
                        ? max(0, (float)$project->quotation_value) / $totalQuoted
                        : (1 / max(1, $projects->count()));

                    $poValue        = round(((float)$data['po_value']) * $ratio, 2);
                    $poValueWithVat = round($poValue * 1.15, 2);

                    DB::insert("
                    INSERT INTO `salesorderlog`
                    (
                        `Client Name`, `region`, `project_region`, `Location`, `date_rec`,
                        `PO. No.`, `Products`, `Products_raw`, `Quote No.`, `Ref.No.`, `Cur`,
                        `PO Value`, `value_with_vat`, `Payment Terms`,
                        `Project Name`, `Project Location`,
                        `Sales OAA`, `Job No.`, `Factory Loc`, `Sales Source`,
                        `Remarks`, `rejected_at`, `created_by_id`, `created_at`
                    )
                    VALUES (?,?,?,?,?,
                            ?,?,?,?,?,
                            ?,?,?,?,
                            ?,?,
                            ?,?,?,?,
                            ?,?,?,NOW())
                ", [
                        // use YOUR project columns (same as your existing code)
                        $project->client_name,
                        $project->area,
                        $project->area,
                        $project->project_location,
                        Carbon::parse($data['po_date'])->format('Y-m-d'),

                        $poNo,
                        $project->atai_products,
                        $project->atai_products,
                        $project->quotation_no,
                        null,

                        'SAR',
                        $poValue,
                        $poValueWithVat,
                        $data['payment_terms'] ?? null,

                        $project->project_name,
                        $project->project_location,

                        $newOaa,
                        $data['job_no'] ?? null,
                        $project->area,
                        $project->salesman ?? $project->salesperson,

                        $data['remarks'] ?? null,
                        $createRejectedAtSql,
                        $user->id,
                    ]);

                    $soId = (int)DB::getPdo()->lastInsertId();
                    $createdSoIds[] = $soId;

                    // Breakdown sync (create) - amount split by ratio if user provided subtype amount
                    $this->syncPoBreakdownRow(
                        (int)$soId,
                        $data['atai_products_family'] ?? null,
                        $data['products_subtype'] ?? null,
                        array_key_exists('products_subtype_amount', $data) && $data['products_subtype_amount'] !== null
                            ? round(((float)$data['products_subtype_amount']) * $ratio, 2)
                            : null,
                        (float)$poValue,
                        $project->quotation_no ?? null
                    );

                    // Update project status (keep your behavior)
                    $project->status = 'PO-RECEIVED';
                    $project->status_current = 'PO-RECEIVED';
                    $project->coordinator_updated_by_id = $user->id;
                    $project->save();
                }

                // Attachments upload (create): attach to ALL created SO rows
                if (!empty($attachments) && !empty($createdSoIds)) {
                    foreach ($createdSoIds as $soId) {
                        foreach ($attachments as $file) {
                            if (!$file || !$file->isValid()) continue;

                            $path = $file->store("salesorders/{$soId}", 'public');

                            SalesOrderAttachment::create([
                                'salesorderlog_id' => $soId,
                                'disk'             => 'public',
                                'path'             => $path,
                                'original_name'    => $file->getClientOriginalName(),
                                'size_bytes'       => $file->getSize(),
                                'mime_type'        => $file->getClientMimeType(),
                                'uploaded_by'      => $user->id,
                            ]);
                        }
                    }
                }

                return response()->json([
                    'ok' => true,
                    'message' => 'PO saved successfully.',
                    'created_salesorder_ids' => $createdSoIds,
                ]);
            });

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Server error while saving PO.',
            ], 500);
        }
    }



    /* ============================================================
     | SHOW SALES ORDER (Modal data)
     | ============================================================ */

    public function showSalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $this->enforceSalesOrderScopeOr403($request, $salesorder);

        $salesorder->load('attachments', 'creator');

        // Breakdown (if any) for prefill
        $breakdown = DB::table('salesorderlog_product_breakdowns')
            ->where('salesorderlog_id', $salesorder->id)
            ->first();

        return response()->json([
            'ok' => true,
            'salesorder' => [
                'id'            => $salesorder->id,
                'po_no'         => $salesorder->po_no,
                'project'       => $salesorder->project,
                'client'        => $salesorder->client,
                'salesman'      => $salesorder->salesman,
                'area'          => $salesorder->area,
                'atai_products' => $salesorder->atai_products,
                'po_date'       => optional($salesorder->po_date)->format('Y-m-d'),
                'po_value'      => $salesorder->total_po_value ?? $salesorder->po_value,
                'payment_terms' => $salesorder->payment_terms,
                'remarks'       => $salesorder->remarks,
                'created_by'    => optional($salesorder->creator)->name,
                'created_at'    => optional($salesorder->created_at)->format('Y-m-d H:i'),
            ],
            'breakdown' => $breakdown ? [
                'family'  => $breakdown->family,
                'subtype' => $breakdown->subtype,
                'amount'  => (float)$breakdown->amount,
            ] : null,
            'attachments' => $salesorder->attachments->map(function ($a) {
                return [
                    'id'            => $a->id,
                    'original_name' => $a->original_name,
                    'url'           => route('coordinator.attachments.view', ['attachment' => $a->id]),
                    'created_at'    => optional($a->created_at)->format('Y-m-d H:i'),
                    'size_bytes'    => $a->size_bytes,
                ];
            })->values(),
        ]);
    }

    /* ============================================================
     | EXPORTS
     | ============================================================ */

    public function exportSalesOrders(Request $request): BinaryFileResponse
    {
        $month = (int)$request->input('month');
        if (!$month) abort(400, 'Month is required');

        $year = (int)($request->input('year') ?: now()->year);
        $from = $request->input('from');
        $to   = $request->input('to');
        $regionKey = $request->input('region', 'all');

        $user = $request->user();

        // Effective scope (RBAC + UI region filter)
        $salesmenScope = $this->buildEffectiveSalesmenScope($request, $this->coordinatorSalesmenScope($user));

        // ✅ Western coordinator: force western-only exports
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western')) {
            $salesmenScope = $this->normalizeSalesmenScope($this->salesmenForRegionSelection('western'));
            $regionKey = 'western';
        }

        $regionsScope = $this->coordinatorRegionScope($user);
        $rows = $this->fetchSalesOrdersForExport($regionsScope, $salesmenScope, $year, $month, $from, $to);

        $regionSlug = $this->regionSlugFromKey($regionKey);
        $filename = 'sales_order_log_' . $regionSlug . '.xlsx';

        return Excel::download(
            new SalesOrdersMonthExport($rows, $year, $month),
            $filename
        );
    }

    public function exportSalesOrdersYear(Request $request): BinaryFileResponse
    {
        $year    = (int)($request->input('year') ?: now()->year);
        $region  = strtolower((string)$request->input('region', 'all'));
        $salesman = trim((string)$request->input('salesman', 'all'));
        $from    = $request->input('from');
        $to      = $request->input('to');

        $user = $request->user();

        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        // ✅ Western coordinator: force western-only exports
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western')) {
            $salesmenScope = $this->normalizeSalesmenScope($this->salesmenForRegionSelection('western'));
            $region = 'western';
        }

        $regionSlug = $this->regionSlugFromKey($region);
        $filename = 'sales_order_log_' . $regionSlug . '.xlsx';

        return Excel::download(
            new SalesOrdersYearExport(
                $regionsScope,
                $year,
                $region,
                $from,
                $to,
                $salesman !== '' ? strtoupper($salesman) : 'all',
                null,
                $salesmenScope
            ),
            $filename
        );
    }

    public function exportSalesOrdersPdf(Request $request)
    {
        $year  = (int)($request->input('year') ?: now()->year);
        $month = $request->filled('month') ? (int)$request->input('month') : null;
        $from  = $request->input('from');
        $to    = $request->input('to');
        $region = strtolower((string)$request->input('region', 'all'));

        $user = $request->user();

        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->buildEffectiveSalesmenScope($request, $this->coordinatorSalesmenScope($user));

        // ✅ Western coordinator: force western-only exports
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western')) {
            $salesmenScope = $this->normalizeSalesmenScope($this->salesmenForRegionSelection('western'));
            $region = 'western';
        }

        $rows = $this->fetchSalesOrdersForExport($regionsScope, $salesmenScope, $year, $month, $from, $to);

        $summary = $this->buildSalesOrdersProvinceSummary($rows);
        $order = ['EASTERN', 'WESTERN', 'CENTRAL', 'EXPORT'];

        $summaryRows = [];
        $totalOrders = 0.0;
        $totalRejected = 0.0;

        foreach ($order as $key) {
            $label = $summary[$key]['label'] ?? $key;
            $total = (float)($summary[$key]['total'] ?? 0);
            $rejected = (float)($summary[$key]['rejected'] ?? 0);
            $summaryRows[] = [
                'label' => $label,
                'total' => $total,
                'rejected' => $rejected,
            ];
            $totalOrders += $total;
            $totalRejected += $rejected;
        }

        $periodLabel = 'All Years';
        if ($month) {
            $monthName = date('M', mktime(0, 0, 0, $month, 1));
            $periodLabel = $monthName . '-' . $year;
        } elseif ($from || $to) {
            if ($from && $to) {
                $periodLabel = $from . ' to ' . $to;
            } elseif ($from) {
                $periodLabel = 'From ' . $from;
            } else {
                $periodLabel = 'To ' . $to;
            }
        } elseif ($year) {
            $periodLabel = (string)$year;
        }

        $regionLabel = ($region && $region !== 'all')
            ? ucfirst($region) . ' Region'
            : 'All Regions';

        $mappedRows = $rows->map(function ($so) {
            $poValue  = $so->po_value ?? $so->total_po_value ?? $so->{'PO Value'} ?? 0;
            $vatValue = $so->value_with_vat ?? $so->{'value_with_vat'} ?? 0;

            $dateRec = $so->date_rec ?? $so->po_date ?? '';
            if ($dateRec instanceof \Carbon\Carbon) {
                $dateRec = $dateRec->format('Y-m-d');
            }

            $area = $so->area ?? $so->region ?? '';
            $location = $so->location ?? $so->project_location ?? '';
            $projectLocation = $so->project_location ?? $so->location ?? '';
            $status = $so->status ?? ($so->{'Status'} ?? '');
            $oaa = $so->oaa ?? ($so->{'Sales OAA'} ?? '');
            $salesman = $so->salesman ?? ($so->{'Sales Source'} ?? '');
            $remarks = $so->remarks ?? ($so->{'Remarks'} ?? '');

            $statusNorm = $this->extractCoordinatorStatus($oaa, $status);
            $isRejected = !empty($so->rejected_at) || ($statusNorm === 'REJECTED');

            return [
                'client'           => $so->client ?? '',
                'area'             => $area,
                'location'         => $location,
                'date_rec'         => $dateRec ?: '',
                'po_no'            => $so->po_no ?? '',
                'atai_products'    => $so->atai_products ?? '',
                'quotation_no'     => $so->quotation_no ?? '',
                'ref_no'           => $so->ref_no ?? '',
                'cur'              => $so->cur ?? 'SAR',
                'po_value'         => (float) $poValue,
                'value_with_vat'   => (float) $vatValue,
                'payment_terms'    => $so->payment_terms ?? '',
                'project'          => $so->project ?? '',
                'project_location' => $projectLocation,
                'status'           => $status ?? '',
                'oaa'              => $oaa ?? '',
                'job_no'           => $so->job_no ?? '',
                'salesman'         => $salesman ?? '',
                'remarks'          => $remarks ?? '',
                'is_rejected'      => $isRejected,
            ];
        });

        $logoPath = public_path('images/atai-logo.png');
        if (!file_exists($logoPath)) {
            $logoPath = null;
        }

        $payload = [
            'generatedAt'   => now()->format('Y-m-d'),
            'periodLabel'   => $periodLabel,
            'regionLabel'   => $regionLabel,
            'summaryRows'   => $summaryRows,
            'totalOrders'   => $totalOrders,
            'totalRejected' => $totalRejected,
            'rows'          => $mappedRows,
            'logoPath'      => $logoPath,
        ];

        $regionSlug = $this->regionSlugFromKey($region);
        $fileName = 'sales_order_log_' . $regionSlug . '.pdf';

        return Pdf::loadView('reports.coordinator-sales-orders-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    /* ============================================================
     | COORDINATOR GRAPH PDF (Eastern only)
     | ============================================================ */

    public function saveCoordinatorGraph(Request $request)
    {
        $user = $request->user();
        if (!($user && method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_eastern'))) {
            abort(403, 'Not allowed.');
        }

        $image = $request->input('image');
        if (!$image || !str_starts_with($image, 'data:image/png;base64,')) {
            return response()->json(['ok' => false, 'message' => 'Invalid image'], 422);
        }

        $token = (string) $request->input('token', '');
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);
        if ($token === '') {
            $token = (string) Str::uuid();
        }

        $prefix = 'data:image/png;base64,';
        $base64 = substr($image, strlen($prefix));
        $binary = base64_decode($base64);

        if ($binary === false) {
            return response()->json(['ok' => false, 'message' => 'Decode failed'], 422);
        }

        $relativePath = "reports/coordinator_chart_{$token}.png";
        Storage::disk('public')->put($relativePath, $binary);

        // Also ensure file exists under public/storage for DomPDF lookup
        $publicPath = public_path("storage/{$relativePath}");
        $publicDir  = dirname($publicPath);
        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        @file_put_contents($publicPath, $binary);

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'path'  => $relativePath,
        ]);
    }

    public function coordinatorGraphPdf(Request $request)
    {
        $user = $request->user();
        if (!($user && method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_eastern'))) {
            abort(403, 'Not allowed.');
        }

        $token = (string) $request->input('token', '');
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);

        $chartImagePath = null;
        if ($token !== '') {
            $relative   = "reports/coordinator_chart_{$token}.png";
            $publicPath = public_path("storage/{$relative}");
            if (file_exists($publicPath)) {
                $chartImagePath = $publicPath;
            }
            if (!$chartImagePath) {
                $storagePath = storage_path("app/public/{$relative}");
                if (file_exists($storagePath)) {
                    // copy to public/storage so DomPDF can read
                    $publicDir = dirname($publicPath);
                    if (!is_dir($publicDir)) {
                        @mkdir($publicDir, 0755, true);
                    }
                    @copy($storagePath, $publicPath);
                    if (file_exists($publicPath)) {
                        $chartImagePath = $publicPath;
                    }
                }
            }
        }

        $yearParam = $request->input('year');
        $month  = $request->input('month');
        $from   = $request->input('from');
        $to     = $request->input('to');
        $region = $request->input('region', 'all');

        $yearInt  = $yearParam ? (int) $yearParam : null;
        $monthInt = $month ? (int) $month : null;
        $yearLabel = $yearInt ? (string)$yearInt : 'All Years';

        // Scope (RBAC + UI region)
        $salesmenScope = $this->buildEffectiveSalesmenScope($request, $this->coordinatorSalesmenScope($user));
        $regionsScope  = $this->coordinatorRegionScope($user);

        // ✅ Use SAME grouped rows as the portal table / Excel export
        $rows = $this->fetchSalesOrdersForExport(
            $regionsScope,
            $salesmenScope,
            $yearInt,
            $monthInt,
            $from,
            $to
        );

        // Totals by region (non-rejected only)
        $regionTotals = [
            'Eastern' => 0.0,
            'Central' => 0.0,
            'Western' => 0.0,
        ];
        $rejectedByRegion = [
            'Eastern' => 0.0,
            'Central' => 0.0,
            'Western' => 0.0,
        ];

        // Monthly totals by region (year view)
        $monthlyByRegion = [
            'Eastern' => array_fill(1, 12, 0.0),
            'Central' => array_fill(1, 12, 0.0),
            'Western' => array_fill(1, 12, 0.0),
        ];

        // Status counts + values
        $statusCounts = [
            'ACCEPTANCE'     => 0,
            'PRE-ACCEPTANCE' => 0,
            'REJECTED'       => 0,
            'CANCELLED'      => 0,
        ];
        $statusValues = [
            'ACCEPTANCE'     => 0.0,
            'PRE-ACCEPTANCE' => 0.0,
            'REJECTED'       => 0.0,
            'CANCELLED'      => 0.0,
        ];

        foreach ($rows as $r) {
            $po = (float) ($r->po_value ?? $r->total_po_value ?? 0);
            if ($po < 0) $po = 0;

            // Status counts/values should include rejected rows (for PDF summary table)
            $status = $this->extractCoordinatorStatus($r->oaa ?? null, $r->status ?? null);
            if (!empty($r->rejected_at)) {
                $status = 'REJECTED';
            } elseif (!$status) {
                $status = 'ACCEPTANCE';
            }
            if (isset($statusCounts[$status])) {
                $statusCounts[$status] += 1;
                $statusValues[$status] += $po;
            }

            // ✅ Match KPI behavior for region totals: ignore rejected rows
            if (!empty($r->rejected_at)) {
                $areaRaw = $r->area ?? $r->region ?? null;
                $reg = $this->resolveRegionForRow($r->salesman ?? null, $areaRaw);
                if ($reg && isset($rejectedByRegion[$reg])) {
                    $rejectedByRegion[$reg] += $po;
                }
                continue;
            }

            $areaRaw = $r->area ?? $r->region ?? null;
            $reg = $this->resolveRegionForRow($r->salesman ?? null, $areaRaw);
            if ($reg && isset($regionTotals[$reg])) {
                $regionTotals[$reg] += $po;

                $m = $r->po_date ? (int) date('n', strtotime($r->po_date)) : null;
                if ($m && isset($monthlyByRegion[$reg][$m])) {
                    $monthlyByRegion[$reg][$m] += $po;
                }
            }
        }

        $totalOrdersCount = array_sum($statusCounts);
        $totalOrdersValue = array_sum($statusValues);

        $payload = [
            'user'           => $user,
            'today'          => now()->format('d-m-Y'),
            'year'           => $yearInt,
            'month'          => $monthInt,
            'from'           => $from,
            'to'             => $to,
            'region'         => $region,
            'chartImagePath' => $chartImagePath,
            'chartDataUri'   => $this->makeCoordinatorRegionChartBase64($regionTotals, $yearLabel),
            'regionTotals'   => $regionTotals,
            'rejectedByRegion' => $rejectedByRegion,
            'monthlyByRegion'=> $monthlyByRegion,
            'statusCounts'   => $statusCounts,
            'statusValues'   => $statusValues,
            'totalOrdersCount' => $totalOrdersCount,
            'totalOrdersValue' => $totalOrdersValue,
            'yearLabel'      => $yearLabel,
        ];

        $pdf = Pdf::loadView('reports.coordinator-graph-pdf', $payload)
            ->setPaper('a4', 'landscape');

        $fileName = 'Coordinator_Graph_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    /* ============================================================
     | ATTACHMENTS (List + Stream)
     | ============================================================ */

    public function salesOrderAttachments(Request $request, SalesOrderLog $salesorder)
    {
        $this->enforceSalesOrderScopeOr403($request, $salesorder);

        $salesorder->load('attachments');

        $items = $salesorder->attachments->map(function ($a) {
            return [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => route('coordinator.attachments.view', ['attachment' => $a->id]),
                'created_at'    => optional($a->created_at)->format('Y-m-d H:i'),
                'size_bytes'    => $a->size_bytes,
            ];
        })->values();

        return response()->json(['ok' => true, 'attachments' => $items]);
    }

    /**
     * Secure attachment viewer:
     * - Loads parent SO
     * - Applies RBAC scope
     * - Streams file inline (prevents direct /storage access)
     */
    public function viewAttachment(Request $request, SalesOrderAttachment $attachment)
    {
        $salesorder = SalesOrderLog::findOrFail($attachment->salesorderlog_id);
        $this->enforceSalesOrderScopeOr403($request, $salesorder);

        $disk = $attachment->disk ?: 'public';
        $path = $attachment->path;

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found.');
        }

        $fullPath = Storage::disk($disk)->path($path);

        return response()->file($fullPath, [
            'Content-Type'        => $attachment->mime_type ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($attachment->original_name ?: basename($path)) . '"',
        ]);
    }

    /* ============================================================
     | DELETE (SOFT DELETE)
     | ============================================================ */

    public function destroyProject(Request $request, Project $project)
    {
        $user = $request->user();

        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        if (!in_array(strtolower((string)($project->area ?? '')), $regionsScope, true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed (region mismatch).'], 403);
        }

        if (!empty($salesmenScope)) {
            $projSm = strtoupper(trim((string)($project->salesman ?? $project->salesperson ?? '')));
            if ($projSm !== '' && !in_array($projSm, $salesmenScope, true)) {
                return response()->json(['ok' => false, 'message' => 'Not allowed (salesman mismatch).'], 403);
            }
        }

        $project->delete();

        return response()->json(['ok' => true, 'message' => 'Inquiry deleted successfully (soft delete).']);
    }

    public function destroySalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $user = $request->user();

        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $soRegion = strtolower(trim((string)($salesorder->project_region ?? $salesorder->area ?? '')));
        if ($soRegion !== '' && !in_array($soRegion, $regionsScope, true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed (region mismatch).'], 403);
        }

        if (!empty($salesmenScope)) {
            $sm = strtoupper(trim((string)($salesorder->{'Sales Source'} ?? $salesorder->salesman ?? '')));
            if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                return response()->json(['ok' => false, 'message' => 'Not allowed (salesman mismatch).'], 403);
            }
        }

        $poNo  = $salesorder->{'PO. No.'};
        $jobNo = $salesorder->{'Job No.'} ?? null;

        if (!$poNo) {
            return response()->json(['ok' => false, 'message' => 'PO number not found.'], 422);
        }

        $affected = DB::table('salesorderlog')
            ->whereRaw('`PO. No.` = ?', [$poNo])
            ->when($jobNo, function ($q) use ($jobNo) {
                $q->whereRaw('`Job No.` = ?', [$jobNo]);
            })
            ->update(['deleted_at' => now()]);

        return response()->json([
            'ok' => true,
            'message' => "Sales order(s) for PO {$poNo} deleted successfully (soft delete).",
            'count' => $affected,
        ]);
    }

    /* ============================================================
     | RELATED + SEARCH QUOTATIONS (SCOPED)
     | ============================================================ */

    public function relatedQuotations(Request $request)
    {
        $projectId   = (int)$request->input('project_id');
        $quotationNo = trim((string)$request->input('quotation_no'));

        if (!$projectId || !$quotationNo) {
            return response()->json(['ok' => false, 'message' => 'Missing project_id or quotation_no.', 'projects' => []], 400);
        }

        $user         = $request->user();
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $base = Project::extractBaseCode($quotationNo);
        if (!$base) {
            return response()->json(['ok' => false, 'message' => 'Unable to detect base code.', 'projects' => []], 200);
        }

        $q = Project::query()
            ->whereNull('deleted_at')
            ->where('id', '<>', $projectId)
            ->where('quotation_no', 'LIKE', $base . '%');

        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->get([
            'id', 'project_name', 'client_name', 'quotation_no', 'area', 'quotation_value'
        ]);

        return response()->json([
            'ok' => true,
            'base' => $base,
            'projects' => $projects->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'quotation_no'   => $p->quotation_no,
                    'project'        => $p->project_name,
                    'client'         => $p->client_name,
                    'area'           => $p->area,
                    'quotation_value'=> (float)$p->quotation_value,
                ];
            })->values(),
        ]);
    }

    public function searchQuotations(Request $request)
    {
        $term = trim((string)$request->input('term', ''));
        if ($term === '') {
            return response()->json(['ok' => false, 'message' => 'Search term is required.', 'results' => []], 400);
        }

        $user         = $request->user();
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $q = Project::query()
            ->whereNull('deleted_at')
            ->where('quotation_no', 'LIKE', '%' . $term . '%');

        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->limit(20)->get([
            'id', 'project_name', 'client_name', 'quotation_no', 'area', 'quotation_value'
        ]);

        return response()->json([
            'ok' => true,
            'results' => $projects->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'quotation_no'   => $p->quotation_no,
                    'project'        => $p->project_name,
                    'client'         => $p->client_name,
                    'area'           => $p->area,
                    'quotation_value'=> (float)$p->quotation_value,
                ];
            })->values(),
        ]);
    }
}
