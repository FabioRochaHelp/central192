<?php

use App\Console\Commands\ImportarIbgeMunicipios;
use App\Console\Commands\VincularIbgeMunicipios;
use App\Http\Middleware\EnsureOperationalCentral;
use App\Http\Middleware\EnsureScreenIsUnlocked;
use App\Http\Middleware\InitializeOperationalTenancy;
use App\Jobs\SyncTrackingPositions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ImportarIbgeMunicipios::class,
        VincularIbgeMunicipios::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $provider = (string) config('tracking.provider', 'traccar');
        $interval = (int) config("{$provider}.positions_sync_interval", 60);
        $schedule->job(new SyncTrackingPositions)->everyMinute()->when($interval <= 60);
        $schedule->job(new SyncTrackingPositions)->everyFiveMinutes()->when($interval > 60);

        $schedule->command('focos:importar-satelite')
            ->everyTenSeconds()
            ->withoutOverlapping();
    })
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'operational.tenant' => InitializeOperationalTenancy::class,
            'operational.central' => EnsureOperationalCentral::class,
            'screen.unlocked' => EnsureScreenIsUnlocked::class,
        ]);

        $middleware->web(append: [
            EnsureScreenIsUnlocked::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'integrations/calls/incident-intake',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
