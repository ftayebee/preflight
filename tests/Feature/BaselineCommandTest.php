<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\BaselineManager;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class BaselineCommandTest extends TestCase
{
    private string $baselinePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baselinePath = base_path('storage/app/preflight-baseline-test.json');
        @unlink($this->baselinePath);
        config()->set('preflight.baseline.file', $this->baselinePath);
        config()->set('preflight.baseline_file', $this->baselinePath);
        config()->set('preflight.enabled_scanners', ['env']);
    }

    protected function tearDown(): void
    {
        @unlink($this->baselinePath);

        parent::tearDown();
    }

    public function test_baseline_show_when_no_baseline_exists(): void
    {
        $exitCode = Artisan::call('preflight:baseline', ['action' => 'show']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No baseline file found.', $output);
        $this->assertStringContainsString('Run: php artisan preflight:baseline generate', $output);
    }

    public function test_baseline_generate_creates_baseline_file(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'generate']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->baselinePath);
        $this->assertStringContainsString('Baseline generated:', Artisan::output());

        $payload = json_decode((string) file_get_contents($this->baselinePath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['version']);
        $this->assertArrayHasKey('entries', $payload);
        $this->assertSame('ENV_DEBUG_TRUE', $payload['entries'][0]['code']);
    }

    public function test_existing_audit_baseline_option_still_works(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--baseline' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->baselinePath);
        $this->assertStringContainsString('Issues stored:', Artisan::output());
    }

    public function test_baseline_show_displays_entries(): void
    {
        $this->seedBaseline();

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'show']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Entries: 1', $output);
        $this->assertStringContainsString('ENV_DEBUG_TRUE', $output);
        $this->assertStringContainsString('Fingerprint:', $output);
    }

    public function test_baseline_show_json_returns_valid_json(): void
    {
        $this->seedBaseline();

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'show', '--format' => 'json']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['exists']);
        $this->assertSame(1, $payload['count']);
        $this->assertSame('ENV_DEBUG_TRUE', $payload['entries'][0]['code']);
    }

    public function test_baseline_prune_removes_resolved_entries(): void
    {
        $this->seedBaseline();
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'prune']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Removed resolved entries: 1', Artisan::output());
        $this->assertSame([], app(BaselineManager::class)->entries());
    }

    public function test_baseline_clear_force_deletes_baseline_file(): void
    {
        $this->seedBaseline();

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'clear', '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileDoesNotExist($this->baselinePath);
        $this->assertStringContainsString('Baseline cleared:', Artisan::output());
    }

    public function test_invalid_baseline_action_returns_failure(): void
    {
        $exitCode = Artisan::call('preflight:baseline', ['action' => 'unknown']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid action. Supported actions: generate, show, prune, clear', Artisan::output());
    }

    public function test_invalid_baseline_format_returns_failure(): void
    {
        $exitCode = Artisan::call('preflight:baseline', ['action' => 'show', '--format' => 'xml']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid format. Supported formats: console, json', Artisan::output());
    }

    public function test_fail_on_new_returns_failure_when_new_issues_exist(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'show', '--fail-on-new' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('New issues found: 1', Artisan::output());
    }

    public function test_fail_on_resolved_returns_failure_when_prune_removes_entries(): void
    {
        $this->seedBaseline();
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:baseline', ['action' => 'prune', '--fail-on-resolved' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Resolved baseline entries found: 1', Artisan::output());
    }

    public function test_audit_use_baseline_includes_json_baseline_metadata(): void
    {
        $this->seedBaseline();
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=\n");

        $exitCode = Artisan::call('preflight:audit', ['--format' => 'json', '--use-baseline' => true]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['baseline']['used']);
        $this->assertSame(1, $payload['baseline']['ignored']);
        $this->assertSame(1, $payload['baseline']['new']);
        $this->assertSame(0, $payload['baseline']['resolved']);
    }

    public function test_audit_use_baseline_console_shows_counts(): void
    {
        $this->seedBaseline();
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=\n");

        $exitCode = Artisan::call('preflight:audit', ['--use-baseline' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Baseline ignored issues: 1', $output);
        $this->assertStringContainsString('New issues after baseline: 1', $output);
        $this->assertStringContainsString('Resolved baseline entries: 0', $output);
    }

    public function test_baseline_manager_diff_returns_new_baselined_and_resolved_counts(): void
    {
        $manager = app(BaselineManager::class);
        $baselineResult = new AuditResult('ENV_DEBUG_TRUE', 'env', Severity::Critical, 'Debug mode is enabled', 'APP_DEBUG=true.', '.env');
        $newResult = new AuditResult('ENV_APP_KEY_MISSING', 'env', Severity::Critical, 'Application key is missing', 'APP_KEY missing.', '.env');

        $manager->saveEntries($manager->generateFromResults([$baselineResult, new AuditResult('ROUTE_NO_MIDDLEWARE', 'routes', Severity::High, 'Route has no middleware', 'Old.', 'routes/web.php')]));

        $diff = $manager->diff([$baselineResult, $newResult]);

        $this->assertSame(1, $diff['counts']['baselined']);
        $this->assertSame(1, $diff['counts']['new']);
        $this->assertSame(1, $diff['counts']['resolved']);
    }

    public function test_old_baseline_format_loads_gracefully(): void
    {
        $fingerprint = 'abc123';
        is_dir(dirname($this->baselinePath)) || mkdir(dirname($this->baselinePath), 0755, true);
        file_put_contents($this->baselinePath, json_encode([
            'generated_at' => date(DATE_ATOM),
            'count' => 1,
            'fingerprints' => [$fingerprint],
        ], JSON_THROW_ON_ERROR));

        $entries = app(BaselineManager::class)->entries();

        $this->assertSame($fingerprint, $entries[0]['fingerprint']);
        $this->assertNull($entries[0]['code']);
    }

    public function test_missing_baseline_file_is_handled_gracefully(): void
    {
        $manager = app(BaselineManager::class);

        $this->assertFalse($manager->exists());
        $this->assertSame([], $manager->entries());
        $this->assertSame(0, $manager->diff([])['counts']['total_baseline']);
    }

    private function seedBaseline(): void
    {
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");
        Artisan::call('preflight:baseline', ['action' => 'generate']);
    }
}
