<?php

namespace App\Http\Controllers;

use App\Exports\ProjectsMonthlyExport;
use App\Exports\ProjectsWeeklyExport;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;



class EstimatorReportController extends Controller
{
    public function index(Request $request)
    {
        // just to pre-fill filters on the page
        $year = (int)($request->input('year') ?: now()->year);
        $month = (int)($request->input('month') ?: now()->month);
        $salesmen = User::salesmen()->orderBy('name')->get(['id', 'name']);
        return view('estimation.reports', compact('year', 'month', 'salesmen'));


    }

    /**
     * Base query ONLY for Estimation team reports
     * - Estimator can see *all* salesmen in their region
     * - GM/Admin can see all regions
     */

    protected function estimatorBaseQuery(Request $request): Builder
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Start from ALL non-deleted projects
        $q = Project::query()->whereNull('deleted_at');

        // 🔐 Normal estimator: only their own inquiries (by estimator_name)
        if (!$user->hasRole('Admin') && !$user->hasRole('GM')) {
            $nameKey = strtoupper(trim((string)$user->name));
            $q->whereRaw('UPPER(TRIM(estimator_name)) = ?', [$nameKey]);
        }

        // 🔹 Region filter (from top dropdown)
        if ($area = $request->input('area')) {
            $q->where('area', $area);
        }

        // 🔹 Salesman filter (case-insensitive, uses `salesperson` column only)
        if ($salesman = $request->input('salesman')) {
            $salesmanKey = strtoupper(trim((string)$salesman));
            if ($salesmanKey !== '') {
                $q->whereRaw('UPPER(TRIM(salesperson)) = ?', [$salesmanKey]);
            }
        }

        // ✅ Product family filter (match SAME behavior as DataTable)
        if (($family = $request->input('family')) && $family !== 'all') {
            $familyLc = strtolower(trim((string)$family));

            if ($familyLc === 'ductwork') {
                $q->where(function ($qq) {
                    $qq->whereRaw("UPPER(atai_products) LIKE '%DUCTWORK%'")
                        ->orWhereRaw("UPPER(atai_products) LIKE '%PRE-INSULATED%'")
                        ->orWhereRaw("UPPER(atai_products) LIKE '%SPIRAL%'");
                });
            } elseif ($familyLc === 'sound') {
                $q->whereRaw("UPPER(atai_products) LIKE '%SOUND%'");
            } elseif ($familyLc === 'dampers') {
                $q->whereRaw("UPPER(atai_products) LIKE '%DAMPER%'");
            } elseif ($familyLc === 'accessories') {
                $q->whereRaw("UPPER(atai_products) LIKE '%ACCESSORIES%'");
            } else {
                // fallback
                $q->where('atai_products', 'LIKE', '%' . $family . '%');
            }
        }

