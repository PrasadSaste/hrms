# Deploying on Ubuntu 24.04 with nginx

A complete, copy-and-paste installation of BySure HRMS on a fresh Ubuntu 24.04
LTS server, followed by the routine for shipping an update.

Everything below assumes:

| | |
| --- | --- |
| Domain | `hrms.example.in` — substitute yours throughout |
| Application root | `/var/www/hrms` |
| System user | `deploy` (owns the code), `www-data` (runs PHP-FPM and nginx) |
| Database | MySQL 8 on the same host |

> **Four things are easy to miss and each one silently breaks a feature.**
> A queue worker must run or **no email is ever sent**. The scheduler needs its
> one cron entry or **the evening reports never go out**. TLS must be real or
> **the punch buttons stop working**, because browsers only hand out a location
> over HTTPS. And `storage/` must be writable by `www-data` or uploads and
> payslip PDFs fail.

---

## 1. Packages

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 ships with Ubuntu 24.04 and satisfies the ^8.3 requirement.
# For 8.4, add ppa:ondrej/php first and swap the version below.
sudo apt install -y nginx mysql-server git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 22 — only needed to build the assets. If you build elsewhere and ship
# public/build with the release, the server does not need Node at all.
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

`php8.3-intl` is **not** optional: net pay is written out in words with
`NumberFormatter`, and payroll fails without it. Confirm the extensions loaded:

```bash
php -m | grep -E 'intl|bcmath|gd|mbstring|pdo_mysql|zip|xml'
```

### PHP settings

```bash
sudo tee /etc/php/8.3/fpm/conf.d/99-hrms.ini >/dev/null <<'INI'
memory_limit = 512M
upload_max_filesize = 12M
post_max_size = 16M
max_execution_time = 120
date.timezone = Asia/Kolkata
expose_php = Off
INI
sudo systemctl restart php8.3-fpm
```

The 12 MB upload ceiling covers the largest thing the application accepts — a
5 MB leave attachment or a 10 MB verification document — with room for the rest
of the form. Payroll for a few hundred employees runs comfortably inside 120
seconds; raise it if a single run covers thousands.

---

## 2. Database

```bash
sudo mysql_secure_installation

sudo mysql <<'SQL'
CREATE DATABASE hrms_bysure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hrms'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON hrms_bysure.* TO 'hrms'@'localhost';
FLUSH PRIVILEGES;
SQL
```

`utf8mb4` matters: names carry diacritics and the interface uses the rupee
sign. A `latin1` schema mangles both.

---

## 3. The application

```bash
sudo mkdir -p /var/www/hrms
sudo chown deploy:www-data /var/www/hrms
sudo -u deploy git clone <your-repository-url> /var/www/hrms
cd /var/www/hrms

sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy npm ci && sudo -u deploy npm run build

sudo -u deploy cp .env.example .env
sudo -u deploy php artisan key:generate
```

Then edit `.env`. The values that must change from the example:

```dotenv
APP_NAME="Beyond Sure HRMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hrms.example.in
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_bysure
DB_USERNAME=hrms
DB_PASSWORD=a-long-random-password

# Queue, cache and sessions all live in the database. Nothing else to install.
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=smtp
MAIL_HOST=email-smtp.ap-south-1.amazonaws.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<ses-smtp-username>
MAIL_PASSWORD=<ses-smtp-password>
MAIL_FROM_ADDRESS="noreply@bysure.in"
MAIL_FROM_NAME="BeyondSure Private Limited"

# Outside production every message is diverted to this one inbox. Production
# ignores the line entirely, so it is safe to leave in place — but leave it out
# of a production .env anyway, so nobody has to reason about it.
# MAIL_REDIRECT_ALL_TO=api@shrigodatechlabs.com

# Never true in production — it would create the demo workforce.
SEED_DEMO_DATA=false

# Only needed if a browser app on another origin uses cookie authentication.
SANCTUM_STATEFUL_DOMAINS=hrms.example.in
```

`APP_DEBUG=false` is not cosmetic. With it on, a stack trace containing
database credentials is served to whoever triggered the error.

