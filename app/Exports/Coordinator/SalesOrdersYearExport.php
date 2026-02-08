<?php

namespace App\Exports\Coordinator;

use App\Models\SalesOrderLog;
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
     * Canonical salesman => aliases list.
     * (Keep aligned with coordinator filters)
     */
    private function salesmanAliasMap(): array
    {
        return [
            'SOHAIB'    => ['SOHAIB', 'SOAHIB', 'SOAIB', 'SOHIB', 'SOHAI', 'RAVINDER', 'WASEEM', 'FAISAL', 'CLIENT', 'EXPORT'],
            'TAREQ'     => ['TARIQ', 'TAREQ', 'TAREQ '],
            'JAMAL'     => ['JAMAL'],
            'ABU_MERHI' => ['M.ABU MERHI', 'M. ABU MERHI', 'M.MERHI', 'MERHI', 'ABU MERHI', 'M ABU MERHI', 'MOHAMMED'],
            'ABDO'      => ['ABDO', 'ABDO YOUSEF', 'ABDO YOUSSEF', 'ABDO YOUSIF'],
            'AHMED'     => ['AHMED', 'AHMED AMIN', 'AHMED AMEEN', 'AHMED AMIN ', 'AHMED AMIN.', 'AHMED AMEEN.', 'Ahmed Amin'],
        ];
    }

    /**
     * Region => allowed salesmen (aliases).
     */
    private function salesmenForRegionSelection(string $regionKey): array
    {
        $regionKey = strtolower(trim($regionKey));
        if ($regionKey === 'all' || $regionKey === '') {
            return [];
        }

        $regionMap = [
            'eastern' => ['SOHAIB'],
            'central' => ['TAREQ', 'JAMAL', 'ABU_MERHI'],
            'western' => ['ABDO', 'AHMED'],
        ];

        $aliasMap  = $this->salesmanAliasMap();
        $canonicals = $regionMap[$regionKey] ?? [];

        $names = [];
        foreach ($canonicals as $c) {
            $c = strtoupper(trim($c));
            foreach (($aliasMap[$c] ?? [$c]) as $a) {
                $names[] = strtoupper(trim((string)$a));
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

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

        $salesmanAliasMap = $this->salesmanAliasMap();

        // Factory → canonical salesmen mapping
        $factoryMap = [
            'Jubail'  => ['SOHAIB', 'TARIQ', 'JAMAL'],
            'Madinah' => ['AHMED', 'ABDO'],
        ];

        // Effective scope (RBAC + UI region)
        $effectiveSalesmen = $this->salesmenScope;
        $byRegion = $this->salesmenForRegionSelection($this->regionKey ?? 'all');
        if (!empty($byRegion)) {
            if (!empty($effectiveSalesmen)) {
                $set = array_flip($effectiveSalesmen);
                $effectiveSalesmen = array_values(array_filter($byRegion, fn ($x) => isset($set[$x])));
            } else {
                $effectiveSalesmen = $byRegion;
            }
        }

        for ($month = 1; $month <= 12; $month++) {

            $query = SalesOrderLog::coordinatorGroupedQuery($this->normalizedRegions)
                ->addSelect([
                    DB::raw("GROUP_CONCAT(DISTINCT s.`Ref.No.` ORDER BY s.`Ref.No.` SEPARATOR ', ') AS ref_no"),
                    DB::raw("MAX(s.`Cur`) AS cur"),
                    DB::raw("MAX(s.`Location`) AS location"),
                    DB::raw("MAX(s.`Project Location`) AS project_location"),
                    DB::raw("MAX(s.`Payment Terms`) AS payment_terms"),
                    DB::raw("MAX(s.`Sales OAA`) AS oaa"),
                    DB::raw("MAX(s.`Status`) AS status"),
                    DB::raw("MAX(s.`Remarks`) AS remarks"),
                    DB::raw("MAX(s.`rejected_at`) AS rejected_at"),
                ]);

            // ✅ Effective salesman scope (RBAC + UI region)
            if (!empty($effectiveSalesmen)) {
                $query->whereIn(DB::raw('UPPER(TRIM(s.`Sales Source`))'), $effectiveSalesmen);
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
                $query->whereIn(DB::raw('UPPER(TRIM(s.`Sales Source`))'), $aliasesUpper);
            } elseif ($this->factory && isset($factoryMap[$this->factory])) {
                $canonList = $factoryMap[$this->factory];
                $aliases = [];

                foreach ($canonList as $canon) {
                    $aliases = array_merge($aliases, $salesmanAliasMap[$canon] ?? [$canon]);
                }

                $aliasesUpper = array_values(array_unique(array_map(fn($v)=>strtoupper(trim((string)$v)), $aliases)));
                if (!empty($aliasesUpper)) {
                    $query->whereIn(DB::raw('UPPER(TRIM(s.`Sales Source`))'), $aliasesUpper);
                }
            }

            // ✅ Month/year filter
            $query->whereYear('date_rec', $this->year)
                ->whereMonth('date_rec', $month);

            // ✅ Optional global from/to inside each sheet query
            if ($this->from) $query->whereDate('date_rec', '>=', $this->from);
            if ($this->to)   $query->whereDate('date_rec', '<=', $this->to);

            $rows = collect($query->orderBy('po_date')->get());

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
