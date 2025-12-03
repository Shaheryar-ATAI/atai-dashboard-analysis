<?php

namespace App\Http\Controllers;

use App\Models\BncProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BncProjectController extends Controller
{
    // GET /bnc
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Base query with region scoping
        $baseQuery = BncProject::query();

        // --- Region visibility rules ---
        if ($user->hasAnyRole(['admin', 'gm'])) {
            if ($region = $request->string('region')->trim()->value()) {
                $baseQuery->where('region', $region);
            }
        } else {
            if (! empty($user->region)) {
                $baseQuery->where('region', $user->region);
            }
        }

        // --- KPIs for header cards ---
        $kpiQuery = clone $baseQuery;

        $kpis = [
            'total_projects'   => (clone $kpiQuery)->count(),
            'total_value'      => (clone $kpiQuery)->sum('value_usd'),
            'approached'       => (clone $kpiQuery)->where('approached', true)->count(),
            'qualified'        => (clone $kpiQuery)->whereIn('lead_qualified', ['Hot', 'Warm'])->count(),
            'expected_close30' => (clone $kpiQuery)
                ->whereNotNull('expected_close_date')
                ->whereBetween('expected_close_date', [now(), now()->addDays(30)])
                ->count(),
        ];

        $projects = $baseQuery->orderBy('value_usd', 'desc')->paginate(50);

        return view('bnc.index', compact('projects', 'kpis'));
    }

    // DataTables source
    public function datatable(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Base Eloquent query with LEFT JOIN to inquiries (projects)
        $query = BncProject::query()
            ->leftJoin('projects as inquiries', function ($join) {
                // force both sides to same collation and drop LOWER()
                $join->on(
                    DB::raw('inquiries.project_name COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('bnc_projects.project_name COLLATE utf8mb4_unicode_ci')
                );
            })
            ->select([
                'bnc_projects.*',
                'inquiries.quotation_no as inquiry_quotation_no',
                'inquiries.quotation_value as inquiry_quotation_value',
            ]);

        // ---------- Region scoping (same logic as index) ----------
        if ($user->hasAnyRole(['admin', 'gm'])) {
            if ($region = $request->string('region')->trim()->value()) {
                $query->where('bnc_projects.region', $region);
            }
        } else {
            $region = $user->region;

            if (! $region) {
                if ($user->hasRole('sales_eastern')) {
                    $region = 'Eastern';
                } elseif ($user->hasRole('sales_central')) {
                    $region = 'Central';
                } elseif ($user->hasRole('sales_western')) {
                    $region = 'Western';
                }
            }

            if ($region) {
                $query->where('bnc_projects.region', $region);
            }
        }

        // ---------- Filters from request ----------
        if ($stage = $request->string('stage')->trim()->value()) {
            $query->where('bnc_projects.stage', $stage);
        }

        if ($lead = $request->string('lead_qualified')->trim()->value()) {
            $query->where('bnc_projects.lead_qualified', $lead);
        }

        if (($approached = $request->input('approached', null)) !== null && $approached !== '') {
            $query->where('bnc_projects.approached', (bool) $approached);
        }

        if ($q = $request->string('q')->trim()->value()) {
            $query->where(function ($qq) use ($q) {
                $qq->where('bnc_projects.project_name', 'like', "%{$q}%")
                    ->orWhere('bnc_projects.city', 'like', "%{$q}%")
                    ->orWhere('bnc_projects.client', 'like', "%{$q}%")
                    ->orWhere('bnc_projects.consultant', 'like', "%{$q}%")
                    ->orWhere('bnc_projects.reference_no', 'like', "%{$q}%");
            });
        }

        $USD_TO_SAR = 3.75;

        // ---------- DataTables response ----------
        return DataTables::of($query)
            ->addIndexColumn() // DT_RowIndex

            // Value (USD) nicely formatted
            ->editColumn('value_usd', function (BncProject $p) {
                return number_format($p->value_usd ?? 0, 0);
            })

            // NEW: Value (SAR) using fixed FX rate
            ->addColumn('value_sar', function (BncProject $p) use ($USD_TO_SAR) {
                $sar = ($p->value_usd ?? 0) * $USD_TO_SAR;
                return number_format($sar, 0);
            })

            // Approached badge
            ->editColumn('approached', function (BncProject $p) {
                if ($p->approached) {
                    return '<span class="badge bg-success">Yes</span>';
                }
                return '<span class="badge bg-secondary">No</span>';
            })

            // Expected close date simple format
            ->editColumn('expected_close_date', function (BncProject $p) {
                return $p->expected_close_date
                    ? $p->expected_close_date->format('Y-m-d')
                    : '';
            })

            // NEW: Quoted? column
            ->addColumn('quoted_status', function (BncProject $p) {
                if (!empty($p->inquiry_quotation_no)) {
                    return 'Quoted (' . e($p->inquiry_quotation_no) . ')';
                }
                return 'Not Quoted';
            })

            // NEW: Quoted value (SAR) column
            ->addColumn('quoted_value_sar', function (BncProject $p) {
                if (!empty($p->inquiry_quotation_no) && $p->inquiry_quotation_value) {
                    return number_format($p->inquiry_quotation_value, 0);
                }
                return '';
            })

            // Actions col
            ->addColumn('actions', function (BncProject $p) {
                return '<button type="button" class="btn btn-sm btn-outline-light btn-bnc-view" data-id="'
                    . e($p->id)
                    . '">View</button>';
            })

            ->rawColumns(['approached', 'actions'])
            ->make(true);
    }

    // GET /bnc/{id}
    public function show(BncProject $bncProject)
    {
        $this->authorizeRegion($bncProject);

        return response()->json($bncProject->load('responsibleSalesman'));
    }

    // POST /bnc/{id} – update checkpoints from modal
    public function update(Request $request, BncProject $bncProject)
    {
        $this->authorizeRegion($bncProject);

        $user = $request->user();

        $data = $request->validate([
            'approached'               => ['sometimes', 'boolean'],
            'lead_qualified'           => ['sometimes', 'in:Hot,Warm,Cold,Unknown'],
            'penetration_percent'      => ['sometimes', 'integer', 'min:0', 'max:100'],
            'boq_shared'               => ['sometimes', 'boolean'],
            'submittal_shared'         => ['sometimes', 'boolean'],
            'submittal_approved'       => ['sometimes', 'boolean'],
            'expected_close_date'      => ['sometimes', 'date', 'nullable'],
            'responsible_salesman_id'  => ['sometimes', 'nullable', 'exists:users,id'],
            'notes'                    => ['sometimes', 'nullable', 'string'],
        ]);

        $bncProject->fill($data);
        $bncProject->updated_by_id = $user->id;
        $bncProject->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Simple region + role authorization for show/update.
     */
    protected function authorizeRegion(BncProject $project): void
    {
        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('gm')) {
            return;
        }

        if ($user->region !== $project->region) {
            abort(403, 'You are not allowed to view this project.');
        }
    }

    /**
     * Upload & import BNC CSV file.
     * IMPORTANT: Excel must be saved as CSV first.
     */
    public function upload(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // 1. Validate input
        $data = $request->validate([
            'region' => ['required', 'in:Eastern,Central,Western'],
            'file'   => [
                'required',
                'file',
                'mimes:csv,txt',   // we parse ONLY CSV
                'max:20480',       // 20 MB
            ],
        ], [
            'file.mimes' => 'Please export/save the BNC report as a CSV file and upload that CSV (not XLS/XLSX).',
        ]);

        $region = $data['region'];
        $file   = $data['file'];

        // 2. Read CSV into array of rows
        $path   = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return back()->with('error', 'Unable to read the uploaded file.');
        }

        // --- detect delimiter from first non-empty line ---
        $firstLine = '';
        while (($firstLine = fgets($handle)) !== false) {
            if (trim($firstLine) !== '') {
                break;
            }
        }
        if ($firstLine === '') {
            fclose($handle);
            return back()->with('error', 'Uploaded file seems to be empty.');
        }

        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, $delimiter)
            && substr_count($firstLine, "\t") >= substr_count($firstLine, ';')) {
            $delimiter = "\t";         // tab-delimited
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, $delimiter)) {
            $delimiter = ';';          // semicolon-delimited
        }

        // rewind pointer to beginning
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->with('error', 'Uploaded file seems to be empty.');
        }

        // 3. Detect header row (any cell containing "Reference Number")
        $headerIndex = null;
        foreach ($rows as $i => $row) {
            foreach ($row as $cell) {
                $cell = trim((string) $cell);
                $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell); // strip BOM
                if (stripos($cell, 'Reference Number') !== false) {
                    $headerIndex = $i;
                    break 2;
                }
            }
        }

        if ($headerIndex === null) {
            return back()->with('error', 'Could not detect header row (Reference Number). Check the CSV format.');
        }

        $header = $rows[$headerIndex];

        // 3b. Map columns by header text (robust against order changes)
        $colMap = [
            'reference_no'     => null,
            'project_name'     => null,
            'city'             => null,
            'region'           => null, // not used, we pass selected region
            'country'          => null,
            'stage'            => null,
            'industry'         => null,
            'value_usd'        => null,
            'award_date'       => null,
            'datasets'         => null,

            // scraped fields
            'consultant'       => null,
            'main_contractor'  => null,
            'mep_contractor'   => null,
            'overview_info'    => null,
            'latest_news'      => null,
        ];

        foreach ($header as $idx => $label) {
            $labelNorm = strtolower(trim(preg_replace('/\s+/', ' ', (string) $label)));

            if (str_contains($labelNorm, 'reference number')) {
                $colMap['reference_no'] = $idx;

            } elseif (str_contains($labelNorm, 'project name')) {
                $colMap['project_name'] = $idx;

            } elseif ($labelNorm === 'city' || str_contains($labelNorm, 'city')) {
                $colMap['city'] = $idx;

            } elseif (str_contains($labelNorm, 'country')) {
                $colMap['country'] = $idx;

            } elseif (str_contains($labelNorm, 'stage')) {
                $colMap['stage'] = $idx;

            } elseif (str_contains($labelNorm, 'industry')) {
                $colMap['industry'] = $idx;

            } elseif (str_contains($labelNorm, 'value') && str_contains($labelNorm, 'usd')) {
                $colMap['value_usd'] = $idx;

            } elseif (str_contains($labelNorm, 'award') && str_contains($labelNorm, 'date')) {
                $colMap['award_date'] = $idx;

            } elseif (str_contains($labelNorm, 'dataset')) {
                $colMap['datasets'] = $idx;

            } elseif (str_contains($labelNorm, 'mep') && str_contains($labelNorm, 'contractor')) {
                $colMap['mep_contractor'] = $idx;

            } elseif (str_contains($labelNorm, 'consultant')) {
                $colMap['consultant'] = $idx;

            } elseif (str_contains($labelNorm, 'contractor')) {
                if ($colMap['main_contractor'] === null && ! str_contains($labelNorm, 'mep')) {
                    $colMap['main_contractor'] = $idx;
                }

            } elseif (str_contains($labelNorm, 'overview') && str_contains($labelNorm, 'info')) {
                $colMap['overview_info'] = $idx;

            } elseif (str_contains($labelNorm, 'latest') && str_contains($labelNorm, 'news')) {
                $colMap['latest_news'] = $idx;
            }
        }

        if ($colMap['reference_no'] === null) {
            return back()->with('error', 'Could not find "Reference Number" column in header.');
        }

        // helper to safely read a column
        $getCol = function (array $row, ?int $idx): ?string {
            if ($idx === null) {
                return null;
            }
            return isset($row[$idx]) ? trim((string) $row[$idx]) : null;
        };

        // 4. Process data rows
        $created = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            // Start from the row after header
            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                $reference = $getCol($row, $colMap['reference_no']);
                if ($reference === null || $reference === '') {
                    continue;
                }

                // Skip totals/footer rows: only process PRJ...
                if (! Str::startsWith($reference, 'PRJ')) {
                    continue;
                }

                $projectName     = $getCol($row, $colMap['project_name']) ?? '';
                $city            = $getCol($row, $colMap['city']) ?? '';
                $stage           = $getCol($row, $colMap['stage']) ?? '';
                $country         = $getCol($row, $colMap['country']) ?: 'Saudi Arabia';
                $industry        = $getCol($row, $colMap['industry']) ?? '';
                $datasets        = $getCol($row, $colMap['datasets']);

                // scraped columns (may be null if not present in CSV)
                $consultant      = $getCol($row, $colMap['consultant']);
                $mainContractor  = $getCol($row, $colMap['main_contractor']);
                $mepContractor   = $getCol($row, $colMap['mep_contractor']);
                $overviewInfo    = $getCol($row, $colMap['overview_info']);
                $latestNews      = $getCol($row, $colMap['latest_news']);

                // Value(USD) like "300,000,000"
                $valueRaw = $getCol($row, $colMap['value_usd']) ?? '';
                $valueRaw = str_replace([',', ' '], '', $valueRaw);
                $valueUsd = is_numeric($valueRaw) ? (float) $valueRaw : null;

                // Est. Award Date
                $awardDate = null;
                $awardCol  = $getCol($row, $colMap['award_date']);
                if ($awardCol !== null && $awardCol !== '') {
                    $parsed = strtotime($awardCol);
                    if ($parsed !== false) {
                        $awardDate = date('Y-m-d', $parsed);
                    }
                }

                // Upsert by (reference_no + region)
                $attributes = [
                    'reference_no' => $reference,
                    'region'       => $region,
                ];

                $values = [
                    'project_name'     => $projectName,
                    'city'             => $city,
                    'country'          => $country,
                    'stage'            => $stage,
                    'industry'         => $industry,
                    'value_usd'        => $valueUsd,
                    'award_date'       => $awardDate,
                    'datasets'         => $datasets,
                    'source_file'      => $file->getClientOriginalName(),
                    'updated_by_id'    => $user->id,

                    'consultant'       => $consultant,
                    'main_contractor'  => $mainContractor,
                    'mep_contractor'   => $mepContractor,
                    'overview_info'    => $overviewInfo,
                    'latest_news'      => $latestNews,
                ];

                /** @var \App\Models\BncProject|null $model */
                $model = BncProject::where($attributes)->first();

                if ($model) {
                    $model->fill($values);
                    $model->save();
                    $updated++;
                } else {
                    $values['created_by_id'] = $user->id;
                    BncProject::create($attributes + $values);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('bnc.index')
            ->with('success', "BNC import complete for {$region}. Created {$created}, updated {$updated} projects.");
    }
}
