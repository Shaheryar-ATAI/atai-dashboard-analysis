<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    // Relationships
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    /* -------------------------
     | Canonical accessors
     |--------------------------*/
    public function getCanonicalNameAttribute(): ?string
    {
        return $this->project_name ?: $this->name;
    }

    public function getCanonicalClientAttribute(): ?string
    {
        return $this->client_name ?: $this->client;
    }

    public function getCanonicalLocationAttribute(): ?string
    {
        return $this->project_location ?: $this->location;
    }

    /** Prefer quotation_value, fall back to price */
    public function getCanonicalValueAttribute(): float
    {
        return (float) ($this->quotation_value ?? $this->price ?? 0);
    }

    /** Safe date string for UI */
    public function getQuotationDateYmdAttribute(): ?string
    {
        return optional($this->quotation_date)->format('Y-m-d');
    }

    /* -------------------------
     | Scopes
     |--------------------------*/
    /** Limit to user’s region unless GM/Admin */
    public function scopeForUserRegion(Builder $q, $user): Builder
    {
        if (!(method_exists($user, 'hasRole') && $user->hasRole(['gm', 'admin'])) && !empty($user->region)) {
            $q->where('area', $user->region);
        }
        return $q;
    }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('status', $status) : $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $s = trim((string) $term);
        if ($s === '') return $q;

        return $q->where(function ($qq) use ($s) {
            $qq->where('project_name', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('client_name', 'like', "%{$s}%")
                ->orWhere('client', 'like', "%{$s}%")
                ->orWhere('project_location', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")
                ->orWhere('area', 'like', "%{$s}%")
                ->orWhere('quotation_no', 'like', "%{$s}%")
                ->orWhere('atai_products', 'like', "%{$s}%");
        });
    }

    /** Your existing helper used in the list */
    public function progressPercent(): int
    {
        // keep your real logic here; fallback 0
        return (int) ($this->attributes['progress_percent'] ?? 0);
    }
}
