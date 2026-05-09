<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight;

use FahimTayebee\Preflight\Commands\AuditCommand;
use FahimTayebee\Preflight\Commands\BaselineCommand;
use FahimTayebee\Preflight\Commands\ConfigCheckCommand;
use FahimTayebee\Preflight\Commands\DoctorCommand;
use FahimTayebee\Preflight\Commands\ListRulesCommand;
use FahimTayebee\Preflight\Commands\ReportCommand;
use FahimTayebee\Preflight\Commands\SelfTestCommand;
use Illuminate\Support\ServiceProvider;

final class PreflightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/preflight.php', 'preflight');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/preflight.php' => config_path('preflight.php'),
        ], 'preflight-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'preflight');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/preflight'),
        ], 'preflight-views');

        if (config('preflight.ui.enabled') === true) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
                BaselineCommand::class,
                ConfigCheckCommand::class,
                DoctorCommand::class,
                ListRulesCommand::class,
                ReportCommand::class,
                SelfTestCommand::class,
            ]);
        }
    }
}
