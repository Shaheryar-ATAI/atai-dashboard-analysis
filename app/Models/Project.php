<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'project_name',
        'client_name',
        'project_location',
        'area',
        'quotation_no',
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
        'updated_at',

    ];

    protected $casts = [
        'quotation_date' => 'date',
        'date_rec'       => 'date',
        'value_with_vat' => 'float',
        'quotation_value'=> 'float',
    ];

    /* ---------- Canonical helpers ---------- */
    public function getCanonicalNameAttribute(): ?string
    {
        return $this->project_name ?: null;
    }

    public function getCanonicalClientAttribute(): ?string
    {
        return $this->client_name ?: null;
    }

    public function getCanonicalLocationAttribute(): ?string
    {
        return $this->project_location ?: null;
    }

    public function getCanonicalValueAttribute(): float
    {
        return (float) ($this->value_with_vat ?? $this->quotation_value ?? 0);
    }

    public function getQuotationDateYmdAttribute(): ?string
    {
        return optional($this->quotation_date)->format('Y-m-d');
    }

    /* ------------------------- Existing scopes (for user region, search, stale bidding) ------------------------- */

    public function scopeForUserRegion(Builder $q, $user): Builder
    {
        $isManager = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm','admin']);
        if (!$isManager && !empty($user->region)) {
            $q->where('area', $user->region);
        }
        return $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        $like = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $term).'%';
        $norm = strtoupper(preg_replace('/[\s\.\-\/]/', '', $term));

        return $q->where(function ($qq) use ($like, $norm) {
            $qq->where('projects.project_name', 'LIKE', $like)
                ->orWhere('projects.client_name', 'LIKE', $like)
                ->orWhere('projects.project_location', 'LIKE', $like)
                ->orWhere('projects.atai_products', 'LIKE', $like)
                ->orWhere('projects.salesman', 'LIKE', $like)
                ->orWhere('projects.status', 'LIKE', $like)
                ->orWhere('projects.status_current', 'LIKE', $like)
                ->orWhere('projects.quotation_no', 'LIKE', $like)
                ->orWhereRaw("
                    REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(projects.quotation_no)), ' ', ''), '-', ''), '.', ''), '/', '') = ?
                ", [$norm]);
        });
    }

    public function scopeStaleBidding(Builder $q): Builder
    {
        $threshold = now()->subMonths(3)->startOfDay();

        return $q
            ->whereRaw("LOWER(TRIM(project_type)) = 'bidding'")
            ->where(function ($qq) {
                $qq->whereNull('status')
                    ->orWhereRaw("TRIM(status) = ''");
            })
            ->whereNull('status_current')
            ->whereDate('quotation_date', '<=', $threshold)
            ->where('quotation_value','>=',500000);
    }

    /*
    |--------------------------------------------------------------------------
    | COORDINATOR HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Base query for coordinator scope (area = Eastern / Central / Western).
     * $regionsScope is array like ['eastern','central'].
     */
    public static function coordinatorBaseQuery(
        array $regionsScope,
        array $salesmenScope = []
    ): Builder {
        $normalizedRegions = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        $normalizedSalesmen = array_map(
            fn ($s) => strtoupper(trim($s)),
            $salesmenScope
        );

        return static::query()
            ->whereNull('deleted_at')
            ->whereNull('status')
            ->whereNull('status_current')
            ->whereIn('area', $normalizedRegions)
            ->when(!empty($normalizedSalesmen), function (Builder $q) use ($normalizedSalesmen) {
                $q->whereIn(
                    DB::raw('UPPER(TRIM(salesman))'),
                    $normalizedSalesmen
                );
            });
    }

    /**
     * Count of projects for coordinator regions.
     */
    public static function kpiProjectsCountForCoordinator(array $regionsScope): int
    {
        return static::coordinatorBaseQuery($regionsScope)->count();
    }

    /**
     * Quotation totals per area for stacked chart.
     * Returns array keyed by lowercase area: ['eastern' => 12345, ...]
     */
    public static function quotationTotalsByRegion(array $regionsScope): array
    {
        $normalized = array_map(
            fn ($r) => ucfirst(strtolower($r)),
            $regionsScope
        );

        return static::query()
            ->selectRaw('area, SUM(quotation_value) as total_q')
            ->whereIn('area', $normalized)
            ->groupBy('area')
            ->get()
            ->mapWithKeys(function ($row) {
                return [strtolower($row->area) => (float) $row->total_q];
            })
            ->toArray();
    }


    /*
  |--------------------------------------------------------------------------
  | SIMPLE ACCESSORS FOR COORDINATOR / VIEWS
  |--------------------------------------------------------------------------
  */

    public function getProjectAttribute(): ?string
    {
        return $this->project_name;
    }

    public function getClientAttribute(): ?string
    {
        return $this->client_name;
    }

    public function getLocationAttribute(): ?string
    {
        return $this->project_location;
    }

    public function getDateReceivedAttribute()
    {
        // used in coordinator view for date_received
        return $this->date_rec;
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getEstimatorAttribute(): ?string
    {
        // prefer relation, fallback to stored estimator_name
        return $this->creator->name ?? $this->estimator_name;
    }

    public function coordinatorUpdater()
        {
            return $this->belongsTo(User::class, 'coordinator_updated_by_id');
        }

    /**
     * Extract base project code from a quotation number.
     * Example: "S.4339.1A.3010.JK.R0" -> "S.4339"
     */
    public static function extractBaseCode(?string $quotationNo): ?string
    {
        if (!$quotationNo) {
            return null;
        }

        $parts = explode('.', $quotationNo);
        if (count($parts) >= 2) {
            return $parts[0] . '.' . $parts[1];   // "S.4339"
        }

        return $quotationNo;
    }



}
