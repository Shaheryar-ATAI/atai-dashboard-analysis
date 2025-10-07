<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// app/Models/ProjectNote.php
class ProjectNote extends Model {
    use HasFactory;

    protected $fillable = ['project_id','note','created_by'];
    public function project(){ return $this->belongsTo(Project::class); }
}
