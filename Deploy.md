# Bio-Frappe Production Deployment Guide

**Bio-Frappe** is a high-performance, multi-tenant biometric attendance middleware that bridges physical attendance hardware (ZKTeco standalone TCP/IP, eSSL, ADMS, and eBio Server) directly to **Frappe HR v15 (ERPNext)**.

This guide covers complete production deployment on Ubuntu (22.04 / 24.04 LTS), including daemonized scheduling via Supervisor (**no crontab required**), concurrent batch synchronization for 300+ punch morning/evening surges, and multi-tenant setup.

---

## 1. Server Prerequisites

Install system dependencies on your production server (e.g. VPS, DigitalOcean, Hetzner, AWS EC2):

```bash
# 1. Update system packages
sudo apt update && sudo apt upgrade -y

# 2. Install Web Server (Nginx)
sudo apt install nginx -y

# 3. Install Database (PostgreSQL or MariaDB/MySQL)
sudo apt install postgresql postgresql-contrib -y
# OR: sudo apt install mariadb-server -y

# 4. Install PHP 8.2+ and required extensions
sudo apt install php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip php8.2-intl php8.2-redis unzip -y

# 5. Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 6. Install Supervisor (Process Manager for Queues & Scheduler Daemon)
sudo apt install supervisor -y
```

---

## 2. Step-by-Step Installation

### Step 1: Clone Repository
```bash
sudo mkdir -p /var/www/bio-frappe
sudo chown -R $USER:$USER /var/www/bio-frappe
cd /var/www/bio-frappe

git clone https://github.com/Af1ah/bio-frappe.git .
```

### Step 2: Install Composer Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### Step 3: Configure Environment (.env)
```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Set the essential environment variables:
```env
APP_NAME=Bio-Frappe
APP_ENV=production
APP_DEBUG=false
APP_URL=https://attendance.yourdomain.com
CENTRAL_DOMAIN=attendance.yourdomain.com

# Database Settings
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bio_frappe_central
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Default Frappe HR v15 Connection (Fallback if not overridden per tenant organisation)
FRAPPE_HR_URL=https://hrm.yourdomain.com
FRAPPE_HR_API_KEY=your_frappe_api_key
FRAPPE_HR_API_SECRET=your_frappe_api_secret

# Queue Connection (Always use database or redis in production, NEVER sync!)
QUEUE_CONNECTION=database
```

### Step 4: Run Central and Tenant Migrations
Because Bio-Frappe uses multi-tenant schema/database isolation (`stancl/tenancy`):

```bash
# 1. Migrate Central Database
php artisan migrate --force

# 2. Migrate All Tenant Databases
php artisan tenants:migrate --force

# 3. Create initial Master Admin User
php artisan make:filament-user
```

### Step 5: Storage Link & Directory Permissions
```bash
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Step 6: Caches and Optimization
```bash
php artisan optimize
php artisan filament:optimize
```

---

## 3. Daemonized Supervisor Setup (Skipping System Crontab)

Traditional Laravel deployments require adding `* * * * * php artisan schedule:run >> /dev/null 2>&1` to `crontab -e`. 

**In Bio-Frappe, you can completely skip crontab** by running Laravel's built-in scheduler worker daemon:
```bash
php artisan schedule:work
```
When monitored by Supervisor, both the queue worker and the scheduler run continuously as background daemons, auto-restarting on crashes or server reboots.

### Create the Supervisor Configuration
Create `/etc/supervisor/conf.d/bio-frappe.conf`:

```bash
sudo nano /etc/supervisor/conf.d/bio-frappe.conf
```

Paste the following configuration (adjust path `/var/www/bio-frappe` and user if needed):

```ini
; ==============================================================================
; 1. Bio-Frappe Background Queue Worker
; Processes hardware polling, batch punches, and Frappe HR API requests
; ==============================================================================
[program:bio-frappe-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bio-frappe/artisan queue:work --sleep=2 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/bio-frappe/storage/logs/worker.log
stopwaitsecs=3600

; ==============================================================================
; 2. Bio-Frappe Scheduler Daemon (Completely replaces system crontab!)
; Executes attendance:auto-sync every minute in the background
; ==============================================================================
[program:bio-frappe-scheduler]
process_name=%(program_name)s
command=php /var/www/bio-frappe/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bio-frappe/storage/logs/scheduler.log
stopwaitsecs=60
```

### Start Supervisor Daemons
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

Verify that both `bio-frappe-worker` and `bio-frappe-scheduler` show `RUNNING`.

---

## 4. High-Capacity Batched Sync (300+ Punch Surges)

During morning and evening shifts, hundreds of employees punch within minutes. 

Bio-Frappe handles this with a high-concurrency, streaming pipeline:
1. **Streaming Memory Protection:** `attendance:auto-sync` uses `chunkById(50)` to read unsynced logs, preventing PHP out-of-memory errors even with tens of thousands of records.
2. **Parallel Multi-cURL Pooling:** Requests are dispatched to Frappe HR v15 using `Http::pool()` with 10–25 parallel connections. 
   - *Result:* 300 punches sync in **~3–5 seconds** instead of 60–90 seconds sequentially.
