# Production Deployment Guide

Since we have merged everything into a single, unified application, deploying to a fresh production instance (like a VPS or Laravel Forge) is now incredibly simple. 

You no longer need to worry about custom packages, symlinks, or private repositories. Your entire app lives in one place on GitHub: `https://github.com/Af1ah/bio-notifier`.

## Prerequisites

On your fresh production server (e.g. Ubuntu 22.04/24.04), install the required dependencies one by one:

**1. Update system packages:**
```bash
sudo apt update && sudo apt upgrade -y
```

**2. Install Web Server (Nginx):**
```bash
sudo apt install nginx -y
```

**3. Install Database (PostgreSQL or MySQL):**
```bash
# For PostgreSQL
sudo apt install postgresql postgresql-contrib -y

# OR for MySQL/MariaDB
sudo apt install mariadb-server -y
```

**4. Install PHP and Required Extensions (adjust version 8.2+ as needed):**
```bash
sudo apt install php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip unzip -y
```

**5. Install Composer:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**6. Install Supervisor (for Background Queues & Gateway):**
```bash
sudo apt install supervisor -y
```

**7. Install Go (1.22+ for ADMS Gateway):**
```bash
sudo apt install golang-go -y
```

## Step-by-Step Deployment

### 1. Clone the Repository
SSH into your production server and navigate to your web directory (e.g. `/var/www/html`), then clone your repository:
```bash
git clone https://github.com/Af1ah/bio-notifier.git .
```

### 2. Install Dependencies
Install all required PHP packages optimized for production:
```bash
composer install --optimize-autoloader --no-dev
```

### 3. Environment Configuration
Copy the example environment file and generate your application key:
```bash
cp .env.example .env
php artisan key:generate
```

Now, open the `.env` file using a text editor like `nano`:
```bash
nano .env
```
Update your database credentials to match your production MySQL database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_production_db_name
DB_USERNAME=your_production_db_user
DB_PASSWORD=your_production_db_password
```

> [!IMPORTANT]
> Make sure you change `APP_ENV=local` to `APP_ENV=production` and `APP_DEBUG=true` to `APP_DEBUG=false` in your `.env` file!

### 4. Run Migrations & Setup Database
Because this is a multi-tenant system, you must run migrations for BOTH the central database (Master Admin) and the tenant databases.

1. **Migrate the central database:**
```bash
php artisan migrate --force
```

2. **Migrate all tenant databases:**
```bash
php artisan tenants:migrate --force
```

3. **Create your initial Master Admin user:**
```bash
php artisan make:filament-user
```

4. **Issue Go ADMS Gateway Token:**
```bash
php artisan device-gateway:issue-token <admin-email>
```
Store the resulting token in `.env` as `LARAVEL_GATEWAY_TOKEN`.

### 5. Optimize Caches
To ensure your production application runs as fast as possible, cache your configurations, routes, and views:
```bash
php artisan optimize
php artisan filament:optimize
```

### 6. Storage Link & Permissions
Ensure Nginx/Apache has permission to read and write to the storage folders, and link the public storage directory:
```bash
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 7. Configure Background Services (Queue Worker & Go ADMS Gateway)

Both the Laravel queue worker and the Go ADMS gateway should be managed by Supervisor.

#### A. Build the Go ADMS Gateway Binary
```bash
cd /var/www/html/gateway
go build -o /usr/local/bin/adms-gateway .
```

#### B. Supervisor Configuration
Create the supervisor config file:
```bash
sudo nano /etc/supervisor/conf.d/bio-notifier.conf
```

