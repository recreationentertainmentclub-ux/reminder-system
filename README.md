# SmartSender Reminder System

A PHP-based reminder system that automatically sends email notifications via the [SmartSender](https://smartsender.com) API.

## Features

- **Admin Dashboard** – Create, edit, cancel and delete reminders via a web UI
- **REST API** – Programmatic CRUD access to reminders (`public/api.php`)
- **Automated Notifications** – Sends a reminder email 24 h *and* 12 h before each event using SmartSender tags (`REMIND_24` / `REMIND_12`)
- **GitHub Actions Cron** – Workflow runs every 5 minutes and triggers the cron script
- **Delivery Logging** – Every SmartSender API call is recorded in the `reminder_logs` table
- **Comprehensive Error Handling** – Failures are logged but never crash the cron run

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.0+ |
| Database | MySQL / MariaDB |
| Email delivery | SmartSender API |
| Scheduling | GitHub Actions (cron) |
| Dependency management | Composer |
| Testing | PHPUnit 10 |

---

## Directory Layout

```
reminder-system/
├── .github/
│   └── workflows/
│       └── cron.yml          # GitHub Actions – runs every 5 min
├── config/
│   └── config.php            # App configuration (reads env vars)
├── cron/
│   └── send_reminders.php    # Cron entry-point
├── logs/                     # Runtime log output (git-ignored)
├── public/
│   ├── index.php             # Admin dashboard
│   └── api.php               # REST API
├── sql/
│   └── schema.sql            # Database schema
├── src/
│   ├── Database.php          # PDO singleton
│   ├── Reminder.php          # Reminder model
│   └── SmartSender.php       # SmartSender API client
├── tests/
│   ├── ReminderTest.php
│   └── SmartSenderTest.php
├── composer.json
└── phpunit.xml
```

---

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/recreationentertainmentclub-ux/reminder-system.git
cd reminder-system
```

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Create the database

```bash
mysql -u root -p < sql/schema.sql
```

### 4. Configure environment variables

Copy `.env.example` to `.env` (or export the variables in your shell):

| Variable | Description | Default |
|---|---|---|
| `DB_HOST` | MySQL host | `localhost` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `reminder_system` |
| `DB_USER` | Database user | `root` |
| `DB_PASSWORD` | Database password | _(empty)_ |
| `SMARTSENDER_API_URL` | SmartSender base URL | `https://app.smartsender.com/api` |
| `SMARTSENDER_API_KEY` | SmartSender Bearer token | _(required)_ |
| `APP_TIMEZONE` | PHP timezone | `UTC` |
| `APP_LOG_FILE` | Absolute path to log file | `logs/app.log` |

### 5. Web server

Point the document root to the `public/` directory.  
Example (Apache virtual host):

```apache
DocumentRoot /var/www/reminder-system/public
<Directory /var/www/reminder-system/public>
    AllowOverride All
    Require all granted
</Directory>
```

### 6. GitHub Actions cron (recommended)

Add the following **repository secrets** in *Settings → Secrets and variables → Actions*:

`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`,  
`SMARTSENDER_API_URL`, `SMARTSENDER_API_KEY`, `APP_TIMEZONE`

The workflow `.github/workflows/cron.yml` will then run `cron/send_reminders.php` every 5 minutes automatically.

### 7. Traditional cron (alternative)

```cron
*/5 * * * * php /var/www/reminder-system/cron/send_reminders.php >> /var/www/reminder-system/logs/cron.log 2>&1
```

---

## REST API

All responses are JSON. Base URL: `/api.php`

| Method | URL | Description |
|---|---|---|
| `GET` | `/api.php` | List all reminders |
| `GET` | `/api.php?id=N` | Get a single reminder |
| `POST` | `/api.php` | Create a reminder (JSON body) |
| `PUT` | `/api.php?id=N` | Update a reminder (JSON body) |
| `DELETE` | `/api.php?id=N` | Delete a reminder |

### Create / Update payload

```json
{
  "title": "Team meeting",
  "description": "Quarterly review",
  "email": "alice@example.com",
  "phone": "+1 555 0100",
  "event_datetime": "2026-06-15 14:00:00"
}
```

---

## SmartSender Integration

The system upserts a contact in SmartSender and assigns one of two tags:

| Tag | Sent when |
|---|---|
| `REMIND_24` | Event is 23–25 hours away |
| `REMIND_12` | Event is 11–13 hours away |

Each tag should map to an automation workflow in your SmartSender account that sends the appropriate email to the contact.

---

## Running Tests

```bash
composer install   # includes dev dependencies
php vendor/bin/phpunit --testdox
```

Tests use an in-memory SQLite database – no real MySQL or SmartSender account needed.