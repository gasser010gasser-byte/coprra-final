<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InternationalizationSeeder extends Seeder
{
    /**
     * Seed the internationalization data (languages, currencies, countries)
     * Based on the Top 25 Global Languages dataset.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Clear existing data
            Country::query()->delete();

            // Get existing languages and currencies
            $languages = Language::all()->keyBy('code');
            $currencies = Currency::all()->keyBy('code');

            // Seed countries with hierarchical relationships
            $this->seedCountries($languages, $currencies);
        });

        $this->command->info('✅ Internationalization seeding completed!');
    }

    private function seedCountries($languages, $currencies): void
    {
        $sortOrder = 0;

        // Dataset based on Top 25 Global Languages
        $dataset = [
            // 1. English (1528M speakers)
            ['US', 'United States', 'United States', '🇺🇸', 'en', 'USD', ++$sortOrder],
            ['GB', 'United Kingdom', 'United Kingdom', '🇬🇧', 'en', 'GBP', ++$sortOrder],
            ['IN', 'India', 'भारत', '🇮🇳', 'en', 'INR', ++$sortOrder],
            ['CA', 'Canada', 'Canada', '🇨🇦', 'en', 'CAD', ++$sortOrder],
            ['AU', 'Australia', 'Australia', '🇦🇺', 'en', 'AUD', ++$sortOrder],
            ['NZ', 'New Zealand', 'New Zealand', '🇳🇿', 'en', 'AUD', ++$sortOrder],
            ['SG', 'Singapore', 'Singapore', '🇸🇬', 'en', 'SGD', ++$sortOrder],
            ['ZA', 'South Africa', 'South Africa', '🇿🇦', 'en', 'ZAR', ++$sortOrder],

            // 2. Arabic (379M speakers)
            ['SA', 'Saudi Arabia', 'المملكة العربية السعودية', '🇸🇦', 'ar', 'SAR', ++$sortOrder],
            ['EG', 'Egypt', 'مصر', '🇪🇬', 'ar', 'EGP', ++$sortOrder],
            ['AE', 'UAE', 'الإمارات', '🇦🇪', 'ar', 'AED', ++$sortOrder],
            ['IQ', 'Iraq', 'العراق', '🇮🇶', 'ar', 'IQD', ++$sortOrder],
            ['DZ', 'Algeria', 'الجزائر', '🇩🇿', 'ar', 'DZD', ++$sortOrder],
            ['MA', 'Morocco', 'المغرب', '🇲🇦', 'ar', 'MAD', ++$sortOrder],
            ['SD', 'Sudan', 'السودان', '🇸🇩', 'ar', 'SDG', ++$sortOrder],
            ['YE', 'Yemen', 'اليمن', '🇾🇪', 'ar', 'YER', ++$sortOrder],
            ['SY', 'Syria', 'سوريا', '🇸🇾', 'ar', 'SYP', ++$sortOrder],
            ['JO', 'Jordan', 'الأردن', '🇯🇴', 'ar', 'JOD', ++$sortOrder],
            ['TN', 'Tunisia', 'تونس', '🇹🇳', 'ar', 'TND', ++$sortOrder],
            ['LY', 'Libya', 'ليبيا', '🇱🇾', 'ar', 'LYD', ++$sortOrder],
            ['LB', 'Lebanon', 'لبنان', '🇱🇧', 'ar', 'LBP', ++$sortOrder],
            ['PS', 'Palestine', 'فلسطين', '🇵🇸', 'ar', 'ILS', ++$sortOrder],
            ['OM', 'Oman', 'عمان', '🇴🇲', 'ar', 'OMR', ++$sortOrder],
            ['KW', 'Kuwait', 'الكويت', '🇰🇼', 'ar', 'KWD', ++$sortOrder],
            ['QA', 'Qatar', 'قطر', '🇶🇦', 'ar', 'QAR', ++$sortOrder],
            ['BH', 'Bahrain', 'البحرين', '🇧🇭', 'ar', 'BHD', ++$sortOrder],
            ['MR', 'Mauritania', 'موريتانيا', '🇲🇷', 'ar', 'MRU', ++$sortOrder],
            ['DJ', 'Djibouti', 'جيبوتي', '🇩🇯', 'ar', 'DJF', ++$sortOrder],
            ['KM', 'Comoros', 'جزر القمر', '🇰🇲', 'ar', 'KMF', ++$sortOrder],

            // 3. Spanish (548M speakers)
            ['ES', 'Spain', 'España', '🇪🇸', 'es', 'EUR', ++$sortOrder],
            ['MX', 'Mexico', 'México', '🇲🇽', 'es', 'MXN', ++$sortOrder],
            ['CO', 'Colombia', 'Colombia', '🇨🇴', 'es', 'COP', ++$sortOrder],
            ['AR', 'Argentina', 'Argentina', '🇦🇷', 'es', 'ARS', ++$sortOrder],
            ['PE', 'Peru', 'Perú', '🇵🇪', 'es', 'PEN', ++$sortOrder],
            ['VE', 'Venezuela', 'Venezuela', '🇻🇪', 'es', 'VES', ++$sortOrder],
            ['CL', 'Chile', 'Chile', '🇨🇱', 'es', 'CLP', ++$sortOrder],
            ['EC', 'Ecuador', 'Ecuador', '🇪🇨', 'es', 'USD', ++$sortOrder],
            ['GT', 'Guatemala', 'Guatemala', '🇬🇹', 'es', 'GTQ', ++$sortOrder],
            ['CU', 'Cuba', 'Cuba', '🇨🇺', 'es', 'CUP', ++$sortOrder],
            ['BO', 'Bolivia', 'Bolivia', '🇧🇴', 'es', 'BOB', ++$sortOrder],
            ['DO', 'Dominican Republic', 'República Dominicana', '🇩🇴', 'es', 'DOP', ++$sortOrder],
            ['HN', 'Honduras', 'Honduras', '🇭🇳', 'es', 'HNL', ++$sortOrder],
            ['PY', 'Paraguay', 'Paraguay', '🇵🇾', 'es', 'PYG', ++$sortOrder],
            ['SV', 'El Salvador', 'El Salvador', '🇸🇻', 'es', 'USD', ++$sortOrder],
            ['NI', 'Nicaragua', 'Nicaragua', '🇳🇮', 'es', 'NIO', ++$sortOrder],
            ['CR', 'Costa Rica', 'Costa Rica', '🇨🇷', 'es', 'CRC', ++$sortOrder],
            ['PA', 'Panama', 'Panamá', '🇵🇦', 'es', 'PAB', ++$sortOrder],
            ['UY', 'Uruguay', 'Uruguay', '🇺🇾', 'es', 'UYU', ++$sortOrder],
            ['PR', 'Puerto Rico', 'Puerto Rico', '🇵🇷', 'es', 'USD', ++$sortOrder],
            ['GQ', 'Equatorial Guinea', 'Guinea Ecuatorial', '🇬🇶', 'es', 'XAF', ++$sortOrder],

            // 4. Chinese (1120M speakers)
            ['CN', 'China', '中国', '🇨🇳', 'zh', 'CNY', ++$sortOrder],
            ['TW', 'Taiwan', '台灣', '🇹🇼', 'zh', 'TWD', ++$sortOrder],
            ['HK', 'Hong Kong', '香港', '🇭🇰', 'zh', 'HKD', ++$sortOrder],
            ['MO', 'Macau', '澳門', '🇲🇴', 'zh', 'MOP', ++$sortOrder],

            // 5. Hindi (602M speakers)
            ['IN', 'India', 'भारत', '🇮🇳', 'hi', 'INR', ++$sortOrder],
            ['NP', 'Nepal', 'नेपाल', '🇳🇵', 'hi', 'NPR', ++$sortOrder],
            ['FJ', 'Fiji', 'फिजी', '🇫🇯', 'hi', 'FJD', ++$sortOrder],

            // 6. Portuguese (264M speakers)
            ['BR', 'Brazil', 'Brasil', '🇧🇷', 'pt', 'BRL', ++$sortOrder],
            ['PT', 'Portugal', 'Portugal', '🇵🇹', 'pt', 'EUR', ++$sortOrder],
            ['AO', 'Angola', 'Angola', '🇦🇴', 'pt', 'AOA', ++$sortOrder],
            ['MZ', 'Mozambique', 'Moçambique', '🇲🇿', 'pt', 'MZN', ++$sortOrder],

            // 7. French (280M speakers)
            ['FR', 'France', 'France', '🇫🇷', 'fr', 'EUR', ++$sortOrder],
            ['CD', 'DR Congo', 'RD Congo', '🇨🇩', 'fr', 'CDF', ++$sortOrder],
            ['CA', 'Canada', 'Canada', '🇨🇦', 'fr', 'CAD', ++$sortOrder],
            ['BE', 'Belgium', 'Belgique', '🇧🇪', 'fr', 'EUR', ++$sortOrder],
            ['CH', 'Switzerland', 'Suisse', '🇨🇭', 'fr', 'CHF', ++$sortOrder],
            ['CI', 'Ivory Coast', 'Côte d\'Ivoire', '🇨🇮', 'fr', 'XOF', ++$sortOrder],
            ['CM', 'Cameroon', 'Cameroun', '🇨🇲', 'fr', 'XAF', ++$sortOrder],
            ['MG', 'Madagascar', 'Madagascar', '🇲🇬', 'fr', 'MGA', ++$sortOrder],
            ['ML', 'Mali', 'Mali', '🇲🇱', 'fr', 'XOF', ++$sortOrder],
            ['SN', 'Senegal', 'Sénégal', '🇸🇳', 'fr', 'XOF', ++$sortOrder],
            ['TG', 'Togo', 'Togo', '🇹🇬', 'fr', 'XOF', ++$sortOrder],
            ['BJ', 'Benin', 'Bénin', '🇧🇯', 'fr', 'XOF', ++$sortOrder],
            ['RW', 'Rwanda', 'Rwanda', '🇷🇼', 'fr', 'RWF', ++$sortOrder],
            ['GN', 'Guinea', 'Guinée', '🇬🇳', 'fr', 'GNF', ++$sortOrder],
            ['TD', 'Chad', 'Tchad', '🇹🇩', 'fr', 'XAF', ++$sortOrder],
            ['HT', 'Haiti', 'Haïti', '🇭🇹', 'fr', 'HTG', ++$sortOrder],
            ['BI', 'Burundi', 'Burundi', '🇧🇮', 'fr', 'BIF', ++$sortOrder],
            ['BF', 'Burkina Faso', 'Burkina Faso', '🇧🇫', 'fr', 'XOF', ++$sortOrder],
            ['NE', 'Niger', 'Niger', '🇳🇪', 'fr', 'XOF', ++$sortOrder],

            // 8. German (135M speakers)
            ['DE', 'Germany', 'Deutschland', '🇩🇪', 'de', 'EUR', ++$sortOrder],
            ['AT', 'Austria', 'Österreich', '🇦🇹', 'de', 'EUR', ++$sortOrder],
            ['CH', 'Switzerland', 'Schweiz', '🇨🇭', 'de', 'CHF', ++$sortOrder],
            ['LI', 'Liechtenstein', 'Liechtenstein', '🇱🇮', 'de', 'CHF', ++$sortOrder],
            ['LU', 'Luxembourg', 'Luxemburg', '🇱🇺', 'de', 'EUR', ++$sortOrder],

            // 9. Japanese (125M speakers)
            ['JP', 'Japan', '日本', '🇯🇵', 'ja', 'JPY', ++$sortOrder],

            // 10. Russian (258M speakers)
            ['RU', 'Russia', 'Россия', '🇷🇺', 'ru', 'RUB', ++$sortOrder],
            ['KZ', 'Kazakhstan', 'Казахстан', '🇰🇿', 'ru', 'KZT', ++$sortOrder],
            ['BY', 'Belarus', 'Беларусь', '🇧🇾', 'ru', 'BYN', ++$sortOrder],
            ['KG', 'Kyrgyzstan', 'Кыргызстан', '🇰🇬', 'ru', 'KGS', ++$sortOrder],

            // 11. Korean (82M speakers)
            ['KR', 'South Korea', '대한민국', '🇰🇷', 'ko', 'KRW', ++$sortOrder],
            ['KP', 'North Korea', '조선', '🇰🇵', 'ko', 'KPW', ++$sortOrder],

            // 12. Turkish (85M speakers)
            ['TR', 'Turkey', 'Türkiye', '🇹🇷', 'tr', 'TRY', ++$sortOrder],
            ['CY', 'Cyprus', 'Kıbrıs', '🇨🇾', 'tr', 'EUR', ++$sortOrder],

            // 13. Italian (68M speakers)
            ['IT', 'Italy', 'Italia', '🇮🇹', 'it', 'EUR', ++$sortOrder],
            ['CH', 'Switzerland', 'Svizzera', '🇨🇭', 'it', 'CHF', ++$sortOrder],
            ['SM', 'San Marino', 'San Marino', '🇸🇲', 'it', 'EUR', ++$sortOrder],

            // 14. Indonesian (199M speakers)
            ['ID', 'Indonesia', 'Indonesia', '🇮🇩', 'id', 'IDR', ++$sortOrder],
            ['MY', 'Malaysia', 'Malaysia', '🇲🇾', 'id', 'MYR', ++$sortOrder],

            // 15. Dutch (25M speakers)
            ['NL', 'Netherlands', 'Nederland', '🇳🇱', 'nl', 'EUR', ++$sortOrder],
            ['BE', 'Belgium', 'België', '🇧🇪', 'nl', 'EUR', ++$sortOrder],
        ];

        foreach ($dataset as $data) {
            [$code, $name, $nativeName, $flag, $langCode, $currCode, $sort] = $data;

            $language = $languages->get($langCode);
            $currency = $currencies->get($currCode);

            if (! $language) {
                $this->command->warn("⚠️  Language '{$langCode}' not found for country '{$name}'. Skipping.");

                continue;
            }

            if (! $currency) {
                $this->command->warn("⚠️  Currency '{$currCode}' not found for country '{$name}'. Skipping.");

                continue;
            }

            // Check if country already exists (some countries appear multiple times for different languages)
            $existing = Country::where('code', $code)->where('language_id', $language->id)->first();

            if (! $existing) {
                Country::create([
                    'code' => $code,
                    'name' => $name,
                    'native_name' => $nativeName,
                    'language_id' => $language->id,
                    'currency_id' => $currency->id,
                    'flag_emoji' => $flag,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]);
            }
        }

        $this->command->info('✅ Seeded ' . Country::count() . ' countries with language-currency relationships');
    }
}
