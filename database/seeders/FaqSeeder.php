<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WebContent;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                // Beispielinhalte für verschiedene Seiten
                $contents = [        
                    ['key' => 'Was unterscheidet RegulierungsCheck von Trustpilot oder Google Reviews?', 'value' => 'Wir fokussieren uns ausschließlich auf die Schadensregulierung – den entscheidenden Moment einer Versicherung. Keine allgemeinen Sternebewertungen, sondern echte Erfahrungswerte mit Regulierungsdauer und Fairness.', 'type' => 'faq'],
                    ['key' => 'Warum braucht es Transparenz bei Versicherungen?', 'value' => 'Weil Versicherungen im Ernstfall liefern müssen. Wir zeigen, wie sie das tatsächlich tun –
auf Basis realer Daten.', 'type' => 'faq'],
                    ['key' => 'Wie entsteht der Durchschnittswert bei einer Versicherung?', 'value' => 'Durchschnitt aus allen gemeldeten Regulierungsdauern in Tagen plus Bewertung zur Fairness. So entsteht ein realistisches Bild.', 'type' => 'faq'],
                    ['key' => 'Kann ich mehrere Schäden oder Versicherungen bewerten?', 'value' => 'Ja, du kannst für jede Schadensart oder Versicherung eine eigene Bewertung abgeben.', 'type' => 'faq'],
                    ['key' => 'Wie wird Missbrauch verhindert (z.B. Fake-Bewertungen)?', 'value' => 'Wir setzen automatisierte Prüfmechanismen ein, filtern Mehrfachnennungen und reagieren auf Hinweise aus der Community.', 'type' => 'faq'],
                    ['key' => 'Kann eine Versicherung ihre Bewertungen manipulieren?', 'value' => 'Nein. Es gibt keine Möglichkeit, Bewertungen zu kaufen, zu löschen oder zu verändern.', 'type' => 'faq'],
                    ['key' => 'Besteht ein rechtliches Risiko, wenn ich eine schlechte Erfahrung teile?', 'value' => 'Nein, solange du deine eigene Erfahrung schilderst, sachlich bleibst und keine Beleidigungen oder falschen Tatsachen verbreitest.', 'type' => 'faq'],
                    ['key' => 'Wie wird meine Anonymität garantiert?', 'value' => 'Wir speichern keine Namen, keine Policenummern und keine personenbezogenen Daten. Deine Bewertung bleibt anonym.', 'type' => 'faq'],
                    ['key' => 'Was plant RegulierungsCheck für die Zukunft?', 'value' => 'Mehr Schadensarten, Vergleichsfilter, API-Zugänge für Medien und langfristig eine Echtzeit-Datenbank für Verbraucherschutz.', 'type' => 'faq'],
                    ['key' => 'Wie kann ich euch unterstützen?', 'value' => 'Bewerten, teilen, Feedback geben – jede Stimme macht die Plattform besser und sichtbarer.', 'type' => 'faq'],
                   
                ];
        
                foreach ($contents as $content) {
                    WebContent::updateOrCreate(['key' => $content['key']], $content);
                }
    }
}
