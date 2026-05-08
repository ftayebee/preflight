<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;

final class EnvScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'env';
    }

    public function scan(): array
    {
        $path = $this->paths->env();
        $contents = $this->files->get($path);

        if ($contents === null) {
            return [];
        }

        $env = $this->parseEnv($contents);
        $file = $this->paths->relative($path);
        $results = [];
        $appEnv = strtolower((string) ($env['APP_ENV'] ?? ''));
        $isLocal = in_array($appEnv, $this->optionArray('local_env_names', ['local', 'development', 'testing']), true);
        $isProductionLike = $appEnv === '' || in_array($appEnv, $this->optionArray('production_env_names', ['production', 'prod', 'staging']), true);

        if ($this->truthy($env['APP_DEBUG'] ?? null)) {
            $severity = $isLocal ? Severity::Low : Severity::Critical;
            $message = $isLocal
                ? 'APP_DEBUG=true is enabled in a local-like environment.'
                : 'APP_DEBUG=true exposes stack traces and sensitive application details.';
            $results[] = $this->result('ENV_DEBUG_TRUE', $severity, 'Debug mode is enabled', $message, $file, $contents, 'APP_DEBUG', 'Set APP_DEBUG=false before production deployment.');
        }

        if ($appEnv === 'local' && $isProductionLike) {
            $results[] = $this->result('ENV_LOCAL_ENVIRONMENT', Severity::High, 'Application environment is local', 'APP_ENV=local should not be used for deployed production-like environments.', $file, $contents, 'APP_ENV', 'Use APP_ENV=production for production deployments.');
        }

        if (strtolower((string) ($env['LOG_LEVEL'] ?? '')) === 'debug') {
            $results[] = $this->result('ENV_LOG_LEVEL_DEBUG', Severity::Medium, 'Debug log level is enabled', 'LOG_LEVEL=debug can write sensitive data to application logs.', $file, $contents, 'LOG_LEVEL', 'Use a less verbose log level such as error or warning in production.');
        }

        if ($this->optionBool('require_secure_session_cookie', true) && array_key_exists('SESSION_SECURE_COOKIE', $env) && ! $this->truthy($env['SESSION_SECURE_COOKIE'])) {
            $results[] = $this->result('ENV_SESSION_SECURE_COOKIE_FALSE', Severity::Medium, 'Session secure cookie is disabled', 'SESSION_SECURE_COOKIE=false allows session cookies over non-HTTPS connections.', $file, $contents, 'SESSION_SECURE_COOKIE', 'Set SESSION_SECURE_COOKIE=true when serving the app over HTTPS.');
        }

        if (trim((string) ($env['APP_KEY'] ?? '')) === '') {
            $results[] = $this->result('ENV_APP_KEY_MISSING', Severity::Critical, 'Application key is missing', 'Missing APP_KEY prevents secure encryption and signed data handling.', $file, $contents, 'APP_KEY', 'Generate an app key with php artisan key:generate.');
        }

        if (array_key_exists('DB_PASSWORD', $env) && trim((string) $env['DB_PASSWORD']) === '' && ! ($isLocal && $this->optionBool('allow_empty_db_password_in_local', true))) {
            $results[] = $this->result('ENV_DB_PASSWORD_EMPTY', Severity::Medium, 'Database password is empty', 'An empty DB_PASSWORD is risky for shared, staging, or production databases.', $file, $contents, 'DB_PASSWORD', 'Use a strong database password and least-privileged database user.');
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), '\'"');
        }

        return $values;
    }

    private function truthy(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'on'], true);
    }

    private function result(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, 'high');
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        return array_map('strtolower', $this->options?->array($this->name(), $key, $default) ?? $default);
    }

    private function optionBool(string $key, bool $default): bool
    {
        return $this->options?->bool($this->name(), $key, $default) ?? $default;
    }
}
