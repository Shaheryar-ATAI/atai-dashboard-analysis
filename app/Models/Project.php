<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /** Real DB columns so imports & upserts are safe */
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
        'date_rec',
        'action1',
        'salesperson',
        'salesman',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'date_rec'       => 'date',
        'value_with_vat' => 'float',
        'quotation_value'=> 'float',
    ];

    /* ---------- Canonical helpers (used by details & fallbacks) ---------- */
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

    /* ------------------------- Scopes ------------------------- */
    /** Limit to user’s region unless GM/Admin */
    public function scopeForUserRegion(Builder $q, $user): Builder
    {
        $isManager = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['gm','admin']);
        if (!$isManager && !empty($user->region)) {
            $q->where('area', $user->region);
        }
        return $q;
    }

    /** Global search used by DataTables */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $s = trim((string)$term);
        if ($s === '') return $q;

        return $q->where(function ($w) use ($s) {
            $w->where('project_name', 'like', "%{$s}%")
                ->orWhere('client_name', 'like', "%{$s}%")
                ->orWhere('project_location', 'like', "%{$s}%")
                ->orWhere('area', 'like', "%{$s}%")
                ->orWhere('quotation_no', 'like', "%{$s}%")
                ->orWhere('atai_products', 'like', "%{$s}%");
        });
    }
}
