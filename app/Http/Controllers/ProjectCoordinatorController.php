<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use App\Models\SalesOrderLog;
use Illuminate\Http\Request;
use App\Models\SalesOrderAttachment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Coordinator\SalesOrdersMonthExport;
use App\Exports\Coordinator\SalesOrdersYearExport;
use Carbon\Carbon;

class ProjectCoordinatorController extends Controller
{
    /* ============================================================
     *  SCOPES
     * ============================================================ */

    private function coordinatorSalesmenScope($user): array
    {
        // GM/Admin (or permission) => all
        if (method_exists($user, 'can') && $user->can('viewAllRegions')) {
            return [];
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('gm|admin')) {
            return [];
        }

        // ✅ Eastern coordinator: ALL salesmen
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_eastern')) {
            return [];
        }

        // ✅ Western coordinator: ONLY ABDO + AHMED (with aliases)
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_western')) {
            return [
                'ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF',
                'AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin'
            ];
        }

        // (Optional) Central coordinator: decide later
        if (method_exists($user, 'hasRole') && $user->hasRole('project_coordinator_central')) {
            // If you want central to be like western, keep restricted.
            // If you want central to be like eastern, return [].
            return ['TARIQ', 'TAREQ', 'JAMAL'];
        }

        return []; // default: no restriction
    }

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

        // Fallback: use user.region if present
        $r = strtolower((string) ($user->region ?? ''));
        if (in_array($r, ['eastern','central','western'], true)) {
            return [$r];
        }

        // safest fallback
        return [];
    }


    /* ============================================================
     *  HELPERS
     * ============================================================ */

    private function normalizeSalesmenScope(array $salesmenScope): array
    {
        return array_values(array_unique(
            array_filter(array_map(fn($v) => strtoupper(trim((string)$v)), $salesmenScope))
        ));
    }

    private function applySalesmenScopeToSalesOrderQuery($query, array $salesmenScope)
    {
        $allowed = $this->normalizeSalesmenScope($salesmenScope);
        if (!empty($allowed)) {
            // salesorderlog column is `Sales Source`
            $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $allowed);
        }
        return $query;
    }

    private function applySalesmenScopeToProjectsQuery($query, array $salesmenScope)
    {
        $allowed = $this->normalizeSalesmenScope($salesmenScope);
        if (!empty($allowed)) {
            // projects might have salesman OR salesperson
            $query->whereIn(DB::raw('UPPER(TRIM(COALESCE(`salesman`,`salesperson`)))'), $allowed);
        }
        return $query;
    }

    private function enforceSalesOrderScopeOr403(Request $request, SalesOrderLog $salesorder): void
    {
        $user = $request->user();
        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        if (!in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
            abort(403, 'Not allowed (region).');
        }

        if (!empty($salesmenScope)) {
            $sm = strtoupper(trim((string)($salesorder->salesman ?? $salesorder->{'Sales Source'} ?? '')));
            if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                abort(403, 'Not allowed (salesman).');
            }
        }
    }

    /* ============================================================
     *  INDEX
     * ============================================================ */

    public function index(Request $request)
    {
        $user         = $request->user();
        $userRegion   = strtolower($user->region ?? '');
        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        // Canonical alias map (canonical keys should match UI)
        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF'],
            'AHMED'  => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin'],
        ];

        $normalizedRegions = array_map(fn ($r) => ucfirst(strtolower($r)), $regionsScope);

        // --------------------------
        // Salesmen dropdown options (scoped)
        // --------------------------
        $projectsSalesmenQ = Project::query()
            ->whereNull('deleted_at')
            ->whereIn('area', $normalizedRegions);
        $this->applySalesmenScopeToProjectsQuery($projectsSalesmenQ, $salesmenScope);

        $salesOrdersSalesmenQ = DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->whereIn('project_region', $normalizedRegions);
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
            foreach ($salesmanAliasMap as $canonical => $aliases) {
                if (in_array($s, $aliases, true)) {
                    $salesmenFilterOptions[$canonical] = $canonical;
                    break;
                }
            }
        }

        if (empty($salesmenFilterOptions)) {
            // if restricted, show only restricted names
            if (!empty($salesmenScope)) {
                $salesmenFilterOptions = $salesmenScope;
            } else {
                $salesmenFilterOptions = array_keys($salesmanAliasMap);
            }
        } else {
            $salesmenFilterOptions = array_values($salesmenFilterOptions);
        }

        // --------------------------
        // Projects + Sales Orders (scoped)
        // --------------------------
        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $projects = (clone $projectsQuery)->orderByDesc('quotation_date')->get();

        $salesOrdersQuery = SalesOrderLog::coordinatorGroupedQuery($regionsScope);
        $this->applySalesmenScopeToSalesOrderQuery($salesOrdersQuery, $salesmenScope);
        $salesOrders = $salesOrdersQuery->orderByDesc('po_date')->get();

        // KPIs (scoped)
        $kpiProjectsCount = (clone $projectsQuery)->count();
        $kpiSalesOrdersCount = $salesOrders->count();
        $kpiSalesOrdersValue = (float) $salesOrders->sum('total_po_value');

        // --------------------------
        // CHART DATA (IMPORTANT FIX)
        // Do NOT reuse grouped query (it joins users => ambiguous "region")
        // Use clean simple aggregate queries with explicit column.
        // --------------------------
        $projectByRegion = DB::table('projects')
            ->whereNull('deleted_at')
            ->whereIn('area', $normalizedRegions)
            ->when(!empty($salesmenScope), function ($q) use ($salesmenScope) {
                $q->whereIn(DB::raw('UPPER(TRIM(COALESCE(`salesman`,`salesperson`)))'), $salesmenScope);
            })
            ->selectRaw('LOWER(area) as region_key, SUM(quotation_value) as total')
            ->groupBy('region_key')
            ->pluck('total', 'region_key')
            ->toArray();

        $poByRegion = DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->whereIn('project_region', $normalizedRegions)
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
            $chartProjects[]   = (float) ($projectByRegion[$r] ?? 0);
            $chartPOs[]        = (float) ($poByRegion[$r] ?? 0);
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

    public function storePo(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'source'        => ['required', 'in:project,salesorder'],
            'record_id'     => ['required', 'integer'],

            // LEFT SIDE
            'project'        => ['nullable', 'string', 'max:255'],
            'client'         => ['nullable', 'string', 'max:255'],
            'salesman'       => ['nullable', 'string', 'max:255'],
            'location'       => ['nullable', 'string', 'max:255'],
            'area'           => ['nullable', 'string', 'max:255'],
            'quotation_no'   => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['nullable', 'date'],
            'date_received'  => ['nullable', 'date'],
            'atai_products'  => ['nullable', 'string', 'max:255'],
            'quotation_value'=> ['nullable', 'numeric', 'min:0'],

            // RIGHT SIDE
            'job_no'        => ['nullable', 'string', 'max:255'],
            'po_no'         => ['required', 'string', 'max:255'],
            'po_date'       => ['required', 'date'],
            'po_value'      => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'remarks'       => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:51200'],
            'oaa'           => ['nullable', 'string', 'max:50'],
        ]);

        try {
            return DB::transaction(function () use ($data, $request, $user) {

                $regionsScope  = $this->coordinatorRegionScope($user);
                $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

                /* ============================
                 * UPDATE EXISTING SALES ORDER
                 * ============================ */
                if ($data['source'] === 'salesorder') {
                    /** @var \App\Models\SalesOrderLog $so */
                    $so = SalesOrderLog::findOrFail($data['record_id']);

                    if (!in_array(strtolower($so->area ?? ''), $regionsScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'Not allowed (region).'], 403);
                    }

                    if (!empty($salesmenScope)) {
                        $sm = strtoupper(trim((string)($so->salesman ?? $so->{'Sales Source'} ?? '')));
                        if ($sm !== '' && !in_array($sm, $salesmenScope, true)) {
                            return response()->json(['ok' => false, 'message' => 'Not allowed (salesman).'], 403);
                        }
                    }

                    $poDate         = Carbon::parse($data['po_date'])->format('Y-m-d');
                    $poValueWithVat = round($data['po_value'] * 1.15, 2);

                    $client      = $data['client']        ?? $so->client;
                    $projectName = $data['project']       ?? $so->project;
                    $salesSource = $data['salesman']      ?? $so->salesman;
                    $location    = $data['location']      ?? $so->location;
                    $area        = $data['area']          ?? $so->area;
                    $quoteNo     = $data['quotation_no']  ?? $so->quotation_no;
                    $products    = $data['atai_products'] ?? $so->atai_products;

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
                            `Status`           = ?,
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
                        $data['po_no'],
                        $data['po_value'],
                        $poValueWithVat,
                        $data['payment_terms'] ?? null,
                        $data['oaa'] ?? null,
                        $data['job_no'] ?? null,
                        $area,
                        $data['remarks'] ?? null,
                        $user->id,
                        $so->id,
                    ]);

                    if ($request->hasFile('attachments')) {
                        $files = $request->file('attachments');
                        if (!is_array($files)) $files = [$files];

                        foreach ($files as $file) {
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
                    }

                    return response()->json(['ok' => true, 'message' => 'PO updated and documents uploaded.']);
                }

                /* ============================
                 * CREATE NEW PO (FROM PROJECT)
                 * ============================ */

                $mainProject = Project::findOrFail($data['record_id']);

                // region + salesman security
                if (!in_array(strtolower($mainProject->area ?? ''), $regionsScope, true)) {
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

                $projects = Project::query()
                    ->whereIn('id', $projectIds)
                    ->get();

                if ($projects->isEmpty()) {
                    return response()->json(['ok' => false, 'message' => 'No projects found.'], 422);
                }

                // ensure all selected are within scope
                foreach ($projects as $p) {
                    if (!in_array(strtolower($p->area ?? ''), $regionsScope, true)) {
                        return response()->json(['ok' => false, 'message' => 'One of selected quotations is out of your region scope.'], 403);
                    }
                    if (!empty($salesmenScope)) {
                        $pSm = strtoupper(trim((string)($p->salesman ?? $p->salesperson ?? '')));
                        if ($pSm !== '' && !in_array($pSm, $salesmenScope, true)) {
                            return response()->json(['ok' => false, 'message' => 'One of selected quotations is out of your salesman scope.'], 403);
                        }
                    }
                }

                if ($projects->contains(fn(Project $p) => !is_null($p->status_current))) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'One or more selected quotations already have a PO. Please check Sales Order Log.',
                    ], 422);
                }

                $totalQuoted = (float) $projects->sum('quotation_value');
                if ($totalQuoted <= 0) $totalQuoted = 0;

                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $files = $request->file('attachments');
                    $attachments = is_array($files) ? $files : [$files];
                }

                foreach ($projects as $project) {
                    $ratio = ($totalQuoted > 0)
                        ? max(0, (float)$project->quotation_value) / $totalQuoted
                        : (1 / max(1, $projects->count()));

                    $poValue        = round($data['po_value'] * $ratio, 2);
                    $poValueWithVat = round($poValue * 1.15, 2);

                    DB::insert("
                        INSERT INTO `salesorderlog`
                        (
                            `Client Name`, `region`, `project_region`, `Location`, `date_rec`,
                            `PO. No.`, `Products`, `Products_raw`, `Quote No.`, `Ref.No.`, `Cur`,
                            `PO Value`, `value_with_vat`, `Payment Terms`,
                            `Project Name`, `Project Location`,
                            `Status`, `Job No.`, `Factory Loc`, `Sales Source`,
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
                        $data['po_date'],
                        $data['po_no'],
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

                    $soId = DB::getPdo()->lastInsertId();

                    $project->status = 'PO-RECEIVED';
                    $project->status_current = 'PO-RECEIVED';
                    $project->coordinator_updated_by_id = $user->id;
                    $project->save();

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

        return response()->json([
            'ok' => true,
            'salesorder' => [
                'id' => $salesorder->id,
                'po_no' => $salesorder->po_no,
                'project' => $salesorder->project,
                'client' => $salesorder->client,
                'salesman' => $salesorder->salesman,
                'area' => $salesorder->area,
                'atai_products' => $salesorder->atai_products,
                'po_date' => optional($salesorder->po_date)->format('Y-m-d'),
                'po_value' => $salesorder->total_po_value ?? $salesorder->po_value,
                'payment_terms' => $salesorder->payment_terms,
                'remarks' => $salesorder->remarks,
                'created_by' => optional($salesorder->creator)->name,
                'created_at' => optional($salesorder->created_at)->format('Y-m-d H:i'),
            ],
            'attachments' => $salesorder->attachments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'original_name' => $a->original_name,
                    'url' => '/storage/' . ltrim($a->path, '/'),
                    'created_at' => optional($a->created_at)->format('Y-m-d H:i'),
                    'size_bytes' => $a->size_bytes,
                ];
            })->values(),
        ]);
    }

    /* ============================================================
     *  EXPORTS
     * ============================================================ */

    public function exportSalesOrders(Request $request): BinaryFileResponse
    {
        $month = (int) $request->input('month');
        if (!$month) abort(400, 'Month is required');

        $year     = (int) ($request->input('year') ?: now()->year);
        $region   = $request->input('region', 'all');
        $salesman = strtoupper(trim((string)$request->input('salesman', 'all')));
        $from     = $request->input('from');
        $to       = $request->input('to');

        $user = $request->user();
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF'],
            'AHMED'  => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'Ahmed Amin'],
        ];

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

        if ($region && strtolower($region) !== 'all') {
            $query->where('project_region', ucfirst(strtolower($region)));
        }

        // ✅ ALWAYS apply coordinator restriction
        if (!empty($salesmenScope)) {
            $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $salesmenScope);
        }

        $query->whereYear('date_rec', $year)->whereMonth('date_rec', $month);

        if ($from) $query->whereDate('date_rec', '>=', $from);
        if ($to)   $query->whereDate('date_rec', '<=', $to);

        if ($salesman !== '' && $salesman !== 'ALL' && $salesman !== 'all') {
            $aliases = $salesmanAliasMap[$salesman] ?? [$salesman];
            $query->whereIn('Sales Source', $aliases);
        }

        $rows = collect($query->orderBy('date_rec')->get());

        $filename = sprintf('sales_orders_%d_%02d.xlsx', $year, $month);

        $regionLabel = (strtolower($region) !== 'all')
            ? ucfirst(strtolower($region)) . ' Region'
            : 'All Regions';

        return Excel::download(
            new SalesOrdersMonthExport($rows, $year, $month, $regionLabel),
            $filename
        );
    }

    public function exportSalesOrdersYear(Request $request): BinaryFileResponse
    {
        $year     = (int) ($request->input('year') ?: now()->year);
        $region   = strtolower((string) $request->input('region', 'all')); // 'all'|'eastern'|'central'|'western'
        $salesman = trim((string) $request->input('salesman', 'all'));     // canonical like SOHAIB/TARIQ/...
        $from     = $request->input('from');
        $to       = $request->input('to');

        $user = $request->user();

        // ✅ Enforced RBAC scopes
        $regionsScope  = $this->coordinatorRegionScope($user);                 // ['eastern','central'] etc
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user)); // aliases allowed

        $filename = sprintf('sales_orders_%d_full_year.xlsx', $year);

        // ✅ IMPORTANT: pass what the Export expects (array regionsScope FIRST)
        return Excel::download(
            new SalesOrdersYearExport(
                $regionsScope,     // ✅ array, not Collection
                $year,
                $region,           // regionKey ('all' or specific)
                $from,
                $to,
                $salesman !== '' ? strtoupper($salesman) : 'all',
                null,              // factory (if you use it later)
                $salesmenScope     // ✅ pass allowed salesmen too (if your export supports it)
            ),
            $filename
        );
    }


    /* ============================================================
     *  ATTACHMENTS LIST
     * ============================================================ */

    public function salesOrderAttachments(SalesOrderLog $salesorder)
    {
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
        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        if (!in_array(strtolower($project->area ?? ''), $regionsScope, true)) {
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
        $user         = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));

        if (!in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed (region mismatch).'], 403);
        }

        if (!empty($salesmenScope)) {
            $sm = strtoupper(trim((string)($salesorder->salesman ?? $salesorder->{'Sales Source'} ?? '')));
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
     *  RELATED + SEARCH QUOTATIONS (SCOPED)
     * ============================================================ */

    public function relatedQuotations(Request $request)
    {
        $projectId   = (int) $request->input('project_id');
        $quotationNo = trim((string) $request->input('quotation_no'));

        if (!$projectId || !$quotationNo) {
            return response()->json(['ok' => false, 'message' => 'Missing project_id or quotation_no.', 'projects' => []], 400);
        }

        $user = $request->user();
        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));
        $normalizedRegions = array_map(fn($r) => ucfirst(strtolower($r)), $regionsScope);

        $base = Project::extractBaseCode($quotationNo);
        if (!$base) {
            return response()->json(['ok' => false, 'message' => 'Unable to detect base code.', 'projects' => []], 200);
        }

        $q = Project::query()
            ->whereNull('deleted_at')
            ->whereIn('area', $normalizedRegions)
            ->where('id', '<>', $projectId)
            ->where('quotation_no', 'LIKE', $base . '%');

        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->get([
            'id','project_name','client_name','quotation_no','area','quotation_value'
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
                    'quotation_value' => (float) $p->quotation_value,
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
        $regionsScope  = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->normalizeSalesmenScope($this->coordinatorSalesmenScope($user));
        $normalizedRegions = array_map(fn($r) => ucfirst(strtolower($r)), $regionsScope);

        $q = Project::query()
            ->whereNull('deleted_at')
            ->whereNull('status')
            ->whereNull('status_current')
            ->whereIn('area', $normalizedRegions)
            ->where('quotation_no', 'LIKE', '%' . $term . '%');

        $this->applySalesmenScopeToProjectsQuery($q, $salesmenScope);

        $projects = $q->orderBy('quotation_no')->limit(20)->get([
            'id','project_name','client_name','quotation_no','area','quotation_value'
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
