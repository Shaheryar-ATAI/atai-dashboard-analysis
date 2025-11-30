<?php

namespace App\Exports\Coordinator;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesOrdersYearExport implements WithMultipleSheets
{
    use Exportable;

    protected array $regionsScope;
    protected array $normalizedRegions;
    protected int $year;
    protected ?string $regionKey;
    protected ?string $from;
    protected ?string $to;

    /**
     * @param array       $regionsScope  e.g. ['eastern','central'] from coordinatorRegionScope()
     * @param int         $year          selected year
     * @param string|null $regionKey     'all' | 'eastern' | 'central' | 'western'
     * @param string|null $from          optional from-date (Y-m-d)
     * @param string|null $to            optional to-date   (Y-m-d)
     */
    public function __construct(
        array $regionsScope,
        int $year,
        ?string $regionKey = 'all',
        ?string $from = null,
        ?string $to = null
    ) {
        $this->regionsScope      = $regionsScope;
        $this->normalizedRegions = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        $this->year      = $year;
        $this->regionKey = $regionKey;
        $this->from      = $from;
        $this->to        = $to;
    }

    public function sheets(): array
    {
        $sheets = [];

        $regionLabel = ($this->regionKey && strtolower($this->regionKey) !== 'all')
            ? ucfirst(strtolower($this->regionKey)) . ' Region'
            : 'All Regions';

        // One sheet per month, using EXACTLY the same structure as exportSalesOrders()
        for ($month = 1; $month <= 12; $month++) {

            $query = DB::table('salesorderlog')
                ->whereNull('deleted_at')
                ->whereIn('region', $this->normalizedRegions)
                ->selectRaw("
                    `Client Name`      AS client,
                    `project_region`   AS area,
                    `Location`         AS location,
                    `date_rec`         AS date_rec,
                    `PO. No.`          AS po_no,
                    `Products`         AS atai_products,
                    `Products_raw`     AS products_raw,
                    `Quote No.`        AS quotation_no,
                    `Ref.No.`          AS ref_no,
                    `Cur`              AS cur,
                    `PO Value`         AS po_value,
                    `value_with_vat`   AS value_with_vat,
                    `Payment Terms`    AS payment_terms,
                    `Project Name`     AS project,
                    `Project Location` AS project_location,
                    `Status`           AS status,
                    `Sales OAA`        AS oaa,
                    `Job No.`          AS job_no,
                    `Factory Loc`      AS factory_loc,
                    `Sales Source`     AS salesman,
                    `Remarks`          AS remarks
                ");

            // same region dropdown logic as monthly
            if ($this->regionKey && strtolower($this->regionKey) !== 'all') {
                $query->where('project_region', ucfirst(strtolower($this->regionKey)));
            }

            // filter for THIS month of the selected year
            $query->whereYear('date_rec', $this->year)
                ->whereMonth('date_rec', $month);

            // optional global from/to (applied inside each month)
            if ($this->from) {
                $query->whereDate('date_rec', '>=', $this->from);
            }
            if ($this->to) {
                $query->whereDate('date_rec', '<=', $this->to);
            }

            $rows = collect($query->orderBy('date_rec')->get());

            // if you ever want to skip empty months, you can uncomment:
            // if ($rows->isEmpty()) continue;

            $sheets[] = new SalesOrdersMonthExport(
                $rows,
                $this->year,
                $month,
                $regionLabel
            );
        }

        return $sheets;
    }
}
