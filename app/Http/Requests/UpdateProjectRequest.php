<?php
class UpdateProjectRequest extends FormRequest {
    public function authorize(){ return auth()->check(); }
    public function rules(){
        return [
            'comments' => ['nullable','string','max:2000'],
            'checklist.mep_contractor_appointed' => ['boolean'],
            'checklist.boq_quoted'               => ['boolean'],
            'checklist.boq_submitted'            => ['boolean'],
            'checklist.priced_at_discount'       => ['boolean'],
            'status'   => ['nullable','in:BIDDING,IN HAND,LOST,Accepted,Pre-Acceptance,Waiting,Rejected'], // support both pages
        ];
    }
}
