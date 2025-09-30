<?php

namespace App\Http\Controllers;

use App\Models\Project;   // <- make sure this import exists
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        return view('projects.index', ['user' => auth()->user()]);
    }

    /**
     * Lightweight JSON list (keeps your original shape).
     */
    public function list(Request $req)
    {
        $user = $req->user();

        $projects = Project::query()
            ->with('salesperson:id,name')
            ->forUserRegion($user)
            ->status($req->query('status'))
            ->search($req->query('search'))
            ->orderByDesc('id')
            ->get()
            ->map(function (Project $p) {
                $pct = $p->status === 'bidding' ? $p->progressPercent() : 100;

                return [
                    'id'               => $p->id,
                    'name'             => $p->canonical_name,
                    'client'           => $p->canonical_client,
                    'location'         => $p->canonical_location,
                    'area'             => $p->area,

                    'quotation_no'     => $p->quotation_no,
                    'atai_products'    => $p->atai_products,
                    'quotation_date'   => $p->quotation_date_ymd,
                    'action1'          => $p->action1,
                    'quotation_value'  => $p->canonical_value,

                    'price'            => $p->canonical_value,
                    'currency'         => 'SAR',

                    'status'           => $p->status,
                    'progress'         => $pct,
                    'salesperson'      => $p->salesperson?->name ?? $p->salesman,
                ];
            });

        return response()->json($projects);
    }

    /**
     * Detail for modal (no checklistItems relation).
     */
    public function detail(Project $project, Request $req)
    {
        // $this->authorize('view', $project);
        $project->loadMissing('salesperson:id,name');

        $checklist = [
            'mep_contractor_appointed' => (bool) $project->mep_contractor_appointed,
            'boq_quoted'               => (bool) $project->boq_quoted,
            'boq_submitted'            => (bool) $project->boq_submitted,
            'priced_at_discount'       => (bool) $project->priced_at_discount,
        ];

        return response()->json([
            'id'              => $project->id,
            'projectName'     => $project->canonical_name,
            'clientName'      => $project->canonical_client,
            'projectLocation' => $project->canonical_location,
            'area'            => $project->area,

            // pricing: prefer quotation_value, fall back to price
            'quotationValue'  => (float) ($project->quotation_value ?? $project->price ?? 0),

            'quotationNo'     => $project->quotation_no,
            'quotationDate'   => $project->quotation_date_ymd,
            'ataiProducts'    => $project->atai_products,

            // Estimator name lives in action1 column
            'estimator'       => $project->action1,

            'status'          => $project->status,
            'salesperson'     => $project->salesperson?->name ?? $project->salesman,
            'comments'        => $project->comments,

            // Show date received
            'dateRec'         => optional($project->date_rec)->format('Y-m-d'),

            'clientReference' => $project->client_reference,
            'projectType'     => $project->project_type,

            'checklist'       => $checklist,
        ]);
    }

    public function showJson($id)
    {
        $p = Project::findOrFail($id);

        return response()->json([
            'id'        => $p->id,
            'name'      => $p->canonical_name,
            'client'    => $p->canonical_client,
            'location'  => $p->canonical_location,
            'area'      => $p->area,
            'price'     => $p->canonical_value,
            'status'    => $p->status,
            'checklist' => [
                'mep_contractor_appointed' => (bool) $p->mep_contractor_appointed,
                'boq_quoted'               => (bool) $p->boq_quoted,
                'boq_submitted'            => (bool) $p->boq_submitted,
                'priced_at_discount'       => (bool) $p->priced_at_discount,
            ],
            'comments'  => $p->comments ?? '',
        ]);
    }
}
