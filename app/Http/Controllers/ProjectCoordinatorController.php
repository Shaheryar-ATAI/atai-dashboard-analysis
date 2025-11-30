<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use App\Models\SalesOrderLog;
use Illuminate\Http\Request;
use App\Models\SalesOrderAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Exports\Coordinator\SalesOrdersMonthExport;
use App\Exports\Coordinator\SalesOrdersYearExport;

class ProjectCoordinatorController extends Controller
{


    private function coordinatorSalesmenScope($user): array
    {
        $name = strtolower(trim($user->name ?? ''));

        // GM/Admin → all salesmen
        if (method_exists($user, 'hasRole') && $user->hasRole('gm|admin')) {
            return ['SOHAIB', 'TARIQ', 'JAMAL','SOAHIB','TAREQ', 'ABDO', 'AHMED'];
        }

        // Shenoy → Eastern coordinator
        if ($name === 'shenoy') {
            return ['SOHAIB', 'TARIQ','SOAHIB','TAREQ', 'JAMAL'];
        }

        // Niyas → Western coordinator
        if ($name === 'niyas') {
            return ['ABDO', 'AHMED'];
        }

        // default (no restriction, or adjust as you like)
        return [];
    }

    public function index(Request $request)
    {
        $user       = $request->user();
        $userRegion = strtolower($user->region ?? '');


        // 🔹 Region scope for coordinators:
        // - Eastern / Central users → Eastern + Central
        // - Western users → Western only
        // - GM / Admin (canViewAll) → all

        // region scope (Eastern/Central/Western etc.)
        $regionsScope  = $this->coordinatorRegionScope($user);
        // salesman scope (Sohaib/Tariq/Jamal etc.)
        $salesmenScope = $this->coordinatorSalesmenScope($user);





        /*
        |------------------------------------------------------------------
        |  Salesmen scope per coordinator
        |------------------------------------------------------------------
        |
        | Shenoy (project_coordinator_eastern):
        |   → Sohaib, Tariq, Jamal
        |
        | Niyas (project_coordinator_western):
        |   → Abdo, Ahmed
        |
        | GM / Admin:
        |   → all of them
        */

        // Projects (filtered by region + salesman)
        $projectsQuery = Project::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $projects      = (clone $projectsQuery)
            ->orderByDesc('quotation_date')
            ->get();

        // Sales Orders (same logic)
        $salesOrdersQuery = SalesOrderLog::coordinatorBaseQuery($regionsScope, $salesmenScope);
        $salesOrders      = (clone $salesOrdersQuery)
            ->orderByDesc('date_rec')
            ->get();
        /*
        |--------------------------------------------------------------------------
        |  KPIs
        |--------------------------------------------------------------------------
        */

        $kpiProjectsCount = Project::kpiProjectsCountForCoordinator($regionsScope);

        $salesKpis            = SalesOrderLog::kpisForCoordinator($regionsScope);
        $kpiSalesOrdersCount  = $salesKpis['count'];
        $kpiSalesOrdersValue  = $salesKpis['value'];

        /*
        |--------------------------------------------------------------------------
        |  CHART DATA (Quotation vs PO by region)
        |--------------------------------------------------------------------------
        */

        // Chart (still by region, but only for visible records)
        $projectByRegion = Project::quotationTotalsByRegion($regionsScope);
        $poByRegion      = SalesOrderLog::poTotalsByRegion($regionsScope);

        $regions         = ['eastern', 'central', 'western'];
        $chartCategories = [];
        $chartProjects   = [];
        $chartPOs        = [];

        foreach ($regions as $r) {
            if (!in_array($r, $regionsScope)) {
                continue;
            }

            $chartCategories[] = ucfirst($r);
            $chartProjects[]   = (float) ($projectByRegion[$r] ?? 0);
            $chartPOs[]        = (float) ($poByRegion[$r] ?? 0);
        }

        return view('coordinator.index', [
            'userRegion'           => $userRegion,
            'regionsScope'         => $regionsScope,
            'salesmenScope'        => $salesmenScope,
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
            'job_no'        => ['nullable', 'string', 'max:255'],
            'po_no'         => ['required', 'string', 'max:255'],
            'po_date'       => ['required', 'date'],
            'po_value'      => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'remarks'       => ['nullable', 'string'],
            'attachments.*' => ['file', 'max:51200'], // 50 MB
            'oaa'           => ['nullable', 'string', 'max:50'],
        ]);

        try {
            return DB::transaction(function () use ($data, $request, $user) {

                // ---------- your existing logic starts here ----------
                if ($data['source'] === 'salesorder') {
                    /** @var \App\Models\SalesOrderLog $so */
                    $so = SalesOrderLog::findOrFail($data['record_id']);

                    $regionsScope = $this->coordinatorRegionScope($user);
                    if (! in_array(strtolower($so->area ?? ''), $regionsScope, true)) {
                        return response()->json([
                            'ok'      => false,
                            'message' => 'You are not allowed to update this sales order.',
                        ], 403);
                    }

                    $poValueWithVat = round($data['po_value'] * 1.15, 2);

                    DB::update("
                    UPDATE `salesorderlog`
                    SET
                        `PO. No.`        = ?,
                        `date_rec`       = ?,
                        `PO Value`       = ?,
                        `value_with_vat` = ?,
                        `Payment Terms`  = ?,
                        `Status`         = ?,
                        `Job No.`        = ?,
                        `Remarks`        = ?,
                        created_by_id    = $user->id,
                        `updated_at`     = NOW()
                    WHERE `id` = ?
                ", [
                        $data['po_no'],
                        $data['po_date'],
                        $data['po_value'],
                        $poValueWithVat,
                        $data['payment_terms'] ?? null,
                        $data['oaa'] ?? null,
                        $data['job_no'] ?? null,
                        $data['remarks'] ?? null,
                        $so->id,
                    ]);

                    // attachments
                    if ($request->hasFile('attachments')) {
                        $files = $request->file('attachments');
                        if (! is_array($files)) {
                            $files = [$files];
                        }

                        foreach ($files as $file) {
                            if (! $file || ! $file->isValid()) {
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

                // ---------- CREATE NEW PO (from Projects tab) ----------
                /** @var \App\Models\Project $project */
                $project = Project::findOrFail($data['record_id']);

                if (! is_null($project->status_current)) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'This project already has a PO. Please edit it from the Sales Order Log tab.',
                    ], 422);
                }

                $poValueWithVat = round($data['po_value'] * 1.15, 2);

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
                    $data['po_value'],
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

                $project->status                    = 'PO-RECEIVED';
                $project->status_current            = 'PO-RECEIVED';
                $project->coordinator_updated_by_id = $user->id;
                $project->save();

                // attachments
                if ($request->hasFile('attachments')) {
                    $files = $request->file('attachments');
                    if (! is_array($files)) {
                        $files = [$files];
                    }

                    foreach ($files as $file) {
                        if (! $file || ! $file->isValid()) {
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
                    'message' => 'PO saved, project updated to PO-RECEIVED, and files uploaded.',
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






    private function coordinatorRegionScope($user): array
    {
        $userRegion = strtolower($user->region ?? '');

        if (method_exists($user, 'can') && $user->can('viewAllRegions')) {
            return ['eastern', 'central', 'western'];
        }

        if ($userRegion === 'western') {
            return ['western'];
        }

        return ['eastern', 'central'];
    }




    /**
     * Return a single sales order + attachments (for View button in coordinator screen)
     */
    public function showSalesOrder(Request $request, SalesOrderLog $salesorder)
    {
        $user        = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        // enforce same scope as index: only see allowed region(s)
        if (! in_array(strtolower($salesorder->area ?? ''), $regionsScope, true)) {
            return response()->json(['ok' => false, 'message' => 'Not allowed'], 403);
        }

        $salesorder->load('attachments', 'creator');

        return response()->json([
            'ok'         => true,
            'salesorder' => [
                'id'             => $salesorder->id,
                'po_no'          => $salesorder->po_no,
                'project'        => $salesorder->project,
                'client'         => $salesorder->client,
                'salesman'       => $salesorder->salesman,
                'area'           => $salesorder->area,
                'atai_products'  => $salesorder->atai_products,
                'po_date'        => optional($salesorder->po_date)->format('Y-m-d'),
                'po_value'       => $salesorder->total_po_value ?? $salesorder->po_value,
                'payment_terms'  => $salesorder->payment_terms,
                'remarks'        => $salesorder->remarks,
                'created_by'     => optional($salesorder->creator)->name,
                'created_at'     => optional($salesorder->created_at)->format('Y-m-d H:i'),
            ],
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

    /**
     * Export Sales Order Log for selected month (plus region / date range filters)
     */
    public function exportSalesOrders(Request $request): BinaryFileResponse
    {
        $user         = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);   // ['eastern', 'central'] etc.

        $month = (int) $request->input('month'); // 1..12
        if (! $month) {
            abort(400, 'Month is required');
        }

        $year     = (int) ($request->input('year') ?: now()->year);
        $region   = $request->input('region', 'all');
        $from     = $request->input('from');
        $to       = $request->input('to');
        $salesman = strtoupper(trim($request->input('salesman', '')));   // from chip

        // normalise region names to match DB values (Eastern/Central/Western)
        $normalizedRegions = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        // Canonical salesman → all accepted spellings in DB
        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO'],
            'AHMED'  => ['AHMED'],
        ];

        // ❗ use DB::table so we get plain stdClass rows (no Eloquent accessors)
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

        // Region dropdown filter
        if ($region && strtolower($region) !== 'all') {
            $query->where('project_region', ucfirst(strtolower($region)));
        }

        // Salesman chip filter (with aliases)
        if ($salesman && $salesman !== 'ALL') {
            $aliases = $salesmanAliasMap[$salesman] ?? [$salesman];
            $query->whereIn('Sales Source', $aliases);
        }

        // Month + year filters (PO date = date_rec)
        $query->whereYear('date_rec', $year)
            ->whereMonth('date_rec', $month);

        // Optional From / To date filters
        if ($from) {
            $query->whereDate('date_rec', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_rec', '<=', $to);
        }

        // get rows as Illuminate\Support\Collection
        $rows = collect($query->orderBy('date_rec')->get());

        $filename    = sprintf('sales_orders_%d_%02d.xlsx', $year, $month);
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
        $user         = $request->user();
        $regionsScope = $this->coordinatorRegionScope($user);

        $year     = (int) ($request->input('year') ?: now()->year);
        $region   = $request->input('region', 'all');
        $from     = $request->input('from');
        $to       = $request->input('to');
        $salesman = strtoupper(trim($request->input('salesman', '')));

        // You’ll add salesman handling inside the export class
        $export   = new SalesOrdersYearExport($regionsScope, $year, $region, $from, $to, $salesman);
        $filename = sprintf('sales_orders_%d_full_year.xlsx', $year);

        return Excel::download($export, $filename);
    }
    public function salesOrderAttachments(SalesOrderLog $salesorder)
    {
        // Optional: if you want region security same as index(),
        // you can re-use your region scope check here.

        $salesorder->load('attachments');

        $items = $salesorder->attachments->map(function ($a) {
            return [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => Storage::disk($a->disk)->url($a->path),
                'created_at'    => optional($a->created_at)->format('Y-m-d H:i'),
                'size_bytes'    => $a->size_bytes,
            ];
        })->values();

        return response()->json([
            'ok'          => true,
            'attachments' => $items,
        ]);
    }
}
