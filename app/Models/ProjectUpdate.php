<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// app/Models/ProjectUpdate.php
class ProjectUpdate extends Model {
    use HasFactory;
    protected $fillable = ['project_id','changed_by','changes'];
    protected $casts = ['changes'=>'array'];
    public function project(){ return $this->belongsTo(Project::class); }
}
