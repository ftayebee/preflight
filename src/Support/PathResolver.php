<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

final class PathResolver
{
    public function base(?string $path = null): string
    {
        return $this->join($this->root(), $path);
    }

    public function basePath(?string $path = null): string
    {
        return $this->base($path);
    }

    public function app(?string $path = null): string
    {
        return $this->join($this->base('app'), $path);
    }

    public function appPath(?string $path = null): string
    {
        return $this->app($path);
    }

    public function database(?string $path = null): string
    {
        return $this->join($this->base('database'), $path);
    }

    public function databasePath(?string $path = null): string
    {
        return $this->database($path);
    }

    public function routes(?string $path = null): string
    {
        return $this->join($this->base('routes'), $path);
    }

    public function routesPath(?string $path = null): string
    {
        return $this->routes($path);
    }

    public function config(?string $path = null): string
    {
        return $this->join($this->base('config'), $path);
    }

    public function configPath(?string $path = null): string
    {
        return $this->config($path);
    }

    public function env(): string
    {
        return $this->base('.env');
    }

    public function envPath(): string
    {
        return $this->env();
    }

    public function composerJson(): string
    {
        return $this->base('composer.json');
    }

    public function composerJsonPath(): string
    {
        return $this->composerJson();
    }

    public function composerLock(): string
    {
        return $this->base('composer.lock');
    }

    public function composerLockPath(): string
    {
        return $this->composerLock();
    }

    public function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->root()), '/');
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $base)) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return $path;
    }

    private function root(): string
    {
        $configured = config('preflight.base_path');

        return rtrim((is_string($configured) && $configured !== '') ? $configured : base_path(), DIRECTORY_SEPARATOR . '/\\');
    }

    private function join(string $base, ?string $path): string
    {
        if ($path === null || $path === '') {
            return $base;
        }

        return rtrim($base, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR . '/\\');
    }
}
