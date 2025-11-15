<?php

namespace App\Livewire\Tools\Ai;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\ReportBookEntry;

class ReportBookAiAssistant extends Component
{
    public bool $showModal = false;

    public ?ReportBookEntry $entry = null;

    /** Text, der aktuell als Basis dient (ursprünglicher oder bereits optimierter Text) */
    public string $currentText = '';

    /** Zusätzliches Feedback / Wünsche an die KI */
    public string $feedback = '';

    /** Von der KI optimierter Text für das Berichtsheft */
    public string $optimizedText = '';

    /** Kurzer Kommentar der KI (Erklärung / Hinweise) */
    public string $aiComment = '';

    public bool $isLoading = false;

    // AI-Konfiguration
    public $status, $assistantName, $apiUrl, $apiKey, $aiModel, $modelTitle, $refererUrl, $trainContent;

    protected $listeners = [
        // Event aus anderen Komponenten: $dispatch('open-reportbook-ai-assistant', { id: entryId })
        'open-reportbook-ai-assistant' => 'openForEntry',
    ];

    public function mount(): void
    {
        // AI-Settings wie im Chatbot laden
        $this->status        = Setting::getValue('ai_assistant', 'status');
        $this->assistantName = Setting::getValue('ai_assistant', 'assistant_name');
        $this->apiUrl        = Setting::getValue('ai_assistant', 'api_url');
        $this->apiKey        = Setting::getValue('ai_assistant', 'api_key');
        $this->aiModel       = Setting::getValue('ai_assistant', 'ai_model');
        $this->modelTitle    = Setting::getValue('ai_assistant', 'model_title');
        $this->refererUrl    = Setting::getValue('ai_assistant', 'referer_url');
        $this->trainContent  = Setting::getValue('ai_assistant', 'train_content');
    }

    /**
     * Wird per Event mit einer ReportBookEntry-ID geöffnet.
     */
    public function openForEntry($payload): void
    {
        $id = (int) ($payload['id'] ?? $payload);

        $this->entry = ReportBookEntry::findOrFail($id);

        $this->currentText  = (string) ($this->entry->text ?? '');
        $this->optimizedText = '';
        $this->aiComment     = '';
        $this->feedback      = '';

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    /**
     * KI-Vorschlag erzeugen oder weiter verbessern.
     * Basis: vorhandener optimierter Text, sonst currentText.
     */
    public function generateSuggestion(): void
    {
        $base = trim($this->optimizedText) !== ''
            ? $this->optimizedText
            : $this->currentText;

        if ($base === '') {
            return;
        }

        if (!$this->apiUrl || !$this->apiKey || !$this->aiModel) {
            Log::warning('ReportBookAiAssistant: AI-Konfiguration unvollständig.');
            return;
        }

        $this->isLoading = true;

        $userPrompt = $this->buildUserPrompt($base, $this->feedback);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'HTTP-Referer'  => $this->refererUrl,
                'X-Title'       => $this->modelTitle,
                'Content-Type'  => 'application/json',
            ])->post($this->apiUrl, [
                'model'    => $this->aiModel,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => trim(preg_replace('/\s+/', ' ', (string) $this->trainContent)),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            if ($content) {
                // Nicht-deutsche Schriften (z.B. asiatische Schriftzeichen) rausfiltern
                $content = preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}]/u', '', $content);

                // Erwartet JSON: {"text":"...","comment":"..."}
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->optimizedText = trim((string) ($decoded['text'] ?? ''));
                    $this->aiComment     = trim((string) ($decoded['comment'] ?? ''));
                } else {
                    // Fallback: kompletter Inhalt als Text, kein Kommentar
                    $this->optimizedText = trim($content);
                    $this->aiComment     = '';
                }
            }
        } catch (\Throwable $e) {
            Log::error('ReportBookAiAssistant: API-Fehler', [
                'message' => $e->getMessage(),
            ]);
        }

        $this->isLoading = false;
    }

    /**
     * Nimmt den aktuellen Vorschlag als neuen "Basistext" für weitere Verbesserungen.
     */
    public function useSuggestionAsBase(): void
    {
        if (!trim($this->optimizedText)) {
            return;
        }

        $this->currentText = $this->optimizedText;
        // Feedback kann stehen bleiben, oder geleert werden – ich leere es:
        $this->feedback = '';
        session()->flash('reportbook_ai_info', 'Der KI-Vorschlag wird jetzt als Grundlage für weitere Verbesserungen verwendet.');
    }

    /**
     * Speichert den optimierten Text in den eigentlichen ReportBookEntry.
     */
    public function saveToEntry(): void
    {
        if (!$this->entry) {
            return;
        }

        $textToSave = trim($this->optimizedText) !== ''
            ? $this->optimizedText
            : $this->currentText;

        if ($textToSave === '') {
            return;
        }

        $this->entry->text = $textToSave;
        $this->entry->save();

        $this->currentText = $textToSave;

        session()->flash('reportbook_ai_saved', 'Der optimierte Text wurde im Berichtsheft-Eintrag gespeichert.');
    }

    /**
     * Prompt für das Modell bauen – mit JSON-Schema-Anweisung.
     */
    protected function buildUserPrompt(string $baseText, string $feedback): string
    {
        $feedback = trim($feedback);

        $prompt = <<<TXT
                    Du unterstützt Auszubildende dabei, einen Berichtsheft-Eintrag in gut lesbares, korrektes und verständliches Deutsch zu formulieren.

                    Bitte:

                    1. Überarbeite den Text sprachlich und stilistisch, ohne neue Inhalte zu erfinden.
                    2. Schreibe in der Ich-Perspektive, sachlich und ausbildungsbezogen.
                    3. Halte die Länge ungefähr passend für einen typischen Tages- oder Wocheneintrag.
                    4. Achte auf korrekte Rechtschreibung und Grammatik.

                    Antworte AUSSCHLIESSLICH mit gültigem JSON im folgenden Format (ohne Erklärungstext davor oder danach):

                    {
                    "text": "OPTIMIERTER BERICHTSHEFTTEXT AUF DEUTSCH",
                    "comment": "KURZE ERKLÄRUNG / HINWEISE FÜR DIE PERSON"
                    }

                    Berichtstext:
                    "{$baseText}"
                    TXT;

        if ($feedback !== '') {
            $prompt .= "\n\nZusätzliche Wünsche / Verbesserungswünsche der Person:\n\"{$feedback}\"";
        }

        return $prompt;
    }

    public function render()
    {
        return view('livewire.tools.ai.report-book-ai-assistant');
    }
}
