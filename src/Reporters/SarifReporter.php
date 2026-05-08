<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Reporters;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Reporters\Contracts\ReporterInterface;
use FahimTayebee\Preflight\Support\PathResolver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SarifReporter implements ReporterInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PathResolver $paths,
    ) {
    }

    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $results = array_values(array_filter(
            $report['results'],
            static fn (mixed $result): bool => $result instanceof AuditResult
        ));

        $payload = [
            'version' => '2.1.0',
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'runs' => [
                [
                    'invocations' => [
                        [
                            'executionSuccessful' => true,
                            'startTimeUtc' => $report['meta']['generated_at'] ?? date(DATE_ATOM),
                        ],
                    ],
                    'tool' => [
                        'driver' => [
                            'name' => (string) $this->config->get('preflight.sarif.tool_name', 'Preflight'),
                            'informationUri' => (string) $this->config->get('preflight.sarif.information_uri', 'https://github.com/fahimtayebee/preflight'),
                            'rules' => $this->rules($results),
                        ],
                    ],
                    'results' => array_map(fn (AuditResult $result): array => $this->result($result), $results),
                ],
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, array<string, mixed>>
     */
    private function rules(array $results): array
    {
        $rules = [];

        foreach ($results as $result) {
            if (isset($rules[$result->code])) {
                continue;
            }

            $rule = [
                'id' => $result->code,
                'name' => $result->title,
                'shortDescription' => [
                    'text' => $result->title,
                ],
                'fullDescription' => [
                    'text' => $result->message,
                ],
                'properties' => [
                    'tags' => [
                        $result->scanner,
                        $result->severity->value,
                        $result->confidence,
                    ],
                ],
            ];

            if ($result->recommendation !== null) {
                $rule['help'] = [
                    'text' => $result->recommendation,
                ];
            }

            $rules[$result->code] = $rule;
        }

        return array_values($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(AuditResult $result): array
    {
        $sarif = [
            'ruleId' => $result->code,
            'level' => $this->level($result->severity),
            'message' => [
                'text' => $result->message,
            ],
            'properties' => [
                'severity' => $result->severity->value,
                'scanner' => $result->scanner,
                'confidence' => $result->confidence,
                'recommendation' => $result->recommendation,
            ],
        ];

        if ($result->file !== null) {
            $physicalLocation = [
                'artifactLocation' => [
                    'uri' => $this->artifactUri($result->file),
                ],
            ];

            if ($result->line !== null) {
                $physicalLocation['region'] = [
                    'startLine' => $result->line,
                ];
            }

            $sarif['locations'] = [
                [
                    'physicalLocation' => $physicalLocation,
                ],
            ];
        } else {
            $sarif['locations'] = [];
        }

        return $sarif;
    }

    private function level(Severity $severity): string
    {
        return match ($severity) {
            Severity::Critical, Severity::High => 'error',
            Severity::Medium => 'warning',
            Severity::Low, Severity::Info => 'note',
        };
    }

    private function artifactUri(string $file): string
    {
        return str_replace('\\', '/', $this->paths->relative($file));
    }
}