**The sender address must be verified with your mail provider.** On SES an
unverified `MAIL_FROM_ADDRESS` fails every send, and because mail is queued the
failure lands in the log rather than in front of the person who caused it.

**The mail server can also be set in the interface.** Settings → Email → Mail
server overrides `MAIL_MAILER` and the SMTP block above once an administrator
chooses something other than "use the environment file". If mail is going
somewhere you did not expect, look there before you look at `.env` — the line
under the sender fields on that screen states what is actually in force. On a
box where deployment owns the configuration, leave that setting on the
environment file.

**A staging server must not email your staff.** Any environment whose `APP_ENV`
is not `production` sends every message to `MAIL_REDIRECT_ALL_TO` instead of the
employee it names, with the real recipients kept on the message as
`X-Original-To`. Set that variable on staging and on every developer machine;
production ignores it whether or not it is set. If a staging box is running with
`APP_ENV=production` — a common copy-paste — the redirect is off and real people
will be mailed, so check `php artisan tinker --execute="echo app()->environment()"`
before restoring a database copy onto it.

### Set it up

On a server, do this from the command line rather than in a browser. It asks
nothing you have not already decided, and it means the web installer is never
open on a public address even for a minute:

```bash
sudo -u deploy php artisan hrms:install \
    --company="Beyond Sure" \
    --admin-name="Your Name" \
    --admin-email=you@example.in \
    --admin-password='a long password you have somewhere safe' \
    --no-interaction
```

That checks the server, creates every table, loads the reference data — roles
and permissions, the settings, the leave types, the salary components and the
help guides — creates your company with its head office and a general shift,
and creates one administrator who can do everything. Then it writes
`storage/installed.json`, which is what closes setup.

Add this to `.env` afterwards, so the web installer cannot be reached at all
even if that file is ever lost:

```dotenv
INSTALL_LOCKED=true
```

**If you would rather set it up in a browser**, skip the command and open the
site: with no lock file every page redirects to `/install` and the wizard walks
through the same four steps. Finish it immediately — until you do, anybody who
can reach the address can complete it and become the administrator. That is
true of any web installer; the command above is how you avoid the window.

`storage/installed.json` belongs to the server, not the release: it is in
`.gitignore`, and a deployment must not lose it. Updating in place with
`git pull`, as *Shipping an update* below does, keeps it; a release-directory
deployment that builds a fresh checkout each time should symlink `storage/`
across releases, as it already must for uploads and logs. Setting
`INSTALL_LOCKED=true` makes the point moot.

The old route still works if you prefer it — `php artisan migrate --force` then
`php artisan db:seed --force` — but that seeds Beyond Sure's own companies and
branches as well, and creates `admin@beyondsure.example` with the password
`Password123!`, which you would then have to change before the server is
reachable from the internet.

### Permissions and the storage link

```bash
sudo chown -R deploy:www-data /var/www/hrms
sudo find /var/www/hrms -type d -exec chmod 755 {} \;
sudo find /var/www/hrms -type f -exec chmod 644 {} \;

# PHP-FPM writes logs, cached views, queued mail and uploads.
sudo chmod -R 775 /var/www/hrms/storage /var/www/hrms/bootstrap/cache
sudo chown -R www-data:www-data /var/www/hrms/storage /var/www/hrms/bootstrap/cache

sudo -u www-data php artisan storage:link
```

`storage:link` publishes logos and employee photographs. Identity documents,
experience letters and salary slips deliberately stay on the private disk and
are streamed through the application, so they are never reachable by URL.

---

## 4. nginx

```bash
sudo tee /etc/nginx/sites-available/hrms >/dev/null <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name hrms.example.in;

    # Certbot rewrites this block to redirect to HTTPS.
    root /var/www/hrms/public;
    index index.php;

    charset utf-8;
    client_max_body_size 16M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # A payroll run for a large company can take a while.
        fastcgi_read_timeout 120;
    }

    # Built assets are content-hashed, so they can be cached hard.
    location ^~ /build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ /\.(?!well-known).* { deny all; }

    error_page 404 /index.php;
    access_log /var/log/nginx/hrms-access.log;
    error_log  /var/log/nginx/hrms-error.log;
}
NGINX

sudo ln -s /etc/nginx/sites-available/hrms /etc/nginx/sites-enabled/hrms
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

The root is `public/`, never the project directory. Pointing nginx one level up
would expose `.env`.

`client_max_body_size` must be at least as large as PHP's `post_max_size`, or
nginx rejects a large upload with a 413 before PHP ever sees it.

### TLS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d hrms.example.in
```

