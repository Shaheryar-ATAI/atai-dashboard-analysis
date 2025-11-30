
<?php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    $this->configureRateLimiting();

    $this->routes(function () {
        // your routes
    });
}

protected function configureRateLimiting(): void
{
    RateLimiter::for('login', function ($job) {
        return Limit::perMinute(5)->by(optional($job->ip())->getIp());
    });
}
