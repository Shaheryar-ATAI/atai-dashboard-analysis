<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class StaleBiddingProjectsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $projects;
    public string $regionName;

    public function __construct(Collection $projects, string $regionName)
    {
        $this->projects   = $projects;
        $this->regionName = $regionName;
    }

    public function build()
    {
        $count = $this->projects->count();

        return $this->subject("Reminder: {$count} bidding project(s) need your update")
            ->view('emails.projects.stale_bidding_projects')
            ->with([
                'projects'   => $this->projects,
                'regionName' => $this->regionName,
            ]);
    }
}