Certbot rewrites the server block for HTTPS and installs a renewal timer.
Confirm it with `sudo systemctl status certbot.timer`.

**This step is mandatory, not optional hardening.** Browsers refuse
`navigator.geolocation` outside a secure context, so on plain HTTP every punch
reports that location is unavailable and attendance cannot be recorded at all
while *Require location to check in and out* is on.

---

## 5. The queue worker

Mailables implement `ShouldQueue`, so credentials, leave decisions, payslip
notifications and verification reminders are all handed to the queue. Without a
worker they sit in the `jobs` table forever.

```bash
sudo tee /etc/systemd/system/hrms-worker.service >/dev/null <<'UNIT'
[Unit]
Description=BySure HRMS queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/hrms
ExecStart=/usr/bin/php /var/www/hrms/artisan queue:work \
    --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=90

[Install]
WantedBy=multi-user.target
UNIT

sudo systemctl daemon-reload
sudo systemctl enable --now hrms-worker
sudo systemctl status hrms-worker
```

`--max-time=3600` retires the worker every hour and systemd starts a fresh one,
which keeps a long-lived process from holding stale configuration. `--tries=3`
retries a send three times before the job is written to `failed_jobs`; a
`JobFailed` listener records it in the application log as well.

Check for stuck mail with:

```bash
php artisan queue:failed          # what failed and why
php artisan queue:retry all       # try them again
```

### The scheduler

One entry runs everything the application does on a clock — the evening report
of punches made away from the branch, at the hour chosen under Settings,
Attendance, and the overnight crediting of monthly leave.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/hrms && php artisan schedule:run >> /dev/null 2>&1
```

Every minute is correct: Laravel decides internally what is due, so this one
line covers every scheduled task the application will ever have. Confirm it
with `php artisan schedule:list`, and test the report itself without waiting
for the hour:

```bash
sudo -u www-data php artisan hrms:location-report --dry-run
sudo -u www-data php artisan hrms:accrue-leave --dry-run
```

Without this entry the report is never sent — and because it is also sent on a
day with nothing to report, its silence looks exactly like a quiet day. Leave
that is earned monthly is not credited either, though nothing is lost: the
accrual ledger catches every missed month up as soon as the entry is in
place.

---

## 6. Cache the configuration

Do this last, after `.env` is final. Every one of these caches a file that
becomes stale the moment you change the thing it was built from.

```bash
cd /var/www/hrms
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
sudo -u deploy php artisan event:cache
```

**Once `config:cache` has run, `env()` outside a config file returns null.**
Change `.env` and you must re-run `config:cache` — nothing else picks it up.

---

## 7. Verify

```bash
# The application answers, and over TLS.
curl -I https://hrms.example.in

# The API is reachable and authentication works.
curl -s -X POST https://hrms.example.in/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=admin@beyondsure.example&password=<your-password>&device_name=smoke-test'

# Mail actually leaves the building: Administration → Notifications → any
# event → "Send a test", then
sudo journalctl -u hrms-worker -n 50
```

Then sign in and walk the three things that depend on the pieces above:

1. **Punch in.** The browser asks for location; the padlock must be closed or
   the request is refused before it starts.
2. **Upload a photograph** on an employee record. A failure here is `storage/`
   permissions or the `storage:link`.
3. **Download a payslip.** A blank or broken PDF means `gd` is missing.
4. **Run `php artisan hrms:location-report --dry-run`.** It prints the day's
   out-of-range punches without sending anything, which proves the scheduler's
   work will succeed when it fires.

---

## Shipping an update

```bash
cd /var/www/hrms

