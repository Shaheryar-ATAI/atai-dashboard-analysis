<?php

namespace App\Providers;

use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate; // <-- add this

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Project::class => ProjectPolicy::class,
    ];

 public function boot(): void
{

    $this->registerPolicies();
    Gate::define('viewPerformance', function ($user) {
        return strcasecmp(trim((string)($user->name ?? '')), 'Shaheryar') === 0
            || strcasecmp(trim((string)($user->role ?? '')), 'Khalid') === 0;
    });
}
}
