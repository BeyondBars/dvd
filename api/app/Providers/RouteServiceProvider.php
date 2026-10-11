<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Routes themselves are registered in bootstrap/app.php (Laravel 11 style). This provider only
 * configures the named rate limiters used by those routes.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    protected function configureRateLimiters(): void
    {
        // General API limit, per user when logged in and per IP otherwise.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // The customer website's public endpoints, per IP.
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(60)->by('public:'.$request->ip());
        });

        // Emails that can be triggered without logging in (sign-up link, password reset).
        RateLimiter::for('emails', function (Request $request) {
            return [
                Limit::perMinute(3)->by('emails:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perHour(20)->by('emails:'.$request->ip()),
            ];
        });

        // Sending a 2FA code by email/SMS.
        RateLimiter::for('two-factor-send', function (Request $request) {
            return Limit::perMinute(3)->by('2fa-send:'.($request->user()?->id ?: $request->ip()));
        });

        // Guessing a 2FA code: 5 tries per minute, 20 per hour, per user.
        RateLimiter::for('two-factor-verify', function (Request $request) {
            $key = '2fa-verify:'.($request->user()?->id ?: $request->ip());

            return [Limit::perMinute(5)->by($key), Limit::perHour(20)->by($key.':h')];
        });
    }
}
