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
    public string $feedback = '';

    public string $optimizedText = '';
    public string $aiComment = '';

    public bool $isLoading = false;

    public $status, $assistantName, $apiUrl, $apiKey, $aiModel, $modelTitle, $refererUrl, $trainContent;

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

    public function generateSuggestion(): void
    {
        $base = trim($this->optimizedText) !== ''
            ? $this->optimizedText
            : $this->currentText;

        if ($base === '') {
            return;
        }

        if (!$this->apiUrl || !$this->apiKey || !$this->aiModel) {
            Log::warning('AI-Konfiguration unvollständig.');
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
                        'content' => trim(preg_replace('/\s+/', ' ', (string) $this->trainContent)),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $this->buildDynamicPrompt($base, $this->feedback),
                    ],
                ],
            ]);

            $content = $response->json()['choices'][0]['message']['content'] ?? '';

            if ($content) {
                $content = preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}]/u', '', $content);

                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->optimizedText = trim((string) ($decoded['text'] ?? ''));
                    $this->aiComment     = trim((string) ($decoded['comment'] ?? ''));
                } else {
                    $this->optimizedText = trim($content);
                    $this->aiComment     = '';
                }
            }

        } catch (\Throwable $e) {
            Log::error('AI Fehler', ['message' => $e->getMessage()]);
        }

        $this->isLoading = false;
    }

    protected function buildDynamicPrompt(string $base, string $feedback): string
    {
        // KEINE Regeln, KEINE Formatvorgaben hier — alles muss in train_content stehen!
        $data = [
            'berichtText' => $base,
            'feedback'    => trim($feedback) ?: null,
        ];

        return json_encode($data);
    }

    public function useSuggestionAsBase(): void
    {
        if (!trim($this->optimizedText)) {
            return;
        }

        $this->currentText = $this->optimizedText;
        $this->feedback = '';
    }

    public function saveToEntry(): void
    {
        if (!$this->entry) return;

        $textToSave = trim($this->optimizedText) !== ''
            ? $this->optimizedText
            : $this->currentText;

        if ($textToSave === '') return;

        $this->entry->text = $textToSave;
        $this->entry->save();
    }

    public function render()
    {
        return view('livewire.tools.ai.report-book-ai-assistant');
    }
}
