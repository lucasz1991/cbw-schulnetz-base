# Einzelcoaching aktivieren

Im Schulnetz-Adminbereich unter **Konfiguration → Einzelcoaching** den Schalter
**Einzelcoaching im Schulnetz aktivieren** setzen und speichern. Der Tab und die
Speicheraktion sind wie der API-Tab auf die Superadmin-Rolle `admin` beschränkt.

Base und Admin verwenden den gemeinsamen Datensatz `settings` mit
`type=coaching`, `key=enabled` und einem JSON-Boolean. Ohne Eintrag ist die
Funktion ausgeschaltet. Es ist keine neue Migration erforderlich.

Die frühere ENV-Variable `COACHING_ENABLED` wird nicht mehr ausgewertet. Nach
einem Update die gewünschte Freigabe im Adminbereich einmal speichern; eine
alte ENV-Freigabe wird nicht automatisch übernommen. Base und Admin müssen
dieselbe Schulnetz-Datenbank verwenden. Vorhandene Coaching-Migrationen und
die UVS-API-Verbindung bleiben Voraussetzung.

Menüs, Planung, Portal-Zugangsprüfung und `coaching:sync` lesen den aktuellen
Wert aus der Datenbank, unabhängig von getrennten Anwendungs-/Konfigurationscaches.
Auch der fünfminütige Scheduler in Base berücksichtigt den Schalter. Der
Scheduler selbst muss wie bisher auf dem Server eingerichtet sein.

Deaktivieren sperrt die Coaching-Planung und verhindert weitere Abgleichstarts.
Vorhandene Verträge, Pläne und Bausteindaten werden nicht gelöscht; bereits
laufende Abgleiche werden nicht abgebrochen. Die bestehende Dokumentation von
Bausteinen wird dadurch nicht rückgängig gemacht.

Die zusätzliche Auswahl des neuen Verfahrens am einzelnen UVS-Vertrag und
die Beschränkung dieser UVS-Auswahl auf Admin-ID 1 bleiben bestehen.


## Erweiterung vom 22.09.2026
Für Systemmitteilungen und E-Mails ist zusätzlich die neue Base-Migration `2026_09_22_100000_create_coaching_notices.php` erforderlich. Die Settings-Tabelle selbst benötigt weiterhin keine Migration. Einrichtung und Ereignisse: `docs/coaching-notifications.md`.
