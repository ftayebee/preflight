<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

final class RuleRegistry
{
    /**
     * @return array<string, array<string, string|null>>
     */
    public function all(): array
    {
        return [
            'ENV_DEBUG_TRUE' => $this->rule('env', 'Debug mode is enabled', Severity::Critical, 'Detects APP_DEBUG=true.', 'Set APP_DEBUG=false before production deployment.', [
                'impact' => 'Debug mode can expose stack traces, environment values, SQL, and source paths to users.',
                'fix_example_bad' => "APP_ENV=production\nAPP_DEBUG=true",
                'fix_example_good' => "APP_ENV=production\nAPP_DEBUG=false",
                'false_positive_guidance' => 'If this is a local-only fixture, scan with local environment config or disable ENV_DEBUG_TRUE for that environment.',
            ]),
            'ENV_LOCAL_ENVIRONMENT' => $this->rule('env', 'Application environment is local', Severity::High, 'Detects APP_ENV=local in deployed projects.', 'Use APP_ENV=production for production deployments.'),
            'ENV_LOG_LEVEL_DEBUG' => $this->rule('env', 'Debug log level is enabled', Severity::Medium, 'Detects LOG_LEVEL=debug.', 'Use a less verbose log level in production.'),
            'ENV_SESSION_SECURE_COOKIE_FALSE' => $this->rule('env', 'Session secure cookie is disabled', Severity::Medium, 'Detects SESSION_SECURE_COOKIE=false.', 'Set SESSION_SECURE_COOKIE=true when serving over HTTPS.'),
            'ENV_APP_KEY_MISSING' => $this->rule('env', 'Application key is missing', Severity::Critical, 'Detects missing APP_KEY.', 'Generate an app key with php artisan key:generate.'),
            'ENV_DB_PASSWORD_EMPTY' => $this->rule('env', 'Database password is empty', Severity::Medium, 'Detects empty DB_PASSWORD.', 'Use a strong database password and least-privileged database user.'),

            'ROUTE_NO_MIDDLEWARE' => $this->rule('routes', 'Route has no middleware', Severity::High, 'Detects routes without middleware outside the public allowlist.', 'Attach appropriate middleware or add intentional public routes to the allowlist.'),
            'ROUTE_SENSITIVE_WITHOUT_AUTH' => $this->rule('routes', 'Sensitive route lacks auth middleware', Severity::Critical, 'Detects sensitive route patterns without auth or authorization middleware.', 'Protect sensitive routes with auth and authorization middleware.', [
                'impact' => 'Sensitive routes can expose admin, account, settings, payment, or user-management actions to unauthenticated visitors.',
                'fix_example_bad' => "Route::get('/admin/users', [AdminUserController::class, 'index']);",
                'fix_example_good' => "Route::get('/admin/users', [AdminUserController::class, 'index'])\n    ->middleware(['auth', 'can:viewAny,App\\\\Models\\\\User']);",
                'false_positive_guidance' => 'Add the route to ignored_routes, public_route_allowlist, or disable the rule in config/preflight.php.',
            ]),
            'ROUTE_DANGEROUS_URI' => $this->rule('routes', 'Dangerous route URI detected', Severity::Critical, 'Detects route URIs that expose destructive database operations.', 'Remove deployment-only routes or guard them behind strict authorization.'),
            'ROUTE_DESTRUCTIVE_GET' => $this->rule('routes', 'GET route appears destructive', Severity::High, 'Detects GET routes with destructive URI or action names.', 'Use non-GET methods with CSRF protection for state-changing actions.', [
                'impact' => 'GET requests may be triggered by crawlers, previews, browser prefetching, or accidental clicks, causing unintended state changes.',
                'fix_example_bad' => "Route::get('/users/{user}/delete', [UserController::class, 'destroy']);",
                'fix_example_good' => "Route::delete('/users/{user}', [UserController::class, 'destroy'])\n    ->middleware(['auth', 'can:delete,user']);",
                'false_positive_guidance' => 'Add the route to ignored_routes, public_route_allowlist, or disable the rule in config/preflight.php.',
            ]),
            'ROUTE_CLOSURE_ACTION' => $this->rule('routes', 'Route uses a Closure action', Severity::Low, 'Detects Closure route actions.', 'Prefer controller actions for production routes.'),

            'CONTROLLER_MISSING_AUTHORIZATION' => $this->rule('controllers', 'Controller has no visible authorization checks', Severity::Medium, 'Detects controllers without visible authorization patterns.', 'Add route middleware, controller middleware, policies, or gate checks where needed.', [
                'impact' => 'Controller actions can become sensitive over time; missing visible authorization makes access control harder to review.',
                'fix_example_bad' => "public function update(Request \$request, Project \$project)\n{\n    \$project->update(\$request->validated());\n}",
                'fix_example_good' => "public function update(UpdateProjectRequest \$request, Project \$project)\n{\n    \$this->authorize('update', \$project);\n\n    \$project->update(\$request->validated());\n}",
                'false_positive_guidance' => 'Add project-specific authorization keywords under scanners.controllers.options.authorization_keywords.',
            ]),
            'CONTROLLER_WRITE_WITHOUT_AUTHORIZATION' => $this->rule('controllers', 'Write controller methods lack authorization checks', Severity::High, 'Detects store, update, delete, or destroy methods without visible authorization.', 'Authorize state-changing actions with policies, gates, middleware, or permission checks.'),
            'CONTROLLER_PLAINTEXT_PASSWORD' => $this->rule('controllers', 'Plain password field referenced', Severity::Critical, 'Detects visible_password or plain_password references.', 'Never store or expose plain-text passwords.'),
            'CONTROLLER_DESTRUCTIVE_DB_STATEMENT' => $this->rule('controllers', 'Destructive raw database statement detected', Severity::Critical, 'Detects DROP or TRUNCATE through DB::statement().', 'Remove destructive database statements from HTTP controllers.'),
            'CONTROLLER_REQUEST_ALL_WRITE_CONTEXT' => $this->rule('controllers', 'Direct request all usage in write context', Severity::Medium, 'Detects request()->all() or $request->all() in write contexts.', 'Use validated input from Form Requests or $request->validated().'),

            'MODEL_GUARDED_EMPTY' => $this->rule('models', 'Model allows all attributes for mass assignment', Severity::High, 'Detects protected $guarded = [].', 'Prefer explicit $fillable attributes or a non-empty $guarded list.', [
                'impact' => 'Every current and future column becomes mass assignable, which can expose privilege or ownership fields to request input.',
                'fix_example_bad' => "class User extends Model\n{\n    protected \$guarded = [];\n}",
                'fix_example_good' => "class User extends Model\n{\n    protected \$fillable = ['name', 'email'];\n}",
                'false_positive_guidance' => 'Disable MODEL_GUARDED_EMPTY for trusted internal models or add model paths to ignored_paths.',
            ]),
            'MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING' => $this->rule('models', 'Password is fillable without visible hashing context', Severity::Critical, 'Detects password in $fillable without obvious hashing.', 'Ensure password assignment is always hashed before persistence.'),
            'MODEL_PLAINTEXT_PASSWORD' => $this->rule('models', 'Plain password field detected on model', Severity::Critical, 'Detects visible_password or plain_password fields.', 'Remove plain-text password fields and store only hashed passwords.'),
            'MODEL_MISSING_HAS_FACTORY' => $this->rule('models', 'Model does not use HasFactory', Severity::Info, 'Detects models without HasFactory.', 'Add HasFactory if this model needs factory support.'),
            'MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION' => $this->rule('models', 'Model has no mass-assignment declaration', Severity::Low, 'Detects models without $fillable or $guarded.', 'Declare either $fillable or $guarded.'),

            'MIGRATION_VISIBLE_PASSWORD' => $this->rule('migrations', 'Plain password column detected', Severity::Critical, 'Detects visible_password or plain_password columns.', 'Never create columns intended to store plain-text passwords.'),
            'MIGRATION_USERS_EMAIL_NULLABLE' => $this->rule('migrations', 'Users email column is nullable', Severity::Medium, 'Detects nullable email in users migrations.', 'Require email where authentication or account recovery depends on it.'),
            'MIGRATION_PASSWORD_NULLABLE' => $this->rule('migrations', 'Password column is nullable', Severity::High, 'Detects nullable password columns.', 'Avoid nullable password columns unless intentionally designed.'),
            'MIGRATION_IS_ADMIN_COLUMN' => $this->rule('migrations', 'is_admin boolean column detected', Severity::Medium, 'Detects is_admin boolean columns.', 'Consider policies or a dedicated roles and permissions system.'),
            'MIGRATION_ROLE_COLUMN' => $this->rule('migrations', 'role string column detected', Severity::Low, 'Detects role string columns.', 'Use a proper roles and permissions design when authorization needs grow.'),
            'MIGRATION_USERS_REMEMBER_TOKEN_MISSING' => $this->rule('migrations', 'Users table is missing remember token', Severity::Low, 'Detects users migrations without rememberToken().', 'Add rememberToken() if the application supports remember-me sessions.'),

            'REQUEST_AUTHORIZE_MISSING' => $this->rule('requests', 'FormRequest authorize method is missing', Severity::Medium, 'Detects FormRequest classes without authorize().', 'Add authorize() and enforce permission checks where needed.'),
            'REQUEST_AUTHORIZE_ALWAYS_TRUE' => $this->rule('requests', 'FormRequest authorize always returns true', Severity::Low, 'Detects authorize() methods returning true.', 'Use permission checks for sensitive requests.', [
                'impact' => 'Validation does not decide who may perform an action; sensitive requests still need authorization checks.',
                'fix_example_bad' => "public function authorize(): bool\n{\n    return true;\n}",
                'fix_example_good' => "public function authorize(): bool\n{\n    return \$this->user()?->can('update', \$this->route('project')) ?? false;\n}",
                'false_positive_guidance' => 'Add public request classes to scanners.requests.options.allow_authorize_true_for.',
            ]),
            'REQUEST_RULES_EMPTY' => $this->rule('requests', 'FormRequest rules are empty', Severity::Medium, 'Detects empty rules() arrays.', 'Add validation rules for incoming request data.'),
            'REQUEST_RULES_MISSING' => $this->rule('requests', 'FormRequest rules method is missing', Severity::High, 'Detects FormRequest classes without rules().', 'Add a rules() method with validation requirements.'),
            'REQUEST_NULLABLE_PASSWORD' => $this->rule('requests', 'Nullable password validation rule detected', Severity::Medium, 'Detects nullable password validation rules.', 'Avoid nullable password rules unless optional password changes are intentional.'),
            'REQUEST_SOMETIMES_RULE' => $this->rule('requests', 'Sometimes validation rule detected', Severity::Low, 'Detects use of sometimes validation.', 'Review optional validation paths for sensitive fields.'),

            'COMPOSER_LOCK_MISSING' => $this->rule('composer', 'composer.lock is missing', Severity::Medium, 'Detects missing composer.lock.', 'Commit composer.lock for applications to keep dependency installs reproducible.'),
            'COMPOSER_LOCK_OUTDATED' => $this->rule('composer', 'composer.lock may be outdated', Severity::Medium, 'Detects composer.json modified after composer.lock.', 'Run composer update or composer install and commit the updated lock file.'),
            'COMPOSER_JSON_INVALID' => $this->rule('composer', 'composer.json is invalid', Severity::High, 'Detects invalid composer.json syntax.', 'Fix composer.json so Composer and deployment tooling can parse it.'),
            'COMPOSER_MINIMUM_STABILITY_DEV' => $this->rule('composer', 'minimum-stability is dev', Severity::Medium, 'Detects minimum-stability=dev.', 'Avoid dev stability in production applications.'),
            'COMPOSER_PREFER_STABLE_MISSING' => $this->rule('composer', 'prefer-stable missing with dev stability', Severity::Low, 'Detects missing prefer-stable when minimum-stability is dev.', 'Set prefer-stable=true if dev stability is required.'),
            'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE' => $this->rule('composer', 'Debug package is installed in production require', Severity::High, 'Detects risky debug packages in require.', 'Move debug packages to require-dev or remove them.', [
                'impact' => 'Debug packages can expose application internals or add unnecessary production attack surface.',
                'fix_example_bad' => "{\"require\":{\"barryvdh/laravel-debugbar\":\"^3.0\"}}",
                'fix_example_good' => "{\"require-dev\":{\"barryvdh/laravel-debugbar\":\"^3.0\"}}",
                'false_positive_guidance' => 'Tune scanners.composer.options.risky_packages_in_require for project-specific debug packages.',
            ]),
            'COMPOSER_UNSAFE_SCRIPT' => $this->rule('composer', 'Unsafe Composer script detected', Severity::High, 'Detects risky shell commands in Composer scripts.', 'Remove destructive Composer scripts or move them to guarded deployment tooling.'),

            'POLICY_MISSING_FOR_MODEL' => $this->rule('policies', 'Important model is missing a policy', Severity::Medium, 'Detects important app models without a convention-based policy file.', 'Create a policy with php artisan make:policy ModelPolicy --model=Model and add action-specific checks.'),
            'POLICY_METHOD_MISSING' => $this->rule('policies', 'Policy is missing a required method', Severity::Medium, 'Detects policy files missing configured methods such as view, create, update, or delete.', 'Add the missing policy method and return an explicit user, role, ownership, or permission decision.'),
            'POLICY_ALWAYS_TRUE' => $this->rule('policies', 'Policy method always returns true', Severity::High, 'Detects policy methods whose entire body directly returns true.', 'Replace return true with a role, permission, ownership, or Gate check; keep before() broad access intentional and documented.'),
            'POLICY_MODEL_NOT_FOUND' => $this->rule('policies', 'Policy model was not found', Severity::Low, 'Detects convention-based policy files without a matching app model.', 'Rename the policy, create the matching model, or remove stale policy files.'),

            'BLADE_RAW_OUTPUT' => $this->rule('blade', 'Raw Blade output detected', Severity::High, 'Detects {!! !!} raw Blade echo statements.', 'Use escaped Blade output {{ $variable }} unless the value is intentionally sanitized HTML.'),
            'BLADE_FORM_MISSING_CSRF' => $this->rule('blade', 'Blade form is missing CSRF protection', Severity::High, 'Detects state-changing Blade forms without @csrf or csrf_field().', 'Add @csrf inside every POST, PUT, PATCH, or DELETE form.'),
            'BLADE_DELETE_FORM_MISSING_METHOD' => $this->rule('blade', 'Delete form is missing method spoofing', Severity::Medium, 'Detects destructive-looking forms without DELETE method spoofing.', "Add @method('DELETE') or method_field('DELETE') to forms that delete, destroy, or remove records."),
            'BLADE_UNGUARDED_ADMIN_ACTION' => $this->rule('blade', 'Admin action may be missing a Blade authorization guard', Severity::Low, 'Detects sensitive-looking Blade actions without nearby authorization directives.', 'Wrap sensitive links, buttons, and forms with @can, @role, @permission, @auth, or a project-specific Gate check.'),

            'AUTH_ROUTE_MISSING_THROTTLE' => $this->rule('auth', 'Auth route is missing throttle middleware', Severity::Medium, 'Detects login, registration, and password routes without throttle or rate limiting middleware.', 'Add throttle middleware or a named Laravel rate limiter to authentication routes.'),
            'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE' => $this->rule('auth', 'Admin route uses weak middleware', Severity::High, 'Detects sensitive admin-like routes that use auth without role, permission, ability, or can middleware.', 'Add can:, role:, permission:, ability:, or abilities: middleware to sensitive routes.'),
            'AUTH_API_ROUTE_WEAK_GUARD' => $this->rule('auth', 'API route uses a weak token guard', Severity::Medium, 'Detects API routes using generic auth middleware without an explicit token guard.', 'Use auth:sanctum, auth:api, auth:passport, token middleware, or ability middleware for API routes.'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function get(string $code): array
    {
        return $this->all()[$code] ?? [];
    }

    /**
     * @param array<string, string|null> $metadata
     * @return array<string, string|null>
     */
    private function rule(string $scanner, string $title, Severity $severity, string $description, string $recommendation, array $metadata = []): array
    {
        $rule = [
            'scanner' => $scanner,
            'title' => $title,
            'default_severity' => $severity->value,
            'description' => $description,
            'recommendation' => $recommendation,
            'impact' => $metadata['impact'] ?? null,
            'fix_example_bad' => $metadata['fix_example_bad'] ?? null,
            'fix_example_good' => $metadata['fix_example_good'] ?? null,
            'false_positive_guidance' => $metadata['false_positive_guidance'] ?? $this->defaultFalsePositiveGuidance($scanner),
        ];

        $rule['docs_url'] = $metadata['docs_url'] ?? 'https://github.com/ftayebee/preflight/blob/main/docs/rules.md#' . strtolower($this->codeFromTitle($title));

        return $rule;
    }

    private function defaultFalsePositiveGuidance(string $scanner): string
    {
        return match ($scanner) {
            'routes', 'auth' => 'Add the route to ignored_routes, public_route_allowlist, or disable the rule in config/preflight.php.',
            'controllers' => 'Add project-specific authorization keywords under scanners.controllers.options.authorization_keywords.',
            'blade' => 'Add view paths to scanners.blade.options.ignored_view_paths or disable noisy Blade rules in config/preflight.php.',
            'policies' => 'Tune scanners.policies options such as important_model_names, ignored_models, or required_policy_methods.',
            default => 'Disable the rule, tune scanner options, or add the finding to a reviewed baseline when it is intentional.',
        };
    }

    private function codeFromTitle(string $title): string
    {
        foreach ($this->allCodeTitleMap() as $code => $knownTitle) {
            if ($knownTitle === $title) {
                return $code;
            }
        }

        return str_replace('-', '_', strtolower($title));
    }

    /**
     * @return array<string, string>
     */
    private function allCodeTitleMap(): array
    {
        return [
            'ENV_DEBUG_TRUE' => 'Debug mode is enabled',
            'ENV_LOCAL_ENVIRONMENT' => 'Application environment is local',
            'ENV_LOG_LEVEL_DEBUG' => 'Debug log level is enabled',
            'ENV_SESSION_SECURE_COOKIE_FALSE' => 'Session secure cookie is disabled',
            'ENV_APP_KEY_MISSING' => 'Application key is missing',
            'ENV_DB_PASSWORD_EMPTY' => 'Database password is empty',
            'ROUTE_NO_MIDDLEWARE' => 'Route has no middleware',
            'ROUTE_SENSITIVE_WITHOUT_AUTH' => 'Sensitive route lacks auth middleware',
            'ROUTE_DANGEROUS_URI' => 'Dangerous route URI detected',
            'ROUTE_DESTRUCTIVE_GET' => 'GET route appears destructive',
            'ROUTE_CLOSURE_ACTION' => 'Route uses a Closure action',
            'CONTROLLER_MISSING_AUTHORIZATION' => 'Controller has no visible authorization checks',
            'CONTROLLER_WRITE_WITHOUT_AUTHORIZATION' => 'Write controller methods lack authorization checks',
            'CONTROLLER_PLAINTEXT_PASSWORD' => 'Plain password field referenced',
            'CONTROLLER_DESTRUCTIVE_DB_STATEMENT' => 'Destructive raw database statement detected',
            'CONTROLLER_REQUEST_ALL_WRITE_CONTEXT' => 'Direct request all usage in write context',
            'MODEL_GUARDED_EMPTY' => 'Model allows all attributes for mass assignment',
            'MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING' => 'Password is fillable without visible hashing context',
            'MODEL_PLAINTEXT_PASSWORD' => 'Plain password field detected on model',
            'MODEL_MISSING_HAS_FACTORY' => 'Model does not use HasFactory',
            'MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION' => 'Model has no mass-assignment declaration',
            'MIGRATION_VISIBLE_PASSWORD' => 'Plain password column detected',
            'MIGRATION_USERS_EMAIL_NULLABLE' => 'Users email column is nullable',
            'MIGRATION_PASSWORD_NULLABLE' => 'Password column is nullable',
            'MIGRATION_IS_ADMIN_COLUMN' => 'is_admin boolean column detected',
            'MIGRATION_ROLE_COLUMN' => 'role string column detected',
            'MIGRATION_USERS_REMEMBER_TOKEN_MISSING' => 'Users table is missing remember token',
            'REQUEST_AUTHORIZE_MISSING' => 'FormRequest authorize method is missing',
            'REQUEST_AUTHORIZE_ALWAYS_TRUE' => 'FormRequest authorize always returns true',
            'REQUEST_RULES_EMPTY' => 'FormRequest rules are empty',
            'REQUEST_RULES_MISSING' => 'FormRequest rules method is missing',
            'REQUEST_NULLABLE_PASSWORD' => 'Nullable password validation rule detected',
            'REQUEST_SOMETIMES_RULE' => 'Sometimes validation rule detected',
            'COMPOSER_LOCK_MISSING' => 'composer.lock is missing',
            'COMPOSER_LOCK_OUTDATED' => 'composer.lock may be outdated',
            'COMPOSER_JSON_INVALID' => 'composer.json is invalid',
            'COMPOSER_MINIMUM_STABILITY_DEV' => 'minimum-stability is dev',
            'COMPOSER_PREFER_STABLE_MISSING' => 'prefer-stable missing with dev stability',
            'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE' => 'Debug package is installed in production require',
            'COMPOSER_UNSAFE_SCRIPT' => 'Unsafe Composer script detected',
            'POLICY_MISSING_FOR_MODEL' => 'Important model is missing a policy',
            'POLICY_METHOD_MISSING' => 'Policy is missing a required method',
            'POLICY_ALWAYS_TRUE' => 'Policy method always returns true',
            'POLICY_MODEL_NOT_FOUND' => 'Policy model was not found',
            'BLADE_RAW_OUTPUT' => 'Raw Blade output detected',
            'BLADE_FORM_MISSING_CSRF' => 'Blade form is missing CSRF protection',
            'BLADE_DELETE_FORM_MISSING_METHOD' => 'Delete form is missing method spoofing',
            'BLADE_UNGUARDED_ADMIN_ACTION' => 'Admin action may be missing a Blade authorization guard',
            'AUTH_ROUTE_MISSING_THROTTLE' => 'Auth route is missing throttle middleware',
            'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE' => 'Admin route uses weak middleware',
            'AUTH_API_ROUTE_WEAK_GUARD' => 'API route uses a weak token guard',
        ];
    }
}
