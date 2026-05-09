# Preflight Rules

This page lists the public preview rule registry. You can also generate the current list from an installed project:

```bash
php artisan preflight:rules --format=md
```

| Code | Scanner | Default Severity | Description | Recommendation |
| --- | --- | --- | --- | --- |
| `ENV_DEBUG_TRUE` | env | critical | Detects APP_DEBUG=true. | Set APP_DEBUG=false before production deployment. |
| `ENV_LOCAL_ENVIRONMENT` | env | high | Detects APP_ENV=local in deployed projects. | Use APP_ENV=production for production deployments. |
| `ENV_LOG_LEVEL_DEBUG` | env | medium | Detects LOG_LEVEL=debug. | Use a less verbose log level in production. |
| `ENV_SESSION_SECURE_COOKIE_FALSE` | env | medium | Detects SESSION_SECURE_COOKIE=false. | Set SESSION_SECURE_COOKIE=true when serving over HTTPS. |
| `ENV_APP_KEY_MISSING` | env | critical | Detects missing APP_KEY. | Generate an app key with php artisan key:generate. |
| `ENV_DB_PASSWORD_EMPTY` | env | medium | Detects empty DB_PASSWORD. | Use a strong database password and least-privileged database user. |
| `ROUTE_NO_MIDDLEWARE` | routes | high | Detects routes without middleware outside the public allowlist. | Attach appropriate middleware or add intentional public routes to the allowlist. |
| `ROUTE_SENSITIVE_WITHOUT_AUTH` | routes | critical | Detects sensitive route patterns without auth or authorization middleware. | Protect sensitive routes with auth and authorization middleware. |
| `ROUTE_DANGEROUS_URI` | routes | critical | Detects route URIs that expose destructive database operations. | Remove deployment-only routes or guard them behind strict authorization. |
| `ROUTE_DESTRUCTIVE_GET` | routes | high | Detects GET routes with destructive URI or action names. | Use non-GET methods with CSRF protection for state-changing actions. |
| `ROUTE_CLOSURE_ACTION` | routes | low | Detects Closure route actions. | Prefer controller actions for production routes. |
| `CONTROLLER_MISSING_AUTHORIZATION` | controllers | medium | Detects controllers without visible authorization patterns. | Add route middleware, controller middleware, policies, or gate checks where needed. |
| `CONTROLLER_WRITE_WITHOUT_AUTHORIZATION` | controllers | high | Detects store, update, delete, or destroy methods without visible authorization. | Authorize state-changing actions with policies, gates, middleware, or permission checks. |
| `CONTROLLER_PLAINTEXT_PASSWORD` | controllers | critical | Detects visible_password or plain_password references. | Never store or expose plain-text passwords. |
| `CONTROLLER_DESTRUCTIVE_DB_STATEMENT` | controllers | critical | Detects DROP or TRUNCATE through DB::statement(). | Remove destructive database statements from HTTP controllers. |
| `CONTROLLER_REQUEST_ALL_WRITE_CONTEXT` | controllers | medium | Detects request()->all() or $request->all() in write contexts. | Use validated input from Form Requests or $request->validated(). |
| `MODEL_GUARDED_EMPTY` | models | high | Detects protected $guarded = []. | Prefer explicit $fillable attributes or a non-empty $guarded list. |
| `MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING` | models | critical | Detects password in $fillable without obvious hashing. | Ensure password assignment is always hashed before persistence. |
| `MODEL_PLAINTEXT_PASSWORD` | models | critical | Detects visible_password or plain_password fields. | Remove plain-text password fields and store only hashed passwords. |
| `MODEL_MISSING_HAS_FACTORY` | models | info | Detects models without HasFactory. | Add HasFactory if this model needs factory support. |
| `MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION` | models | low | Detects models without $fillable or $guarded. | Declare either $fillable or $guarded. |
| `MIGRATION_VISIBLE_PASSWORD` | migrations | critical | Detects visible_password or plain_password columns. | Never create columns intended to store plain-text passwords. |
| `MIGRATION_USERS_EMAIL_NULLABLE` | migrations | medium | Detects nullable email in users migrations. | Require email where authentication or account recovery depends on it. |
| `MIGRATION_PASSWORD_NULLABLE` | migrations | high | Detects nullable password columns. | Avoid nullable password columns unless intentionally designed. |
| `MIGRATION_IS_ADMIN_COLUMN` | migrations | medium | Detects is_admin boolean columns. | Consider policies or a dedicated roles and permissions system. |
| `MIGRATION_ROLE_COLUMN` | migrations | low | Detects role string columns. | Use a proper roles and permissions design when authorization needs grow. |
| `MIGRATION_USERS_REMEMBER_TOKEN_MISSING` | migrations | low | Detects users migrations without rememberToken(). | Add rememberToken() if the application supports remember-me sessions. |
| `REQUEST_AUTHORIZE_MISSING` | requests | medium | Detects FormRequest classes without authorize(). | Add authorize() and enforce permission checks where needed. |
| `REQUEST_AUTHORIZE_ALWAYS_TRUE` | requests | low | Detects authorize() methods returning true. | Use permission checks for sensitive requests. |
| `REQUEST_RULES_EMPTY` | requests | medium | Detects empty rules() arrays. | Add validation rules for incoming request data. |
| `REQUEST_RULES_MISSING` | requests | high | Detects FormRequest classes without rules(). | Add a rules() method with validation requirements. |
| `REQUEST_NULLABLE_PASSWORD` | requests | medium | Detects nullable password validation rules. | Avoid nullable password rules unless optional password changes are intentional. |
| `REQUEST_SOMETIMES_RULE` | requests | low | Detects use of sometimes validation. | Review optional validation paths for sensitive fields. |
| `COMPOSER_LOCK_MISSING` | composer | medium | Detects missing composer.lock. | Commit composer.lock for applications to keep dependency installs reproducible. |
| `COMPOSER_LOCK_OUTDATED` | composer | medium | Detects composer.json modified after composer.lock. | Run composer update or composer install and commit the updated lock file. |
| `COMPOSER_JSON_INVALID` | composer | high | Detects invalid composer.json syntax. | Fix composer.json so Composer and deployment tooling can parse it. |
| `COMPOSER_MINIMUM_STABILITY_DEV` | composer | medium | Detects minimum-stability=dev. | Avoid dev stability in production applications. |
| `COMPOSER_PREFER_STABLE_MISSING` | composer | low | Detects missing prefer-stable when minimum-stability is dev. | Set prefer-stable=true if dev stability is required. |
| `COMPOSER_DEBUG_PACKAGE_IN_REQUIRE` | composer | high | Detects risky debug packages in require. | Move debug packages to require-dev or remove them. |
| `COMPOSER_UNSAFE_SCRIPT` | composer | high | Detects risky shell commands in Composer scripts. | Remove destructive Composer scripts or move them to guarded deployment tooling. |
| `POLICY_MISSING_FOR_MODEL` | policies | medium | Detects important app models without a convention-based policy file. | Create a policy with php artisan make:policy ModelPolicy --model=Model and add action-specific checks. |
| `POLICY_METHOD_MISSING` | policies | medium | Detects policy files missing configured methods such as view, create, update, or delete. | Add the missing policy method and return an explicit user, role, ownership, or permission decision. |
| `POLICY_ALWAYS_TRUE` | policies | high | Detects policy methods whose entire body directly returns true. | Replace return true with a role, permission, ownership, or Gate check; keep before() broad access intentional and documented. |
| `POLICY_MODEL_NOT_FOUND` | policies | low | Detects convention-based policy files without a matching app model. | Rename the policy, create the matching model, or remove stale policy files. |
| `BLADE_RAW_OUTPUT` | blade | high | Detects {!! !!} raw Blade echo statements. | Use escaped Blade output {{ $variable }} unless the value is intentionally sanitized HTML. |
| `BLADE_FORM_MISSING_CSRF` | blade | high | Detects state-changing Blade forms without @csrf or csrf_field(). | Add @csrf inside every POST, PUT, PATCH, or DELETE form. |
| `BLADE_DELETE_FORM_MISSING_METHOD` | blade | medium | Detects destructive-looking forms without DELETE method spoofing. | Add @method('DELETE') or method_field('DELETE') to forms that delete, destroy, or remove records. |
| `BLADE_UNGUARDED_ADMIN_ACTION` | blade | low | Detects sensitive-looking Blade actions without nearby authorization directives. | Wrap sensitive links, buttons, and forms with @can, @role, @permission, @auth, or a project-specific Gate check. |
| `AUTH_ROUTE_MISSING_THROTTLE` | auth | medium | Detects login, registration, and password routes without throttle or rate limiting middleware. | Add throttle middleware or a named Laravel rate limiter to authentication routes. |
| `AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE` | auth | high | Detects sensitive admin-like routes that use auth without role, permission, ability, or can middleware. | Add can:, role:, permission:, ability:, or abilities: middleware to sensitive routes. |
| `AUTH_API_ROUTE_WEAK_GUARD` | auth | medium | Detects API routes using generic auth middleware without an explicit token guard. | Use auth:sanctum, auth:api, auth:passport, token middleware, or ability middleware for API routes. |

