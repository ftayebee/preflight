<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Support\ReportOpener;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class ReportCommand extends Command
{
    protected $signature = 'preflight:report
        {--file= : Path to an existing Preflight HTML report}
        {--latest : Open the latest Preflight HTML report from the configured reports directory}
        {--generate : Generate a fresh HTML report before opening}
        {--explain : Include explainability content when generating}
        {--preset=default : Preset to use when generating: relaxed, default, strict}
        {--changed : Generate report using changed-files mode}
        {--base-ref= : Base ref for changed-files mode}';

    protected $description = 'Open a local Preflight HTML report.';

    public function handle(ReportOpener $opener): int
    {
        if ((bool) $this->option('generate')) {
            return $this->generateAndOpen($opener);
        }

        $file = $this->option('file');
        if (is_string($file) && $file !== '') {
            return $this->openExisting($opener, $this->resolvePath($file));
        }

        if ((bool) $this->option('latest')) {
            $latest = $this->latestReport();
            if ($latest === null) {
                $this->line('No HTML reports found.');
                $this->line('Run: php artisan preflight:report --generate');
                return self::SUCCESS;
            }

            return $this->openExisting($opener, $latest);
        }

        $default = $this->defaultHtmlPath();
        if (! is_file($default)) {
            $this->line('No HTML report found.');
            $this->line('Run: php artisan preflight:audit --format=html');
            $this->line('Or run: php artisan preflight:report --generate');
            return self::SUCCESS;
        }

        return $this->openExisting($opener, $default);
    }

    private function generateAndOpen(ReportOpener $opener): int
    {
        $path = $this->defaultHtmlPath();
        $arguments = [
            '--format' => 'html',
            '--output' => $path,
            '--preset' => (string) $this->option('preset'),
        ];

        if ((bool) $this->option('explain')) {
            $arguments['--explain'] = true;
        }

        if ((bool) $this->option('changed')) {
            $arguments['--changed'] = true;
        }

        $baseRef = $this->option('base-ref');
        if (is_string($baseRef) && $baseRef !== '') {
            $arguments['--base-ref'] = $baseRef;
        }

        $exitCode = Artisan::call('preflight:audit', $arguments);
        $this->line('HTML report saved to: ' . $this->relativePath($path));

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        return $this->openExisting($opener, $path);
    }

    private function openExisting(ReportOpener $opener, string $path): int
    {
        if (! is_file($path)) {
            $this->error('HTML report file not found: ' . $this->relativePath($path));
            return self::FAILURE;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['html', 'htm'], true)) {
            $this->error('Report file must be .html or .htm: ' . $this->relativePath($path));
            return self::FAILURE;
        }

        if (! $opener->open($path)) {
            $this->warn('Unable to open HTML report automatically.');
            if ($opener->lastError() !== null) {
                $this->warn($opener->lastError());
            }
        }

        $this->line('HTML report: ' . $this->relativePath($path));
        return self::SUCCESS;
    }

    private function latestReport(): ?string
    {
        $directory = (string) config('preflight.reports.directory', storage_path('app/preflight'));
        if (! is_dir($directory)) {
            return null;
        }

        $files = array_filter(glob(rtrim($directory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . '*.{html,htm}', GLOB_BRACE) ?: [], 'is_file');

        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => ((int) filemtime($b)) <=> ((int) filemtime($a)));

        return $files[0];
    }

    private function defaultHtmlPath(): string
    {
        $packageDefault = storage_path('app/preflight-report.html');
        $reportsDefault = config('preflight.reports.default_html');

        if (is_string($reportsDefault) && $reportsDefault !== '' && $reportsDefault !== $packageDefault) {
            return $reportsDefault;
        }

        $htmlDefault = config('preflight.html.default_output');

        if (is_string($htmlDefault) && $htmlDefault !== '' && $htmlDefault !== $packageDefault) {
            return $htmlDefault;
        }

        if (is_string($reportsDefault) && $reportsDefault !== '') {
            return $reportsDefault;
        }

        if (is_string($htmlDefault) && $htmlDefault !== '') {
            return $htmlDefault;
        }

        return $packageDefault;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/');
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $base)) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return $path;
    }
}
