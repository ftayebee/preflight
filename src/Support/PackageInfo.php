<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

use Illuminate\Contracts\Foundation\Application;

final class PackageInfo
{
    public function name(): string
    {
        return 'fahimtayebee/preflight';
    }

    public function version(): ?string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion($this->name());
            } catch (\Throwable) {
                return null;
            }

            return is_string($version) ? $version : null;
        }

        return null;
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function laravelVersion(): ?string
    {
        if (! function_exists('app')) {
            return null;
        }

        try {
            $app = app();

            if ($app instanceof Application) {
                return $app->version();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
