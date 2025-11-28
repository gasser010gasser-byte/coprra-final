<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeolocationService
{
    /**
     * Detect user's locale based on IP address.
     */
    public function detectLocaleFromIP(?string $ipAddress = null): array
    {
        $defaultLocale = [
            'language' => 'en',
            'country' => null,
            'currency' => 'USD',
        ];

        if (! $ipAddress || $this->isLocalIP($ipAddress)) {
            return $defaultLocale;
        }

        $cacheKey = 'geolocation_' . md5($ipAddress);
        $cached = Cache::get($cacheKey);

        if ($cached && is_array($cached)) {
            return $cached;
        }

        $geoData = $this->fetchGeolocationData($ipAddress);

        if (! $geoData) {
            return $defaultLocale;
        }

        $locale = $this->mapGeoDataToLocale($geoData);
        Cache::put($cacheKey, $locale, 86400);

        return $locale;
    }

    private function isLocalIP(string $ip): bool
    {
        $localPatterns = [
            '/^127\./',
            '/^192\.168\./',
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
            '/^::1$/',
            '/^localhost$/',
        ];

        foreach ($localPatterns as $pattern) {
            if (preg_match($pattern, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function fetchGeolocationData(string $ipAddress): ?array
    {
        try {
            $response = Http::timeout(3)->get("https://ipapi.co/{$ipAddress}/json/");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'country_code' => $data['country_code'] ?? null,
                    'country_name' => $data['country_name'] ?? null,
                    'languages' => $data['languages'] ?? null,
                    'currency' => $data['currency'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Geolocation API failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function mapGeoDataToLocale(array $geoData): array
    {
        $countryCode = $geoData['country_code'] ?? null;
        $currencyCode = $geoData['currency'] ?? 'USD';
        $languagesString = $geoData['languages'] ?? 'en';

        $locale = [
            'language' => 'en',
            'country' => null,
            'currency' => 'USD',
        ];

        $country = null;
        if ($countryCode) {
            $country = Country::with(['language', 'currency'])
                ->where('code', $countryCode)
                ->where('is_active', true)
                ->first();
        }

        if ($country) {
            $locale['country'] = $country->code;
            $locale['language'] = $country->language->code ?? 'en';
            $locale['currency'] = $country->currency->code ?? $currencyCode;
        } else {
            $primaryLang = $this->extractPrimaryLanguage($languagesString);
            $language = Language::where('code', $primaryLang)
                ->where('is_active', true)
                ->first();

            if ($language) {
                $locale['language'] = $language->code;
            }

            $currency = Currency::where('code', $currencyCode)
                ->where('is_active', true)
                ->first();

            if ($currency) {
                $locale['currency'] = $currency->code;
            }
        }

        return $locale;
    }

    private function extractPrimaryLanguage(string $languagesString): string
    {
        $languages = explode(',', $languagesString);
        $primaryLang = trim($languages[0] ?? 'en');

        $langMap = [
            'en-US' => 'en', 'en-GB' => 'en',
            'es-ES' => 'es', 'es-MX' => 'es',
            'zh-CN' => 'zh', 'zh-TW' => 'zh',
            'ar-SA' => 'ar', 'ar-EG' => 'ar',
            'pt-BR' => 'pt', 'pt-PT' => 'pt',
            'fr-FR' => 'fr', 'fr-CA' => 'fr',
            'de-DE' => 'de', 'de-AT' => 'de',
        ];

        if (strlen($primaryLang) > 2) {
            return $langMap[$primaryLang] ?? substr($primaryLang, 0, 2);
        }

        return $primaryLang;
    }

    public function applyLocale(array $locale, $user = null): void
    {
        app()->setLocale($locale['language']);

        session([
            'locale' => $locale['language'],
            'locale_country' => $locale['country'],
            'currency' => $locale['currency'],
            'locale_detected' => true,
        ]);

        if ($user) {
            $language = Language::where('code', $locale['language'])->first();
            $currency = Currency::where('code', $locale['currency'])->first();

            if ($language || $currency) {
                $setting = \App\Models\UserLocaleSetting::firstOrNew(['user_id' => $user->id]);

                if ($language) {
                    $setting->language_id = $language->id;
                }
                if ($currency) {
                    $setting->currency_id = $currency->id;
                }

                $setting->save();
            }
        }
    }

    public function hasDetectedLocale(): bool
    {
        return (bool) session('locale_detected', false);
    }
}
