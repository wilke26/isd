# API-Dokumentation

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

Widerruft den aktuellen Token.

**Response 200:**
```json
{ "message": "Erfolgreich abgemeldet." }
```

---

### GET /auth/me

Gibt das Profil des aktuell eingeloggten Benutzers zurück.

**Response 200:**
```json
{
  "id": 1,
  "name": "System Administrator",
  "email": "admin@isd.local"
}
```

---

## Tickets

### GET /tickets

Gibt eine paginierte Liste von Tickets zurück. Admins und Agents sehen alle Tickets; normale Benutzer nur ihre eigenen.

**Query-Parameter:**

| Parameter | Typ | Beschreibung |
|---|---|---|
| `status` | string | `open`, `in_progress`, `resolved`, `closed` |
| `priority` | string | `low`, `medium`, `high`, `critical` |
| `assignee_id` | integer | Nur Tickets dieses Agenten |
| `search` | string | Volltextsuche im Titel |
| `per_page` | integer | Ergebnisse pro Seite (Standard: 15) |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Laptop startet nicht mehr",
      "description": "...",
      "status": { "value": "in_progress", "label": "In Bearbeitung", "color": "amber" },
      "priority": { "value": "high", "label": "Hoch", "color": "amber" },
      "requester": { "id": 4, "name": "Clara Weber", "email": "c.weber@isd.local" },
      "assignee": { "id": 2, "name": "Anna Müller", "email": "a.mueller@isd.local" },
      "category": { "id": 5, "name": "Laptop/PC" },
      "asset": { "id": 1, "asset_tag": "NB-001", "name": "MacBook Pro 14\"" },
      "due_at": "2026-07-29T08:33:08+00:00",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "2026-07-28T08:33:08+00:00",
      "updated_at": "2026-07-28T08:33:08+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 4
  }
}
```

---

### POST /tickets

Erstellt ein neues Ticket. Der eingeloggte Benutzer wird automatisch als `requester` gesetzt.

**Request:**
```json
{
  "title": "VPN funktioniert nicht",
  "description": "Detaillierte Beschreibung des Problems...",
  "priority": "medium",
  "category_id": 9,
  "asset_id": 2,
  "due_at": "2026-08-01T12:00:00+00:00"
}
```

| Feld | Pflicht | Typ | Beschreibung |
|---|---|---|---|
| `title` | ✓ | string | max. 255 Zeichen |
| `description` | ✓ | string | Problembeschreibung |
| `priority` | | string | `low`, `medium` (Standard), `high`, `critical` |
| `category_id` | | integer | ID aus `/ticket-categories` |
| `asset_id` | | integer | Betroffenes Asset |
| `due_at` | | datetime | ISO 8601, muss in der Zukunft liegen |

**Response 201:** Ticket-Objekt (ohne `data`-Wrapper)

---

### GET /tickets/{id}

Gibt ein einzelnes Ticket mit allen Relationen zurück.

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "title": "...",
    "comments": [...],
    "attachments": [...],
    "history": [
      {
        "id": 1,
        "field": "status",
        "old_value": "open",
        "new_value": "in_progress",
        "user": { "id": 2, "name": "Anna Müller", "email": "a.mueller@isd.local" },
        "created_at": "2026-07-28T09:00:00+00:00"
      }
    ]
  }
}
```

**Fehler 404** wenn Ticket nicht gefunden.

---

### PATCH /tickets/{id}

Aktualisiert ein Ticket. Alle Felder sind optional.

**Request:**
```json
{
  "status": "in_progress",
  "assignee_id": 2,
  "priority": "high"
}
```

Statusübergänge lösen automatisch Zeitstempel aus:
- `resolved` → `resolved_at` wird gesetzt
- `closed` → `closed_at` wird gesetzt
- Rücksetzen auf `open` → Zeitstempel werden gelöscht

Alle Änderungen an `status`, `priority` und `assignee_id` werden in `ticket_history` protokolliert.

**Response 200:** Aktualisiertes Ticket-Objekt (in `data`-Wrapper)

---

### POST /tickets/{id}/comments

Fügt einen Kommentar zum Ticket hinzu.

**Request:**
```json
{
  "body": "Das Problem wurde untersucht und...",
  "is_internal": false
}
```

| Feld | Pflicht | Beschreibung |
|---|---|---|
| `body` | ✓ | Kommentartext |
| `is_internal` | | `true` = nur für Agents sichtbar (Standard: `false`) |

**Response 201:**
```json
{ "message": "Kommentar hinzugefügt." }
```

---

## Assets

### GET /assets

Paginierte Asset-Liste mit Filteroptionen.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `category_id` | Nur Assets dieser Kategorie |
| `status_id` | Nur Assets mit diesem Status |
| `search` | Suche in Name, Asset-Tag und Seriennummer |
| `per_page` | Ergebnisse pro Seite (Standard: 15) |

