<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight;

use FahimTayebee\Preflight\Commands\AuditCommand;
use FahimTayebee\Preflight\Commands\BaselineCommand;
use FahimTayebee\Preflight\Commands\ConfigCheckCommand;
use FahimTayebee\Preflight\Commands\DoctorCommand;
use FahimTayebee\Preflight\Commands\ListRulesCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
                BaselineCommand::class,
                ConfigCheckCommand::class,
                DoctorCommand::class,
                ListRulesCommand::class,
                SelfTestCommand::class,
            ]);
        }
    }
}
