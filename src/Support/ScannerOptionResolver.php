<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class ScannerOptionResolver
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function get(string $scanner, string $key, mixed $default = null): mixed
    {
        return $this->config->get("preflight.scanners.{$scanner}.options.{$key}", $default);
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    public function array(string $scanner, string $key, array $default = []): array
    {
        $value = $this->get($scanner, $key, $default);

        return is_array($value) ? $value : $default;
    }

    public function bool(string $scanner, string $key, bool $default = false): bool
    {
        $value = $this->get($scanner, $key, $default);

        return is_bool($value) ? $value : $default;
    }

    public function string(string $scanner, string $key, string $default = ''): string
    {
        $value = $this->get($scanner, $key, $default);

        return is_string($value) ? $value : $default;
    }
}
