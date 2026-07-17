<?php

namespace App\Providers;

use App\Integrations\BurnScar\Contracts\BurnScarProvider;
use App\Integrations\BurnScar\Providers\ExternalBurnScarProvider;
use App\Integrations\BurnScar\Providers\FocosBurnScarProvider;
use App\Integrations\Traccar\TraccarClient;
use App\Integrations\Traccar\TraccarService;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\Providers\SsxProvider;
use App\Integrations\Tracking\Providers\TraccarProvider;
use App\Models\Incident;
use App\Models\User;
use App\Observers\IncidentObserver;
use App\Policies\IncidentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL as UrlFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TraccarClient::class);
        $this->app->singleton(TraccarService::class);

        $this->app->singleton(TrackingProvider::class, fn ($app): TrackingProvider => match (config('tracking.provider')) {
            'ssx' => $app->make(SsxProvider::class),
            default => $app->make(TraccarProvider::class),
        });

        $this->app->bind(BurnScarProvider::class, fn ($app): BurnScarProvider => match (config('burnscar.provider')) {
            'focos' => $app->make(FocosBurnScarProvider::class),
            default => $app->make(ExternalBurnScarProvider::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Incident::observe(IncidentObserver::class);

        Gate::define('createOperational', function (?User $user, ?int $municipioId = null): bool {
            if ($user === null) {
                return false;
            }

            return app(IncidentPolicy::class)->createOperational($user, $municipioId);
        });

        $this->configureDefaults();
        $this->configureRateLimiting();

        if ($this->app->isProduction()) {
            UrlFacade::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction() ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised() : null);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('screen-lock-unlock', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
