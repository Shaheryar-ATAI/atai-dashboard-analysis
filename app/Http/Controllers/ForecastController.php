<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForecastController extends Controller
{
    // ✅ SALES SOURCE: Allowed dropdown values per region
    protected function allowedSalesSources(string $region): array
    {
        $r = $this->norm($region);

        return match ($r) {
            'WESTERN' => ['ABDO', 'AHMED'],
            'CENTRAL' => ['TAREQ', 'JAMAL'],
            'EASTERN' => ['SOHAIB'],
            default   => [],
        };
    }

    /* ---------------------- FORM ---------------------- */
    public function create()
    {
        return view('forecast.create', [
            'region' => auth()->user()->region,
            'salesman' => auth()->user()->name,
            'year' => now()->year,
            'month_no' => now()->month,
            'month' => now()->format('F'),
            'issuedBy' => auth()->user()->name ?? '—',
            'issuedDate' => now()->toDateString(),
        ]);
    }


    /* ---------------------- LIST (read-only) ---------------------- */
    public function list(Request $r)
    {
        $user = $r->user();

        $year = (int) $r->input('year', now()->year);
        $monthNo = (int) $r->input('month_no', now()->month);
        if ($monthNo < 1 || $monthNo > 12) $monthNo = (int) now()->month;

        $month = (string) $r->input('month', '');
        if ($month === '') {
            $month = date('F', mktime(0, 0, 0, $monthNo, 1));
        }

        $region = (string) ($user->region ?? '');
        $salesman = (string) ($user->name ?? '');

        $mexpr = $this->monthNumberExpr();

        $rows = Forecast::query()
            ->when($salesman !== '', fn($q) => $q->where('salesman', $salesman))
            ->when($region !== '', fn($q) => $q->where('region', $region))
            ->where('year', $year)
            ->whereRaw("$mexpr = ?", [$monthNo])
            ->orderBy('created_at')
            ->get();

        $newRows = $rows->where('type', 'new')->values();
        $carryRows = $rows->where('type', 'carry')->values();

        $kpis = [
            'new_count' => $newRows->count(),
            'new_value' => (float) $newRows->sum('value_sar'),
            'carry_count' => $carryRows->count(),
            'carry_value' => (float) $carryRows->sum('value_sar'),
        ];

        return view('forecast.list', [
            'region' => $region,
            'salesman' => $salesman,
            'year' => $year,
            'month_no' => $monthNo,
            'month' => $month,
            'newRows' => $newRows,
            'carryRows' => $carryRows,
            'kpis' => $kpis,
        ]);
    }
    /* =========================================================
     * Helpers
     * ========================================================= */

    protected function norm(?string $s): string
    {
        return strtoupper(trim((string)$s));
    }

    protected function monthNumberExpr(): string
    {
        return "COALESCE(month_no, MONTH(STR_TO_DATE(month, '%M')), 0)";
    }

    protected function latestPriorForUser(
        string $salesman, string $region,
        string $custKey, string $projKey,
        int    $year, int $monthNo
    ): ?Forecast
    {
        $mexpr = $this->monthNumberExpr();

        return Forecast::query()
            ->where('salesman', $salesman)
            ->where('region', $region)
            ->whereRaw('UPPER(TRIM(customer_name)) = ?', [$custKey])
            ->whereRaw('UPPER(TRIM(project_name))  = ?', [$projKey])
            ->where(function ($q) use ($year, $monthNo, $mexpr) {
                $q->where('year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $monthNo, $mexpr) {
                        $q2->where('year', $year)
                            ->whereRaw("$mexpr < ?", [$monthNo]);
                    });
            })
            ->orderByDesc('year')
            ->orderByDesc(DB::raw($mexpr))
            ->orderByDesc('created_at')
            ->first();
    }

    protected function existsSameMonthForUser(
        string $salesman, string $region,
        string $custKey, string $projKey,
        int    $year, int $monthNo
    ): bool
    {
        $mexpr = $this->monthNumberExpr();

        return Forecast::query()
            ->where('salesman', $salesman)
            ->where('region', $region)
            ->whereRaw('UPPER(TRIM(customer_name)) = ?', [$custKey])
            ->whereRaw('UPPER(TRIM(project_name))  = ?', [$projKey])
            ->where('year', $year)
            ->whereRaw("$mexpr = ?", [$monthNo])
            ->exists();
    }

    protected function monthLabel(?Forecast $f): ?string
    {
        if (!$f) return null;
        $m = $f->month ?: date('F', mktime(0, 0, 0, max(1, (int)$f->month_no), 10));
        return "{$m} {$f->year}";
    }

    /** Extract one section ("new" or "carry") from the request. */
    protected function extractRows(Request $r, string $section): array
    {
        $nestedKey = $section === 'new' ? 'new_orders' : 'carry_over';
        $nested = $r->input($nestedKey, []);

        // --- Preferred: nested rows like new_orders[3][customer_name] ---
        if (is_array($nested) && !empty($nested) && is_array(reset($nested))) {
            $rows = array_values(array_filter(array_map(function ($row) {
                // Clean value: remove commas/spaces BEFORE casting
                $rawVal = $row['value_sar'] ?? '0';
                $val    = (float)preg_replace('/[^\d.\-]/', '', (string)$rawVal);

                $pct = isset($row['percentage']) && $row['percentage'] !== ''
                    ? (float)$row['percentage']
                    : null;

                $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);

                // ✅ SALES SOURCE: normalize to uppercase (optional here; final validation later)
                if (isset($row['sales_source'])) {
                    $row['sales_source'] = strtoupper(trim((string)$row['sales_source']));
                }

                if (empty($row['customer_name']) && empty($row['products']) && empty($row['project_name']) && $val <= 0) {
                    return null;
                }

                return [
                    'customer_name'  => $row['customer_name'] ?? null,
                    'project_name'   => $row['project_name'] ?? null,
                    'quotation_no'   => $row['quotation_no'] ?? null,
                    'products'       => $row['products'] ?? null,
                    'product_family' => $row['product_family'] ?? null,

                    // ✅ SALES SOURCE: captured from UI
                    'sales_source'   => $row['sales_source'] ?? null,

                    'percentage'     => $pct,
                    'value_sar'      => $val,
                    'remarks'        => $row['remarks'] ?? null,
                ];
            }, $nested)));

            return $rows; // <-- IMPORTANT: stop here
        }

        // --- Fallback: parallel arrays (older form) ---
        $prefix = $section;
        $customers = $r->input("{$prefix}_customer_name", []);
        $products = $r->input("{$prefix}_products", []);
        $projects = $r->input("{$prefix}_project_name", []);
        $quotes = $r->input("{$prefix}_quotation_no", []);
        $values = $r->input("{$prefix}_value_sar", []);
        $remarks = $r->input("{$prefix}_remarks", []);
        $families = $r->input("{$prefix}_product_family", []);
        $pcts = $r->input("{$prefix}_percentage", []);

        // ✅ SALES SOURCE: fallback array
        $sources = $r->input("{$prefix}_sales_source", []);

        $rows = [];

        // ✅ include sources count in max
        $max = max(
            count($customers),
            count($products),
            count($projects),
            count($values),
            count($remarks),
            count($families),
            count($pcts),
            count($sources)
        );

        for ($i = 0; $i < $max; $i++) {
            $row = [
                'customer_name'  => trim((string)($customers[$i] ?? '')),
                'products'       => trim((string)($products[$i] ?? '')),
                'project_name'   => trim((string)($projects[$i] ?? '')),

                'percentage'     => isset($pcts[$i]) && $pcts[$i] !== '' ? (float)$pcts[$i] : null,
                'value_sar'      => (float)preg_replace('/[^\d.\-]/', '', (string)($values[$i] ?? '0')),
                'quotation_no'   => trim((string)($quotes[$i] ?? '')),
                'remarks'        => trim((string)($remarks[$i] ?? '')),
                'product_family' => trim((string)($families[$i] ?? '')),

                // ✅ SALES SOURCE
                'sales_source'   => strtoupper(trim((string)($sources[$i] ?? ''))),
            ];

            $empty = $row['customer_name'] === '' && $row['products'] === '' && $row['project_name'] === '' && $row['value_sar'] <= 0;
            if (!$empty) $rows[] = $row;
        }

        return $rows;
    }

    private const QTN_RE = '/^[A-Z]\.\d{4}\.\d\.\d{4}\.[A-Z]{2}\.R\d+$/';

    protected function validateRows(
        array  $rowsNew,
        array  $rowsCarry,
        int    $year,
        int    $monthNo,
        string $salesman,
        string $region,
        bool   $allowSameMonthReplace = false
    ): array {
        $errors = [];

        // Track pairs present in "new" to forbid the same pair in "carry"
        $pairsNew = [];
        foreach ($rowsNew as $r) {
            $ck = $this->norm($r['customer_name'] ?? '');
            $pk = $this->norm($r['project_name'] ?? '');
            if ($ck && $pk) {
                $pairsNew["$ck|$pk"] = true;
            }
        }

        // Track duplicates within the current submission
        $seenSubmission = [];

        $validateOne = function (array $row, string $type, int $i)
        use (&$errors, &$seenSubmission, $pairsNew, $salesman, $region, $year, $monthNo, $allowSameMonthReplace) {
            $custRaw = $row['customer_name'] ?? '';
            $projRaw = $row['project_name'] ?? '';
            $custKey = $this->norm($custRaw);
            $projKey = $this->norm($projRaw);
            $label   = strtoupper($type) . ' Row ' . ($i + 1);

            // --- Required fields: Customer + Project still required ---
            if ($custKey === '' || $projKey === '') {
                $errors[$label][] = 'Customer and Project are required.';
                return;
            }

            // ✅ SALES SOURCE: required + must match region allowed list
            $allowed = $this->allowedSalesSources($region);
            $srcRaw  = $row['sales_source'] ?? '';
            $srcKey  = $this->norm($srcRaw);

            if ($srcKey === '') {
                $errors[$label][] = 'Sales Source is required.';
                return;
            }
            if (!empty($allowed) && !in_array($srcKey, $allowed, true)) {
                $errors[$label][] = 'Invalid Sales Source for your region.';
                return;
            }

            // --- Quotation format check (OPTIONAL, only if filled) ---
            $qtn = trim((string)($row['quotation_no'] ?? ''));
            if ($qtn !== '' && !preg_match(self::QTN_RE, $qtn)) {
                $errors[$label][] = 'Quotation No. must match S.0000.0.0000.XX.R0 (e.g., S.4135.1.2605.MH.R0).';
            }

            // --- De-dup within this submission by Customer + Project ---
            $pkey = "$custKey|$projKey";
            if (isset($seenSubmission[$pkey])) {
                $errors[$label][] = 'Duplicate entry in this submission for the same Customer/Project.';
                return;
            }
            $seenSubmission[$pkey] = true;

            // --- Percentage: OPTIONAL ---
            $pctRaw = $row['percentage'] ?? null;
            $pct    = null;
            if ($pctRaw !== '' && $pctRaw !== null) {
                if (!is_numeric($pctRaw)) {
                    $errors[$label][] = 'Percentage must be numeric if provided.';
                    return;
                }
                $pct = (float)$pctRaw;
            }

            // --- Historical checks (NEW vs CARRY logic) ---
            $prior = $this->latestPriorForUser($salesman, $region, $custKey, $projKey, $year, $monthNo);
            $same  = $this->existsSameMonthForUser($salesman, $region, $custKey, $projKey, $year, $monthNo);

            if ($type === 'new') {
                if ($same && !$allowSameMonthReplace) {
                    $errors[$label][] = 'Already exists in your sheet this month. Edit that row or use Carry Over.';
                } elseif ($prior) {
                    $when = $this->monthLabel($prior);
                    $errors[$label][] = "A previous month entry exists ({$when}). Put this in Carry Over instead of New.";
                }
            } else {
                // carry
                if (isset($pairsNew[$pkey])) {
                    $errors[$label][] = 'This pair is already in New Orders in this submission. Keep it in New, not Carry.';
                    return;
                }
                // (Your old carry rules are intentionally kept commented as you left them.)
            }
        };

        foreach ($rowsNew as $i => $r) {
            $validateOne($r, 'new', $i);
        }
        foreach ($rowsCarry as $i => $r) {
            $validateOne($r, 'carry', $i);
        }

        return $errors;
    }

    protected function replaceSheet(
        string $salesman, string $region, int $year, int $monthNo, string $month,
        array  $rowsNew, array $rowsCarry
    ): int
    {
        $now = now();
        $payload = [];

        $push = function (array $row, string $type) use (&$payload, $salesman, $region, $year, $monthNo, $month, $now) {
            $payload[] = [
                'customer_name' => $row['customer_name'] ?? null,
                'project_name' => $row['project_name'] ?? null,
                'quotation_no' => $row['quotation_no'] ?? null,
                'products' => $row['products'] ?? null,
                'product_family' => $row['product_family'] ?? null,

                // ✅ SALES SOURCE: insert into DB
                'sales_source' => $row['sales_source'] ?? null,

                'value_sar' => (float)($row['value_sar'] ?? 0),
                'percentage' => isset($row['percentage']) ? (int)$row['percentage'] : null,
                'remarks' => $row['remarks'] ?? null,
                'type' => $type,
                'salesman' => $salesman,
                'region' => $region,
                'month' => $month,
                'month_no' => $monthNo,
                'year' => $year,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        foreach ($rowsNew as $r) $push($r, 'new');
        foreach ($rowsCarry as $r) $push($r, 'carry');

        DB::transaction(function () use ($salesman, $region, $year, $monthNo, $payload) {
            Forecast::where('salesman', $salesman)
                ->where('region', $region)
                ->where('year', $year)
                ->where('month_no', $monthNo)
                ->delete();

            if (!empty($payload)) {
                Forecast::insert($payload);
            }
        });

        return count($payload);
    }

    /* ---------------------- SAVE (AJAX) ---------------------- */
    public function save(Request $r)
    {
        $user = auth()->user();
        $region = $user->region ?? '';
        $salesman = $user->name ?? '';

        $year = (int)$r->input('year', now()->year);
        $monthNo = (int)$r->input('month_no', now()->month);
        $month = (string)$r->input('month', now()->format('F'));

        // --- extract + filter out blank rows ------------------------------------
        $rowsNewRaw = $this->extractRows($r, 'new');
        $rowsCarryRaw = $this->extractRows($r, 'carry');

        $hasData = function (array $row): bool {
            $val = (float)preg_replace('/[^\d.\-]/', '', (string)($row['value_sar'] ?? '0'));
            return
                trim((string)($row['customer_name'] ?? '')) !== '' ||
                trim((string)($row['products'] ?? '')) !== '' ||
                trim((string)($row['project_name'] ?? '')) !== '' ||
                trim((string)($row['quotation_no'] ?? '')) !== '' ||
                trim((string)($row['product_family'] ?? '')) !== '' ||

                // ✅ SALES SOURCE: include in "hasData"
                trim((string)($row['sales_source'] ?? '')) !== '' ||

                trim((string)($row['remarks'] ?? '')) !== '' ||
                (isset($row['percentage']) && $row['percentage'] !== '' && $row['percentage'] !== null) ||
                $val > 0;
        };

        $normalize = function (array $row): array {
            // Trim strings
            foreach (['customer_name', 'products', 'project_name', 'quotation_no', 'product_family', 'remarks', 'sales_source'] as $k) {
                if (isset($row[$k])) {
                    $row[$k] = trim((string)$row[$k]);
                }
            }

            // ✅ SALES SOURCE normalize to uppercase
            if (isset($row['sales_source'])) {
                $row['sales_source'] = strtoupper(trim((string)$row['sales_source']));
            }

            // Clean numbers
            if (isset($row['value_sar'])) {
                $row['value_sar'] = (float)preg_replace('/[^\d.\-]/', '', (string)$row['value_sar']);
            }
            if (isset($row['percentage']) && $row['percentage'] !== '') {
                $row['percentage'] = (int)$row['percentage'];
            }
            return $row;
        };

        $rowsNew = array_values(array_map($normalize, array_filter($rowsNewRaw, $hasData)));
        $rowsCarry = array_values(array_map($normalize, array_filter($rowsCarryRaw, $hasData)));

        // --- block empty submissions --------------------------------------------
        if (empty($rowsNew) && empty($rowsCarry)) {
            return response()->json([
                'ok' => false,
                'issues' => [[
                    'section' => 'new',
                    'index' => 0,
                    'messages' => ['Please add at least one row with data before saving.']
                ]]
            ], 422);
        }

        // --- validate ------------------------------------------------------------
        $errors = $this->validateRows($rowsNew, $rowsCarry, $year, $monthNo, $salesman, $region, true);
        if (!empty($errors)) {
            return response()->json(['ok' => false, 'issues' => $this->packIssues($errors)], 422);
        }

        // --- persist -------------------------------------------------------------
        $saved = $this->replaceSheet($salesman, $region, $year, $monthNo, $month, $rowsNew, $rowsCarry);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    /* ---------------------- PDF (validate → save → render) ---------------------- */
    public function pdf(Request $r)
    {
        // 1) User / period meta
        $authRegion = auth()->user()->region ?? '--';
        $authSales = auth()->user()->name ?? '—';

        $region = (string)$r->input('region', $authRegion);
        $salesman = (string)$r->input('salesman', $authSales);
        $year = (int)$r->input('year', now()->year);
        $monthNo = (int)$r->input('month_no', now()->month);
        $month = (string)$r->input('month', now()->format('F'));
        $issuedBy = (string)$r->input('issuedBy', $salesman);
        $issuedDate = (string)$r->input('issuedDate', now()->toDateString());
        $submissionDate = now()->toDateString();

        // helper to show a small inline HTML error page (works in target=_blank tab)
        $inlineError = function (string $title, array $messages, int $status = 422) {
            $lis = '';
            foreach ($messages as $m) {
                if (is_array($m)) {
                    foreach (($m['messages'] ?? []) as $mm) {
                        $lis .= '<li>' . e($mm) . '</li>';
                    }
                } else {
                    $lis .= '<li>' . e($m) . '</li>';
                }
            }
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><title>{$title}</title>
<style>
 body{font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;background:#f8f9fa;color:#333;padding:24px}
 h1{margin:0 0 8px;color:#b02a37;font-size:22px}
 .box{background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px}
 ul{margin:8px 0 0 20px}
</style>
</head><body>
  <div class="box">
    <h1>{$title}</h1>
    <ul>{$lis}</ul>
  </div>
</body></html>
HTML;
            return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
        };

        // 2) Extract rows posted with the form
        $rowsNewPosted = $this->extractRows($r, 'new');
        $rowsCarryPosted = $this->extractRows($r, 'carry');

        // 3) Validate with the posted arrays
        $errors = $this->validateRows($rowsNewPosted, $rowsCarryPosted, $year, $monthNo, $salesman, $region, true);
        if (!empty($errors)) {
            $issues = $this->packIssues($errors);
            return $inlineError('Validation failed', $issues, 422);
        }

        // 4) Save/replace this month’s sheet for the user
        if (!empty($rowsNewPosted) || !empty($rowsCarryPosted)) {
            $this->replaceSheet($salesman, $region, $year, $monthNo, $month, $rowsNewPosted, $rowsCarryPosted);
        }

        // 5) Read back canonical rows to print
        $rows = Forecast::query()
            ->where('salesman', $salesman)
            ->where('region', $region)
            ->where('year', $year)
            ->where('month_no', $monthNo)
            ->orderBy('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return $inlineError('Nothing to print', [['messages' => ['No rows found for this month after save.']]], 422);
        }

        $map = fn($row) => [
            'customer'       => $row->customer_name,
            'product'        => $row->products,
            'project'        => $row->project_name,
            'quotation'      => $row->quotation_no,

            'percentage'     => $row->percentage,
            'product_family' => $row->product_family,
            'sales_source'   => $row->sales_source,

            'value'          => (float)$row->value_sar,
            'remarks'        => $row->remarks,
        ];

        $newOrders = $rows->where('type', 'new')->map($map)->values()->all();
        $carryOver = $rows->where('type', 'carry')->map($map)->values()->all();

        $totA = array_sum(array_column($newOrders, 'value'));
        $totB = array_sum(array_column($carryOver, 'value'));
        $totAll = $totA + $totB;

        // header boxes
        $criteria = [
            'price_agreed' => $r->input('price_agreed'),
            'consultant_approval' => $r->input('consultant_approval'),
            'percentage' => $r->input('percentage'),
        ];
        $forecast = [
            'month_target' => $r->input('month_target'),
            'required_turnover' => $r->input('required_turnover'),
            'required_forecast' => $r->input('required_forecast'),
            'conversion_ratio' => $r->input('conversion_ratio'),
        ];
        if (empty($forecast['month_target'])) {
            $defaults = ['Eastern' => 4200000, 'Central' => 4200000, 'Western' => 3000000];
            if (isset($defaults[$region])) $forecast['month_target'] = $defaults[$region];
        }

        $monthYear = sprintf('%s/%d', $month, $year);

        // 6) Render & save PDF, then stream
        $pdf = Pdf::loadView('forecast.pdf', compact(
            'region', 'monthYear', 'issuedBy', 'issuedDate', 'year', 'submissionDate',
            'criteria', 'forecast', 'newOrders', 'carryOver', 'totA', 'totB', 'totAll'
        ))->setPaper('a4', 'landscape');

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isUnicodeEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        // ensure /public/pdf exists & writable
        $dir = public_path('pdf');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $safeSales = preg_replace('/[^A-Za-z0-9]+/', '_', $salesman);
        $safeRegion = preg_replace('/[^A-Za-z0-9]+/', '_', $region);
        $fileName = "Forecast_{$safeSales}_{$safeRegion}_" . now()->toDateString() . ".pdf";

        try {
            $pdf->save($dir . DIRECTORY_SEPARATOR . $fileName);
        } catch (\Throwable $e) {
            \Log::error('PDF save failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $inlineError('PDF save failed', ['Check folder permissions for /public/pdf.'], 500);
        }

        return $pdf->stream($fileName);
    }

    /* ---------------------- PDF (download saved month) ---------------------- */
    public function pdfSaved(Request $r)
    {
        $user = $r->user();

        $region = (string) ($user->region ?? '');
        $salesman = (string) ($user->name ?? '');

        $year = (int) $r->input('year', now()->year);
        $monthNo = (int) $r->input('month_no', now()->month);
        if ($monthNo < 1 || $monthNo > 12) $monthNo = (int) now()->month;

        $month = (string) $r->input('month', '');
        if ($month === '') {
            $month = date('F', mktime(0, 0, 0, $monthNo, 1));
        }

        $mexpr = $this->monthNumberExpr();

        $rows = Forecast::query()
            ->when($salesman !== '', fn($q) => $q->where('salesman', $salesman))
            ->when($region !== '', fn($q) => $q->where('region', $region))
            ->where('year', $year)
            ->whereRaw("$mexpr = ?", [$monthNo])
            ->orderBy('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No forecast rows found for the selected month.',
            ], 404);
        }

        $map = fn($row) => [
            'customer'       => $row->customer_name,
            'product'        => $row->products,
            'project'        => $row->project_name,
            'quotation'      => $row->quotation_no,
            'percentage'     => $row->percentage,
            'product_family' => $row->product_family,
            'sales_source'   => $row->sales_source,
            'value'          => (float)$row->value_sar,
            'remarks'        => $row->remarks,
        ];

        $newOrders = $rows->where('type', 'new')->map($map)->values()->all();
        $carryOver = $rows->where('type', 'carry')->map($map)->values()->all();

        $totA = array_sum(array_column($newOrders, 'value'));
        $totB = array_sum(array_column($carryOver, 'value'));
        $totAll = $totA + $totB;

        $criteria = [
            'price_agreed' => '',
            'consultant_approval' => '',
            'percentage' => '',
        ];
        $forecast = [
            'month_target' => '',
            'required_turnover' => '',
            'required_forecast' => '',
            'conversion_ratio' => '',
        ];
        if (empty($forecast['month_target'])) {
            $defaults = ['Eastern' => 4200000, 'Central' => 4200000, 'Western' => 3000000];
            if (isset($defaults[$region])) $forecast['month_target'] = $defaults[$region];
        }

        $monthYear = sprintf('%s/%d', $month, $year);
        $issuedBy = $salesman ?: ($user->name ?? '');
        $issuedDate = now()->toDateString();
        $submissionDate = now()->toDateString();

        $pdf = Pdf::loadView('forecast.pdf', compact(
            'region', 'monthYear', 'issuedBy', 'issuedDate', 'year', 'submissionDate',
            'criteria', 'forecast', 'newOrders', 'carryOver', 'totA', 'totB', 'totAll'
        ))->setPaper('a4', 'landscape');

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isUnicodeEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $safeSales = preg_replace('/[^A-Za-z0-9]+/', '_', $salesman ?: 'Salesman');
        $safeRegion = preg_replace('/[^A-Za-z0-9]+/', '_', $region ?: 'Region');
        $fileName = "Forecast_{$safeSales}_{$safeRegion}_{$year}_{$monthNo}.pdf";

        return $pdf->download($fileName);
    }
    protected function packIssues(array $errors): array
    {
        $issues = [];
        foreach ($errors as $label => $msgs) {
            $section = str_contains($label, 'CARRY') ? 'carry' : 'new';
            preg_match('/Row\s+(\d+)/i', $label, $m);
            $idx = isset($m[1]) ? ((int)$m[1] - 1) : 0;
            $issues[] = [
                'section' => $section,
                'index' => $idx,
                'fields' => ['percentage', 'customer_name', 'project_name'],
                'messages' => array_values($msgs),
                'label' => $label,
            ];
        }
        return $issues;
    }




//    public function downloadTargets2026(Request $request)
//    {
//        $data = [
//            'year'      => 2026,
//            'region'    => $request->input('region', 'All Regions'),
//            'issuedBy'  => auth()->user()->name ?? '',
//            'forecast'  => [
//                'annual_target'     => 50_000_000,   // example
//                'required_turnover' => '…',
//            ],
//
//            'totAll'    => 50_000_000, // or sum of regions
//        ];
//
//        $pdf = Pdf::loadView('forecast.targets_2026', $data)
//            ->setPaper('a4', 'landscape');
//
//        return $pdf->download('forecast_targets_2026.pdf');
//    }



    /**
     * Canonical data builder for Targets 2026 (Portal + PDF).
     * Keeps defaults, but allows controller to override header + rows.
     */
//    public function buildTargets2026Data(Request $request, array $overrides = []): array
//    {
//        $user = $request->user();
//
//        // Base (from logged-in user)
//        $baseRegion = $user->region ?? 'All Regions';
//
//        // ✅ Allow override (manual form may pick region)
//        $region = $overrides['region'] ?? $baseRegion;
//
//        // ✅ Region-based annual target (adjust to your real numbers)
//        // You can replace this with DB/config later.
//        $annualTargetByRegion = [
//            'Eastern'     => 50_000_000,
//            'Central'     => 50_000_000,
//            'Western'     => 36_000_000,
//            'All Regions' => 50_000_000,
//        ];
//        $annualTarget = $annualTargetByRegion[$region] ?? 35_000_000;
//
//        // ✅ Rows coming from DB (portal view) - keep your real query later
//        $rowsA = []; // new orders
//        $rowsB = [];
//        $rowsC = [];
//        $rowsD = [];
//
//        // ✅ Merge overrides for rows if passed
//        if (array_key_exists('rowsA', $overrides)) $rowsA = (array) $overrides['rowsA'];
//        if (array_key_exists('rowsB', $overrides)) $rowsB = (array) $overrides['rowsB'];
//        if (array_key_exists('rowsC', $overrides)) $rowsC = (array) $overrides['rowsC'];
//        if (array_key_exists('rowsD', $overrides)) $rowsD = (array) $overrides['rowsD'];
//
//        // ✅ Criteria legend (used by PDF blade)
//        $criteriaLegend = [
//            'A' => 'Commercial matters agreed & MS approved',
//            'B' => 'Commercial matters agreed OR MS approved',
//            'C' => 'Neither commercial matters nor MS achieved',
//            'D' => 'Project is in bidding stage',
//        ];
//
//        return [
//            'year'           => $overrides['year']           ?? 2026,
//            'submissionDate' => $overrides['submissionDate'] ?? now()->format('Y-m-d'),
//            'region'         => $region,
//            'issuedBy'       => $overrides['issuedBy']       ?? ($user->name ?? ''),
//            'issuedDate'     => $overrides['issuedDate']     ?? now()->format('Y-m-d'),
//
//            'forecast' => array_merge([
//                'annual_target' => $annualTarget,
//            ], (array)($overrides['forecast'] ?? [])),
//
//            // ✅ Sections (your PDF blade already supports these)
//            'rowsA' => $rowsA,
//            'rowsB' => $rowsB,
//            'rowsC' => $rowsC,
//            'rowsD' => $rowsD,
//
//            // ✅ Make sure blade sees this name
//            'criteriaLegend' => $criteriaLegend,
//        ];
//    }

    /**
     * 1️⃣ Web page (HTML) – managers see the sheet and a Download button
     */
//    public function showTargets2026(Request $request)
//    {
//        $data = $this->buildTargets2026Data($request);
//
//        return view('forecast.targets_2026', $data + [
//                'showDownloadButton' => true,
//                'isPdf'              => false,
//            ]);
//    }

    /**
     * 2️⃣ GET: show manual entry form (create)
     */
//    public function createTargets2026(Request $request)
//    {
//        $user = $request->user();
//
//        return view('forecast.targets_2026_create', [
//            'year'       => 2026,
//            'region'     => $user->region ?? 'All Regions',
//            'issuedBy'   => $user->name ?? '',
//            'issuedDate' => now()->format('Y-m-d'),
//        ]);
//    }

    /**
     * 3️⃣ POST: Download PDF from manual form
     * Uses canonical builder then overrides headers + annual target + rowsA.
     *
     * ✅ FIX: Do NOT collapse/renumber rows. Keep blank rows so PDF row numbers match the form.
     *       Only trim trailing empty rows after the last filled row.
     */
//    public function downloadTargets2026FromForm(Request $request)
//    {
//        $validated = $request->validate([
//            'year'           => 'required|integer',
//            'region'         => 'required|string',
//            'issuedBy'       => 'required|string',
//            'issuedDate'     => 'required|date',
//            'submissionDate' => 'nullable|date',
//            'annual_target'  => 'required|numeric',
//
//            'orders'                     => 'array',
//            'orders.*.customer'          => 'nullable|string',
//            'orders.*.product'           => 'nullable|string',
//            'orders.*.project'           => 'nullable|string',
//            'orders.*.quotation'         => 'nullable|string',
//            'orders.*.value'             => 'nullable|numeric',
//            'orders.*.status'            => 'nullable|in:In-hand,Bidding',
//            'orders.*.forecast_criteria' => 'nullable|in:A,B,C,D',
//            'orders.*.remarks'           => 'nullable|string',
//        ]);
//
//        // ✅ Normalize rows but KEEP index positions (do not ->filter()->values())
//        $rawOrders = $request->input('orders', []);
//
//        $normalized = collect($rawOrders)->map(function ($row) {
//            return [
//                'customer'          => trim((string)($row['customer'] ?? '')),
//                'product'           => trim((string)($row['product'] ?? '')),
//                'project'           => trim((string)($row['project'] ?? '')),
//                'quotation'         => trim((string)($row['quotation'] ?? '')),
//                'value'             => ($row['value'] ?? null) !== null && $row['value'] !== ''
//                    ? (float) $row['value']
//                    : null,
//                'status'            => trim((string)($row['status'] ?? '')),
//                'forecast_criteria' => trim((string)($row['forecast_criteria'] ?? '')),
//                'remarks'           => trim((string)($row['remarks'] ?? '')),
//            ];
//        });
//
//        // ✅ Find last non-empty row so we keep blanks in between but remove blanks after last entry
//        $lastFilledIndex = -1;
//
//        foreach ($normalized as $idx => $r) {
//            $hasAny =
//                ($r['customer'] !== '')
//                || ($r['product'] !== '')
//                || ($r['project'] !== '')
//                || ($r['quotation'] !== '')
//                || ($r['value'] !== null)
//                || ($r['status'] !== '')
//                || ($r['forecast_criteria'] !== '')
//                || ($r['remarks'] !== '');
//
//            if ($hasAny) {
//                $lastFilledIndex = $idx;
//            }
//        }
//
//        // ✅ Keep up to last filled (preserve row order), else empty
//        $rowsA = $lastFilledIndex >= 0
//            ? $normalized->slice(0, $lastFilledIndex + 1)->values()->all()
//            : [];
//
//        // ✅ Build canonical base + override from form
//        $data = $this->buildTargets2026Data($request, [
//            'year'           => (int) $validated['year'],
//            'region'         => $validated['region'],
//            'issuedBy'       => $validated['issuedBy'],
//            'issuedDate'     => $validated['issuedDate'],
//            'submissionDate' => $validated['submissionDate'] ?? now()->format('Y-m-d'),
//            'forecast'       => [
//                'annual_target' => (float) $validated['annual_target'],
//            ],
//            'rowsA'          => $rowsA,
//        ]);
//
//        // ✅ Pick PDF view if exists else fallback to same view
//        $view = view()->exists('forecast.targets_2026_pdf')
//            ? 'forecast.targets_2026_pdf'
//            : 'forecast.targets_2026';
//
//        $pdf = Pdf::loadView($view, $data + [
//                'showDownloadButton' => false,
//                'isPdf'              => true,
//            ])
//            ->setPaper('a4', 'landscape');
//
//        return $pdf->download('annual_targets_2026.pdf');
//    }

















}





