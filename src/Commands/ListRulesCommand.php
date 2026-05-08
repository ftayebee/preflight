<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Core\RuleManager;
use FahimTayebee\Preflight\Core\RuleRegistry;
use Illuminate\Console\Command;

final class ListRulesCommand extends Command
{
    protected $signature = 'preflight:rules
        {--format=console : Output format: console, json, or md}';

    protected $description = 'List Preflight rule metadata and configuration.';

    public function handle(RuleRegistry $registry, RuleManager $rules): int
    {
        $format = strtolower((string) $this->option('format'));
        $items = $this->rules($registry, $rules);

        if ($format === 'json') {
            $this->line(json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($format === 'md' || $format === 'markdown') {
            $this->line($this->markdown($items));

            return self::SUCCESS;
        }

        if ($format !== 'console') {
            $this->error('Invalid format. Supported formats: console, json, md');

            return self::FAILURE;
        }

        $this->table(
            ['Code', 'Scanner', 'Default Severity', 'Configured Severity', 'Enabled', 'Description'],
            array_map(static fn (array $rule): array => [
                $rule['code'],
                $rule['scanner'],
                $rule['default_severity'],
                $rule['configured_severity'],
                $rule['enabled'] ? 'yes' : 'no',
                $rule['description'],
            ], $items)
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rules(RuleRegistry $registry, RuleManager $rules): array
    {
        $items = [];

        foreach ($registry->all() as $code => $metadata) {
            $configured = config("preflight.rules.{$code}.severity", $metadata['default_severity']);
            $items[] = [
                'code' => $code,
                'scanner' => $metadata['scanner'],
                'title' => $metadata['title'],
                'default_severity' => $metadata['default_severity'],
                'configured_severity' => is_string($configured) ? $configured : $metadata['default_severity'],
                'enabled' => $rules->isEnabled($code) && ! $rules->isIgnored($code),
                'description' => $metadata['description'],
                'recommendation' => $metadata['recommendation'],
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function markdown(array $items): string
    {
        $lines = ['# Preflight Rules', ''];

        foreach ($items as $rule) {
            $lines[] = '## ' . $rule['code'];
            $lines[] = '';
            $lines[] = 'Scanner: ' . $rule['scanner'] . '  ';
            $lines[] = 'Default Severity: ' . $rule['default_severity'] . '  ';
            $lines[] = 'Configured Severity: ' . $rule['configured_severity'] . '  ';
            $lines[] = 'Enabled: ' . ($rule['enabled'] ? 'yes' : 'no') . '  ';
            $lines[] = 'Description: ' . $rule['description'] . '  ';
            $lines[] = 'Recommendation: ' . $rule['recommendation'];
            $lines[] = '';
        }

        return rtrim(implode(PHP_EOL, $lines)) . PHP_EOL;
    }
}