        // ❌ No date filters here – exportMonthly/exportWeekly add them.
        return $q;
    }



    public function exportMonthly(Request $request)
    {
        $year  = (int) $request->input('year');
        $month = (int) $request->input('month');

        if (!$year || !$month) {
            return back()->with('error', 'Please select year and month for monthly export.');
        }

        // 📅 SAME effective date as listing: COALESCE(quotation_date, date_rec)
        $dateExprSql = "COALESCE(DATE(quotation_date), DATE(date_rec))";

        // Start from estimator-scoped base query (estimator, area, salesman, family)
        $q = $this->estimatorBaseQuery($request)
            ->whereRaw("YEAR($dateExprSql) = ?", [$year])
            ->whereRaw("MONTH($dateExprSql) = ?", [$month])
            ->orderByRaw("$dateExprSql ASC");

        $rows = $q->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'No inquiries found for the selected filters.');
        }

        // 🔹 Month label
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        // 🔹 Salesman part for filename
        $salesmanFilter = trim((string) $request->input('salesman', ''));
        $salesmanPart   = $salesmanFilter !== '' ? $salesmanFilter : 'All Salesmen';

        // Make salesman safe for filename
        $safeSalesman = preg_replace('/[\/\\\\:*?"<>|]+/', '-', $salesmanPart);

        // 📁 Final filename: ATAI-Projects-Monthly - Ahmed Amin - December 2025.xlsx
        $fileName = sprintf(
            'ATAI-Projects-Monthly - %s - %s.xlsx',
            $safeSalesman,
            $monthName
        );

        $estimatorName = optional(Auth::user())->name;

        return Excel::download(
            new ProjectsMonthlyExport($rows, $monthName, $estimatorName),
            $fileName
        );
    }


    public function exportWeekly(Request $request)
    {
        $year     = (int) $request->input('year');
        $month    = (int) $request->input('month');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if (!$month && !$dateFrom && !$dateTo) {
            return back()->with('error', 'Please select a month or a date range for weekly export.');
        }

        $dateExprSql = "COALESCE(DATE(quotation_date), DATE(date_rec))";

        // salesman filter (for filename)
        $salesmanFilter = trim((string) $request->input('salesman', ''));

        // Base filtered query
        $q = $this->estimatorBaseQuery($request);

        if ($dateFrom || $dateTo) {
            if ($dateFrom) {
                $q->whereRaw("$dateExprSql >= ?", [$dateFrom]);
            }
            if ($dateTo) {
                $q->whereRaw("$dateExprSql <= ?", [$dateTo]);
            }
        } else {
            if (!$year || !$month) {
                return back()->with('error', 'Please select year and month for weekly export.');
            }

            $q->whereRaw("YEAR($dateExprSql) = ?", [$year])
                ->whereRaw("MONTH($dateExprSql) = ?", [$month]);
        }

        $rows = $q->orderByRaw("$dateExprSql ASC")->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'No inquiries found for the selected filters.');
        }

        // ---- Group rows into weeks ----
        $groups = [];
        foreach ($rows as $p) {
            $date = $p->quotation_date ?? $p->date_rec;
            if (!$date) {
                $key = 'No Date';
            } else {
                $c     = Carbon::parse($date);
                $start = $c->copy()->startOfWeek(Carbon::SUNDAY);
                $end   = $start->copy()->addDays(4); // Thursday

                // The week label used inside sheet
                $key = $start->format('d-m-Y') . ' to ' . $end->format('d-m-Y');
            }
            $groups[$key][] = $p;
        }
        ksort($groups);

        // Extract the FIRST week key for filename
        $firstWeekKey = array_key_first($groups);   // e.g. "30-11-2025 to 04-12-2025"

        // Convert for filename compatibility
        $safeWeekKey = str_replace([' ', '/'], ['', '-'], $firstWeekKey);
        // becomes "30-11-2025to04-12-2025"

        // Add salesman if selected
        $fileSalesman = $salesmanFilter ? preg_replace('/[^A-Za-z0-9]+/', '_', $salesmanFilter) : 'All';

        // FINAL FILENAME
        $fileName = "ATAI-Projects-Weekly-{$fileSalesman}-{$safeWeekKey}.xlsx";

        $estimatorName = optional(Auth::user())->name;

        return Excel::download(
            new ProjectsWeeklyExport($groups, $estimatorName, $salesmanFilter),
            $fileName
        );
    }







    public function projects()
    {
        // Pass the signed-in name to the navbar button (your blade expects $user)
        $user = Auth::user()?->name ?? 'User';

        return view('estimation.index', [
            'user' => $user,
        ]);
    }

    // Inquiries Log page (DataTable only)
