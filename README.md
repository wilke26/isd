# IT Service Desk

**English** | [Deutsch](README.de.md)

A production-oriented **IT service desk and asset management system** for managing
support tickets, IT assets, software licenses, and an internal knowledge base.

## Contents

- [Highlights](#highlights)
- [Technology stack](#technology-stack)
- [Architecture](#architecture)
- [Quick start](#quick-start)
- [Installation](#installation)
- [API documentation](#api-documentation)
- [Authorization](#authorization)
- [Observability](#observability)
- [Tests](#tests)
- [Database schema](#database-schema)
- [CI/CD](#cicd)
- [Project structure](#project-structure)

## Highlights

**Project focus:** Tickets, assets, and authorization form the domain core and
demonstrate the system's main engineering concerns: explicit state transitions,
server-side access control, concurrency safety, and a complete audit trail. The
knowledge base and license management are fully functional and tested extension
modules.

### Asset management

- Manage workstations, servers, and network infrastructure
- Organize assets in hierarchical categories, such as Hardware → Laptops
- Keep a complete assignment history with transactional locking and a database
  constraint that prevents two active assignments for the same asset
- Restrict requesters to assets assigned to them

### License management

- Manage inventory, vendor, product, seat capacity, and expiration in Filament
- Store license keys encrypted and never repopulate existing keys into edit forms
- Assign individual seats to users or assets
- Prevent concurrent over-allocation through transactions and row-level locks
- Derive used seats from assignments instead of maintaining a drift-prone counter
- Prevent duplicate assignments for the same license target through database
  constraints
- Allow agents and administrators to manage inventory and assignments, while only
  administrators may delete an unused license

### Ticketing

- Create, update, resolve, and close support tickets
- Support low, medium, high, and critical priorities
- Enforce a five-stage workflow:
  `open → in_progress → waiting_for_requester → resolved → closed`, including
  `open → resolved` and reopening through `resolved → in_progress`
- Reject invalid transitions with HTTP 409 for every role, including administrators
- Automatically return `waiting_for_requester` tickets to `in_progress` when the
  requester posts a public reply
- Keep internal agent-only comments out of requester responses on the server side
- Record all changes and automatic transitions in an audit trail
- Link tickets to affected assets

### Knowledge base

- Editorial workflow: `draft → submitted → published → archived`
- Let requesters create, edit, and submit their own drafts; publishing remains
  restricted to agents and administrators
- Categories, tags, and full-text search
- Collision-safe slugs such as `title`, `title-2`, and `title-3`

### Users and roles

- Administrator, agent, and requester roles
- Laravel Policies enforce authorization throughout the application, rather than
  relying on UI filtering
- Token-based API authentication with Laravel Sanctum

### REST API

- Versioned `/api/v1` endpoints for tickets, assets, and knowledge articles,
  including workflow operations such as submit, publish, archive, and assignment
- Paginated JSON responses with status, priority, category, and full-text filters
- `throttle:api` rate limiting per authenticated user on all protected API routes;
  `throttle:login` additionally limits login attempts per email/IP pair
- Users, roles, permissions, and asset/ticket categories and statuses intentionally
  have no dedicated API endpoints. License management remains internal to Filament;
  the requester portal receives neither license keys nor inventory data.

### Observability

- Prometheus-compatible `/metrics` endpoint with ticket totals, HTTP request rate,
  and latency
- Request IDs in the `X-Request-Id` response header and structured log context
- Structured JSON logs sent directly to Loki

## Technology stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.5 |
| Framework | **Laravel 13** |
| Web server | FrankenPHP + Caddy |
| Database | MySQL 8.4 |
| Cache, queue, and metric counters | Redis 7 |
| Authentication | Laravel Sanctum |
| Authorization | Laravel Policies |
| Administration | Filament |
| Observability | Prometheus metrics and structured Loki logs |
| Tests | PHPUnit via `php artisan test` |
| Code style | Laravel Pint |
| Static analysis | PHPStan + Larastan, level 5 and enforced in CI |
| Containers | Docker + Docker Compose; non-root development container |
| CI/CD | GitHub Actions |

## Architecture

```text
Laravel 13
│
├── Authentication (Sanctum token authentication)
├── Authorization (TicketPolicy, AssetPolicy, KbArticlePolicy)
├── REST API v1
│   ├── AuthController
│   ├── TicketController
│   ├── AssetController
│   └── KbArticleController (submit, publish, archive)
├── Middleware
│   ├── AssignRequestId
│   └── RecordRequestMetrics
├── Service layer
│   ├── TicketService (transition matrix, history, automatic transitions)
│   ├── AssetService (locking and assignment history)
│   └── KbArticleService (editorial workflow and collision-safe slugs)
├── Eloquent models and PHP enums
├── API resources (JSON transformation and role-aware comment filtering)
├── Form requests (separate create and update validation)
├── MetricsController (Prometheus text format)
├── LokiHandler (structured logs over HTTP)
├── MySQL
├── Redis
├── Migrations and seeders
└── PHPUnit tests
```

The application follows a service-layer architecture. Controllers authorize
requests through Policies and delegate domain mutations to services, which work
directly with Eloquent models. A separate repository layer is deliberately omitted
because Eloquent already provides the required persistence abstraction.

The mandatory boundaries between entry points, services, and direct Eloquent
access are documented in
[ADR 0001: Domain changes through the service layer](docs/adr/0001-service-layer-boundaries.md)
(German). New controllers, Filament actions, jobs, and commands are classified
using the checklist in that decision record.

## Quick start

The following commands reproduce the Docker-based development setup. The complete
installation guide documents platform-specific details.

```bash
git clone https://github.com/wilke26/isd.git
cd isd

# 1) Laravel application configuration
cp .env.example .env

# 2) Docker Compose configuration, kept separate from .env
cp .env.docker.example .env.docker
echo "UID=$(id -u)" >> .env.docker
echo "GID=$(id -g)" >> .env.docker

# 3) Point Laravel at the Docker network service names
set_env_var() {
  local key="$1" value="$2"
  if grep -qE "^#?[[:space:]]*${key}=" .env; then
    sed -i '' -E "s/^#?[[:space:]]*${key}=.*/${key}=${value}/" .env
  else
    echo "${key}=${value}" >> .env
  fi
}
set_env_var DB_CONNECTION mysql
set_env_var DB_HOST mysql
set_env_var DB_PORT 3306
set_env_var DB_DATABASE it_service_desk
set_env_var DB_USERNAME isd_user
set_env_var DB_PASSWORD secret
set_env_var REDIS_HOST redis
set_env_var CACHE_STORE redis
set_env_var QUEUE_CONNECTION redis
set_env_var SESSION_DRIVER redis
set_env_var MAIL_HOST mailpit
set_env_var MAIL_PORT 1025
set_env_var METRICS_TOKEN "$(openssl rand -hex 32)"

docker compose --env-file .env.docker build
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec app composer install
docker compose --env-file .env.docker exec app php artisan key:generate
docker compose --env-file .env.docker exec app php artisan migrate --seed
```

Services after setup:

- Application: **https://isd.local**
- Mailpit: **http://localhost:8025**
- Metrics: **http://isd.local/metrics** using the `METRICS_TOKEN` bearer token

Seeded development accounts:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@isd.local` | `password` |
| Agent | `a.mueller@isd.local` | `password` |
| Requester | `c.weber@isd.local` | `password` |

These credentials are development fixtures and must not be used in production.

## Installation

See [INSTALLATION.md](INSTALLATION.md) (German) for the complete guide, including
TLS certificates, IDE setup, platform notes, and observability integration.

## API documentation

See [API.md](API.md) (German) for the complete endpoint reference. The machine-
readable OpenAPI 3.1 contract is available at
[`openapi/portal-v1.json`](openapi/portal-v1.json).

## Authorization

Every resource is protected by a dedicated Laravel Policy in `app/Policies`.

| Action | Administrator | Agent | Requester/owner |
|---|---|---|---|
| View ticket | Yes | Yes | Own tickets only |
| Edit, assign, or transition ticket | Yes | Yes | No |
| Add public comment | Yes | Yes | Own tickets only |
| Add internal comment | Yes | Yes | No |
| View asset | Yes | Yes | Assigned assets only |
| Create, edit, or assign asset | Yes | Yes | No |
| Delete asset | Yes | No | No |
| Create or edit knowledge draft | Yes | Yes | Own drafts only |
| Publish or archive knowledge article | Yes | Yes | No |
| Delete knowledge article | All | Own articles | Own drafts only |

## Observability

ISD integrates with the separate
[`observability-stack`](https://github.com/wilke26/observability-stack)
through `host.docker.internal`; the stacks do not share a Docker network.

```text
Prometheus :9090 ── GET /metrics + bearer token ──> ISD :80
ISD ── HTTP push ──> Loki :3100
ISD ── request counters ──> Redis
Grafana :3000 ──> Prometheus and Loki
```

- **Metrics:** `isd_tickets_by_status`, `isd_http_requests_total`, and
  `isd_http_request_duration_seconds_{sum,count}`; `/metrics` requires a dedicated
  bearer token
- **Logs:** structured JSON for every completed request, with `request_id` retained
  as a searchable JSON field rather than a high-cardinality Loki label
- **Correlation:** follow `X-Request-Id` from the response into the LogQL query
  `{job="isd"} | json | request_id="..."`

See the [observability section](INSTALLATION.md#observability-anbindung) of the
installation guide for configuration details.

## Tests

```bash
# Complete test suite
docker compose --env-file .env.docker exec app php artisan test

# Individual suites
docker compose --env-file .env.docker exec app php artisan test --testsuite=Unit
docker compose --env-file .env.docker exec app php artisan test --testsuite=Feature

# Coverage report
docker compose --env-file .env.docker exec app php artisan test --coverage
```

The authoritative test count is shown by the latest CI run. The suite includes
dedicated coverage for ticket state transitions, the Loki log handler,
authorization boundaries, concurrency rules, and database constraints.

Tests use an in-memory SQLite database through `phpunit.xml` and do not access
development data.

## Database schema

The PlantUML schema at `database/schema/it_service_desk.puml` can be rendered with
an IDE plugin or at [plantuml.com](https://www.plantuml.com/plantuml).

| Area | Tables |
|---|---|
| Authentication | `users`, `roles`, `role_user`, `personal_access_tokens` |
| Assets and licenses | `assets`, `asset_categories`, `asset_statuses`, `asset_assignments`, `licenses`, `license_assignments` |
| Tickets | `tickets`, `ticket_categories`, `ticket_comments`, `ticket_attachments`, `ticket_history` |
| Knowledge base | `kb_articles`, `kb_categories`, `kb_article_tag`, `tags` |
| Laravel internals | `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `sessions` |

## CI/CD

The GitHub Actions workflow in `.github/workflows/ci.yml` runs for pushes and pull
requests targeting `main` and `develop`.

| Job | Purpose |
|---|---|
| `build-production-image` | Build the production container image |
| `test` | Run PHPUnit against MySQL and Redis |
| `static-analysis` | Enforce Laravel Pint and PHPStan/Larastan level 5 and audit dependencies |
| `fresh-checkout-smoke-test` | Reproduce the documented quick start from a clean checkout |

## Project structure

```text
isd/
├── app/
│   ├── Enums/                    Domain enums and ticket transition matrix
│   ├── Exceptions/               Domain exceptions mapped to HTTP responses
│   ├── Http/
│   │   ├── Controllers/Api/V1/   Versioned API controllers
│   │   ├── Controllers/MetricsController.php
│   │   ├── Middleware/            Request IDs and metrics
│   │   ├── Requests/Api/          Separate create and update validation
│   │   └── Resources/Api/         JSON resource transformation
│   ├── Logging/                  Loki integration
│   ├── Models/                   Eloquent models
│   ├── Policies/                 Resource authorization
│   └── Services/                 Domain mutations and workflows
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── caddy/Caddyfile
│   └── php/
├── openapi/portal-v1.json        OpenAPI 3.1 contract for the requester portal
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── .github/workflows/ci.yml
├── docker-compose.yml
├── docker-compose.ci.yml
├── Dockerfile
├── phpstan.neon
└── pint.json
```

## License

This project is available under the terms in [LICENSE](LICENSE).
