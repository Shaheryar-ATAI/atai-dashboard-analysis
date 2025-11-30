<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'project_name'     => $this->canonical_name,
            'client_name'      => $this->canonical_client,
            'location'         => $this->canonical_location,
            'country'          => $this->country ?? 'KSA',
            'area'             => $this->area,
            'quotation_no'     => $this->quotation_no,
            'client_reference' => $this->client_reference,
            'atai_products'    => $this->atai_products,
            'action1'          => $this->action1,
            'price'            => (float) ($this->canonical_price ?? 0),
            'status'           => $this->status,
            'salesman'         => $this->salesman,
            'date_rec'         => optional($this->date_rec)->format('Y-m-d'),
            'quotation_date'   => optional($this->quotation_date)->format('Y-m-d'),
            'project_type'     => $this->project_type,
            'remark'           => $this->remark,
            'updated_at'       => optional($this->updated_at)->format('Y-m-d H:i'),
            // If you later load checklistItems, you can include them here:
            // 'checklist'      => $this->checklistItems->map(fn($i)=>[
            //     'key'=>$i->key, 'completed'=>(bool)$i->completed
            // ]),
            'progress'         => $this->progress_percent,
        ];
    }
}