3. **Idempotent Deduplication:** If a punch was already recorded in Frappe HR, Frappe's `"already has a log with the same timestamp"` response is caught, linked, and marked as successfully synced without throwing errors.
4. **Shift Type Auto-Attendance Trigger:** After pushing check-ins, `FrappeHrService::triggerAutoAttendance()` automatically updates `last_sync_of_checkin` and executes `process_auto_attendance` on Frappe HR Shift Types, instantly generating Present/Absent/Half-day attendance records.

### Manual or On-Demand Sync Command
You can also run or test the sync manually anytime:
```bash
# Sync new punches and trigger Frappe HR auto attendance
php artisan attendance:auto-sync --trigger-attendance

# Poll online IP biometric devices directly, sync, and trigger attendance
php artisan attendance:auto-sync --fetch-devices --trigger-attendance

# Force resync of all unsynced punches
php artisan attendance:auto-sync --force
```

---

## 5. Frappe HR v15 Configuration Checklist

To ensure seamless automatic attendance generation in Frappe HR v15:

1. **API Keys:**
   - In Frappe HR, go to **User > API Access** (or create a dedicated user e.g. `apiuser@yourdomain.com`).
   - Generate API Key and Secret. Assign the user roles: **HR Manager** or **HR User**.
2. **Employee Mapping:**
   - In Frappe HR, open each **Employee** record.
   - Navigate to **Attendance and Leaves**.
   - Fill in **Attendance Device ID (Biometric/RF tag ID)** with the employee's biometric PIN (e.g. `1002`).
3. **Shift Type Configuration:**
   - In Frappe HR, open **Shift Type** (e.g. *General Shift*).
   - Check **Enable Auto Attendance**.
   - Set **Determine Check-in / Check-out based on** to `Alternating entries` (or `Strictly based on Log Type`).
   - Set **Working Hours Calculation Based On** to `First Check-in and Last Check-out`.
   - Set **Process Attendance After** to the shift end time or leave standard buffer.

---

## 6. Nginx Web Server Configuration

Create `/etc/nginx/sites-available/bio-frappe`:

```nginx
server {
    listen 80;
    listen [::]:80;

    # Replace with your central domain and wildcard for tenants
    server_name attendance.yourdomain.com *.attendance.yourdomain.com;
    
    root /var/www/bio-frappe/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the configuration and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/bio-frappe /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

Install SSL with Let's Encrypt (wildcard):
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d attendance.yourdomain.com -d *.attendance.yourdomain.com
```

---

## 7. Production Checklist

- [ ] `.env` has `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] `QUEUE_CONNECTION=database` or `redis`.
- [ ] Supervisor is active with `bio-frappe-worker` and `bio-frappe-scheduler`.
- [ ] `php artisan storage:link` has been created and permissions assigned to `www-data`.
- [ ] Frappe HR API User has appropriate permissions for `Employee Checkin` and `Shift Type`.
- [ ] Frappe HR employees have `attendance_device_id` populated matching device PINs.
- [ ] Shift Types have **Enable Auto Attendance** checked.

---

## 8. Single Docker Deployment (Docker Compose)

Bio-Frappe includes a single, unified `Dockerfile` that builds an optimized PHP 8.2 runtime equipped with PostgreSQL/MySQL drivers, ZKTeco socket communication extensions (`sockets`), SOAP, Redis, and Supervisor.

### Architecture
Using `docker-compose.yml`, all three application services build from the single root `Dockerfile`:
- **`app`**: Web application serving Filament and API webhooks on port 8000.
- **`worker`**: Background queue worker processing batch punches and Frappe HR API synchronization.
- **`scheduler`**: Scheduler daemon executing `attendance:auto-sync` every minute (no host crontab required).
- **`pgsql`**: Isolated PostgreSQL 15 database container.

### Step-by-Step Commands

1. **Build and Launch All Services:**
   ```bash
   docker compose up -d --build
   ```

2. **Initialize Database and Migrations:**
   ```bash
   # Central database migrations
   docker compose exec app php artisan migrate --force

   # Multi-tenant schema/database migrations
   docker compose exec app php artisan tenants:migrate --force

   # Create initial Master Admin user
   docker compose exec app php artisan make:filament-user
   ```

3. **Monitor Running Services and Logs:**
   ```bash
   # View container status
   docker compose ps

   # Tail web application logs
   docker compose logs -f app

   # Tail queue worker logs (punch batches & hardware commands)
   docker compose logs -f worker

   # Tail scheduler logs (auto-sync execution)
   docker compose logs -f scheduler
   ```

4. **Trigger Manual Punch Sync inside Docker:**
   ```bash
   docker compose exec app php artisan attendance:auto-sync --trigger-attendance
   ```

5. **Stop or Restart Containers:**
   ```bash
   # Stop services
   docker compose down

   # Restart specific service (e.g. worker after code changes)
   docker compose restart worker
   ```

