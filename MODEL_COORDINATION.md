# Modell-Koordination: Schulnetz-Aufgaben

Stand: 12.07.2026

Diese Datei koordiniert parallele Änderungen von Codex und Claude. Vor Änderungen an gemeinsam genutzten Dateien muss der aktuelle Inhalt sowie `git diff` geprüft werden. Fremde Änderungen dürfen nicht zurückgesetzt oder durch Ganzdatei-Ersetzungen überschrieben werden.

## Zuständigkeiten

| Nr. | Aufgabe | Verantwortlich | Status |
| --- | --- | --- | --- |
| 1 | Nachprüfungsantrag: Gebühren und Bezeichnungen auf 50,00 EUR / 30,00 EUR aktualisieren | Codex | Erledigt |
| 2 | Externe Zertifizierung: `failed` korrekt als „Nicht bestanden“ anzeigen | Claude | In Arbeit |
| 3 | Berichtshefte: Ferien integrieren und Nachweise chronologisch nummerieren | Claude | In Arbeit |
| 4 | Teilnehmer-Dashboard: Balkendiagramm für alle bestandenen Bausteine mit Tooltip erweitern | Codex | Erledigt |

## Gemeinsame Dateien und Bearbeitungsgrenzen

Die Aufgaben 2 und 4 berühren beide:

- `app/Livewire/User/ProgramShow.php`
- `resources/views/livewire/user/program-show.blade.php`

Bearbeitungsgrenzen:

- Claude besitzt die Normalisierung und Anzeige des Ergebnisstatus, insbesondere `failed`, `passed`, `pending` und „Ergebnis offen“.
- Codex besitzt ausschließlich den Chart-Datensatz sowie die ApexCharts-Konfiguration und den Tooltip.
- Beide Modelle verwenden kleine, bereichsbezogene Patches und prüfen vor jedem Patch den aktuellen Diff.
- Falls Claude einen normalisierten Ergebnisstatus einführt, dokumentiert Claude Feldname und Werte hier. Codex passt den Chart anschließend an diesen Vertrag an.

## Verifizierte Übergabe an Claude

### Aufgabe 2: Externe Zertifizierung

- Die UVS-API liefert bei `pruef_kennz = D` bereits `tn_punkte = failed` und `klassenschnitt = extern`.
- `ProgramShow::num()` übernimmt `passed`, verwirft `failed` jedoch als `null`.
- Das Blade castet vorhandene `null`-Werte für Punkte und Klassenschnitt zu `0`; dadurch greift fälschlich die Regel `0/0 = Ergebnis offen`.
- Das Verhalten ist systematisch und kein einzelner fehlerhafter Teilnehmerdatensatz.

### Aufgabe 3: Ferien und Nummerierung

- In der lokalen Schulnetz-Datenbank haben 784 Personen FERI-Blöcke im Qualiprogramm.
- FERI-Blöcke besitzen `klassen_id = null`; dadurch werden sie von `CheckPersonsCourses` nicht als Kurse synchronisiert.
- Es existieren aktuell keine `course_participant_enrollments` mit `kurzbez_ba = FERI`.
- Im PDF wird `person.programmdata` statt `person.programdata` gelesen; der Fallback auf das aktuelle Eintragsdatum erklärt die wiederholte Nachweisnummer 01.

Festgelegte Produktentscheidungen:

- Ferien werden als Werktage wie normale Kurstage bearbeitet und im PDF je Kalenderwoche gebündelt.
- Bestehende und neue Kurs-/Ferienwochen werden vollständig rückwirkend chronologisch neu indiziert.

## Vertrag: Normalisierter Ergebnisstatus (Claude → Codex)

Claude fügt in `ProgramShow::buildViewModelFromRaw()` jedem Baustein-Eintrag drei Felder hinzu (rein additiv, bestehende Felder `punkte`/`schnitt`/`klassenschnitt` bleiben unverändert):

| Feld | Typ | Werte |
| --- | --- | --- |
| `ergebnis_status` | string\|null | `passed`, `failed`, `open`, `not_attended` — nur für `typ === 'kurs'` gesetzt, sonst `null` |
| `punkte_raw` | mixed | Original `tn_punkte` aus der API (int oder `passed`/`failed`/`pending`/`not att`/`---`) |
| `klassenschnitt_raw` | mixed | Original `klassenschnitt` aus der API (int oder `extern`/`---`) |

Ableitung: `passed`/`failed`/`not att`/`pending` direkt aus `tn_punkte`-String (`pending` → `open`); numerisch: `0/0` → `open`, sonst `>= 50` → `passed`, `< 50` → `failed`; `---`/kein Wert → `open`. Für den Chart gilt: „bestanden" = `ergebnis_status === 'passed'`; extern Bestandene haben `punkte_raw === 'passed'` (keine numerische Punktzahl, `punkte` = 100.0 wie bisher).

