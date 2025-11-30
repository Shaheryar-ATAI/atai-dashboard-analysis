<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectChecklistState;
use App\Models\ProjectNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function detail(Project $project, Request $req)
    {
        $data = [
            'id' => $project->id,
            'projectName' => $project->canonical_name,
            'clientName' => $project->canonical_client,
            'projectLocation' => $project->canonical_location,
            'area' => $project->area,
            'quotationValue' => $project->canonical_value,
            'quotationNo' => $project->quotation_no,
            'quotationDate' => $project->quotation_date_ymd,
            'ataiProducts' => $project->atai_products,
            'estimator' => $project->action1 ?? null,
            'status' => $project->project_type ?? null,
            'salesperson' => $project->salesperson ?? $project->salesman,
            'dateRec' => optional($project->date_rec)->format('Y-m-d'),
            'clientReference' => $project->client_reference,
            'projectType' => $project->project_type,
        ];

        $loadPhase = function (string $phase) use ($project) {
            $rows = DB::table('project_checklist_states')
                ->where('project_id', $project->id)
                ->where('phase', $phase)
                ->get(['item_key', 'checked', 'progress']);
            $map = [];
            $progress = 0;
            foreach ($rows as $r) {
                $map[$r->item_key] = (bool)$r->checked;
                $progress = max($progress, (int)($r->progress ?? 0));
            }
            return [$map, $progress];
        };

        [$bidMap, $bidProg] = $loadPhase('BIDDING');
        [$ihMap, $ihProg] = $loadPhase('INHAND');

        // notes
        $notes = DB::table('project_notes')
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'note', 'created_by', 'created_at']);

        // ✅ progress history (we’ll insert into this table on every change)
        $history = DB::table('project_status_history')
            ->leftJoin('users', 'users.id', '=', 'project_status_history.changed_by')
            ->where('project_status_history.project_id', $project->id)
            ->orderByDesc('project_status_history.created_at')
            ->limit(100)
            ->get([
                'project_status_history.phase',
                'project_status_history.progress',
                'project_status_history.created_at',
                DB::raw('COALESCE(users.name, project_status_history.changed_by) as by_name'),
            ]);

        return response()->json(array_merge($data, [
            'checklistBidding' => $bidMap,
            'biddingProgress' => $bidProg,
            'checklistInhand' => $ihMap,
            'inhandProgress' => $ihProg,
            'notes' => $notes,
            'progressHistory' => $history,
        ]));
    }


    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $action = (string)$request->string('action');

        if ($action === 'checklist_bidding') {
            return $this->saveBiddingChecklist($request, $project);
        }

        if ($action === 'checklist_inhand') {
            return $this->saveInhandChecklist($request, $project);
        }

        if ($action === 'add_note') {
            return $this->addProjectNote($request, $project);
        }

        // === NEW: change status (In-Hand -> Lost / PO-received, etc.) ===
        if ($action === 'update_status') {
            // accept various spellings and map to canonical status used in DB/UI
            $map = [
                'bidding' => 'Bidding',
                'open' => 'Bidding',
                'submitted' => 'Bidding',
                'inhand' => 'In-Hand',
                'in-hand' => 'In-Hand',
                'in hand' => 'In-Hand',
                'won' => 'In-Hand',
                'order' => 'In-Hand',
                'order in hand' => 'In-Hand',
                'lost' => 'Lost',
                'po' => 'PO-received',
                'po received' => 'PO-received',
                'po-received' => 'PO-received',
                'po recieved' => 'PO-received', // forgive the typo
            ];

            $raw = strtolower(trim((string)$request->input('to_status')));
            $to = $map[$raw] ?? null;

            if (!$to) {
                return response()->json(['ok' => false, 'message' => 'Invalid status'], 422);
            }

            $from = $project->status;

            // update project
            $project->status = $to;
            // if you store both, keep them in sync
            if ($project->isFillable('status_current') || \Schema::hasColumn($project->getTable(), 'status_current')) {
                $project->status_current = $to;
            }
            $project->save();

            // write a history row (make sure these columns exist in your table)
            try {
                \DB::table('project_status_history')->insert([
                    'project_id' => $project->id,
                    'phase' => strtoupper($to),     // e.g., BIDDING / IN-HAND / PO-RECEIVED / LOST
                    'from_status' => $from,
                    'to_status' => $to,                 // prevents the 1364 error you saw
                    'progress' => 100,                 // adjust if you track actual percentage
                    'changed_by' => optional($request->user())->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Optional: log but don't fail the response
                \Log::warning('Failed to insert status history', ['e' => $e->getMessage()]);
            }

            return response()->json(['ok' => true]);
        }
        $action = (string)$request->input('action', '');

        if ($action === 'update_project_type') {
            $toType = trim((string)$request->input('to_type', ''));
            // Allow only the buckets you support
            $allowed = ['Bidding', 'In-Hand', 'Lost'];
            if (!in_array($toType, $allowed, true)) {
                return response()->json(['ok' => false, 'message' => 'Invalid project type'], 422);
            }

            $project->project_type = $toType;
            // (Optional) also normalize status_current if you want them aligned:
            // if ($toType === 'In-Hand')   { $project->status_current = 'In-Hand'; }
            // if ($toType === 'Bidding')   { $project->status_current = 'Bidding'; }
            // if ($toType === 'Lost')      { $project->status_current = 'Lost'; }

            $project->save();

            return response()->json(['ok' => true, 'id' => $project->id, 'project_type' => $project->project_type]);
        }
        return response()->json(['ok' => false, 'message' => 'Unknown action'], 422);
    }


//    public  function saveBiddingChecklist(Request $request, Project $project)
//    {
//        $items = [
//            'mep_contractor_appointed' => $request->boolean('mep_contractor_appointed'),
//            'boq_quoted' => $request->boolean('boq_quoted'),
//            'boq_submitted' => $request->boolean('boq_submitted'),
//            'priced_at_discount' => $request->boolean('priced_at_discount'),
//        ];
//
//        $table = 'project_checklist_states';
//        $phase = 'BIDDING';
//        $uid = $request->user()->id;
//        $checkCol = 'checked'; // your table uses `checked`
//
//        // Compute progress (simple average of 4 items)
//        $total = count($items);
//        $done = collect($items)->filter()->count();
//        $progress = (int)floor(($done / max($total, 1)) * 100);
//
//        DB::transaction(function () use ($project, $items, $uid, $table, $checkCol, $phase, $progress) {
//            foreach ($items as $itemKey => $checked) {
//                DB::table($table)->updateOrInsert(
//                    [
//                        'project_id' => $project->id,
//                        'phase' => $phase,
//                        'item_key' => $itemKey,
//                    ],
//                    [
//                        $checkCol => $checked ? 1 : 0,
//                        'updated_by' => $uid,
//                        'progress' => $progress,
//                        'updated_at' => now(),
//                        'created_at' => now(),
//                    ]
//                );
//            }
//            DB::table($table)
//                ->where('project_id', $project->id)
//                ->where('phase', $phase)
//                ->update(['progress' => $progress, 'updated_at' => now()]);
//        });
//        $this->logProgressChange($project->id, 'BIDDING', $progress, $request->user()->id);
//        return response()->json([
//            'ok' => true,
//            'message' => 'Bidding checklist saved.',
//            'progress' => $progress,
//            'state' => $items,
//        ]);
//    }

    public function saveBiddingChecklist(Request $request, Project $project)
    {

        // NEW bidding items (equal weight)
        $items = [
            'vendor_approved' => $request->boolean('vendor_approved'),
            'specs_compliant' => $request->boolean('specs_compliant'),
            'regular_discount' => $request->boolean('regular_discount'),
            'client_visit' => $request->boolean('client_visit'),
            'winning_factor' => $request->boolean('winning_factor'),
            'prequalification_submitted' => $request->boolean('prequalification_submitted'),
        ];

        $table = 'project_checklist_states';
        $phase = 'BIDDING';
        $uid = $request->user()->id;
        $checkCol = 'checked'; // your table uses `checked`

        // progress = % of boxes checked
        $total = max(count($items), 1);
        $done = collect($items)->filter()->count();
        $progress = (int)floor(($done / $total) * 100);

        DB::transaction(function () use ($project, $items, $uid, $table, $checkCol, $phase, $progress) {
            foreach ($items as $itemKey => $checked) {
                DB::table($table)->updateOrInsert(
                    ['project_id' => $project->id, 'phase' => $phase, 'item_key' => $itemKey],
                    [$checkCol => $checked ? 1 : 0, 'updated_by' => $uid, 'progress' => $progress, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            DB::table($table)
                ->where('project_id', $project->id)->where('phase', $phase)
                ->update(['progress' => $progress, 'updated_at' => now()]);
        });

        $this->logProgressChange($project->id, $phase, $progress, $uid);

        return response()->json([
            'ok' => true,
            'message' => 'Bidding checklist saved.',
            'progress' => $progress,
            'state' => $items,
        ]);
    }


//    public  function saveInhandChecklist(Request $request, Project $project)
//    {
//        // Define In-Hand checklist items and their weights (percent)
//        $defs = [
//            'submittal_approved' => 25,
//            'sample_approved' => 25,
//            'commercial_terms_agreed' => 50,
//            'no_approval_or_terms' => 0,
//            'discount_offered_as_standard' => 0,
//        ];
//
//        // Normalize incoming booleans
//        $items = [];
//        foreach ($defs as $key => $w) {
//            $items[$key] = $request->boolean($key);
//        }
//
//        $table = 'project_checklist_states';
//        $phase = 'INHAND';
//        $uid = $request->user()->id;
//
//        // Your table has `checked` (per your screenshot)
//        $checkCol = 'checked';
//
//        // Compute weighted progress
//        $progress = 0;
//        foreach ($items as $k => $v) {
//            if ($v) $progress += ($defs[$k] ?? 0);
//        }
//        $progress = max(0, min(100, (int)round($progress)));
//
//        DB::transaction(function () use ($project, $items, $uid, $table, $checkCol, $phase, $progress) {
//            foreach ($items as $itemKey => $checked) {
//                DB::table($table)->updateOrInsert(
//                    [
//                        'project_id' => $project->id,
//                        'phase' => $phase,
//                        'item_key' => $itemKey,
//                    ],
//                    [
//                        $checkCol => $checked ? 1 : 0,
//                        'updated_by' => $uid,
//                        'progress' => $progress,
//                        'updated_at' => now(),
//                        'created_at' => now(),
//                    ]
//                );
//            }
//
//            // ensure all INHAND rows carry the latest progress
//            DB::table($table)
//                ->where('project_id', $project->id)
//                ->where('phase', $phase)
//                ->update(['progress' => $progress, 'updated_at' => now()]);
//        });
//        // or for In-Hand:
//        $this->logProgressChange($project->id, 'INHAND', $progress, $request->user()->id);
//
//        return response()->json([
//            'ok' => true,
//            'message' => 'In-Hand checklist saved.',
//            'progress' => $progress,
//            'state' => $items,
//        ]);
//    }
    public function saveInhandChecklist(Request $request, Project $project)
    {


        // NEW in-hand items + weights (must total 100 for clean %)
        $defs = [
            'prices_agreed' => 25,
            'payment_terms_agreed' => 25,
            'delivery_schedule_agreed' => 0,
            'technical_parameters_approved' => 25,
            'material_submitted' => 25,
            'samples_submitted' => 0,
            'factory_visit_required' => 0,
        ];

        // normalize booleans
        $items = [];
        foreach ($defs as $key => $w) {
            $items[$key] = $request->boolean($key);
        }

        $table = 'project_checklist_states';
        $phase = 'INHAND';
        $uid = $request->user()->id;
        $checkCol = 'checked';

        // weighted progress
        $progress = 0;
        foreach ($items as $k => $v) {
            if ($v) $progress += ($defs[$k] ?? 0);
        }
        $progress = max(0, min(100, (int)round($progress)));

        DB::transaction(function () use ($project, $items, $uid, $table, $checkCol, $phase, $progress) {
            foreach ($items as $itemKey => $checked) {
                DB::table($table)->updateOrInsert(
                    ['project_id' => $project->id, 'phase' => $phase, 'item_key' => $itemKey],
                    [$checkCol => $checked ? 1 : 0, 'updated_by' => $uid, 'progress' => $progress, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            DB::table($table)
                ->where('project_id', $project->id)->where('phase', $phase)
                ->update(['progress' => $progress, 'updated_at' => now()]);
        });

        $this->logProgressChange($project->id, $phase, $progress, $uid);

        return response()->json([
            'ok' => true,
            'message' => 'In-Hand checklist saved.',
            'progress' => $progress,
            'state' => $items,
        ]);
    }


    public function addProjectNote(Request $request, Project $project)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $note = DB::transaction(function () use ($project, $data, $request) {
            return ProjectNote::create([
                'project_id' => $project->id,
                'note' => trim($data['note']),
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'ok' => true,
            'msg' => 'Note added.',
            'id' => $note->id,
            'note' => $note->note,
            'when' => $note->created_at?->toDateTimeString(),
            'by' => $request->user()->name ?? 'You',
        ]);
    }

    public function logProgressChange(int $projectId, string $phase, int $progress, int $userId): void
    {
        // only insert when it actually changed
        $last = DB::table('project_status_history')
            ->where('project_id', $projectId)
            ->where('phase', $phase)
            ->orderByDesc('id')
            ->value('progress');

        if ($last === null || (int)$last !== (int)$progress) {
            DB::table('project_status_history')->insert([
                'project_id' => $projectId,
                'phase' => $phase,
                'progress' => $progress,
                'changed_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
