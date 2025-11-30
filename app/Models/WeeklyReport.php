<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReport extends Model
{
    // App\Models\WeeklyReport
    protected $fillable = ['user_id','engineer_name', 'week_start'];



    public function items()
    {
        return $this->hasMany(WeeklyReportItem::class)->orderBy('row_no');
    }
}
