<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForecastController extends Controller
{

    /* ---------------------- FORM ---------------------- */
    public function create()
    {
        return view('forecast.create', [
            'region'     => auth()->user()->region,
            'salesman'   => auth()->user()->name,
            'year'       => now()->year,
            'month_no'   => now()->month,
            'month'      => now()->format('F'),
            'issuedBy'   => auth()->user()->name ?? '—',
            'issuedDate' => now()->toDateString(),
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
        int $year, int $monthNo
    ): ?Forecast {
        $mexpr = $this->monthNumberExpr();

        return Forecast::query()
            ->where('salesman', $salesman)
            ->where('region',   $region)
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
        int $year, int $monthNo
    ): bool {
        $mexpr = $this->monthNumberExpr();

        return Forecast::query()
            ->where('salesman', $salesman)
            ->where('region',   $region)
            ->whereRaw('UPPER(TRIM(customer_name)) = ?', [$custKey])
            ->whereRaw('UPPER(TRIM(project_name))  = ?', [$projKey])
            ->where('year', $year)
            ->whereRaw("$mexpr = ?", [$monthNo])
            ->exists();
    }

    protected function monthLabel(?Forecast $f): ?string
    {
        if (!$f) return null;
        $m = $f->month ?: date('F', mktime(0,0,0, max(1,(int)$f->month_no), 10));
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
                $val = (float)($row['value_sar'] ?? 0);
                $pct = isset($row['percentage']) && $row['percentage'] !== '' ? (float)$row['percentage'] : null;
                $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);

                if (empty($row['customer_name']) && empty($row['products']) && empty($row['project_name']) && $val <= 0) {
                    return null;
                }
                return [
                    'customer_name'  => $row['customer_name']  ?? null,
                    'project_name'   => $row['project_name']   ?? null,
                    'quotation_no'   => $row['quotation_no']   ?? null,   // <— NEW
                    'products'       => $row['products']       ?? null,
                    'product_family' => $row['product_family'] ?? null,
                    'percentage'     => $pct,
                    'value_sar'      => $val,
                    'remarks'        => $row['remarks']        ?? null,
                ];
            }, $nested)));

            return $rows; // <-- IMPORTANT: stop here
        }

        // --- Fallback: parallel arrays (older form) ---
        $prefix    = $section;
        $customers = $r->input("{$prefix}_customer_name", []);
        $products  = $r->input("{$prefix}_products", []);
        $projects  = $r->input("{$prefix}_project_name", []);
        $quotes    = $r->input("{$prefix}_quotation_no", []);
        $values    = $r->input("{$prefix}_value_sar", []);
        $remarks   = $r->input("{$prefix}_remarks", []);
        $families  = $r->input("{$prefix}_product_family", []); // this line is fine
        $pcts      = $r->input("{$prefix}_percentage", []);

        $rows = [];
        $max  = max(count($customers), count($products), count($projects), count($values), count($remarks), count($families), count($pcts));

        for ($i = 0; $i < $max; $i++) {
            $row = [
                'customer_name'  => trim((string)($customers[$i] ?? '')),
                'products'       => trim((string)($products[$i]  ?? '')),
                'project_name'   => trim((string)($projects[$i]  ?? '')),
                'percentage'     => isset($pcts[$i]) && $pcts[$i] !== '' ? (float)$pcts[$i] : null,
                'value_sar'      => (float)($values[$i] ?? 0),
                'quotation_no'   => trim((string)($quotes[$i]    ?? '')),
                'remarks'        => trim((string)($remarks[$i]  ?? '')),
                'product_family' => trim((string)($families[$i] ?? '')),
            ];
            $empty = $row['customer_name'] === '' && $row['products'] === '' && $row['project_name'] === '' && $row['value_sar'] <= 0;
            if (!$empty) $rows[] = $row;
        }
        return $rows;
    }
    private const QTN_RE = '/^[A-Z]\.\d{4}\.\d\.\d{4}\.[A-Z]{2}\.R\d+$/';

    protected function validateRows(
        array $rowsNew,
        array $rowsCarry,
        int $year,
        int $monthNo,
        string $salesman,
        string $region,
        bool $allowSameMonthReplace = false
    ): array {
        $errors = [];

        // Track pairs present in "new" to forbid the same pair in "carry"
        $pairsNew = [];
        foreach ($rowsNew as $r) {
            $ck = $this->norm($r['customer_name'] ?? '');
            $pk = $this->norm($r['project_name']  ?? '');
            if ($ck && $pk) $pairsNew["$ck|$pk"] = true;
        }

        // Track duplicates within the current submission
        $seenSubmission = [];

        $validateOne = function (array $row, string $type, int $i)
        use (&$errors, &$seenSubmission, $pairsNew, $salesman, $region, $year, $monthNo, $allowSameMonthReplace)
        {
            $custRaw = $row['customer_name'] ?? '';
            $projRaw = $row['project_name']  ?? '';
            $custKey = $this->norm($custRaw);
            $projKey = $this->norm($projRaw);
            $label   = strtoupper($type) . ' Row ' . ($i + 1);

            if ($custKey === '' || $projKey === '') {
                $errors[$label][] = 'Customer and Project are required.';
                return;
            }

            // Quotation format check (advisory server-side)
            $qtn = trim((string)($row['quotation_no'] ?? ''));
            if ($qtn !== '' && !preg_match(self::QTN_RE, $qtn)) {
                $errors[$label][] = 'Quotation No. must match S.0000.0.0000.XX.R0 (e.g., S.4135.1.2605.MH.R0).';
            }

            // De-dup within this submission
            $pkey = "$custKey|$projKey";
            if (isset($seenSubmission[$pkey])) {
                $errors[$label][] = 'Duplicate entry in this submission for the same Customer/Project.';
                return;
            }
            $seenSubmission[$pkey] = true;

            // Percentage rules
            if (!is_numeric($row['percentage'] ?? null)) {
                $errors[$label][] = 'Percentage is required and must be numeric.';
                return;
            }
            $pct = (float) $row['percentage'];
            if ($pct < 75) {
                $errors[$label][] = 'Percentage must be at least 75%.';
            }

            // Historical checks
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
                if (!$prior) {
                    $errors[$label][] = 'No previous forecast found for you. This looks NEW — put it in New Orders.';
                    return;
                }
                $lastPct = (float) ($prior->percentage ?? 0);
                if ($pct <= $lastPct) {
                    $when = $this->monthLabel($prior);
                    $errors[$label][] = "Percentage must be greater than last time ({$lastPct}% in {$when}).";
                }
            }
        };

        foreach ($rowsNew as $i => $r)   { $validateOne($r, 'new',   $i); }
        foreach ($rowsCarry as $i => $r) { $validateOne($r, 'carry', $i); }

        return $errors;
    }


    protected function replaceSheet(
        string $salesman, string $region, int $year, int $monthNo, string $month,
        array $rowsNew, array $rowsCarry
    ): int {
        $now = now();
        $payload = [];

        $push = function(array $row, string $type) use (&$payload, $salesman, $region, $year, $monthNo, $month, $now) {
            $payload[] = [
                'customer_name' => $row['customer_name'] ?? null,
                'project_name'  => $row['project_name']  ?? null,
                'quotation_no'  => $row['quotation_no'] ?? null,
                'products'      => $row['products']      ?? null,
                'product_family'=> $row['product_family']?? null,
                'value_sar'     => (float)($row['value_sar'] ?? 0),
                'percentage'    => isset($row['percentage']) ? (int)$row['percentage'] : null,
                'remarks'       => $row['remarks']       ?? null,
                'type'          => $type,
                'salesman'      => $salesman,
                'region'        => $region,
                'month'         => $month,
                'month_no'      => $monthNo,
                'year'          => $year,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        };

        foreach ($rowsNew as $r)   $push($r, 'new');
        foreach ($rowsCarry as $r) $push($r, 'carry');

        DB::transaction(function () use ($salesman, $region, $year, $monthNo, $payload) {
            Forecast::where('salesman', $salesman)
                ->where('region',   $region)
                ->where('year',     $year)
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
        $user     = auth()->user();
        $region   = $user->region ?? '';
        $salesman = $user->name   ?? '';

        $year    = (int) $r->input('year', now()->year);
        $monthNo = (int) $r->input('month_no', now()->month);
        $month   = (string) $r->input('month', now()->format('F'));

        // --- extract + filter out blank rows ------------------------------------
        $rowsNewRaw   = $this->extractRows($r, 'new');   // expect array of assoc rows
        $rowsCarryRaw = $this->extractRows($r, 'carry'); // expect array of assoc rows

        $hasData = function (array $row): bool {
            $val = (float) preg_replace('/[^\d.\-]/', '', (string)($row['value_sar'] ?? '0'));
            return
                trim((string)($row['customer_name']  ?? '')) !== '' ||
                trim((string)($row['products']       ?? '')) !== '' ||
                trim((string)($row['project_name']   ?? '')) !== '' ||
                trim((string)($row['quotation_no']   ?? '')) !== '' ||
                trim((string)($row['product_family'] ?? '')) !== '' ||
                trim((string)($row['remarks']        ?? '')) !== '' ||
                (isset($row['percentage']) && $row['percentage'] !== '' && $row['percentage'] !== null) ||
                $val > 0;
        };

        $normalize = function (array $row): array {
            // Trim strings
            foreach (['customer_name','products','project_name','quotation_no','product_family','remarks'] as $k) {
                if (isset($row[$k])) { $row[$k] = trim((string)$row[$k]); }
            }
            // Clean numbers
            if (isset($row['value_sar'])) {
                $row['value_sar'] = (float) preg_replace('/[^\d.\-]/', '', (string)$row['value_sar']);
            }
            if (isset($row['percentage']) && $row['percentage'] !== '') {
                $row['percentage'] = (int) $row['percentage'];
            }
            return $row;
        };

        $rowsNew   = array_values(array_map($normalize, array_filter($rowsNewRaw,   $hasData)));
        $rowsCarry = array_values(array_map($normalize, array_filter($rowsCarryRaw, $hasData)));

        // --- block empty submissions --------------------------------------------
        if (empty($rowsNew) && empty($rowsCarry)) {
            return response()->json([
                'ok'     => false,
                'issues' => [[
                    'section'  => 'new',
                    'index'    => 0,
                    'messages' => ['Please add at least one row with data before saving.']
                ]]
            ], 422);
        }

        // --- validate (reuse your validator) ------------------------------------
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
        $authRegion  = auth()->user()->region ?? '--';
        $authSales   = auth()->user()->name   ?? '—';

        $region         = (string) $r->input('region',   $authRegion);
        $salesman       = (string) $r->input('salesman', $authSales);
        $year           = (int)    $r->input('year',     now()->year);
        $monthNo        = (int)    $r->input('month_no', now()->month);
        $month          = (string) $r->input('month',    now()->format('F'));
        $issuedBy       = (string) $r->input('issuedBy', $salesman);
        $issuedDate     = (string) $r->input('issuedDate', now()->toDateString());
        $submissionDate = now()->toDateString();

        // helper to show a small inline HTML error page (works in target=_blank tab)
        $inlineError = function (string $title, array $messages, int $status = 422) {
            $lis = '';
            foreach ($messages as $m) {
                if (is_array($m)) {
                    foreach (($m['messages'] ?? []) as $mm) { $lis .= '<li>'.e($mm).'</li>'; }
                } else {
                    $lis .= '<li>'.e($m).'</li>';
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
        $rowsNewPosted   = $this->extractRows($r, 'new');
        $rowsCarryPosted = $this->extractRows($r, 'carry');

        // 3) Validate with the *posted* arrays (IMPORTANT: include salesman/region)
        $errors = $this->validateRows($rowsNewPosted, $rowsCarryPosted, $year, $monthNo, $salesman, $region, true);
        if (!empty($errors)) {
            // convert to UI-friendly list for the small error page
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
            ->where('region',   $region)
            ->where('year',     $year)
            ->where('month_no', $monthNo)
            ->orderBy('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return $inlineError('Nothing to print', [['messages' => ['No rows found for this month after save.']]], 422);
        }

        $map = fn($row) => [
            'customer'   => $row->customer_name,
            'product'    => $row->products,
            'project'    => $row->project_name,
            'quotation'  => $row->quotation_no,
            'value'      => (float) $row->value_sar,
            'percentage' => $row->percentage,
            'remarks'    => $row->remarks,
        ];
        $newOrders = $rows->where('type', 'new')->map($map)->values()->all();
        $carryOver = $rows->where('type', 'carry')->map($map)->values()->all();

        $totA   = array_sum(array_column($newOrders,  'value'));
        $totB   = array_sum(array_column($carryOver,  'value'));
        $totAll = $totA + $totB;

        // header boxes
        $criteria = [
            'price_agreed'        => $r->input('price_agreed'),
            'consultant_approval' => $r->input('consultant_approval'),
            'percentage'          => $r->input('percentage'),
        ];
        $forecast = [
            'month_target'      => $r->input('month_target'),
            'required_turnover' => $r->input('required_turnover'),
            'required_forecast' => $r->input('required_forecast'),
            'conversion_ratio'  => $r->input('conversion_ratio'),
        ];
        if (empty($forecast['month_target'])) {
            $defaults = ['Eastern' => 3000000, 'Central' => 3100000, 'Western' => 2500000];
            if (isset($defaults[$region])) $forecast['month_target'] = $defaults[$region];
        }

        $monthYear = sprintf('%s/%d', $month, $year);

        // 6) Render & save PDF, then stream
        $pdf = Pdf::loadView('forecast.pdf', compact(
            'region','monthYear','issuedBy','issuedDate','year','submissionDate',
            'criteria','forecast','newOrders','carryOver','totA','totB','totAll'
        ))->setPaper('a4', 'landscape');

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'isUnicodeEnabled'     => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        // ensure /public/pdf exists & writable
        $dir = public_path('pdf');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $safeSales  = preg_replace('/[^A-Za-z0-9]+/', '_', $salesman);
        $safeRegion = preg_replace('/[^A-Za-z0-9]+/', '_', $region);
        $fileName   = "Forecast_{$safeSales}_{$safeRegion}_" . now()->toDateString() . ".pdf";

        try {
            $pdf->save($dir . DIRECTORY_SEPARATOR . $fileName);
        } catch (\Throwable $e) {
            \Log::error('PDF save failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $inlineError('PDF save failed', ['Check folder permissions for /public/pdf.'], 500);
        }

        return $pdf->stream($fileName);
    }



    protected function packIssues(array $errors): array
    {
        $issues = [];
        foreach ($errors as $label => $msgs) {
            $section = str_contains($label, 'CARRY') ? 'carry' : 'new';
            preg_match('/Row\s+(\d+)/i', $label, $m);
            $idx = isset($m[1]) ? ((int)$m[1] - 1) : 0;
            $issues[] = [
                'section'  => $section,
                'index'    => $idx,
                'fields'   => ['percentage','customer_name','project_name'],
                'messages' => array_values($msgs),
                'label'    => $label,
            ];
        }
        return $issues;
    }
}
