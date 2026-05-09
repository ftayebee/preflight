<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Core\AuditManager;
use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\BaselineManager;
use FahimTayebee\Preflight\Core\RuleRegistry;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Reporters\MarkdownReporter;
use FahimTayebee\Preflight\Scanners\AuthScanner;
use FahimTayebee\Preflight\Scanners\BladeScanner;
use FahimTayebee\Preflight\Scanners\EnvScanner;
use FahimTayebee\Preflight\Scanners\ComposerScanner;
use FahimTayebee\Preflight\Scanners\PolicyScanner;
use FahimTayebee\Preflight\Scanners\RequestScanner;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PackageInfo;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class AuditCommandTest extends TestCase
{
    public function test_command_runs_successfully(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $this->artisan('preflight:audit')
            ->assertExitCode(0);
    }

    public function test_env_scanner_detects_debug_mode(): void
    {
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $results = (new EnvScanner(new PathResolver(), new FileReader()))->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->severity === Severity::Critical
                && $result->title === 'Debug mode is enabled'
                && $result->code === 'ENV_DEBUG_TRUE'
                && $result->confidence === 'high'
        ));
    }

    public function test_score_calculation_uses_configured_severity_weights(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);

        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=\n");

        $report = app(AuditManager::class)->run();

        $this->assertSame(50, $report['score']);
        $this->assertSame(2, $report['counts']['critical']);
    }

    public function test_json_output_is_valid(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(100, $payload['score']);
        $this->assertSame(0, $payload['total_issues']);
        $this->assertIsArray($payload['results']);
        $this->assertArrayHasKey('scanner_timings', $payload);
    }

    public function test_json_output_includes_code_and_confidence(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ENV_DEBUG_TRUE', $payload['results'][0]['code']);
        $this->assertSame('high', $payload['results'][0]['confidence']);
    }

    public function test_markdown_report_generation(): void
    {
        $result = new AuditResult(
            'ENV_DEBUG_TRUE',
            'env',
            Severity::Critical,
            'Debug mode is enabled',
            'APP_DEBUG=true exposes stack traces.',
            '.env',
            1,
            'Set APP_DEBUG=false.',
            'high'
        );

        $markdown = (new MarkdownReporter())->render([
            'results' => [$result],
            'score' => 75,
            'counts' => ['critical' => 1, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0],
            'total_issues' => 1,
            'scanner_timings' => ['env' => 0.012],
        ]);

        $this->assertStringContainsString('# Preflight Security Audit Report', $markdown);
        $this->assertStringContainsString('**Code:** ENV_DEBUG_TRUE', $markdown);
        $this->assertStringContainsString('**Confidence:** high', $markdown);
        $this->assertStringContainsString('- env: 0.012s', $markdown);
    }

    public function test_ignored_issue_codes_filter_results(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.ignored_issue_codes', ['ENV_DEBUG_TRUE']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run();

        $this->assertSame(100, $report['score']);
        $this->assertSame(0, $report['total_issues']);
    }

    public function test_baseline_generation_and_filtering(): void
    {
        $baselinePath = base_path('preflight-baseline-test.json');
        @unlink($baselinePath);

        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.baseline_file', $baselinePath);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run();
        app(BaselineManager::class)->save($report['results']);

        $filtered = app(AuditManager::class)->run(useBaseline: true);

        $this->assertFileExists($baselinePath);
        $this->assertSame(0, $filtered['total_issues']);
        $this->assertSame(1, $filtered['baseline_ignored_issues']);

        @unlink($baselinePath);
    }

    public function test_output_file_creation(): void
    {
        config()->set('preflight.enabled_scanners', []);
        $path = 'storage/app/preflight-test-report.json';
        @unlink(base_path($path));

        $exitCode = Artisan::call('preflight:audit', [
            '--format' => 'json',
            '--output' => $path,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists(base_path($path));
        $this->assertStringContainsString('Report saved to: ' . $path, Artisan::output());

        $payload = json_decode((string) file_get_contents(base_path($path)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(100, $payload['score']);

        @unlink(base_path($path));
    }

    public function test_fail_under_still_returns_failure_when_score_is_too_low(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=\n");

        $exitCode = Artisan::call('preflight:audit', ['--fail-under' => '80']);

        $this->assertSame(1, $exitCode);
    }

    public function test_sarif_output_is_valid_json_with_required_shape(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'sarif']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('2.1.0', $payload['version']);
        $this->assertIsArray($payload['runs']);
        $this->assertSame('Preflight', $payload['runs'][0]['tool']['driver']['name']);
        $this->assertSame('ENV_DEBUG_TRUE', $payload['runs'][0]['tool']['driver']['rules'][0]['id']);
        $this->assertSame('ENV_DEBUG_TRUE', $payload['runs'][0]['results'][0]['ruleId']);
    }

    public function test_invalid_format_returns_failure(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'xml']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid format. Supported formats: console, json, md, sarif', Artisan::output());
    }

    public function test_severity_filter_shows_only_requested_severities_and_above(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\nLOG_LEVEL=debug\n");

        $exitCode = Artisan::call('preflight:audit', [
            '--format' => 'json',
            '--severity' => 'high',
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['filtered']);
        $this->assertSame('high', $payload['severity_filter']);
        $this->assertSame(2, $payload['total_issues']);
        $this->assertSame(1, $payload['visible_issues']);
        $this->assertSame(['critical'], array_column($payload['results'], 'severity'));
        $this->assertSame(67, $payload['score']);
    }

    public function test_fail_on_severity_returns_failure_when_matching_issue_exists(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--fail-on-severity' => 'critical']);

        $this->assertSame(1, $exitCode);
    }

    public function test_fail_on_severity_returns_success_when_no_matching_issue_exists(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=false\nAPP_KEY=base64:test\nLOG_LEVEL=debug\n");

        $exitCode = Artisan::call('preflight:audit', ['--fail-on-severity' => 'high']);

        $this->assertSame(0, $exitCode);
    }

    public function test_sarif_output_file_can_be_created(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");
        $path = 'storage/app/preflight-test.sarif';
        @unlink(base_path($path));

        $exitCode = Artisan::call('preflight:audit', [
            '--format' => 'sarif',
            '--output' => $path,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists(base_path($path));

        $payload = json_decode((string) file_get_contents(base_path($path)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2.1.0', $payload['version']);
        $this->assertIsArray($payload['runs']);

        @unlink(base_path($path));
    }

    public function test_invalid_severity_returns_failure(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:audit', ['--fail-on-severity' => 'urgent']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid severity. Supported severities: critical, high, medium, low, info', Artisan::output());
    }

    public function test_disabled_rule_does_not_appear(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.rules.ENV_DEBUG_TRUE.enabled', false);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run();

        $this->assertSame(0, $report['total_issues']);
        $this->assertSame(100, $report['score']);
    }

    public function test_severity_override_changes_result_severity(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.rules.ENV_DEBUG_TRUE.severity', 'low');
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run();

        $this->assertSame('low', $report['all_results'][0]->severity->value);
        $this->assertSame(97, $report['score']);
    }

    public function test_rules_command_works(): void
    {
        $exitCode = Artisan::call('preflight:rules');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ENV_DEBUG_TRUE', $output);
        $this->assertStringContainsString('POLICY_MISSING_FOR_MODEL', $output);
        $this->assertStringContainsString('BLADE_RAW_OUTPUT', $output);
        $this->assertStringContainsString('AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE', $output);
    }

    public function test_rules_command_json_returns_valid_json(): void
    {
        $exitCode = Artisan::call('preflight:rules', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ENV_DEBUG_TRUE', $payload[0]['code']);
        $this->assertArrayHasKey('configured_severity', $payload[0]);
        $this->assertContains('POLICY_ALWAYS_TRUE', array_column($payload, 'code'));
        $this->assertContains('BLADE_FORM_MISSING_CSRF', array_column($payload, 'code'));
        $this->assertContains('AUTH_API_ROUTE_WEAK_GUARD', array_column($payload, 'code'));
    }

    public function test_rules_command_md_returns_markdown_text(): void
    {
        $exitCode = Artisan::call('preflight:rules', ['--format' => 'md']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('# Preflight Rules', $output);
        $this->assertStringContainsString('## ENV_DEBUG_TRUE', $output);
        $this->assertStringContainsString('## POLICY_METHOD_MISSING', $output);
        $this->assertStringContainsString('## BLADE_UNGUARDED_ADMIN_ACTION', $output);
        $this->assertStringContainsString('## AUTH_ROUTE_MISSING_THROTTLE', $output);
    }

    public function test_policy_scanner_reports_important_model_without_policy(): void
    {
        $modelDir = base_path('app/Models');
        is_dir($modelDir) || mkdir($modelDir, 0755, true);
        $path = $modelDir . '/Payment.php';
        file_put_contents($path, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
}
PHP);

        $results = app(PolicyScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'POLICY_MISSING_FOR_MODEL'
                && $result->metadata['model'] === 'Payment'
        ));

        @unlink($path);
    }

    public function test_policy_scanner_reports_missing_required_method(): void
    {
        $policyDir = base_path('app/Policies');
        $modelDir = base_path('app/Models');
        is_dir($policyDir) || mkdir($policyDir, 0755, true);
        is_dir($modelDir) || mkdir($modelDir, 0755, true);
        file_put_contents($modelDir . '/Order.php', '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Order extends Model {}');
        file_put_contents($policyDir . '/OrderPolicy.php', <<<'PHP'
<?php

namespace App\Policies;

class OrderPolicy
{
    public function viewAny($user): bool { return $user->id > 0; }
}
PHP);

        $results = app(PolicyScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'POLICY_METHOD_MISSING'
                && $result->metadata['policy'] === 'OrderPolicy'
                && $result->metadata['method'] === 'view'
        ));

        @unlink($modelDir . '/Order.php');
        @unlink($policyDir . '/OrderPolicy.php');
    }

    public function test_policy_scanner_reports_policy_method_return_true(): void
    {
        $policyDir = base_path('app/Policies');
        $modelDir = base_path('app/Models');
        is_dir($policyDir) || mkdir($policyDir, 0755, true);
        is_dir($modelDir) || mkdir($modelDir, 0755, true);
        file_put_contents($modelDir . '/Invoice.php', '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Invoice extends Model {}');
        file_put_contents($policyDir . '/InvoicePolicy.php', <<<'PHP'
<?php

namespace App\Policies;

class InvoicePolicy
{
    public function view($user, $invoice): bool
    {
        return true;
    }
}
PHP);

        $results = app(PolicyScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'POLICY_ALWAYS_TRUE'
                && $result->metadata['method'] === 'view'
        ));

        @unlink($modelDir . '/Invoice.php');
        @unlink($policyDir . '/InvoicePolicy.php');
    }

    public function test_policy_scanner_ignored_model_does_not_report(): void
    {
        $modelDir = base_path('app/Models');
        is_dir($modelDir) || mkdir($modelDir, 0755, true);
        $path = $modelDir . '/Team.php';
        file_put_contents($path, '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Team extends Model {}');
        config()->set('preflight.scanners.policies.options.ignored_models', ['Team']);

        $results = app(PolicyScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'POLICY_MISSING_FOR_MODEL'
                && $result->metadata['model'] === 'Team'
        ));

        @unlink($path);
    }

    public function test_blade_scanner_reports_raw_output(): void
    {
        $path = $this->writeBlade('raw.blade.php', '<p>{!! $name !!}</p>');

        $results = app(BladeScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(fn (AuditResult $result): bool => $result->code === 'BLADE_RAW_OUTPUT'));

        @unlink($path);
    }

    public function test_blade_scanner_reports_post_form_without_csrf(): void
    {
        $path = $this->writeBlade('missing-csrf.blade.php', '<form method="POST" action="/users"><button>Save</button></form>');

        $results = app(BladeScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(fn (AuditResult $result): bool => $result->code === 'BLADE_FORM_MISSING_CSRF'));

        @unlink($path);
    }

    public function test_blade_scanner_does_not_report_post_form_with_csrf(): void
    {
        $path = $this->writeBlade('with-csrf.blade.php', '<form method="POST" action="/users">@csrf<button>Save</button></form>');

        $results = app(BladeScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(fn (AuditResult $result): bool => $result->code === 'BLADE_FORM_MISSING_CSRF'));

        @unlink($path);
    }

    public function test_blade_scanner_reports_delete_form_without_method_spoofing(): void
    {
        $path = $this->writeBlade('delete.blade.php', '<form method="POST" action="/users/1/delete">@csrf<button>Delete</button></form>');

        $results = app(BladeScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(fn (AuditResult $result): bool => $result->code === 'BLADE_DELETE_FORM_MISSING_METHOD'));

        @unlink($path);
    }

    public function test_blade_unguarded_admin_action_is_disabled_by_default(): void
    {
        config()->set('preflight.enabled_scanners', ['blade']);
        $path = $this->writeBlade('unguarded.blade.php', '<a href="/admin/users/1/edit">Edit admin</a>');

        $report = app(AuditManager::class)->run();

        $this->assertFalse(collect($report['all_results'])->contains(fn (AuditResult $result): bool => $result->code === 'BLADE_UNGUARDED_ADMIN_ACTION'));

        @unlink($path);
    }

    public function test_auth_scanner_reports_login_route_without_throttle(): void
    {
        app('router')->post('login', ['middleware' => ['web'], 'uses' => 'AuthController@login']);

        $results = app(AuthScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'AUTH_ROUTE_MISSING_THROTTLE'
                && $result->metadata['uri'] === 'login'
        ));
    }

    public function test_auth_scanner_reports_admin_route_with_only_auth(): void
    {
        app('router')->get('admin/users', ['middleware' => ['auth'], 'uses' => 'AdminController@index']);

        $results = app(AuthScanner::class)->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE'
                && $result->metadata['uri'] === 'admin/users'
        ));
    }

    public function test_auth_scanner_does_not_report_admin_route_with_permission(): void
    {
        app('router')->get('admin/roles', ['middleware' => ['auth', 'permission:manage roles'], 'uses' => 'AdminController@roles']);

        $results = app(AuthScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE'
                && $result->metadata['uri'] === 'admin/roles'
        ));
    }

    public function test_auth_scanner_does_not_report_api_route_with_sanctum(): void
    {
        app('router')->get('api/profile', ['middleware' => ['api', 'auth:sanctum'], 'uses' => 'ApiController@profile']);

        $results = app(AuthScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'AUTH_API_ROUTE_WEAK_GUARD'
                && $result->metadata['uri'] === 'api/profile'
        ));
    }

    public function test_new_issue_codes_exist_in_rule_registry(): void
    {
        $registry = app(RuleRegistry::class)->all();

        foreach ([
            'POLICY_MISSING_FOR_MODEL',
            'POLICY_METHOD_MISSING',
            'POLICY_ALWAYS_TRUE',
            'POLICY_MODEL_NOT_FOUND',
            'BLADE_RAW_OUTPUT',
            'BLADE_FORM_MISSING_CSRF',
            'BLADE_DELETE_FORM_MISSING_METHOD',
            'BLADE_UNGUARDED_ADMIN_ACTION',
            'AUTH_ROUTE_MISSING_THROTTLE',
            'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE',
            'AUTH_API_ROUTE_WEAK_GUARD',
        ] as $code) {
            $this->assertArrayHasKey($code, $registry);
            $this->assertArrayHasKey('recommendation', $registry[$code]);
        }
    }

    public function test_request_scanner_detects_missing_authorize(): void
    {
        $directory = base_path('app/Http/Requests');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/StoreDemoRequest.php';
        file_put_contents($path, <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDemoRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required']];
    }
}
PHP);

        $results = (new RequestScanner(new PathResolver(), new FileReader()))->scan();

        $this->assertTrue(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'REQUEST_AUTHORIZE_MISSING'
        ));

        @unlink($path);
    }

    public function test_composer_scanner_detects_debugbar_in_require(): void
    {
        $composerPath = base_path('composer.json');
        $original = file_get_contents($composerPath);

        try {
            file_put_contents($composerPath, json_encode([
                'require' => [
                    'php' => '^8.2',
                    'barryvdh/laravel-debugbar' => '^3.0',
                ],
            ], JSON_PRETTY_PRINT));

            $results = (new ComposerScanner(new PathResolver(), new FileReader()))->scan();

            $this->assertTrue(collect($results)->contains(
                fn (AuditResult $result): bool => $result->code === 'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE'
            ));
        } finally {
            file_put_contents($composerPath, (string) $original);
        }
    }

    public function test_scanner_disabled_via_config_is_skipped(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.scanners.env.enabled', false);
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run();

        $this->assertSame(0, $report['total_issues']);
    }

    public function test_fail_on_severity_respects_severity_override(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.rules.ENV_DEBUG_TRUE.severity', 'low');
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--fail-on-severity' => 'critical']);

        $this->assertSame(0, $exitCode);
    }

    public function test_sarif_respects_severity_override(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.rules.ENV_DEBUG_TRUE.severity', 'medium');
        file_put_contents(base_path('.env'), "APP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'sarif']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('warning', $payload['runs'][0]['results'][0]['level']);
        $this->assertSame('medium', $payload['runs'][0]['results'][0]['properties']['severity']);
    }

    public function test_scanner_option_resolver_returns_defaults(): void
    {
        $resolver = app(ScannerOptionResolver::class);

        $this->assertSame(['fallback'], $resolver->array('routes', 'missing', ['fallback']));
        $this->assertTrue($resolver->bool('env', 'missing', true));
        $this->assertSame('fallback', $resolver->string('env', 'missing', 'fallback'));
    }

    public function test_custom_route_auth_middleware_prevents_false_positive(): void
    {
        config()->set('preflight.scanners.routes.options.auth_middleware_keywords', ['tenant-auth']);

        $router = app('router');
        $router->get('admin/reports', ['middleware' => ['tenant-auth'], 'uses' => 'ReportController@index']);

        $results = app(\FahimTayebee\Preflight\Scanners\RouteScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'ROUTE_SENSITIVE_WITHOUT_AUTH'
        ));
    }

    public function test_custom_composer_risky_package_is_detected(): void
    {
        $composerPath = base_path('composer.json');
        $original = file_get_contents($composerPath);

        try {
            config()->set('preflight.scanners.composer.options.risky_packages_in_require', [
                'vendor/debug-tool' => 'high',
            ]);
            file_put_contents($composerPath, json_encode([
                'require' => ['vendor/debug-tool' => '^1.0'],
            ], JSON_PRETTY_PRINT));

            $results = app(ComposerScanner::class)->scan();

            $this->assertTrue(collect($results)->contains(
                fn (AuditResult $result): bool => $result->code === 'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE'
            ));
        } finally {
            file_put_contents($composerPath, (string) $original);
        }
    }

    public function test_config_check_passes_with_valid_config(): void
    {
        $exitCode = Artisan::call('preflight:config-check');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Severity levels valid', Artisan::output());
    }

    public function test_config_check_warns_with_invalid_rule_severity(): void
    {
        config()->set('preflight.rules.ROUTE_NO_MIDDLEWARE.severity', 'danger');

        $exitCode = Artisan::call('preflight:config-check');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Rule ROUTE_NO_MIDDLEWARE has invalid severity "danger"', Artisan::output());
    }

    public function test_config_check_json_output_is_valid(): void
    {
        $exitCode = Artisan::call('preflight:config-check', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['passed']);
        $this->assertIsArray($payload['errors']);
        $this->assertIsArray($payload['warnings']);
    }

    public function test_composer_scanner_detects_outdated_lock(): void
    {
        $composerPath = base_path('composer.json');
        $lockPath = base_path('composer.lock');
        $originalComposer = file_get_contents($composerPath);
        $originalLock = is_file($lockPath) ? file_get_contents($lockPath) : null;

        try {
            file_put_contents($lockPath, '{}');
            touch($lockPath, time() - 60);
            file_put_contents($composerPath, (string) $originalComposer . "\n");
            touch($composerPath, time());

            $results = app(ComposerScanner::class)->scan();

            $this->assertTrue(collect($results)->contains(
                fn (AuditResult $result): bool => $result->code === 'COMPOSER_LOCK_OUTDATED'
            ));
        } finally {
            file_put_contents($composerPath, (string) $originalComposer);
            if ($originalLock !== null) {
                file_put_contents($lockPath, $originalLock);
            }
        }
    }

    public function test_env_scanner_treats_debug_differently_in_local_and_production(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");
        $local = app(EnvScanner::class)->scan();

        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");
        $production = app(EnvScanner::class)->scan();

        $this->assertSame('low', collect($local)->firstWhere('code', 'ENV_DEBUG_TRUE')->severity->value);
        $this->assertSame('critical', collect($production)->firstWhere('code', 'ENV_DEBUG_TRUE')->severity->value);
    }

    public function test_request_scanner_respects_allow_authorize_true_for(): void
    {
        $directory = base_path('app/Http/Requests');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        @unlink($directory . '/StoreDemoRequest.php');

        $path = $directory . '/PublicContactRequest.php';
        file_put_contents($path, <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required']];
    }
}
PHP);
        config()->set('preflight.scanners.requests.options.allow_authorize_true_for', ['PublicContactRequest']);

        $results = app(RequestScanner::class)->scan();

        $this->assertFalse(collect($results)->contains(
            fn (AuditResult $result): bool => $result->code === 'REQUEST_AUTHORIZE_ALWAYS_TRUE'
        ));

        @unlink($path);
    }

    public function test_doctor_returns_success_on_valid_config(): void
    {
        $exitCode = Artisan::call('preflight:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Preflight Doctor', $output);
        $this->assertStringContainsString('Config loaded', $output);
    }

    public function test_doctor_json_output_is_valid(): void
    {
        $exitCode = Artisan::call('preflight:doctor', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['passed']);
        $this->assertIsArray($payload['errors']);
        $this->assertIsArray($payload['warnings']);
        $this->assertIsArray($payload['checks']);
    }

    public function test_self_test_returns_success(): void
    {
        $exitCode = Artisan::call('preflight:self-test');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Preflight Self Test', $output);
        $this->assertStringContainsString('Reporters rendered', $output);
    }

    public function test_self_test_json_output_is_valid(): void
    {
        $exitCode = Artisan::call('preflight:self-test', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['passed']);
        $this->assertIsArray($payload['errors']);
        $this->assertIsArray($payload['checks']);
    }

    public function test_reports_include_generated_metadata_and_enabled_scanners(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_ENV=local\nAPP_DEBUG=false\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertArrayHasKey('generated_at', $payload['meta']);
        $this->assertArrayHasKey('php_version', $payload['meta']);
        $this->assertSame(['env'], $payload['meta']['enabled_scanners']);
        $this->assertContains('routes', $payload['meta']['skipped_scanners']);
    }

    public function test_package_info_returns_php_version(): void
    {
        $this->assertSame(PHP_VERSION, app(PackageInfo::class)->phpVersion());
    }

    public function test_console_output_includes_final_status(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:audit');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Status: Passed', $output);
        $this->assertStringContainsString('Next: Review medium and low findings before deployment.', $output);
    }

    public function test_invalid_scanner_config_is_caught_by_doctor_and_config_check(): void
    {
        config()->set('preflight.scanners.routes.enabled', 'yes');

        $doctorExit = Artisan::call('preflight:doctor');
        $doctorOutput = Artisan::output();

        $configExit = Artisan::call('preflight:config-check');
        $configOutput = Artisan::output();

        $this->assertSame(1, $doctorExit);
        $this->assertStringContainsString('Scanner routes enabled value must be boolean', $doctorOutput);
        $this->assertSame(1, $configExit);
        $this->assertStringContainsString('Scanner routes enabled value must be boolean', $configOutput);
    }

    public function test_command_docs_do_not_reference_unsupported_options(): void
    {
        $content = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/commands.md');

        preg_match_all('/--[a-z][a-z-]*/', $content, $matches);

        $supported = [
            '--format',
            '--output',
            '--baseline',
            '--use-baseline',
            '--severity',
            '--fail-under',
            '--fail-on-severity',
            '--only',
            '--skip',
        ];

        $this->assertSame([], array_values(array_diff(array_unique($matches[0]), $supported)));
    }

    private function writeBlade(string $name, string $contents): string
    {
        $directory = base_path('resources/views');
        is_dir($directory) || mkdir($directory, 0755, true);
        $path = $directory . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}
