<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Concerns\UsesScannerContext;
use FahimTayebee\Preflight\Scanners\Contracts\ContextAwareScannerInterface;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use Illuminate\Support\Str;

final class PolicyScanner implements ScannerInterface, ContextAwareScannerInterface
{
    use UsesScannerContext;

    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'policies';
    }

    public function scan(): array
    {
        $results = [];
        $models = $this->models();
        $policies = $this->policyFiles();

        foreach ($models as $modelName => $model) {
            if (! $this->isImportantModel($modelName) || $this->isIgnoredModel($modelName) || $model['pivot']) {
                continue;
            }

            if (! isset($policies[$modelName])) {
                $results[] = new AuditResult(
                    'POLICY_MISSING_FOR_MODEL',
                    $this->name(),
                    Severity::Medium,
                    'Important model is missing a policy',
                    "The {$modelName} model appears important but app/Policies/{$modelName}Policy.php was not found.",
                    $model['file'],
                    $model['line'],
                    'Create a policy with php artisan make:policy ' . $modelName . 'Policy --model=' . $modelName . ' and register or auto-discover it.',
                    'medium',
                    ['model' => $modelName]
                );
            }
        }

        foreach ($policies as $modelName => $policy) {
            $contents = $this->files->get($policy['path']);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            $file = $this->paths->relative($policy['path']);

            if (! isset($models[$modelName])) {
                $results[] = new AuditResult(
                    'POLICY_MODEL_NOT_FOUND',
                    $this->name(),
                    Severity::Low,
                    'Policy model was not found',
                    "The {$policy['class']} policy exists but app/Models/{$modelName}.php was not found.",
                    $file,
                    1,
                    'Rename the policy, create the matching model, or remove stale policy files.',
                    'medium',
                    ['model' => $modelName, 'policy' => $policy['class']]
                );
            }

            foreach ($this->optionArray('required_policy_methods', ['viewAny', 'view', 'create', 'update', 'delete']) as $method) {
                if (! $this->hasMethod($scanContents, $method)) {
                    $results[] = new AuditResult(
                        'POLICY_METHOD_MISSING',
                        $this->name(),
                        Severity::Medium,
                        'Policy is missing a required method',
                        "The {$policy['class']} policy is missing {$method}().",
                        $file,
                        1,
                        "Add {$method}() to the policy and return an explicit authorization decision.",
                        'high',
                        ['policy' => $policy['class'], 'method' => $method]
                    );
                }
            }

            foreach ($this->alwaysTrueMethods($scanContents) as $method => $line) {
                if ($method === 'before' && $this->optionBool('allow_policy_before_true', true)) {
                    continue;
                }

                $results[] = new AuditResult(
                    'POLICY_ALWAYS_TRUE',
                    $this->name(),
                    Severity::High,
                    'Policy method always returns true',
                    "The {$policy['class']}::{$method}() method directly returns true.",
                    $file,
                    $line,
                    'Replace return true with a role, ownership, permission, or Gate check that matches the action.',
                    'medium',
                    ['policy' => $policy['class'], 'method' => $method]
                );
            }
        }

        return $results;
    }

    /**
     * @return array<string, array{file: string, line: int|null, pivot: bool}>
     */
    private function models(): array
    {
        $models = [];

        foreach ($this->scanFiles($this->paths->app('Models')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            $class = $this->className($scanContents) ?? pathinfo($path, PATHINFO_FILENAME);
            $models[$class] = [
                'file' => $this->paths->relative($path),
                'line' => $this->files->lineFor($contents, 'class '),
                'pivot' => preg_match('/extends\s+(Pivot|.*\\\\Pivot)\b/i', $scanContents) === 1 || str_ends_with($class, 'Pivot'),
            ];
        }

        return $models;
    }

    /**
     * @return array<string, array{path: string, class: string}>
     */
    private function policyFiles(): array
    {
        $policies = [];

        foreach ($this->scanFiles($this->paths->app('Policies')) as $path) {
            $class = pathinfo($path, PATHINFO_FILENAME);

            if (! str_ends_with($class, 'Policy')) {
                continue;
            }

            $modelName = substr($class, 0, -6);

            if ($modelName === '') {
                continue;
            }

            $policies[$modelName] = ['path' => $path, 'class' => $class];
        }

        return $policies;
    }

    /**
     * @return array<int, string>
     */
    private function scanFiles(string $directory): array
    {
        return array_values(array_filter(
            $this->files->files($directory),
            fn (string $path): bool => ! $this->isIgnored($path) && $this->shouldScanFile($path)
        ));
    }

    private function isIgnored(string $path): bool
    {
        $relative = strtolower($this->paths->relative($path));

        foreach ((array) config('preflight.ignored_paths', []) as $pattern) {
            if (Str::is(strtolower((string) $pattern), $relative)) {
                return true;
            }
        }

        return false;
    }

    private function isImportantModel(string $modelName): bool
    {
        return in_array($modelName, $this->optionArray('important_model_names', [
            'User',
            'Admin',
            'Role',
            'Permission',
            'Payment',
            'Order',
            'Invoice',
            'Transaction',
            'Product',
            'Project',
            'Team',
            'Tournament',
            'Match',
            'Player',
        ]), true);
    }

    private function isIgnoredModel(string $modelName): bool
    {
        return in_array($modelName, $this->optionArray('ignored_models', []), true);
    }

    private function hasMethod(string $contents, string $method): bool
    {
        return preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/i', $contents) === 1;
    }

    /**
     * @return array<string, int>
     */
    private function alwaysTrueMethods(string $contents): array
    {
        $methods = [];

        if (preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*(?::\s*[^{]+)?\{\s*return\s+true\s*;\s*\}/is', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $methods;
        }

        foreach ($matches[1] as $index => $match) {
            $methods[$match[0]] = substr_count(substr($contents, 0, $matches[0][$index][1]), "\n") + 1;
        }

        return $methods;
    }

    private function className(string $contents): ?string
    {
        return preg_match('/class\s+([A-Za-z0-9_]+)/', $contents, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        $resolver = $this->options ?? app(ScannerOptionResolver::class);

        return $resolver->array($this->name(), $key, $default);
    }

    private function optionBool(string $key, bool $default): bool
    {
        $resolver = $this->options ?? app(ScannerOptionResolver::class);

        return $resolver->bool($this->name(), $key, $default);
    }

    private function stripPhpComments(string $content): string
    {
        $tokens = token_get_all($content);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $output .= is_array($token) ? $token[1] : $token;
        }

        return $output;
    }
}
