<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReportItem extends Model
{
// App\Models\WeeklyReportItem
    protected $fillable = [
        'weekly_report_id','row_no','customer_name','project_name','quotation_no', 'project_location',
        'value_sar','project_status','contact_name','contact_mobile_e164','visit_date','notes'
    ];

    protected $casts = ['visit_date' => 'date'];

    public function report()
    {
        return $this->belongsTo(WeeklyReport::class, 'weekly_report_id');
    }
}
