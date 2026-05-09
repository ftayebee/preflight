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

final class RequestScanner implements ScannerInterface, ContextAwareScannerInterface
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
        return 'requests';
    }

    public function scan(): array
    {
        $results = [];

        foreach ($this->scanFiles($this->paths->app('Http/Requests')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            if (! $this->extendsFormRequest($scanContents)) {
                continue;
            }

            $file = $this->paths->relative($path);
            $class = $this->className($scanContents) ?? pathinfo($path, PATHINFO_FILENAME);

            if (preg_match('/function\s+authorize\s*\(/i', $scanContents) !== 1) {
                $results[] = $this->result('REQUEST_AUTHORIZE_MISSING', Severity::Medium, 'FormRequest authorize method is missing', 'The FormRequest does not define an authorize() method.', $file, $contents, 'class ', 'Add authorize() and enforce permission checks where needed.', 'medium');
            } elseif (! in_array($class, $this->optionArray('allow_authorize_true_for', []), true) && preg_match('/function\s+authorize\s*\([^)]*\)\s*(?::\s*bool)?\s*\{[^}]*return\s+true\s*;/is', $scanContents) === 1) {
                $results[] = $this->result('REQUEST_AUTHORIZE_ALWAYS_TRUE', Severity::Low, 'FormRequest authorize always returns true', 'The FormRequest authorize() method returns true for every request.', $file, $contents, 'authorize', 'Use permission checks for sensitive requests.', 'medium');
            }

            if (preg_match('/function\s+rules\s*\(/i', $scanContents) !== 1) {
                $results[] = $this->result('REQUEST_RULES_MISSING', Severity::High, 'FormRequest rules method is missing', 'The FormRequest does not define a rules() method.', $file, $contents, 'class ', 'Add a rules() method with validation requirements.', 'high');
            } elseif (preg_match('/function\s+rules\s*\([^)]*\)\s*(?::\s*array)?\s*\{[^}]*return\s+(\[\s*\]|array\s*\(\s*\))\s*;/is', $scanContents) === 1) {
                $results[] = $this->result('REQUEST_RULES_EMPTY', Severity::Medium, 'FormRequest rules are empty', 'The FormRequest rules() method returns an empty array.', $file, $contents, 'rules', 'Add validation rules for incoming request data.', 'high');
            }

            $sensitiveRulePattern = implode('|', array_map('preg_quote', $this->optionArray('sensitive_rule_keywords', ['password', 'role', 'permission', 'is_admin'])));
            if (preg_match('/[\'"](' . $sensitiveRulePattern . ')[\'"]\s*=>\s*[^,\]]*nullable|[\'"]nullable\|[^\'"]*(' . $sensitiveRulePattern . ')|[\'"](' . $sensitiveRulePattern . ')[^\'"]*nullable/i', $scanContents) === 1) {
                $results[] = $this->result('REQUEST_NULLABLE_PASSWORD', Severity::Medium, 'Nullable password validation rule detected', 'The FormRequest appears to allow nullable password input.', $file, $contents, 'password', 'Avoid nullable password rules unless optional password changes are intentional.', 'medium');
            }

            if (preg_match('/[\'"]sometimes\b|\bsometimes\|/i', $scanContents) === 1) {
                $results[] = $this->result('REQUEST_SOMETIMES_RULE', Severity::Low, 'Sometimes validation rule detected', 'The FormRequest uses sometimes validation.', $file, $contents, 'sometimes', 'Review optional validation paths for sensitive fields.', 'low');
            }
        }

        return $results;
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

    private function result(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, $confidence);
    }

    private function extendsFormRequest(string $contents): bool
    {
        return preg_match('/extends\s+(FormRequest|.*\\\\FormRequest)\b/i', $contents) === 1;
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
