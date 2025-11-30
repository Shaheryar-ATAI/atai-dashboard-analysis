<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Project $project;
    public string  $from;
    public string  $to;
    public ?User   $changer;

    /**
     * NOTE:
     * This signature matches how SendProjectStatusChangedEmail calls it:
     *
     *  new ProjectStatusChangedMail(
     *      project: $p,
     *      from: $event->fromStatus,
     *      to:   $event->toStatus,
     *      changerId: $event->changedByUserId,
     *  )
     */
    public function __construct(Project $project, string $from, string $to, int $changerId)
    {
        // Always work with the latest project state from DB
        $this->project = $project->fresh();
        $this->from    = $from;
        $this->to      = $to;
        $this->changer = User::find($changerId);
    }

    public function build()
    {
        return $this->subject('Project Status Updated — '.$this->project->quotation_no)
            ->markdown('emails.projects.status_changed');
    }
}
