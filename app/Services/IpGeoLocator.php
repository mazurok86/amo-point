<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IpGeoLocator
{
    private const CACHE_TTL_SECONDS = 86400;

    private const HTTP_TIMEOUT_SECONDS = 1;

    private const ENDPOINT = 'http://ip-api.com/json/';

    /**
     * @return array{country: ?string, city: ?string}
     */
    public function resolve(string $ip): array
    {
        if ($ip === '' || $this->isPrivateOrReserved($ip)) {
            return ['country' => null, 'city' => null];
        }

        return Cache::remember(
            "geo:{$ip}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->fetch($ip),
        );
    }

    /**
     * @return array{country: ?string, city: ?string}
     */
    private function fetch(string $ip): array
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::ENDPOINT.$ip, ['fields' => 'status,countryCode,city']);

            if (! $response->successful()) {
                return ['country' => null, 'city' => null];
            }

            $data = $response->json();

            if (($data['status'] ?? null) !== 'success') {
                return ['country' => null, 'city' => null];
            }

            return [
                'country' => $this->normalizeCountry($data['countryCode'] ?? null),
                'city' => $this->normalizeCity($data['city'] ?? null),
            ];
        } catch (Throwable $e) {
            Log::warning('IpGeoLocator failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return ['country' => null, 'city' => null];
        }
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    private function normalizeCountry(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return strtoupper(substr($value, 0, 2));
    }

    private function normalizeCity(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, 120);
    }
}
