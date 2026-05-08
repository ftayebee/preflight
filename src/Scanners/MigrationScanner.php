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

final class MigrationScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'migrations';
    }

    public function scan(): array
    {
        $results = [];
        $usersMigrationFound = false;
        $usersMigrationHasRememberToken = false;

        foreach ($this->scanFiles($this->paths->database('migrations')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            $file = $this->paths->relative($path);
            $isUsersMigration = $this->isUsersMigration($scanContents);
            $isCreatingUsers = $this->isCreatingUsersMigration($scanContents);
            $usersMigrationFound = $usersMigrationFound || $isUsersMigration;
            $usersMigrationHasRememberToken = $usersMigrationHasRememberToken || ($isCreatingUsers && stripos($scanContents, 'rememberToken') !== false);

            if ($this->containsConfiguredColumn($scanContents, $this->optionArray('plaintext_password_columns', ['visible_password', 'plain_password', 'plaintext_password']))) {
                $results[] = $this->fileResult('MIGRATION_VISIBLE_PASSWORD', Severity::Critical, 'Plain password column detected', 'Migration creates a visible_password or plain_password column.', $file, $contents, 'password', 'Never create columns intended to store plain-text passwords.');
            }

            if ($isUsersMigration && preg_match('/[\'"]email[\'"].*?->nullable\s*\(/is', $scanContents) === 1) {
                $results[] = $this->fileResult('MIGRATION_USERS_EMAIL_NULLABLE', Severity::Medium, 'Users email column is nullable', 'The users table appears to allow nullable email addresses.', $file, $contents, 'email', 'Require email where authentication or account recovery depends on it.');
            }

            if (preg_match('/[\'"]password[\'"].*?->nullable\s*\(/is', $scanContents) === 1) {
                $results[] = $this->fileResult('MIGRATION_PASSWORD_NULLABLE', Severity::High, 'Password column is nullable', 'A password column appears to allow null values.', $file, $contents, 'password', 'Avoid nullable password columns unless an alternate auth provider flow is explicitly designed.');
            }

            foreach ($this->optionArray('suspicious_columns', ['is_admin' => 'medium', 'role' => 'low']) as $column => $severity) {
                if (preg_match('/(boolean|string)\s*\(\s*[\'"]' . preg_quote((string) $column, '/') . '[\'"]\s*[,)]/i', $scanContents) === 1) {
                    $code = (string) $column === 'is_admin' ? 'MIGRATION_IS_ADMIN_COLUMN' : 'MIGRATION_ROLE_COLUMN';
                    $title = (string) $column === 'is_admin' ? 'is_admin boolean column detected' : 'role string column detected';
                    $results[] = $this->fileResult($code, Severity::tryFrom((string) $severity) ?? Severity::Low, $title, "A {$column} column may be too limited for authorization logic.", $file, $contents, (string) $column, 'Use a proper roles and permissions design when authorization needs grow.');
                }
            }
        }

        if ($usersMigrationFound && ! $usersMigrationHasRememberToken) {
            $results[] = new AuditResult(
                'MIGRATION_USERS_REMEMBER_TOKEN_MISSING',
                $this->name(),
                Severity::Low,
                'Users table is missing remember token',
                'A users migration was found, but rememberToken() was not detected.',
                null,
                null,
                'Add rememberToken() if the application supports "remember me" sessions.',
                'high'
            );
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

    private function isUsersMigration(string $contents): bool
    {
        return preg_match('/Schema::(create|table)\s*\(\s*[\'"]users[\'"]/i', $contents) === 1;
    }

    private function isCreatingUsersMigration(string $contents): bool
    {
        return preg_match('/Schema::create\s*\(\s*[\'"]users[\'"]/i', $contents) === 1;
    }

    private function fileResult(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, 'high');
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    private function optionArray(string $key, array $default): array
    {
        return $this->options?->array($this->name(), $key, $default) ?? $default;
    }

    /**
     * @param array<int, string> $columns
     */
    private function containsConfiguredColumn(string $contents, array $columns): bool
    {
        foreach ($columns as $column) {
            if (preg_match('/[\'"]' . preg_quote($column, '/') . '[\'"]/i', $contents) === 1) {
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
