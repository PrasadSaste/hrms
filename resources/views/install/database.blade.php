<x-install.layout :step="$step" title="Database">
    <h2>Where should the records live?</h2>
    <p class="sub">
        These are written to <code>.env</code> and used to create the tables. The database
        does not have to exist yet — if this user is allowed to create one, it will be created.
    </p>

    <form method="POST" action="{{ route('install.database.store') }}">
        @csrf

        <div class="card">
            <h3>Connection</h3>

            <div class="field">
                <label for="driver">Database</label>
                <select name="driver" id="driver" onchange="mysqlOnly(this.value)">
                    <option value="mysql" @selected(old('driver', $current['driver']) === 'mysql')>MySQL</option>
                    <option value="mariadb" @selected(old('driver', $current['driver']) === 'mariadb')>MariaDB</option>
                    <option value="sqlite" @selected(old('driver', $current['driver']) === 'sqlite')>SQLite (a single file — for a trial, not for a company)</option>
                </select>
            </div>

            <div class="row two js-server">
                <div class="field">
                    <label for="host">Host</label>
                    <input type="text" name="host" id="host" value="{{ old('host', $current['host']) }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="port">Port</label>
                    <input type="number" name="port" id="port" value="{{ old('port', $current['port']) }}" autocomplete="off">
                </div>
            </div>

            <div class="field">
                <label for="database"><span class="js-db-label">Database name</span></label>
                <input type="text" name="database" id="database" value="{{ old('database', $current['database']) }}" autocomplete="off" required>
                <p class="hint js-db-hint">Created with <code>utf8mb4</code> if it does not exist, so names and the rupee sign store correctly.</p>
            </div>

            <div class="row two js-server">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username', $current['username']) }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="password">Password <span class="opt">— left blank if there is none</span></label>
                    <input type="password" name="password" id="password" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="notice warn">
            Pressing <strong>Set up the database</strong> creates every table and loads the roles, leave
            types, salary components and help guides. On an empty database that takes a few seconds; do not
            close the tab while it runs.
        </div>

        <div class="actions">
            <button type="submit" class="primary">Set up the database</button>
            <button type="submit" class="ghost" name="test_only" value="1">Test connection</button>
            <span class="spacer"></span>
            <a class="btn ghost" href="{{ route('install.index') }}">Back</a>
        </div>
    </form>

    <script>
        // SQLite is one file: a host, a port and a login mean nothing for it.
        function mysqlOnly(driver) {
            var server = driver !== 'sqlite';
            document.querySelectorAll('.js-server').forEach(function (row) { row.hidden = ! server; });
            document.querySelector('.js-db-label').textContent = server ? 'Database name' : 'Path to the database file';
            document.querySelector('.js-db-hint').hidden = ! server;
        }
        mysqlOnly(document.getElementById('driver').value);
    </script>
</x-install.layout>
