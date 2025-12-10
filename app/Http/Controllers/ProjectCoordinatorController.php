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
    private function coordinatorSalesmenScope($user): array
    {
        // TEMP: disable salesman restriction – coordinators see ALL salesmen
        return [];

        /*
        // === OLD LOGIC (KEEP FOR FUTURE USE) ===
        $name = strtolower(trim($user->name ?? ''));

        // GM/Admin → all salesmen
        if (method_exists($user, 'hasRole') && $user->hasRole('gm|admin')) {
            return ['SOHAIB', 'TARIQ', 'JAMAL', 'SOAHIB', 'TAREQ', 'ABDO', 'AHMED'];
        }

        // Shenoy → Eastern coordinator
        if ($name === 'shenoy') {
            return ['SOHAIB', 'TARIQ', 'SOAHIB', 'TAREQ', 'JAMAL'];
        }

        // Niyas → Western coordinator
        if ($name === 'niyas') {
            return ['ABDO', 'AHMED'];
        }

        // default (no restriction, or adjust as you like)
        return [];
        */
    }

    private function coordinatorRegionScope($user): array
    {
        $userRegion = strtolower($user->region ?? '');

        if (method_exists($user, 'can') && $user->can('viewAllRegions')) {
            return ['eastern', 'central', 'western'];
        }

        if ($userRegion === 'western') {
            return ['eastern', 'central', 'western'];
        }

        return ['eastern', 'central', 'western'];
    }

