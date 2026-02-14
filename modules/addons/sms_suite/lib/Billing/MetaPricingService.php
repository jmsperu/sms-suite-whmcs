<?php
/**
 * SMS Suite - Meta WhatsApp Platform Pricing Service
 *
 * Manages Meta's per-message costs for WhatsApp Business Platform.
 * Handles CSV import, cost lookup, and country→market mapping.
 */

namespace SMSSuite\Billing;

use WHMCS\Database\Capsule;

class MetaPricingService
{
    /**
     * Import base rates from CSV.
     * Expected CSV format: Market, Marketing, Utility, Authentication, Authentication - International, Service
     *
     * @param string $csvFilePath Path to the uploaded CSV file
     * @param string $effectiveDate YYYY-MM-DD
     * @return array ['success' => bool, 'imported' => int, 'errors' => string[]]
     */
    public static function importBaseRates(string $csvFilePath, string $effectiveDate): array
    {
        $imported = 0;
        $errors = [];

        if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
            return ['success' => false, 'imported' => 0, 'errors' => ['File not found or not readable']];
        }

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return ['success' => false, 'imported' => 0, 'errors' => ['Could not open file']];
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header || count($header) < 2) {
            fclose($handle);
            return ['success' => false, 'imported' => 0, 'errors' => ['Invalid CSV header']];
        }

        // Normalize header names
        $header = array_map(function ($h) {
            return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h)));
        }, $header);

        // Find the market column (first column)
        $marketCol = 0;

        // Map remaining columns to category names
        $categoryMap = [];
        for ($i = 1; $i < count($header); $i++) {
            $col = $header[$i];
            if (stripos($col, 'marketing') !== false) {
                $categoryMap[$i] = 'marketing';
            } elseif (stripos($col, 'utility') !== false) {
                $categoryMap[$i] = 'utility';
            } elseif (stripos($col, 'authentication') !== false && stripos($col, 'international') !== false) {
                $categoryMap[$i] = 'authentication_international';
            } elseif (stripos($col, 'authentication') !== false) {
                $categoryMap[$i] = 'authentication';
            } elseif (stripos($col, 'service') !== false) {
                $categoryMap[$i] = 'service';
            }
        }

        if (empty($categoryMap)) {
            fclose($handle);
            return ['success' => false, 'imported' => 0, 'errors' => ['No recognized category columns found']];
        }

        $lineNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            $marketName = trim($row[$marketCol] ?? '');
            if (empty($marketName)) {
                continue;
            }

            foreach ($categoryMap as $colIdx => $category) {
                $rateStr = trim($row[$colIdx] ?? '');
                if ($rateStr === '' || $rateStr === '-' || $rateStr === 'N/A') {
                    continue;
                }

                // Clean rate value (remove $ and commas)
                $rate = (float) str_replace(['$', ',', ' '], '', $rateStr);

                try {
                    Capsule::table('mod_sms_meta_wa_rates')->updateOrInsert(
                        [
                            'market_name' => $marketName,
                            'category' => $category,
                            'effective_date' => $effectiveDate,
                        ],
                        ['rate' => $rate]
                    );
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Line {$lineNum}: {$e->getMessage()}";
                }
            }
        }

        fclose($handle);

        return [
            'success' => empty($errors),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Import volume discount tiers from CSV.
     * Expected CSV columns: Market, Category, Volume From, Volume To, Rate, Discount %
     *
     * @param string $csvFilePath
     * @param string $effectiveDate
     * @return array
     */
    public static function importVolumeTiers(string $csvFilePath, string $effectiveDate): array
    {
        $imported = 0;
        $errors = [];

        if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
            return ['success' => false, 'imported' => 0, 'errors' => ['File not found or not readable']];
        }

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return ['success' => false, 'imported' => 0, 'errors' => ['Could not open file']];
        }

        $header = fgetcsv($handle);
        if (!$header || count($header) < 4) {
            fclose($handle);
            return ['success' => false, 'imported' => 0, 'errors' => ['Invalid CSV header — need at least Market, Category, Volume From, Rate']];
        }

        $header = array_map(function ($h) {
            return strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h)));
        }, $header);

        // Map columns by name
        $colMap = [];
        foreach ($header as $i => $h) {
            if (stripos($h, 'market') !== false) $colMap['market'] = $i;
            elseif (stripos($h, 'category') !== false || stripos($h, 'type') !== false) $colMap['category'] = $i;
            elseif (stripos($h, 'from') !== false || stripos($h, 'min') !== false) $colMap['volume_from'] = $i;
            elseif (stripos($h, 'to') !== false || stripos($h, 'max') !== false) $colMap['volume_to'] = $i;
            elseif (stripos($h, 'rate') !== false || stripos($h, 'price') !== false) $colMap['rate'] = $i;
            elseif (stripos($h, 'discount') !== false) $colMap['discount_pct'] = $i;
        }

        if (!isset($colMap['market'], $colMap['rate'])) {
            fclose($handle);
            return ['success' => false, 'imported' => 0, 'errors' => ['Missing required columns: Market, Rate']];
        }

        $lineNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            $marketName = trim($row[$colMap['market']] ?? '');
            if (empty($marketName)) {
                continue;
            }

            $category = strtolower(trim($row[$colMap['category'] ?? -1] ?? 'utility'));
            $volumeFrom = (int) str_replace([',', ' '], '', $row[$colMap['volume_from'] ?? -1] ?? '0');
            $volumeToStr = trim($row[$colMap['volume_to'] ?? -1] ?? '');
            $volumeTo = ($volumeToStr === '' || strtolower($volumeToStr) === 'unlimited' || $volumeToStr === '-')
                ? null
                : (int) str_replace([',', ' '], '', $volumeToStr);

            $rateStr = trim($row[$colMap['rate']] ?? '');
            $rate = (float) str_replace(['$', ',', ' '], '', $rateStr);

            $discountStr = trim($row[$colMap['discount_pct'] ?? -1] ?? '0');
            $discountPct = (float) str_replace(['%', ',', ' '], '', $discountStr);

            try {
                Capsule::table('mod_sms_meta_wa_volume_tiers')->updateOrInsert(
                    [
                        'market_name' => $marketName,
                        'category' => $category,
                        'volume_from' => $volumeFrom,
                        'effective_date' => $effectiveDate,
                    ],
                    [
                        'volume_to' => $volumeTo,
                        'rate' => $rate,
                        'discount_pct' => $discountPct,
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Line {$lineNum}: {$e->getMessage()}";
            }
        }

        fclose($handle);

        return [
            'success' => empty($errors),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Seed the country→market mapping table with Meta's market definitions.
     * Based on Meta's WhatsApp Business Platform pricing documentation.
     *
     * @return int Number of records inserted
     */
    public static function seedMarketMapping(): int
    {
        $markets = self::getCountryMarketMap();
        $count = 0;

        foreach ($markets as $countryCode => $info) {
            try {
                Capsule::table('mod_sms_meta_wa_markets')->updateOrInsert(
                    ['country_code' => strtoupper($countryCode)],
                    [
                        'market_name' => $info['market'],
                        'country_name' => $info['name'],
                    ]
                );
                $count++;
            } catch (\Exception $e) {
                // Skip duplicates
            }
        }

        return $count;
    }

    /**
     * Get the Meta market name for a given ISO country code.
     *
     * @param string $countryCode ISO 2-letter country code (e.g., "KE", "US")
     * @return string Market name (e.g., "Rest of Africa", "North America")
     */
    public static function getMarketForCountry(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));

        // Try database first
        $record = Capsule::table('mod_sms_meta_wa_markets')
            ->where('country_code', $countryCode)
            ->first();

        if ($record) {
            return $record->market_name;
        }

        // Fallback to hardcoded map
        $map = self::getCountryMarketMap();
        if (isset($map[$countryCode])) {
            return $map[$countryCode]['market'];
        }

        return 'Other';
    }

    /**
     * Get the Meta platform cost for a WhatsApp message.
     *
     * @param string $countryCode ISO 2-letter code
     * @param string $category Message category: marketing, utility, authentication, authentication_international, service
     * @return float|null Cost in USD, or null if no rate found
     */
    public static function getMetaCost(string $countryCode, string $category = 'utility'): ?float
    {
        $market = self::getMarketForCountry($countryCode);

        $rate = Capsule::table('mod_sms_meta_wa_rates')
            ->where('market_name', $market)
            ->where('category', $category)
            ->orderByDesc('effective_date')
            ->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Try "Other" market as final fallback
        if ($market !== 'Other') {
            $rate = Capsule::table('mod_sms_meta_wa_rates')
                ->where('market_name', 'Other')
                ->where('category', $category)
                ->orderByDesc('effective_date')
                ->first();

            if ($rate) {
                return (float) $rate->rate;
            }
        }

        return null;
    }

    /**
     * Get all base rates, optionally for a specific effective date.
     *
     * @param string|null $effectiveDate If null, returns latest rates
     * @return array
     */
    public static function getAllRates(?string $effectiveDate = null): array
    {
        $query = Capsule::table('mod_sms_meta_wa_rates');

        if ($effectiveDate) {
            $query->where('effective_date', $effectiveDate);
        } else {
            // Get the latest effective date
            $latestDate = Capsule::table('mod_sms_meta_wa_rates')
                ->max('effective_date');

            if ($latestDate) {
                $query->where('effective_date', $latestDate);
            }
        }

        $rates = $query->orderBy('market_name')->get();

        // Pivot into market → categories
        $result = [];
        foreach ($rates as $rate) {
            if (!isset($result[$rate->market_name])) {
                $result[$rate->market_name] = [];
            }
            $result[$rate->market_name][$rate->category] = (float) $rate->rate;
        }

        return $result;
    }

    /**
     * Get volume discount tiers for a specific market and category.
     *
     * @param string $marketName
     * @param string $category
     * @return array
     */
    public static function getVolumeTiers(string $marketName, string $category): array
    {
        return Capsule::table('mod_sms_meta_wa_volume_tiers')
            ->where('market_name', $marketName)
            ->where('category', $category)
            ->orderByDesc('effective_date')
            ->orderBy('volume_from')
            ->get()
            ->toArray();
    }

    /**
     * Get all available effective dates.
     *
     * @return array
     */
    public static function getEffectiveDates(): array
    {
        return Capsule::table('mod_sms_meta_wa_rates')
            ->select('effective_date')
            ->distinct()
            ->orderByDesc('effective_date')
            ->pluck('effective_date')
            ->toArray();
    }

    /**
     * Get all country→market mappings from the database.
     *
     * @return array
     */
    public static function getAllMappings(): array
    {
        return Capsule::table('mod_sms_meta_wa_markets')
            ->orderBy('market_name')
            ->orderBy('country_name')
            ->get()
            ->toArray();
    }

    /**
     * Add or update a country→market mapping.
     *
     * @param string $countryCode
     * @param string $marketName
     * @param string $countryName
     */
    public static function saveMapping(string $countryCode, string $marketName, string $countryName): void
    {
        Capsule::table('mod_sms_meta_wa_markets')->updateOrInsert(
            ['country_code' => strtoupper(trim($countryCode))],
            [
                'market_name' => trim($marketName),
                'country_name' => trim($countryName),
            ]
        );
    }

    /**
     * Delete a country→market mapping.
     *
     * @param int $id
     */
    public static function deleteMapping(int $id): void
    {
        Capsule::table('mod_sms_meta_wa_markets')->where('id', $id)->delete();
    }

    /**
     * Hardcoded Meta WhatsApp market mapping.
     * Based on Meta's pricing documentation as of 2025.
     * Key = ISO 2-letter country code, Value = ['market' => ..., 'name' => ...]
     *
     * @return array
     */
    private static function getCountryMarketMap(): array
    {
        return [
            // Argentina
            'AR' => ['market' => 'Argentina', 'name' => 'Argentina'],

            // Brazil
            'BR' => ['market' => 'Brazil', 'name' => 'Brazil'],

            // Chile
            'CL' => ['market' => 'Chile', 'name' => 'Chile'],

            // Colombia
            'CO' => ['market' => 'Colombia', 'name' => 'Colombia'],

            // Egypt
            'EG' => ['market' => 'Egypt', 'name' => 'Egypt'],

            // France
            'FR' => ['market' => 'France', 'name' => 'France'],

            // Germany
            'DE' => ['market' => 'Germany', 'name' => 'Germany'],

            // India
            'IN' => ['market' => 'India', 'name' => 'India'],

            // Indonesia
            'ID' => ['market' => 'Indonesia', 'name' => 'Indonesia'],

            // Israel
            'IL' => ['market' => 'Israel', 'name' => 'Israel'],

            // Italy
            'IT' => ['market' => 'Italy', 'name' => 'Italy'],

            // Malaysia
            'MY' => ['market' => 'Malaysia', 'name' => 'Malaysia'],

            // Mexico
            'MX' => ['market' => 'Mexico', 'name' => 'Mexico'],

            // Netherlands
            'NL' => ['market' => 'Netherlands', 'name' => 'Netherlands'],

            // Nigeria
            'NG' => ['market' => 'Nigeria', 'name' => 'Nigeria'],

            // Pakistan
            'PK' => ['market' => 'Pakistan', 'name' => 'Pakistan'],

            // Peru
            'PE' => ['market' => 'Peru', 'name' => 'Peru'],

            // Russia
            'RU' => ['market' => 'Russia', 'name' => 'Russia'],

            // Saudi Arabia
            'SA' => ['market' => 'Saudi Arabia', 'name' => 'Saudi Arabia'],

            // South Africa
            'ZA' => ['market' => 'South Africa', 'name' => 'South Africa'],

            // Spain
            'ES' => ['market' => 'Spain', 'name' => 'Spain'],

            // Turkey
            'TR' => ['market' => 'Turkey', 'name' => 'Turkey'],

            // United Arab Emirates
            'AE' => ['market' => 'United Arab Emirates', 'name' => 'United Arab Emirates'],

            // United Kingdom
            'GB' => ['market' => 'United Kingdom', 'name' => 'United Kingdom'],

            // North America
            'US' => ['market' => 'North America', 'name' => 'United States'],
            'CA' => ['market' => 'North America', 'name' => 'Canada'],

            // Rest of Africa
            'KE' => ['market' => 'Rest of Africa', 'name' => 'Kenya'],
            'GH' => ['market' => 'Rest of Africa', 'name' => 'Ghana'],
            'TZ' => ['market' => 'Rest of Africa', 'name' => 'Tanzania'],
            'UG' => ['market' => 'Rest of Africa', 'name' => 'Uganda'],
            'ET' => ['market' => 'Rest of Africa', 'name' => 'Ethiopia'],
            'RW' => ['market' => 'Rest of Africa', 'name' => 'Rwanda'],
            'SN' => ['market' => 'Rest of Africa', 'name' => 'Senegal'],
            'CI' => ['market' => 'Rest of Africa', 'name' => "Cote d'Ivoire"],
            'CM' => ['market' => 'Rest of Africa', 'name' => 'Cameroon'],
            'CD' => ['market' => 'Rest of Africa', 'name' => 'DR Congo'],
            'AO' => ['market' => 'Rest of Africa', 'name' => 'Angola'],
            'MZ' => ['market' => 'Rest of Africa', 'name' => 'Mozambique'],
            'ZM' => ['market' => 'Rest of Africa', 'name' => 'Zambia'],
            'ZW' => ['market' => 'Rest of Africa', 'name' => 'Zimbabwe'],
            'MW' => ['market' => 'Rest of Africa', 'name' => 'Malawi'],
            'BJ' => ['market' => 'Rest of Africa', 'name' => 'Benin'],
            'BF' => ['market' => 'Rest of Africa', 'name' => 'Burkina Faso'],
            'ML' => ['market' => 'Rest of Africa', 'name' => 'Mali'],
            'NE' => ['market' => 'Rest of Africa', 'name' => 'Niger'],
            'TD' => ['market' => 'Rest of Africa', 'name' => 'Chad'],
            'MG' => ['market' => 'Rest of Africa', 'name' => 'Madagascar'],
            'SO' => ['market' => 'Rest of Africa', 'name' => 'Somalia'],
            'LY' => ['market' => 'Rest of Africa', 'name' => 'Libya'],
            'TN' => ['market' => 'Rest of Africa', 'name' => 'Tunisia'],
            'DZ' => ['market' => 'Rest of Africa', 'name' => 'Algeria'],
            'MA' => ['market' => 'Rest of Africa', 'name' => 'Morocco'],
            'SD' => ['market' => 'Rest of Africa', 'name' => 'Sudan'],
            'SS' => ['market' => 'Rest of Africa', 'name' => 'South Sudan'],
            'ER' => ['market' => 'Rest of Africa', 'name' => 'Eritrea'],
            'DJ' => ['market' => 'Rest of Africa', 'name' => 'Djibouti'],
            'GM' => ['market' => 'Rest of Africa', 'name' => 'Gambia'],
            'GN' => ['market' => 'Rest of Africa', 'name' => 'Guinea'],
            'GW' => ['market' => 'Rest of Africa', 'name' => 'Guinea-Bissau'],
            'SL' => ['market' => 'Rest of Africa', 'name' => 'Sierra Leone'],
            'LR' => ['market' => 'Rest of Africa', 'name' => 'Liberia'],
            'TG' => ['market' => 'Rest of Africa', 'name' => 'Togo'],
            'GA' => ['market' => 'Rest of Africa', 'name' => 'Gabon'],
            'GQ' => ['market' => 'Rest of Africa', 'name' => 'Equatorial Guinea'],
            'CG' => ['market' => 'Rest of Africa', 'name' => 'Republic of Congo'],
            'CF' => ['market' => 'Rest of Africa', 'name' => 'Central African Republic'],
            'BI' => ['market' => 'Rest of Africa', 'name' => 'Burundi'],
            'NA' => ['market' => 'Rest of Africa', 'name' => 'Namibia'],
            'BW' => ['market' => 'Rest of Africa', 'name' => 'Botswana'],
            'SZ' => ['market' => 'Rest of Africa', 'name' => 'Eswatini'],
            'LS' => ['market' => 'Rest of Africa', 'name' => 'Lesotho'],
            'MU' => ['market' => 'Rest of Africa', 'name' => 'Mauritius'],
            'SC' => ['market' => 'Rest of Africa', 'name' => 'Seychelles'],
            'KM' => ['market' => 'Rest of Africa', 'name' => 'Comoros'],
            'CV' => ['market' => 'Rest of Africa', 'name' => 'Cape Verde'],
            'ST' => ['market' => 'Rest of Africa', 'name' => 'Sao Tome and Principe'],
            'MR' => ['market' => 'Rest of Africa', 'name' => 'Mauritania'],

            // Rest of Asia Pacific
            'JP' => ['market' => 'Rest of Asia Pacific', 'name' => 'Japan'],
            'KR' => ['market' => 'Rest of Asia Pacific', 'name' => 'South Korea'],
            'AU' => ['market' => 'Rest of Asia Pacific', 'name' => 'Australia'],
            'NZ' => ['market' => 'Rest of Asia Pacific', 'name' => 'New Zealand'],
            'SG' => ['market' => 'Rest of Asia Pacific', 'name' => 'Singapore'],
            'TH' => ['market' => 'Rest of Asia Pacific', 'name' => 'Thailand'],
            'PH' => ['market' => 'Rest of Asia Pacific', 'name' => 'Philippines'],
            'VN' => ['market' => 'Rest of Asia Pacific', 'name' => 'Vietnam'],
            'MM' => ['market' => 'Rest of Asia Pacific', 'name' => 'Myanmar'],
            'KH' => ['market' => 'Rest of Asia Pacific', 'name' => 'Cambodia'],
            'LA' => ['market' => 'Rest of Asia Pacific', 'name' => 'Laos'],
            'BD' => ['market' => 'Rest of Asia Pacific', 'name' => 'Bangladesh'],
            'LK' => ['market' => 'Rest of Asia Pacific', 'name' => 'Sri Lanka'],
            'NP' => ['market' => 'Rest of Asia Pacific', 'name' => 'Nepal'],
            'AF' => ['market' => 'Rest of Asia Pacific', 'name' => 'Afghanistan'],
            'MN' => ['market' => 'Rest of Asia Pacific', 'name' => 'Mongolia'],
            'CN' => ['market' => 'Rest of Asia Pacific', 'name' => 'China'],
            'TW' => ['market' => 'Rest of Asia Pacific', 'name' => 'Taiwan'],
            'HK' => ['market' => 'Rest of Asia Pacific', 'name' => 'Hong Kong'],
            'MO' => ['market' => 'Rest of Asia Pacific', 'name' => 'Macau'],
            'BN' => ['market' => 'Rest of Asia Pacific', 'name' => 'Brunei'],
            'FJ' => ['market' => 'Rest of Asia Pacific', 'name' => 'Fiji'],
            'PG' => ['market' => 'Rest of Asia Pacific', 'name' => 'Papua New Guinea'],

            // Rest of Central & Eastern Europe
            'PL' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Poland'],
            'CZ' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Czech Republic'],
            'HU' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Hungary'],
            'RO' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Romania'],
            'BG' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Bulgaria'],
            'SK' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Slovakia'],
            'HR' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Croatia'],
            'SI' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Slovenia'],
            'RS' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Serbia'],
            'BA' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Bosnia and Herzegovina'],
            'ME' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Montenegro'],
            'MK' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'North Macedonia'],
            'AL' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Albania'],
            'XK' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Kosovo'],
            'UA' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Ukraine'],
            'BY' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Belarus'],
            'MD' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Moldova'],
            'LT' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Lithuania'],
            'LV' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Latvia'],
            'EE' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Estonia'],
            'GE' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Georgia'],
            'AM' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Armenia'],
            'AZ' => ['market' => 'Rest of Central & Eastern Europe', 'name' => 'Azerbaijan'],

            // Rest of Latin America
            'VE' => ['market' => 'Rest of Latin America', 'name' => 'Venezuela'],
            'EC' => ['market' => 'Rest of Latin America', 'name' => 'Ecuador'],
            'BO' => ['market' => 'Rest of Latin America', 'name' => 'Bolivia'],
            'PY' => ['market' => 'Rest of Latin America', 'name' => 'Paraguay'],
            'UY' => ['market' => 'Rest of Latin America', 'name' => 'Uruguay'],
            'GY' => ['market' => 'Rest of Latin America', 'name' => 'Guyana'],
            'SR' => ['market' => 'Rest of Latin America', 'name' => 'Suriname'],
            'PA' => ['market' => 'Rest of Latin America', 'name' => 'Panama'],
            'CR' => ['market' => 'Rest of Latin America', 'name' => 'Costa Rica'],
            'GT' => ['market' => 'Rest of Latin America', 'name' => 'Guatemala'],
            'HN' => ['market' => 'Rest of Latin America', 'name' => 'Honduras'],
            'SV' => ['market' => 'Rest of Latin America', 'name' => 'El Salvador'],
            'NI' => ['market' => 'Rest of Latin America', 'name' => 'Nicaragua'],
            'BZ' => ['market' => 'Rest of Latin America', 'name' => 'Belize'],
            'CU' => ['market' => 'Rest of Latin America', 'name' => 'Cuba'],
            'DO' => ['market' => 'Rest of Latin America', 'name' => 'Dominican Republic'],
            'HT' => ['market' => 'Rest of Latin America', 'name' => 'Haiti'],
            'JM' => ['market' => 'Rest of Latin America', 'name' => 'Jamaica'],
            'TT' => ['market' => 'Rest of Latin America', 'name' => 'Trinidad and Tobago'],
            'PR' => ['market' => 'Rest of Latin America', 'name' => 'Puerto Rico'],

            // Rest of Middle East
            'IQ' => ['market' => 'Rest of Middle East', 'name' => 'Iraq'],
            'JO' => ['market' => 'Rest of Middle East', 'name' => 'Jordan'],
            'KW' => ['market' => 'Rest of Middle East', 'name' => 'Kuwait'],
            'LB' => ['market' => 'Rest of Middle East', 'name' => 'Lebanon'],
            'OM' => ['market' => 'Rest of Middle East', 'name' => 'Oman'],
            'QA' => ['market' => 'Rest of Middle East', 'name' => 'Qatar'],
            'BH' => ['market' => 'Rest of Middle East', 'name' => 'Bahrain'],
            'YE' => ['market' => 'Rest of Middle East', 'name' => 'Yemen'],
            'SY' => ['market' => 'Rest of Middle East', 'name' => 'Syria'],
            'PS' => ['market' => 'Rest of Middle East', 'name' => 'Palestine'],
            'IR' => ['market' => 'Rest of Middle East', 'name' => 'Iran'],

            // Rest of Western Europe
            'AT' => ['market' => 'Rest of Western Europe', 'name' => 'Austria'],
            'BE' => ['market' => 'Rest of Western Europe', 'name' => 'Belgium'],
            'CH' => ['market' => 'Rest of Western Europe', 'name' => 'Switzerland'],
            'DK' => ['market' => 'Rest of Western Europe', 'name' => 'Denmark'],
            'FI' => ['market' => 'Rest of Western Europe', 'name' => 'Finland'],
            'GR' => ['market' => 'Rest of Western Europe', 'name' => 'Greece'],
            'IE' => ['market' => 'Rest of Western Europe', 'name' => 'Ireland'],
            'IS' => ['market' => 'Rest of Western Europe', 'name' => 'Iceland'],
            'LU' => ['market' => 'Rest of Western Europe', 'name' => 'Luxembourg'],
            'NO' => ['market' => 'Rest of Western Europe', 'name' => 'Norway'],
            'PT' => ['market' => 'Rest of Western Europe', 'name' => 'Portugal'],
            'SE' => ['market' => 'Rest of Western Europe', 'name' => 'Sweden'],
            'MT' => ['market' => 'Rest of Western Europe', 'name' => 'Malta'],
            'CY' => ['market' => 'Rest of Western Europe', 'name' => 'Cyprus'],

            // Rest of Central Asia
            'KZ' => ['market' => 'Other', 'name' => 'Kazakhstan'],
            'UZ' => ['market' => 'Other', 'name' => 'Uzbekistan'],
            'TM' => ['market' => 'Other', 'name' => 'Turkmenistan'],
            'KG' => ['market' => 'Other', 'name' => 'Kyrgyzstan'],
            'TJ' => ['market' => 'Other', 'name' => 'Tajikistan'],
        ];
    }
}
