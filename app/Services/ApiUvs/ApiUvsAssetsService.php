<?php

namespace App\Services\ApiUvs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiUvsAssetsService
{
    protected ApiUvsService $api;

    // Cache-Zeit in Sekunden (z. B. 6 Stunden)
    protected int $ttl = 60 * 60 * 6;

    public function __construct(ApiUvsService $api)
    {
        $this->api = $api;
    }

    /**
     * Alle Institute aus der UVS-API laden (mit optionalem Caching)
     *
     */
    public function getInstitutionsInfos(bool $refresh = false) : array
    {
        $cacheKey = 'uvs_assets_institutions_infos';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->ttl, function () {
            $response = $this->api->request('GET', '/api/assets/institutions');
            if (!($response['ok'] ?? false)) {
                return [];
            }
            $data = $response['data']['data'] ?? [];
            return collect($data)
                ->filter(fn ($row) => isset($row['institut_id']))
                ->keyBy('institut_id')
                ->toArray();
        });
    }

    /**
     * Status-Optionen abrufen (mit optionalem Caching)
     */
    public function getTestResultStatusOptions(bool $refresh = false): array
    {
        return Cache::remember('uvs_assets_test_result_status_options', $this->ttl, function () {
            
            $response = $this->api->request('GET', '/api/assets/pruef-kennz-options');

            return $response['ok'] ? ($response['data'] ?? []) : [];
        });
    }
}
