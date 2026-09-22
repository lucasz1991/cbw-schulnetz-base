# Einzelcoaching: Systemmitteilungen, E-Mails und Registrierung

## Ablauf
1. Ein UVS-Mitarbeiter legt den Vertragsentwurf mit Modulen und Dozent an und aktiviert das neue Verfahren (UVS weiterhin nur Admin-ID 1).
2. Der API-Abgleich legt für Teilnehmer und Dozent jeweils eine Planungsaufforderung an. Bekannte verknüpfte Konten erhalten eine interne Systemmitteilung von Benutzer 1 und eine E-Mail. Ohne Konto wird an die eindeutige UVS-E-Mail eine Einladung zur normalen Registrierung geschickt.
3. Nach Registrierung mit derselben E-Mail und UVS-Personenidentität werden Konten zugeordnet und interne Mitteilungen nachgeliefert. Der normale Passwort-Link folgt erst nach erfolgreicher Kontotransaktion. Auch ein reiner Coaching-Entwurf erlaubt den Planungszugang; der Unterrichtsbaustein bleibt noch gesperrt.
4. Beide Seiten bestätigen alle Termine. Der Gesamtplan wird per API an UVS zurückgegeben; die erfolgreiche Übernahme löst eine eigene Mitteilung aus.
5. Erst ein Mitarbeiter erstellt/aktiviert den finalen Teilnehmervertrag im UVS. Die UVS-Umwandlung prüft den empfangenen vollständigen Plan. Schulnetz übernimmt den Status, prüft Plan-Hash und unveränderten Umfang, erzeugt den normalen Baustein und benachrichtigt beide Seiten über die Freigabe.

## Ereignisse
| Ereignis | Empfänger |
|---|---|
| Planungsaufforderung / Registrierungseinladung | Teilnehmer und Dozent |
| Neuer Gesamtplan | Gegenüber des Vorschlagenden |
| Erste Bestätigung | Andere Seite |
| Beidseitige Bestätigung, Übertragung ausstehend | Beide |
| Erfolgreiche UVS-Übertragung | Beide |
| Finale Vertrags- und Bausteinfreigabe, erster Termin | Beide, mit rollenspezifischen Aufgaben |
| Geänderter Umfang / unbestätigter Plan ungültig | Beide |
| Dozent entfernt | Bisheriger Dozent, ohne Link zum entzogenen Vorgang |
| Neuer Dozent | Neuer Dozent und Teilnehmer |
| Nach Bestätigung abweichender Vertragsumfang | Beide; Prüfung durch Verwaltung erforderlich |
| Deaktivierung, Storno oder Kündigung | Beide |
| Wiederfreigabe des Vorgangs | Beide |
| Neue Chatnachricht | Andere Seite, ohne Chatinhalt in der E-Mail |

Die Texte stehen zentral in `app/Services/Coaching/NoticeService.php`. E-Mails nutzen `CoachingNotification` und das bestehende Schulnetz-Layout. In Admin liegt eine auf Coaching beschränkte Kopie des Base-Mail-Layouts unter `resources/views/coaching-mail`; andere Admin-Mails behalten ihr Layout. Bei Änderungen des Base-Layouts ist diese Kopie mitzuführen.

## Zustellung und Grenzen
- `coaching_notices` speichert pro Ereignis/Empfänger einen eindeutigen Schlüssel, getrennte Nachweise für interne Mitteilung und Mail, Wiederholungszeit und bereinigten Fehlerstatus.
- Die Datenbank-Zeilensperre koordiniert Base/Admin auch bei getrennten Caches. Interne Mitteilungen werden bei Mailfehlern nicht doppelt angelegt. Bereits versandte E-Mails werden nicht erneut versandt, wenn später das Konto verknüpft wird.
- Verspätete überholte Vorschlags-/Bestätigungsnachrichten sowie Nachrichten an nicht mehr zugeordnete Empfänger werden verworfen. Alte bereits zugestellte Mitteilungen bleiben erhalten.
- Registrierung erzeugt keinen künstlich aktivierten Account und verknüpft nicht allein anhand gleicher E-Mail. Institut, UVS-Personen-ID und Dozentenrolle bleiben maßgeblich. Gleiche Mail für beide unregistrierten Rollen sowie mehrdeutige oder ungültige UVS-Mailangaben erfordern eine Korrektur.
- Queue-Job `DeliverCoachingNotices` wird erst nach Datenbank-Commit gestartet. Scheduler/`coaching:notify` übernimmt offene Zustellungen unabhängig von vorübergehenden API-Ausfällen. Wiederholungsabstand steigt bis maximal 60 Minuten.
- Im Adminbereich Einzelcoaching sind offene Zustellungen und Fehler pro Rolle sichtbar; „Erneut abgleichen“ stößt eine Wiederholung an.
- Ein gesetztes `mail_sent_at` bedeutet erfolgreiche Übergabe an den konfigurierten Mailtransport, nicht bestätigten Eingang im Postfach. Ein Prozessabbruch zwischen SMTP-Annahme und Datenbank-Commit kann eine erneute Mail verursachen; SMTP bietet dafür keine transaktionale Genau-einmal-Garantie.

## Installation
Base, Admin, UVS-API und UVS gemeinsam aktualisieren. In **Base** die Migration `2026_09_22_100000_create_coaching_notices.php` ausführen (üblicher Deployment-Aufruf `php artisan migrate --force`). Keine Migration in Admin und keine neue UVS-SQL-Migration.

Base/Admin müssen dieselbe Schulnetz-Datenbank verwenden. `api.base_api_url` muss auf das Teilnehmer-/Dozentenportal zeigen. Systembenutzer ID 1, erreichbare UVS-API, korrekte SMTP-Konfiguration und Queue-/Scheduler-Betrieb werden benötigt. In Base wird `coaching:notify` jede Minute, `coaching:sync` alle fünf Minuten eingeplant. Lang laufende Queue-Worker nach Deployment regulär neu starten.

Die globale Freigabe erfolgt ausschließlich über Konfiguration → Einzelcoaching (Superadmin-Rolle admin); ohne Freigabe kein neuer Abgleich/Versand. Die Erweiterung wird bei fehlender neuer Base-Migration nicht freigeschaltet.

## Abnahme
Mit getrennten Testkonten echte Registrierung, Passwort-E-Mail, gegenseitige Planung, UVS-Rückmeldung, Mitarbeiterfreigabe, Baustein und Inbox/Postfach prüfen. Lokale Tests verwenden isoliertes SQLite und fangen Benachrichtigungen ab; sie ersetzen diese Zielabnahme nicht.
