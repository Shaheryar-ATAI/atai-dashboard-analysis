<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// app/Models/ProjectStatusHistory.php
class ProjectStatusHistory extends Model {
    use HasFactory;
    protected $fillable = ['project_id','from_status','to_status','changed_by'];
    public function project(){ return $this->belongsTo(Project::class); }
}
