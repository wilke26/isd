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

Prüfen:
```bash
grep isd.local /etc/hosts
# Ausgabe: 127.0.0.1  isd.local
```

---

## Schritt 3 — TLS-Zertifikat erzeugen

```bash
cd /pfad/zu/isd
mkcert isd.local
```

Die Dateien `isd.local.pem` und `isd.local-key.pem` landen im Projektverzeichnis. Sie werden in Schritt 7 in den Container kopiert.

> **Hinweis:** `.gitignore` enthält bereits `*.pem`, die Zertifikate werden nicht ins Repository eingecheckt.

---

## Schritt 4 — Umgebungsvariablen anlegen

```bash
# Docker-Compose-Variablen (Datenbank, UID/GID)
cp .env.docker.example .env

# Eigene UID/GID eintragen (verhindert Datei-Berechtigungsprobleme)
echo "UID=$(id -u)" >> .env
echo "GID=$(id -g)" >> .env
```

---

## Schritt 5 — Docker-Image bauen

```bash
docker compose --env-file .env build
```

Beim ersten Aufruf werden das FrankenPHP-Basis-Image und alle PHP-Extensions heruntergeladen (~3 min, abhängig von der Internetverbindung).

---

## Schritt 6 — Stack starten

```bash
docker compose --env-file .env up -d
```

Alle Container prüfen:
```bash
docker compose --env-file .env ps
```

Erwartete Ausgabe: `isd-app`, `isd-mysql`, `isd-redis`, `isd-mailpit`, `isd-queue`, `isd-scheduler` mit Status `Up`.

> **Hinweis MySQL-Port:** Falls Port 3306 auf dem Host bereits belegt ist (z. B. durch eine lokale MySQL-Installation), ändere in `docker-compose.yml` das Port-Mapping auf `"3307:3306"`.

---

## Schritt 7 — Laravel einrichten

```bash
# PHP-Dependencies installieren
docker compose --env-file .env exec app composer install

# Application Key generieren
docker compose --env-file .env exec app php artisan key:generate

# Datenbankmigrationen ausführen
docker compose --env-file .env exec app php artisan migrate

# Testdaten einspielen (optional, empfohlen für Entwicklung)
docker compose --env-file .env exec app php artisan db:seed
```

---

## Schritt 8 — TLS-Zertifikat in den Container kopieren

```bash
docker compose --env-file .env cp isd.local.pem app:/data/caddy/certificates/local/isd.local/isd.local.crt
docker compose --env-file .env cp isd.local-key.pem app:/data/caddy/certificates/local/isd.local/isd.local.key
```

---

## Schritt 9 — Installation prüfen

Öffne im Browser:
- **https://isd.local** → Laravel-Willkommensseite
- **http://localhost:8025** → Mailpit (E-Mail-Vorschau)

API-Test:
```bash
curl -s -X POST https://isd.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@isd.local","password":"password"}' \
  --insecure | python3 -m json.tool
```

Erwartete Ausgabe: JSON-Objekt mit `token` und `user`.

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
5. PHP version 8.5.x und Xdebug 3.x werden automatisch erkannt

### Xdebug

1. Settings → PHP → Debug → Port: `9003`
2. Settings → PHP → Servers → `+`:
   - Name: `isd.local`, Host: `isd.local`, Port: `443`, Debugger: Xdebug
   - "Use path mappings" aktivieren: lokales Projektverzeichnis → `/app`

Xdebug aktivieren:
```bash
# In .env
XDEBUG_MODE=debug
docker compose --env-file .env up -d --force-recreate app
```

### Datenbank-Verbindung

View → Tool Windows → Database → `+` → Data Source → MySQL:
- Host: `localhost`, Port: `3307` (oder `3306` falls nicht geändert)
- User: `isd_user`, Password: `secret`
- Database: `it_service_desk`

---

## Häufige Probleme

**Port 443 bereits belegt:**
```bash
sudo lsof -i :443
# Prozess beenden oder docker-compose.yml Port anpassen
```

**MySQL-Port belegt:**
```bash
# In docker-compose.yml:
ports:
  - "3307:3306"  # statt 3306:3306
```

**Segmentation Fault (FrankenPHP, selten auf Apple Silicon):**
```bash
# In docker-compose.yml beim app-Service ergänzen:
platform: linux/amd64
# Dann neu bauen: docker compose --env-file .env build
```

**Vendor-Verzeichnis leer nach Container-Neustart:**

`vendor/` liegt in einem Docker-Volume, nicht im Bind-Mount. Nach einem `docker compose down -v` (Volumes löschen) muss `composer install` erneut ausgeführt werden:
```bash
docker compose --env-file .env exec app composer install
```

---

## Stack stoppen und starten

```bash
# Stoppen (Daten bleiben erhalten)
docker compose --env-file .env stop

# Wieder starten
docker compose --env-file .env start

# Stoppen und alle Volumes löschen (setzt Datenbank zurück)
docker compose --env-file .env down -v
```
