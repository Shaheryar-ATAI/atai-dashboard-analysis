<?php
namespace App\Services;
use App\Models\Project;

use Illuminate\Support\Facades\DB;
use App\Events\ProjectStatusChanged;
class ProjectUpdater
{
    public function handle(Project $project, array $payload, int $userId): Project
    {
        return DB::transaction(function () use ($project,$payload,$userId) {

            // Checklist …
            $cl = $project->checklist()->firstOrNew();
            $cl->fill([
                'mep_contractor_appointed' => (bool)($payload['checklist']['mep_contractor_appointed'] ?? false),
                'boq_quoted'               => (bool)($payload['checklist']['boq_quoted']               ?? false),
                'boq_submitted'            => (bool)($payload['checklist']['boq_submitted']            ?? false),
                'priced_at_discount'       => (bool)($payload['checklist']['priced_at_discount']       ?? false),
            ]);
            $cl->save();

            $progress = collect($cl->getAttributes())
                    ->only(['mep_contractor_appointed','boq_quoted','boq_submitted','priced_at_discount'])
                    ->filter()->count() * 25;

            $targetStatus = $payload['status'] ?? $project->status;
            if ($progress === 100 && stripos($targetStatus,'lost') === false) $targetStatus = 'IN HAND';
            if ($progress === 0   && stripos($targetStatus,'lost') === false) $targetStatus = 'BIDDING';

            $before = $project->only(['status','comments','checklist_progress']);

            $project->checklist_progress = $progress;
            if (array_key_exists('comments',$payload)) $project->comments = trim($payload['comments']);
            $from = $project->status;
            $project->status = $targetStatus;
            $project->updated_by = $userId;
            $project->save();

            $after = $project->only(['status','comments','checklist_progress']);
            $diff  = array_diff_assoc($after, $before);
            if (!empty($diff)) {
                $project->updates()->create(['changed_by'=>$userId,'changes'=>$diff]);
            }

            if ($from !== $targetStatus) {
                $project->statusHistory()->create([
                    'from_status'=>$from,'to_status'=>$targetStatus,'changed_by'=>$userId
                ]);

                // Fire the event – listeners will send email
                event(new ProjectStatusChanged($project, $from, $targetStatus, $userId));
            }

            if (!empty($payload['comments'])) {
                $project->notes()->create(['note'=>$payload['comments'],'created_by'=>$userId]);
            }

            return $project->refresh()->load(['checklist','statusHistory','updates','notes']);
        });
    }
}
