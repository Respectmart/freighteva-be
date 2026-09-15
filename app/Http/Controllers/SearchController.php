<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Resolve countries using ID first if available
        if (!empty($fromId) && is_numeric($fromId)) {
            $originCountry = DB::table('countries')->where('id', $fromId)->first();
        } else {
            $originCode = $this->resolveCountryCode($from);
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

                    $originAddress = $this->buildEasyshipAddress($from, $originCountry, $originCode);
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

        // Air Freight or Ocean Freight
        $routeMode = ($mode === 'Ocean Freight') ? 'ocean' : 'air';

        // Query routes
        $routes = DB::table('shipment_routes')
            ->join('tenants', 'shipment_routes.tenant_id', '=', 'tenants.id')
            ->leftJoin('domains', function ($join) {
                $join->on('tenants.id', '=', 'domains.tenant_id')
                     ->whereRaw('domains.id = (select min(id) from domains where tenant_id = tenants.id)');
            })
            ->select(
                'shipment_routes.*',
                'tenants.company_name',
                'tenants.status as tenant_status',
                'tenants.sub_type',
                'tenants.subdomain',
                DB::raw("COALESCE(domains.domain, tenants.custom_domain) as custom_domain"),
                DB::raw("COALESCE((SELECT ROUND(AVG(rating), 1) FROM reviews WHERE reviews.tenant_id = tenants.id), 0) as rating_avg"),
                DB::raw("COALESCE((SELECT COUNT(id) FROM reviews WHERE reviews.tenant_id = tenants.id), 0) as rating_count")
            )
            ->where('sending_country_id', $originCountry->id)
            ->where('receiving_country_id', $destCountry->id)
            ->where('mode', $routeMode)
            ->get();

        $sortedRoutes = $routes->sort(function ($a, $b) {
            $subPriority = ['advanced' => 3, 'intermediate' => 2, 'starter' => 1];
            $aSub = $subPriority[$a->sub_type] ?? 1;
            $bSub = $subPriority[$b->sub_type] ?? 1;

            if ($aSub !== $bSub) {
                return $bSub <=> $aSub;
            }

            // Status Priority
            $aActive = ($a->tenant_status === 'active') ? 1 : 0;
            $bActive = ($b->tenant_status === 'active') ? 1 : 0;
            if ($aActive !== $bActive) {
                return $bActive <=> $aActive;
            }

            // Rating Priority
            if ($a->rating_avg != $b->rating_avg) {
                return $b->rating_avg <=> $a->rating_avg; // Higher rating first
            }

            // Rate Priority
            return $a->shipping_rate <=> $b->shipping_rate; // Lower rate first
        });

        // Format for Vue search results list
        $formattedResults = [];
        foreach ($sortedRoutes as $route) {
            $sub = $route->custom_domain ?: ($route->subdomain ? $route->subdomain . '.respectmart.test' : 'respectmart.test');
            $formattedResults[] = [
                'id' => $route->id,
                'tenant_id' => $route->tenant_id,
                'domain' => $sub,
                'initial' => strtoupper(substr($route->company_name ?? 'C', 0, 1)),
                'name' => $route->company_name ?? 'Verified Carrier',
                'verified' => $route->tenant_status === 'active',
                'rating' => number_format($route->rating_avg, 1),
                'reviews' => $route->rating_count,
                'location' => $originCountry->name . ' Depot',
                'pickup' => 'Door-to-door',
                'tags' => [
                    $originCode . ' - ' . $destCode . ' specialty',
                    ($routeMode === 'air') ? 'Air consolidation' : 'Ocean container',
                    'Insured',
                    'Live tracking'
                ],
                'days' => $route->min_delivery_day . '-' . $route->max_delivery_day . ' days',
                'price' => '$' . number_format($route->shipping_rate, 2),
                'total' => '~ $' . number_format($route->shipping_rate * $weight, 2) . ' USD',
                'featured' => $route->sub_type === 'advanced'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $formattedResults
        ]);
    }

    /**
     * Resolve search string to 2-letter country code.
     */
    private function resolveCountryCode(string $string): string
    {
        $string = strtolower($string);

        if (str_contains($string, 'toronto') || str_contains($string, 'canada') || preg_match('/\bca\b/i', $string)) {
            return 'CA';
        }
        if (str_contains($string, 'lagos') || str_contains($string, 'nigeria') || preg_match('/\bng\b/i', $string)) {
            return 'NG';
        }
        if (str_contains($string, 'nairobi') || str_contains($string, 'kenya') || preg_match('/\bke\b/i', $string)) {
            return 'KE';
        }
        if (str_contains($string, 'london') || str_contains($string, 'kingdom') || preg_match('/\buk\b/i', $string) || preg_match('/\bgb\b/i', $string)) {
            return 'GB';
        }
        if (str_contains($string, 'chicago') || str_contains($string, 'states') || preg_match('/\bus\b/i', $string)) {
            return 'US';
        }
        if (str_contains($string, 'karachi') || str_contains($string, 'pakistan') || preg_match('/\bpk\b/i', $string)) {
            return 'PK';
        }

        // Try to match dynamically from the database
        $parts = array_map('trim', explode(',', $string));
        foreach ($parts as $part) {
            $country = DB::table('countries')
                ->where('name', 'like', "%{$part}%")
                ->orWhere('iso_code_1', strtoupper($part))
                ->first();
            if ($country) {
                return $country->iso_code_1;
            }
        }

        return 'US'; // Default fallback
    }

    /**
     * Resolve state/region code for the address from query string.
     */
    private function resolveStateCode(string $string, string $countryCode): ?string
    {
        $string = strtolower($string);
        $parts = array_map('trim', explode(',', $string));

        if ($countryCode === 'US') {
            if (str_contains($string, 'washington') || str_contains($string, 'wa')) return 'WA';
            if (str_contains($string, 'illinois') || str_contains($string, 'il') || str_contains($string, 'chicago')) return 'IL';
            if (str_contains($string, 'new york') || str_contains($string, 'ny')) return 'NY';
            if (str_contains($string, 'texas') || str_contains($string, 'tx')) return 'TX';
            if (str_contains($string, 'california') || str_contains($string, 'ca')) return 'CA';

            foreach ($parts as $part) {
                if (strlen($part) === 2 && preg_match('/^[a-z]{2}$/i', $part)) {
                    return strtoupper($part);
                }
            }
            return 'NY'; // Fallback
        }

        if ($countryCode === 'CA') {
            if (str_contains($string, 'ontario') || str_contains($string, 'on') || str_contains($string, 'toronto')) return 'ON';
            if (str_contains($string, 'quebec') || str_contains($string, 'qc') || str_contains($string, 'montreal')) return 'QC';
            if (str_contains($string, 'alberta') || str_contains($string, 'ab')) return 'AB';
            if (str_contains($string, 'british columbia') || str_contains($string, 'bc') || str_contains($string, 'vancouver')) return 'BC';

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
    private function buildEasyshipAddress(string $query, $country, string $countryCode): array
    {
        $code = strtoupper($countryCode);
        $state = $this->resolveStateCode($query, $code);

        $addressMap = [
            'US' => [
                'NY' => ['city' => 'New York', 'postal_code' => '10001', 'state' => 'NY'],
                'CA' => ['city' => 'Los Angeles', 'postal_code' => '90001', 'state' => 'CA'],
                'IL' => ['city' => 'Chicago', 'postal_code' => '60601', 'state' => 'IL'],
                'TX' => ['city' => 'Houston', 'postal_code' => '77001', 'state' => 'TX'],
                'WA' => ['city' => 'Seattle', 'postal_code' => '98101', 'state' => 'WA'],
                'default' => ['city' => 'New York', 'postal_code' => '10001', 'state' => 'NY'],
            ],
            'CA' => [
                'ON' => ['city' => 'Toronto', 'postal_code' => 'M5V 2T6', 'state' => 'ON'],
                'QC' => ['city' => 'Montreal', 'postal_code' => 'H3A 0G4', 'state' => 'QC'],
                'BC' => ['city' => 'Vancouver', 'postal_code' => 'V6B 1A1', 'state' => 'BC'],
                'AB' => ['city' => 'Calgary', 'postal_code' => 'T2P 2M5', 'state' => 'AB'],
                'default' => ['city' => 'Toronto', 'postal_code' => 'M5V 2T6', 'state' => 'ON'],
            ],
            'GB' => [
                'default' => ['city' => 'London', 'postal_code' => 'SW1A 1AA', 'state' => 'London'],
            ],
            'NG' => [
                'default' => ['city' => 'Lagos', 'postal_code' => '100001', 'state' => 'Lagos'],
            ],
            'KE' => [
                'default' => ['city' => 'Nairobi', 'postal_code' => '00100', 'state' => 'Nairobi'],
            ],
            'PK' => [
                'default' => ['city' => 'Karachi', 'postal_code' => '74200', 'state' => 'Sindh'],
            ],
            'IN' => [
                'default' => ['city' => 'Delhi', 'postal_code' => '110001', 'state' => 'Delhi'],
            ],
            'AU' => [
                'default' => ['city' => 'Sydney', 'postal_code' => '2000', 'state' => 'NSW'],
            ],
            'DE' => [
                'default' => ['city' => 'Berlin', 'postal_code' => '10115', 'state' => 'Berlin'],
            ],
            'NL' => [
                'default' => ['city' => 'Amsterdam', 'postal_code' => '1012 JS', 'state' => 'North Holland'],
            ],
            'CM' => [
                'default' => ['city' => 'Douala', 'postal_code' => '00237', 'state' => 'Littoral'],
            ],
        ];

        if (isset($addressMap[$code])) {
            $config = $addressMap[$code][$state] ?? $addressMap[$code]['default'];
            return [
                'country_alpha2' => $code,
                'postal_code' => $config['postal_code'],
                'city' => $config['city'],
                'state' => $config['state'],
            ];
        }

        return [
            'country_alpha2' => $code,
            'postal_code' => $this->getDefaultZipCode($code),
            'city' => $country->capital ?? 'Capital',
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
