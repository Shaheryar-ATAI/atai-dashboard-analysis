<?php

namespace App\Http\Controllers;

use App\Models\BncProject;
use Barryvdh\DomPDF\Facade\Pdf;
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
//        $totalUsd = (clone $kpiQuery)->sum('value_usd');
        $totalSar = (clone $kpiQuery)->sum('value_usd') * 3.75;
    //    $totalValueUsd = self::compactUsd($totalUsd);
        $totalValueSar = self::compactSar($totalSar);

        $kpis = [
            'total_projects'    => (clone $kpiQuery)->count(),
          //  'total_value'       => $totalValueUsd,
            'total_value_Sar'   => $totalValueSar,
            'approached'        => (clone $kpiQuery)->where('approached', true)->count(),
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

        // Give this endpoint enough time (local/dev). Still optimize SQL so it won’t need it.
        @set_time_limit(180);

        $USD_TO_SAR = 3.75;

        // Prevent truncated quote lists
        DB::statement("SET SESSION group_concat_max_len = 65535");

        // -----------------------------
        // SQL normalizers (MariaDB/MySQL)
        // NOTE: REGEXP_REPLACE is heavy. We keep it minimal here.
        // -----------------------------
        $norm = function ($expr) {
            return "LOWER(TRIM(REGEXP_REPLACE(REPLACE(REPLACE($expr, CHAR(160), ' '), '-', ' '), '[[:space:]]+', ' '))) COLLATE utf8mb4_unicode_ci";
        };

        $normKey3 = function ($expr) use ($norm) {
            return "SUBSTRING_INDEX({$norm($expr)}, ' ', 3)";
        };

        $normCountry = function ($expr) use ($norm) {
            $base = "COALESCE(NULLIF(TRIM($expr), ''), 'Saudi Arabia')";
            return "CASE
            WHEN {$norm($base)} IN ('ksa','k.s.a','kingdom of saudi arabia','saudi','saudiarabia') THEN 'saudi arabia'
            ELSE {$norm($base)}
        END";
        };

        // -----------------------------
        // Base BNC query (FAST)
        // -----------------------------
        $query = BncProject::query()->select([
            'bnc_projects.id',
            'bnc_projects.reference_no',
            'bnc_projects.project_name',
            'bnc_projects.city',
            'bnc_projects.region',
            'bnc_projects.stage',
            'bnc_projects.value_usd',
            'bnc_projects.approached',
            'bnc_projects.expected_close_date',
        ]);

        // -----------------------------
        // REGION SCOPING (same rules)
        // -----------------------------
        if ($user->hasAnyRole(['admin', 'gm'])) {
            if ($region = $request->string('region')->trim()->value()) {
                $query->where('bnc_projects.region', $region);
            }
        } else {
            $region = $user->region;

            if (! $region) {
                if ($user->hasRole('sales_eastern')) $region = 'Eastern';
                elseif ($user->hasRole('sales_central')) $region = 'Central';
                elseif ($user->hasRole('sales_western')) $region = 'Western';
            }

            if ($region) {
                $query->where('bnc_projects.region', $region);
            }
        }

        // -----------------------------
        // FILTERS
        // -----------------------------
        if ($stage = $request->string('stage')->trim()->value()) {
            $query->where('bnc_projects.stage', $stage);
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

        // -----------------------------
        // CORRELATED SUBQUERIES (only for current page rows)
        // -----------------------------
        // Matching rules:
        // - Tier A: name contains both ways
        // - Tier B: first 3 words key contains
        // - City/Location soft
        // - Region/Area soft
        // - Country soft
        $bncName = $norm("bnc_projects.project_name");
        $bncKey  = $normKey3("bnc_projects.project_name");

        $bncCity = $norm("bnc_projects.city");
        $bncReg  = $norm("bnc_projects.region");
        $bncCtry = $normCountry("bnc_projects.country");

        $inqName = $norm("p.project_name");
        $inqKey  = $normKey3("p.project_name");

        $inqLoc  = $norm("p.project_location");
        $inqArea = $norm("p.area");
        $inqCtry = $normCountry("p.country");

        // Quotation number normalization (same as your earlier logic)
        $inqQnoNorm = "
        UPPER(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(TRIM(COALESCE(p.quotation_no,'')), CHAR(160), ''),
                        '\r',''),
                    '\n',''),
                '\t',''),
            ' ','')
        )
    ";

        $matchSql = "(
        (
            $inqName LIKE CONCAT('%', $bncName, '%')
            OR $bncName LIKE CONCAT('%', $inqName, '%')
        )
        OR
        (
            $inqName LIKE CONCAT('%', $bncKey, '%')
            OR $bncName LIKE CONCAT('%', $inqKey, '%')
        )
    )
    AND (
        TRIM(COALESCE(p.project_location,'')) = ''
        OR TRIM(COALESCE(bnc_projects.city,'')) = ''
        OR $inqLoc = $bncCity
        OR $inqLoc LIKE CONCAT('%', $bncCity, '%')
        OR $bncCity LIKE CONCAT('%', $inqLoc, '%')
    )
    AND (
        TRIM(COALESCE(p.area,'')) = ''
        OR TRIM(COALESCE(bnc_projects.region,'')) = ''
        OR $inqArea = $bncReg
    )
    AND (
        TRIM(COALESCE(p.country,'')) = ''
        OR TRIM(COALESCE(bnc_projects.country,'')) = ''
        OR $inqCtry = $bncCtry
    )";

        $query->addSelect([
            DB::raw("(
            SELECT GROUP_CONCAT(
                DISTINCT CONCAT($inqQnoNorm, '||', COALESCE(p.quotation_value,0))
                SEPARATOR '##'
            )
            FROM projects p
            WHERE $matchSql
        ) AS inquiry_quotes_concat"),

            DB::raw("(
            SELECT SUM(COALESCE(p.quotation_value,0))
            FROM projects p
            WHERE $matchSql
        ) AS inquiry_quotation_total_value"),
        ]);

        // -----------------------------
        // DATATABLE RESPONSE
        // -----------------------------
        return DataTables::of($query)
            ->addIndexColumn()

            ->addColumn('value_sar_raw', function ($row) use ($USD_TO_SAR) {
                return (float)($row->value_usd ?? 0) * $USD_TO_SAR;
            })

            ->addColumn('value_sar', function ($row) use ($USD_TO_SAR) {
                $sar = (float)($row->value_usd ?? 0) * $USD_TO_SAR;
                return $sar > 0 ? self::compactSar($sar) : '—';
            })

            ->addColumn('quoted_value_sar', function ($row) {
                $total = (float)($row->inquiry_quotation_total_value ?? 0);
                return $total > 0 ? self::compactSar($total) : '<span class="text-muted">—</span>';
            })

            ->editColumn('approached', function ($row) {
                return $row->approached
                    ? '<span class="badge bg-success">Yes</span>'
                    : '<span class="badge bg-secondary">No</span>';
            })

            ->editColumn('expected_close_date', function ($row) {
                if (empty($row->expected_close_date)) {
                    return '<span class="text-muted">—</span>';
                }
                $d = \Illuminate\Support\Carbon::parse($row->expected_close_date);
                return $d->format('d-M-Y');
            })

            ->addColumn('quoted_status', function ($row) {
                $concat  = (string)($row->inquiry_quotes_concat ?? '');
                $entries = array_filter(explode('##', $concat));

                $count = 0;
                foreach ($entries as $entry) {
                    [$qNo] = array_pad(explode('||', $entry), 2, '');
                    $qNo = strtoupper(preg_replace('/\s+/u', '', str_replace("\xC2\xA0", '', (string)$qNo)));
                    if ($qNo !== '') $count++;
                }

                if ($count === 0) {
                    return '<span class="text-muted small">Not quoted</span>';
                }

                $label = $count === 1 ? '1 quote' : $count . ' quotes';

                return '<button type="button" class="btn btn-sm btn-outline-light bnc-quotes-toggle" data-count="'
                    . e($count) . '">'
                    . e($label)
                    . ' <i class="bi bi-chevron-down small"></i></button>';
            })

            ->addColumn('quotes_list', function ($row) {
                $concat  = (string)($row->inquiry_quotes_concat ?? '');
                $entries = array_filter(explode('##', $concat));

                $lines = [];
                foreach ($entries as $entry) {
                    [$qNo, $val] = array_pad(explode('||', $entry), 2, 0);

                    $qNo = strtoupper(preg_replace('/\s+/u', '', str_replace("\xC2\xA0", '', (string)$qNo)));
                    $qNo = preg_replace("/[\r\n\t]/", "", $qNo);

                    if ($qNo !== '') {
                        $lines[] = ['no' => $qNo, 'val' => (float)$val];
                    }
                }

                usort($lines, fn($a,$b) => ($b['val'] <=> $a['val']));

                return $lines; // ✅ IMPORTANT: return array (NOT json_encode)
            })


            ->addColumn('actions', function ($row) {
                return '<button type="button" class="btn btn-sm btn-outline-light btn-bnc-view" data-id="'
                    . e($row->id) . '">View</button>';
            })

            ->orderColumn('value_sar', function ($q, $order) use ($USD_TO_SAR) {
                $q->orderByRaw("COALESCE(bnc_projects.value_usd,0) * {$USD_TO_SAR} {$order}");
            })

            ->rawColumns([
                'approached',
                'quoted_status',
                'quoted_value_sar',
                'expected_close_date',
                'actions',
            ])
            ->make(true);
    }



    private function getInquiryAggForBnc($bnc): array
    {
        // cache per BNC row for 5 minutes (datatable reloads a lot)
        $cacheKey = 'bnc_inq_agg_' . (int)$bnc->id;

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addMinutes(5), function () use ($bnc) {

            $bncName = $this->normText((string)$bnc->project_name);
            $bncKey3 = $this->key3($bncName);

            $bncCity = $this->normText((string)$bnc->city);
            $bncRegion = $this->normText((string)$bnc->region);
            $bncCountry = 'saudi arabia'; // default; you can read from bnc if needed

            // ✅ Tight WHERE to avoid scanning everything
            $q = DB::table('projects as p')
                ->selectRaw("
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(p.quotation_no), CHAR(160), ''), '\r',''), '\n',''), '\t',''), ' ', '')),
                        '||',
                        COALESCE(p.quotation_value, 0)
                    )
                    SEPARATOR '##'
                ) as concat,
                SUM(COALESCE(p.quotation_value,0)) as total
            ")
                // Region filter first (big cut)
                ->where(function($w) use ($bncRegion) {
                    // inquiries.area is ATAI region
                    if ($bncRegion !== '') {
                        $w->whereRaw("LOWER(TRIM(COALESCE(p.area,''))) = ?", [$bncRegion]);
                    }
                })
                // Name match (fast-ish using key3 strategy)
                ->where(function($w) use ($bncKey3) {
                    if ($bncKey3 !== '') {
                        $w->whereRaw("LOWER(TRIM(COALESCE(p.project_name,''))) LIKE ?", ["%{$bncKey3}%"]);
                    }
                })
                // City match soft (only if both present)
                ->where(function($w) use ($bncCity) {
                    if ($bncCity !== '') {
                        $w->where(function($x) use ($bncCity) {
                            $x->whereRaw("TRIM(COALESCE(p.project_location,'')) = ''")
                                ->orWhereRaw("LOWER(TRIM(COALESCE(p.project_location,''))) LIKE ?", ["%{$bncCity}%"]);
                        });
                    }
                });

            $row = $q->first();

            return [
                'concat' => (string)($row->concat ?? ''),
                'total'  => (float)($row->total ?? 0),
            ];
        });
    }

    private function normText(string $s): string
    {
        $s = str_replace("\xC2\xA0", ' ', $s); // NBSP
        $s = str_replace(['-', '_', '/', '\\'], ' ', $s);
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return strtolower(trim($s));
    }

    private function key3(string $s): string
    {
        $parts = array_values(array_filter(explode(' ', trim($s))));
        return strtolower(trim(implode(' ', array_slice($parts, 0, 3))));
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
        $usd = (float)($bncProject->value_usd ?? 0);
        $sar = $usd * 3.75;
        return response()->json([
            'id' => $bncProject->id,
            'reference_no' => $bncProject->reference_no,
            'project_name' => $bncProject->project_name,
            'city' => $bncProject->city,
            'region' => $bncProject->region,
            'country' => $bncProject->country,
            'stage' => $bncProject->stage,
            'industry' => $bncProject->industry,
            'value_usd' => $usd,          // optional (you can keep for internal)
            'value_sar' => $sar,
            'award_date' => optional($bncProject->award_date)->toDateString(),

            'client' => $bncProject->client,
            'consultant' => $bncProject->consultant,
            'main_contractor' => $bncProject->main_contractor,
            'mep_contractor' => $bncProject->mep_contractor,

            'datasets' => $bncProject->datasets,
            'overview_info' => $bncProject->overview_info,
            'latest_news' => $bncProject->latest_news,

            'raw_parties' => $bncProject->raw_parties ?: [],

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

    private function normalizeQno(string $q): string
    {
        // remove NBSP and all whitespace, then trim
        $q = str_replace("\xC2\xA0", ' ', $q);          // NBSP -> space
        $q = preg_replace('/\s+/u', '', $q);            // remove ALL whitespace
        return strtoupper(trim($q));                    // keep consistent
    }


    public function lookupQuotes(Request $request)
    {
        $debug = $request->boolean('debug') || (string)$request->query('debug') === '1';

        $data = $request->validate([
            'qnos'   => ['required', 'array', 'min:1', 'max:200'],
            'qnos.*' => ['required', 'string', 'max:150'],
        ]);

        // -----------------------------
        // PHP normalizer (must match JS)
        // -----------------------------
        $normalize = function (string $q): ?string {
            $q = str_replace("\xC2\xA0", '', $q);          // NBSP
            $q = preg_replace("/[\r\n\t]/", "", $q);       // controls
            $q = preg_replace('/[^0-9A-Za-z]+/u', '', $q); // keep alnum only
            $q = strtoupper(trim($q));
            return $q !== '' ? $q : null;
        };

        $rawQnos = $data['qnos'] ?? [];
        $qnosNorm = array_values(array_unique(array_filter(array_map(
            fn($q) => $normalize((string)$q),
            $rawQnos
        ))));

        if ($debug) {
            dd([
                'HIT_LOOKUP_QUOTES' => true,
                'RAW_QNOS'          => $rawQnos,
                'NORMALIZED_QNOS'   => $qnosNorm,
            ]);
        }

        if (empty($qnosNorm)) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        // -----------------------------
        // MariaDB-safe SQL normalizer (no REGEXP_REPLACE)
        // IMPORTANT: This produces an expression we can groupBy safely.
        // -----------------------------
        $normExpr = function (string $col): string {
            return "UPPER(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
                REPLACE(TRIM($col), CHAR(160), ''),
            ' ', ''),
            '.', ''),
            '-', ''),
            '/', ''),
            '_', ''),
            '\\\\', '')
        )";
        };

        $salesNorm = $normExpr("s.`Quote No.`");
        $projNorm  = $normExpr("p.`quotation_no`");

        // -----------------------------
        // A) salesorderlog (PO received)
        // -----------------------------
        $poRows = DB::table('salesorderlog as s')
            ->selectRaw("
            {$salesNorm} as q_norm,
            COALESCE(NULLIF(TRIM(s.`Sales Source`),''),'Not Mentioned') as sales_source,
            COUNT(DISTINCT NULLIF(TRIM(s.`PO. No.`),'')) as po_count,
            SUM(COALESCE(s.`PO Value`,0)) as po_value_sum,
            MAX(s.`date_rec`) as last_po_date
        ")
            //->whereIn(DB::raw($salesNorm), $qnosNorm)
            ->groupBy('q_norm', 'sales_source')
            ->get();

        $poMap = [];
        foreach ($poRows as $r) {
            $q = (string)$r->q_norm;
            if ($q === '') continue;

            if (!isset($poMap[$q])) {
                $poMap[$q] = [
                    'found_in'     => 'salesorderlog',
                    'po_received'  => true,
                    'po_value'     => (float)$r->po_value_sum,
                    'po_count'     => (int)$r->po_count,
                    'last_po_date' => $r->last_po_date,
                    'sales_source' => (string)$r->sales_source,
                    // optional fields for consistency
                    'quotation_value' => 0.0,
                    'quotation_date'  => null,
                    'updated_at'      => null,
                    'area'            => '',
                ];
            } else {
                $poMap[$q]['po_value'] += (float)$r->po_value_sum;
                $poMap[$q]['po_count'] += (int)$r->po_count;
            }
        }

        // -----------------------------
        // B) projects (inquiries) for missing qnos
        // -----------------------------
        $missing = array_values(array_filter($qnosNorm, fn($q) => !isset($poMap[$q])));

        $inqMap = [];
        if (!empty($missing)) {
            $inqRows = DB::table('projects as p')
                ->selectRaw("
                {$projNorm} as q_norm,
                COALESCE(p.`quotation_value`,0) as quotation_value,
                p.`quotation_date` as quotation_date,
                p.`updated_at` as updated_at,
                COALESCE(NULLIF(TRIM(p.`area`),''),'') as area,
                COALESCE(NULLIF(TRIM(p.`salesman`),''),'Not Mentioned') as sales_source
            ")
                //->whereIn(DB::raw($projNorm), $missing)
                ->get();

            foreach ($inqRows as $r) {
                $q = (string)$r->q_norm;
                if ($q === '') continue;

                $inqMap[$q] = [
                    'found_in'        => 'projects',
                    'po_received'     => false,
                    'po_value'        => 0.0,
                    'po_count'        => 0,
                    'last_po_date'    => null,
                    'sales_source'    => (string)($r->sales_source ?? 'Not Mentioned'),
                    'quotation_value' => (float)($r->quotation_value ?? 0),
                    'quotation_date'  => $r->quotation_date,
                    'updated_at'      => $r->updated_at,
                    'area'            => (string)($r->area ?? ''),
                ];
            }
        }

        // -----------------------------
        // C) Merge (always return all requested keys)
        // -----------------------------
        $result = [];
        foreach ($qnosNorm as $q) {
            $result[$q] = $poMap[$q] ?? $inqMap[$q] ?? [
                'found_in'        => 'none',
                'po_received'     => false,
                'po_value'        => 0.0,
                'po_count'        => 0,
                'last_po_date'    => null,
                'sales_source'    => '—',
                'quotation_value' => 0.0,
                'quotation_date'  => null,
                'updated_at'      => null,
                'area'            => '',
            ];
        }

        return response()->json(['ok' => true, 'data' => $result]);
    }


















}
