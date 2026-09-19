# Bio-Frappe

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-3.x-FFA500?style=for-the-badge&logo=livewire&logoColor=white)](https://filamentphp.com/)
[![Frappe HR](https://img.shields.io/badge/Frappe_HR-v15-0089FF?style=for-the-badge&logo=frappe&logoColor=white)](https://frappehr.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

**Bio-Frappe** is a high-performance, multi-tenant biometric attendance middleware designed to seamlessly connect physical biometric devices (ZKTeco standalone TCP/IP, eSSL, ADMS, and eBio Server) directly to **Frappe HR v15 (ERPNext)**.

Built on Laravel 12 and Filament v3, Bio-Frappe captures real-time biometric punches, batches and syncs them concurrently via the Frappe HR REST API, and automatically triggers Shift Type attendance calculation—ensuring zero UI lag and eliminating server bottlenecks during peak rush hours (300+ punches).

---

## 🚀 Key Features

### 🏢 Direct Frappe HR v15 / ERPNext Integration
- **Automated Employee Checkin:** Pushes punches directly to Frappe HR's `Employee Checkin` doctype via REST API.
- **Native Device Mapping:** Seamlessly maps biometric user PINs to Frappe HR's standard `attendance_device_id` field under *HR > Employee > Attendance and Leaves*.
- **Shift Type Auto-Attendance Trigger:** Interacts directly with Frappe HR Shift Types (`process_auto_attendance`), updating `last_sync_of_checkin` and generating daily attendance records (Present, Half-Day, Absent) in real time.
- **Idempotent Deduplication:** Gracefully resolves duplicate punches without throwing errors or creating duplicate records in Frappe HR.

### ⚡ High-Capacity Batched Concurrency (Rush-Hour Ready)
- **Multi-cURL Asynchronous Pooling:** Leverages `Http::pool()` to process 10–25 punch requests concurrently. A surge of 300+ morning punches finishes in **~3–5 seconds** rather than minutes.
- **Memory-Safe Streaming:** Uses `chunkById(50)` to stream backlog logs, preventing PHP out-of-memory errors.
- **Daemonized Scheduler (No Crontab Needed):** Runs Laravel's `php artisan schedule:work` under Supervisor, automatically executing `attendance:auto-sync` every minute without needing system crontab.

### 🔌 Diverse Biometric Hardware Support
- **ZKTeco Standalone (TCP/IP):** Direct network socket communication over port 4370 (device info, user sync, punch retrieval, reboot).
- **eSSL / ADMS Protocols:** Supports push-mode biometric devices transmitting over HTTP `/iclock/cdata`.
- **eBio Server (SOAP 1.1 Web Services):** High-speed asynchronous webhooks and SOAP operations for enterprise deployments.

### 🌐 Multi-Tenancy Architecture
- Powered by `stancl/tenancy` with PostgreSQL schema / database isolation.
- **Independent Organisation Domains:** Each organisation gets an isolated subdomain (e.g. `tenant1.attendance.yourdomain.com`).
- **Organisation-Specific Settings:** Each tenant can specify their own Frappe HR instance URL, API keys, shifts, and biometric devices.
- **Zero Cross-Data Bleed:** Decoupled routing between Central Master Admin and Tenant Admin panels.

### 💬 Instant WhatsApp Alerts (WAHA API)
- Optional real-time notification engine sending instant WhatsApp check-in / check-out alerts to employees upon punch recognition.

---

## 🛠️ Technology Stack

- **Backend:** [Laravel 12](https://laravel.com/) (PHP 8.2+)
- **Admin Panels:** [Filament v3](https://filamentphp.com/) (Livewire 3)
- **Target ERP/HRMS:** [Frappe HR v15 / ERPNext](https://frappehr.com/)
- **Multi-Tenancy:** [Stancl/Tenancy](https://tenancyforlaravel.com/)
- **Database:** PostgreSQL (multi-tenant isolation) or MySQL
- **Process Manager:** Supervisor (`queue:work` + `schedule:work`)
- **Queue System:** Database or Redis
- **Hardware Protocols:** ZKTeco Standalone TCP/IP, ADMS, eBioServer SOAP API

---

## 📦 Quick Start & Installation

### Local Development

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Af1ah/bio-frappe.git
   cd bio-frappe
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure Frappe HR credentials in `.env`:**
   ```env
   FRAPPE_HR_URL=https://hrm.yourdomain.com
   FRAPPE_HR_API_KEY=your_api_key
   FRAPPE_HR_API_SECRET=your_api_secret
   QUEUE_CONNECTION=database
   ```

5. **Run Migrations:**
   ```bash
   php artisan migrate
   php artisan tenants:migrate
   php artisan make:filament-user
   ```

6. **Start the local development server & queue worker:**
   ```bash
   php artisan serve
   php artisan queue:work
   ```

---

## ⏱️ Auto-Sync & Manual Commands

Bio-Frappe includes an automated command to poll devices, sync checkins to Frappe HR, and trigger auto-attendance:

```bash
# Fetch online devices, push unsynced checkins, and trigger Shift Type attendance:
php artisan attendance:auto-sync --fetch-devices --trigger-attendance

# Resync all unsynced punches in memory-safe batches:
php artisan attendance:auto-sync --trigger-attendance

# Force resync:
php artisan attendance:auto-sync --force
```

---

## 🚀 Production Deployment

For complete, step-by-step production deployment instructions using Nginx and Supervisor daemons (without system crontab), please refer to:

👉 **[Production Deployment Guide (Deploy.md)](./Deploy.md)**

---

## 🧪 Testing

Bio-Frappe is backed by an automated test suite covering Frappe HR API syncing, multi-cURL batch pooling, device commands, and webhooks:

```bash
php artisan test
```

---

## 📝 License

Bio-Frappe is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
