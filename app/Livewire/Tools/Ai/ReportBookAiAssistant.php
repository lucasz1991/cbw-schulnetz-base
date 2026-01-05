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

    public string $currentText = '';
    public string $feedback    = '';

    public string $optimizedText = '';
    public string $aiComment     = '';

    public bool $isLoading = false;

    public $status;
    public $assistantName;
    private $apiUrl;
    private $apiKey;
    private $aiModel;
    private $modelTitle;
    private $refererUrl;
    private $trainContent;

    protected $listeners = [
        'open-reportbook-ai-assistant' => 'openForEntry',
    ];

    public function mount(): void
    {
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
     * Kann entweder mit einer ID oder mit Payload ['id' => X] aufgerufen werden.
     */
    public function openForEntry($payload = null): void
    {
        if (is_numeric($payload)) {
            $id = (int) $payload;
        } elseif (is_array($payload) && isset($payload['id'])) {
            $id = (int) $payload['id'];
        } else {
            return;
        }

        $this->entry = ReportBookEntry::findOrFail($id);

        $this->currentText   = (string) ($this->entry->text ?? '');
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
     * Ruft die AI auf und erwartet JSON mit "text" (HTML erlaubt) und "comment".
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
                        // kompletter Prompt inkl. HTML-Regeln kommt aus den Settings
                        'content' => trim(preg_replace('/\s+/', ' ', (string) $this->trainContent)),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $this->buildDynamicPrompt($base, $this->feedback),
                    ],
                ],
            ]);

            $json = $response->json();

            if (!isset($json['choices'][0]['message']['content'])) {
                Log::warning('ReportBookAiAssistant: Unerwartete API-Antwortstruktur.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return;
            }

            $content = $json['choices'][0]['message']['content'];

            // Nicht-deutsche Scriptblöcke rausfiltern, falls das Modell ausrastet
            $content = preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}]/u', '', $content);

            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->optimizedText = trim((string) ($decoded['text'] ?? ''));
                $this->aiComment     = trim((string) ($decoded['comment'] ?? ''));
            } else {
                // Fallback: wenn die KI doch reinen Text zurückgibt
                $this->optimizedText = trim($content);
                $this->aiComment     = '';
                Log::notice('ReportBookAiAssistant: Antwort war kein valides JSON, Fallback auf Raw-Text.', [
                    'content' => $content,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ReportBookAiAssistant: AI Fehler', [
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Baut die User-Message – nur Daten, keine Regeln.
     * Die KI weiß aus train_content, dass "berichtText" HTML enthalten kann
     * und wie es zu verarbeiten ist.
     */
    protected function buildDynamicPrompt(string $base, string $feedback): string
    {
        return json_encode([
            'berichtText' => $base,                   // kann HTML enthalten
            'feedback'    => trim($feedback) ?: null, // optionale Wünsche
        ]);
    }

    /**
     * Übernimmt den AI-Vorschlag als neuen Basistext und blendet das Ergebnis wieder aus.
     */
    public function useSuggestionAsBase(): void
    {
        if (!trim($this->optimizedText)) {
            return;
        }

        $this->currentText   = $this->optimizedText;
        $this->feedback      = '';
        $this->optimizedText = '';
        $this->aiComment     = '';
    }

    /**
     * Speichert den finalen Text (HTML erlaubt) in den ReportBookEntry.
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
    }

    public function render()
    {
        return view('livewire.tools.ai.report-book-ai-assistant');
    }
}