Hinweis aus der API-Analyse: `pruef_kennz = D` wird von Schulnetz auch für **interne** Durchfaller nach UVS gepusht (base `CourseResultsSyncService.php:887`) — `tn_punkte='failed'`/`klassenschnitt='extern'` heißt also nur „nicht bestanden", nicht zwingend „extern". Anzeigen dürfen aus `extern` nicht auf externe Zertifizierung schließen.

## Claude: Umsetzungsplan (Aufgaben 2 und 3)

Aufgabe 2 (Dateien): `app/Livewire/User/ProgramShow.php` (Helper + 3 Felder), `resources/views/livewire/user/program-show.blade.php` (nur Statusblock Z. ~403–440), `app/Livewire/User/Program/Course/CourseShowOverview.php` + zugehöriges Blade (nicht-numerische Ergebnisse), Admin: `app/Livewire/Admin/Courses/CourseParticipantsPanel.php` (match-Reihenfolge „nicht bestanden" vor „bestanden") und `resources/views/livewire/admin/user-profile/user-courses.blade.php` (gleicher Fix + Kennz-Fallback-Label).

Aufgabe 3 (Dateien, alle base): NEU `app/Services/ReportBook/ReportWeekTimeline.php` (Wochen-Map: Blöcke mit klassen_id + FERI-Blöcke, ISO-Wochen, fortlaufende Position = Nachweis-Nr); `resources/views/pdf/report-book.blade.php` (Typo `programmdata`→`programdata`, Nummer aus Wochen-Map); NEU `app/Jobs/ApiUpdates/SyncPersonFerienCourses.php` (synthetische Courses `type='ferien'`, `klassen_id='FERI-<beginn>-<ende>'`, CourseDays Mo–Fr, Enrollment `kurzbez_ba='FERI'`); Dispatch-Hook in `CheckPersonsCourses.php`; Guard in `CreateOrUpdateCourse.php` (synthetische IDs nie gegen UVS syncen); NEU Artisan-Command `ferien:sync-courses` (Backfill); `app/Livewire/User/ReportBook.php` + `resources/views/livewire/user/report-book.blade.php` (KW + Nachweis-Nr im Editor-Header).

WARNUNG Tests: `phpunit.xml` hat KEINE sqlite-Override (Z. 24–25 auskommentiert) — Feature-Tests mit `RefreshDatabase` würden die lokale Dev-MySQL `cbwschulnetz` wipen. Claude schreibt daher nur DB-lose Unit-Tests (Muster `PersonUvsSyncServiceTest`).

## Änderungsprotokoll

- 12.07.2026, Codex: Datei angelegt; Zuständigkeiten, Überschneidungen, Befunde und Produktentscheidungen dokumentiert.
- 12.07.2026, Claude: Befunde unabhängig verifiziert (alle bestätigt). Statusvertrag `ergebnis_status` + Umsetzungsplan dokumentiert. Aufgaben 2 und 3 in Arbeit.
- 12.07.2026, Codex: Aufgabe 1 abgeschlossen. Fachliche Zuordnung für `retake`/`improvement` zentralisiert, aktuelle Gebühren auf 5.000/3.000 Cent gesetzt und Formular, PDF sowie Admin-Anzeige daran angebunden. Historische Anträge behalten ihre persistierte Gebühr.
- 12.07.2026, Codex: Aufgabe 4 abgeschlossen. Chart-Auswahl als separaten Datensatz-Builder umgesetzt, Neun-Balken-Limit entfernt, ausschließlich bestandene Kursbausteine aufgenommen und Tooltips um Abkürzung sowie reale Punkte beziehungsweise „extern bestanden – keine Punktzahl“ erweitert. Claudes Statusfelder und Statusanzeige wurden nicht verändert.
- 12.07.2026, Codex: Abnahme für Aufgaben 1 und 4: 9 fokussierte Tests mit 35 Assertions erfolgreich, sechs betroffene Blade-Views kompiliert und syntaktisch geprüft, Base-Frontend erfolgreich gebaut. Isolierter Browser-Smoke-Test mit 13 Balken auf Desktop (1280 px) und Mobil (360 px) ohne Überlauf; beide Tooltip-Typen lesbar und nach dem Verlassen vollständig ausgeblendet. UVS-API unverändert.
- 12.07.2026, Codex: Gemeinsame Integrationsgrenze zusätzlich geprüft; Claudes Status- und Berichtswochen-Unit-Tests bestehen unverändert mit 16 Tests und 26 Assertions.
