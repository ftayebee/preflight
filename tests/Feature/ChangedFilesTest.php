<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Core\AuditManager;
use FahimTayebee\Preflight\Core\ScannerContext;
use FahimTayebee\Preflight\Support\GitChangedFilesResolver;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class ChangedFilesTest extends TestCase
{
    /** @var array<int, string> */
    private array $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_git_changed_files_resolver_handles_failure_safely(): void
    {
        config()->set('preflight.base_path', base_path('missing-git-repo'));
        $resolver = new GitChangedFilesResolver(new PathResolver());

        $files = $resolver->changedFiles('missing/ref');

        $this->assertSame([], $files);
        $this->assertNotNull($resolver->lastError());
    }

    public function test_git_changed_files_resolver_normalizes_paths(): void
    {
        $resolver = new GitChangedFilesResolver(new PathResolver());
        $method = new \ReflectionMethod($resolver, 'normalizePath');
        $method->setAccessible(true);

        $this->assertSame('app/Http/Controllers/UserController.php', $method->invoke($resolver, '.\\app\\Http\\Controllers\\UserController.php'));
    }

    public function test_scanner_context_should_scan_file_with_windows_and_unix_separators(): void
    {
        $context = new ScannerContext(true, ['app/Models/User.php'], 'origin/main', 'C:\\project');

        $this->assertTrue($context->shouldScanFile('C:\\project\\app\\Models\\User.php'));
        $this->assertTrue($context->shouldScanFile('app/Models/User.php'));
        $this->assertFalse($context->shouldScanFile('C:\\project\\app\\Models\\Post.php'));
    }

    public function test_scanner_context_relevant_files_filters_extensions(): void
    {
        $context = new ScannerContext(true, ['app/Models/User.php', 'README.md'], 'origin/main', base_path());

        $this->assertSame(['app/Models/User.php'], $context->relevantFilesFor(['php']));
    }

    public function test_scanner_context_non_changed_mode_scans_all(): void
    {
        $context = new ScannerContext(false, [], null, base_path());

        $this->assertTrue($context->shouldScanFile('anything.php'));
        $this->assertSame([], $context->relevantFilesFor(['php']));
    }

    public function test_changed_git_failure_falls_back_to_full_scan_when_configured(): void
    {
        $this->fakeChangedFiles([], 'bad base ref');
        config()->set('preflight.changed_files.fallback_to_full_scan', true);
        config()->set('preflight.enabled_scanners', ['env']);
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $exitCode = Artisan::call('preflight:audit', ['--changed' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Changed mode warning: Could not resolve changed files. Falling back to full scan.', $output);
        $this->assertStringContainsString('ENV_DEBUG_TRUE', $output);
    }

    public function test_changed_git_failure_returns_error_when_fallback_disabled(): void
    {
        $this->fakeChangedFiles([], 'bad base ref');
        config()->set('preflight.changed_files.fallback_to_full_scan', false);

        $exitCode = Artisan::call('preflight:audit', ['--changed' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Changed mode error: bad base ref', Artisan::output());
    }

    public function test_changed_json_output_includes_metadata(): void
    {
        $this->fakeChangedFiles(['app/Http/Controllers/RiskyController.php']);
        config()->set('preflight.enabled_scanners', ['controllers']);
        $this->writeController('RiskyController.php', 'class RiskyController { public function update($request, $model) { $model->update($request->all()); } }');

        $exitCode = Artisan::call('preflight:audit', ['--changed' => true, '--format' => 'json', '--base-ref' => 'origin/main']);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['changed_files']['enabled']);
        $this->assertSame('origin/main', $payload['changed_files']['base_ref']);
        $this->assertSame(1, $payload['changed_files']['count']);
    }

    public function test_changed_console_output_shows_changed_files_mode(): void
    {
        $this->fakeChangedFiles([]);
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:audit', ['--changed' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Changed Files Mode:', Artisan::output());
    }

    public function test_changed_mode_respects_only(): void
    {
        $this->fakeChangedFiles([]);
        config()->set('preflight.enabled_scanners', ['env', 'controllers']);
        config()->set('preflight.changed_files.include_project_scanners', false);
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run(['env'], [], false, null, 'default', new ScannerContext(true, [], 'origin/main', base_path(), ['env'], []), [
            'enabled' => true,
            'base_ref' => 'origin/main',
            'count' => 0,
            'files' => [],
            'error' => null,
            'fallback_used' => false,
        ]);

        $this->assertSame(['env'], $report['meta']['enabled_scanners']);
    }

    public function test_changed_mode_respects_skip(): void
    {
        config()->set('preflight.enabled_scanners', ['controllers', 'models']);

        $report = app(AuditManager::class)->run([], ['models'], false, null, 'default', new ScannerContext(true, ['app/Models/User.php'], 'origin/main', base_path(), [], ['models']));

        $this->assertNotContains('models', $report['meta']['enabled_scanners']);
    }

    public function test_changed_mode_filters_controller_scanner_to_changed_files(): void
    {
        config()->set('preflight.enabled_scanners', ['controllers']);
        $this->writeController('ChangedController.php', 'class ChangedController { public function update($request, $model) { $model->update($request->all()); } }');
        $this->writeController('UnchangedController.php', 'class UnchangedController { public function update($request, $model) { $model->update($request->all()); } }');

        $report = app(AuditManager::class)->run([], [], false, null, 'default', new ScannerContext(true, ['app/Http/Controllers/ChangedController.php'], 'origin/main', base_path()));
        $files = array_values(array_filter(array_map(fn ($result): ?string => $result->file, $report['all_results'])));

        $this->assertContains('app/Http/Controllers/ChangedController.php', $files);
        $this->assertNotContains('app/Http/Controllers/UnchangedController.php', $files);
    }

    public function test_changed_mode_does_not_scan_unchanged_model_request_or_migration_files(): void
    {
        config()->set('preflight.enabled_scanners', ['models', 'requests', 'migrations']);
        $this->writeFile('app/Models/User.php', '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class User extends Model { protected $guarded = []; }');
        $this->writeFile('app/Http/Requests/UpdateUserRequest.php', '<?php namespace App\Http\Requests; use Illuminate\Foundation\Http\FormRequest; class UpdateUserRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ["name" => ["required"]]; } }');
        $this->writeFile('database/migrations/2024_01_01_000000_create_users_table.php', "<?php Schema::create('users', function (\$table) { \$table->string('password')->nullable(); });");

        $report = app(AuditManager::class)->run([], [], false, null, 'default', new ScannerContext(true, ['README.md'], 'origin/main', base_path()));

        $this->assertSame([], $report['all_results']);
    }

    public function test_project_scanners_run_when_include_project_scanners_true(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.changed_files.include_project_scanners', true);
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $report = app(AuditManager::class)->run([], [], false, null, 'default', new ScannerContext(true, [], 'origin/main', base_path()));

        $this->assertSame(['env'], $report['meta']['enabled_scanners']);
        $this->assertNotEmpty($report['all_results']);
    }

    public function test_project_scanners_skip_when_include_project_scanners_false_unless_only_includes_them(): void
    {
        config()->set('preflight.enabled_scanners', ['env']);
        config()->set('preflight.changed_files.include_project_scanners', false);
        file_put_contents(base_path('.env'), "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=base64:test\n");

        $skipped = app(AuditManager::class)->run([], [], false, null, 'default', new ScannerContext(true, [], 'origin/main', base_path()));
        $explicit = app(AuditManager::class)->run(['env'], [], false, null, 'default', new ScannerContext(true, [], 'origin/main', base_path(), ['env'], []));

        $this->assertSame([], $skipped['meta']['enabled_scanners']);
        $this->assertSame(['env'], $explicit['meta']['enabled_scanners']);
    }

    /**
     * @param array<int, string> $files
     */
    private function fakeChangedFiles(array $files, ?string $error = null): void
    {
        app()->instance(GitChangedFilesResolver::class, new class($files, $error) extends GitChangedFilesResolver {
            public function __construct(private readonly array $files, private readonly ?string $error)
            {
            }

            public function changedFiles(string $baseRef = 'origin/main'): array
            {
                return $this->files;
            }

            public function isGitAvailable(): bool
            {
                return $this->error === null;
            }

            public function lastError(): ?string
            {
                return $this->error;
            }
        });
    }

    private function writeController(string $name, string $classBody): void
    {
        $this->writeFile('app/Http/Controllers/' . $name, "<?php\nnamespace App\\Http\\Controllers;\n" . $classBody);
    }

    private function writeFile(string $path, string $contents): void
    {
        $fullPath = base_path($path);
        $directory = dirname($fullPath);
        is_dir($directory) || mkdir($directory, 0755, true);
        file_put_contents($fullPath, $contents);
        $this->writtenFiles[] = $fullPath;
    }
}