## Important Rule Details

### ENV_DEBUG_TRUE

Docs anchor: `#env_debug_true`

Impact: Debug mode can expose stack traces, environment values, SQL, and source paths to users.

Bad example:

```env
APP_ENV=production
APP_DEBUG=true
```

Better example:

```env
APP_ENV=production
APP_DEBUG=false
```

False-positive guidance: If this is a local-only fixture, scan with local environment config or disable `ENV_DEBUG_TRUE` for that environment.

### ROUTE_DESTRUCTIVE_GET

Docs anchor: `#route_destructive_get`

Impact: GET requests may be triggered by crawlers, previews, browser prefetching, or accidental clicks, causing unintended state changes.

Bad example:

```php
Route::get('/users/{user}/delete', [UserController::class, 'destroy']);
```

Better example:

```php
Route::delete('/users/{user}', [UserController::class, 'destroy'])
    ->middleware(['auth', 'can:delete,user']);
```

False-positive guidance: Add the route to `ignored_routes`, `public_route_allowlist`, or disable the rule in `config/preflight.php`.

### ROUTE_SENSITIVE_WITHOUT_AUTH

Docs anchor: `#route_sensitive_without_auth`

Impact: Sensitive routes can expose admin, account, settings, payment, or user-management actions to unauthenticated visitors.

