<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderLogProductBreakdown extends Model
{
    protected $table = 'salesorderlog_product_breakdowns';

    protected $fillable = [
        'salesorderlog_id',
        'family',
        'subtype',
        'amount',
        'quotation_no',
    ];

    public function salesOrderLog()
    {
        return $this->belongsTo(SalesOrderLog::class, 'salesorderlog_id');
    }
}
