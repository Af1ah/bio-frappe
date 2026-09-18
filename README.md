# Bio-Notifier

## Project reference: V1 and V2

The approved direction extends Bio-Notifier with a Go ADMS device gateway, reliable attendance, leaves and payroll. V1 covers device migration and basic payroll; V2 covers deeper payroll/currency policies and general improvements. These documents describe planned work, not features already shipped.

- [Migration plan and four checkpoints](reference/docs/migration-plan.md)
- [Device API, tenant isolation, HTTP ingress and recovery](reference/docs/device-api-and-tenancy.md)
- [Attendance, leave, holidays, OT and payroll automation](reference/docs/attendance-leave-and-payroll.md)
- [Coding standards and agent rules](AGENTS.md)

The existing feature/setup material below describes the legacy implementation and may contain outdated capability or performance claims. Use the reference documents and installed dependencies when planning the migration.


Bio-Notifier is a powerful, modern, multi-tenant middleware designed to seamlessly bridge the gap between physical biometric attendance hardware (eSSL / eBio Server) and real-time communication platforms. 

Built on Laravel and the Filament admin panel, it acts as a centralized notification engine that intercepts attendance punches and instantly alerts employees via WhatsApp. Furthermore, it aggregates this data to compile comprehensive attendance reports.

## 🚀 Key Features

### 💬 Real-Time WhatsApp Notifications (WAHA API)
- **eSSL / eBio Server Integration:** Captures real-time attendance webhooks pushed directly from eSSL and eBio Servers.
- **Instant Alerts:** Automatically triggers WhatsApp messages to employees the moment they punch in or punch out on the biometric device.
- **WAHA API Support:** Fully integrated with the WhatsApp HTTP API (WAHA) for stable, session-based messaging.

### 📊 Report Compilation & Analytics
- **Comprehensive Reporting Dashboard:** Database-driven, highly configurable reporting system.
- **Automated Compilations:** Generates attendance reports including working hours, present/absent statistics, and late marks.
- **Filament Integration:** Data visualizations and tables built natively into the beautiful Filament admin dashboard.

### 🏢 Multi-Tenancy Architecture (Subdomain Routing)
- Robust multi-tenant environment powered by `stancl/tenancy` ensuring complete PostgreSQL database isolation.
- **Automated Subdomain Provisioning:** Instantly spins up a new isolated domain (e.g., `company.bionotifier.com/admin`) upon company creation.
- **Zero Cross-Data Bleed:** Central Master Admin panel and Tenant Admin panels operate on completely decoupled routing networks.

### 🔌 Hardware Integration & Full SOAP Device Sync
- **eBio Server Webhooks (Fire & Forget):** Asynchronous queue-based architecture capable of handling thousands of simultaneous device pings in <10ms to prevent device lockups.
- **Biometric Pushing & Pulling:** Push employee details to devices natively.
- **Advanced Device Commands:** Supports deleting users from specific devices, setting employee expiration dates, and triggering remote device fetches/reboots via SOAP API integration.
- **Biometric Face & Fingerprint Uploads:** Support for extracting and syncing Base64 biometric templates (Face ID & Fingerprints) across mixed hardware.
- **ZKTeco / Hikvision / Matrix Support:** Direct fallback integration capabilities for pushing user data back to legacy hardware.

## 🛠️ Technology Stack
- **Framework:** [Laravel](https://laravel.com/) (PHP 8.2+)
- **Admin Interface:** [Filament v3/v4](https://filamentphp.com/)
- **Multi-Tenancy:** [Stancl/Tenancy](https://tenancyforlaravel.com/)
- **Database:** PostgreSQL (with schema isolation) / MySQL
- **WhatsApp API:** WAHA (WhatsApp HTTP API)

## ⚙️ Setup & Installation

For production deployments, please refer to the detailed [Deploy.md](./Deploy.md) guide included in this repository. 
For a complete architectural overview and implementation guidelines, refer to the [Guide.md](./Guide.md) file.

## ⚙️ Running Locally (Developer Quick Start)

Bio-Notifier consists of 4 services that need to run concurrently:

| Service | Directory | Command | Port | Purpose |
|---|---|---|---|---|
| **1. Database (PostgreSQL)** | Root | `docker compose up -d pgsql` (or local PostgreSQL) | `5433` (or `5432`) | Central and tenant databases |
| **2. Web App (Laravel)** | Root | `php artisan serve --port=8000` | `8000` | Admin panels and internal APIs |
| **3. Queue Worker** | Root | `php artisan queue:work database --sleep=1 --tries=3 --timeout=180` | - | Processes attendance punches, notifications, commands |
| **4. ADMS Device Gateway** | `gateway/` | `go run .` | `8080` (devices), `8081` (internal API) | Biometric hardware communication & durable buffering |

### Step-by-Step Setup

1. **Environment & Dependencies**
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   ```
   Ensure `.env` has your database credentials, `DEVICE_GATEWAY_TOKEN`, and `DEVICE_GATEWAY_URL=http://127.0.0.1:8081`.

2. **Database Migrations**
   ```bash
   php artisan migrate
   php artisan tenants:migrate
   ```

3. **Issue Gateway Token**
   Generate a scoped Sanctum token for the Go gateway:
   ```bash
   php artisan device-gateway:issue-token admin@gmail.com
   ```
   Copy the output token into `.env` as `LARAVEL_GATEWAY_TOKEN`.

4. **Start the Services**
   - **Terminal 1 (Laravel App):**
     ```bash
     php artisan serve --port=8000
     ```
   - **Terminal 2 (Queue Worker):**
     ```bash
     php artisan queue:work database --sleep=1 --tries=3 --timeout=180
     ```
   - **Terminal 3 (Go ADMS Gateway):**
     ```bash
     cd gateway
     ADMS_MANAGEMENT_TOKEN="bio-notifier-dev-only-change-me" \
     LARAVEL_INTERNAL_URL="http://127.0.0.1:8000" \
     LARAVEL_GATEWAY_TOKEN="<your_token>" \
     ADMS_STORE_PATH="../storage/gateway.db" \
     ADMS_DEVICE_ADDR=":8080" \
     ADMS_MANAGEMENT_ADDR=":8081" \
     go run .
     ```

### Testing Endpoints

- **Master Admin Panel:** `http://127.0.0.1:8000/master`
- **Tenant Admin Panel:** `http://127.0.0.1:8000/{tenant}/admin` (e.g. `http://127.0.0.1:8000/secumax/admin`)
- **ADMS Device Ingress:** `http://<YOUR_IP>:8080/iclock/cdata` (Health check: `http://127.0.0.1:8080/health`)
- **Gateway Management API:** `http://127.0.0.1:8081/internal/v1/...` (Private, authenticated)

## 📝 License
This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
