<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectChecklistItem;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function update(Project $project, Request $req)
    {
        $this->authorize('view', $project);
        $data = $req->validate([
            'checklist' => 'required|array',
            'checklist.*' => 'boolean',
            'comments' => 'nullable|string'
        ]);

        foreach ($data['checklist'] as $key => $completed) {
            ProjectChecklistItem::updateOrCreate(
                ['project_id' => $project->id, 'key' => $key],
                ['completed' => $completed, 'completed_at' => $completed ? now() : null]
            );
        }

        // Move to inhand if all done and currently bidding
        if ($project->status === 'bidding' && collect($data['checklist'])->every(fn($v) => $v)) {
            $project->status = 'inhand';
        }

        if (array_key_exists('comments', $data)) {
            $project->comments = $data['comments'];
        }
        $project->save();

        return response()->json(['ok' => true, 'status' => $project->status]);
    }
}
