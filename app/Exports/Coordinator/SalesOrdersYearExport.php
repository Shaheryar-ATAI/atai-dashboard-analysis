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

    // ✅ NEW
    protected ?string $salesman;   // canonical e.g. SOHAIB (or null/ALL)
    protected ?string $factory;    // Jubail | Madinah (or null)

    /**
     * @param array       $regionsScope  e.g. ['eastern','central'] from coordinatorRegionScope()
     * @param int         $year          selected year
     * @param string|null $regionKey     'all' | 'eastern' | 'central' | 'western'
     * @param string|null $from          optional from-date (Y-m-d)
     * @param string|null $to            optional to-date   (Y-m-d)
     * @param string|null $salesman      optional canonical salesman (SOHAIB/TARIQ/...)
     * @param string|null $factory       optional factory (Jubail/Madinah)
     */
    public function __construct(
        array $regionsScope,
        int $year,
        ?string $regionKey = 'all',
        ?string $from = null,
        ?string $to = null,
        ?string $salesman = null,
        ?string $factory = null
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

        $this->salesman  = $salesman ? strtoupper(trim($salesman)) : null;
        $this->factory   = $factory ? ucfirst(strtolower(trim($factory))) : null; // Jubail/Madinah
    }

    public function sheets(): array
    {
        $sheets = [];

        $regionLabel = ($this->regionKey && strtolower($this->regionKey) !== 'all')
            ? ucfirst(strtolower($this->regionKey)) . ' Region'
            : 'All Regions';

        // Same alias map as monthly exportSalesOrders()
        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO'],
            'AHMED'  => ['AHMED'],
        ];

        // ✅ Factory → canonical salesmen mapping (same as frontend chips)
        $factoryMap = [
            'Jubail'  => ['SOHAIB', 'TARIQ', 'JAMAL'],
            'Madinah' => ['AHMED', 'ABDO'],
        ];

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

            // ✅ Region dropdown logic (same as monthly)
            if ($this->regionKey && strtolower($this->regionKey) !== 'all') {
                $query->where('project_region', ucfirst(strtolower($this->regionKey)));
            }

            // ✅ Salesman filter (same as monthly)
            // If a salesman is selected (not ALL), apply aliases
            if ($this->salesman && $this->salesman !== 'ALL') {
                $aliases = $salesmanAliasMap[$this->salesman] ?? [$this->salesman];
                $query->whereIn('Sales Source', $aliases);
            }
            // ✅ Else apply factory filter (only when salesman is ALL/empty)
            elseif ($this->factory && isset($factoryMap[$this->factory])) {
                $canonList = $factoryMap[$this->factory];
                $aliases = [];
                foreach ($canonList as $canon) {
                    $aliases = array_merge($aliases, $salesmanAliasMap[$canon] ?? [$canon]);
                }
                $aliases = array_values(array_unique($aliases));
                if (!empty($aliases)) {
                    $query->whereIn('Sales Source', $aliases);
                }
            }

            // ✅ Month/year filter
            $query->whereYear('date_rec', $this->year)
                ->whereMonth('date_rec', $month);

            // ✅ Optional global from/to inside each sheet query
            if ($this->from) {
                $query->whereDate('date_rec', '>=', $this->from);
            }
            if ($this->to) {
                $query->whereDate('date_rec', '<=', $this->to);
            }

            $rows = collect($query->orderBy('date_rec')->get());

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
