<?php
// app/Events/ProjectStatusChanged.php
use App\Models\Project;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectStatusChanged
{
    use Dispatchable, SerializesModels;
    public function __construct(public Project $project, public string $from, public string $to, public int $userId){}
}