sudo -u deploy php artisan down --retry=60

sudo -u deploy git pull origin main
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy npm ci && sudo -u deploy npm run build

sudo -u deploy php artisan migrate --force

# Permissions are a catalogue in app/Support/Permissions.php: this
# re-syncs any that a release added, and is safe to run every time.
sudo -u deploy php artisan db:seed --class=RolePermissionSeeder --force

sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
sudo -u deploy php artisan event:cache

# The worker holds the old code in memory until it is told otherwise.
sudo systemctl restart hrms-worker
sudo systemctl reload php8.3-fpm

sudo -u deploy php artisan up
```

Two of those are the ones people forget. Skipping the worker restart leaves the
old code sending your new emails. Skipping `db:seed --class=RolePermissionSeeder`
leaves a permission a new screen checks for missing, so the screen 403s for
everybody but the super admin.

A release that adds or renames a help guide also wants
`php artisan db:seed --class=HelpArticleSeeder --force`.

### Rolling back

Migrations in this project are additive, so a rollback is usually just the
code:

```bash
sudo -u deploy git checkout <previous-tag>
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy php artisan config:cache && sudo -u deploy php artisan route:cache
sudo systemctl restart hrms-worker
```

If the release migrated the schema, restore the backup you took first —
`php artisan migrate:rollback` undoes structure, not the data a migration
transformed.

---

## Backups

```bash
sudo tee /usr/local/bin/hrms-backup >/dev/null <<'SH'
#!/bin/bash
set -euo pipefail
STAMP=$(date +%F-%H%M)
DEST=/var/backups/hrms
mkdir -p "$DEST"

mysqldump --single-transaction --quick hrms_bysure | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/storage-$STAMP.tar.gz" -C /var/www/hrms storage/app

find "$DEST" -type f -mtime +30 -delete
SH
sudo chmod +x /usr/local/bin/hrms-backup
```

Run it nightly from root's crontab: `30 1 * * * /usr/local/bin/hrms-backup`.

`storage/app` is not optional in a backup. Identity documents, experience
letters, leave attachments and employee photographs live there and exist
nowhere else — a database restore without them leaves every record pointing at
a missing file. Copy both off the machine.

---

## Where to look when something is wrong

| Symptom | First place to look |
| --- | --- |
| 500 with no detail | `storage/logs/laravel.log`, then `/var/log/nginx/hrms-error.log` |
| A change to `.env` did nothing | `php artisan config:cache` was not re-run |
| No email arriving | `systemctl status hrms-worker`, then `php artisan queue:failed` |
| "Location is unavailable" on every punch | The site is not on HTTPS, or the browser has location blocked for it |
| 413 on an upload | `client_max_body_size` in nginx is below `post_max_size` in PHP |
| Blank or broken payslip PDF | `php8.3-gd` missing, or a logo the renderer cannot read |
| A new screen 403s for everyone | `db:seed --class=RolePermissionSeeder` was skipped |
| The evening location report never arrives | The `schedule:run` cron entry is missing, or the queue worker is down |
| Monthly leave is not being credited | The same `schedule:run` entry. Run `php artisan hrms:accrue-leave` by hand to catch up — no month is lost |
| Every punch is flagged as away from the branch | The branch's coordinates are wrong — latitude and longitude the wrong way round puts the office in the sea |
| 419 on every form after a deploy | `APP_KEY` changed, invalidating existing sessions |
| Permission denied writing anything | `storage/` and `bootstrap/cache` are not owned by `www-data` |

Log level is `LOG_LEVEL=debug` in the example file — set it to `warning` in
production so `laravel.log` stays readable. Logs rotate daily by default
(`LOG_CHANNEL=daily`, 14 files kept).

---

## Hardening worth doing

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
sudo apt install -y fail2ban unattended-upgrades
```

Beyond that: keep MySQL bound to `127.0.0.1` (the Ubuntu default), give the
`hrms` database user privileges on its own schema only, disable password SSH in
favour of keys, and make sure `.env` is `640 deploy:www-data` — readable by
PHP-FPM, nobody else.
