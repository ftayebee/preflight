<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Support;

class GitChangedFilesResolver
{
    private ?string $lastError = null;

    public function __construct(
        private readonly PathResolver $paths,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function changedFiles(string $baseRef = 'origin/main'): array
    {
        $this->lastError = null;

        if (! $this->isGitAvailable()) {
            return [];
        }

        $result = $this->run(['git', 'diff', '--name-only', $baseRef . '...HEAD']);

        if ($result['exit_code'] !== 0) {
            $this->lastError = trim($result['stderr']) ?: trim($result['stdout']) ?: 'Unable to resolve changed files with git diff.';

            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $path): string => $this->normalizePath($path),
            preg_split('/\R/', trim($result['stdout'])) ?: []
        ))));
    }

    public function isGitAvailable(): bool
    {
        $this->lastError = null;

        $result = $this->run(['git', '--version']);

        if ($result['exit_code'] !== 0) {
            $this->lastError = trim($result['stderr']) ?: 'Git is not available.';

            return false;
        }

        return true;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?: $path;

        return ltrim($path, './');
    }

    /**
     * @param array<int, string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function run(array $command): array
    {
        if (! is_dir($this->paths->base())) {
            return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Project path does not exist: ' . $this->paths->base()];
        }

        if (class_exists(\Symfony\Component\Process\Process::class)) {
            $process = new \Symfony\Component\Process\Process($command, $this->paths->base());
            $process->setTimeout(30);
            $process->run();

            return [
                'exit_code' => $process->getExitCode() ?? 1,
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        }

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorSpec, $pipes, $this->paths->base());

        if (! is_resource($process)) {
            return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'Unable to start git process.'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }
}
