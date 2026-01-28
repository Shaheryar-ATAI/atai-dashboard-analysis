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
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectCoordinatorController extends Controller
{
    /* ============================================================
     *  SCOPES (RBAC)
     * ============================================================
     *
     * Goal:
     * - Keep your current working behavior unchanged.
     * - Make RBAC + UI filters consistent and enforced in index/show/delete/export.
     * - Fix the common issue: grouped SalesOrder queries not selecting columns like
     *   Payment Terms / Sales OAA / Remarks (so modal shows blank).
     */

    /**
     * Returns a list of allowed salesman aliases for the logged-in user.
     * Empty array means "no restriction" (GM/Admin/Eastern coordinator).
     */
    private function coordinatorSalesmenScope($user): array
    {
        // GM/Admin (or permission) => all
        if ((method_exists($user, 'can') && $user->can('viewAllRegions')) ||
            (method_exists($user, 'hasRole') && $user->hasRole('gm|admin'))) {
            return [];
        }

        // Eastern coordinator: ALL salesmen (no restriction)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_eastern')) {
            return [];
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
                'M.ABU MERHI', 'M. ABU MERHI', 'M.MERHI', 'MERHI', 'ABU MERHI', 'M ABU MERHI', 'MOHAMMED',
            ];
        }

        return [];
    }

    /**
     * Returns allowed region keys for the logged-in user.
     * NOTE: As per your rule, project coordinators can see ALL regions.
     */
    private function coordinatorRegionScope($user): array
    {
        // GM/Admin or permission => all
        if ((method_exists($user, 'can') && $user->can('viewAllRegions')) ||
            (method_exists($user, 'hasRole') && $user->hasRole('gm|admin'))) {
            return ['eastern', 'central', 'western'];
        }

        // ✅ Project coordinators: ALL REGIONS (per your rule)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western|project_coordinator_eastern|project_coordinator_central')) {
            return ['eastern', 'central', 'western'];
        }

        // fallback: use user.region if present
        $r = strtolower((string)($user->region ?? ''));
        if (in_array($r, ['eastern', 'central', 'western'], true)) {
            return [$r];
        }

        return [];
    }

    /* ============================================================
     *  HELPERS
     * ============================================================ */

    private function selectedSalesTeamRegion(Request $request): string
    {
        $r = strtolower(trim((string)$request->input('region', 'all')));
        return in_array($r, ['eastern', 'central', 'western', 'all'], true) ? $r : 'all';
    }

    /**
     * Canonical => accepted aliases (UPPERCASE preferred)
     */
    private function salesmanAliasMap(): array
    {
        return [
            'SOHAIB' => ['SOHAIB', 'SOAHIB', 'SOAIB', 'SOHIB'],
            'TAREQ' => ['TARIQ', 'TAREQ', 'TAREQ '],
            'JAMAL' => ['JAMAL'],
            'ABU_MERHI' => ['M.ABU MERHI', 'M. ABU MERHI', 'M.MERHI', 'MERHI', 'ABU MERHI', 'M ABU MERHI', 'MOHAMMED'],
            'ABDO' => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF', 'ABDO YOUSIF'],
            'AHMED' => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'AHMED AMIN ', 'AHMED AMIN.', 'AHMED AMEEN.', 'Ahmed Amin'],
        ];
    }

    private function regionSalesmenCanonicalMap(): array
    {
        return [
            'eastern' => ['SOHAIB', 'RAVINDER', 'WASEEM', 'FAISAL', 'CLIENT', 'EXPORT'],
            'central' => ['TAREQ', 'JAMAL'],
            'western' => ['ABDO', 'AHMED'],
        ];
    }

    private function normalizeSalesmenScope(array $salesmenScope): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($v) => strtoupper(trim((string)$v)), $salesmenScope))
        ));
    }

    private function salesmenForRegionSelection(string $regionKey): array
    {
        if ($regionKey === 'all') return [];

        $regionMap = $this->regionSalesmenCanonicalMap();
        $aliasMap = $this->salesmanAliasMap();

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
     * Combine RBAC salesman scope + UI region selection:
     * - If UI region selected -> apply that list
     * - If RBAC list exists too -> INTERSECT
     * - If UI = all -> RBAC only
     */
    private function buildEffectiveSalesmenScope(Request $request, array $rbacSalesmenScope): array
    {
        $rbac = $this->normalizeSalesmenScope($rbacSalesmenScope);

        $regionKey = $this->selectedSalesTeamRegion($request);
        $byRegion = $this->salesmenForRegionSelection($regionKey); // [] if all

        if (!empty($byRegion)) {
            if (!empty($rbac)) {
                $set = array_flip($rbac);
                return array_values(array_filter($byRegion, fn($x) => isset($set[$x])));
            }
            return $byRegion;
        }

        return $rbac;
    }

    /**
     * Apply salesmen scope to salesorderlog query (Sales Source column).
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
     * Apply salesmen scope to projects query (salesman/salesperson columns).
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
     * Hard enforcement for single sales order record access.
     */
    private function enforceSalesOrderScopeOr403(Request $request, SalesOrderLog $salesorder): void
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $soRegion = strtolower(trim((string)($salesorder->project_region ?? $salesorder->area ?? '')));
        if ($soRegion !== '' && !in_array($soRegion, $regionsScope, true)) {
            abort(403, 'Not allowed (region).');
        }

        if (!empty($salesmenScope)) {
            $sm = strtoupper(trim((string)($salesorder->{'Sales Source'} ?? $salesorder->salesman ?? '')));
            if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                abort(403, 'Not allowed (salesman).');
            }
        }
    }

    /**
     * Prevent duplicate PO numbers (case/space-insensitive trimming).
     * If $ignoreId provided, it is excluded (used while updating existing SO).
     */
    private function poNumberExists(string $poNo, ?int $ignoreId = null): bool
    {
        $poNo = trim($poNo);
        if ($poNo === '') return false;

        // Case-insensitive + trim compare (safer for real data)
        return DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->when($ignoreId, fn($q) => $q->where('id', '<>', $ignoreId))
            ->whereRaw('UPPER(TRIM(`PO. No.`)) = UPPER(?)', [$poNo])
            ->exists();
    }

    /**
     * Fix for the "missing fields in Sales Orders list" problem:
     * - Some model "grouped query" methods do not select spaced columns like `Payment Terms`, `Sales OAA`, `Remarks`.
     * - We keep your working query first, then add those columns.
     * - If it still fails due to subquery/grouping, we fallback to a robust DB query with explicit aliases.
     */
    private function fetchSalesOrdersForIndex(array $regionsScope, array $salesmenScope)
    {
        // 1) Keep your existing working model query
        $q = SalesOrderLog::coordinatorGroupedQuery($regionsScope);
        $this->applySalesmenScopeToSalesOrderQuery($q, $salesmenScope);

        // Try to add missing columns (works when base table is salesorderlog)
        $q->addSelect([
            DB::raw("`salesorderlog`.`Payment Terms` AS payment_terms"),
            DB::raw("`salesorderlog`.`Sales OAA`     AS oaa"),
            DB::raw("`salesorderlog`.`Remarks`       AS remarks"),
            DB::raw("`salesorderlog`.`Status`        AS status"),
            DB::raw("`salesorderlog`.`Job No.`       AS job_no"),
        ]);

        try {
            return $q->orderByDesc('po_date')->get();
        } catch (\Throwable $e) {
            // 2) Fallback (very robust): explicit DB query with clean aliases
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
                    created_by_id,
                    created_at
                ");

            if (!empty($salesmenScope)) {
                $fallback->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
            }
            if (!empty($regionsScope)) {
                $fallback->whereIn(DB::raw('LOWER(TRIM(project_region))'), $regionsScope);
            }

            return $fallback->orderByDesc('po_date')->get();
        }
    }

    /* ============================================================
     *  INDEX
     * ============================================================ */

    public function index(Request $request)
    {
        $user = $request->user();
        $userRegion = strtolower((string)($user->region ?? ''));
        $regionsScope = $this->coordinatorRegionScope($user);

        // Effective salesmen scope = RBAC + UI region selection (sales team filter)
        $rbacSalesmenScope = $this->coordinatorSalesmenScope($user);
        $salesmenScope = $this->buildEffectiveSalesmenScope($request, $rbacSalesmenScope);

        // NOTE: This alias map here is only for dropdown canonicalization.
        // Keep minimal canonical keys (UI shows canonicals).
        $dropdownCanonicalMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ' => ['TARIQ', 'TAREQ'],
            'JAMAL' => ['JAMAL'],
            'ABDO' => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF'],
            'AHMED' => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin'],
        ];

        // --------------------------
        // Salesmen dropdown options (scoped)
        // --------------------------
        $projectsSalesmenQ = Project::query()->whereNull('deleted_at');
        $this->applySalesmenScopeToProjectsQuery($projectsSalesmenQ, $salesmenScope);

        $salesOrdersSalesmenQ = DB::table('salesorderlog')->whereNull('deleted_at');
        $this->applySalesmenScopeToSalesOrderQuery($salesOrdersSalesmenQ, $salesmenScope);

        $salesmenRaw = collect()
            ->merge($projectsSalesmenQ->pluck('salesperson'))
            ->merge($projectsSalesmenQ->pluck('salesman'))
            ->merge($salesOrdersSalesmenQ->pluck('Sales Source'))
            ->filter()
            ->map(fn($v) => strtoupper(trim((string)$v)))
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

        // --------------------------
        // Projects + Sales Orders (scoped)
        // --------------------------
        // NOTE: You intentionally commented-out status/status_current restrictions in model/controller.
        // We keep your working behavior. The model query decides what shows.
        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $projects = (clone $projectsQuery)->orderByDesc('quotation_date')->get();

        // ✅ Professional fix: ensure required columns are available for modals and table
        $salesOrders = $this->fetchSalesOrdersForIndex($regionsScope, $salesmenScope);

        // KPIs (scoped)
        $kpiProjectsCount = (clone $projectsQuery)->count();
        $kpiSalesOrdersCount = $salesOrders->count();
        $kpiSalesOrdersValue = (float)$salesOrders->sum('total_po_value');

        // --------------------------
        // Chart Data (clean aggregates)
        // --------------------------
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
            ->when(!empty($salesmenScope), function ($q) use ($salesmenScope) {
                $q->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
            })
            ->selectRaw('LOWER(project_region) as region_key, SUM(`PO Value`) as total')
            ->groupBy('region_key')
            ->pluck('total', 'region_key')
            ->toArray();

        $regions = ['eastern', 'central', 'western'];
        $chartCategories = [];
        $chartProjects = [];
        $chartPOs = [];

        foreach ($regions as $r) {
            if (!in_array($r, $regionsScope, true)) continue;
            $chartCategories[] = ucfirst($r);
            $chartProjects[] = (float)($projectByRegion[$r] ?? 0);
            $chartPOs[] = (float)($poByRegion[$r] ?? 0);
        }

        return view('coordinator.index', [
            'userRegion' => $userRegion,
            'regionsScope' => $regionsScope,
            'salesmenScope' => $salesmenScope,
            'salesmenFilterOptions' => $salesmenFilterOptions,

            'kpiProjectsCount' => $kpiProjectsCount,
            'kpiSalesOrdersCount' => $kpiSalesOrdersCount,
            'kpiSalesOrdersValue' => $kpiSalesOrdersValue,

            'projects' => $projects,
            'salesOrders' => $salesOrders,

            'chartCategories' => $chartCategories,
            'chartProjects' => $chartProjects,
            'chartPOs' => $chartPOs,
        ]);
    }

    /* ============================================================
     *  STORE PO
     * ============================================================ */
    private function syncPoBreakdownRow(
        int $salesorderlogId,
        ?string $family,
        ?string $subtype,
        ?float $subtypeAmount,
        float $defaultAmount,
        ?string $quotationNo = null
    ): void {
        $family  = strtoupper(trim((string)$family));
        $subtype = trim((string)$subtype);

        // ✅ RULE: if no subtype => no breakdown row should exist
        if ($subtype === '') {
            DB::table('salesorderlog_product_breakdowns')
                ->where('salesorderlog_id', $salesorderlogId)
                ->delete();
            return;
        }

        if ($family === '') $family = 'UNKNOWN';

        // ✅ if amount blank => assume full PO value belongs to selected subtype
        $amount = ($subtypeAmount === null) ? $defaultAmount : (float)$subtypeAmount;

        if ($amount < 0) $amount = 0;

        // optional: clamp so user doesn't enter more than PO row value
        if ($amount > $defaultAmount) $amount = $defaultAmount;

        // enforce one row per salesorderlog_id (simple + safe)
        DB::table('salesorderlog_product_breakdowns')
            ->where('salesorderlog_id', $salesorderlogId)
            ->delete();

        DB::table('salesorderlog_product_breakdowns')->insert([
            'salesorderlog_id' => $salesorderlogId,
            'family'           => $family,
            'subtype'          => $subtype,
            'amount'           => $amount,
            'quotation_no'     => $quotationNo,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }


    public function storePo(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'source' => ['required', 'in:project,salesorder'],
            'record_id' => ['required', 'integer'],

            // LEFT SIDE (optional informational fields)
            'project' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'salesman' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'quotation_no' => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['nullable', 'date'],
            'date_received' => ['nullable', 'date'],
            'atai_products' => ['nullable', 'string', 'max:255'],
            'quotation_value' => ['nullable', 'numeric', 'min:0'],

            // RIGHT SIDE
            'job_no' => ['nullable', 'string', 'max:255'],
            'po_no' => ['required', 'string', 'max:255'],
            'po_date' => ['required', 'date'],
            'po_value' => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:51200'],
            'oaa' => ['nullable', 'string', 'max:50'],

            // Niyas-only subtype capture (optional)
            'atai_products_family' => ['nullable', 'string', 'max:50'],
            'products_subtype' => ['nullable', 'string', 'max:255'],
            'products_subtype_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // ✅ Detect Niyas (keep your rule)
        $isNiyas = $user && (
                strtolower(trim((string)$user->name)) === 'niyas'
                || $user->hasRole('project_coordinator_western')
            );

        // ✅ If not Niyas, ignore subtype inputs completely
        if (!$isNiyas) {
            $data['atai_products_family'] = null;
            $data['products_subtype'] = null;
            $data['products_subtype_amount'] = null;
        }

        // ✅ FIX #1: Normalize subtype EARLY (before syncing)
        if (isset($data['products_subtype'])) {
            $data['products_subtype'] = trim((string)$data['products_subtype']);
            if ($data['products_subtype'] === '') {
                $data['products_subtype'] = null;
            }
        }

        try {
            return DB::transaction(function () use ($data, $request, $user, $isNiyas) {

                $regionsScope  = $this->coordinatorRegionScope($user);
                $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

                $poNo = trim((string)$data['po_no']);
                if ($poNo === '') {
                    return response()->json(['ok' => false, 'message' => 'PO number is required.'], 422);
                }

                /* ============================
                 * UPDATE EXISTING SALES ORDER
                 * ============================ */
                if ($data['source'] === 'salesorder') {

                    /** @var \App\Models\SalesOrderLog $so */
                    $so = SalesOrderLog::findOrFail($data['record_id']);

                    // Block duplicate PO No (except current)
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

                    $poDate        = Carbon::parse($data['po_date'])->format('Y-m-d');
                    $poValueWithVat = round(((float)$data['po_value']) * 1.15, 2);

                    // Prefer request fields if provided
                    $client      = $data['client'] ?? $so->client;
                    $projectName = $data['project'] ?? $so->project;
                    $salesSource = $data['salesman'] ?? $so->salesman;
                    $location    = $data['location'] ?? $so->location;

                    $area     = $data['area'] ?? ($so->project_region ?? $so->area);
                    $quoteNo  = $data['quotation_no'] ?? $so->quotation_no;
                    $products = $data['atai_products'] ?? $so->atai_products;

                    // Raw SQL update (keep working approach for dotted columns)
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
                        $data['oaa'] ?? null,
                        $data['job_no'] ?? null,
                        $area,
                        $data['remarks'] ?? null,
                        $user->id,
                        $so->id,
                    ]);

                    // ✅ Sub-product breakdown sync (ONLY if Niyas, else it will delete if subtype null)
                    // ✅ Sub-product breakdown sync (Niyas only)
                    $this->syncPoBreakdownRow(
                        (int)$so->id,
                        $data['atai_products_family'] ?? null,
                        $data['products_subtype'] ?? null,
                        array_key_exists('products_subtype_amount', $data) && $data['products_subtype_amount'] !== null
                            ? (float)$data['products_subtype_amount']
                            : null,
                        (float)$data['po_value'], // full PO value for this row
                        $data['quotation_no'] ?? ($so->quotation_no ?? null)
                    );
                    // Attachments
                    if ($request->hasFile('attachments')) {
                        $files = $request->file('attachments');
                        if (!is_array($files)) $files = [$files];

                        foreach ($files as $file) {
                            if (!$file || !$file->isValid()) continue;

                            $path = $file->store("salesorders/{$so->id}", 'public');

                            SalesOrderAttachment::create([
                                'salesorderlog_id' => $so->id,
                                'disk' => 'public',
                                'path' => $path,
                                'original_name' => $file->getClientOriginalName(),
                                'size_bytes' => $file->getSize(),
                                'mime_type' => $file->getClientMimeType(),
                                'uploaded_by' => $user->id,
                            ]);
                        }
                    }

                    return response()->json(['ok' => true, 'message' => 'PO updated successfully.']);
                }

                /* ============================
                 * CREATE NEW PO (FROM PROJECTS)
                 * ============================ */

                // Block duplicate PO No (global)
                if ($this->poNumberExists($poNo)) {
                    return response()->json([
                        'ok' => false,
                        'message' => "This PO number already exists in Sales Order Log.",
                    ], 422);
                }

                $mainProject = Project::findOrFail($data['record_id']);

                // Security: region + salesman
                if (!in_array(strtolower((string)($mainProject->area ?? '')), $regionsScope, true)) {
                    return response()->json(['ok' => false, 'message' => 'Not allowed (region).'], 403);
                }
                if (!empty($salesmenScope)) {
                    $pSm = strtoupper(trim((string)($mainProject->salesman ?? $mainProject->salesperson ?? '')));
                    if ($pSm !== '' && !in_array($pSm, $salesmenScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'Not allowed (salesman).'], 403);
                    }
                }

                $extraIds   = $request->input('extra_project_ids', []);
                $projectIds = array_unique(array_filter(array_merge([$mainProject->id], (array)$extraIds)));

                $projects = Project::query()->whereIn('id', $projectIds)->get();

                if ($projects->isEmpty()) {
                    return response()->json(['ok' => false, 'message' => 'No projects found.'], 422);
                }

                // Ensure all selected are within scope
                foreach ($projects as $p) {
                    if (!in_array(strtolower((string)($p->area ?? '')), $regionsScope, true)) {
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

                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $files = $request->file('attachments');
                    $attachments = is_array($files) ? $files : [$files];
                }

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
                        `Remarks`, `created_by_id`, `created_at`
                    )
                    VALUES (?,?,?,?,?,
                            ?,?,?,?,?,
                            ?,?,?,?,
                            ?,?,
                            ?,?,?,?,
                            ?,?,NOW())
                ", [
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
                        $data['oaa'] ?? null,
                        $data['job_no'] ?? null,
                        $project->area,
                        $project->salesman ?? $project->salesperson,
                        $data['remarks'] ?? null,
                        $user->id,
                    ]);

                    $soId = (int)DB::getPdo()->lastInsertId();

                    // ✅ FIX #2: breakdown sync for CREATE branch (ratio amount)
                    $this->syncPoBreakdownRow(
                        (int)$soId,
                        $data['atai_products_family'] ?? null,
                        $data['products_subtype'] ?? null,

                        // if user entered subtype amount for whole PO, split it by ratio per row
                        array_key_exists('products_subtype_amount', $data) && $data['products_subtype_amount'] !== null
                            ? round(((float)$data['products_subtype_amount']) * $ratio, 2)
                            : null,

                        (float)$poValue,                 // this row's PO value
                        $project->quotation_no ?? null
                    );

                    // Status updates (keep behavior)
                    $project->status = 'PO-RECEIVED';
                    $project->status_current = 'PO-RECEIVED';
                    $project->coordinator_updated_by_id = $user->id;
                    $project->save();

                    // Attachments
                    foreach ($attachments as $file) {
                        if (!$file || !$file->isValid()) continue;

                        $path = $file->store("salesorders/{$soId}", 'public');

                        SalesOrderAttachment::create([
                            'salesorderlog_id' => $soId,
                            'disk' => 'public',
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'size_bytes' => $file->getSize(),
                            'mime_type' => $file->getClientMimeType(),
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }

                return response()->json([
                    'ok' => true,
                    'message' => 'PO saved for selected quotations, projects updated to PO-RECEIVED, and files uploaded.',
                ]);
            });
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Error while saving PO: ' . $e->getMessage()], 500);
        }
    }


    /* ============================================================
     *  SHOW SALES ORDER
     * ============================================================ */

    public function showSalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $this->enforceSalesOrderScopeOr403($request, $salesorder);

        $salesorder->load('attachments', 'creator');

        // ✅ fetch breakdown row (if any)
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

            // ✅ include breakdown so frontend can auto-fill modal
            'breakdown' => $breakdown ? [
                'family'   => $breakdown->family,
                'subtype'  => $breakdown->subtype,
                'amount'   => (float)$breakdown->amount,
            ] : null,

            'attachments' => $salesorder->attachments->map(function ($a) {
                return [
                    'id'            => $a->id,
                    'original_name' => $a->original_name,
                    'url'           => '/storage/' . ltrim($a->path, '/'),
                    'created_at'    => optional($a->created_at)->format('Y-m-d H:i'),
                    'size_bytes'    => $a->size_bytes,
                ];
            })->values(),
        ]);
    }


    /* ============================================================
     *  EXPORTS
     * ============================================================ */

    public function exportSalesOrders(Request $request): BinaryFileResponse
    {
        $month = (int)$request->input('month');
        if (!$month) abort(400, 'Month is required');

        $year = (int)($request->input('year') ?: now()->year);
        $from = $request->input('from');
        $to = $request->input('to');

        $user = $request->user();

        // Use effective scope (RBAC + UI region filter for sales team)
        $salesmenScope = $this->buildEffectiveSalesmenScope($request, $this->coordinatorSalesmenScope($user));

        $query = DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->selectRaw("
                `Client Name`      AS client,
                `project_region`   AS area,
                `Location`         AS location,
                `date_rec`         AS date_rec,
                `PO. No.`          AS po_no,
                `Products`         AS atai_products,
                `Quote No.`        AS quotation_no,
                `PO Value`         AS po_value,
                `value_with_vat`   AS value_with_vat,
                `Payment Terms`    AS payment_terms,
                `Project Name`     AS project,
                `Project Location` AS project_location,
                `Status`           AS status,
                `Job No.`          AS job_no,
                `Sales Source`     AS salesman,
                `Remarks`          AS remarks
            ");

        // Apply enforced salesman scope once (no duplicates)
        if (!empty($salesmenScope)) {
            $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
        }

        $query->whereYear('date_rec', $year)->whereMonth('date_rec', $month);

        if ($from) $query->whereDate('date_rec', '>=', $from);
        if ($to) $query->whereDate('date_rec', '<=', $to);

        $rows = collect($query->orderBy('date_rec')->get());

        $filename = sprintf('sales_orders_%d_%02d.xlsx', $year, $month);

        return Excel::download(
            new SalesOrdersMonthExport($rows, $year, $month),
            $filename
        );
    }

    public function exportSalesOrdersYear(Request $request): BinaryFileResponse
    {
        $year = (int)($request->input('year') ?: now()->year);
        $region = strtolower((string)$request->input('region', 'all')); // 'all'|'eastern'|'central'|'western'
        $salesman = trim((string)$request->input('salesman', 'all'));
        $from = $request->input('from');
        $to = $request->input('to');

        $user = $request->user();

        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $filename = sprintf('sales_orders_%d_full_year.xlsx', $year);

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

    /* ============================================================
     *  ATTACHMENTS LIST
     * ============================================================ */

    public function salesOrderAttachments(Request $request, SalesOrderLog $salesorder)
    {
        $this->enforceSalesOrderScopeOr403($request, $salesorder);

        $salesorder->load('attachments');

        $items = $salesorder->attachments->map(function ($a) {
            return [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'url' => Storage::disk($a->disk)->url($a->path),
                'created_at' => optional($a->created_at)->format('Y-m-d H:i'),
                'size_bytes' => $a->size_bytes,
            ];
        })->values();

        return response()->json(['ok' => true, 'attachments' => $items]);
    }

    /* ============================================================
     *  DELETE (SOFT DELETE)
     * ============================================================ */

    public function destroyProject(Request $request, Project $project)
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);
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
        $regionsScope = $this->coordinatorRegionScope($user);
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

        $poNo = $salesorder->{'PO. No.'};
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
     *  RELATED + SEARCH QUOTATIONS (SCOPED)
     * ============================================================ */

    public function relatedQuotations(Request $request)
    {
        $projectId = (int)$request->input('project_id');
        $quotationNo = trim((string)$request->input('quotation_no'));

        if (!$projectId || !$quotationNo) {
            return response()->json(['ok' => false, 'message' => 'Missing project_id or quotation_no.', 'projects' => []], 400);
        }

        $user = $request->user();
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $base = Project::extractBaseCode($quotationNo);
        if (!$base) {
            return response()->json(['ok' => false, 'message' => 'Unable to detect base code.', 'projects' => []], 200);
        }

        $q = Project::query()
            ->whereNull('deleted_at')
            ->where('id', '<>', $projectId)
            ->where('quotation_no', 'LIKE', $base . '%');

        // salesman restriction
        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->get([
            'id', 'project_name', 'client_name', 'quotation_no', 'area', 'quotation_value'
        ]);

        return response()->json([
            'ok' => true,
            'base' => $base,
            'projects' => $projects->map(function ($p) {
                return [
                    'id' => $p->id,
                    'quotation_no' => $p->quotation_no,
                    'project' => $p->project_name,
                    'client' => $p->client_name,
                    'area' => $p->area,
                    'quotation_value' => (float)$p->quotation_value,
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

        $user = $request->user();
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $q = Project::query()
            ->whereNull('deleted_at')
            // You intentionally commented these to show all records (keep behavior)
            // ->whereNull('status')
            // ->whereNull('status_current')
            ->where('quotation_no', 'LIKE', '%' . $term . '%');

        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->limit(20)->get([
            'id', 'project_name', 'client_name', 'quotation_no', 'area', 'quotation_value'
        ]);

        return response()->json([
            'ok' => true,
            'results' => $projects->map(function ($p) {
                return [
                    'id' => $p->id,
                    'quotation_no' => $p->quotation_no,
                    'project' => $p->project_name,
                    'client' => $p->client_name,
                    'area' => $p->area,
                    'quotation_value' => (float)$p->quotation_value,
                ];
            })->values(),
        ]);
    }
}
