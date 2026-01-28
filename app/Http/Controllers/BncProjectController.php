<?php

namespace App\Http\Controllers;

use App\Models\BncProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

use Barryvdh\DomPDF\Facade\Pdf;
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
        $totalSar = (clone $kpiQuery)->sum('value_usd') * 3.75;
        $totalValueUsd = self::compactUsd($totalUsd);
        $totalValueSar = self::compactSar($totalSar);

        $kpis = [
            'total_projects'    => (clone $kpiQuery)->count(),
            'total_value'       => $totalValueUsd,
            'total_value_Sar'   => $totalValueSar,
            'approached'        => (clone $kpiQuery)->where('approached', true)->count(),
            'qualified'         => (clone $kpiQuery)->whereIn('lead_qualified', ['Hot', 'Warm'])->count(),
            'expected_close30'  => (clone $kpiQuery)
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

        // ----------------------------------------------------------------------
        // MAIN QUERY (NO JOIN - fast)
        // ----------------------------------------------------------------------
        $query = BncProject::query()
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
            ]);

        // ----------------------------------------------------------------------
        // REGION SCOPING
        // ----------------------------------------------------------------------
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

        // ----------------------------------------------------------------------
        // FILTERS
        // ----------------------------------------------------------------------
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

        // ----------------------------------------------------------------------
        // DATATABLE RESPONSE
        // ----------------------------------------------------------------------
        return DataTables::of($query)
            ->addIndexColumn()

            // Raw sort helpers (now always 0 until user expands row)
            ->addColumn('quoted_value_sar_raw', fn($row) => 0.0)

            // Value SAR
            ->addColumn('value_sar', function ($row) use ($USD_TO_SAR) {
                $sar = (float) ($row->value_usd ?? 0) * $USD_TO_SAR;
                return $sar > 0 ? self::compactSar($sar) : '—';
            })

            // Quoted value (lazy)
            ->addColumn('quoted_value_sar', function ($row) {
                return '<span class="bnc-quoted-total text-muted" data-bnc-id="'.e($row->id).'">—</span>';
            })

            ->addColumn('coverage_pct', function ($row) {
                return '<span class="bnc-coverage badge bg-secondary" data-bnc-id="'.e($row->id).'">—</span>';
            })

            // Approached
            ->editColumn('approached', function ($row) {
                return $row->approached
                    ? '<span class="badge bg-success">Yes</span>'
                    : '<span class="badge bg-secondary">No</span>';
            })

            // Lead badge
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

            // Penetration
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

            // Expected close date
            ->editColumn('expected_close_date', function ($row) {
                if (empty($row->expected_close_date)) {
                    return '<span class="text-muted">—</span>';
                }
                $d = \Illuminate\Support\Carbon::parse($row->expected_close_date);
                return $d->format('d-M-Y');
            })

            // Quotes button (always available, loads via AJAX)
            ->addColumn('quoted_status', function ($row) {
                return '<button type="button"
                class="btn btn-sm btn-outline-light bnc-quotes-toggle"
                data-bnc-id="' . e($row->id) . '"
                data-loaded="0"
                disabled>
                <span class="bnc-quotes-label">…</span>
                <i class="bi bi-chevron-down small d-none"></i>
            </button>';
            })
            // Placeholder detail (JS will replace this with HTML from AJAX)
            ->addColumn('quotes_detail_html', fn() => '<div class="px-3 py-2 text-muted small">Loading…</div>')

            // Actions
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

    public function quotesForBnc(int $id)
    {
        $USD_TO_SAR = 3.75;

        $b = BncProject::query()->findOrFail($id);

        // Normalizer (collation-safe)
        $norm = function ($expr) {
            return "LOWER(TRIM(REGEXP_REPLACE(REPLACE($expr, '-', ' '), '[[:space:]]+', ' '))) COLLATE utf8mb4_unicode_ci";
        };

        $bncName = $b->project_name ?? '';
        $bncCity = $b->city ?? '';
        $bncReg  = $b->region ?? '';
        $bncCty  = $b->country ?? '';

        // IMPORTANT: Convert PHP values into SQL-safe bindings
        $rows = \DB::table('projects as inquiries')
            ->select([
                'inquiries.id',
                'inquiries.project_name',
                'inquiries.area',
                'inquiries.country',
                'inquiries.quotation_no',
                \DB::raw('COALESCE(inquiries.quotation_value,0) as quotation_value'),
            ])
            ->whereRaw("
            (
              {$norm('inquiries.project_name')} LIKE CONCAT('%', {$norm('?')}, '%')
              OR {$norm('?')} LIKE CONCAT('%', {$norm('inquiries.project_name')}, '%')
            )
        ", [$bncName, $bncName])

            // City loose
            ->when(trim($bncCity) !== '', function($q) use ($bncCity, $norm) {
                $q->where(function($qq) use ($bncCity, $norm) {
                    $qq->whereNull('inquiries.project_location')
                        ->orWhereRaw("TRIM(inquiries.project_location) = ''")
                        ->orWhereRaw("{$norm('inquiries.project_location')} LIKE CONCAT('%', {$norm('?')}, '%')", [$bncCity]);
                });
            })

            // Region/Area soft
            ->when($bncReg !== '', function ($q) use ($bncReg, $norm) {
                $inqAreaRaw = $norm('inquiries.area');
                $bncRegRaw  = $norm('?');

                $inqAreaNorm = "CASE
                WHEN $inqAreaRaw LIKE '%east%' THEN 'eastern'
                WHEN $inqAreaRaw LIKE '%central%' OR $inqAreaRaw LIKE '%riyadh%' THEN 'central'
                WHEN $inqAreaRaw LIKE '%west%' OR $inqAreaRaw LIKE '%jeddah%' THEN 'western'
                ELSE $inqAreaRaw
            END";

                $bncRegNorm = "CASE
                WHEN $bncRegRaw LIKE '%east%' THEN 'eastern'
                WHEN $bncRegRaw LIKE '%central%' THEN 'central'
                WHEN $bncRegRaw LIKE '%west%' THEN 'western'
                ELSE $bncRegRaw
            END";

                $q->whereRaw("TRIM(COALESCE(inquiries.area,'')) = '' OR ($inqAreaNorm = $bncRegNorm)", [$bncReg]);
            })

            // Country soft (optional)
            ->limit(200)
            ->get();

        $count = 0;
        $total = 0.0;
        $lines = [];

        foreach ($rows as $r) {
            $qno = trim((string)($r->quotation_no ?? ''));
            if ($qno === '') continue;

            $val = (float)($r->quotation_value ?? 0);
            $count++;
            $total += $val;

            $lines[] = ['no' => $qno, 'val' => $val];
        }

        // Build same HTML table you already use
        $html = '<div class="px-3 py-2">';
        if ($count === 0) {
            $html .= '<div class="text-muted small">No quotations linked yet.</div></div>';
        } else {
            $html .= '<div class="small fw-semibold mb-2">Linked Quotations</div>';
            $html .= '<table class="table table-sm table-dark table-striped mb-0">';
            $html .= '<thead><tr><th>Quotation No.</th><th class="text-end">Quotation Value (SAR)</th></tr></thead><tbody>';
            foreach ($lines as $line) {
                $html .= '<tr><td>' . e($line['no']) . '</td><td class="text-end">' . number_format($line['val'], 0) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        // coverage against BNC value
        $bncSar = (float)($b->value_usd ?? 0) * $USD_TO_SAR;
        $coveragePct = ($bncSar > 0 && $total > 0) ? (int) round(($total / $bncSar) * 100) : 0;

        return response()->json([
            'bnc_id' => $b->id,
            'count'  => $count,
            'total'  => $total,
            'coverage_pct' => $coveragePct,
            'html'   => $html,
        ]);
    }
    protected static function compactUsd(float $value): string
    {
        if ($value >= 1_000_000_000) return number_format($value / 1_000_000_000, 2) . ' B';
        if ($value >= 1_000_000)     return number_format($value / 1_000_000, 2) . ' M';
        if ($value >= 1_000)         return number_format($value / 1_000, 1) . ' K';
        return number_format($value, 0);
    }

    protected static function compactSar(float $value): string
    {
        if ($value >= 1_000_000_000) return number_format($value / 1_000_000_000, 2) . ' B';
        if ($value >= 1_000_000)     return number_format($value / 1_000_000, 2) . ' M';
        if ($value >= 1_000)         return number_format($value / 1_000, 1) . ' K';
        return number_format($value, 0);
    }

    // GET /bnc/{id}
    public function show(BncProject $bncProject)
    {
        $this->authorizeRegion($bncProject);

        $bncProject->load('responsibleSalesman');

        $rawParties = $bncProject->raw_parties ?: [];

        if (empty($rawParties)) {
            $rawParties = [
                'consultant' => self::parsePartyDetails($bncProject->consultant),
                'main_epc'   => self::parsePartyDetails($bncProject->main_contractor),
                'mep_contractor' => self::parsePartyDetails($bncProject->mep_contractor),
                'owners' => self::parsePartyDetails($bncProject->client),
            ];
        }
        return response()->json([
            'id' => $bncProject->id,
            'reference_no' => $bncProject->reference_no,
            'project_name' => $bncProject->project_name,
            'city' => $bncProject->city,
            'region' => $bncProject->region,
            'country' => $bncProject->country,
            'stage' => $bncProject->stage,
            'industry' => $bncProject->industry,

            'award_date' => optional($bncProject->award_date)->toDateString(),

            'client' => $bncProject->client,
            'consultant' => $bncProject->consultant,
            'main_contractor' => $bncProject->main_contractor,
            'mep_contractor' => $bncProject->mep_contractor,

            'datasets' => $bncProject->datasets,
            'overview_info' => $bncProject->overview_info,
            'latest_news' => $bncProject->latest_news,

            'raw_parties' => $rawParties,

            'approached' => (bool) $bncProject->approached,
            'lead_qualified' => $bncProject->lead_qualified ?: 'Unknown',
            'penetration_percent' => (int) ($bncProject->penetration_percent ?? 0),
            'boq_shared' => (bool) $bncProject->boq_shared,
            'submittal_shared' => (bool) $bncProject->submittal_shared,
            'submittal_approved' => (bool) $bncProject->submittal_approved,
            'expected_close_date' => optional($bncProject->expected_close_date)->toDateString(),
            'notes' => $bncProject->notes,

            'responsible_salesman' => $bncProject->responsibleSalesman
                ? ['id' => $bncProject->responsibleSalesman->id, 'name' => $bncProject->responsibleSalesman->name]
                : null,

            'created_at' => optional($bncProject->created_at)->toDateTimeString(),
            'updated_at' => optional($bncProject->updated_at)->toDateTimeString(),
        ]);
    }


    // POST /bnc/{id}
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

    // Upload method unchanged...
    public function upload(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'region' => ['required', 'in:Eastern,Central,Western'],
            'file'   => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ], [
            'file.mimes' => 'Please export/save the BNC report as a CSV file and upload that CSV (not XLS/XLSX).',
        ]);

        $region = $data['region'];
        $file   = $data['file'];

        $path   = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return back()->with('error', 'Unable to read the uploaded file.');
        }

        $firstLine = '';
        while (($firstLine = fgets($handle)) !== false) {
            if (trim($firstLine) !== '') break;
        }
        if ($firstLine === '') {
            fclose($handle);
            return back()->with('error', 'Uploaded file seems to be empty.');
        }

        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, $delimiter)
            && substr_count($firstLine, "\t") >= substr_count($firstLine, ';')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, $delimiter)) {
            $delimiter = ';';
        }

        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->with('error', 'Uploaded file seems to be empty.');
        }

        $headerIndex = null;
        foreach ($rows as $i => $row) {
            foreach ($row as $cell) {
                $cell = trim((string) $cell);
                $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
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

        $colMap = [
            'reference_no'     => null,
            'project_name'     => null,
            'city'             => null,
            'region'           => null,
            'country'          => null,
            'stage'            => null,
            'industry'         => null,
            'value_usd'        => null,
            'award_date'       => null,
            'datasets'         => null,
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

        $getCol = function (array $row, ?int $idx): ?string {
            if ($idx === null) return null;
            return isset($row[$idx]) ? trim((string) $row[$idx]) : null;
        };

        $created = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                $reference = $getCol($row, $colMap['reference_no']);
                if ($reference === null || $reference === '') continue;

                if (! Str::startsWith($reference, 'PRJ')) continue;

                $projectName     = $getCol($row, $colMap['project_name']) ?? '';
                $city            = $getCol($row, $colMap['city']) ?? '';
                $stage           = $getCol($row, $colMap['stage']) ?? '';
                $country         = $getCol($row, $colMap['country']) ?: 'Saudi Arabia';
                $industry        = $getCol($row, $colMap['industry']) ?? '';
                $datasets        = $getCol($row, $colMap['datasets']);

                $consultant      = $getCol($row, $colMap['consultant']);
                $mainContractor  = $getCol($row, $colMap['main_contractor']);
                $mepContractor   = $getCol($row, $colMap['mep_contractor']);
                $overviewInfo    = $getCol($row, $colMap['overview_info']);
                $latestNews      = $getCol($row, $colMap['latest_news']);

                 $valueRaw = $getCol($row, $colMap['value_usd']) ?? '';
                $valueRaw = str_replace([',', ' '], '', $valueRaw);
                $valueUsd = is_numeric($valueRaw) ? (float) $valueRaw : null;

                $awardDate = null;
                $awardCol  = $getCol($row, $colMap['award_date']);
                if ($awardCol !== null && $awardCol !== '') {
                    $parsed = strtotime($awardCol);
                    if ($parsed !== false) {
                        $awardDate = date('Y-m-d', $parsed);
                    }
                }

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
//                    'value_usd'        => $valueUsd,
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



    public function exportPdf(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $USD_TO_SAR = 3.75;

        // Filters from modal
        $minSar   = (float) ($request->input('min_value_sar', 0) ?? 0);
        $stage    = trim((string) $request->input('stage', ''));
        $lead     = trim((string) $request->input('lead_qualified', ''));
        $approached = $request->input('approached', '');

        // region: GM/Admin can choose; sales fixed
        $region = '';
        if ($user->hasAnyRole(['admin','gm'])) {
            $region = trim((string) $request->input('region', ''));
        } else {
            $region = (string) ($user->region ?? '');
        }

        // Build base query (reuse your datatable query idea but simpler for PDF)
        $q = BncProject::query()
            ->leftJoin('projects as inquiries', function ($join) {
                $join->on(
                    DB::raw('inquiries.project_name COLLATE utf8mb4_unicode_ci'),
                    '=',
                    DB::raw('bnc_projects.project_name COLLATE utf8mb4_unicode_ci')
                );
            })
            ->select([
                'bnc_projects.id',
                'bnc_projects.reference_no',
                'bnc_projects.project_name',
                'bnc_projects.city',
                'bnc_projects.region',
                'bnc_projects.country',
                'bnc_projects.stage',
                'bnc_projects.industry',
                'bnc_projects.value_usd',
                'bnc_projects.consultant',
                'bnc_projects.main_contractor',
                'bnc_projects.mep_contractor',
                'bnc_projects.raw_parties',
                'bnc_projects.responsible_salesman_id',
                DB::raw("COUNT(DISTINCT inquiries.quotation_no) AS quotes_count"),
                DB::raw("SUM(COALESCE(inquiries.quotation_value,0)) AS quoted_value_sar"),
            ])
            ->groupBy([
                'bnc_projects.id',
                'bnc_projects.reference_no',
                'bnc_projects.project_name',
                'bnc_projects.city',
                'bnc_projects.region',
                'bnc_projects.country',
                'bnc_projects.stage',
                'bnc_projects.industry',
                'bnc_projects.value_usd',
                'bnc_projects.consultant',
                'bnc_projects.main_contractor',
                'bnc_projects.mep_contractor',
                'bnc_projects.raw_parties',
                'bnc_projects.responsible_salesman_id',
            ]);

        // Apply filters
        if ($region !== '') $q->where('bnc_projects.region', $region);
        if ($stage !== '')  $q->where('bnc_projects.stage', $stage);
        if ($lead !== '')   $q->where('bnc_projects.lead_qualified', $lead);

        if ($approached !== '' && $approached !== null) {
            $q->where('bnc_projects.approached', (bool)$approached);
        }

        // min value SAR filter (computed from value_usd)
        if ($minSar > 0) {
            $q->whereRaw("(COALESCE(bnc_projects.value_usd,0) * ?) >= ?", [$USD_TO_SAR, $minSar]);
        }

        $rows = $q->orderByRaw('COALESCE(bnc_projects.value_usd,0) DESC')->get();

        // Split into quoted vs not quoted
        $quoted = $rows->filter(fn($r) => (int)$r->quotes_count > 0)->values();
        $notQuoted = $rows->filter(fn($r) => (int)$r->quotes_count === 0)->values();

        // Helper formatters
        $fmtSar = fn($n) => number_format((float)$n, 0);
        $compactSar = fn($n) => $this->compactSar((float)$n); // reuse your static if you want

        $payload = [
            'generatedAt' => now()->format('d-M-Y H:i'),
            'filters' => [
                'region' => $region ?: 'All',
                'stage'  => $stage ?: 'All',
                'min_value_sar' => $minSar,
            ],
            'quoted' => $quoted,
            'notQuoted' => $notQuoted,
            'USD_TO_SAR' => $USD_TO_SAR,
        ];

        $pdf = Pdf::loadView('bnc.export_pdf', $payload)
            ->setPaper('a4', 'landscape');

        $fileName = 'BNC_Projects_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($fileName);
    }

}
