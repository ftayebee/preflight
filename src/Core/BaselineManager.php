<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class BaselineManager
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly FindingFingerprint $fingerprints,
    ) {
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<string, mixed>
     */
    public function generate(array $results): array
    {
        return [
            'generated_at' => date(DATE_ATOM),
            'count' => count($results),
            'fingerprints' => array_values(array_unique(array_map(
                fn (AuditResult $result): string => $this->fingerprint($result),
                $results
            ))),
        ];
    }

    /**
     * @param array<int, AuditResult> $results
     */
    public function save(array $results): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create baseline directory: ' . $directory);
        }

        $written = @file_put_contents(
            $path,
            json_encode($this->generate($results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        if ($written === false) {
            throw new \RuntimeException('Unable to write baseline file: ' . $path);
        }
    }

    /**
     * @return array<int, string>
     */
    public function load(): array
    {
        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $fingerprints = $payload['fingerprints'] ?? [];

        if (! is_array($fingerprints)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $fingerprint): string => (string) $fingerprint, $fingerprints)
        ));
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array{results: array<int, AuditResult>, ignored: int}
     */
    public function filter(array $results): array
    {
        $baseline = array_flip($this->load());
        $filtered = [];
        $ignored = 0;

        foreach ($results as $result) {
            if (isset($baseline[$this->fingerprint($result)])) {
                $ignored++;
                continue;
            }

            $filtered[] = $result;
        }

        return [
            'results' => $filtered,
            'ignored' => $ignored,
        ];
    }

    public function fingerprint(AuditResult $result): string
    {
        return $this->fingerprints->primary($result);
    }

    public function path(): string
    {
        return (string) $this->config->get('preflight.baseline_file', base_path('preflight-baseline.json'));
    }
}
