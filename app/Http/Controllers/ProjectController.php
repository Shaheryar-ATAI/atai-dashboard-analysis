<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{


    public function index()
    {
        // require login to view this page (route middleware shown in step 3)
        $user = auth()->user();

        return view('projects.index', [
            'user' => $user,            // <- pass it explicitly
        ]);
    }


    public function list(Request $req)
    {
        $user = $req->user();
        $q = Project::query()->with('salesperson:id,name');

        // Region scoping (policy-level enforced in detail, but filter list here too)
        if (!$user->hasRole(['gm', 'manager'])) {
            $q->where('area', $user->region);
        }

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }

        if ($search = trim($req->query('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%$search%")
                    ->orWhere('client', 'like', "%$search%")
                    ->orWhere('location', 'like', "%$search%")
                    ->orWhere('area', 'like', "%$search%");
            });
        }

        $projects = $q->orderBy('id', 'desc')->get()->map(function ($p) {
            // lightweight progress (for list rows)
            $pct = $p->status === 'bidding' ? $p->progressPercent() : 100;
            return [
                'id' => $p->id,
                'name' => $p->name,
                'client' => $p->client,
                'location' => $p->location,
                'area' => $p->area,
                'price' => (float)$p->price,
                'currency' => 'SAR',
                'status' => $p->status,
                'progress' => $pct,
                'salesperson' => $p->salesperson?->name,
            ];
        });

        return response()->json($projects);
    }

    public function detail(Project $project, Request $req)
    {
        $this->authorize('view', $project);

        $project->load(['salesperson:id,name', 'checklistItems' => function ($q) {
            $q->orderBy('key');
        }]);

        // Flatten checklist to {key: bool}
        $checklist = [];
        foreach (['inquiry_verified', 'quotation_submitted', 'client_approval', 'po_received'] as $k) {
            $checklist[$k] = (bool)optional($project->checklistItems->firstWhere('key', $k))->completed;
        }

        return [
            'id' => $project->id,
            'name' => $project->name,
            'client' => $project->client,
            'location' => $project->location,
            'area' => $project->area,
            'price' => (float)$project->price,
            'currency' => 'SAR',
            'status' => $project->status,
            'salesperson' => $project->salesperson?->name,
            'comments' => $project->comments,
            // Inquiry fields (optional, will show '—' in UI if null)
            'dateRec' => $project->date_received ?? null,
            'clientName' => $project->client,
            'projectName' => $project->name,
            'quotationNo' => $project->quotation_no ?? null,
            'clientReference' => $project->client_reference ?? null,
            'ataiProducts' => $project->atai_products ?? null,
            'action1' => $project->action_1 ?? null,
            'country' => $project->country ?? 'KSA',
            'quotationDate' => $project->quotation_date ?? null,
            'quotationValue' => $project->quotation_value ?? $project->price,
            'projectLocation' => $project->project_location ?? $project->location,
            'projectType' => $project->project_type ?? null,
            'checklist' => $checklist,
        ];
    }

    public function showJson($id)
    {
        $p = \App\Models\Project::findOrFail($id);
        // Return exactly what renderModal() expects (name, status, price, area, location, client, checklist?, comments?)
        return response()->json([
            'id' => $p->id,
            'name' => $p->name,
            'client' => $p->client,
            'location' => $p->location,
            'area' => $p->area,
            'price' => $p->quotation_value,
            'status' => $p->status,
            'checklist' => $p->checklist ?? [],
            'comments' => $p->comments ?? '',
        ]);
    }
}
