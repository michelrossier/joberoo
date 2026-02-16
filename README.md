# Recruiteroo (Jobfunnel)

Multi-tenant recruiting application built with Laravel + Filament.

It provides:
- a public job landing + application flow
- an internal recruiter/admin panel
- workflow tooling (kanban, evaluation kits, analytics)
- audit and email delivery tracking for operational visibility

## What The Project Does

### Public-facing flow
- Campaign pages at `/{org_slug}/{campaign_slug}`
- Candidate application submission with optional attachments
- Thank-you page after submission

### Admin/recruiter flow (`/admin`)
- Multi-tenant panel scoped by `Organization`
- Campaign, application, user, and organization management
- Kanban application status transitions with optional applicant messaging
- Funnel analytics and candidate comparison pages
- Interview kit + stage-based evaluation workflow

### Governance and observability
- Global audit log (`audit_logs`) for auth + model changes
- Email message log (`email_messages`) with Postmark webhook event ingestion (`email_message_events`)
- Application activity timeline entries for send/delivery/open/bounce/spam events

## Core Tech Stack

- `Laravel 12` (backend framework, queues, notifications, Eloquent)
- `PHP 8.2+`
- `Filament 5` (admin panel/resources/pages/widgets)
- `Livewire 4` (interactive Filament pages, kanban, tests)
- `MySQL` (default in `.env.example`; sqlite also supported for tests/local experiments)
- `Vite` + `Tailwind CSS v4` + `Axios` (frontend asset pipeline)
- `Postmark` (`symfony/postmark-mailer`) for transactional email
- Database queue driver (`jobs` / `failed_jobs`) for async email sends
- `PHPUnit 11` + `Mockery` for feature tests

## Project Structure

```text
app/
  Filament/
    Resources/                 # CRUD resources (Campaigns, Applications, Users, etc.)
    Pages/                     # Custom pages (Dashboard, FunnelAnalytics, CandidateCompare)
    Widgets/                   # Dashboard/analytics widgets
  Http/
    Controllers/               # Public campaign flow, webhook controller, downloads
    Middleware/                # Postmark webhook auth, etc.
  Models/                      # Domain entities (Organization, Campaign, Application, ...)
  Notifications/               # Outbound mail notifications
  Support/
    AuditLogger.php
    MailTracking/              # Outbound logger + Postmark webhook processor
database/
  migrations/                  # Full schema history
  seeders/DatabaseSeeder.php   # Bootstraps sample org/admin/campaign
resources/
  views/                       # Blade views and email templates
routes/web.php                 # Public routes + webhook endpoint
docs/
  laravel-cloud-queue-workers.md
```

## Data Model Overview

- `organizations`: tenant boundary
- `users` + `organization_user`: users belong to one or many organizations with role (`admin` / `recruiter`)
- `campaigns`: jobs per organization
- `applications`: candidate applications per campaign
- `application_activities`: per-application timeline/audit trail
- `campaign_scorecard_competencies` + `application_evaluations`: stage-based evaluation system
- `audit_logs`: global immutable super-admin audit history
- `email_messages` + `email_message_events`: outbound email tracking and webhook events

## Local Setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 20+ and npm
- MySQL (or sqlite if preferred)

### 1) Install dependencies

```bash
composer install
npm install
```

### 2) Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- database connection (`DB_*`)
- app url (`APP_URL`)
- mail + Postmark settings (if testing transactional mail)

### 3) Run migrations and seed

```bash
php artisan migrate --seed
```

Seeder creates:
- organization: `Acme Recruiting` (`acme`)
- admin user: `admin@example.com`
- password: `password`

### 4) Start the app

Recommended all-in-one dev command:

```bash
composer dev
```

This starts:
- Laravel HTTP server
- queue listener
- log stream (`pail`)
- Vite dev server

Or run manually:

```bash
php artisan serve
php artisan queue:work database --queue=default --sleep=5 --tries=3 --backoff=30 --timeout=120
npm run dev
```

## Access URLs

- Public home: `http://localhost:8000/`
- Filament admin: `http://localhost:8000/admin`
- Example seeded campaign: `http://localhost:8000/acme/senior-product-designer`

## Queue And Email Requirements

Application status mails and other notifications are queued.  
Without a running queue worker, emails will not be sent.

Default queue config:
- `QUEUE_CONNECTION=database`
- table: `jobs`
- failed table: `failed_jobs` (`database-uuids`)

Useful commands:

```bash
php artisan queue:work database --queue=default --sleep=5 --tries=3 --backoff=30 --timeout=120 --max-time=3600
php artisan queue:failed
php artisan queue:retry all
```

## Temporary Migration Note

During the Filament major-version migration, the applications Excel export action was intentionally disabled.

- Removed from `App\Filament\Resources\ApplicationResource\Pages\ListApplications`
- Follow-up task: reintroduce export with a Filament 5-compatible implementation

## Postmark Integration

Required env keys:
- `MAIL_MAILER` (typically `failover`)
- `POSTMARK_TOKEN`
- `POSTMARK_MESSAGE_STREAM_ID=outbound`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `POSTMARK_WEBHOOK_BASIC_USER`
- `POSTMARK_WEBHOOK_BASIC_PASS`

Webhook endpoint:
- `POST /webhooks/postmark`
- basic-auth protected via `VerifyPostmarkWebhookBasicAuth`

Tracked events:
- delivery
- open
- bounce
- spam complaint

## Super Admin Utility Command

Grant super admin:

```bash
php artisan user:super-admin user@example.com
```

Revoke super admin:

```bash
php artisan user:super-admin user@example.com --revoke
```

## Testing

Run all tests:

```bash
php artisan test
```

Run selected suites:

```bash
php artisan test --filter=ApplicationKanbanTest
php artisan test --filter=PostmarkMailTrackingTest
php artisan test --filter=InterviewKitEvaluationTest
```

## Deployment Notes (Laravel Cloud)

- Ensure migrations run on deploy.
- Set the Laravel Cloud deploy command to:

```bash
composer run deploy:cloud --no-interaction
```

- This command handles:
  - `npm ci`
  - `npm run build`
  - `php artisan migrate --force`
  - `php artisan optimize`
  - `php artisan queue:restart`
- Configure a persistent queue worker process.
- Keep Postmark webhook configured to your production URL.
- See: `docs/laravel-cloud-queue-workers.md`
