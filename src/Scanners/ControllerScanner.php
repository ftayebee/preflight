<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use Illuminate\Support\Str;

final class ControllerScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'controllers';
    }

    public function scan(): array
    {
        $results = [];

        foreach ($this->scanFiles($this->paths->app('Http/Controllers')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            $file = $this->paths->relative($path);
            $sensitiveMethods = $this->optionArray('sensitive_method_names', ['store', 'update', 'destroy', 'delete', 'remove', 'restore', 'forceDelete']);
            $hasAuthorization = $this->hasAuthorizationUsage($scanContents) || $this->hasFormRequestUsage($scanContents);
            $hasSensitiveMethods = preg_match('/function\s+(' . implode('|', array_map('preg_quote', $sensitiveMethods)) . ')\s*\(/i', $scanContents) === 1;
            $hasOnlyReadMethods = ! $hasSensitiveMethods && preg_match('/function\s+(index|show)\s*\(/i', $scanContents) === 1;

            if (! $hasAuthorization && ! $hasOnlyReadMethods) {
                $results[] = $this->fileResult('CONTROLLER_MISSING_AUTHORIZATION', Severity::Medium, 'Controller has no visible authorization checks', 'No middleware, policy, gate, role, permission, or can checks were found.', $file, $contents, 'class ', 'Add route middleware, controller middleware, policies, or gate checks where needed.', 'medium');
            }

            if ($hasSensitiveMethods && ! $hasAuthorization) {
                $results[] = $this->fileResult('CONTROLLER_WRITE_WITHOUT_AUTHORIZATION', Severity::High, 'Write controller methods lack authorization checks', 'Controller has store, update, delete, or destroy methods but no visible authorization keyword.', $file, $contents, 'function ', 'Authorize state-changing actions with policies, gates, middleware, or permission checks.', 'medium');
            }

            if ($this->containsAny($scanContents, $this->optionArray('plaintext_password_keywords', ['visible_password', 'plain_password', 'plaintext_password']))) {
                $results[] = $this->fileResult('CONTROLLER_PLAINTEXT_PASSWORD', Severity::Critical, 'Plain password field referenced', 'Controller references visible_password or plain_password.', $file, $contents, 'password', 'Never store or expose plain-text passwords.', 'high');
            }

            if (preg_match('/DB::statement\s*\(\s*[\'"]\s*(' . implode('|', array_map('preg_quote', $this->optionArray('dangerous_db_keywords', ['DROP', 'TRUNCATE', 'db:wipe', 'migrate:fresh']))) . ')\b/i', $scanContents) === 1) {
                $results[] = $this->fileResult('CONTROLLER_DESTRUCTIVE_DB_STATEMENT', Severity::Critical, 'Destructive raw database statement detected', 'Controller executes DROP or TRUNCATE through DB::statement().', $file, $contents, 'DB::statement', 'Remove destructive database statements from HTTP controllers.', 'high');
            }

            if ($this->hasUnsafeAllUsageInWriteContext($scanContents)) {
                $results[] = $this->fileResult('CONTROLLER_REQUEST_ALL_WRITE_CONTEXT', Severity::Medium, 'Direct request all usage in write context', 'Controller appears to pass all request input into a create or update flow.', $file, $contents, 'all()', 'Use validated input from Form Requests or $request->validated().', 'medium');
            }
        }

        return $results;
    }

    private function hasAuthorizationUsage(string $content): bool
    {
        foreach ($this->optionArray('authorization_keywords', ['$this->authorize', '$this->authorizeResource', 'Gate::allows', 'Gate::denies', 'Gate::authorize', '->middleware(\'can:', '->middleware("can:', 'permission:', 'role:', 'can:']) as $keyword) {
            if ($keyword !== '' && stripos($content, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function methodContainsAuthorization(string $methodBody): bool
    {
        return $this->hasAuthorizationUsage($methodBody);
    }

    private function extractMethodBody(string $content, string $methodName): ?string
    {
        if (preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*\{(?P<body>.*?)^\s*\}/ims', $content, $matches) !== 1) {
            return null;
        }

        return $matches['body'];
    }

    private function hasFormRequestParameter(string $methodSignature): bool
    {
        return preg_match('/\b(?!Illuminate\\\\Http\\\\Request\b)([A-Z][A-Za-z0-9_]*Request)\s+\$/', $methodSignature) === 1;
    }

    private function hasFormRequestUsage(string $contents): bool
    {
        if (preg_match_all('/function\s+(store|update)\s*\((?P<signature>[^)]*)\)/i', $contents, $matches) === 0) {
            return false;
        }

        foreach ($matches['signature'] as $signature) {
            if ($this->hasFormRequestParameter($signature)) {
                return true;
            }
        }

        return false;
    }

    private function hasUnsafeAllUsageInWriteContext(string $contents): bool
    {
        if (preg_match('/(Model::)?(create|update|fill|forceFill)\s*\(\s*(request\(\)->all\(\)|\$request->all\(\))/is', $contents) === 1) {
            return true;
        }

        foreach (['store', 'update', 'create'] as $method) {
            $body = $this->extractMethodBody($contents, $method);
            if ($body !== null && preg_match('/request\(\)->all\(\)|\$request->all\(\)/i', $body) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function scanFiles(string $directory): array
    {
        return array_values(array_filter(
            $this->files->files($directory),
            fn (string $path): bool => ! $this->isIgnored($path)
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

    private function fileResult(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, $confidence);
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        return $this->options?->array($this->name(), $key, $default) ?? $default;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $contents, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($contents, $needle) !== false) {
                return true;
            }
        }

        return false;
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
