# IT-Service-Desk

Ein vollständiges **IT-Service-Desk-System mit Asset-Management**. Das System ermöglicht die zentrale Verwaltung von IT-Assets, die Bearbeitung von Support-Tickets sowie den Betrieb einer internen Wissensdatenbank.

---

## Inhaltsverzeichnis

- [Features](#features)
- [Technologie-Stack](#technologie-stack)
- [Architektur](#architektur)
- [Schnellstart](#schnellstart)
- [Installationsanleitung](#installationsanleitung)
- [API-Dokumentation](#api-dokumentation)
- [Autorisierung](#autorisierung)
- [Observability](#observability)
- [Tests](#tests)
- [Datenbankschema](#datenbankschema)
- [CI/CD](#cicd)
- [Projektstruktur](#projektstruktur)

---

## Features
**Fokus:** Tickets, Assets und Autorisierung bilden den fachlichen Kern und zeigen die eigentliche Substanz (Statusmaschine, echte Rechteprüfung, Audit Trail). Wissensdatenbank und Lizenzverwaltung sind bewusst als erweiterte Module angelegt — vollständig funktionsfähig und getestet, aber nicht der Schwerpunkt.

### Asset-Management
- Verwaltung von Geräten, Servern, Lizenzen und Netzwerkinfrastruktur
- Hierarchische Asset-Kategorien (z. B. Hardware → Laptops)
- Vollständige Zuweisungshistorie, gegen Parallelzugriffe gesperrt (nie zwei aktive Zuweisungen gleichzeitig)
- Lizenz-Tracking mit Sitzplatzkontingent und Ablaufdatum
- Rollenbasierter Zugriff: Requester sehen nur die ihnen zugewiesenen Assets

### Ticketsystem
- Erstellen, Bearbeiten und Schließen von Support-Tickets
- Prioritätsstufen: Niedrig, Mittel, Hoch, Kritisch
- **Fünfstufiger Status-Workflow mit verbindlicher Übergangsmatrix:**
  `open → in_progress → waiting_for_requester → resolved → closed`
  (inkl. Direktweg `open → resolved` und Wiedereröffnung `resolved → in_progress`).
  Ungültige Übergänge werden mit HTTP 409 abgelehnt — einheitlich für alle Rollen, auch Admins.
- Automatischer Statuswechsel `waiting_for_requester → in_progress`, sobald der Requester öffentlich antwortet
- Interne (agenten-only) und öffentliche Kommentare — interne Kommentare werden Requestern serverseitig nie ausgeliefert
- Vollständige Änderungshistorie (Audit Trail), inkl. automatischer Übergänge
- Verknüpfung von Tickets mit betroffenen Assets

### Wissensdatenbank
- Redaktioneller Workflow: `draft → submitted → published → archived`
- Requester können Entwürfe erstellen, bearbeiten und zur Prüfung einreichen — veröffentlichen dürfen nur Agent/Admin
- Kategorien und Tags, Volltextsuche
- Kollisionssicherer Slug (`titel`, `titel-2`, `titel-3`, …)

### Benutzerverwaltung & Rollen
- Rollen: Administrator, Agent, Benutzer — Admin für systemweite/destruktive Vorgänge, Agent für das operative Tagesgeschäft
- Durchgängige Autorisierung über Laravel Policies (nicht nur UI-seitig gefiltert)
- Token-basierte API-Authentifizierung (Laravel Sanctum)

### REST-API
- Versionierte API (`/api/v1/`) für **Tickets, Assets und Wissensartikel** inklusive Workflow-Endpunkten (Submit/Publish/Archive, Asset-Zuweisung)
- JSON-Antworten mit Paginierung, Filtermöglichkeiten nach Status, Priorität, Kategorie, Volltext
- Für Benutzer, Rollen, Berechtigungen, Lizenzen sowie Asset-/Ticket-Kategorien und -Status existieren aktuell **keine** eigenen API-Endpunkte — Verwaltung erfolgt über Seeder/Datenbank. Eine Oberfläche gibt es bislang nur als Laravel-Standardseite plus `/metrics`; als Backend-Referenz ist das bewusst so begrenzt.

### Observability
- `/metrics` im Prometheus-Textformat (Ticket-Zahlen je Status, HTTP-Request-Rate und -Latenz)
- Request-ID pro Anfrage (Response-Header `X-Request-Id` + Log-Kontext)
- Strukturierte JSON-Logs direkt an Loki

---

## Technologie-Stack

| Schicht | Technologie |
|---|---|
| Runtime | PHP 8.5 |
| Framework | **Laravel 13** |
| Webserver | FrankenPHP + Caddy |
| Datenbank | MySQL 8.4 |
| Cache / Queue / Metrik-Zähler | Redis 7 |
| Authentifizierung | Laravel Sanctum |
| Autorisierung | Laravel Policies |
| Observability | Prometheus-Metrikendpunkt, strukturierte Logs an Loki |
| Tests | PHPUnit (via `php artisan test`) |
| Codestyle | Laravel Pint |
| Statische Analyse | PHPStan + Larastan (Level 5, **verbindlich in CI**) |
| Containerisierung | Docker + Docker Compose, Dev-Container läuft als Non-Root-User |
| CI/CD | GitHub Actions |
| IDE | JetBrains PHPStorm |

---

## Architektur

```
Laravel 13
│
├── Authentication (Sanctum Token-Auth)
├── Authorization (Policies: TicketPolicy, AssetPolicy, KbArticlePolicy)
├── REST API v1
│   ├── AuthController
│   ├── TicketController
│   ├── AssetController
│   └── KbArticleController (inkl. submit/publish/archive)
├── Middleware
│   ├── AssignRequestId (Request-ID pro Anfrage)
│   └── RecordRequestMetrics (Redis-Zähler + Loki-Log)
├── Service Layer
│   ├── TicketService (Übergangsmatrix, History-Tracking, Auto-Transitions)
│   ├── AssetService (Zuweisungen mit Lock, History)
│   └── KbArticleService (Redaktions-Workflow, Slug-Kollisionsschutz)
├── Eloquent Models (17 Models, PHP 8.1 Enums inkl. Übergangsmatrix)
├── API Resources (JSON-Transformation, interne Kommentare rollenabhängig gefiltert)
├── Form Requests (getrennte Store-/Update-Validierung)
├── MetricsController (/metrics, Prometheus-Textformat)
├── Custom Logging (LokiHandler — strukturierte Logs per HTTP an Loki)
├── MySQL (27+ Tabellen, normalisiertes Schema)
├── Redis (Cache/Queue/Sessions + Request-Metrik-Zähler)
├── Migrations + Seeders
└── PHPUnit Tests (92 Tests)
```

Das Projekt folgt dem **Service-Layer-Pattern**: Controller autorisieren über Policies und delegieren Geschäftslogik an Services, die direkt mit Eloquent-Models arbeiten. Ein zusätzliches Repository-Pattern wurde bewusst nicht eingesetzt, da Eloquent bereits eine saubere Datenzugriffs-Abstraktion bietet.

---

## Schnellstart

```bash
git clone https://github.com/wilke26/isd.git
cd isd

# 1) Laravel-eigene Konfiguration
cp .env.example .env

# 2) Docker-Compose-Konfiguration — SEPARAT von .env, siehe Installationsanleitung
cp .env.docker.example .env.docker
echo "UID=$(id -u)" >> .env.docker
echo "GID=$(id -g)" >> .env.docker

# 3) .env auf Docker-Netzwerk-Servicenamen umstellen (robust gegen kommentierte
#    oder fehlende Zeilen in .env.example — siehe INSTALLATION.md Schritt 4)
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

docker compose --env-file .env.docker build
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec app composer install
docker compose --env-file .env.docker exec app php artisan key:generate
docker compose --env-file .env.docker exec app php artisan migrate --seed
```

Anwendung erreichbar unter: **https://isd.local**
Mailpit (E-Mail-Vorschau): http://localhost:8025
Metriken: http://isd.local/metrics

Login-Zugangsdaten (Testdaten):
- Admin: `admin@isd.local` / `password`
- Agent: `a.mueller@isd.local` / `password`
- Benutzer: `c.weber@isd.local` / `password`

---

## Installationsanleitung

Siehe [INSTALLATION.md](INSTALLATION.md) für die vollständige Schritt-für-Schritt-Anleitung, inklusive TLS-Zertifikat, PHPStorm-Einrichtung und Observability-Anbindung.

---

## API-Dokumentation

Siehe [API.md](API.md) für die vollständige API-Referenz.

---

## Autorisierung

Jede Ressource ist über eine dedizierte Laravel-Policy abgesichert (`app/Policies/`), nicht nur durch Filterung in der Auflistung:

| Aktion | Admin | Agent | Requester/Owner |
|---|---|---|---|
| Ticket ansehen | ✓ | ✓ | ✓ eigenes |
| Ticket bearbeiten/zuweisen/Status ändern | ✓ | ✓ | ✗ |
| Öffentlich kommentieren | ✓ | ✓ | ✓ eigenes |
| Intern kommentieren | ✓ | ✓ | ✗ |
| Asset ansehen | ✓ | ✓ | ✓ zugewiesenes |
| Asset anlegen/bearbeiten/zuweisen | ✓ | ✓ | ✗ |
| Asset löschen | ✓ | ✗ | ✗ |
| Wissensartikel-Entwurf anlegen/bearbeiten | ✓ | ✓ | ✓ eigener |
| Wissensartikel veröffentlichen/archivieren | ✓ | ✓ | ✗ |
| Wissensartikel löschen | ✓ (alle) | ✓ (eigene) | ✓ (eigener Entwurf) |

---

## Observability

ISD ist an einen separaten, eigenständigen `observability-stack` (Prometheus/Grafana/Loki) angebunden — einseitig über `host.docker.internal`, ohne gemeinsames Docker-Netzwerk:

```
Prometheus :9090 ──GET host.docker.internal:80/metrics──> ISD :80
ISD ──HTTP Push──> Loki :3100
ISD ──Redis-Zähler──> Redis (Request-Rate/Latenz)
Grafana :3000 ──> Prometheus, Loki
```

- **Metriken:** `isd_tickets_by_status`, `isd_http_requests_total`, `isd_http_request_duration_seconds_{sum,count}`
- **Logs:** strukturierte JSON-Zeilen pro abgeschlossenem Request, mit `request_id` als durchsuchbarem JSON-Feld (bewusst kein Loki-Label, um Kardinalitätsexplosion zu vermeiden)
- **Request-ID-Korrelation:** Response-Header `X-Request-Id` → LogQL-Suche `{job="isd"} | json | request_id="..."`

Details zur Einrichtung siehe [INSTALLATION.md](INSTALLATION.md#observability-anbindung).

---

## Tests

```bash
# Alle Tests ausführen
docker compose --env-file .env.docker exec app php artisan test

# Einzelne Test-Suite
docker compose --env-file .env.docker exec app php artisan test --testsuite=Unit
docker compose --env-file .env.docker exec app php artisan test --testsuite=Feature

# Mit Coverage-Report
docker compose --env-file .env.docker exec app php artisan test --coverage
```

**Aktueller Teststand:** 101 Tests, 0 Fehler — inkl. dedizierter Unit-Tests für die Ticket-Status-Übergangsmatrix, den Loki-Log-Handler und Autorisierungs-Grenzfälle (z. B. "Agent darf fremden Wissensartikel nicht löschen").

Tests laufen gegen eine SQLite-In-Memory-Datenbank (`phpunit.xml`) und sind vollständig unabhängig von den Entwicklungsdaten.

---

## Datenbankschema

Das Schema (`database/schema/it_service_desk.puml`) kann mit dem PlantUML-Plugin in PHPStorm oder unter [plantuml.com](https://www.plantuml.com/plantuml) gerendert werden.

Tabellen-Übersicht:

| Bereich | Tabellen |
|---|---|
| Auth | `users`, `roles`, `role_user`, `personal_access_tokens` |
| Assets | `assets`, `asset_categories`, `asset_statuses`, `asset_assignments`, `licenses`, `license_assignments` |
| Tickets | `tickets`, `ticket_categories`, `ticket_comments`, `ticket_attachments`, `ticket_history` |
| Wissensdatenbank | `kb_articles`, `kb_categories`, `kb_article_tag`, `tags` |
| Laravel intern | `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `sessions` |

---

## CI/CD

GitHub Actions Pipeline (`.github/workflows/ci.yml`):

| Job | Beschreibung |
|---|---|
| `build-production-image` | Docker-Produktions-Image bauen |
| `test` | PHPUnit-Tests gegen MySQL + Redis |
| `static-analysis` | Laravel Pint (Codestyle) + PHPStan Level 5 (**verbindlich**, kein `continue-on-error` mehr) |

Wird ausgelöst bei Push/PR auf `main` und `develop`.

---

## Projektstruktur

```
isd/
├── app/
│   ├── Enums/              # PHP 8.1 Backed Enums (TicketStatus inkl. Übergangsmatrix, TicketPriority, ArticleStatus)
│   ├── Exceptions/         # InvalidTicketStatusTransitionException (→ HTTP 409)
│   ├── Http/
│   │   ├── Controllers/Api/V1/   # API-Controller
│   │   ├── Controllers/MetricsController.php
│   │   ├── Middleware/            # AssignRequestId, RecordRequestMetrics
│   │   ├── Requests/Api/         # Form Requests (getrennte Store-/Update-Validierung)
│   │   └── Resources/Api/        # API Resources (JSON-Transformation)
│   ├── Logging/            # LokiHandler (custom Monolog-Handler)
│   ├── Models/             # Eloquent Models (17 Models)
│   ├── Policies/           # TicketPolicy, AssetPolicy, KbArticlePolicy
│   └── Services/           # Service Layer (Geschäftslogik)
├── database/
│   ├── factories/          # Model Factories für Tests
│   ├── migrations/         # inkl. additiver Migrationen für neue Enum-Werte
│   └── seeders/            # Testdaten
├── docker/
│   ├── caddy/Caddyfile     # FrankenPHP/Caddy-Konfiguration
│   └── php/                # PHP-Konfiguration (dev/prod)
├── routes/
│   ├── api.php             # API-Routen v1
│   └── web.php             # /metrics
├── tests/
│   ├── Feature/            # Feature-Tests (API-Endpunkte, Autorisierung)
│   └── Unit/               # Unit-Tests (Services, Enums, Logging)
├── .github/workflows/      # GitHub Actions CI
├── docker-compose.yml      # Entwicklungsumgebung (Ports an 127.0.0.1 gebunden)
├── docker-compose.ci.yml   # CI-Override
├── Dockerfile              # Multi-Stage Build (dev/prod, Dev-Container als Non-Root-User)
├── phpstan.neon            # PHPStan-Konfiguration
└── pint.json               # Laravel Pint-Konfiguration
```
