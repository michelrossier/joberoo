<?php

namespace App\Providers;

use App\Filament\Auth\LoginResponse as FilamentLoginResponse;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Support\AuditLogger;
use App\Support\MailTracking\OutboundMailLogger;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, FilamentLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            app(AuditLogger::class)->logAuth(AuditLog::EVENT_AUTH_LOGIN, $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            app(AuditLogger::class)->logAuth(AuditLog::EVENT_AUTH_LOGOUT, $event->user);
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            app(OutboundMailLogger::class)->handleSent($event);
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            app(OutboundMailLogger::class)->handleFailed($event);
        });

        Campaign::observe(AuditableObserver::class);
        Application::observe(AuditableObserver::class);

        RateLimiter::for('applications', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
