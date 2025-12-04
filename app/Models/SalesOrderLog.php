<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesOrderLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salesorderlog';

    protected $fillable = [
        'S.N',
        'Client Name',
        'region',
        'project_region',
        'Location',
        'date_rec',
        'PO. No.',
        'Products',
        'Products_raw',
        'Quote No.',
        'Ref.No.',
        'Cur',
        'PO Value',
        'value_with_vat',
        'Payment Terms',
        'Project Name',
        'Project Location',
        'Status',
        'Sales OAA',
        'Job No.',
        'Factory Loc',
        'Sales Source',
        'Remarks',
        'created_by_id',
    ];

    protected $casts = [
        'date_rec' => 'date',
        'PO Value' => 'decimal:2',
        'value_with_vat' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS – friendly attribute names for Blade & controllers
    |--------------------------------------------------------------------------
    */

    public function getClientAttribute()
    {
        return $this->{'Client Name'};
    }

    public function getProjectAttribute()
    {
        return $this->{'Project Name'};
    }

    public function getLocationAttribute()
    {
        return $this->{'Project Location'}  ?? null;
    }

    public function getAreaAttribute()
    {
        return $this->project_region;
    }

    /**
     * Salesman name – always from `Sales Source`
     */
    public function getSalesmanAttribute()
    {
        return $this->attributes['Sales Source'] ?? null;
    }

    public function getAtaiProductsAttribute()
    {
        return $this->Products;
    }

    public function getQuotationNoAttribute()
    {
        return $this->{'Quote No.'};
    }

    public function getPoNoAttribute()
    {
        return $this->{'PO. No.'};
    }

    public function getQuotationDateAttribute()
    {
        // Used like quotation date in UI
        return $this->date_rec;
    }

    public function getDateReceivedAttribute()
    {
        return $this->date_rec;
    }

    public function getPoDateAttribute()
    {
        return $this->date_rec;
    }

    public function getQuotationValueAttribute()
    {
        return $this->{'PO Value'};
    }

    public function getTotalPoValueAttribute()
    {
        return $this->{'PO Value'};
    }


    public function getPaymentTermsAttribute()
    {
        // read raw column `Payment Terms`
        return $this->attributes['Payment Terms'] ?? null;
    }

    public function getJobNoAttribute()
    {
        // read raw column `Job No.`
        return $this->attributes['Job No.'] ?? null;
    }

    public function getFactoryLocAttribute()
    {
        return $this->attributes['Factory Loc'] ?? null;
    }
    /**
     * OAA helper for Blade (we now store OAA text in Status column)
     */
    public function getOaaAttribute()
    {
        return $this->attributes['Status'] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | COORDINATOR HELPERS (for controller / KPIs / charts)
    |--------------------------------------------------------------------------
    */

    /**
     * Base query for coordinator scope by region.
     * $regionsScope is an array like ['eastern','central'].
     */
    public static function coordinatorBaseQuery(array $regionsScope): Builder
    {
        $normalized = array_map(
            fn($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        return static::query()
            ->whereIn('region', $normalized);

    }

    /**
     * KPIs (count + total PO value) for coordinator.
     */
    public static function kpisForCoordinator(array $regionsScope): array
    {
        $base = static::coordinatorBaseQuery($regionsScope);

        return [
            'count' => (clone $base)->count(),
            'value' => (clone $base)->sum('PO Value'),
        ];
    }

    public static function kpiProjectsCountForCoordinator(array $regionsScope): int
    {
        return static::coordinatorBaseQuery($regionsScope)->count();
    }

    /**
     * PO totals per region for stacked chart.
     * Returns array keyed by lowercase region: ['eastern' => 12345, ...]
     */
    public static function poTotalsByRegion(array $regionsScope): array
    {
        $normalized = array_map(
            fn($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        return static::query()
            ->selectRaw('region, SUM(`PO Value`) as total_po')
            ->whereIn('region', $normalized)
            ->groupBy('region')
            ->get()
            ->mapWithKeys(function ($row) {
                return [strtolower($row->region) => (float)$row->total_po];
            })
            ->toArray();
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function attachments()
    {
        return $this->hasMany(SalesOrderAttachment::class, 'salesorderlog_id');
    }



    /**
     * Coordinator view: group multiple quotations under same PO/Job into one row.
     */
    public static function coordinatorGroupedQuery(array $regionsScope)
    {
        $normalizedRegions = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        return DB::table('salesorderlog as s')
            ->leftJoin('users as u', 'u.id', '=', 's.created_by_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.project_region', $normalizedRegions)
            ->selectRaw("
                MIN(s.id) AS id,
                s.`PO. No.` AS po_no,
                GROUP_CONCAT(DISTINCT s.`Quote No.` ORDER BY s.`Quote No.` SEPARATOR ', ') AS quotation_no,
                s.`Job No.` AS job_no,
                s.`Project Name` AS project,
                s.`Client Name` AS client,
                s.`Sales Source` AS salesman,
                s.project_region AS area,
                s.`Products` AS atai_products,
                MAX(s.date_rec) AS po_date,
                SUM(s.`PO Value`) AS total_po_value,
                SUM(s.value_with_vat) AS value_with_vat,
                u.name AS created_by
            ")
            ->groupByRaw("
                s.`PO. No.`,
                s.`Job No.`,
                s.`Project Name`,
                s.`Client Name`,
                s.`Sales Source`,
                s.project_region,
                s.`Products`,
                u.name
            ");
    }




}
