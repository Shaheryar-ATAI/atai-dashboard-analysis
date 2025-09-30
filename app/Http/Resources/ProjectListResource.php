<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->canonical_name,
            'client'      => $this->canonical_client,
            'location'    => $this->canonical_location,
            'area'        => $this->area,
            'price'       => (float) ($this->canonical_price ?? 0),
            'currency'    => 'SAR',
            'quotation_date'  => optional($this->quotation_date)->format('Y-m-d'),
            'action1'         => $this->action1,
            'status'      => $this->status,
            'progress'    => $this->progress_percent,
            'salesman'    => $this->salesman, // string column for now
            'updated_at'  => optional($this->updated_at)->format('Y-m-d H:i'),
        ];
    }
}