//    /*
//     *  old index fucntion
//     *
//     * */
////    public function index(Request $request)
////    {
////        $user = $request->user();
////        $userRegion = strtolower($user->region ?? '');
////        $regionsScope = $this->coordinatorRegionScope($user);
////        $salesmenScope = $this->coordinatorSalesmenScope($user);
////
////        // Projects (filtered by region + salesman)
////        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
////        $projects = (clone $projectsQuery)
////            ->orderByDesc('quotation_date')
////            ->get();
////
////        // Sales Orders (same logic)
////        $salesOrdersQuery = SalesOrderLog::coordinatorBaseQuery($regionsScope);
////        $salesOrders = (clone $salesOrdersQuery)
////            ->orderByDesc('date_rec')
////            ->get();
////
////        // KPIs
////        $kpiProjectsCount = Project::kpiProjectsCountForCoordinator($regionsScope);
////
////        $salesKpis = SalesOrderLog::kpisForCoordinator($regionsScope);
////        $kpiSalesOrdersCount = $salesKpis['count'];
////        $kpiSalesOrdersValue = $salesKpis['value'];
////
////        // CHART DATA (Quotation vs PO by region)
////        $projectByRegion = Project::quotationTotalsByRegion($regionsScope);
////        $poByRegion = SalesOrderLog::poTotalsByRegion($regionsScope);
////
////        $regions = ['eastern', 'central', 'western'];
////        $chartCategories = [];
////        $chartProjects = [];
////        $chartPOs = [];
////
////        foreach ($regions as $r) {
////            if (!in_array($r, $regionsScope)) {
////                continue;
////            }
////
////            $chartCategories[] = ucfirst($r);
////            $chartProjects[] = (float)($projectByRegion[$r] ?? 0);
////            $chartPOs[] = (float)($poByRegion[$r] ?? 0);
////        }
////
////        return view('coordinator.index', [
////            'userRegion' => $userRegion,
////            'regionsScope' => $regionsScope,
////            'salesmenScope' => $salesmenScope,
////            'kpiProjectsCount' => $kpiProjectsCount,
////            'kpiSalesOrdersCount' => $kpiSalesOrdersCount,
////            'kpiSalesOrdersValue' => $kpiSalesOrdersValue,
////            'projects' => $projects,
////            'salesOrders' => $salesOrders,
////            'chartCategories' => $chartCategories,
////            'chartProjects' => $chartProjects,
////            'chartPOs' => $chartPOs,
////        ]);
////    }



    /*
         *  new  index fucntion
         *  for combine sales order log  groupoing them
         * */


    public function index(Request $request)
    {
        $user         = $request->user();
        $userRegion   = strtolower($user->region ?? '');
        $regionsScope = $this->coordinatorRegionScope($user);

        // For now: no salesman restriction in queries
        $salesmenScope = [];

        // ==========================
        //  Build Salesman filter list
        // ==========================
        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO'],
            'AHMED'  => ['AHMED'],
        ];

        $normalizedRegions = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        // Collect salesman names from Projects + Sales Orders within region scope
        $salesmenRaw = collect()
            ->merge(
                Project::query()
                    ->whereNull('deleted_at')
                    ->whereIn('area', $normalizedRegions)
                    ->pluck('salesperson')
            )
            ->merge(
                Project::query()
                    ->whereNull('deleted_at')
                    ->whereIn('area', $normalizedRegions)
                    ->pluck('salesperson')
            )
            ->merge(
                SalesOrderLog::query()
                    ->whereNull('deleted_at')
                    ->whereIn('region', $normalizedRegions)
                    ->pluck('Sales Source')
            )
            ->filter()
            ->map(fn ($v) => strtoupper(trim($v)))
            ->unique()
            ->values();

        // Map raw names to canonical codes (SOHAIB, TARIQ, …)
        $salesmenFilterOptions = [];

        foreach ($salesmenRaw as $s) {
            foreach ($salesmanAliasMap as $canonical => $aliases) {
                if (in_array($s, $aliases, true)) {
                    $salesmenFilterOptions[$canonical] = $canonical;
                    break;
                }
            }
        }

        // If nothing found in data, fall back to all canonical names
        if (empty($salesmenFilterOptions)) {
            $salesmenFilterOptions = array_keys($salesmanAliasMap);
        } else {
            $salesmenFilterOptions = array_values($salesmenFilterOptions);
        }

        // ==========================
        //  Projects + Sales Orders
        // ==========================
        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $projects      = (clone $projectsQuery)
            ->orderByDesc('quotation_date')
            ->get();

        // Grouped Sales Orders (one row per PO)
        $salesOrders = SalesOrderLog::coordinatorGroupedQuery($regionsScope)
            ->orderByDesc('po_date')
            ->get();

        // KPIs
        $kpiProjectsCount    = Project::kpiProjectsCountForCoordinator($regionsScope);
        $kpiSalesOrdersCount = $salesOrders->count();
        $kpiSalesOrdersValue = (float) $salesOrders->sum('total_po_value');

        // CHART DATA – Quotation vs PO by region
        $projectByRegion = Project::quotationTotalsByRegion($regionsScope);
        $poByRegion      = SalesOrderLog::poTotalsByRegion($regionsScope);

        $regions         = ['eastern', 'central', 'western'];
        $chartCategories = [];
        $chartProjects   = [];
        $chartPOs        = [];

        foreach ($regions as $r) {
            if (!in_array($r, $regionsScope, true)) {
                continue;
            }

            $chartCategories[] = ucfirst($r);
            $chartProjects[]   = (float) ($projectByRegion[$r] ?? 0);
            $chartPOs[]        = (float) ($poByRegion[$r] ?? 0);
        }

        return view('coordinator.index', [
            'userRegion'           => $userRegion,
            'regionsScope'         => $regionsScope,
            'salesmenScope'        => $salesmenScope,          // still used for permissions
            'salesmenFilterOptions'=> $salesmenFilterOptions,  // 👈 for chips + dropdown

            'kpiProjectsCount'     => $kpiProjectsCount,
            'kpiSalesOrdersCount'  => $kpiSalesOrdersCount,
            'kpiSalesOrdersValue'  => $kpiSalesOrdersValue,
            'projects'             => $projects,
            'salesOrders'          => $salesOrders,
            'chartCategories'      => $chartCategories,
            'chartProjects'        => $chartProjects,
            'chartPOs'             => $chartPOs,
        ]);
    }



    public function storePo(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'source'        => ['required', 'in:project,salesorder'],
            'record_id'     => ['required', 'integer'],

            // LEFT-SIDE (inquiry) fields – editable when source = salesorder
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

            // RIGHT-SIDE (PO) fields
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

                /* ============================
                 *  UPDATE EXISTING SALES ORDER
                 * ============================ */
                if ($data['source'] === 'salesorder') {
                    /** @var \App\Models\SalesOrderLog $so */
                    $so = SalesOrderLog::findOrFail($data['record_id']);

                    $regionsScope = $this->coordinatorRegionScope($user);
                    if (!in_array(strtolower($so->area ?? ''), $regionsScope, true)) {
                        return response()->json([
                            'ok'      => false,
                            'message' => 'You are not allowed to update this sales order.',
                        ], 403);
                    }

                    // Always treat date_rec as PO Date (fix for 0005-12-03 bug)
                    $poDate         = \Carbon\Carbon::parse($data['po_date'])->format('Y-m-d');
                    $poValueWithVat = round($data['po_value'] * 1.15, 2);

                    // Use edited values, falling back to existing if needed
                    $client      = $data['client']        ?? $so->client;
                    $projectName = $data['project']       ?? $so->project;
                    $salesSource = $data['salesman']      ?? $so->salesman;
                    $location    = $data['location']      ?? $so->location;
                    $area        = $data['area']          ?? $so->area;
                    $quoteNo     = $data['quotation_no']  ?? $so->quotation_no;
                    $products    = $data['atai_products'] ?? $so->atai_products;
                    // $quotationVal  = $data['quotation_value'] ?? $so->po_value;

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
                        `date_rec`         = ?,   -- now always PO date
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
                        $area,                    // project_region
                        $area,                    // region
                        $products,
                        $products,
                        $quoteNo,
                        $poDate,                  // 👈 fixed here
                        $data['po_no'],
                        $data['po_value'],
                        $poValueWithVat,
                        $data['payment_terms'] ?? null,
                        $data['oaa'] ?? null,
                        $data['job_no'] ?? null,
                        $area,                    // Factory Loc
                        $data['remarks'] ?? null,
                        $user->id,
                        $so->id,
                    ]);

                    // attachments
                    if ($request->hasFile('attachments')) {
                        $files = $request->file('attachments');
                        if (!is_array($files)) {
                            $files = [$files];
                        }

                        foreach ($files as $file) {
                            if (!$file || !$file->isValid()) {
                                continue;
                            }

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

                    return response()->json([
                        'ok'      => true,
                        'message' => 'PO updated and documents uploaded.',
                    ]);
                }

                /* ============================
                 *  CREATE NEW PO (FROM PROJECT)
                 *  Single OR multiple quotations
                 * ============================ */

                $mainProject = Project::findOrFail($data['record_id']);

                // Extra project IDs chosen in modal
                $extraIds   = $request->input('extra_project_ids', []);
                $projectIds = array_unique(
                    array_filter(
                        array_merge([$mainProject->id], (array) $extraIds),
                        fn ($id) => !empty($id)
                    )
                );

                // Load all selected projects
                $projects = Project::query()
                    ->whereIn('id', $projectIds)
                    ->get();

                if ($projects->isEmpty()) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'No projects found to attach this PO to.',
                    ], 422);
                }

                // Check none already have PO
                if ($projects->contains(fn (Project $p) => !is_null($p->status_current))) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'One or more selected quotations already have a PO. Please check Sales Order Log.',
                    ], 422);
                }

                // Total quotation value of selected projects
                $totalQuoted = (float) $projects->sum('quotation_value');
                if ($totalQuoted <= 0) {
                    // Fallback: equal split
                    $totalQuoted = 0;
                }

                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $files       = $request->file('attachments');
                    $attachments = is_array($files) ? $files : [$files];
                }

                foreach ($projects as $project) {

                    // share ratio
                    if ($totalQuoted > 0) {
                        $ratio = max(0, (float) $project->quotation_value) / $totalQuoted;
                    } else {
                        $ratio = 1 / max(1, $projects->count());
                    }

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
                        $data['po_date'], // date_rec (for new rows we keep as po_date)
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

                    // Mark project as PO-RECEIVED
                    $project->status                     = 'PO-RECEIVED';
                    $project->status_current             = 'PO-RECEIVED';
                    $project->coordinator_updated_by_id  = $user->id;
                    $project->save();

                    // Attach documents to each SO
                    foreach ($attachments as $file) {
                        if (!$file || !$file->isValid()) {
                            continue;
                        }

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
                    'ok'      => true,
                    'message' => 'PO saved for selected quotations, projects updated to PO-RECEIVED, and files uploaded.',
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'Error while saving PO: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Return a single sales order + attachments (for View button)
     */
    public function showSalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        // enforce same scope as index: only see allowed region(s)
        if (!in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed'], 403);
        }

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

    /**
     * Export Sales Order Log for selected month (plus filters)
     */
    public function exportSalesOrders(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        $month = (int)$request->input('month'); // 1..12
        if (!$month) {
            abort(400, 'Month is required');
        }

        $year = (int)($request->input('year') ?: now()->year);
        $region = $request->input('region', 'all');
        $from = $request->input('from');
        $to = $request->input('to');
        $salesman = strtoupper(trim($request->input('salesman', '')));

        $normalizedRegions = array_map(
            fn($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ' => ['TARIQ', 'TAREQ'],
            'JAMAL' => ['JAMAL'],
            'ABDO' => ['ABDO'],
            'AHMED' => ['AHMED'],
        ];

        $query = DB::table('salesorderlog')
            ->whereNull('deleted_at')
            ->whereIn('region', $normalizedRegions)
            ->selectRaw("
                `Client Name`      AS client,
                `project_region`   AS area,
                `Location`         AS location,
                `date_rec`         AS date_rec,
                `PO. No.`          AS po_no,
                `Products`         AS atai_products,
                `Products_raw`     AS products_raw,
                `Quote No.`        AS quotation_no,
                `Ref.No.`          AS ref_no,
                `Cur`              AS cur,
                `PO Value`         AS po_value,
                `value_with_vat`   AS value_with_vat,
                `Payment Terms`    AS payment_terms,
                `Project Name`     AS project,
                `Project Location` AS project_location,
                `Status`           AS status,
                `Sales OAA`        AS oaa,
                `Job No.`          AS job_no,
                `Factory Loc`      AS factory_loc,
                `Sales Source`     AS salesman,
                `Remarks`          AS remarks
            ");

        if ($region && strtolower($region) !== 'all') {
            $query->where('project_region', ucfirst(strtolower($region)));
        }

        if ($salesman && $salesman !== 'ALL') {
            $aliases = $salesmanAliasMap[$salesman] ?? [$salesman];
            $query->whereIn('Sales Source', $aliases);
        }

        $query->whereYear('date_rec', $year)
            ->whereMonth('date_rec', $month);

        if ($from) {
            $query->whereDate('date_rec', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_rec', '<=', $to);
        }

        $rows = collect($query->orderBy('date_rec')->get());

        $filename = sprintf('sales_orders_%d_%02d.xlsx', $year, $month);
        $regionLabel = ($region && strtolower($region) !== 'all')
            ? ucfirst(strtolower($region)) . ' Region'
            : 'All Regions';

        return Excel::download(
            new SalesOrdersMonthExport($rows, $year, $month, $regionLabel),
            $filename
        );
    }

    public function exportSalesOrdersYear(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        $year = (int)($request->input('year') ?: now()->year);
        $region = $request->input('region', 'all');
        $from = $request->input('from');
        $to = $request->input('to');
        $salesman = strtoupper(trim($request->input('salesman', '')));

        $export = new SalesOrdersYearExport($regionsScope, $year, $region, $from, $to, $salesman);
        $filename = sprintf('sales_orders_%d_full_year.xlsx', $year);

        return Excel::download($export, $filename);
    }

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

        return response()->json([
            'ok' => true,
            'attachments' => $items,
        ]);
    }

    /* ======================================================================
     *  DELETE (SOFT DELETE) – Inquiries & Sales Orders
     * =================================================================== */

    public function destroyProject(Request $request, Project $project)
    {
        $user = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);
        $salesmenScope = $this->coordinatorSalesmenScope($user);

        // region check
        if (!in_array(strtolower($project->area ?? ''), $regionsScope, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'You are not allowed to delete this inquiry (region mismatch).',
            ], 403);
        }

        // salesman check (only if scope list is not empty)
        if (!empty($salesmenScope)) {
            $allowed = array_map('strtoupper', $salesmenScope);
            $projSm = strtoupper(trim($project->salesman ?? $project->salesperson ?? ''));
            if ($projSm && !in_array($projSm, $allowed, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'You are not allowed to delete this inquiry (salesman mismatch).',
                ], 403);
            }
        }

        $project->delete(); // Soft delete

        return response()->json([
            'ok' => true,
            'message' => 'Inquiry deleted successfully (soft delete).',
        ]);
    }

//    public function destroySalesOrder(Request $request, SalesOrderLog $salesorder)
//    {
//        $user = $request->user();
//        $regionsScope = $this->coordinatorRegionScope($user);
//
//        if (!in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
//            return response()->json([
//                'ok' => false,
//                'message' => 'You are not allowed to delete this sales order (region mismatch).',
//            ], 403);
//        }
//
//        $salesorder->delete(); // soft delete
//
//        return response()->json([
//            'ok' => true,
//            'message' => 'Sales order deleted successfully (soft delete).',
//        ]);
//    }

    public function destroySalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $user         = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        if (!in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'You are not allowed to delete this sales order (region mismatch).',
            ], 403);
        }

        // Get PO No. and Job No. from this record
        $poNo  = $salesorder->{'PO. No.'};
        $jobNo = $salesorder->{'Job No.'} ?? null;

        if (!$poNo) {
            return response()->json([
                'ok'      => false,
                'message' => 'PO number not found for this record.',
            ], 422);
        }

        // Soft-delete ALL rows for the same PO (and Job, if present)
        $affected = DB::table('salesorderlog')
            ->whereRaw('`PO. No.` = ?', [$poNo])
            ->when($jobNo, function ($q) use ($jobNo) {
                $q->whereRaw('`Job No.` = ?', [$jobNo]);
            })
            ->update([
                'deleted_at' => now(),
            ]);

        return response()->json([
            'ok'      => true,
            'message' => "Sales order(s) for PO {$poNo} deleted successfully (soft delete).",
            'count'   => $affected,
        ]);
    }

    /**
     * Return list of related quotations with the same base project code.
     * Used by coordinator modal when ticking "Multiple quotations".
     */
    public function relatedQuotations(Request $request)
    {
        $projectId = (int)$request->input('project_id');
        $quotationNo = trim((string)$request->input('quotation_no'));

        if (!$projectId || !$quotationNo) {
            return response()->json([
                'ok' => false,
                'message' => 'Missing project_id or quotation_no.',
                'projects' => [],
            ], 400);
        }

        $base = Project::extractBaseCode($quotationNo);
        if (!$base) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to detect base code.',
                'projects' => [],
            ], 200);
        }

        $projects = Project::query()
            ->whereNull('deleted_at')
            ->where('id', '<>', $projectId)
            ->where('quotation_no', 'LIKE', $base . '%')
            ->orderBy('quotation_no')
            ->get([
                'id',
                'project_name',
                'client_name',
                'quotation_no',
                'area',
                'quotation_value',
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

    /**
     * Search projects by quotation number (for multi-quotation PO picker).
     */
    public function searchQuotations(Request $request)
    {
        $term = trim((string)$request->input('term', ''));
        $user = $request->user();

        if ($term === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Search term is required.',
                'results' => [],
            ], 400);
        }

        // Respect coordinator region scope
        $regionsScope = $this->coordinatorRegionScope($user);
        $normalizedRegions = array_map(
            fn($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        $projects = Project::query()
            ->whereNull('deleted_at')
            // only fresh inquiries (no PO yet) – you asked for this
            ->whereNull('status')
            ->whereNull('status_current')
            ->whereIn('area', $normalizedRegions)
            ->where('quotation_no', 'LIKE', '%' . $term . '%')
            ->orderBy('quotation_no')
            ->limit(20)
            ->get([
                'id',
                'project_name',
                'client_name',
                'quotation_no',
                'area',
                'quotation_value',
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
