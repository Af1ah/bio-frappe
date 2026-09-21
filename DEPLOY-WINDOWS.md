# Bio-Frappe native Windows hosting

This deployment runs directly on Windows: PHP 8.3 CGI serves Laravel through Caddy, while PostgreSQL 18 stores the data. Docker is not used.

## Configuration included in this repository

- `.env` uses production settings, PostgreSQL on `127.0.0.1:5432`, database `bio-frappe`, and application account `essl`.
- `Caddyfile` serves only the Laravel `public` folder over `https://localhost` and forwards PHP requests to `127.0.0.1:9000`.
- `scripts/windows/Start-BioFrappe.ps1` starts PHP-CGI, the queue worker, scheduler, and reloads Caddy.
- `scripts/windows/Initialize-Postgres.ps1` creates the `essl` role and `bio-frappe` database without putting the PostgreSQL administrator password in a file or command history.

## One-time install and build

Open an elevated PowerShell in the repository and run:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate --force
```

PHP 8.3 is already installed at `C:\PHP\8.3`. Confirm that `php -m` includes `pdo_pgsql` and `pgsql`. If PHP prints an error for `pdo_firebird`, remove or comment out `extension=pdo_firebird` in `C:\PHP\8.3\php.ini`; it is unrelated to this app.

The Composer platform is pinned to PHP 8.3.33 so its lockfile cannot select packages that need PHP 8.4 or newer.

Caddy must be available at `C:\caddy\caddy.exe`. It is already present on this machine.

## PostgreSQL initialization

The configured PostgreSQL role/database are `essl` / `bio-frappe`. If they do not already exist, creating them requires the password for the existing PostgreSQL `postgres` administrator. Run the following and enter that administrator password only when PowerShell prompts for it:

```powershell
.\scripts\windows\Initialize-Postgres.ps1 -PostgresAdminPassword (Read-Host 'PostgreSQL postgres password' -AsSecureString)
```

Then run Laravel's central and tenant migrations:

```powershell
php artisan migrate --force
php artisan tenants:migrate --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize
```

Create the first administrator interactively:

```powershell
php artisan make:filament-user
```

## Start and reload hosting

Run the hosting launcher from the repository. It starts Caddy if it is not already running; otherwise it reloads the active Caddy process safely.

```powershell
.\scripts\windows\Start-BioFrappe.ps1
```

Open [https://localhost](https://localhost). Caddy generates a local HTTPS certificate; accept or trust its local CA only on this development/host machine. For an internet-facing deployment, replace `localhost` in both `.env` (`APP_URL`, `CENTRAL_DOMAIN`) and `Caddyfile` with the real DNS name before reloading Caddy.

## Run automatically after a reboot

Use an elevated PowerShell to create one startup task that launches Caddy, PHP-CGI, the queue worker, and the scheduler after every reboot:

```powershell
$project = 'C:\Users\Aflah\projects\bio-frappe'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$project\scripts\windows\Start-BioFrappe.ps1`""
$trigger = New-ScheduledTaskTrigger -AtStartup
Register-ScheduledTask -TaskName 'Bio-Frappe Hosting' -Action $action -Trigger $trigger -RunLevel Highest -Force
```

## Security notes

- `.env` is ignored by Git. Keep the supplied application credentials out of commits and do not expose PostgreSQL port 5432 to the network.
- `APP_DEBUG=false` is required for hosting and is already set.
- Keep `FRAPPE_HR_API_KEY` and `FRAPPE_HR_API_SECRET` empty until real values are supplied; add them only to `.env`.
- Configure a real domain and Caddy-managed HTTPS before exposing the service to the internet.
