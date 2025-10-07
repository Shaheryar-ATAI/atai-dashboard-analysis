<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectChecklistState extends Model {
    use HasFactory;
    protected $fillable = ['project_id','mep_contractor_appointed','boq_quoted','boq_submitted','priced_at_discount'];
    public $timestamps = true;

    protected $casts = [
        'mep_contractor_appointed' => 'bool',
        'boq_quoted'               => 'bool',
        'boq_submitted'            => 'bool',
        'priced_at_discount'       => 'bool',
    ];

    public function project(){ return $this->belongsTo(Project::class); }
}
