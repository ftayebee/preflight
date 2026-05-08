<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Core\AuditManager;
use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\FindingFingerprint;
use FahimTayebee\Preflight\Core\RuleRegistry;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\ComposerScanner;
use FahimTayebee\Preflight\Scanners\ControllerScanner;
use FahimTayebee\Preflight\Scanners\EnvScanner;
use FahimTayebee\Preflight\Scanners\MigrationScanner;
use FahimTayebee\Preflight\Scanners\ModelScanner;
use FahimTayebee\Preflight\Scanners\RequestScanner;
use FahimTayebee\Preflight\Scanners\RouteScanner;
use FahimTayebee\Preflight\Tests\TestCase;

final class ScannerReliabilityTest extends TestCase
{
    public function test_fingerprint_normalizes_path_separators_and_ignores_line_for_primary(): void
    {
        $fingerprints = new FindingFingerprint();
        $a = new AuditResult('ENV_DEBUG_TRUE', 'env', Severity::Critical, 'Debug mode is enabled', 'Message', 'app\\Http\\Foo.php', 10);
        $b = new AuditResult('ENV_DEBUG_TRUE', 'env', Severity::Critical, 'Debug mode is enabled', 'Message', 'app/Http/Foo.php', 99);
        $c = new AuditResult('ENV_DEBUG_TRUE', 'env', Severity::Critical, 'Debug mode is enabled', 'Message', 'app/Http/Bar.php', 10);

        $this->assertSame($fingerprints->primary($a), $fingerprints->primary($b));
        $this->assertNotSame($fingerprints->primary($a), $fingerprints->primary($c));
        $this->assertNotSame($fingerprints->secondary($a), $fingerprints->secondary($b));
    }

