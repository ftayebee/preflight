<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Preflight Reports')</title>
    <style>
        :root { color-scheme: light; --bg: #f6f7f9; --panel: #ffffff; --text: #111827; --muted: #5b6472; --border: #d9dee7; --accent: #0f766e; --danger: #991b1b; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; line-height: 1.5; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 24px; }
        header { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 28px; }
        h2 { margin-top: 0; font-size: 20px; }
        .muted { color: var(--muted); }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 18px; box-shadow: 0 1px 2px rgba(17, 24, 39, .04); }
        .notice { border-left: 4px solid var(--accent); }
        .danger { border-left-color: var(--danger); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text); font-weight: 600; }
        .button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-size: 13px; }
        .badge { display: inline-block; border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px; font-size: 12px; color: var(--muted); }
        iframe { width: 100%; min-height: 72vh; border: 1px solid var(--border); border-radius: 10px; background: #fff; }
        .stack { display: grid; gap: 16px; }
        @media (max-width: 720px) { .wrap { padding: 16px; } header { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
    <main class="wrap">
        <header>
            <div>
                <h1>Preflight</h1>
                <div class="muted">Read-only Laravel audit report UI</div>
            </div>
            <div class="actions">
                <a class="button" href="{{ route('preflight.index') }}">Reports</a>
                <a class="button primary" href="{{ route('preflight.latest') }}">View latest</a>
            </div>
        </header>

        <section class="panel notice">
            <strong>Local report viewer.</strong>
            <span class="muted">This UI only reads generated HTML reports. It does not run audits, store history, or require a database.</span>
        </section>

        <div style="height: 16px"></div>

        @yield('content')
    </main>
</body>
</html>
