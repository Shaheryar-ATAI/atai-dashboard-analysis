<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            'month'      => now()->format('F'), // Full month name, matches your table examples
            'issuedBy'   => auth()->user()->name ?? '—',
            'issuedDate' => now()->toDateString(),
        ]);
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    /**
     * Extract rows for one section ("new" or "carry") from the request.
     * Accepts BOTH shapes:
     * 1) Nested objects: new_orders[0][customer_name], ...
     * 2) Parallel arrays: new_customer_name[], new_products[], ...
     */
    protected function extractRows(Request $r, string $section): array
    {
        // 1) Nested
        $nestedKey = $section === 'new' ? 'new_orders' : 'carry_over';
        $nested = $r->input($nestedKey, []);
        if (is_array($nested) && isset($nested[0]) && is_array($nested[0])) {
            return array_values(array_filter(array_map(function ($row) {
                $val = (float)($row['value_sar'] ?? 0);
                $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);
                // Skip empty rows
                if (
                    empty($row['customer_name']) &&
                    empty($row['products']) &&
                    empty($row['project_name']) &&
                    $val <= 0
                ) {
                    return null;
                }
                return [
                    'customer_name' => $row['customer_name'] ?? null,
                    'project_name'  => $row['project_name']  ?? null,
                    'products'      => $row['products']      ?? null,
                    'product_family'=> $row['product_family']?? null,
                    'value_sar'     => $val,
                    'remarks'       => $row['remarks']       ?? null,
                ];
            }, $nested)));
        }

        // 2) Parallel arrays
        $prefix = $section; // 'new' or 'carry'
        $customers = $r->input("{$prefix}_customer_name", []);
        $products  = $r->input("{$prefix}_products", []);
        $projects  = $r->input("{$prefix}_project_name", []);
        $values    = $r->input("{$prefix}_value_sar", []);
        $remarks   = $r->input("{$prefix}_remarks", []);
        $families  = $r->input("{$prefix}_product_family", []);

        $rows = [];
        $max  = max(
            count($customers), count($products), count($projects),
            count($values), count($remarks), count($families)
        );

        for ($i = 0; $i < $max; $i++) {
            $row = [
                'customer_name' => trim((string)($customers[$i] ?? '')),
                'products'      => trim((string)($products[$i]  ?? '')),
                'project_name'  => trim((string)($projects[$i]  ?? '')),
                'value_sar'     => (float)($values[$i] ?? 0),
                'remarks'       => trim((string)($remarks[$i]  ?? '')),
                'product_family'=> trim((string)($families[$i] ?? '')),
            ];
            $empty = $row['customer_name'] === '' &&
                $row['products']      === '' &&
                $row['project_name']  === '' &&
                $row['value_sar']     <= 0;
            if (!$empty) $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Upsert a whole sheet for the (salesman, region, year, month_no).
     * Wipes the previous set then inserts the new one atomically.
     */
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
                'products'      => $row['products']      ?? null,
                'product_family'=> $row['product_family']?? null,
                'value_sar'     => (float)($row['value_sar'] ?? 0),
                'remarks'       => $row['remarks']       ?? null,
                'type'          => $type, // 'new' or 'carry'
                'salesman'      => $salesman,
                'region'        => $region,
                'month'         => $month,    // e.g. "October"
                'month_no'      => $monthNo,  // 1-12
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

    /* ---------------------- SAVE (AJAX or form submit) ---------------------- */
    public function save(Request $r)
    {
        $region    = auth()->user()->region;
        $salesman  = auth()->user()->name;
        $year      = (int)$r->input('year', now()->year);
        $monthNo   = (int)$r->input('month_no', now()->month+1);
        // Prefer full month name to match your previous imports
        $month     = (string)$r->input('month', now()->format('F'));

        $rowsNew   = $this->extractRows($r, 'new');
        $rowsCarry = $this->extractRows($r, 'carry');

        $saved = $this->replaceSheet($salesman, $region, $year, $monthNo, $month, $rowsNew, $rowsCarry);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    /* ---------------------- PDF (save + render + write file) ---------------------- */
    public function pdf(Request $r)
    {
        // 1) Meta (prefer logged-in user; allow overrides coming from request)
        $authRegion  = auth()->user()->region ?? 'Eastern';
        $authSales   = auth()->user()->name   ?? '—';

        $region         = (string) $r->input('region',   $authRegion);
        $salesman       = (string) $r->input('salesman', $authSales);
        $year           = (int)    $r->input('year',     now()->year);
        $monthNo        = (int)    $r->input('month_no', now()->month+1);
        $month          = (string) $r->input('month',    now()->format('F'));
        $issuedBy       = (string) $r->input('issuedBy', $salesman);
        $issuedDate     = (string) $r->input('issuedDate', now()->toDateString());
        $submissionDate = now()->toDateString();

        // 2) If rows are posted with the PDF request, save them first
        $rowsNewPosted   = $this->extractRows($r, 'new');
        $rowsCarryPosted = $this->extractRows($r, 'carry');
        if (!empty($rowsNewPosted) || !empty($rowsCarryPosted)) {
            $this->replaceSheet($salesman, $region, $year, $monthNo, $month, $rowsNewPosted, $rowsCarryPosted);
        }

        // 3) Fetch the finalized rows from DB
        $rows = Forecast::query()
            ->where('salesman', $salesman)
            ->where('region',   $region)
            ->where('year',     $year)
            ->where('month_no', $monthNo)
            ->orderBy('created_at')   // table may have no auto-increment id
            ->get();

        // 4) Transform rows for Blade
        $map = fn($row) => [
            'customer' => $row->customer_name,
            'product'  => $row->products,
            'project'  => $row->project_name,
            'value'    => (float) $row->value_sar,
            'remarks'  => $row->remarks,
        ];
        $newOrders = $rows->where('type', 'new')->map($map)->values()->all();
        $carryOver = $rows->where('type', 'carry')->map($map)->values()->all();

        // 5) Totals now reflect the actual DB data
        $totA   = array_sum(array_column($newOrders,  'value'));
        $totB   = array_sum(array_column($carryOver,  'value'));
        $totAll = $totA + $totB;

        // 6) Header/criteria/forecast boxes
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

        // 6a) Default Month Target by region if blank
        if (empty($forecast['month_target'])) {
            $defaults = [
                'Eastern' => 3000000, // 3.0M
                'Central' => 3100000, // 3.1M
                'Western' => 2500000, // 2.5M
            ];
            if (isset($defaults[$region])) {
                $forecast['month_target'] = $defaults[$region];
            }
        }

        $monthYear = sprintf('%s/%d', $month, $year);

        // 7) Render + Save
        $pdf = Pdf::loadView('forecast.pdf', compact(
            'region','monthYear','issuedBy','issuedDate','year','submissionDate',
            'criteria','forecast','newOrders','carryOver','totA','totB','totAll'
        ))->setPaper('a4', 'landscape');

        // save file to public/pdf
        $safeSales  = preg_replace('/[^A-Za-z0-9]+/', '_', $salesman);
        $safeRegion = preg_replace('/[^A-Za-z0-9]+/', '_', $region);
        $fileName   = "Forecast_{$safeSales}_{$safeRegion}_" . now()->toDateString() . ".pdf";
        $dir        = public_path('pdf');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isUnicodeEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $pdf->save($dir . DIRECTORY_SEPARATOR . $fileName);

        return $pdf->stream($fileName);
    }

}
