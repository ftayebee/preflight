<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests;

use FahimTayebee\Preflight\PreflightServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PreflightServiceProvider::class,
        ];
    }
}
