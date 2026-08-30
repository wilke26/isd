# API-Dokumentation

> **Verbindlicher Portalvertrag:** [`openapi/portal-v1.json`](openapi/portal-v1.json)
> ist die maschinenlesbare OpenAPI-3.1-Quelle für alle vom `isd-portal`
> verwendeten Endpunkte. Diese Datei ist maßgeblich für generierte
> TypeScript-Typen; die Beispiele in diesem Dokument dienen als Erläuterung.

**Base URL:** `https://isd.local/api/v1`
**Format:** JSON
**Authentifizierung:** Bearer Token (Laravel Sanctum)

---

## Authentifizierung

Alle Endpunkte außer `POST /auth/login` erfordern einen Bearer Token im `Authorization`-Header:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Jede Anfrage erhält zusätzlich eine **Request-ID** im Response-Header `X-Request-Id` — nützlich zur Korrelation mit den strukturierten Logs (siehe [Observability](README.md#observability)).

Authentifizierte Endpunkte sind pro Benutzer auf standardmäßig 120 Anfragen
pro Minute begrenzt. Der Wert kann über `API_RATE_LIMIT_PER_MINUTE` angepasst
werden. Bei Überschreitung antwortet die API mit `429 Too Many Requests` und
den üblichen `Retry-After`-/Rate-Limit-Headern.

### Autorisierungssemantik

- `401 Unauthorized`: Es fehlt eine gültige Anmeldung oder der Token ist abgelaufen.
- `403 Forbidden`: Die Ressource ist für den Benutzer sichtbar, die konkrete Aktion ist jedoch nicht erlaubt (beispielsweise interne Kommentare oder Asset-Historien für Requester).
- `404 Not Found`: Die Ressource existiert nicht **oder liegt außerhalb des Sichtbarkeitsbereichs des Benutzers**. Dadurch lassen sich fremde Ticket-, Asset- und private Artikel-IDs nicht enumerieren.

---

### POST /auth/login

Authentifiziert einen Benutzer und gibt einen API-Token zurück.

**Request:**
```json
{
  "email": "admin@isd.local",
  "password": "password"
}
```

**Response 200:**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "System Administrator",
    "email": "admin@isd.local"
  }
}
```

**Fehler 422** bei ungültigen Zugangsdaten.

---

### POST /auth/logout

Widerruft den aktuellen Token. **Response 200:** `{ "message": "Erfolgreich abgemeldet." }`

---

### GET /auth/me

Gibt das Profil des aktuell eingeloggten Benutzers zurück.

---

## Tickets

Jede Ticket-Route ist über `TicketPolicy` autorisiert: Requester sehen und bearbeiten ausschließlich eigene Tickets (Bearbeiten/Zuweisen/Statuswechsel ist Agent/Admin vorbehalten), interne Kommentare sind für Requester unsichtbar.

### GET /tickets

Paginierte, gefilterte Liste.

| Parameter | Typ | Beschreibung |
|---|---|---|
| `status` | string | `open`, `in_progress`, `waiting_for_requester`, `resolved`, `closed` |
| `priority` | string | `low`, `medium`, `high`, `critical` |
| `assignee_id` | integer | Nur Tickets dieses Agenten |
| `search` | string | Volltextsuche im Titel |
| `per_page` | integer | Standard: 15, erlaubt: 1–100 |

---

### POST /tickets

Erstellt ein neues Ticket, Status wird automatisch auf `open` gesetzt.
Requester dürfen nur ein Asset verknüpfen, das ihnen aktuell zugewiesen ist;
Admin und Agent dürfen jedes vorhandene Asset auswählen.

**Request:**
```json
{
  "title": "VPN funktioniert nicht",
  "description": "Detaillierte Beschreibung...",
  "priority": "medium",
  "category_id": 9,
  "asset_id": 2,
  "due_at": "2026-08-01T12:00:00+00:00"
}
```

**Response 201:** Ticket-Objekt im einheitlichen `data`-Wrapper.

---

### GET /tickets/{id}

Ticket mit allen Relationen (`data`-Wrapper). Interne Kommentare werden nur ausgeliefert, wenn der anfragende Benutzer Admin oder Agent ist.

**Fehler 403** wenn ein Requester ein fremdes Ticket abruft. **Fehler 404** wenn nicht gefunden.

---

### PATCH /tickets/{id}

Aktualisiert ein Ticket (Admin/Agent). Alle Felder optional.

**Statusübergänge folgen einer verbindlichen Matrix:**

| Von \ Nach | `open` | `in_progress` | `waiting_for_requester` | `resolved` | `closed` |
|---|:---:|:---:|:---:|:---:|:---:|
| `open` | – | ✓ | ✗ | ✓ | ✗ |
| `in_progress` | ✗ | – | ✓ | ✓ | ✗ |
| `waiting_for_requester` | ✗ | ✓ | – | ✓ | ✗ |
| `resolved` | ✗ | ✓ | ✗ | – | ✓ |
| `closed` | ✗ | ✗ | ✗ | ✗ | – |

Gilt einheitlich für alle Rollen, auch Admins. Ein unzulässiger Übergang (z. B. `open → closed` direkt) liefert **HTTP 409 Conflict**:

```json
{
  "message": "Ungültiger Statusübergang von 'open' zu 'closed'.",
  "from": "open",
  "to": "closed"
}
```

Zeitstempel-Automatik:
- → `resolved`: `resolved_at` wird gesetzt
- `resolved` → `in_progress`: `resolved_at` wird zurückgesetzt (Wiedereröffnung)
- `resolved` → `closed`: `resolved_at` bleibt erhalten, `closed_at` wird zusätzlich gesetzt

Jede Statusänderung wird in `ticket_history` protokolliert (auch automatische, siehe unten).

**Response 200:** Aktualisiertes Ticket (`data`-Wrapper).

---

### POST /tickets/{id}/comments

Fügt einen Kommentar hinzu.

```json
{
  "body": "Das Problem wurde untersucht und...",
  "is_internal": false
}
```

`is_internal: true` ist Admin/Agent vorbehalten (`TicketPolicy::commentInternally`).

**Automatischer Statuswechsel:** Antwortet der Requester öffentlich (`is_internal: false`) auf ein Ticket im Status `waiting_for_requester`, wechselt es automatisch zu `in_progress` — der Agent muss erneut aktiv werden. Interne Kommentare lösen keinen Statuswechsel aus.

**Response 201:** `{ "message": "Kommentar hinzugefügt." }`

---

## Assets

Requester sehen nur die ihnen aktuell zugewiesenen Assets. Anlegen/Bearbeiten/Zuweisen ist Agent/Admin vorbehalten, Löschen ausschließlich Admin.

### GET /assets

| Parameter | Beschreibung |
|---|---|
| `category_id` | Nur Assets dieser Kategorie |
| `status_id` | Nur Assets mit diesem Status |
| `search` | Suche in Name, Asset-Tag, Seriennummer |
| `per_page` | Standard: 15, erlaubt: 1–100 |

---

### POST /assets

Legt ein Asset an (Admin/Agent).

```json
{
  "asset_tag": "NB-010",
  "name": "Dell XPS 15",
  "asset_category_id": 4,
  "asset_status_id": 1,
  "serial_number": "DELL-XPS15-001",
  "manufacturer": "Dell",
  "model": "XPS 15 9530",
  "purchased_at": "2024-01-15",
  "warranty_until": "2027-01-15"
}
```

---

### GET /assets/{id}

Asset mit Kategorie, Status, Elternelement und aktueller Zuweisung (`data`-Wrapper).

---

### PATCH /assets/{id}

Aktualisiert ein Asset (Admin/Agent). Alle Felder optional (`sometimes`-Validierung, getrennt von `POST`). Die Unique-Prüfung für `asset_tag` ignoriert dabei das eigene Asset. `parent_asset_id` darf nicht auf das Asset selbst verweisen.

---

### DELETE /assets/{id}

Löscht ein Asset (Soft Delete). **Nur Admin.**

**Response 200:** `{ "message": "Asset NB-010 wurde gelöscht." }`

---

### POST /assets/{id}/assign

Weist ein Asset einem Benutzer zu (Admin/Agent). Eine bestehende aktive Zuweisung wird automatisch beendet — die Operation ist gegen parallele Zuweisungsversuche gesperrt (`lockForUpdate`) und zusätzlich durch einen Unique Constraint abgesichert. Dadurch kann es auch bei direkten Datenbankzugriffen nie zwei gleichzeitig aktive Zuweisungen geben.

```json
{ "user_id": 4 }
```

---

### DELETE /assets/{id}/assign

Hebt die aktuelle Zuweisung auf.

---

### GET /assets/{id}/history

Vollständige Zuweisungshistorie.

---

## Wissensdatenbank

Redaktioneller Workflow: **`draft → submitted → published → archived`**. Requester sehen veröffentlichte Artikel sowie ihre eigenen (unabhängig vom Status). Autoren dürfen ihren Entwurf nur bearbeiten, solange er noch `draft` ist.

### GET /kb/articles

| Parameter | Beschreibung |
|---|---|
| `category_id` | Nur Artikel dieser Kategorie |
| `status` | `draft`, `submitted`, `published`, `archived` |
| `tag` | Nur Artikel mit diesem Tag-Slug |
| `search` | Suche in Titel und Inhalt |
| `per_page` | Standard: 15, erlaubt: 1–100 |

---

### POST /kb/articles

Erstellt einen Artikel. Jeder authentifizierte Benutzer darf einen **Entwurf** anlegen; nur Admin/Agent können direkt mit `status: published` erstellen. Der Slug wird automatisch generiert und bei Kollision eindeutig gemacht (`titel`, `titel-2`, …).

```json
{
  "title": "VPN einrichten unter Windows 11",
  "body": "# VPN einrichten\n\n## Voraussetzungen...",
  "status": "draft",
  "category_id": 3,
  "tags": [1, 2]
}
```

**Response 201:** Artikel-Objekt im einheitlichen `data`-Wrapper.

---

### GET /kb/articles/{id}

Artikel mit Autor, Kategorie und Tags (`data`-Wrapper).

---

### PATCH /kb/articles/{id}

Aktualisiert einen Artikel. Admin/Agent jederzeit; der Autor selbst nur solange der Artikel noch `draft` ist.

---

### POST /kb/articles/{id}/submit

Reicht einen eigenen Entwurf zur redaktionellen Prüfung ein (`draft → submitted`). Nur der Autor, nur aus dem Entwurfsstatus heraus.

**Response 200:** Aktualisierter Artikel.

---

### POST /kb/articles/{id}/publish

Veröffentlicht einen Artikel (aus `draft` oder `submitted`). **Nur Admin/Agent.**

---

### POST /kb/articles/{id}/archive

Archiviert einen veröffentlichten Artikel (`published → archived`). **Nur Admin/Agent.**

---

### DELETE /kb/articles/{id}

Löscht einen Artikel (Soft Delete). Admin darf jeden Artikel löschen, Agent nur eigene, ein Requester nur den eigenen, noch unveröffentlichten Entwurf.

---

## Metriken

### GET /metrics

*(Kein `/api/v1`-Präfix; separater Bearer-Token aus `METRICS_TOKEN` erforderlich.)*

```http
Authorization: Bearer <METRICS_TOKEN>
```

Liefert Kennzahlen im Prometheus-Textformat:

```
isd_tickets_by_status{status="open"} 2
isd_http_requests_total{method="GET",route="api/v1/tickets",status="200"} 4
isd_http_request_duration_seconds_sum{method="GET",route="api/v1/tickets"} 0.00619
isd_http_request_duration_seconds_count{method="GET",route="api/v1/tickets"} 5
```

Details zur Grafana/Prometheus-Anbindung siehe [INSTALLATION.md](INSTALLATION.md#observability-anbindung).

---

## Antwortformat

### Paginierung

```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

