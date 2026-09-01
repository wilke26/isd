# ADR 0001: Domänenänderungen über die Service-Schicht

- **Status:** Akzeptiert
- **Datum:** 2026-09-01

## Kontext

Die Domäne wird über mehrere Einstiegspunkte bedient: REST-API, Filament-Panel
und künftig möglicherweise Queue-Jobs oder CLI-Kommandos. Laravel und Filament
machen einfache Eloquent-Änderungen direkt im jeweiligen Einstiegspunkt
bequem. Werden fachliche Regeln dort implementiert, können sich die
Einstiegspunkte jedoch unterschiedlich verhalten oder wichtige Sperren,
Historieneinträge und Nebenwirkungen umgehen.

Das Projekt verwendet deshalb `TicketService`, `AssetService` und
`KbArticleService` als gemeinsame Domänenschicht. Diese ADR legt fest, wann ein
neuer Einstiegspunkt zwingend diese Services verwenden muss und wann ein
direkter Eloquent-Zugriff vertretbar bleibt.

## Entscheidung

### Verantwortlichkeiten der Einstiegspunkte

Controller, Filament-Actions, Jobs und Commands sind Adapter. Sie dürfen:

1. Eingaben validieren und normalisieren,
2. den Zugriff über Policies oder Gates autorisieren,
3. einen Service aufrufen und
4. das Ergebnis in HTTP-, UI- oder Job-spezifische Ausgaben übersetzen.

Autorisierungsentscheidungen bleiben am Einstiegspunkt. Services sichern
fachliche Invarianten unabhängig davon, welcher bereits autorisierte Adapter
sie aufruft.

Bei öffentlichen oder Requester-sichtbaren Lesezugriffen werden die
sichtbarkeitsbeschränkten Service-Methoden wie `list()` und
`findVisibleToOrFail()` verwendet. Dadurch bleibt eine fremde Ressource von
einer nicht existierenden Ressource ununterscheidbar.

### Wann ein Service verpflichtend ist

Eine Operation muss durch die Service-Schicht laufen, sobald mindestens eines
der folgenden Merkmale zutrifft:

- sie verändert mehrere Datensätze oder Modelle,
- sie benötigt eine Transaktion oder Datenbanksperre,
- sie prüft oder verändert einen fachlichen Statusübergang,
- sie erzeugt Audit-, History- oder Zeitstempel-Einträge,
- sie schreibt oder löscht Dateien beziehungsweise externe Objekte,
- sie besitzt Kollisions-, Retry- oder Eindeutigkeitslogik,
- sie löst weitere fachliche Nebenwirkungen aus oder
- sie implementiert eine benutzerabhängige Sichtbarkeitsgrenze.

Diese Regeln gelten für alle Einstiegspunkte. Ein UI-Framework darf eine
Service-Methode nicht durch seine standardmäßige CRUD-Persistenz umgehen.

### Wann direkter Eloquent-Zugriff zulässig ist

Direkter Eloquent-Zugriff ist zulässig für:

- reine Staff-Leseansichten, deren Zugriff bereits durch Filament und Policies
  beschränkt ist,
- einfache Metadaten-Persistenz eines einzelnen Datensatzes ohne fachliche
  Nebenwirkung und
- technische Abfragen innerhalb eines Services.

Entsteht später auch bei einer bisher einfachen CRUD-Operation eine fachliche
Regel, werden im selben Änderungssatz **alle** Einstiegspunkte auf die
entsprechende Service-Methode umgestellt. Eine teilweise Migration ist nicht
zulässig.

## Aktuelle Zuordnung

| Bereich | Muss über Service laufen | Zulässige direkte Zugriffe |
|---|---|---|
| Tickets | Erstellen, Bearbeiten, Statuswechsel, Kommentare, Anhänge und Requester-sichtbare Abfragen | Staff-Listen und rein darstellende Filament-Abfragen |
| Assets | API-CRUD, Zuweisen, Zurückgeben, Zuweisungshistorie und Requester-sichtbare Abfragen | Einfache Filament-Pflege der Asset-Metadaten sowie Soft-Delete/Restore, solange keine zusätzliche Invariante entsteht |
| Wissensdatenbank | Erstellen, Bearbeiten, Löschen, Slug-Vergabe, Submit/Publish/Archive, Addenda und Requester-sichtbare Abfragen | Staff-Listen und rein darstellende Filament-Abfragen |

Die Asset-Zuweisung ist das wichtigste Gegenbeispiel zu direktem CRUD: Sie muss
immer `AssetService::assign()` beziehungsweise `unassign()` verwenden, weil dort
Transaktion und `lockForUpdate()` konkurrierende aktive Zuweisungen verhindern.

## Prüfliste für neue Einstiegspunkte

Vor dem Merge eines neuen Controllers, Filament-Workflows, Jobs oder Commands:

- [ ] Ist die Eingabe am Adapter validiert?
- [ ] Erfolgt die Autorisierung über eine bestehende oder neue Policy-Ability?
- [ ] Trifft eines der verpflichtenden Service-Merkmale zu?
- [ ] Verwendet der Adapter dieselbe Service-Methode wie bestehende Adapter?
- [ ] Ist eine Requester-Sichtbarkeitsgrenze serverseitig in der Abfrage verankert?
- [ ] Prüfen Service-Tests die fachliche Regel unabhängig vom Adapter?
- [ ] Prüft mindestens ein Feature-Test den neuen Einstiegspunkt einschließlich Autorisierung?

## Konsequenzen

### Positiv

- API, Filament und Hintergrundverarbeitung wenden dieselben Regeln an.
- Nebenläufigkeit, History und Storage-Fehler werden zentral behandelt.
- Domänenregeln lassen sich ohne HTTP- oder Filament-Kontext testen.
- Code-Reviews erhalten eine konkrete Prüfliste für neue Einstiegspunkte.

### Negativ

- Auch kleine neue Aktionen benötigen gelegentlich eine zusätzliche
  Service-Methode.
- Die Services hängen weiterhin direkt von Eloquent ab; sie sind keine
  frameworkunabhängige Domänenschicht.
- Autorisierung und Validierung bleiben bewusst außerhalb der Services und
  müssen von jedem Adapter korrekt aufgerufen werden.

## Verworfene Alternativen

### Geschäftslogik in Controller- oder Filament-Hooks

Das wäre kurzfristig kompakter, dupliziert Regeln aber zwischen API und Panel
und kann Sperren oder Nebenwirkungen umgehen.

### Model Observer für alle Nebenwirkungen

Observer sind für technische, modellnahe Reaktionen geeignet. Komplexe
Workflows würden dort jedoch implizit ausgelöst und wären für Aufrufer sowie
Tests schwerer nachvollziehbar.

### Zusätzliches Repository-Pattern

Ein Repository würde für die aktuelle Anwendung keinen zweiten Datenzugriffsweg
abstrahieren. Eloquent bleibt deshalb die Datenzugriffsschicht innerhalb der
Services; die Services kapseln die fachlichen Abläufe.
