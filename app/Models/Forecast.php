<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $table = 'forecast';

    protected $fillable = [
        'customer_name',
        'project_name',
        'products',
        'product_family',
        'value_sar',
        'remarks',
        'type',         // 'new' or 'carry'
        'percentage',
        'salesman',
        'region',
        'month',
        'month_no',      // 1..12
        'year',
    ];
}
