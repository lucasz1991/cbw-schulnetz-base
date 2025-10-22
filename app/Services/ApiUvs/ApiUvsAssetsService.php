<?php

namespace App\Services\ApiUvs;

use Illuminate\Support\Facades\Cache;

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
