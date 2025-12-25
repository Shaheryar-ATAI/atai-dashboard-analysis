<?php

namespace App\Exports\Coordinator;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesOrdersYearExport implements WithMultipleSheets
{
    use Exportable;

    /** @var array<string> */
    protected array $regionsScope;

    /** @var array<string> Normalized: ['Eastern','Central','Western'] */
    protected array $normalizedRegions;

    protected int $year;

    protected ?string $regionKey;
    protected ?string $from;
    protected ?string $to;

    protected ?string $salesman;   // canonical e.g. SOHAIB (or null/ALL)
    protected ?string $factory;    // Jubail | Madinah (or null)

    /** ✅ RBAC: allowed salesmen aliases (UPPER) e.g. ['ABDO','AHMED',...] */
    protected array $salesmenScope;

    /**
     * @param array       $regionsScope   e.g. ['eastern','central'] from coordinatorRegionScope()
     * @param int         $year           selected year
     * @param string|null $regionKey      'all' | 'eastern' | 'central' | 'western'
     * @param string|null $from           optional from-date (Y-m-d)
     * @param string|null $to             optional to-date   (Y-m-d)
     * @param string|null $salesman       optional canonical salesman (SOHAIB/TARIQ/...)
     * @param string|null $factory        optional factory (Jubail/Madinah)
     * @param array       $salesmenScope  ✅ allowed salesmen (aliases), empty => no restriction
     */
    public function __construct(
        array $regionsScope,
        int $year,
        ?string $regionKey = 'all',
        ?string $from = null,
        ?string $to = null,
        ?string $salesman = null,
        ?string $factory = null,
        array $salesmenScope = []
    ) {
        $this->regionsScope      = $regionsScope;
        $this->normalizedRegions = array_map(fn ($r) => ucfirst(strtolower($r)), $regionsScope);

        $this->year      = $year;
        $this->regionKey = $regionKey ? strtolower(trim($regionKey)) : 'all';
        $this->from      = $from ?: null;
        $this->to        = $to ?: null;

        $this->salesman  = $salesman ? strtoupper(trim($salesman)) : null;
        $this->factory   = $factory ? ucfirst(strtolower(trim($factory))) : null;

        $this->salesmenScope = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $salesmenScope
        ))));
    }

    public function sheets(): array
    {
        $sheets = [];

        $regionLabel = ($this->regionKey && $this->regionKey !== 'all')
            ? ucfirst($this->regionKey) . ' Region'
            : 'All Regions';

        // Alias map (expand if needed)
        $salesmanAliasMap = [
            'SOHAIB' => ['SOHAIB', 'SOAHIB'],
            'TARIQ'  => ['TARIQ', 'TAREQ'],
            'JAMAL'  => ['JAMAL'],
            'ABDO'   => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF'],
            'AHMED'  => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN'],
        ];

        // Factory → canonical salesmen mapping
        $factoryMap = [
            'Jubail'  => ['SOHAIB', 'TARIQ', 'JAMAL'],
            'Madinah' => ['AHMED', 'ABDO'],
        ];

        for ($month = 1; $month <= 12; $month++) {

            $query = DB::table('salesorderlog')
                ->whereNull('deleted_at')
                // ✅ IMPORTANT: scope by project_region (matches UI + export-month)
                ->whereIn('project_region', $this->normalizedRegions)
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

            // ✅ Region dropdown filter (still uses project_region)
            if ($this->regionKey && $this->regionKey !== 'all') {
                $query->where('project_region', ucfirst($this->regionKey));
            }

            // ✅ RBAC salesman scope (cannot be bypassed)
            if (!empty($this->salesmenScope)) {
                $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $this->salesmenScope);
            }

            /**
             * ✅ Optional salesman / factory filters
             * MUST be intersection-safe with RBAC:
             * - If salesman selected => apply aliases (still within RBAC due to above whereIn)
             * - Else if factory selected => apply factory aliases (still within RBAC)
             */
            if ($this->salesman && $this->salesman !== 'ALL') {
                $aliases = $salesmanAliasMap[$this->salesman] ?? [$this->salesman];
                $aliasesUpper = array_values(array_unique(array_map('strtoupper', $aliases)));

                // apply case-insensitive
                $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $aliasesUpper);
            } elseif ($this->factory && isset($factoryMap[$this->factory])) {
                $canonList = $factoryMap[$this->factory];
                $aliases = [];

                foreach ($canonList as $canon) {
                    $aliases = array_merge($aliases, $salesmanAliasMap[$canon] ?? [$canon]);
                }

                $aliasesUpper = array_values(array_unique(array_map(fn($v)=>strtoupper(trim((string)$v)), $aliases)));
                if (!empty($aliasesUpper)) {
                    $query->whereIn(DB::raw('UPPER(TRIM(`Sales Source`))'), $aliasesUpper);
                }
            }

            // ✅ Month/year filter
            $query->whereYear('date_rec', $this->year)
                ->whereMonth('date_rec', $month);

            // ✅ Optional global from/to inside each sheet query
            if ($this->from) $query->whereDate('date_rec', '>=', $this->from);
            if ($this->to)   $query->whereDate('date_rec', '<=', $this->to);

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
