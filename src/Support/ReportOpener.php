<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

class ReportOpener
{
    private ?string $lastError = null;

    public function open(string $path): bool
    {
        $this->lastError = null;
        $path = $this->normalizePath($path);
        $command = PHP_OS_FAMILY === 'Windows'
            ? ['cmd', '/c', 'start', '', $path]
            : (PHP_OS_FAMILY === 'Darwin' ? ['open', $path] : ['xdg-open', $path]);
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorSpec, $pipes);

        if (! is_resource($process)) {
            $this->lastError = 'Unable to start browser opener.';
            return false;
        }

        $stderr = '';
        foreach ($pipes as $index => $pipe) {
            if ($index === 2) {
                $stderr = stream_get_contents($pipe) ?: '';
            }
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->lastError = trim($stderr) ?: 'Browser opener returned exit code ' . $exitCode . '.';
            return false;
        }

        return true;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