### Fehlercodes

| Code | Bedeutung |
|---|---|
| 200 | Erfolg |
| 201 | Ressource erstellt |
| 401 | Nicht authentifiziert |
| 403 | Nicht autorisiert (z. B. fremdes Ticket, fremder Artikel-Entwurf) |
| 404 | Ressource nicht gefunden |
| 409 | Konflikt — z. B. unzulässiger Ticket-Statusübergang |
| 422 | Validierungsfehler |
| 500 | Interner Serverfehler |

---

## Enum-Werte

### Ticket-Status

| Wert | Label | Farbe |
|---|---|---|
| `open` | Offen | blue |
| `in_progress` | In Bearbeitung | amber |
| `waiting_for_requester` | Wartet auf Rückmeldung | purple |
| `resolved` | Gelöst | green |
| `closed` | Geschlossen | gray |

Siehe [Übergangsmatrix](#patch-ticketsid) oben.

### Ticket-Priorität

| Wert | Label | Farbe |
|---|---|---|
| `low` | Niedrig | gray |
| `medium` | Mittel | blue |
| `high` | Hoch | amber |
| `critical` | Kritisch | red |

### Artikel-Status

| Wert | Label |
|---|---|
| `draft` | Entwurf |
| `submitted` | Zur Prüfung eingereicht |
| `published` | Veröffentlicht |
| `archived` | Archiviert |
