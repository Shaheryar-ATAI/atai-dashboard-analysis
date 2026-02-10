<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PendingQuotationsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $projects;
    public string $requestedBy;

    public function __construct(Collection $projects, string $requestedBy)
    {
        $this->projects = $projects;
        $this->requestedBy = $requestedBy;
    }

    public function build()
    {
        $count = $this->projects->count();

        return $this->subject("Pending Quotations Report ({$count})")
            ->view('emails.projects.pending_quotations')
            ->with([
                'projects' => $this->projects,
                'requestedBy' => $this->requestedBy,
            ]);
    }
}
