# Preflight Limitations

Preflight is a static/project audit helper.

It does not replace professional security review.

It may produce false positives.

It does not execute code.

It does not perform dependency vulnerability database scanning.

It does not guarantee a project is secure.

SARIF support is for reporting Preflight project findings.

Baseline files should be reviewed before committing.

Blade authorization checks can be noisy because templates vary widely and nearby directives are heuristic.

Policy detection is convention-based and expects model and policy names such as `Product` and `ProductPolicy`.

Auth route detection depends on route URI and middleware naming, so custom guards or permission middleware may need scanner option tuning.

Changed mode depends on Git history availability.

Shallow clones may need `fetch-depth: 0` or an explicit fetch of the base ref.

Route, env, and auth checks are project-wide unless disabled in changed-files configuration.

Some findings require a full scan and may not appear in changed-files mode.

The HTML report is static.

It does not provide a persistent dashboard.

It does not store historical reports.

Do not expose HTML reports publicly if file paths or project details are sensitive.
