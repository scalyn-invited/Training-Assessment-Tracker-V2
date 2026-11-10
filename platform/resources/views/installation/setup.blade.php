<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Set up Training Tracker</title>
    <link rel="stylesheet" href="/setup.css">
    <script src="/setup.js" defer></script>
</head>
<body>
<a href="#main" class="skip">Skip to setup</a>
<div class="shell">
    <aside>
        <p class="brand">TRAINING TRACKER</p>
        <h1>A workspace<br>ready for progress.</h1>
        <p>Connect your database and create the first Global Admin.</p>
        <p class="mode">{{ $mode === 'local' ? 'Local development · fictional demo included' : 'Hosted installation · empty workspace' }}</p>
        <ol class="steps" aria-label="Installation steps">
            @foreach(['requirements' => 'Check requirements', 'database' => 'Connect database', 'administrator' => 'Create administrator', 'review' => 'Review and install'] as $key => $label)
                <li @if($step === $key || ($key === 'review' && in_array($step, ['progress', 'complete']))) aria-current="step" @endif>{{ $label }}</li>
            @endforeach
        </ol>
        <p class="note">Setup closes after installation. Your database and account credentials stay private.</p>
    </aside>
    <main id="main" tabindex="-1">
        @if($problems)
            <div class="error" role="alert" tabindex="-1" id="errors"><h2>Setup needs your attention</h2><ul>@foreach($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
        @endif
        @if($step === 'token')
            <h2>Unlock installation</h2><p>Enter the one-time token from your server terminal. It expires after one hour.</p>
            <form method="post" action="/setup">@csrf<input type="hidden" name="action" value="token">
                <label for="setup_token">Setup token</label><input id="setup_token" name="setup_token" type="password" required autocomplete="off" maxlength="64">
                <button type="button" class="secondary reveal" data-for="setup_token">Show token</button>
                <button class="primary">Unlock setup</button>
            </form>
        @elseif($step === 'requirements')
            <p class="eyebrow">STEP 1 OF 4</p><h2>Before we begin</h2>
            <p>Create an empty database in phpMyAdmin first. Keep its name and connection details ready.</p>
            <ul class="checks">@foreach($requirements as $label => $passed)<li><span>{{ $label }}</span><strong class="{{ $passed ? 'pass' : 'fail' }}">{{ $passed ? 'Ready' : 'Action needed' }}</strong></li>@endforeach</ul>
            <form method="post" action="/setup">@csrf<input type="hidden" name="action" value="requirements"><button class="primary">{{ in_array(false, $requirements, true) ? 'Check requirements again' : 'Continue to database' }}</button></form>
        @elseif($step === 'database')
            <p class="eyebrow">STEP 2 OF 4</p><h2>Connect your database</h2>
            <p>{{ $mode === 'local' ? 'Use your XAMPP or standalone MariaDB server.' : 'Use a MariaDB or MySQL server.' }} Setup uses an existing empty database and never deletes existing tables.</p>
            <form method="post" action="/setup">@csrf<input type="hidden" name="action" value="database">
                <div class="row"><div><label for="host">Database host</label><input id="host" name="host" value="{{ $database['host'] ?? '' }}" required maxlength="253" autocomplete="off"></div>
                    <div><label for="port">Port</label><input id="port" name="port" type="number" value="{{ $database['port'] ?? '3306' }}" required min="1" max="65535" inputmode="numeric"></div></div>
                <label for="database">Database name</label><input id="database" name="database" value="{{ $database['database'] ?? '' }}" required maxlength="64" autocomplete="off">
                <label for="username">Database username</label><input id="username" name="username" value="{{ $database['username'] ?? '' }}" required maxlength="128" autocomplete="off">
                <label for="db_password">Database password <span>(leave blank only if your server permits it)</span></label><input id="db_password" name="db_password" type="password" maxlength="1024" autocomplete="off">
                <button type="button" class="secondary reveal" data-for="db_password">Show password</button>
                <div class="actions"><button name="action" value="back" formnovalidate class="secondary">Back</button><button class="primary">Test connection and continue</button></div>
            </form>
        @elseif($step === 'administrator')
            <p class="eyebrow">STEP 3 OF 4</p><h2>Your Global Admin account</h2>
            <p>This account manages the platform. Use your own email, separate from the fictional demo accounts.</p>
            <form method="post" action="/setup">@csrf<input type="hidden" name="action" value="administrator">
                <label for="name">Your name</label><input id="name" name="name" value="{{ $administrator['name'] ?? '' }}" autocomplete="name" required maxlength="120">
                <label for="email">Email address</label><input id="email" name="email" type="email" value="{{ $administrator['email'] ?? '' }}" autocomplete="username" required maxlength="200">
                <p id="password-help" class="help">Use at least 15 characters and at most 72 bytes. A long, unique passphrase works well.</p>
                @foreach(['password' => 'Administrator password', 'password_confirmation' => 'Confirm administrator password'] as $field => $label)
                    <label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="password" required minlength="15" maxlength="72" autocomplete="new-password" aria-describedby="password-help">
                    <button type="button" class="secondary reveal" data-for="{{ $field }}">Show password</button>
                @endforeach
                @if($mode === 'local')
                    <fieldset><legend>Fictional demo accounts</legend><p>Choose a separate password for the demo client administrators, coordinators and members.</p>
                        @foreach(['demo_password' => 'Demo account password', 'demo_password_confirmation' => 'Confirm demo password'] as $field => $label)
                            <label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="password" required minlength="15" maxlength="72" autocomplete="new-password" aria-describedby="password-help">
                            <button type="button" class="secondary reveal" data-for="{{ $field }}">Show password</button>
                        @endforeach
                    </fieldset>
                @endif
                <div class="actions"><button name="action" value="back" formnovalidate class="secondary">Back</button><button class="primary">Review installation</button></div>
            </form>
        @elseif($step === 'review')
            <p class="eyebrow">STEP 4 OF 4</p><h2>Ready to install</h2>
            <dl><dt>Database</dt><dd>{{ $details['database'] ?? '' }} on {{ $details['host'] ?? '' }}:{{ $details['port'] ?? '' }}</dd><dt>Server</dt><dd>{{ $details['version'] ?? '' }}</dd>
                <dt>Global Admin</dt><dd>{{ $administrator['name'] ?? '' }} · {{ $administrator['email'] ?? '' }}</dd><dt>Starting data</dt><dd>{{ $mode === 'local' ? 'Three fictional clients, 30 demo accounts and 21 training scenarios.' : 'An empty workspace with your Global Admin account.' }}</dd></dl>
            <p>Installation creates tables, accounts and private configuration. Live AI and email delivery remain disabled.</p>
            <form method="post" action="/setup">@csrf<input type="hidden" name="action" value="install"><div class="actions"><button name="action" value="back" class="secondary">Back</button><button class="primary">Install Training Tracker</button></div></form>
        @elseif($step === 'progress')
            <h2>Installation in progress</h2>
            <p role="status">Completed: {{ ['migrated' => 'database migrations', 'seeded' => 'starting workspace data', 'administrator' => 'Global Admin account'][$stage] ?? $stage ?? 'preparation' }}.</p>
            <p>Continue to the next stage. If a stage is interrupted, retry here; setup preserves completed work.</p>
            <form method="post" action="/setup" data-advance="{{ $problems ? 'false' : 'true' }}">@csrf<input type="hidden" name="action" value="install"><button class="primary">Continue installation</button></form>
        @else
            <p class="eyebrow">INSTALLATION COMPLETE</p><h2>Your workspace is ready</h2><p>Sign in with the personal administrator email and password you chose. Setup is now locked.</p>
            @if($mode === 'local')<p>Demo emails follow <code>demo.CLIENT.PERSON@example.invalid</code>: clients 1–3; person 0 is Client Admin, 1–2 are Coordinators and 3–9 are Members. Use your demo password.</p>@endif
            <a class="primary button" href="/login">Go to sign in</a>
        @endif
        <p id="working" role="status" hidden>Working on this step. Please keep this page open.</p>
    </main>
</div>
</body></html>