**Response 200:** Paginierte Liste mit `data`-Array und `meta`.

---

### POST /assets

Legt ein neues Asset an.

**Request:**
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

| Feld | Pflicht | Beschreibung |
|---|---|---|
| `asset_tag` | ✓ | Eindeutige Inventarnummer (max. 50 Zeichen) |
| `name` | ✓ | Bezeichnung |
| `asset_category_id` | ✓ | Kategorie-ID |
| `asset_status_id` | ✓ | Status-ID |
| `parent_asset_id` | | Übergeordnetes Asset (für Hierarchien) |

**Response 201:** Asset-Objekt

---

### GET /assets/{id}

Gibt ein einzelnes Asset mit Kategorie, Status, Elternelement und aktueller Zuweisung zurück.

**Response 200:** Asset-Objekt in `data`-Wrapper.

---

### PATCH /assets/{id}

Aktualisiert ein Asset (alle Felder optional, gleiche Validierung wie POST).

---

### POST /assets/{id}/assign

Weist ein Asset einem Benutzer zu. Eine bestehende aktive Zuweisung wird automatisch beendet.

**Request:**
```json
{ "user_id": 4 }
```

**Response 200:**
```json
{ "message": "Asset NB-010 wurde Clara Weber zugewiesen." }
```

---

### DELETE /assets/{id}/assign

Hebt die aktuelle Zuweisung auf (`returned_at` wird gesetzt).

**Response 200:**
```json
{ "message": "Zuweisung für NB-010 aufgehoben." }
```

---

### GET /assets/{id}/history

Gibt die vollständige Zuweisungshistorie eines Assets zurück.

**Response 200:**
```json
[
  {
    "user": { "id": 4, "name": "Clara Weber" },
    "assigned_at": "2026-04-28T08:33:08+00:00",
    "returned_at": null
  }
]
```

---

## Wissensdatenbank

### GET /kb/articles

Paginierte Artikel-Liste. Normale Benutzer sehen nur veröffentlichte Artikel; Agents und Admins auch Entwürfe.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `category_id` | Nur Artikel dieser Kategorie |
| `status` | `draft`, `published`, `archived` |
| `tag` | Nur Artikel mit diesem Tag-Slug |
| `search` | Suche in Titel und Inhalt |
| `per_page` | Ergebnisse pro Seite (Standard: 15) |

---

### POST /kb/articles

Erstellt einen neuen Artikel. Der eingeloggte Benutzer wird als Autor gesetzt.

**Request:**
```json
{
  "title": "VPN einrichten unter Windows 11",
  "body": "# VPN einrichten\n\n## Voraussetzungen...",
  "status": "draft",
  "category_id": 3,
  "tags": [1, 2]
}
```

Der `slug` wird automatisch aus dem `title` generiert. Bei `status: published` wird `published_at` automatisch auf den aktuellen Zeitstempel gesetzt.

**Response 201:** Artikel-Objekt (ohne `data`-Wrapper)

---

### GET /kb/articles/{id}

**Response 200:** Artikel mit Autor, Kategorie und Tags (in `data`-Wrapper).

---

### PATCH /kb/articles/{id}

Aktualisiert einen Artikel. Bei Änderung des Titels wird der Slug neu generiert.

**Response 200:** Aktualisierter Artikel (in `data`-Wrapper).

---

### DELETE /kb/articles/{id}

Löscht einen Artikel (Soft Delete — der Eintrag bleibt in der Datenbank, ist aber nicht mehr abrufbar).

**Response 200:**
```json
{ "message": "Artikel gelöscht." }
```

---

## Antwortformat

### Paginierung

Listen-Endpunkte geben immer folgende Struktur zurück:

```json
{
  "data": [...],
  "links": {
    "first": "https://isd.local/api/v1/tickets?page=1",
    "last": "https://isd.local/api/v1/tickets?page=3",
    "prev": null,
    "next": "https://isd.local/api/v1/tickets?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

### Fehlercodes

| Code | Bedeutung |
|---|---|
| 200 | Erfolg |
| 201 | Ressource erstellt |
| 401 | Nicht authentifiziert (kein oder ungültiger Token) |
| 403 | Nicht autorisiert (Token gültig, aber fehlende Berechtigung) |
| 404 | Ressource nicht gefunden |
| 422 | Validierungsfehler |
| 500 | Interner Serverfehler |

### Validierungsfehler (422)

```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."],
    "description": ["The description field is required."]
  }
}
```

---

## Enum-Werte

### Ticket-Status

| Wert | Label | Farbe |
|---|---|---|
| `open` | Offen | blue |
| `in_progress` | In Bearbeitung | amber |
| `resolved` | Gelöst | green |
| `closed` | Geschlossen | gray |

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
| `published` | Veröffentlicht |
| `archived` | Archiviert |