Bad example:

```php
Route::get('/admin/users', [AdminUserController::class, 'index']);
```

Better example:

```php
Route::get('/admin/users', [AdminUserController::class, 'index'])
    ->middleware(['auth', 'can:viewAny,App\Models\User']);
```

False-positive guidance: Add the route to `ignored_routes`, `public_route_allowlist`, or disable the rule in `config/preflight.php`.

### CONTROLLER_MISSING_AUTHORIZATION

Docs anchor: `#controller_missing_authorization`

Impact: Controller actions can become sensitive over time; missing visible authorization makes access control harder to review.

Bad example:

```php
public function update(Request $request, Project $project)
{
    $project->update($request->validated());
}
```

Better example:

```php
public function update(UpdateProjectRequest $request, Project $project)
{
    $this->authorize('update', $project);

    $project->update($request->validated());
}
```

False-positive guidance: Add project-specific authorization keywords under `scanners.controllers.options.authorization_keywords`.

### MODEL_GUARDED_EMPTY

Docs anchor: `#model_guarded_empty`

Impact: Every current and future column becomes mass assignable, which can expose privilege or ownership fields to request input.

Bad example:

```php
class User extends Model
{
    protected $guarded = [];
}
```

Better example:

```php
class User extends Model
{
    protected $fillable = ['name', 'email'];
}
```

False-positive guidance: Disable `MODEL_GUARDED_EMPTY` for trusted internal models or add model paths to `ignored_paths`.

### REQUEST_AUTHORIZE_ALWAYS_TRUE

Docs anchor: `#request_authorize_always_true`

Impact: Validation does not decide who may perform an action; sensitive requests still need authorization checks.

Bad example:

```php
public function authorize(): bool
{
    return true;
}
```

Better example:

```php
public function authorize(): bool
{
    return $this->user()?->can('update', $this->route('project')) ?? false;
}
```

False-positive guidance: Add public request classes to `scanners.requests.options.allow_authorize_true_for`.

### COMPOSER_DEBUG_PACKAGE_IN_REQUIRE

Docs anchor: `#composer_debug_package_in_require`

Impact: Debug packages can expose application internals or add unnecessary production attack surface.

Bad example:

```json
{
  "require": {
    "barryvdh/laravel-debugbar": "^3.0"
  }
}
```

Better example:

```json
{
  "require-dev": {
    "barryvdh/laravel-debugbar": "^3.0"
  }
}
```

False-positive guidance: Tune `scanners.composer.options.risky_packages_in_require` for project-specific debug packages.
