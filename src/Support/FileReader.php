<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

final class FileReader
{
    /** @var array<int, string> */
    private array $ignoredDirectories = ['storage', 'bootstrap/cache', 'node_modules'];

    /**
     * @return array<int, string>
     */
    public function files(string $directory, string $extension = 'php'): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (UnexpectedValueException) {
            return [];
        }

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (! $file->isReadable() || $this->isIgnoredPath($file->getPathname())) {
                continue;
            }

            if ($file->getExtension() === $extension) {
                $files[] = $this->normalizePath($file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    public function get(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    public function relative(string $path, string $root): string
    {
        $root = rtrim($this->normalizePath($root), '/');
        $path = $this->normalizePath($path);

        if (str_starts_with($path, $root)) {
            return ltrim(substr($path, strlen($root)), '/');
        }

        return $path;
    }

    public function lineFor(string $contents, string $needle): ?int
    {
        $position = stripos($contents, $needle);

        if ($position === false) {
            return null;
        }

        return substr_count(substr($contents, 0, $position), "\n") + 1;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function isIgnoredPath(string $path): bool
    {
        $path = $this->normalizePath($path);

        foreach ($this->ignoredDirectories as $directory) {
            if (str_contains($path, '/' . $directory . '/') || str_ends_with($path, '/' . $directory)) {
                return true;
            }
        }

        return false;
    }
}
