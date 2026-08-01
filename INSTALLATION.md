# Installationsanleitung

## Voraussetzungen

### macOS (Sonoma, Apple Silicon)

| Software | Version | Installation |
|---|---|---|
| Docker Desktop | ≥ 4.30 | [docker.com/desktop](https://www.docker.com/products/docker-desktop/) |
| mkcert | ≥ 1.4 | `brew install mkcert nss && mkcert -install` |
| Git | beliebig | vorinstalliert |

**Docker Desktop konfigurieren:**
Settings → General → "Choose file sharing implementation for your containers" → **VirtioFS** auswählen. Das verbessert die Dateisystem-Performance auf macOS erheblich.

---

## Schritt 1 — Repository klonen

```bash
git clone https://github.com/wilke26/isd.git
cd isd
```

---

## Schritt 2 — Hosts-Eintrag setzen

```bash
echo "127.0.0.1 isd.local" | sudo tee -a /etc/hosts
```

---

## Schritt 3 — TLS-Zertifikat erzeugen

```bash
cd /pfad/zu/isd
mkcert isd.local
```

Die Dateien `isd.local.pem` und `isd.local-key.pem` landen im Projektverzeichnis (`.gitignore` schließt `*.pem` aus, sie werden nicht eingecheckt).

---

## Schritt 4 — Umgebungsvariablen anlegen

**Wichtig:** ISD verwendet **zwei getrennte** Env-Dateien für zwei unterschiedliche Zwecke — das gezielt auseinanderzuhalten ist der häufigste Stolperstein bei der Ersteinrichtung:

| Datei | Wird gelesen von | Zweck |
|---|---|---|
| `.env` | Laravel (innerhalb des Containers) | `APP_KEY`, `DB_*`, `REDIS_*`, `LOKI_*` — die eigentliche Anwendungskonfiguration |
| `.env.docker` | `docker compose` (auf dem Host) | `UID`/`GID`, MySQL-Root-Passwort — nur für den Compose-Aufruf selbst |

```bash
# 1) Laravel-eigene Konfiguration
cp .env.example .env

# 2) Docker-Compose-Konfiguration — SEPARAT, niemals in .env kopieren!
cp .env.docker.example .env.docker
echo "UID=$(id -u)" >> .env.docker
echo "GID=$(id -g)" >> .env.docker
```

Danach in `.env` die Container-internen Verbindungsdaten eintragen (Datenbank/Redis laufen im selben Docker-Netzwerk unter ihrem Servicenamen, nicht `localhost`):

```bash
sed -i '' \
  -e 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' \
  -e 's/^DB_HOST=.*/DB_HOST=mysql/' \
  -e 's/^DB_PORT=.*/DB_PORT=3306/' \
  -e 's/^REDIS_HOST=.*/REDIS_HOST=redis/' \
  -e 's/^CACHE_STORE=.*/CACHE_STORE=redis/' \
  -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' \
  -e 's/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' \
  -e 's/^MAIL_HOST=.*/MAIL_HOST=mailpit/' \
  -e 's/^MAIL_PORT=.*/MAIL_PORT=1025/' \
  .env

cat >> .env << 'EOF'
DB_DATABASE=it_service_desk
DB_USERNAME=isd_user
DB_PASSWORD=secret
EOF
```

Ab hier **immer** `docker compose --env-file .env.docker ...` verwenden (nicht `.env`) — der `--env-file`-Parameter betrifft ausschließlich, welche Variablen Docker Compose selbst für Platzhalter wie `${UID}` nutzt, nicht was Laravel im Container zu sehen bekommt (das liest ohnehin immer die bind-gemountete `.env`).

---

## Schritt 5 — Docker-Image bauen

```bash
docker compose --env-file .env.docker build
```

Beim ersten Aufruf werden das FrankenPHP-Basis-Image und alle PHP-Extensions heruntergeladen (~3 min).

---

## Schritt 6 — Stack starten

```bash
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
```

Erwartete Ausgabe: `isd-app`, `isd-mysql`, `isd-redis`, `isd-mailpit`, `isd-queue`, `isd-scheduler` mit Status `Up`.

Alle Ports sind bewusst an `127.0.0.1` gebunden (Netzwerk-Härtung) — von anderen Geräten im lokalen Netzwerk aus nicht erreichbar, vom Host selbst (Browser, PHPStorm) aber uneingeschränkt.

> **Hinweis MySQL-Port:** Falls `127.0.0.1:3306` bereits belegt ist (z. B. durch eine lokale MySQL-Installation), ändere in `docker-compose.yml` das Port-Mapping auf `"127.0.0.1:3307:3306"` (Standard in diesem Repo bereits so gesetzt).

---

## Schritt 7 — Laravel einrichten

```bash
docker compose --env-file .env.docker exec app composer install
docker compose --env-file .env.docker exec app php artisan key:generate
docker compose --env-file .env.docker exec app php artisan migrate
docker compose --env-file .env.docker exec app php artisan db:seed
```

---

## Schritt 8 — TLS-Zertifikat in den Container kopieren

```bash
docker compose --env-file .env.docker cp isd.local.pem app:/data/caddy/certificates/local/isd.local/isd.local.crt
docker compose --env-file .env.docker cp isd.local-key.pem app:/data/caddy/certificates/local/isd.local/isd.local.key
```

---

## Schritt 9 — Installation prüfen

- **https://isd.local** → Laravel-Willkommensseite
- **http://localhost:8025** → Mailpit (E-Mail-Vorschau)
- **http://isd.local/metrics** → Prometheus-Metriken (Ticket-Zahlen, Request-Statistiken)

API-Test:
```bash
curl -s -X POST https://isd.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@isd.local","password":"password"}' \
  --insecure | python3 -m json.tool
```

---

## Testdaten (nach `db:seed`)

| Rolle | E-Mail | Passwort |
|---|---|---|
| Administrator | admin@isd.local | password |
| Agent | a.mueller@isd.local | password |
| Agent | b.schmidt@isd.local | password |
| Benutzer | c.weber@isd.local | password |
| Benutzer | d.bauer@isd.local | password |
| Benutzer | e.fischer@isd.local | password |

---

## PHPStorm-Einrichtung

### Remote-Interpreter (Docker)

1. Settings → PHP → CLI Interpreter → `...` → `+` → "From Docker, Vagrant, ..."
2. **Docker Compose** auswählen
3. Server: `Docker`, Configuration files: `./docker-compose.yml`, Service: `app`
4. Lifecycle: **"Connect to existing container (docker-compose exec)"**
5. PHP-Version 8.5.x und Xdebug 3.x werden automatisch erkannt

### Xdebug

1. Settings → PHP → Debug → Port: `9003`
2. Settings → PHP → Servers → `+`: Name `isd.local`, Host `isd.local`, Port `443`, Debugger Xdebug; "Use path mappings" aktivieren

Aktivieren via `.env.docker`: `XDEBUG_MODE=debug`, danach `docker compose --env-file .env.docker up -d --force-recreate app`.

### Datenbank-Verbindung

View → Tool Windows → Database → `+` → Data Source → MySQL:
Host `localhost`, Port `3307`, User `isd_user`, Password `secret`, Database `it_service_desk`.

---

## Observability-Anbindung

Voraussetzung: Der separate `observability-stack` (Prometheus/Grafana/Loki) läuft bereits lokal.

### 1. In ISDs `.env` ergänzen (bereits per Default in `.env.example` vorhanden)

```dotenv
LOKI_ENDPOINT=http://host.docker.internal:3100/loki/api/v1/push
LOKI_JOB=isd
```

### 2. Prometheus-Scrape-Ziel im `observability-stack` ergänzen

In `prometheus/prometheus.yml` dieses Repos (separat!):

```yaml
scrape_configs:
  - job_name: isd
    metrics_path: /metrics
    static_configs:
      - targets: ["host.docker.internal:80"]
```

Danach Prometheus neu laden:
```bash
docker compose restart prometheus
```

### 3. Verbindung prüfen

```bash
curl -fsS http://localhost/metrics
```

Danach [Prometheus Targets](http://localhost:9090/targets) öffnen — der Job `isd` muss `UP` sein.

### Nützliche Grafana-Abfragen

```promql
sum by (status) (isd_tickets_by_status)
sum(rate(isd_http_requests_total[5m]))

# Durchschnittliche Antwortzeit
sum(rate(isd_http_request_duration_seconds_sum[5m]))
/
sum(rate(isd_http_request_duration_seconds_count[5m]))
```

```logql
{job="isd"} | json | request_id="<ID aus X-Request-Id Header>"
```

Die beiden Compose-Projekte bleiben bewusst unabhängig und kommunizieren einseitig über `host.docker.internal` — kein gemeinsames Docker-Netzwerk nötig.

---

## Häufige Probleme

**Port 443/80 bereits belegt:**
```bash
sudo lsof -nP -iTCP:443 -sTCP:LISTEN
# Prozess beenden oder docker-compose.yml Port anpassen
```

**Segmentation Fault (FrankenPHP, selten auf Apple Silicon):**
```bash
# In docker-compose.yml beim app-Service ergänzen:
platform: linux/amd64
docker compose --env-file .env.docker build
```

**`chown: invalid group: 'appuser:appuser'` bei manuellen Eingriffen im Container:**
Auf macOS kollidiert die Host-GID `20` (Gruppe `staff`) mit der Debian-Systemgruppe `dialout` — `appuser` landet dadurch in `dialout`, nicht in einer Gruppe namens `appuser`. Numerisch statt namensbasiert arbeiten:
```bash
docker compose --env-file .env.docker exec -u root app chown -R 501:20 <Pfad>
```

**Vendor-/Volume-Verzeichnis leer oder nicht beschreibbar nach Volume-Neuanlage:**
`vendor-data`, `node-modules-data`, `caddy-data`, `caddy-config` sind benannte Docker-Volumes. Werden sie neu angelegt (z. B. nach `docker compose down -v` oder manuellem `docker volume rm`), gehören sie zunächst `root`, der Dev-Container läuft aber als `appuser`:
```bash
docker compose --env-file .env.docker exec -u root app chown -R 501:20 /app/vendor /app/node_modules /data /config
```

---

## Stack stoppen und starten

```bash
# Stoppen (Daten bleiben erhalten)
docker compose --env-file .env.docker stop

# Wieder starten
docker compose --env-file .env.docker start

# Stoppen und alle Volumes löschen (setzt Datenbank zurück)
docker compose --env-file .env.docker down -v
```
