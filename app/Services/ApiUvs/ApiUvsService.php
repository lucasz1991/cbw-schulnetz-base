<?php

namespace App\Services\ApiUvs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use Throwable;
use App\Models\Setting;

class ApiUvsService
{
    /** Basis-URL der API  */
    protected string $baseUrl;

    /** API-Zugangsdaten  */
    protected ?string $apiKey;


    public function __construct()
    {
        $this->baseUrl    = Setting::getValue('api', 'uvs_api_url');
        $this->apiKey     = Setting::getValue('api', 'uvs_api_key');
    }


    // =========================
    //   API Endpunkte
    // =========================

    /** Teilnehmer-Daten holen */
    public function getParticipantbyMail($mail): array
    {
        return $this->request('GET', '/api/participants', [], ['mail' => $mail]);
    }

    /** Teilnehmer-Daten mit Qualiprogram-Daten holen */
    public function getParticipantAndQualiprogrambyId($id): array
    {
        return $this->request('GET', "/api/participants/{$id}/qualiprogram");
    }

    /** Person-Status holen (person_id = "{institut_id}-{person_nr}") */
    public function getPersonStatus(string $personId): array
    {
        return $this->request('GET', '/api/person/status', [], [
            'person_id' => $personId,
        ]);
    }

    /** Tutor-Programm-Daten holen (person_id = "{institut_id}-{person_nr}") */
    public function getTutorProgramDataByPersonId(string $personId): array
    {
        return $this->request('GET', '/api/tutorprogram/person', [], [
            'person_id' => $personId,
        ]);
    }


    // =========================
    //   HTTP Helper
    // =========================

    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-KEY'     => (string) $this->apiKey,
            ]);
    }

    protected function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        try {
            $res = match (strtoupper($method)) {
                'GET'    => $this->http()->get($url, $query),
                'POST'   => $this->http()->withQueryParameters($query)->post($url, $payload),
                'PUT'    => $this->http()->withQueryParameters($query)->put($url, $payload),
                'PATCH'  => $this->http()->withQueryParameters($query)->patch($url, $payload),
                'DELETE' => $this->http()->delete($url, $query),
                default  => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            $status = $res->status();
            $json   = $res->json();

            if ($res->successful()) {
                return ['ok' => true, 'status' => $status, 'data' => $json];
            } 

            $msg = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'Request failed') : 'Request failed';
            Log::warning('Api request failed', [
                'method' => $method,
                'url'    => $url,
                'status' => $status,
                'resp'   => $json,
            ]);

            return ['ok' => false, 'status' => $status, 'message' => $msg, 'data' => $json];
        } catch (Throwable $e) {
            Log::error('Api request exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'status' => null, 'message' => $e->getMessage()];
        }
    }
}
