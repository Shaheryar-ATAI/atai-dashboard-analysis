{{-- resources/views/salesorderlog/_row_details.blade.php --}}
@php($s = $safe)
<div class="row small g-2">
    <div class="col-md-4">
        <div><strong>Payment terms:</strong> {{ $s($r->payment_terms) }}</div>
        <div><strong>Quote #:</strong> {{ $s($r->quote_no) }}</div>
        <div><strong>Ref #:</strong> {{ $s($r->ref_no) }}</div>
        <div><strong>Sales OAA:</strong> {{ $s($r->sales_oaa) }}</div>
    </div>
    <div class="col-md-4">
        <div><strong>Project:</strong> {{ $s($r->project_name) }}</div>
        <div><strong>Proj. Location:</strong> {{ $s($r->project_location) }}</div>
        <div><strong>Factory loc:</strong> {{ $s($r->factory_loc) }}</div>
        <div><strong>Job #:</strong> {{ $s($r->job_no) }}</div>
    </div>
    <div class="col-md-4">
        <div><strong>Products:</strong> {{ $s($r->products) }}</div>
        <div><strong>Sales source:</strong> {{ $s($r->sales_source) }}</div>
        <div><strong>Remarks:</strong> {{ $s($r->remarks) }}</div>
        <div><strong>Status:</strong> {{ $s($r->status) }}</div>
    </div>
</div>
