<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSubmittal extends Model
{
    protected $fillable = [
        'project_id','phase','file_path','original_name','mime','size_bytes','uploaded_by'
    ];
}
