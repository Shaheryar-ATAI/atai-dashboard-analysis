<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Models\Project;
use App\Models\SalesOrderLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PowerBiApiController extends Controller
{
    public function projects(): JsonResponse
    {
        $projects = Project::select('project_name',
            'client_name',
            'project_location',
            'area',
            'quotation_no',
            'revision_no',
            'atai_products',
            'value_with_vat',
            'quotation_value',
            'status_current',
            'status',
            'last_comment',
            'project_type',
            'quotation_date',
            'technical_submittal',
            'date_rec',
            'action1',
            'salesperson',
            'salesman',
            'technical_base',
            'contact_person',
            'contact_number',
            'contact_email',
            'company_address',
            'created_by_id',
            'estimator_name',
            'coordinator_updated_by_id',
            'created_at',
            'updated_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        return response()->json($projects);
    }


    public function salesOrders(Request $request)
    {
        // If you want token protection, you can add it back:
        // $this->assertValidToken($request);

        $from = $request->date('from');
        $to   = $request->date('to');

        $query = SalesOrderLog::query();

        // 1) Use REAL column names, but alias them to *unique* names
        $query->selectRaw('
        id,
        `Quote No.`                          AS quote_no_raw,
        `PO. No.`                            AS po_no_raw,
        date_rec                             AS po_date_raw,
        `Client Name`                        AS client_name_raw,
        COALESCE(project_region, region, "") AS area_raw,
        Products                             AS product_family_raw,
        `PO Value`                           AS po_value_raw,
        `Sales Source`                       AS salesman_raw
    ');

        // 2) Only rows with real PO number and positive PO value
        $query->whereNotNull(DB::raw('`PO. No.`'))
            ->whereRaw('TRIM(`PO. No.`) <> ""')
            ->where(DB::raw('`PO Value`'), '>', 0);

        // 3) Optional date filters (based on date_rec)
        if ($from) {
            $query->whereDate('date_rec', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_rec', '<=', $to);
        }

        // 4) Map to clean JSON keys for Power BI
        $rows = $query
            ->orderByDesc('po_date_raw')
            ->get()
            ->map(function ($row) {
                return [
                    'id'             => (int) $row->id,
                    'quotation_no'   => $row->quote_no_raw ?: null,
                    'PO. No.'          => $row->po_no_raw ?: null,
                    'po_date'        => $row->po_date_raw
                        ? substr((string) $row->po_date_raw, 0, 10)
                        : null,
                    'client_name'    => $row->client_name_raw ?: null,
                    'area'           => $row->area_raw ?: null,
                    'product_family' => $row->product_family_raw ?: null,
                    'po_value'       => (float) $row->po_value_raw,
                    'salesman'       => $row->salesman_raw ?: null,
                ];
            });

        return response()->json($rows);
    }



    public function forecast(Request $request)
    {


        // Base query
        $query = Forecast::query();

        // Optional filters
        if ($region = $request->string('region')->trim()->value()) {
            $query->where('region', $region);
        }

        if ($salesman = $request->string('salesman')->trim()->value()) {
            $query->where('salesman', $salesman);
        }

        if ($year = $request->integer('year')) {
            $query->where('year', $year);
        }

        if ($month = $request->integer('month')) {
            $query->where('month_no', $month);
        }

        // Return selected columns only (Power BI friendly)
        $rows = $query->orderBy('id')->get([
            'id',
            'customer_name',
            'project_name',
            'quotation_no',
            'products',
            'product_family',
            'value_sar',
            'percentage',
            'probability_pct',
            'remarks',
            'type',
            'salesman',
            'region',
            'year',
            'month',
            'month_no',
            'created_at',
            'updated_at',
        ]);

        return response()->json($rows);
    }



}



