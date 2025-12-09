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
        $totalUsd = (clone $kpiQuery)->sum('value_usd');
        $totalSar=(clone $kpiQuery)->sum('value_usd')*3.75;
        $totalValueUsd = self::compactUsd($totalUsd);
        $totalValueSar=self::compactSar($totalSar);
        $kpis = [
            'total_projects'   => (clone $kpiQuery)->count(),
            'total_value'      => $totalValueUsd,
            'total_value_Sar'      =>$totalValueSar,
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

        $USD_TO_SAR = 3.75;

        /* ----------------------------------------------------------------------
         * MAIN QUERY (collation safe)
         * ---------------------------------------------------------------------- */
        $query = BncProject::query()
            ->leftJoin('projects as inquiries', function ($join) {
                // City match with SAME collation on both sides
                $join->on(
                    DB::raw("LOWER(TRIM(inquiries.project_location)) COLLATE utf8mb4_unicode_ci"),
                    '=',
                    DB::raw("LOWER(TRIM(bnc_projects.city)) COLLATE utf8mb4_unicode_ci")
                )
                    // Project name: inquiry contains FULL BNC project name
                    ->whereRaw("
                LOWER(TRIM(inquiries.project_name)) COLLATE utf8mb4_unicode_ci
                LIKE CONCAT(
                    '%',
                    LOWER(TRIM(bnc_projects.project_name)) COLLATE utf8mb4_unicode_ci,
                    '%'
                )
            ");
            })
            ->select([
                'bnc_projects.id',
                'bnc_projects.reference_no',
                'bnc_projects.project_name',
                'bnc_projects.city',
                'bnc_projects.region',
                'bnc_projects.stage',
                'bnc_projects.value_usd',
                'bnc_projects.approached',
                'bnc_projects.lead_qualified',
                'bnc_projects.penetration_percent',
                'bnc_projects.expected_close_date',

                DB::raw("
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        inquiries.quotation_no, '||',
                        COALESCE(inquiries.quotation_value, 0)
                    )
                    SEPARATOR '##'
                ) AS inquiry_quotes_concat
            "),
                DB::raw("
                SUM(COALESCE(inquiries.quotation_value, 0))
                AS inquiry_quotation_total_value
            "),
            ])
            ->groupBy([
                'bnc_projects.id',
                'bnc_projects.reference_no',
                'bnc_projects.project_name',
                'bnc_projects.city',
                'bnc_projects.region',
                'bnc_projects.stage',
                'bnc_projects.value_usd',
                'bnc_projects.approached',
                'bnc_projects.lead_qualified',
                'bnc_projects.penetration_percent',
                'bnc_projects.expected_close_date',
            ]);

        /* ----------------------------------------------------------------------
         * REGION SCOPING
         * ---------------------------------------------------------------------- */
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

        /* ----------------------------------------------------------------------
         * FILTERS
         * ---------------------------------------------------------------------- */
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

        /* ----------------------------------------------------------------------
         * DATATABLE RESPONSE
         * ---------------------------------------------------------------------- */
        return DataTables::of($query)
            ->addIndexColumn()

            /* ---------- RAW NUMERIC HELPERS (for sorting) ---------- */
            ->addColumn('value_sar_raw', function ($row) use ($USD_TO_SAR) {
                return (float) ($row->value_usd ?? 0) * $USD_TO_SAR;
            })
            ->addColumn('quoted_value_sar_raw', function ($row) {
                return (float) ($row->inquiry_quotation_total_value ?? 0);
            })

            /* ---------- DISPLAY: VALUE (USD) compact ---------- */
            ->editColumn('value_usd', function ($row) {
                $val = (float) ($row->value_usd ?? 0);
                return $val > 0 ? self::compactUsd($val) : '—';
            })

            /* ---------- DISPLAY: VALUE (SAR) compact ---------- */
            ->addColumn('value_sar', function ($row) use ($USD_TO_SAR) {
                $sar = (float) ($row->value_usd ?? 0) * $USD_TO_SAR;
                return $sar > 0 ? self::compactSar($sar) : '—';
            })

            /* ---------- DISPLAY: QUOTED VALUE (SAR) compact ---------- */
            ->addColumn('quoted_value_sar', function ($row) {
                $total = (float) ($row->inquiry_quotation_total_value ?? 0);
                return $total > 0
                    ? self::compactSar($total)
                    : '<span class="text-muted">—</span>';
            })

            /* ---------- COVERAGE % (QUOTED / BNC VALUE) ---------- */
            ->addColumn('coverage_pct', function ($row) use ($USD_TO_SAR) {
                $bncSar = (float) ($row->value_usd ?? 0) * $USD_TO_SAR;
                $quoted = (float) ($row->inquiry_quotation_total_value ?? 0);

                if ($bncSar <= 0 || $quoted <= 0) {
                    return '<span class="badge bg-secondary">0%</span>';
                }

                $pct = (int) round(($quoted / $bncSar) * 100);

                $class = 'bg-success';
                if ($pct < 30) {
                    $class = 'bg-danger';
                } elseif ($pct < 70) {
                    $class = 'bg-warning text-dark';
                }

                return '<span class="badge ' . $class . '">' . $pct . '%</span>';
            })

            /* ---------- APPROACHED BADGE ---------- */
            ->editColumn('approached', function ($row) {
                return $row->approached
                    ? '<span class="badge bg-success">Yes</span>'
                    : '<span class="badge bg-secondary">No</span>';
            })

            /* ---------- LEAD QUALIFIED BADGE ---------- */
            ->editColumn('lead_qualified', function ($row) {
                $lead      = $row->lead_qualified ?: 'Unknown';
                $leadUpper = strtoupper($lead);

                $class = 'bg-secondary';
                if ($leadUpper === 'HOT') {
                    $class = 'bg-danger';
                } elseif ($leadUpper === 'WARM') {
                    $class = 'bg-warning text-dark';
                } elseif ($leadUpper === 'COLD') {
                    $class = 'bg-info';
                }

                return '<span class="badge ' . $class . '">' . e($lead) . '</span>';
            })

            /* ---------- PENETRATION % BADGE ---------- */
            ->editColumn('penetration_percent', function ($row) {
                $val = (int) ($row->penetration_percent ?? 0);

                if ($val <= 0) {
                    return '<span class="badge bg-secondary">0%</span>';
                }

                $class = 'bg-success';
                if ($val < 30) {
                    $class = 'bg-danger';
                } elseif ($val < 70) {
                    $class = 'bg-warning text-dark';
                }

                return '<span class="badge ' . $class . '">' . $val . '%</span>';
            })

            /* ---------- EXPECTED CLOSE DATE ---------- */
            ->editColumn('expected_close_date', function ($row) {
                if (empty($row->expected_close_date)) {
                    return '<span class="text-muted">—</span>';
                }

                $d = \Illuminate\Support\Carbon::parse($row->expected_close_date);
                return $d->format('d-M-Y');
            })

            /* ---------- QUOTES BUTTON ---------- */
            ->addColumn('quoted_status', function ($row) {
                $concat  = (string) ($row->inquiry_quotes_concat ?? '');
                $entries = array_filter(explode('##', $concat));
                $count   = 0;

                foreach ($entries as $entry) {
                    [$qNo, $val] = array_pad(explode('||', $entry), 2, 0);
                    if (trim($qNo) !== '') {
                        $count++;
                    }
                }

                if ($count === 0) {
                    return '<span class="text-muted small">Not quoted</span>';
                }

                $label = $count === 1 ? '1 quote' : $count . ' quotes';

                return '<button type="button" class="btn btn-sm btn-outline-light bnc-quotes-toggle" data-count="'
                    . e($count)
                    . '">'
                    . e($label)
                    . ' <i class="bi bi-chevron-down small"></i></button>';
            })

            /* ---------- QUOTES DETAIL HTML ---------- */
            ->addColumn('quotes_detail_html', function ($row) {
                $concat  = (string) ($row->inquiry_quotes_concat ?? '');
                $entries = array_filter(explode('##', $concat));

                if (empty($entries)) {
                    return '<div class="px-3 py-2 text-muted small">No quotations linked yet.</div>';
                }

                $lines = [];
                foreach ($entries as $entry) {
                    [$qNo, $val] = array_pad(explode('||', $entry), 2, 0);
                    if (trim($qNo) !== '') {
                        $lines[] = [
                            'no'  => trim($qNo),
                            'val' => (float) $val,
                        ];
                    }
                }

                if (empty($lines)) {
                    return '<div class="px-3 py-2 text-muted small">No quotations linked yet.</div>';
                }

                $html = '<div class="px-3 py-2">';
                $html .= '<div class="small fw-semibold mb-2">Linked Quotations</div>';
                $html .= '<table class="table table-sm table-dark table-striped mb-0">';
                $html .= '<thead><tr><th>Quotation No.</th><th class="text-end">Quotation Value (SAR)</th></tr></thead><tbody>';

                foreach ($lines as $line) {
                    $html .= '<tr><td>' . e($line['no']) . '</td><td class="text-end">'
                        . number_format($line['val'], 0) . '</td></tr>';
                }

                $html .= '</tbody></table></div>';

                return $html;
            })

            /* ---------- ACTIONS ---------- */
            ->addColumn('actions', function ($row) {
                return '<button type="button" class="btn btn-sm btn-outline-light btn-bnc-view" data-id="'
                    . e($row->id)
                    . '">View</button>';
            })

            ->rawColumns([
                'approached',
                'lead_qualified',
                'penetration_percent',
                'quoted_status',
                'quoted_value_sar',
                'coverage_pct',
                'expected_close_date',
                'quotes_detail_html',
                'actions',
            ])
            ->make(true);
    }

    protected static function compactUsd(float $value): string
    {
        if ($value >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2) . ' B';
        }
        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 2) . ' M';
        }
        if ($value >= 1_000) {
            return number_format($value / 1_000, 1) . ' K';
        }
        return number_format($value, 0);
    }

    protected static function compactSar(float $value): string
    {
        if ($value >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2) . ' B';
        }
        if ($value >= 1_000_000) {
            return number_format($value / 1_000_000, 2) . ' M';
        }
        if ($value >= 1_000) {
            return number_format($value / 1_000, 1) . ' K';
        }
        return number_format($value, 0);
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
