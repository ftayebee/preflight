<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\RuleRegistry;
use FahimTayebee\Preflight\Scanners\AuthScanner;
use FahimTayebee\Preflight\Scanners\BladeScanner;
use FahimTayebee\Preflight\Scanners\ComposerScanner;
use FahimTayebee\Preflight\Scanners\ControllerScanner;
use FahimTayebee\Preflight\Scanners\EnvScanner;
use FahimTayebee\Preflight\Scanners\ModelScanner;
use FahimTayebee\Preflight\Scanners\PolicyScanner;
use FahimTayebee\Preflight\Scanners\RequestScanner;
use FahimTayebee\Preflight\Scanners\RouteScanner;
use FahimTayebee\Preflight\Tests\TestCase;

final class RuleFixtureTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('importantRuleProvider')]
    public function test_clean_fixture_does_not_trigger_rule_and_risky_fixture_triggers_it(string $code, string $scanner): void
    {
        $registry = app(RuleRegistry::class)->all();
        $this->assertArrayHasKey($code, $registry);

        $clean = $this->scanFixture($code, 'clean', $scanner);
        $risky = $this->scanFixture($code, 'risky', $scanner);

        $this->assertFalse(collect($clean)->contains(
            fn (AuditResult $result): bool => $result->code === $code
        ), $code . ' should not be reported for clean fixture.');

        $this->assertTrue(collect($risky)->contains(
            fn (AuditResult $result): bool => $result->code === $code
        ), $code . ' should be reported for risky fixture.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function importantRuleProvider(): array
    {
        return [
            'ENV_DEBUG_TRUE' => ['ENV_DEBUG_TRUE', 'env'],
            'ROUTE_DESTRUCTIVE_GET' => ['ROUTE_DESTRUCTIVE_GET', 'routes'],
            'ROUTE_SENSITIVE_WITHOUT_AUTH' => ['ROUTE_SENSITIVE_WITHOUT_AUTH', 'routes'],
            'CONTROLLER_MISSING_AUTHORIZATION' => ['CONTROLLER_MISSING_AUTHORIZATION', 'controllers'],
            'MODEL_GUARDED_EMPTY' => ['MODEL_GUARDED_EMPTY', 'models'],
            'REQUEST_AUTHORIZE_ALWAYS_TRUE' => ['REQUEST_AUTHORIZE_ALWAYS_TRUE', 'requests'],
            'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE' => ['COMPOSER_DEBUG_PACKAGE_IN_REQUIRE', 'composer'],
            'BLADE_RAW_OUTPUT' => ['BLADE_RAW_OUTPUT', 'blade'],
            'BLADE_FORM_MISSING_CSRF' => ['BLADE_FORM_MISSING_CSRF', 'blade'],
            'POLICY_ALWAYS_TRUE' => ['POLICY_ALWAYS_TRUE', 'policies'],
            'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE' => ['AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE', 'auth'],
        ];
    }

    /**
     * @return array<int, AuditResult>
     */
    private function scanFixture(string $code, string $variant, string $scanner): array
    {
        $path = realpath(__DIR__ . '/../Fixtures/rules/' . $code . '/' . $variant);
        $this->assertIsString($path);

        config()->set('preflight.base_path', $path);

        if (in_array($scanner, ['routes', 'auth'], true)) {
            $routeFile = $path . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
            if (is_file($routeFile)) {
                require $routeFile;
            }
        }

        return match ($scanner) {
            'env' => app(EnvScanner::class)->scan(),
            'routes' => app(RouteScanner::class)->scan(),
            'controllers' => app(ControllerScanner::class)->scan(),
            'models' => app(ModelScanner::class)->scan(),
            'requests' => app(RequestScanner::class)->scan(),
            'composer' => app(ComposerScanner::class)->scan(),
            'blade' => app(BladeScanner::class)->scan(),
            'policies' => app(PolicyScanner::class)->scan(),
            'auth' => app(AuthScanner::class)->scan(),
        };
    }
}
