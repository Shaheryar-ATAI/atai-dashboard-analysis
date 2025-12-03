<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BncProject extends Model
{
    use HasFactory;

    // Internal import table, so it's safe to allow all columns
    protected $guarded = [];

    protected $casts = [
        'value_usd'           => 'decimal:2',
        'award_date'          => 'date',
        'expected_close_date' => 'date',
        'bnc_exported_at'     => 'datetime',
        'approached'          => 'boolean',
        'boq_shared'          => 'boolean',
        'submittal_shared'    => 'boolean',
        'submittal_approved'  => 'boolean',
        'penetration_percent' => 'integer',
    ];

    // Relationships
    public function responsibleSalesman()
    {
        return $this->belongsTo(User::class, 'responsible_salesman_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
