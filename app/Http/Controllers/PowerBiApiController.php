<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Models\Project;
use App\Models\SalesOrderLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PowerBiApiController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $year     = (int) $request->query('year', now()->year);
        $month    = (int) $request->query('month', now()->month);
        $area     = $request->query('area');      // Eastern/Central/Western
        $salesman = $request->query('salesman');  // e.g. SOHAIB

        // ✅ Build month boundaries in KSA time, then convert to UTC for DB filter
        $tz = 'Asia/Riyadh';

        $fromKsa = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
        $toKsa   = (clone $fromKsa)->endOfMonth()->setTime(23, 59, 59);

        $fromUtc = (clone $fromKsa)->utc();   // e.g. 2025-12-01 00:00 KSA -> UTC
        $toUtc   = (clone $toKsa)->utc();

        $q = Project::query()
            ->select(
                'project_name',
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
                'updated_at'
            )
            // ✅ Filter on UTC timestamps so KSA “Dec 11” (stored as Dec 10 21:00Z) is INCLUDED
            ->whereBetween('quotation_date', [$fromUtc, $toUtc]);

        if (!empty($area)) {
            $q->where('area', $area);
        }

        if (!empty($salesman)) {
            $sm = strtoupper(trim($salesman));
            $q->whereRaw("UPPER(TRIM(salesman)) = ?", [$sm]);
        }

        $projects = $q
            ->orderBy('quotation_date', 'asc')
            ->orderBy('quotation_no', 'asc')
            ->get();

        // ✅ Optional but strongly recommended: add KSA date column for Power BI slicing
        $projects->transform(function ($p) use ($tz) {
            $p->quotation_date_ksa = $p->quotation_date
                ? Carbon::parse($p->quotation_date)->timezone($tz)->toDateString()
                : null;

            $p->date_rec_ksa = $p->date_rec
                ? Carbon::parse($p->date_rec)->timezone($tz)->toDateString()
                : null;

            return $p;
        });

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



