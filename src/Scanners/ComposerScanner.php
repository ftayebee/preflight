<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;

final class ComposerScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'composer';
    }

    public function scan(): array
    {
        $results = [];
        $composerPath = $this->paths->composerJson();
        $composerContents = $this->files->get($composerPath);

        if (! is_file($this->paths->composerLock())) {
            $results[] = new AuditResult('COMPOSER_LOCK_MISSING', $this->name(), Severity::Medium, 'composer.lock is missing', 'composer.lock was not found.', 'composer.lock', null, 'Commit composer.lock for applications to keep dependency installs reproducible.', 'high');
        } elseif ($this->isLockOutdated($composerPath, $this->paths->composerLock())) {
            $results[] = new AuditResult('COMPOSER_LOCK_OUTDATED', $this->name(), Severity::Medium, 'composer.lock may be outdated', 'composer.json appears newer than composer.lock.', 'composer.lock', null, 'Run composer update or composer install and commit the updated lock file.', 'medium');
        }

        if ($composerContents === null) {
            return $results;
        }

        $file = $this->paths->relative($composerPath);
        try {
            $composer = json_decode($composerContents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                ...$results,
                $this->result('COMPOSER_JSON_INVALID', Severity::High, 'composer.json is invalid', 'composer.json could not be parsed as valid JSON.', $file ?? 'composer.json', $composerContents, '{', 'Fix composer.json so Composer and deployment tooling can parse it.', 'high'),
            ];
        }

        if (! is_array($composer)) {
            return $results;
        }

        if (($composer['minimum-stability'] ?? null) === 'dev') {
            $results[] = $this->result('COMPOSER_MINIMUM_STABILITY_DEV', Severity::Medium, 'minimum-stability is dev', 'composer.json sets minimum-stability to dev.', $file, $composerContents, 'minimum-stability', 'Avoid dev stability in production applications.', 'high');

            if (($composer['prefer-stable'] ?? null) !== true) {
                $results[] = $this->result('COMPOSER_PREFER_STABLE_MISSING', Severity::Low, 'prefer-stable missing with dev stability', 'minimum-stability is dev without prefer-stable=true.', $file, $composerContents, 'minimum-stability', 'Set prefer-stable=true if dev stability is required.', 'high');
            }
        }

        $require = (array) ($composer['require'] ?? []);
        foreach ($this->optionArray('risky_packages_in_require', ['barryvdh/laravel-debugbar' => 'high', 'facade/ignition' => 'high', 'filp/whoops' => 'medium']) as $package => $severity) {
            if (array_key_exists($package, $require)) {
                $results[] = $this->result('COMPOSER_DEBUG_PACKAGE_IN_REQUIRE', Severity::tryFrom((string) $severity) ?? Severity::High, 'Debug package is installed in production require', "{$package} is listed in require instead of require-dev.", $file, $composerContents, (string) $package, 'Move debug packages to require-dev or remove them.', 'high');
            }
        }

        foreach ((array) ($composer['scripts'] ?? []) as $name => $script) {
            $scriptText = strtolower(implode(' ', (array) $script));
            if ($this->containsAny($scriptText, $this->optionArray('unsafe_script_keywords', ['rm -rf', 'chmod -R 777', 'migrate:fresh', 'db:wipe']))) {
                $results[] = $this->result('COMPOSER_UNSAFE_SCRIPT', Severity::High, 'Unsafe Composer script detected', "Composer script {$name} contains a risky command.", $file, $composerContents, (string) $name, 'Remove destructive Composer scripts or move them to guarded deployment tooling.', 'medium');
            }
        }

        return $results;
    }

    private function result(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, $confidence);
    }

    private function isLockOutdated(string $composerPath, string $lockPath): bool
    {
        $composerTime = @filemtime($composerPath);
        $lockTime = @filemtime($lockPath);

        return is_int($composerTime) && is_int($lockTime) && $composerTime > $lockTime;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $contents, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($contents, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    private function optionArray(string $key, array $default): array
    {
        $resolver = $this->options ?? app(ScannerOptionResolver::class);

        return $resolver->array($this->name(), $key, $default);
    }
}
