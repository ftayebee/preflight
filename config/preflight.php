<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Project Path
    |--------------------------------------------------------------------------
    |
    | By default Preflight scans Laravel's base_path(). Tests or advanced
    | integrations may set this to scan a fixture or alternate project root.
    |
    */
    'base_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Scanner Selection
    |--------------------------------------------------------------------------
    */
    'enabled_scanners' => [
        'env',
        'routes',
        'controllers',
        'models',
        'migrations',
        'requests',
        'composer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scanner Configuration
    |--------------------------------------------------------------------------
    |
    | Each scanner is read-only and may expose options for project-specific
    | tuning. Command options --only and --skip take priority at runtime.
    |
    */
    'scanners' => [
        'env' => [
            'enabled' => true,
            'options' => [
                'production_env_names' => ['production', 'prod', 'staging'],
                'local_env_names' => ['local', 'development', 'testing'],
                'require_secure_session_cookie' => true,
                'allow_empty_db_password_in_local' => true,
            ],
        ],
        'routes' => [
            'enabled' => true,
            'options' => [
                'auth_middleware_keywords' => ['auth', 'auth:sanctum', 'verified', 'can:', 'permission:', 'role:'],
                'public_route_allowlist' => ['/', 'login', 'register', 'password/*', 'forgot-password', 'sanctum/csrf-cookie', 'up', 'health', 'api/ping'],
                'sensitive_route_patterns' => ['admin*', 'dashboard*', 'settings*', 'users*', 'roles*', 'permissions*', 'payments*', 'seed*', 'migrate*', 'reset*', 'truncate*', 'database*'],
                'destructive_uri_keywords' => ['delete', 'destroy', 'remove', 'truncate', 'wipe', 'reset'],
            ],
        ],
        'controllers' => [
            'enabled' => true,
            'options' => [
                'authorization_keywords' => ['$this->authorize', '$this->authorizeResource', 'Gate::allows', 'Gate::denies', 'Gate::authorize', '->middleware(\'can:', '->middleware("can:', 'permission:', 'role:', 'can:'],
                'sensitive_method_names' => ['store', 'update', 'destroy', 'delete', 'remove', 'restore', 'forceDelete'],
                'dangerous_db_keywords' => ['DROP', 'TRUNCATE', 'db:wipe', 'migrate:fresh'],
                'plaintext_password_keywords' => ['visible_password', 'plain_password', 'plaintext_password'],
            ],
        ],
        'models' => [
            'enabled' => true,
            'options' => [
                'plaintext_password_fields' => ['visible_password', 'plain_password', 'plaintext_password'],
                'sensitive_fillable_fields' => ['password', 'is_admin', 'role', 'permissions'],
            ],
        ],
        'migrations' => [
            'enabled' => true,
            'options' => [
                'plaintext_password_columns' => ['visible_password', 'plain_password', 'plaintext_password'],
                'suspicious_columns' => ['is_admin' => 'medium', 'role' => 'low'],
            ],
        ],
        'requests' => [
            'enabled' => true,
            'options' => [
                'sensitive_request_names' => ['Store', 'Update', 'Delete', 'Destroy', 'Payment', 'Role', 'Permission', 'User', 'Admin'],
                'allow_authorize_true_for' => [],
                'sensitive_rule_keywords' => ['password', 'role', 'permission', 'is_admin'],
            ],
        ],
        'composer' => [
            'enabled' => true,
            'options' => [
                'risky_packages_in_require' => [
                    'barryvdh/laravel-debugbar' => 'high',
                    'facade/ignition' => 'high',
                    'filp/whoops' => 'medium',
                ],
                'unsafe_script_keywords' => ['rm -rf', 'chmod -R 777', 'migrate:fresh', 'db:wipe'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring And Filtering
    |--------------------------------------------------------------------------
    */
    'severity_score' => [
        'critical' => 25,
        'high' => 15,
        'medium' => 8,
        'low' => 3,
    ],

    'ignored_routes' => [],

    'ignored_paths' => [],

    'ignored_issue_codes' => [
        // 'ROUTE_NO_MIDDLEWARE',
    ],

    'public_route_allowlist' => [
        '/',
        'login',
        'register',
        'password/*',
        'forgot-password',
        'sanctum/csrf-cookie',
        'up',
        'health',
        'api/ping',
    ],

    'sensitive_route_patterns' => [
        'admin*',
        'dashboard*',
        'settings*',
        'users*',
        'roles*',
        'permissions*',
        'payments*',
        'seed*',
        'migrate*',
        'reset*',
        'truncate*',
        'database*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Configuration
    |--------------------------------------------------------------------------
    |
    | Rules can be disabled or assigned a different severity. Issue codes are
    | stable so teams can baseline, ignore, or tune findings over time.
    |
    */
    'rules' => [
        'ENV_DEBUG_TRUE' => ['enabled' => true, 'severity' => 'critical'],
        'ENV_LOCAL_ENVIRONMENT' => ['enabled' => true, 'severity' => 'high'],
        'ENV_LOG_LEVEL_DEBUG' => ['enabled' => true, 'severity' => 'medium'],
        'ENV_SESSION_SECURE_COOKIE_FALSE' => ['enabled' => true, 'severity' => 'medium'],
        'ENV_APP_KEY_MISSING' => ['enabled' => true, 'severity' => 'critical'],
        'ENV_DB_PASSWORD_EMPTY' => ['enabled' => true, 'severity' => 'medium'],
        'ROUTE_NO_MIDDLEWARE' => ['enabled' => true, 'severity' => 'high'],
        'ROUTE_SENSITIVE_WITHOUT_AUTH' => ['enabled' => true, 'severity' => 'critical'],
        'ROUTE_DANGEROUS_URI' => ['enabled' => true, 'severity' => 'critical'],
        'ROUTE_DESTRUCTIVE_GET' => ['enabled' => true, 'severity' => 'high'],
        'ROUTE_CLOSURE_ACTION' => ['enabled' => true, 'severity' => 'low'],
        'CONTROLLER_MISSING_AUTHORIZATION' => ['enabled' => true, 'severity' => 'medium'],
        'CONTROLLER_WRITE_WITHOUT_AUTHORIZATION' => ['enabled' => true, 'severity' => 'high'],
        'CONTROLLER_PLAINTEXT_PASSWORD' => ['enabled' => true, 'severity' => 'critical'],
        'CONTROLLER_DESTRUCTIVE_DB_STATEMENT' => ['enabled' => true, 'severity' => 'critical'],
        'CONTROLLER_REQUEST_ALL_WRITE_CONTEXT' => ['enabled' => true, 'severity' => 'medium'],
        'MODEL_GUARDED_EMPTY' => ['enabled' => true, 'severity' => 'high'],
        'MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING' => ['enabled' => true, 'severity' => 'critical'],
        'MODEL_PLAINTEXT_PASSWORD' => ['enabled' => true, 'severity' => 'critical'],
        'MODEL_MISSING_HAS_FACTORY' => ['enabled' => true, 'severity' => 'info'],
        'MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION' => ['enabled' => true, 'severity' => 'low'],
        'MIGRATION_VISIBLE_PASSWORD' => ['enabled' => true, 'severity' => 'critical'],
        'MIGRATION_USERS_EMAIL_NULLABLE' => ['enabled' => true, 'severity' => 'medium'],
        'MIGRATION_PASSWORD_NULLABLE' => ['enabled' => true, 'severity' => 'high'],
        'MIGRATION_IS_ADMIN_COLUMN' => ['enabled' => true, 'severity' => 'medium'],
        'MIGRATION_ROLE_COLUMN' => ['enabled' => true, 'severity' => 'low'],
        'MIGRATION_USERS_REMEMBER_TOKEN_MISSING' => ['enabled' => true, 'severity' => 'low'],
        'REQUEST_AUTHORIZE_MISSING' => ['enabled' => true, 'severity' => 'medium'],
        'REQUEST_AUTHORIZE_ALWAYS_TRUE' => ['enabled' => true, 'severity' => 'low'],
        'REQUEST_RULES_EMPTY' => ['enabled' => true, 'severity' => 'medium'],
        'REQUEST_RULES_MISSING' => ['enabled' => true, 'severity' => 'high'],
        'REQUEST_NULLABLE_PASSWORD' => ['enabled' => true, 'severity' => 'medium'],
        'REQUEST_SOMETIMES_RULE' => ['enabled' => true, 'severity' => 'low'],
        'COMPOSER_LOCK_MISSING' => ['enabled' => true, 'severity' => 'medium'],
        'COMPOSER_LOCK_OUTDATED' => ['enabled' => true, 'severity' => 'medium'],
        'COMPOSER_JSON_INVALID' => ['enabled' => true, 'severity' => 'high'],
        'COMPOSER_MINIMUM_STABILITY_DEV' => ['enabled' => true, 'severity' => 'medium'],
        'COMPOSER_PREFER_STABLE_MISSING' => ['enabled' => true, 'severity' => 'low'],
        'COMPOSER_DEBUG_PACKAGE_IN_REQUIRE' => ['enabled' => true, 'severity' => 'high'],
        'COMPOSER_UNSAFE_SCRIPT' => ['enabled' => true, 'severity' => 'high'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports And Baselines
    |--------------------------------------------------------------------------
    */
    'baseline_file' => base_path('preflight-baseline.json'),

    'default_format' => 'console',

    'sarif' => [
        'tool_name' => 'Preflight',
        'information_uri' => 'https://github.com/fahimtayebee/preflight',
    ],

    'severity_levels' => [
        'critical' => 5,
        'high' => 4,
        'medium' => 3,
        'low' => 2,
        'info' => 1,
    ],

    'report' => [
        'format' => 'console',
    ],

    'fail_under' => null,
];
