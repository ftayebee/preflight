<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

final class ScannerContext
{
    /**
     * @param array<int, string> $changedFiles
     * @param array<int, string> $onlyScanners
     * @param array<int, string> $skippedScanners
     */
    public function __construct(
        private readonly bool $changedMode,
        private readonly array $changedFiles,
        private readonly ?string $baseRef,
        private readonly string $projectRoot,
        private readonly array $onlyScanners = [],
        private readonly array $skippedScanners = [],
    ) {
    }

    public function isChangedMode(): bool
    {
        return $this->changedMode;
    }

    /**
     * @return array<int, string>
     */
    public function changedFiles(): array
    {
        return array_values(array_unique(array_map(
            fn (string $path): string => $this->normalizePath($path),
            $this->changedFiles
        )));
    }

    public function baseRef(): ?string
    {
        return $this->baseRef;
    }

    public function shouldScanFile(string $path): bool
    {
        if (! $this->changedMode) {
            return true;
        }

        $relative = $this->relativePath($path);

        return in_array($relative, $this->changedFiles(), true);
    }

    /**
     * @param array<int, string> $extensions
     * @return array<int, string>
     */
    public function relevantFilesFor(array $extensions = []): array
    {
        $extensions = array_values(array_filter(array_map(
            static fn (string $extension): string => strtolower(ltrim($extension, '.')),
            $extensions
        )));

        if (! $this->changedMode) {
            return [];
        }

        if ($extensions === []) {
            return $this->changedFiles();
        }

        return array_values(array_filter(
            $this->changedFiles(),
            static fn (string $path): bool => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)
        ));
    }

    /**
     * @return array<int, string>
     */
    public function onlyScanners(): array
    {
        return $this->onlyScanners;
    }

    /**
     * @return array<int, string>
     */
    public function skippedScanners(): array
    {
        return $this->skippedScanners;
    }

    private function relativePath(string $path): string
    {
        $normalized = $this->normalizePath($path);
        $root = rtrim($this->normalizePath($this->projectRoot), '/');

        if (str_starts_with($normalized, $root . '/')) {
            return ltrim(substr($normalized, strlen($root)), '/');
        }

        return ltrim($normalized, '/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        return ltrim($path, './');
    }
}
