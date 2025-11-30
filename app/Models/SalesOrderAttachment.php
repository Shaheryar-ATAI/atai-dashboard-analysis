<?php

// App\Models\SalesOrderAttachment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderAttachment extends Model
{
    protected $table = 'sales_order_attachments';
    protected $fillable = [
        'salesorderlog_id',
        'disk',
        'path',
        'original_name',
        'size_bytes',
        'mime_type',
        'uploaded_by',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrderLog::class, 'salesorderlog_id');
    }
}
