<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PowerBiApiController extends Controller
{
    public function projects(): JsonResponse
    {
        $projects = Project::select('project_name',
            'client_name',
            'project_location',
            'area',
            'quotation_no',
            'revision_no',
            'atai_products',
            'value_with_vat',
            'quotation_value',
            'status_current',
            'status',
            'last_comment',
            'project_type',
            'quotation_date',
            'technical_submittal',
            'date_rec',
            'action1',
            'salesperson',
            'salesman',
            'technical_base',
            'contact_person',
            'contact_number',
            'contact_email',
            'company_address',
            'created_by_id',
            'estimator_name',
            'coordinator_updated_by_id',
            'created_at',
            'updated_at')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        return response()->json($projects);
    }
}



