<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name','client','location','area','price','status','salesperson_id','comments',
        // optional inquiry fields (add when you migrate them):
        'date_received','quotation_no','client_reference','atai_products','action_1','country',
        'quotation_date','quotation_value','remark','project_location','project_type'
    ];

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function checklistItems()
    {
        return $this->hasMany(ProjectChecklistItem::class);
    }

    public function progressPercent(): int
    {
        if ($this->status !== 'bidding') return 100;
        $keys = ['inquiry_verified','quotation_submitted','client_approval','po_received'];
        $done = $this->checklistItems->whereIn('key', $keys)->where('completed', true)->count();
        return (int) round($done / count($keys) * 100);
    }
}
    