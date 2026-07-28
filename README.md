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
- [Tests](#tests)
- [Datenbankschema](#datenbankschema)
- [CI/CD](#cicd)
- [Projektstruktur](#projektstruktur)

---

## Features

### Asset-Management
- Verwaltung von Geräten, Servern, Lizenzen und Netzwerkinfrastruktur
- Hierarchische Asset-Kategorien (z. B. Hardware → Laptops)
- Vollständige Zuweisungshistorie (wer hatte wann welches Gerät)
- Lizenz-Tracking mit Sitzplatzkontingent und Ablaufdatum

### Ticketsystem
- Erstellen, Bearbeiten und Schließen von Support-Tickets
- Prioritätsstufen: Niedrig, Mittel, Hoch, Kritisch
- Status-Workflow: Offen → In Bearbeitung → Gelöst → Geschlossen
- Interne (agenten-nur) und öffentliche Kommentare
- Dateianhänge, vollständige Änderungshistorie (Audit Trail)
- Verknüpfung von Tickets mit betroffenen Assets

### Wissensdatenbank
- Artikel mit Markdown-Inhalt
- Kategorien und Tags
- Entwurfs- und Veröffentlichungsstatus
- Volltextsuche

### Benutzerverwaltung & Rollen
- Rollen: Administrator, Agent, Benutzer
- Feingranulare Berechtigungen pro Rolle
- Token-basierte API-Authentifizierung (Laravel Sanctum)

### REST-API
- Versionierte API (`/api/v1/`)
- JSON-Antworten mit Paginierung
- Vollständige CRUD-Operationen für alle Ressourcen
- Filtermöglichkeiten nach Status, Priorität, Kategorie, Volltext

---

## Technologie-Stack

| Schicht | Technologie |
|---|---|
| Runtime | PHP 8.5 |
| Framework | Laravel 11 |
| Webserver | FrankenPHP + Caddy |
| Datenbank | MySQL 8.4 |
| Cache / Queue | Redis 7 |
| Authentifizierung | Laravel Sanctum |
| Tests | PHPUnit (via `php artisan test`) |
| Codestyle | Laravel Pint |
| Statische Analyse | PHPStan + Larastan (Level 5) |
| Containerisierung | Docker + Docker Compose |
| CI/CD | GitHub Actions |
| IDE | JetBrains PHPStorm |

---

## Architektur

```
Laravel 11
│
├── Authentication (Sanctum Token-Auth)
├── Authorization (Rollen & Berechtigungen)
├── REST API v1
│   ├── AuthController
│   ├── TicketController
│   ├── AssetController
│   └── KbArticleController
├── Service Layer
│   ├── TicketService (Geschäftslogik, History-Tracking)
│   ├── AssetService (Zuweisungen, History)
│   └── KbArticleService (Slug-Generierung, Publish-Flow)
├── Eloquent Models (17 Models, PHP 8.1 Enums)
├── API Resources (JSON-Transformation)
├── Form Requests (Validierung)
├── MySQL (27 Tabellen, normalisiertes Schema)
├── Queue (Redis, async Benachrichtigungen)
├── Migrations + Seeders
└── PHPUnit Tests (60 Tests, 179 Assertions)
```

Das Projekt folgt dem **Service-Layer-Pattern**: Controller delegieren Geschäftslogik an Services, die direkt mit Eloquent-Models arbeiten. Ein zusätzliches Repository-Pattern wurde bewusst nicht eingesetzt, da Eloquent bereits eine saubere Datenzugriffs-Abstraktion bietet.

---

## Schnellstart

```bash
git clone https://github.com/wilke26/isd.git
cd isd
cp .env.docker.example .env
docker compose --env-file .env build
docker compose --env-file .env up -d
docker compose --env-file .env exec app composer install
docker compose --env-file .env exec app php artisan key:generate
docker compose --env-file .env exec app php artisan migrate --seed
```

Anwendung erreichbar unter: **https://isd.local**
Mailpit (E-Mail-Vorschau): http://localhost:8025

Login-Zugangsdaten (Testdaten):
- Admin: `admin@isd.local` / `password`
- Agent: `a.mueller@isd.local` / `password`
- Benutzer: `c.weber@isd.local` / `password`

---

## Installationsanleitung

Siehe [INSTALLATION.md](INSTALLATION.md) für die vollständige Schritt-für-Schritt-Anleitung.

---

## API-Dokumentation

Siehe [API.md](API.md) für die vollständige API-Referenz.

Schnelltest nach der Installation:

```bash
# Login
curl -s -X POST https://isd.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@isd.local","password":"password"}' \
  --insecure

# Tickets abrufen (Token aus Login einsetzen)
curl -s https://isd.local/api/v1/tickets \
  -H "Authorization: Bearer <token>" \
  --insecure
```

---

## Tests

```bash
# Alle Tests ausführen
docker compose --env-file .env exec app php artisan test

# Einzelne Test-Suite
docker compose --env-file .env exec app php artisan test --testsuite=Unit
docker compose --env-file .env exec app php artisan test --testsuite=Feature

# Mit Coverage-Report
docker compose --env-file .env exec app php artisan test --coverage
```

**Aktueller Teststand:** 60 Tests, 179 Assertions, 0 Fehler

Tests laufen gegen eine SQLite-In-Memory-Datenbank (`phpunit.xml`) und sind vollständig unabhängig von den Entwicklungsdaten.

---

## Datenbankschema

Das Schema (`database/schema/it_service_desk.puml`) kann mit dem PlantUML-Plugin in PHPStorm oder unter [plantuml.com](https://www.plantuml.com/plantuml) gerendert werden.

Tabellen-Übersicht (27 Tabellen):

| Bereich | Tabellen |
|---|---|
| Auth | `users`, `roles`, `permissions`, `role_user`, `personal_access_tokens` |
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
| `static-analysis` | Laravel Pint (Codestyle) + PHPStan (Level 5) |

Wird ausgelöst bei Push/PR auf `main` und `develop`.

---

## Projektstruktur

```
isd/
├── app/
│   ├── Enums/              # PHP 8.1 Backed Enums (TicketStatus, TicketPriority, ArticleStatus)
│   ├── Http/
│   │   ├── Controllers/Api/V1/   # API-Controller
│   │   ├── Requests/Api/         # Form Requests (Validierung)
│   │   └── Resources/Api/        # API Resources (JSON-Transformation)
│   ├── Models/             # Eloquent Models (17 Models)
│   └── Services/           # Service Layer (Geschäftslogik)
├── database/
│   ├── factories/          # Model Factories für Tests
│   ├── migrations/         # 21 Migrations
│   └── seeders/            # Testdaten
├── docker/
│   ├── caddy/Caddyfile     # FrankenPHP/Caddy-Konfiguration
│   └── php/                # PHP-Konfiguration (dev/prod)
├── routes/
│   └── api.php             # API-Routen v1
├── tests/
│   ├── Feature/Api/V1/     # Feature-Tests (API-Endpunkte)
│   └── Unit/Services/      # Unit-Tests (Service Layer)
├── .github/workflows/      # GitHub Actions CI
├── docker-compose.yml      # Entwicklungsumgebung
├── docker-compose.ci.yml   # CI-Override
├── Dockerfile              # Multi-Stage Build (dev/prod)
├── phpstan.neon            # PHPStan-Konfiguration
└── pint.json               # Laravel Pint-Konfiguration
```
