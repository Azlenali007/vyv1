# ApexSMM Panel (Core PHP 8.2+ & MySQL)

Production-grade Social Media Marketing (SMM) Panel built strictly with **Core PHP 8.2+**, **MySQL / MariaDB (PDO)**, **HTML5**, **Tailwind CSS**, **Alpine.js**, **Vanilla JavaScript**, **Chart.js**, and **PHP cURL**.

## Technology Stack
- **Backend:** Core PHP 8.2+ (Strict PDO prepared statements, cURL API client, bcrypt password hashing, CSRF tokens, session hardening)
- **Database:** MySQL / MariaDB (20 normalized tables with foreign keys, indexes, and transactional `FOR UPDATE` locking)
- **Frontend:** HTML5, Tailwind CSS, Alpine.js, Vanilla JavaScript, Chart.js, inline SVG icons

## Setup & Installation
1. Import the database schema and structural seed:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/seed.sql
   ```
2. Configure environment variables in `.env` (see `.env.example`):
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=smm_panel
   DB_USER=smm_user
   DB_PASS=smm_pass_2026
   ```
3. Run locally with PHP's built-in server:
   ```bash
   php -S 0.0.0.0:3000 router.php
   ```
4. Default Admin Login (`/admin/login.php`):
   - **Username:** `admin`
   - **Password:** `Admin@12345` *(Change immediately after signing in)*

## Cron Workers
- `php cron/update-orders.php` — Synchronizes active order statuses from connected providers via cURL.
- `php cron/sync-services.php` — Synchronizes provider base rates and recalculates selling prices using configured margins.
- `php cron/process-refunds.php` — Processes eligible full/partial refunds atomically without duplicates.
