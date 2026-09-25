<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /**
     * Autocomplete location search suggestions.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $type = trim($request->query('type', 'from')); // 'from' or 'to'

        if (empty($q)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Fetch sending or receiving countries from shipment_routes
        $routeCountriesQuery = DB::table('shipment_routes')
            ->join('countries', function($join) use ($type) {
                if ($type === 'to') {
                    $join->on('shipment_routes.receiving_country_id', '=', 'countries.id');
                } else {
                    $join->on('shipment_routes.sending_country_id', '=', 'countries.id');
                }
            })
            ->select('countries.*')
            ->where(function($query) use ($q) {
                $query->where('countries.name', 'like', "%{$q}%")
                      ->orWhere('countries.iso_code_1', 'like', "%{$q}%")
                      ->orWhere('countries.iso_code_2', 'like', "%{$q}%")
                      ->orWhere('countries.capital', 'like', "%{$q}%");
            })
            ->distinct()
            ->limit(8)
            ->get();

        $suggestions = [];

        // Add DB matches from active routes
        foreach ($routeCountriesQuery as $country) {
            $suggestions[] = [
                'id' => $country->id,
                'city' => $country->capital ?? '',
                'country' => $country->name,
                'iso_code' => $country->iso_code_1
            ];
        }

        // Add matching zones (cities/states) for countries in active routes
        $routeCountryIds = DB::table('shipment_routes')
            ->selectRaw($type === 'to' ? 'distinct receiving_country_id as cid' : 'distinct sending_country_id as cid')
            ->pluck('cid')
            ->toArray();

        if (!empty($routeCountryIds)) {
            $zones = DB::table('zones')
                ->join('countries', 'zones.country_id', '=', 'countries.id')
                ->select(
                    'zones.name as state_name',
                    'countries.name as country_name',
                    'countries.iso_code_1 as country_code',
                    'countries.id as country_id'
                )
                ->whereIn('zones.country_id', $routeCountryIds)
                ->where('zones.name', 'like', "%{$q}%")
                ->limit(8)
                ->get();

            foreach ($zones as $zone) {
                $suggestions[] = [
                    'id' => $zone->country_id,
                    'city' => $zone->state_name,
                    'country' => $zone->country_name,
                    'iso_code' => $zone->country_code
                ];
            }
        }

        // Fallback popular hubs seed for seamless demo suggestions
        // $popularHubs = [
        //     ['city' => 'Toronto', 'country' => 'Canada', 'iso_code' => 'CA', 'synonyms' => ['toronto', 'tor', 'canada', 'ca']],
        //     ['city' => 'Lagos', 'country' => 'Nigeria', 'iso_code' => 'NG', 'synonyms' => ['lagos', 'lag', 'nigeria', 'ng']],
        //     ['city' => 'Nairobi', 'country' => 'Kenya', 'iso_code' => 'KE', 'synonyms' => ['nairobi', 'nai', 'kenya', 'ke']],
        //     ['city' => 'London', 'country' => 'United Kingdom', 'iso_code' => 'GB', 'synonyms' => ['london', 'lon', 'united kingdom', 'uk', 'gb']],
        //     ['city' => 'Chicago', 'country' => 'United States', 'iso_code' => 'US', 'synonyms' => ['chicago', 'chi', 'united states', 'usa', 'us']],
        //     ['city' => 'Karachi', 'country' => 'Pakistan', 'iso_code' => 'PK', 'synonyms' => ['karachi', 'kar', 'pakistan', 'pk']],
        // ];

        // $lowercaseQ = strtolower($q);
        // foreach ($popularHubs as $hub) {
        //     // Only add if relevant to the search type
        //     $isOriginMatch = ($type === 'from' && in_array($hub['iso_code'], ['CA', 'GB', 'US', 'KE']));
        //     $isDestMatch = ($type === 'to' && in_array($hub['iso_code'], ['NG', 'US', 'KE']));

        //     if ($isOriginMatch || $isDestMatch) {
        //         foreach ($hub['synonyms'] as $syn) {
        //             if (str_contains($syn, $lowercaseQ)) {
        //                 $suggestions[] = [
        //                     'city' => $hub['city'],
        //                     'country' => $hub['country'],
        //                     'iso_code' => $hub['iso_code']
        //                 ];
        //                 break;
        //             }
        //         }
        //     }
        // }

        // Global fallback search on all countries if suggestions are still sparse
        if (count($suggestions) < 3) {
            $globalCountries = DB::table('countries')
                ->where('name', 'like', "%{$q}%")
                ->orWhere('iso_code_1', 'like', "%{$q}%")
                ->orWhere('capital', 'like', "%{$q}%")
                ->limit(5)
                ->get();

            foreach ($globalCountries as $country) {
                $suggestions[] = [
                    'city' => $country->capital ?? '',
                    'country' => $country->name,
                    'iso_code' => $country->iso_code_1
                ];
            }
        }

        // De-duplicate array
        $uniqueSuggestions = [];
        $keys = [];
        foreach ($suggestions as $s) {
            $key = $s['city'] . '|' . $s['country'] . '|' . $s['iso_code'];
            if (!in_array($key, $keys)) {
                $keys[] = $key;
                $uniqueSuggestions[] = $s;
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_slice($uniqueSuggestions, 0, 8)
        ]);
    }

    /**
     * Search and rank rates from local routes or external services.
     */
    public function searchRates(Request $request): JsonResponse
    {
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        $fromId = $request->input('from_id');
        $toId = $request->input('to_id');
        $mode = $request->input('mode', 'Air Freight');
        $weight = (float)$request->input('weight', 1.0);
        $unit = $request->input('unit', 'kg');

        $userCountry = $request->input('user_country', $request->header('CF-IPCountry', 'US'));
        $userRegion = $request->input('user_region', $request->header('CF-Region', 'WA'));

        // Resolve countries using ID first if available
        if (!empty($fromId) && is_numeric($fromId)) {
            $originCountry = DB::table('countries')->where('id', $fromId)->first();
        } else {
            $originCode = $this->resolveCountryCode($from, $userCountry);
            $originCountry = DB::table('countries')->where('iso_code_1', $originCode)->first();
        }

        if (!empty($toId) && is_numeric($toId)) {
            $destCountry = DB::table('countries')->where('id', $toId)->first();
        } else {
            $destCode = $this->resolveCountryCode($to);
            $destCountry = DB::table('countries')->where('iso_code_1', $destCode)->first();
        }

        $originCode = $originCountry->iso_code_1 ?? 'US';
        $destCode = $destCountry->iso_code_1 ?? 'US';

        if (!$originCountry || !$destCountry) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        if ($mode === 'Direct Carrier') {
            // Read optional dimension fields for Easyship API
            $length = floatval($request->input('length', 10.0));
            $width = floatval($request->input('width', 10.0));
            $height = floatval($request->input('height', 10.0));
            $dimUnit = $request->input('dim_unit', 'in');

            // Convert to metric (cm/kg)
            $length_cm = ($dimUnit === 'in') ? $length * 2.54 : $length;
            $width_cm = ($dimUnit === 'in') ? $width * 2.54 : $width;
            $height_cm = ($dimUnit === 'in') ? $height * 2.54 : $height;
            $weight_kg = ($unit === 'lb') ? $weight * 0.453592 : $weight;

            // Fetch Easyship Credentials from Environment
            $easyshipToken = env('EASYSHIP_BARRIER_TOKEN');
            $easyshipUrl = env('EASYSHIP_BASE_URL', 'https://public-api.easyship.com/2024-09');

            if (!empty($easyshipToken)) {
                try {
                    $headers = [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $easyshipToken,
                    ];

                    $originAddress = $this->buildEasyshipAddress($from, $originCountry, $originCode, $userRegion);
                    $destAddress = $this->buildEasyshipAddress($to, $destCountry, $destCode);

                    $postData = [
                        "destination_address" => $destAddress,
                        "origin_address" => $originAddress,
                        "incoterms" => "DDU",
                        "insurance" => [
                            "is_insured" => false,
                        ],
                        "courier_settings" => [
                            "show_courier_logo_url" => true,
                            "apply_shipping_rules" => true,
                        ],
                        "shipping_settings" => [
                            "units" => [
                                "weight" => "kg",
                                "dimensions" => "cm",
                            ],
                        ],
                        "parcels" => [
                            [
                                "items" => [
                                    [
                                        "contains_battery_pi966" => false,
                                        "contains_battery_pi967" => false,
                                        "contains_liquids" => false,
                                        "quantity" => 1,
                                        "declared_currency" => "USD",
                                        "hs_code" => "9999",
                                        "declared_customs_value" => 1.0,
                                        "dimensions" => [
                                            "length" => round($length_cm, 2),
                                            "width" => round($width_cm, 2),
                                            "height" => round($height_cm, 2),
                                        ],
                                        "actual_weight" => round($weight_kg, 2),
                                    ],
                                ],
                            ],
                        ],
                    ];

                    \Illuminate\Support\Facades\Log::info('Easyship Request:', $postData);
                    $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                        ->post($easyshipUrl . "/rates", $postData);

                    \Illuminate\Support\Facades\Log::info('Easyship Response Status: ' . $response->status());
                    \Illuminate\Support\Facades\Log::info('Easyship Response Body: ' . substr($response->body(), 0, 500));

                    if ($response->successful()) {
                        $ratesData = $response->json();
                        if (!empty($ratesData['rates'])) {
                            $formatted = [];
                            foreach ($ratesData['rates'] as $index => $rate) {
                                // Lookup a fallback domain for default tenant 1
                                $tenant = DB::table('tenants')->where('id', 1)->first();
                                $domain = DB::table('domains')->where('tenant_id', 1)->first();
                                $sub = ($domain && isset($domain->domain)) ? $domain->domain : (($tenant && isset($tenant->subdomain)) ? $tenant->subdomain : 'abidexpress') . '.respectmart.test';

                                $courierName = $rate['courier_service']['name'] ?? 'Courier Service';
                                $courierLogo = $rate['courier_service']['logo'] ?? null;

                                $formatted[] = [
                                    'id' => $index + 100,
                                    'tenant_id' => 1,
                                    'domain' => $sub,
                                    'initial' => strtoupper(substr($courierName, 0, 1)),
                                    'logo' => $courierLogo,
                                    'name' => $courierName,
                                    'verified' => true,
                                    'rating' => '4.9',
                                    'reviews' => 300 + $index,
                                    'location' => 'Worldwide Express',
                                    'pickup' => 'Door-to-door',
                                    'tags' => ['Live tracking', 'Express service', 'Signature delivery'],
                                    'days' => ($rate['min_delivery_time'] ?? '3') . '-' . ($rate['max_delivery_time'] ?? '5') . ' days',
                                    'price' => '$' . number_format($rate['shipment_charge'], 2),
                                    'total' => '~ $' . number_format($rate['total_charge'], 2) . ' USD',
                                    'featured' => $index === 0
                                ];
                            }
                            return response()->json([
                                'success' => true,
                                'data' => $formatted
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Easyship API rate call failed: ' . $e->getMessage());
                }
            }

            // Direct Carrier: Return simulated courier live rates fallback
            // $directCarriers = [
            //     [
            //         'id' => 1,
            //         'tenant_id' => 1,
            //         'domain' => 'abidexpress.freightmata.com',
            //         'initial' => 'D',
            //         'name' => 'DHL Express',
            //         'verified' => true,
            //         'rating' => '4.9',
            //         'reviews' => 412,
            //         'location' => 'Worldwide Express',
            //         'pickup' => 'Door-to-door',
            //         'tags' => ['Live tracking', 'Express service', 'Signature delivery'],
            //         'days' => '3-5 days',
            //         'price' => '$12.50',
            //         'total' => '~ $' . round($weight * 12.50, 2) . ' USD',
            //         'featured' => true
            //     ],
            //     [
            //         'id' => 2,
            //         'tenant_id' => 2,
            //         'domain' => 'oneil.respectmart.test',
            //         'initial' => 'F',
            //         'name' => 'FedEx International',
            //         'verified' => true,
            //         'rating' => '4.8',
            //         'reviews' => 389,
            //         'location' => 'Priority Cargo',
            //         'pickup' => 'Door-to-door',
            //         'tags' => ['Live tracking', 'Customs handling'],
            //         'days' => '4-6 days',
            //         'price' => '$11.80',
            //         'total' => '~ $' . round($weight * 11.80, 2) . ' USD',
            //         'featured' => false
            //     ],
            //     [
            //         'id' => 3,
            //         'tenant_id' => 4,
            //         'domain' => 'usman.freightmata.com',
            //         'initial' => 'U',
            //         'name' => 'UPS Worldwide Saver',
            //         'verified' => true,
            //         'rating' => '4.7',
            //         'reviews' => 256,
            //         'location' => 'Expedited',
            //         'pickup' => 'Door-to-door',
            //         'tags' => ['Live tracking', 'Guaranteed delivery'],
            //         'days' => '5-7 days',
            //         'price' => '$10.90',
            //         'total' => '~ $' . round($weight * 10.90, 2) . ' USD',
            //         'featured' => false
            //     ]
            // ];

            // return response()->json([
            //     'success' => true,
            //     'data' => $directCarriers
            // ]);
        }

        // Air Freight or Ocean Freight: Generate Top Curated Freighteva Recommendations
        $routeMode = ($mode === 'Ocean Freight') ? 'ocean' : 'air';
        $weightInKg = ($unit === 'lb') ? $weight * 0.453592 : $weight;

        $recommendationEngine = app(\App\Services\Recommendation\PartnerRecommendationEngine::class);
        $recResult = $recommendationEngine->getRecommendations([
            'origin_country_id' => $originCountry->id,
            'destination_country_id' => $destCountry->id,
            'mode' => $routeMode,
            'weight_kg' => $weightInKg,
        ]);

        $formattedResults = [];
        $iconMap = [
            'swift' => '⚡',
            'savers' => '💰',
            'trusted' => '★',
            'convenient' => '🚪',
            'flexible' => '📦',
        ];

        foreach ($recResult['recommendations'] as $rec) {
            $partner = $rec['partner_attribution'];
            $icon = $iconMap[$rec['service_key']] ?? '⚡';

            $formattedResults[] = [
                'id' => $rec['quote_id'],
                'quote_id' => $rec['quote_id'],
                'tenant_id' => $partner['merchant_id'],
                'domain' => 'freighteva.com',
                'initial' => $icon,
                'name' => $rec['service_name'],
                'service_name' => $rec['service_name'],
                'service_key' => $rec['service_key'],
                'badge' => $rec['badge'],
                'highlight' => $rec['highlight'],
                'tagline' => $rec['tagline'],
                'carrier_name' => $partner['company_name'],
                'verified' => true,
                'is_endorsed' => true,
                'rating' => number_format($partner['rating_score'], 1),
                'reviews' => $partner['rating_count'],
                'location' => ($partner['metro_area'] ?: $originCountry->name) . ' Hub',
                'pickup' => ($rec['features']['pickup_available'] ?? true) ? 'Door-to-door' : 'Depot drop-off',
                'tags' => [
                    $rec['badge'],
                    $rec['highlight'],
                    ($routeMode === 'air') ? 'Air consolidation' : 'Ocean container',
                    'Insured & Protected',
                    'Live tracking'
                ],
                'days' => $rec['transit_days'],
                'price' => '$' . number_format($rec['base_rate'], 2),
                'total' => '$' . number_format($rec['calculated_price'], 2) . ' ' . $rec['currency'],
                'calculated_price' => $rec['calculated_price'],
                'base_rate' => $rec['base_rate'],
                'currency' => $rec['currency'],
                'booking_token' => $rec['booking_token'],
                'expires_at' => $rec['expires_at'],
                'expires_in_seconds' => $rec['expires_in_seconds'],
                'scores' => $rec['scores'],
                'partner_attribution' => $partner,
                'features' => $rec['features'],
                'featured' => ($rec['service_key'] === 'swift' || $rec['service_key'] === 'trusted')
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $formattedResults,
            'meta' => [
                'total_recommended' => count($formattedResults),
                'quote_ttl_minutes' => $recResult['quote_ttl_minutes'],
                'diagnostics' => $recResult['diagnostics'] ?? null,
            ]
        ]);
    }


    /**
     * Resolve search string to 2-letter country code with IP awareness and smart city mapping.
     */
    private function resolveCountryCode(string $string, ?string $userCountry = null): string
    {
        $cleanString = trim($string);
        if (empty($cleanString)) {
            return strtoupper($userCountry ?: 'US');
        }

        // 1. Direct Postal / Zip code regex detection
        if (preg_match('/^\d{5}(-\d{4})?$/', $cleanString)) {
            return 'US';
        }
        if (preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i', $cleanString)) {
            return 'CA';
        }
        if (preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i', $cleanString)) {
            return 'GB';
        }

        // 2. Check if country is explicitly specified in parentheses or as last comma segment
        // e.g. "Vancouver, Washington, United States" -> last segment is "United States"
        // e.g. "Manchester, England, United Kingdom (GB)"
        if (preg_match('/\b\(([A-Z]{2})\)\b/i', $cleanString, $matches)) {
            return strtoupper($matches[1]);
        }

        $parts = array_map('trim', explode(',', $cleanString));
        if (count($parts) > 1) {
            $lastPart = end($parts);
            
            // Exact DB match on last part (Country name or ISO code)
            $matchedCountry = DB::table('countries')
                ->where('name', '=', $lastPart)
                ->orWhere('iso_code_1', '=', strtoupper($lastPart))
                ->orWhere('iso_code_2', '=', strtoupper($lastPart))
                ->first();

            if ($matchedCountry) {
                return $matchedCountry->iso_code_1;
            }

            // Substring match on last part
            $matchedCountryLike = DB::table('countries')
                ->where('name', 'like', "%{$lastPart}%")
                ->first();

            if ($matchedCountryLike) {
                return $matchedCountryLike->iso_code_1;
            }
        }

        // 3. Exact DB match on full string
        $fullCountryMatch = DB::table('countries')
            ->where('name', '=', $cleanString)
            ->orWhere('iso_code_1', '=', strtoupper($cleanString))
            ->first();

        if ($fullCountryMatch) {
            return $fullCountryMatch->iso_code_1;
        }

        // 4. Check for known country keywords in the full string
        $lower = strtolower($cleanString);
        if (str_contains($lower, 'united states') || str_contains($lower, 'usa') || preg_match('/\b(us)\b/i', $cleanString)) {
            return 'US';
        }
        if (str_contains($lower, 'united kingdom') || str_contains($lower, 'england') || str_contains($lower, 'scotland') || str_contains($lower, 'wales') || str_contains($lower, 'great britain') || preg_match('/\b(uk|gb)\b/i', $cleanString)) {
            return 'GB';
        }
        if (str_contains($lower, 'canada') || preg_match('/\b(ca)\b/i', $cleanString)) {
            return 'CA';
        }
        if (str_contains($lower, 'nigeria') || preg_match('/\b(ng)\b/i', $cleanString)) {
            return 'NG';
        }
        if (str_contains($lower, 'kenya') || preg_match('/\b(ke)\b/i', $cleanString)) {
            return 'KE';
        }
        if (str_contains($lower, 'pakistan') || preg_match('/\b(pk)\b/i', $cleanString)) {
            return 'PK';
        }
        if (str_contains($lower, 'germany') || str_contains($lower, 'deutschland') || preg_match('/\b(de)\b/i', $cleanString)) {
            return 'DE';
        }
        if (str_contains($lower, 'australia') || preg_match('/\b(au)\b/i', $cleanString)) {
            return 'AU';
        }
        if (str_contains($lower, 'netherlands') || str_contains($lower, 'holland') || preg_match('/\b(nl)\b/i', $cleanString)) {
            return 'NL';
        }

        // 5. Fallback City keyword matching (Only when no explicit country is in the string)
        if (str_contains($lower, 'toronto') || str_contains($lower, 'montreal') || str_contains($lower, 'calgary') || str_contains($lower, 'ottawa') || str_contains($lower, 'vancouver')) {
            return 'CA';
        }
        if (str_contains($lower, 'lagos') || str_contains($lower, 'abuja') || str_contains($lower, 'kano') || str_contains($lower, 'ibadan')) {
            return 'NG';
        }
        if (str_contains($lower, 'nairobi') || str_contains($lower, 'mombasa')) {
            return 'KE';
        }
        if (str_contains($lower, 'london') || str_contains($lower, 'manchester') || str_contains($lower, 'birmingham') || str_contains($lower, 'edinburgh')) {
            return 'GB';
        }
        if (str_contains($lower, 'chicago') || str_contains($lower, 'marysville') || str_contains($lower, 'seattle') || str_contains($lower, 'houston') || str_contains($lower, 'dallas') || str_contains($lower, 'new york') || str_contains($lower, 'los angeles') || str_contains($lower, 'miami')) {
            return 'US';
        }
        if (str_contains($lower, 'karachi') || str_contains($lower, 'lahore') || str_contains($lower, 'islamabad') || str_contains($lower, 'rawalpindi')) {
            return 'PK';
        }

        // 6. DB substring match on parts
        foreach ($parts as $part) {
            $country = DB::table('countries')
                ->where('name', 'like', "%{$part}%")
                ->orWhere('iso_code_1', strtoupper($part))
                ->first();
            if ($country) {
                return $country->iso_code_1;
            }
        }

        // 7. Fallback to client IP context if present
        if (!empty($userCountry) && strlen($userCountry) === 2) {
            return strtoupper($userCountry);
        }

        return 'US'; // Default fallback
    }


    /**
     * Resolve state/region code for the address from query string.
     */
    private function resolveStateCode(string $string, string $countryCode, ?string $userRegion = null): ?string
    {
        $string = strtolower($string);
        $parts = array_map('trim', explode(',', $string));

        if ($countryCode === 'US') {
            if (str_contains($string, 'washington') || str_contains($string, 'seattle') || str_contains($string, 'marysville') || preg_match('/\bwa\b/i', $string)) return 'WA';
            if (str_contains($string, 'illinois') || str_contains($string, 'chicago') || preg_match('/\bil\b/i', $string)) return 'IL';
            if (str_contains($string, 'new york') || preg_match('/\bny\b/i', $string)) return 'NY';
            if (str_contains($string, 'texas') || str_contains($string, 'houston') || str_contains($string, 'dallas') || preg_match('/\btx\b/i', $string)) return 'TX';
            if (str_contains($string, 'california') || str_contains($string, 'los angeles') || str_contains($string, 'san francisco') || preg_match('/\bca\b/i', $string)) return 'CA';
            if (str_contains($string, 'ohio') || str_contains($string, 'columbus') || str_contains($string, 'cleveland') || preg_match('/\boh\b/i', $string)) return 'OH';
            if (str_contains($string, 'florida') || str_contains($string, 'miami') || preg_match('/\bfl\b/i', $string)) return 'FL';
            if (str_contains($string, 'georgia') || str_contains($string, 'atlanta') || preg_match('/\bga\b/i', $string)) return 'GA';

            foreach ($parts as $part) {
                if (strlen($part) === 2 && preg_match('/^[a-z]{2}$/i', $part)) {
                    return strtoupper($part);
                }
            }

            if (!empty($userRegion) && strlen($userRegion) === 2) {
                return strtoupper($userRegion);
            }

            return 'WA'; // Default US fallback for Marysville/Washington corridor
        }

        if ($countryCode === 'CA') {
            if (str_contains($string, 'ontario') || str_contains($string, 'toronto') || str_contains($string, 'ottawa') || preg_match('/\bon\b/i', $string)) return 'ON';
            if (str_contains($string, 'quebec') || str_contains($string, 'montreal') || preg_match('/\bqc\b/i', $string)) return 'QC';
            if (str_contains($string, 'alberta') || str_contains($string, 'calgary') || str_contains($string, 'edmonton') || preg_match('/\bab\b/i', $string)) return 'AB';
            if (str_contains($string, 'british columbia') || str_contains($string, 'vancouver') || preg_match('/\bbc\b/i', $string)) return 'BC';

            foreach ($parts as $part) {
                if (strlen($part) === 2 && preg_match('/^[a-z]{2}$/i', $part)) {
                    return strtoupper($part);
                }
            }
            return 'ON'; // Fallback
        }

        return null;
    }

    /**
     * Build an accurate and validated address object for the Easyship rate calculation.
     */
    private function buildEasyshipAddress(string $query, $country, string $countryCode, ?string $userRegion = null): array
    {
        $code = strtoupper($countryCode);
        $state = $this->resolveStateCode($query, $code, $userRegion);

        // Extract city from query if provided (e.g. "Marysville, WA" -> "Marysville")
        $rawCity = null;
        $cachedPostal = null;
        $cleanQuery = trim($query);

        // Check if AI location cache has parsed values for this query
        $cacheKey = "openai_loc_" . md5("{$cleanQuery}_{$code}");
        $cachedAi = Cache::driver('file')->get($cacheKey);
        if ($cachedAi && !empty($cachedAi['city'])) {
            $rawCity = $cachedAi['city'];
            if (!empty($cachedAi['state'])) {
                $state = $cachedAi['state'];
            }
            if (!empty($cachedAi['postal_code'])) {
                $cachedPostal = $cachedAi['postal_code'];
            }
        }

        if (empty($rawCity) && !empty($cleanQuery)) {
            $parts = array_map('trim', explode(',', $cleanQuery));
            if (!empty($parts[0])) {
                // If parts[0] is not just a country name
                $testCountry = DB::table('countries')->where('name', $parts[0])->first();
                if (!$testCountry) {
                    $rawCity = ucwords($parts[0]);
                }
            }
        }

        $addressMap = [
            'US' => [
                'WA' => ['city' => $rawCity ?: 'Marysville', 'postal_code' => $cachedPostal ?: '98270', 'state' => 'WA'],
                'OH' => ['city' => $rawCity ?: 'Marysville', 'postal_code' => $cachedPostal ?: '43040', 'state' => 'OH'],
                'NY' => ['city' => $rawCity ?: 'New York', 'postal_code' => $cachedPostal ?: '10001', 'state' => 'NY'],
                'CA' => ['city' => $rawCity ?: 'Los Angeles', 'postal_code' => $cachedPostal ?: '90001', 'state' => 'CA'],
                'IL' => ['city' => $rawCity ?: 'Chicago', 'postal_code' => $cachedPostal ?: '60601', 'state' => 'IL'],
                'TX' => ['city' => $rawCity ?: 'Houston', 'postal_code' => $cachedPostal ?: '77001', 'state' => 'TX'],
                'FL' => ['city' => $rawCity ?: 'Miami', 'postal_code' => $cachedPostal ?: '33101', 'state' => 'FL'],
                'GA' => ['city' => $rawCity ?: 'Atlanta', 'postal_code' => $cachedPostal ?: '30301', 'state' => 'GA'],
                'default' => ['city' => $rawCity ?: 'Marysville', 'postal_code' => $cachedPostal ?: '98270', 'state' => 'WA'],
            ],
            'CA' => [
                'ON' => ['city' => $rawCity ?: 'Toronto', 'postal_code' => $cachedPostal ?: 'M5V 2T6', 'state' => 'ON'],
                'QC' => ['city' => $rawCity ?: 'Montreal', 'postal_code' => $cachedPostal ?: 'H3A 0G4', 'state' => 'QC'],
                'BC' => ['city' => $rawCity ?: 'Vancouver', 'postal_code' => $cachedPostal ?: 'V6B 1A1', 'state' => 'BC'],
                'AB' => ['city' => $rawCity ?: 'Calgary', 'postal_code' => $cachedPostal ?: 'T2P 2M5', 'state' => 'AB'],
                'default' => ['city' => $rawCity ?: 'Toronto', 'postal_code' => $cachedPostal ?: 'M5V 2T6', 'state' => 'ON'],
            ],
            'GB' => [
                'default' => ['city' => $rawCity ?: 'London', 'postal_code' => 'SW1A 1AA', 'state' => 'London'],
            ],
            'NG' => [
                'default' => ['city' => $rawCity ?: 'Lagos', 'postal_code' => '100001', 'state' => 'Lagos'],
            ],
            'KE' => [
                'default' => ['city' => $rawCity ?: 'Nairobi', 'postal_code' => '00100', 'state' => 'Nairobi'],
            ],
            'PK' => [
                'default' => ['city' => $rawCity ?: 'Karachi', 'postal_code' => '74200', 'state' => 'Sindh'],
            ],
            'IN' => [
                'default' => ['city' => $rawCity ?: 'Delhi', 'postal_code' => '110001', 'state' => 'Delhi'],
            ],
            'AU' => [
                'default' => ['city' => $rawCity ?: 'Sydney', 'postal_code' => '2000', 'state' => 'NSW'],
            ],
            'DE' => [
                'default' => ['city' => $rawCity ?: 'Berlin', 'postal_code' => '10115', 'state' => 'Berlin'],
            ],
            'NL' => [
                'default' => ['city' => $rawCity ?: 'Amsterdam', 'postal_code' => '1012 JS', 'state' => 'North Holland'],
            ],
            'CM' => [
                'default' => ['city' => $rawCity ?: 'Douala', 'postal_code' => '00237', 'state' => 'Littoral'],
            ],
        ];

        if (isset($addressMap[$code])) {
            $config = $addressMap[$code][$state] ?? $addressMap[$code]['default'];
            return [
                'country_alpha2' => $code,
                'postal_code' => $config['postal_code'],
                'city' => $rawCity ?: $config['city'],
                'state' => $config['state'],
            ];
        }

        return [
            'country_alpha2' => $code,
            'postal_code' => $this->getDefaultZipCode($code),
            'city' => $rawCity ?: ($country->capital ?? 'Capital'),
            'state' => $state ?: ($country->capital ?? 'State'),
        ];
    }

    /**
     * Get default zip code for Easyship API location fallback.
     */
    private function getDefaultZipCode(string $countryCode): string
    {
        $zips = [
            'US' => '10001',
            'CA' => 'M5V 2T6',
            'GB' => 'EC1A 1BB',
            'NG' => '100001',
            'KE' => '00100',
            'PK' => '74200',
            'IN' => '110001',
            'AU' => '2000',
        ];
        return $zips[strtoupper($countryCode)] ?? '10001';
    }

    /**
     * Get unique active popular routes from the database.
     */
    public function popularRoutes(): JsonResponse
    {
        try {
            $routes = DB::table('shipment_routes')
                ->join('countries as c1', 'shipment_routes.sending_country_id', '=', 'c1.id')
                ->join('countries as c2', 'shipment_routes.receiving_country_id', '=', 'c2.id')
                ->select(
                    'c1.id as from_id',
                    'c1.name as from_name',
                    'c1.iso_code_1 as from_code',
                    'c1.emoji as from_flag',
                    'c2.id as to_id',
                    'c2.name as to_name',
                    'c2.iso_code_1 as to_code',
                    'c2.emoji as to_flag'
                )
                ->distinct()
                ->limit(10)
                ->get()
                ->map(function ($r) {
                    return [
                        'from' => $r->from_name,
                        'from_id' => $r->from_id,
                        'from_code' => $r->from_code,
                        'to' => $r->to_name,
                        'to_id' => $r->to_id,
                        'to_code' => $r->to_code,
                        'fromFlag' => (!empty($r->from_flag) && !str_contains($r->from_flag, '?')) ? $r->from_flag : null,
                        'toFlag' => (!empty($r->to_flag) && !str_contains($r->to_flag, '?')) ? $r->to_flag : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $routes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
