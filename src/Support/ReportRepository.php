<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

final class ReportRepository
{
    /**
     * @return array<int, array{filename: string, path: string, size: int, modified_at: int, modified_at_human: string, is_fallback: bool}>
     */
    public function reports(): array
    {
        $reports = [];
        $seen = [];

        foreach ($this->reportFiles() as $path) {
            $realPath = realpath($path);
            if (! is_string($realPath) || isset($seen[$realPath]) || ! $this->isHtml($realPath)) {
                continue;
            }

            if (! $this->isAllowedReportPath($realPath)) {
                continue;
            }

            $reports[] = $this->makeReport($realPath, false);
            $seen[$realPath] = true;
        }

        $fallback = $this->fallbackReport();
        if ($fallback !== null && ! isset($seen[$fallback]) && $this->isAllowedReportPath($fallback)) {
            $reports[] = $this->makeReport($fallback, true);
        }

        usort($reports, fn (array $a, array $b): int => $b['modified_at'] <=> $a['modified_at']);

        return $reports;
    }

    /**
     * @return array{filename: string, path: string, size: int, modified_at: int, modified_at_human: string, is_fallback: bool}|null
     */
    public function latest(): ?array
    {
        return $this->reports()[0] ?? null;
    }

    /**
     * @return array{filename: string, path: string, size: int, modified_at: int, modified_at_human: string, is_fallback: bool}|null
     */
    public function find(string $filename): ?array
    {
        if ($filename !== basename($filename) || str_contains($filename, '..')) {
            return null;
        }

        $filename = basename($filename);

        if ($filename === '' || ! $this->isHtml($filename)) {
            return null;
        }

        foreach ($this->reports() as $report) {
            if ($report['filename'] === $filename) {
                return $report;
            }
        }

        return null;
    }

    public function isAllowedReportPath(string $path): bool
    {
        $realPath = realpath($path);

        if (! is_string($realPath) || ! is_file($realPath) || ! $this->isHtml($realPath)) {
            return false;
        }

        $reportsDirectory = $this->reportsDirectory();
        if ($reportsDirectory !== null && str_starts_with($realPath, $reportsDirectory . DIRECTORY_SEPARATOR)) {
            return true;
        }

        return $this->fallbackReport() === $realPath;
    }

    /**
     * @return array<int, string>
     */
    private function reportFiles(): array
    {
        $directory = $this->reportsDirectory();

        if ($directory === null) {
            return [];
        }

        return array_values(array_filter(glob($directory . DIRECTORY_SEPARATOR . '*.{html,htm}', GLOB_BRACE) ?: [], 'is_file'));
    }

    private function reportsDirectory(): ?string
    {
        $directory = config('preflight.ui.reports_directory', config('preflight.reports.directory'));

        if (! is_string($directory) || $directory === '') {
            return null;
        }

        if (! is_dir($directory)) {
            return null;
        }

        $realPath = realpath($directory);

        return is_string($realPath) ? rtrim($realPath, DIRECTORY_SEPARATOR) : null;
    }

    private function fallbackReport(): ?string
    {
        $path = config('preflight.ui.fallback_report', config('preflight.reports.default_html'));

        if (! is_string($path) || $path === '' || ! is_file($path) || ! $this->isHtml($path)) {
            return null;
        }

        $realPath = realpath($path);

        return is_string($realPath) ? $realPath : null;
    }

    /**
     * @return array{filename: string, path: string, size: int, modified_at: int, modified_at_human: string, is_fallback: bool}
     */
    private function makeReport(string $path, bool $fallback): array
    {
        $modifiedAt = (int) filemtime($path);

        return [
            'filename' => basename($path),
            'path' => $path,
            'size' => (int) filesize($path),
            'modified_at' => $modifiedAt,
            'modified_at_human' => date('Y-m-d H:i:s', $modifiedAt),
            'is_fallback' => $fallback,
        ];
    }

    private function isHtml(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true);
    }
}
