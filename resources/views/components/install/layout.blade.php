{{--
    The setup wizard's own page.

    Deliberately self-contained: its styles are inline and it reads nothing
    from the database. This is the one screen that has to draw correctly on a
    machine where `npm run build` has not been run, no settings exist and no
    connection is configured, so it can afford to depend on none of them.
--}}
@props(['step' => 'requirements', 'title' => 'Set up'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Set up' }} &middot; HRMS setup</title>
    <style>
        :root {
            --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --page: #f1f5f9;
            --card: #ffffff; --brand: #2563eb; --brand-dark: #1d4ed8; --brand-soft: #eff6ff;
            --good: #059669; --good-soft: #ecfdf5; --bad: #dc2626; --bad-soft: #fef2f2;
            --warn: #b45309; --warn-soft: #fffbeb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--page); color: var(--ink);
            font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--brand); }
        .shell { display: flex; min-height: 100vh; flex-direction: column; }
        @media (min-width: 900px) { .shell { flex-direction: row; } }

        /* Rail */
        .rail {
            background: #0b1220; color: #cbd5e1; padding: 28px 26px;
            background-image: linear-gradient(160deg, #12203a 0%, #0b1220 60%);
        }
        @media (min-width: 900px) { .rail { width: 310px; flex: none; padding: 40px 32px; } }
        .rail h1 { color: #fff; font-size: 19px; margin: 0 0 4px; letter-spacing: -0.01em; }
        .rail p.lede { color: #94a3b8; font-size: 13.5px; margin: 0 0 30px; }
        .steps { list-style: none; margin: 0; padding: 0; }
        .steps li { display: flex; gap: 12px; align-items: flex-start; padding: 9px 0; font-size: 14px; }
        .steps .dot {
            flex: none; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center;
            font-size: 12px; font-weight: 700; background: #1e293b; color: #94a3b8; border: 1px solid #334155;
        }
        .steps li.done .dot { background: var(--good); border-color: var(--good); color: #fff; }
        .steps li.here .dot { background: var(--brand); border-color: var(--brand); color: #fff; }
        .steps li.here { color: #fff; font-weight: 600; }
        .steps small { display: block; color: #64748b; font-weight: 400; font-size: 12.5px; }
        .rail footer { margin-top: 34px; font-size: 12px; color: #64748b; }

        /* Content */
        main { flex: 1; padding: 28px 20px 56px; }
        @media (min-width: 900px) { main { padding: 48px; } }
        .wrap { max-width: 720px; margin: 0 auto; }
        h2 { font-size: 24px; margin: 0 0 6px; letter-spacing: -0.02em; }
        .sub { color: var(--muted); margin: 0 0 26px; }

        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 22px; margin-bottom: 18px; }
        .card > h3 { margin: 0 0 14px; font-size: 14px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }

        /* Fields */
        .field { margin-bottom: 16px; }
        .row { display: grid; gap: 16px; }
        @media (min-width: 620px) { .row.two { grid-template-columns: 1fr 1fr; } .row.three { grid-template-columns: 1fr 1fr 1fr; } }
        label { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 5px; }
        label .opt { font-weight: 400; color: var(--muted); }
        input[type=text], input[type=email], input[type=url], input[type=password], input[type=number], input[type=file], select, textarea {
            width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px;
            font: inherit; color: var(--ink); background: #fff;
        }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--brand); outline-offset: -1px; border-color: var(--brand); }
        input[type=color] { width: 100%; height: 40px; padding: 3px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .hint { color: var(--muted); font-size: 12.5px; margin-top: 5px; }
        .err { color: var(--bad); font-size: 12.5px; margin-top: 5px; }

        /* Buttons */
        .actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 24px; }
        button, .btn {
            font: inherit; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: 1px solid transparent;
            cursor: pointer; text-decoration: none; display: inline-block;
        }
        .primary { background: var(--brand); color: #fff; }
        .primary:hover { background: var(--brand-dark); }
        .primary[disabled] { background: #94a3b8; cursor: not-allowed; }
        .ghost { background: #fff; color: var(--ink); border-color: #cbd5e1; }
        .ghost:hover { background: #f8fafc; }
        .spacer { flex: 1; }

        /* Notices */
        .notice { border-radius: 10px; padding: 12px 15px; margin-bottom: 18px; font-size: 14px; border: 1px solid; }
        .notice.ok { background: var(--good-soft); border-color: #a7f3d0; color: #065f46; }
        .notice.bad { background: var(--bad-soft); border-color: #fecaca; color: #991b1b; }
        .notice.warn { background: var(--warn-soft); border-color: #fde68a; color: #92400e; }
        .notice ul { margin: 6px 0 0; padding-left: 20px; }

        /* Checks */
        table.checks { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.checks td { padding: 7px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.checks tr:last-child td { border-bottom: 0; }
        table.checks .what { font-weight: 600; }
        table.checks .why { color: var(--muted); font-size: 12.5px; }
        table.checks .verdict { text-align: right; white-space: nowrap; font-weight: 600; font-size: 13px; }
        .yes { color: var(--good); }
        .no { color: var(--bad); }
        /* An optional extension that is absent is information, not a failure. */
        .soft { color: var(--muted); font-weight: 400; }
    </style>
</head>
<body>
<div class="shell">
    <aside class="rail">
        <h1>HRMS setup</h1>
        <p class="lede">Four short steps and the system is yours.</p>

        @php
            $order = ['requirements', 'database', 'organisation', 'administrator', 'complete'];
            $steps = [
                'requirements'  => ['Server check', 'What this machine needs'],
                'database'      => ['Database', 'Where the records live'],
                'organisation'  => ['Your company', 'Name, address and colours'],
                'administrator' => ['Administrator', 'The first account'],
                'complete'      => ['Done', 'Sign in and begin'],
            ];
            $at = array_search($step, $order, true);
        @endphp

        <ol class="steps">
            @foreach ($steps as $key => [$name, $detail])
                @php $index = array_search($key, $order, true); @endphp
                <li class="{{ $index < $at ? 'done' : ($index === $at ? 'here' : '') }}">
                    <span class="dot">{{ $index < $at ? '✓' : $index + 1 }}</span>
                    <span>{{ $name }}<small>{{ $detail }}</small></span>
                </li>
            @endforeach
        </ol>

        <footer>Nothing here is reachable once setup has finished.</footer>
    </aside>

    <main>
        <div class="wrap">
            @if (session('status'))
                <div class="notice ok">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="notice bad">
                    <strong>That did not work.</strong>
                    <ul>
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
