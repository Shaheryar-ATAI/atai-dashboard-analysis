<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate; // <-- add this

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

 public function boot(): void
{
    Gate::define('viewPerformance', function ($user) {
        return strcasecmp(trim((string)($user->name ?? '')), 'shaheryar') === 0
            || strcasecmp(trim((string)($user->role ?? '')), 'gm') === 0;
    });
}
}
