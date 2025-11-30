<?php

namespace App\Listeners;

use App\Events\ProjectStatusChanged;
use App\Mail\ProjectStatusChangedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendProjectStatusChangedEmail implements ShouldQueue
{
    public function handle(ProjectStatusChanged $event): void
    {
        $p = $event->project->fresh();

        // Who gets notified? 2 options below — pick ONE:

        // (A) Role-based (GM/Admin), optionally filtered by region/area
        $to = User::query()
            ->whereHas('roles', fn($q)=> $q->whereIn('name', ['gm','admin']))
            // ->when($p->area, fn($q)=> $q->where('region', $p->area)) // uncomment if region-based routing
            ->pluck('email')
            ->filter()
            ->all();

        // (B) Or keep a config list:
        // $to = config('atai.notify.status_change', ['gm@example.com','admin@example.com']);

        if (empty($to)) return;

        Mail::to($to)->send(new ProjectStatusChangedMail(
            project: $p,
            from: $event->fromStatus,
            to:   $event->toStatus,
            changerId: $event->changedByUserId,
        ));
    }
}