Add both programs (replace `/var/www/html` and environment values with your actual settings):
```ini
[program:bio-notifier-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work database --sleep=1 --tries=3 --timeout=180
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log

[program:bio-notifier-gateway]
command=/usr/local/bin/adms-gateway
environment=ADMS_MANAGEMENT_TOKEN="%(ENV_DEVICE_GATEWAY_TOKEN)s",LARAVEL_INTERNAL_URL="http://127.0.0.1:80",LARAVEL_GATEWAY_TOKEN="%(ENV_LARAVEL_GATEWAY_TOKEN)s",ADMS_STORE_PATH="/var/www/html/storage/gateway.db",ADMS_DEVICE_ADDR=":8080",ADMS_MANAGEMENT_ADDR=":8081"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/gateway.log
```

#### C. Start Services
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bio-notifier-worker:*
sudo supervisorctl start bio-notifier-gateway:*
```

### 8. Web Server Configuration (Nginx & Multi-Tenancy)

Bio-Notifier uses an isolated domain-based routing system for tenants. To allow clients to have their own domains (like `client1.noti.ariise.cloud`) without breaking other apps on your server, you need to set up a wildcard properly in Nginx.

**DNS Configuration in your Registrar:**
1. Point an A-record for your base domain (e.g. `noti.ariise.cloud`) to your server IP.
2. Point a Wildcard A-record (e.g. `*.noti.ariise.cloud`) to your server IP.

**Nginx Setup:**
1. Create a new Nginx server block configuration:
```bash
sudo nano /etc/nginx/sites-available/bio-notifier
```

2. Add the following standard Nginx setup. Ensure you explicitly list the wildcard in `server_name` so Nginx routes all tenant traffic here!

```nginx
server {
    listen 80;
    listen [::]:80;

    # Explicitly catch the master domain AND all subdomains
    server_name noti.ariise.cloud *.noti.ariise.cloud;
    
    root /var/www/html/public; # IMPORTANT: This MUST point to the /public directory!

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
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # Ensure PHP version matches what you installed
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

3. Enable the site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/bio-notifier /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 9. Environment Variables (.env)

Ensure your `.env` contains the correct routing and gateway settings:

```env
APP_URL=https://noti.ariise.cloud
CENTRAL_DOMAIN=noti.ariise.cloud

# Go ADMS Gateway configuration
DEVICE_GATEWAY_URL=http://127.0.0.1:8081
DEVICE_GATEWAY_TOKEN=your-random-shared-secret
LARAVEL_GATEWAY_TOKEN=your-sanctum-token-issued-by-artisan
ADMS_PORT=8080
ADMS_DEVICE_HOST=noti.ariise.cloud
ADMS_DEVICE_PORT=8080
```
- `DEVICE_GATEWAY_URL`: Internal URL Laravel uses to send commands to the Go gateway (port `8081`).
- `DEVICE_GATEWAY_TOKEN`: Shared secret for internal Laravel-to-Gateway requests.
- `LARAVEL_GATEWAY_TOKEN`: Scoped Sanctum token Go uses to deliver punches/events back to Laravel.

## Configuring the Attendance Devices

On physical biometric devices (ZKTeco / eSSL), navigate to **Cloud Server Settings** / **ADMS Settings**:
- **Server Address:** Your server IP or domain (e.g. `noti.ariise.cloud`)
- **Server Port:** `8080` (or `80`/`443` if routed through Nginx reverse proxy)
- **Server URL:** Leave blank or `/` (do NOT append `/api` or `/iclock`)

## Docker Compose Support (All-in-One)

The project includes `compose.yaml` to spin up all 5 services simultaneously:
1. `pgsql` (PostgreSQL 18 database)
2. `adms-gateway` (Go standalone ADMS device server on port 8080)
3. `laravel.test` (Laravel web application on port 80)
4. `queue` (Laravel database queue worker)
5. `scheduler` (Laravel task scheduler)

### Quick Run with Docker Compose:
```bash
# 1. Setup environment
cp .env.example .env

# 2. Start services
docker compose up -d

# 3. Initialize database & token
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan tenants:migrate
docker compose exec laravel.test php artisan device-gateway:issue-token <admin-email>

# 4. Stop services
docker compose down
```
