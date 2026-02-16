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

        /** Kurse/Klassen suchen (entspricht GET /api/course-classes) */
    public function getCourseClasses(
        ?string $search = null,
        ?int $limit = null,
        ?string $from = null,   // Format: Y-m-d
        ?string $to   = null,    // Format: Y-m-d (>= from)
        ?string $sort = null,  // z.B. 'bezeichnung'
        ?string $order = null   // 'asc' oder 'desc'
    ): array {
        $query = [];

        if (!is_null($search) && trim($search) !== '') {
            $query['search'] = $search;
        }

        if (!is_null($limit)) {
            $query['limit'] = max(1, min(100, $limit));
        }

        if (!is_null($from)) {
            $query['from'] = $from;
        }

        if (!is_null($to)) {
            $query['to'] = $to;
        }

        if (!is_null($sort)) {
            $query['sort'] = $sort;
        }

        if (!is_null($order) && in_array(strtolower($order), ['asc', 'desc'], true)) {
            $query['order'] = strtolower($order);
        }

        return $this->request('GET', '/api/course-classes', [], $query);
    }


    /** Teilnehmer einer Klasse laden (entspricht GET /api/course-classes/participants) */
    public function getCourseClassParticipants(string $courseClassId): array
    {
        return $this->request('GET', '/api/course-classes/participants', [], [
            'course_class_id' => $courseClassId,
        ]);
    }

    public function getCourseByKlassenId(string $klassenId): array
    {
        return $this->request('GET', '/api/course/coursebyklassenid', [], [
            'klassen_id' => $klassenId,
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

    public function request(string $method, string $path, array $payload = [], array $query = []): array
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
                'message' => $msg,
                'payload' => $payload,
                'query'   => $query,

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
