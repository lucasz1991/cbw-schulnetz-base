# Gemeinsamer Datepicker

`x-ui.forms.date-input` verwendet die lokal vorhandene Flatpickr-Version und die gemeinsamen Assets `public/ui/date-picker.js` und `date-picker.css`. Die Blade-Komponente und die Assets sind in Schulnetz Base und Admin identisch. Die Layouts laden `x-ui.forms.date-picker-assets` einmal vor Alpine/Livewire.

Die Gestaltung orientiert sich am Reel von designcodewithav (Dbqfc-SDyF8): schwebende Beschriftung, Monats-/Jahresauswahl und optionaler Zeitbereich mit Spinnern. Schulnetz-Farben, deutsche Datumsanzeige, Montag als Wochenbeginn und 24-Stunden-Zeit bleiben maßgeblich. Keine neue Abhängigkeit oder externe Assetanfrage.

```blade
{{-- Datum: Serverwert Y-m-d, Anzeige d.m.Y --}}
<x-ui.forms.date-input id="datum" model="datum" label="Datum" />

{{-- Datum und Zeit in getrennten Livewire-Feldern --}}
<x-ui.forms.date-input id="start" model="schedule.start_date"
    timeModel="schedule.start_time" label="Start & Uhrzeit" />

{{-- Nur Uhrzeit: Serverwert und Anzeige H:i --}}
<x-ui.forms.date-input id="ende" model="items.0.end" :timeOnly="true" label="Ende" />

{{-- Bestehende Inline-Aufrufe bleiben möglich --}}
<x-ui.forms.date-input id="abwesenheit" model="fehlDatum" label="Datum"
    :inline="true" :required="true" :disableWeekends="true" />
```

- `enableTime` ohne `timeModel` speichert standardmäßig `Y-m-d H:i`. `timeModel` ist für Einzeltermine gedacht: ISO-Datum und `H:i` werden getrennt und gemeinsam an Livewire übergeben.
- Bestehende Optionen bleiben erhalten: `dateFormat`, `altFormat`, `altInput`, `mode` (`single`, `range`, `multiple`), `inline`, `min`, `max`, `disableWeekends`, `required`.
- Ergänzungen: `timeModel`, `timeOnly`, `minuteIncrement` (Standard 5), `disabled`, `readonly`, `name`, `value`, `timeValue`.
- Explizite Formate haben Vorrang. `min`/`max` verwenden das konfigurierte Flatpickr-Eingabeformat. Eine reine Datumsgrenze begrenzt den gesamten jeweiligen Kalendertag.
- Ein stabiler `id` erhält die Instanz bei Livewire-Aktualisierungen. Ein geänderter Modellpfad erzwingt bewusst eine neue Instanz, etwa nach Entfernen einer Zeile. Externe Werte werden ohne erneutes Change-Ereignis synchronisiert; beim Entfernen wird die Instanz zerstört.
- Die Auswahl ändert den Formularentwurf. „Fertig“ schließt nur den Picker. Speichern/Veröffentlichen bleibt Aufgabe des umgebenden Formulars; serverseitige Datums-, Berechtigungs- und Konfliktprüfung bleibt erforderlich.

## Verwendung in Modal oder Dropdown

Im `role="dialog"`-Formular einen direkten, statischen Portal-Container außerhalb des scrollenden Inhalts anlegen:

```blade
<div data-date-picker-portal wire:ignore></div>
```

Die gemeinsame `anchor-dropdown`-Komponente in Base enthält diesen Container bereits. Er hält Kalender innerhalb des Fokusbereichs, außerhalb abgeschnittener Inhalte und schützt das von Flatpickr verwaltete DOM vor Livewire-Morphs. Ohne Portal erscheint das Popup unter `document.body`; Inline-Kalender bleiben im eigenen `wire:ignore`-Element.

Escape schließt zuerst den Picker. Monats-/Jahresauswahl, Zeit-Spinner und Kalender sind per Tastatur bedienbar. Auf schmalen Bildschirmen steht die Uhrzeit unter dem Kalender. Für feste untere UI-Leisten kann `--dp-viewport-bottom` in Pixeln zusätzlichen Platz reservieren.

## Nachweise

`tests/Feature/DateInputComponentTest.php` prüft bestehende Format-/Limitverträge, getrennte Modelle, Escaping, deaktivierte Felder und Modellwechsel. Coaching-Verhalten wird weiterhin durch `CoachingWorkflowTest.php` geprüft. Browserprüfung der tatsächlichen Assets erfolgt in der separaten lokalen Vorschau; sie ersetzt keine authentifizierte Livewire-E2E-Abnahme.
