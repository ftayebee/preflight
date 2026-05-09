# Preflight Fix Examples

These examples show practical fixes for common high-risk findings. Adjust names and authorization logic to match your application.

## ENV_DEBUG_TRUE

Problem: `APP_DEBUG=true` is enabled.

Why it matters: Laravel can expose stack traces, environment values, SQL, and implementation details.

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

Related config: keep `ENV_DEBUG_TRUE` enabled for production CI.

## ROUTE_DESTRUCTIVE_GET

Problem: A `GET` route appears to delete, destroy, reset, or remove data.

Why it matters: Crawlers, previews, and accidental link clicks can trigger state changes.

Bad example:

```php
Route::get('/users/{user}/delete', [UserController::class, 'destroy']);
```

Better example:

```php
Route::delete('/users/{user}', [UserController::class, 'destroy'])
    ->middleware(['auth', 'can:delete,user']);
```

Related config: tune `scanners.routes.options.destructive_uri_keywords` for project-specific verbs.

## ROUTE_SENSITIVE_WITHOUT_AUTH

Problem: A sensitive route does not use auth or authorization middleware.

Why it matters: Admin, settings, user, role, permission, and payment routes usually expose private or destructive actions.

Bad example:

```php
Route::get('/admin/users', [AdminUserController::class, 'index']);
```

Better example:

```php
Route::get('/admin/users', [AdminUserController::class, 'index'])
    ->middleware(['auth', 'can:viewAny,App\Models\User']);
```

Related config: add intentional public routes to `public_route_allowlist`.

## CONTROLLER_MISSING_AUTHORIZATION

Problem: A controller has no visible middleware, policy, Gate, role, permission, or can checks.

Why it matters: Controller methods often become sensitive as features grow.

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

Related config: add project-specific checks to `scanners.controllers.options.authorization_keywords`.

## MODEL_GUARDED_EMPTY

Problem: A model uses `protected $guarded = [];`.

Why it matters: Every column is mass assignable, including columns added later.

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

Related config: keep the rule enabled for models that receive request input.

## REQUEST_AUTHORIZE_ALWAYS_TRUE

Problem: A FormRequest `authorize()` method always returns `true`.

Why it matters: Validation is not authorization; sensitive requests still need permission checks.

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

Related config: use `allow_authorize_true_for` for truly public requests such as contact forms.

## COMPOSER_DEBUG_PACKAGE_IN_REQUIRE

Problem: A debug package is installed in production `require`.

Why it matters: Debug tooling can expose application internals or add unnecessary production surface area.

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

Related config: tune `scanners.composer.options.risky_packages_in_require` for internal packages.

## BLADE_RAW_OUTPUT

Problem: A Blade template uses `{!! !!}` raw output.

Why it matters: Unsanitized raw output can introduce cross-site scripting.

Bad example:

```blade
{!! $comment->body !!}
```

Better example:

```blade
{{ $comment->body }}
```

For intentionally sanitized HTML:

```blade
{!! clean($article->trusted_html) !!}
```

Related config: keep this rule enabled and baseline deliberate sanitized locations.

## BLADE_FORM_MISSING_CSRF

Problem: A state-changing Blade form does not include CSRF protection.

Why it matters: Attackers may trick authenticated users into submitting unwanted requests.

Bad example:

```blade
<form method="POST" action="/profile">
    <button>Save</button>
</form>
```

Better example:

```blade
<form method="POST" action="/profile">
    @csrf
    <button>Save</button>
</form>
```

Related config: none usually needed.

## POLICY_ALWAYS_TRUE

Problem: A policy method directly returns `true`.

Why it matters: The policy allows every authenticated caller for that action.

Bad example:

```php
public function delete(User $user, Invoice $invoice): bool
{
    return true;
}
```

Better example:

```php
public function delete(User $user, Invoice $invoice): bool
{
    return $user->id === $invoice->owner_id || $user->can('invoices.delete');
}
```

Related config: `allow_policy_before_true` permits an intentional `before()` super-admin shortcut.

## AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE

Problem: An admin-like route uses only `auth`.

Why it matters: Authentication proves identity, but it does not prove authorization to manage admin resources.

Bad example:

```php
Route::get('/admin/payments', [PaymentAdminController::class, 'index'])
    ->middleware('auth');
```

Better example:

```php
Route::get('/admin/payments', [PaymentAdminController::class, 'index'])
    ->middleware(['auth', 'permission:payments.view']);
```

Related config: tune `scanners.auth.options.admin_authorization_keywords` if your app uses custom middleware names.