    public function test_deduplication_counts_duplicate_once_and_keeps_higher_severity(): void
    {
        $manager = app(AuditManager::class);
        $method = new \ReflectionMethod($manager, 'deduplicate');
        $method->setAccessible(true);

        $results = $method->invoke($manager, [
            new AuditResult('ROUTE_NO_MIDDLEWARE', 'routes', Severity::Low, 'Same', 'Message', 'routes/web.php', 1, confidence: 'low'),
            new AuditResult('ROUTE_NO_MIDDLEWARE', 'routes', Severity::High, 'Same', 'Message', 'routes\\web.php', 2, confidence: 'high'),
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('high', $results[0]->severity->value);
    }

    public function test_route_reliability_cases(): void
    {
        $router = app('router');
        $router->get('login', fn () => 'ok');
        $router->get('admin', ['middleware' => ['web'], 'uses' => 'AdminController@index']);
        $router->get('admin/secure', ['middleware' => ['auth'], 'uses' => 'AdminController@index']);
        $router->get('users/delete', ['middleware' => ['auth'], 'uses' => 'UserController@delete']);
        $router->post('users/delete', ['middleware' => ['auth'], 'uses' => 'UserController@delete']);
        $router->get('api/ping', fn () => 'pong');

        $results = app(RouteScanner::class)->scan();
        $codesByUri = collect($results)->groupBy(fn (AuditResult $result): string => $result->metadata['uri'] ?? '');

        $this->assertFalse(($codesByUri->get('login') ?? collect())->contains(fn (AuditResult $r): bool => $r->code === 'ROUTE_NO_MIDDLEWARE'));
        $this->assertTrue(($codesByUri->get('admin') ?? collect())->contains(fn (AuditResult $r): bool => $r->code === 'ROUTE_SENSITIVE_WITHOUT_AUTH'));
        $this->assertFalse(($codesByUri->get('admin/secure') ?? collect())->contains(fn (AuditResult $r): bool => $r->code === 'ROUTE_SENSITIVE_WITHOUT_AUTH'));
        $this->assertTrue(($codesByUri->get('users/delete') ?? collect())->contains(fn (AuditResult $r): bool => $r->code === 'ROUTE_DESTRUCTIVE_GET'));
        $this->assertFalse(($codesByUri->get('api/ping') ?? collect())->contains(fn (AuditResult $r): bool => $r->code === 'ROUTE_NO_MIDDLEWARE'));
    }

    public function test_controller_false_positive_reductions(): void
    {
        $dir = base_path('app/Http/Controllers');
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents($dir . '/SafeController.php', <<<'PHP'
<?php
class SafeController {
    public function index() { $data = request()->all(); return $data; }
    public function store($request) { $this->authorize('create'); return User::create($request->validated()); }
}
PHP);
        file_put_contents($dir . '/CommentController.php', <<<'PHP'
<?php
class CommentController {
    public function store($request) { /* $this->authorize('create'); */ return User::create($request->all()); }
}
PHP);

        $results = app(ControllerScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(fn (AuditResult $r): bool => $r->file === 'app/Http/Controllers/SafeController.php' && $r->code === 'CONTROLLER_REQUEST_ALL_WRITE_CONTEXT'));
        $this->assertTrue(collect($results)->contains(fn (AuditResult $r): bool => $r->file === 'app/Http/Controllers/CommentController.php' && $r->code === 'CONTROLLER_WRITE_WITHOUT_AUTHORIZATION'));

        @unlink($dir . '/SafeController.php');
        @unlink($dir . '/CommentController.php');
    }

    public function test_model_reliability_cases(): void
    {
        $dir = base_path('app/Models');
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents($dir . '/HashedUser.php', <<<'PHP'
<?php
use Illuminate\Database\Eloquent\Model;
class HashedUser extends Model {
    protected $fillable = ['password'];
    protected $casts = ['password' => 'hashed'];
}
PHP);
        file_put_contents($dir . '/PivotThing.php', <<<'PHP'
<?php
use Illuminate\Database\Eloquent\Relations\Pivot;
class PivotThing extends Pivot {}
PHP);
        file_put_contents($dir . '/Commented.php', <<<'PHP'
<?php
use Illuminate\Database\Eloquent\Model;
class Commented extends Model {
    // protected $guarded = [];
    protected $fillable = ['name'];
}
PHP);

        $results = app(ModelScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(fn (AuditResult $r): bool => $r->file === 'app/Models/HashedUser.php' && $r->code === 'MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING'));
        $this->assertFalse(collect($results)->contains(fn (AuditResult $r): bool => $r->file === 'app/Models/PivotThing.php' && $r->code === 'MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION'));
        $this->assertFalse(collect($results)->contains(fn (AuditResult $r): bool => $r->file === 'app/Models/Commented.php' && $r->code === 'MODEL_GUARDED_EMPTY'));

        @unlink($dir . '/HashedUser.php');
        @unlink($dir . '/PivotThing.php');
        @unlink($dir . '/Commented.php');
    }

    public function test_composer_reliability_cases(): void
    {
        $composerPath = base_path('composer.json');
        $original = file_get_contents($composerPath);

        try {
            file_put_contents($composerPath, '{"require":');
            $invalid = app(ComposerScanner::class)->scan();
            $this->assertTrue(collect($invalid)->contains(fn (AuditResult $r): bool => $r->code === 'COMPOSER_JSON_INVALID'));

            file_put_contents($composerPath, json_encode(['require-dev' => ['barryvdh/laravel-debugbar' => '^3.0'], 'scripts' => ['safe' => 'php artisan config:cache']]));
            $safe = app(ComposerScanner::class)->scan();
            $this->assertFalse(collect($safe)->contains(fn (AuditResult $r): bool => $r->code === 'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE'));
            $this->assertFalse(collect($safe)->contains(fn (AuditResult $r): bool => $r->code === 'COMPOSER_UNSAFE_SCRIPT'));
        } finally {
            file_put_contents($composerPath, (string) $original);
        }
    }

    public function test_env_reliability_cases(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=\"production\"\nAPP_DEBUG=\"true\"\nAPP_KEY=\nDB_PASSWORD=\n");
        $production = app(EnvScanner::class)->scan();
        $this->assertSame('critical', collect($production)->firstWhere('code', 'ENV_DEBUG_TRUE')->severity->value);
        $this->assertTrue(collect($production)->contains(fn (AuditResult $r): bool => $r->code === 'ENV_APP_KEY_MISSING'));
        $this->assertTrue(collect($production)->contains(fn (AuditResult $r): bool => $r->code === 'ENV_DB_PASSWORD_EMPTY'));

        file_put_contents(base_path('.env'), "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=base64:test\nDB_PASSWORD=\n");
        $local = app(EnvScanner::class)->scan();
        $this->assertSame('low', collect($local)->firstWhere('code', 'ENV_DEBUG_TRUE')->severity->value);
        $this->assertFalse(collect($local)->contains(fn (AuditResult $r): bool => $r->code === 'ENV_DB_PASSWORD_EMPTY'));
    }

    public function test_fixture_summaries_and_rule_registry_coverage(): void
    {
        $registry = app(RuleRegistry::class)->all();
        $risky = $this->scanFixture('laravel-app-risky');
        $clean = $this->scanFixture('laravel-app-clean');

        $this->assertGreaterThanOrEqual(80, $clean['score']);
        $this->assertSame(0, $clean['counts']['critical']);
        $this->assertGreaterThan(0, $risky['counts']['critical'] + $risky['counts']['high'], json_encode(array_map(fn (AuditResult $r): array => $r->toArray(), $risky['all_results'])));
        $this->assertContains('ENV_DEBUG_TRUE', array_map(fn (AuditResult $r): string => $r->code, $risky['all_results']));
        $this->assertContains('COMPOSER_DEBUG_PACKAGE_IN_REQUIRE', array_map(fn (AuditResult $r): string => $r->code, $risky['all_results']));

        foreach (array_merge($clean['all_results'], $risky['all_results']) as $result) {
            $this->assertArrayHasKey($result->code, $registry);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scanFixture(string $name): array
    {
        config()->set('preflight.base_path', realpath(__DIR__ . '/../Fixtures/' . $name));
        config()->set('preflight.enabled_scanners', ['env', 'controllers', 'models', 'migrations', 'requests', 'composer']);
        foreach (['env', 'controllers', 'models', 'migrations', 'requests', 'composer'] as $scanner) {
            config()->set("preflight.scanners.{$scanner}.enabled", true);
        }
        config()->set('preflight.rules.ENV_DEBUG_TRUE.enabled', true);
        config()->set('preflight.rules.ENV_DEBUG_TRUE.severity', 'critical');
        config()->set('preflight.rules.COMPOSER_DEBUG_PACKAGE_IN_REQUIRE.enabled', true);
        config()->set('preflight.rules.COMPOSER_DEBUG_PACKAGE_IN_REQUIRE.severity', 'high');

        return app(AuditManager::class)->run();
    }
}