//    public function inquiriesLog(Request $r)
//    {
//        $user = Auth::user()?->name ?? 'User';
//
//        $salesmen = User::whereNotNull('region')
//            ->orderBy('name')
//            ->get(['name']);
//
//        return view('projects.inquiries_log', [
//            'user'      => $user,
//            'salesmen'  => $salesmen,
//        ]);
//    }

    public function store(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $data = $request->validate([
            'project'            => 'required|string|max:255',
            'client'             => 'required|string|max:255',
            'salesman'           => 'required|string|max:100',
            'location'           => 'nullable|string|max:255',
            'area'               => 'required|in:Eastern,Central,Western',
            'quotation_no'       => 'required|string|max:255|unique:projects,quotation_no',
            'quotation_date'     => 'required|date',
            'revision_no'        => 'nullable|integer|min:0|max:10', // ✅ match UI (Rev 10)
            'date_received'      => 'required|date',
            'atai_products'      => 'required|string|max:255',
            'price'              => 'required|numeric|min:0',
            'status'             => 'required|string|max:50',
            'technical_base'     => 'nullable|string|max:50',
            'technical_submittal'=> 'nullable|string|max:10',
            'contact_person'     => 'nullable|string|max:255',
            'contact_number'     => 'nullable|string|max:50',
            'contact_email'      => 'nullable|email|max:255',
            'company_address'    => 'nullable|string|max:255',
        ]);

        // ✅ Normalize values
        $salesmanKey = strtoupper(trim((string)$data['salesman']));
        $productKey  = strtoupper(trim((string)$data['atai_products']));

        // ✅ STRICT RULE:
        // Only Abdo/Ahmed can create Pre-Insulated / Spiral inquiries
        $allowedSubForSpecial = ['PRE-INSULATED', 'PRE INSULATED', 'SPIRAL'];
        $specialSalesmen      = ['ABDO', 'AHMED'];

        if (in_array($productKey, $allowedSubForSpecial, true) && !in_array($salesmanKey, $specialSalesmen, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Pre-Insulated / Spiral can only be selected for Abdo or Ahmed.',
            ], 422);
        }

        // Optional: unify naming (store pretty values)
        $salesmanPretty = trim((string)$data['salesman']);
        $productPretty  = trim((string)$data['atai_products']);

        $project = Project::create([
            'project_name'        => $data['project'],
            'client_name'         => $data['client'],
            'project_location'    => $data['location'] ?? null,
            'area'                => $data['area'],

            'salesman'            => $salesmanPretty,
            'salesperson'         => $salesmanPretty,

            'quotation_no'        => $data['quotation_no'],
            'revision_no'         => $data['revision_no'] ?? 0,
            'quotation_date'      => $data['quotation_date'],
            'date_rec'            => $data['date_received'],

            'atai_products'       => $productPretty,
            'quotation_value'     => $data['price'],

            // keep filters happy
            'project_type'        => strtoupper((string)$data['status']),
            'status'              => $data['status'],

            'technical_base'      => $data['technical_base'] ?? null,
            'technical_submittal' => $data['technical_submittal'] ?? null,

            'contact_person'      => $data['contact_person'] ?? null,
            'contact_number'      => $data['contact_number'] ?? null,
            'contact_email'       => $data['contact_email'] ?? null,
            'company_address'     => $data['company_address'] ?? null,

            'created_by_id'       => $user?->id,
            'estimator_name'      => $user?->name,
            'action1'             => $user?->name,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Inquiry created successfully.',
            'project' => $project,
        ]);
    }

    public function update(Request $request, Project $inquiry)
    {
        $data = $request->validate([
            'project'            => ['required', 'string', 'max:255'],
            'client'             => ['required', 'string', 'max:255'],
            'salesman'           => ['required', 'string', 'max:255'],
            'area'               => ['required', 'in:Eastern,Central,Western'],
            'technical_base'     => ['nullable', 'string', 'max:50'],
            'technical_submittal'=> ['nullable', 'string', 'max:10'],
            'location'           => ['nullable', 'string', 'max:255'],

            // ✅ unique, but ignore current row
            'quotation_no'       => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'quotation_no')->ignore($inquiry->id),
            ],

            'revision_no'        => ['nullable', 'integer', 'min:0', 'max:10'], // ✅ Rev 10
            'quotation_date'     => ['required', 'date'],
            'date_received'      => ['required', 'date'],
            'atai_products'      => ['required', 'string', 'max:255'],
            'price'              => ['required', 'numeric', 'min:0'],
            'status'             => ['required', 'string', 'max:50'],
            'contact_person'     => ['nullable', 'string', 'max:255'],
            'contact_number'     => ['nullable', 'string', 'max:255'],
            'contact_email'      => ['nullable', 'email', 'max:255'],
            'company_address'    => ['nullable', 'string', 'max:255'],
        ]);

        // ✅ SAME STRICT RULE as store()
        $salesmanKey = strtoupper(trim((string)$data['salesman']));
        $productKey  = strtoupper(trim((string)$data['atai_products']));

        $allowedSubForSpecial = ['PRE-INSULATED', 'PRE INSULATED', 'SPIRAL'];
        $specialSalesmen      = ['ABDO', 'AHMED'];

        if (in_array($productKey, $allowedSubForSpecial, true) && !in_array($salesmanKey, $specialSalesmen, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Pre-Insulated / Spiral can only be selected for Abdo or Ahmed.',
            ], 422);
        }

        $inquiry->fill([
            'project_name'        => $data['project'],
            'client_name'         => $data['client'],
            'salesman'            => $data['salesman'],
            'salesperson'         => $data['salesman'],
            'area'                => $data['area'],
            'technical_base'      => $data['technical_base'] ?? null,
            'technical_submittal' => $data['technical_submittal'] ?? null,
            'project_location'    => $data['location'] ?? null,
            'quotation_no'        => $data['quotation_no'],
            'revision_no'         => $data['revision_no'] ?? 0,
            'quotation_date'      => $data['quotation_date'],
            'date_rec'            => $data['date_received'],
            'atai_products'       => $data['atai_products'],
            'quotation_value'     => $data['price'],
            'project_type'        => strtoupper((string)$data['status']), // keep filters happy
            'status'              => $data['status'],
            'contact_person'      => $data['contact_person'] ?? null,
            'contact_number'      => $data['contact_number'] ?? null,
            'contact_email'       => $data['contact_email'] ?? null,
            'company_address'     => $data['company_address'] ?? null,
        ])->save();

        return response()->json(['ok' => true, 'message' => 'Inquiry updated successfully.']);
    }

    // Used by Select2 for dynamic project / client lists

    // ...

    public function options(Request $request)
    {
        $field = $request->get('field');        // 'project' or 'client'
        $term = trim((string)$request->get('term', ''));

        $column = match ($field) {
            'project' => 'project_name',
            'client' => 'client_name',
            default => null,
        };

        if (!$column) {
            return response()->json(['results' => []]);
        }

        $query = Project::query()
            ->whereNotNull($column)
            ->where($column, '<>', '');

        if ($term !== '') {
            $query->where($column, 'like', '%' . $term . '%');
        }

        $results = $query
            ->distinct()
            ->orderBy($column)
            ->limit(50)
            ->get()
            ->map(fn($row) => [
                'id' => $row->{$column},
                'text' => $row->{$column},
            ]);

        return response()->json(['results' => $results]);
    }

    public function data(Request $req)
    {
        /** @var \App\Models\User $user */
        $user   = $req->user();
        $draw   = (int) $req->input('draw', 1);
        $start  = (int) $req->input('start', 0);
        $length = (int) $req->input('length', 10);

        // Base query: all non–soft-deleted inquiries
        $q = Project::query()->whereNull('deleted_at');

        // Normal estimator: only their own inquiries (by estimator_name)
        if (!$user->hasRole('Admin') && !$user->hasRole('GM')) {
            $estimator = strtoupper(trim($user->name));
            $q->whereRaw("UPPER(TRIM(estimator_name)) = ?", [$estimator]);
        }

        // 🔹 Salesman filter (case-insensitive, uses `salesperson` column only)
        if ($salesman = $req->input('salesman')) {
            $salesmanKey = strtoupper(trim($salesman));
            if ($salesmanKey !== '') {
                $q->whereRaw('UPPER(TRIM(salesperson)) = ?', [$salesmanKey]);
            }
        }

        // ✅ Region filter (from top dropdown)  (YOU HAD THIS COMMENTED)
        if ($area = $req->input('area')) {
            $q->where('area', $area);
        }

        // ----- Status tab (Bidding / In-Hand) -----
        $statusNorm   = (string) $req->input('status_norm', '');
        $statusNormLc = strtolower(trim($statusNorm));

        if ($statusNormLc === 'bidding') {
            $q->whereRaw("UPPER(TRIM(project_type)) = 'BIDDING'");
        } elseif ($statusNormLc === 'in-hand' || $statusNormLc === 'inhand') {
            $q->whereRaw("UPPER(TRIM(project_type)) = 'IN-HAND'");
        }

        // ----- Optional Filters (year, month, family) -----
        $year  = $req->integer('year');
        $month = $req->integer('month');

        $dateExpr = DB::raw("COALESCE(DATE(quotation_date), DATE(date_rec))");
        $dateFrom = $req->input('date_from');
        $dateTo   = $req->input('date_to');

        if ($dateFrom || $dateTo) {
            if ($dateFrom) $q->whereDate($dateExpr, '>=', $dateFrom);
            if ($dateTo)   $q->whereDate($dateExpr, '<=', $dateTo);
        } else {
            if ($year)  $q->whereYear($dateExpr, $year);
            if ($month) $q->whereMonth($dateExpr, $month);
        }

        // ✅ Family filter (IMPORTANT: DUCTWORK must include subtypes)
        if (($family = $req->input('family')) && $family !== 'all') {
            $familyLc = strtolower(trim((string)$family));

            if ($familyLc === 'ductwork') {
                // Ductwork chip should match:
                // - Ductwork
                // - Ductwork and Accessories
                // - Pre-Insulated Ductwork
                // - Spiral Ductwork
                $q->where(function ($qq) {
                    $qq->whereRaw("UPPER(atai_products) LIKE '%DUCTWORK%'")
                        ->orWhereRaw("UPPER(atai_products) LIKE '%PRE-INSULATED%'")
                        ->orWhereRaw("UPPER(atai_products) LIKE '%SPIRAL%'");
                });
            } elseif ($familyLc === 'sound') {
                $q->whereRaw("UPPER(atai_products) LIKE '%SOUND%'");
            } elseif ($familyLc === 'dampers') {
                $q->whereRaw("UPPER(atai_products) LIKE '%DAMPER%'");
            } elseif ($familyLc === 'accessories') {
                $q->whereRaw("UPPER(atai_products) LIKE '%ACCESSORIES%'");
            } else {
                // fallback (if any other family arrives)
                $q->where('atai_products', 'like', '%' . $family . '%');
            }
        }

        // ----- Optional Search -----
        $search = data_get($req->input('search'), 'value', '');
        $qForCount = clone $q; // ✅ counts BEFORE applying search

        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('project_name', 'like', "%$search%")
                    ->orWhere('client_name', 'like', "%$search%")
                    ->orWhere('quotation_no', 'like', "%$search%");
            });
        }

        // ----- Count & Sum for KPIs -----
        $recordsTotal    = (clone $qForCount)->count();        // ✅ before search
        $recordsFiltered = (clone $q)->count();                // ✅ after search
        $sumValue        = (clone $q)->sum('quotation_value'); // ✅ sum respects filters + search

        // ----- Ordering -----
        $orderColIndex = (int) data_get($req->input('order'), '0.column', 0);
        $orderDir      = data_get($req->input('order'), '0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $columns = [
            0  => 'id',
            1  => 'project_name',
            2  => 'client_name',
            3  => 'salesperson',
            4  => 'project_location',
            5  => 'area',
            6  => 'quotation_no',
            7  => 'revision_no',
            8  => 'atai_products',
            9  => 'quotation_value',
            10 => 'project_type',
            11 => 'quotation_date',
            12 => 'date_rec',
            13 => 'created_at',
            14 => 'id',
        ];

        $orderCol = $columns[$orderColIndex] ?? 'id';

        $rows = (clone $q)
            ->orderBy($orderCol, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        // ----- Format rows (match JS columns) -----
        $fmtSar = fn($n) => 'SAR ' . number_format((float)$n, 0);

        $areaBadge = function (?string $a) {
            if (!$a) return '—';
            $up = strtoupper($a);
            return '<span class="badge area-badge area-' . e($up) . '">' . e($up) . '</span>';
        };

        $statusBadge = function (?string $s) {
            if (!$s) return '—';
            $label = strtoupper($s);
            return '<span class="badge bg-warning-subtle text-dark fw-bold">' . e($label) . '</span>';
        };

        $data = $rows->map(function (Project $p) use ($fmtSar, $areaBadge, $statusBadge) {
            $status = $p->project_type ?: $p->status;

            $actionsHtml = '
        <div class="btn-group btn-group-sm" role="group">
            <button type="button"
                    class="btn btn-outline-primary"
                    data-action="edit"
                    data-id="' . $p->id . '">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button"
                    class="btn btn-outline-danger"
                    data-action="delete"
                    data-id="' . $p->id . '">
                <i class="bi bi-trash"></i>
            </button>
        </div>';

            return [
                'id'                  => $p->id,
                'name'                => $p->project_name,
                'client'              => $p->client_name,
                'salesperson'         => $p->salesperson ?? $p->salesman,
                'location'            => $p->project_location,
                'area_badge'          => $areaBadge($p->area),
                'quotation_no'        => $p->quotation_no,
                'revision_no'         => (int) ($p->revision_no ?? 0),
                'quotation_date'      => optional($p->quotation_date)->format('Y-m-d'),
                'date_rec'            => optional($p->date_rec)->format('Y-m-d'),
                'atai_products'       => $p->atai_products,
                'quotation_value_fmt' => $fmtSar($p->quotation_value),
                'status_badge'        => $statusBadge($status),
                'created_at_fmt'      => optional($p->created_at)->format('Y-m-d H:i'),
                'actions'             => $actionsHtml,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'header_sum_value' => (float)$sumValue,
            'sum_quotation_value' => (float)$sumValue,
            'sum_quotation_value_fmt' => $fmtSar($sumValue),
            'data' => $data,
        ]);
    }


    public function show(Project $inquiry)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        // Estimator can only view their own inquiries (Admin/GM can view all)
        if ($user && !$user->hasRole('Admin') && !$user->hasRole('GM')) {
            $nameKey = strtoupper(trim((string)$user->name));
            $rowKey  = strtoupper(trim((string)$inquiry->estimator_name));
            if ($rowKey !== $nameKey) {
                return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
            }
        }

        $fmtDate = function ($v) {
            if (!$v) return null;
            try {
                return Carbon::parse($v)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        };

        $technical = $inquiry->technical_submittal
            ? strtolower(trim($inquiry->technical_submittal))
            : null;

        return response()->json([
            'ok' => true,
            'data' => [
                'id'                 => $inquiry->id,
                'project'            => $inquiry->project_name,
                'client'             => $inquiry->client_name,
                'salesman'           => $inquiry->salesperson ?? $inquiry->salesman,
                'area'               => $inquiry->area,
                'technical_base'     => $inquiry->technical_base,
                'technical_submittal'=> $technical,
                'location'           => $inquiry->project_location,
                'quotation_no'       => $inquiry->quotation_no,
                'revision_no'        => (int)($inquiry->revision_no ?? 0),
                'quotation_date'     => $fmtDate($inquiry->quotation_date),
                'date_received'      => $fmtDate($inquiry->date_rec),
                'atai_products'      => $inquiry->atai_products,
                'price'              => (float)($inquiry->quotation_value ?? 0),
                'status'             => $inquiry->project_type ?? $inquiry->status,
                'contact_person'     => $inquiry->contact_person,
                'contact_number'     => $inquiry->contact_number,
                'contact_email'      => $inquiry->contact_email,
                'company_address'    => $inquiry->company_address,
            ],
        ]);
    }




    public function destroy(Project $inquiry)
    {
        $inquiry->delete();   // soft delete
        return response()->json(['ok' => true, 'message' => 'Inquiry deleted (soft).']);
    }


}
