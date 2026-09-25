<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmartLocationController extends Controller
{
    /**
     * Resolve smart location queries with IP, country, and proximity biasing.
     */
    public function resolve(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $type = trim($request->query('type', 'from')); // 'from' or 'to'
        $biasCountry = strtoupper(trim($request->query('country', $request->header('CF-IPCountry', 'US'))));
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if (empty($q) || strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $cacheKey = "smart_loc_" . md5("{$q}_{$type}_{$biasCountry}_{$lat}_{$lon}");

        try {
            $results = Cache::driver('file')->remember($cacheKey, 86400, function () use ($q, $type, $biasCountry, $lat, $lon) {
                return $this->performLocationSearch($q, $type, $biasCountry, $lat, $lon);
            });
        } catch (\Exception $e) {
            $results = $this->performLocationSearch($q, $type, $biasCountry, $lat, $lon);
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Internal location search logic: DB Country/Hubs First -> Postal Regex -> AI NLP -> Clean Geocoder.
     */
    private function performLocationSearch(string $q, string $type, string $biasCountry, $lat, $lon): array
    {
        $suggestions = [];
        $cleanQ = trim($q);

        // 1. Check Database Countries & Major Logistics Hubs FIRST (Instant local lookup)
        $dbSuggestions = $this->lookupDatabaseLocations($cleanQ, $type);
        if (!empty($dbSuggestions)) {
            $suggestions = array_merge($suggestions, $dbSuggestions);
            // If we have database matches (countries/cities/states), return immediately for instantaneous <20ms search
            if (count($suggestions) >= 2 || (isset($suggestions[0]['_priority']) && $suggestions[0]['_priority'] >= 85)) {
                return array_map(function ($item) {
                    unset($item['_priority']);
                    return $item;
                }, array_slice($suggestions, 0, 8));
            }
        }

        // 2. Direct Postal / Zip Code Regex Detection
        if (preg_match('/^\d{5}(-\d{4})?$/', $cleanQ) || preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i', $cleanQ) || preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i', $cleanQ)) {
            $postalSuggestions = $this->lookupPostalCode($cleanQ, $biasCountry);
            if (!empty($postalSuggestions)) {
                return $postalSuggestions;
            }
        }

        // 3. Check if query is conversational / natural language (e.g. "grandma in Marysville", "near seattle", >2 words)
        $isNaturalLanguage = (str_word_count($cleanQ) > 2) || preg_match('/\b(grandma|house|near|close to|around|airport|terminal|beside|north of|south of)\b/i', $cleanQ);
        if ($isNaturalLanguage) {
            $aiSuggestion = $this->lookupWithOpenAI($cleanQ, $biasCountry, $lat, $lon);
            if (!empty($aiSuggestion)) {
                $suggestions[] = $aiSuggestion;
            }
        }

        // 4. Query Photon (OpenStreetMap global geocoder) with fast 1.2s timeout fallback
        try {
            $params = [
                'q' => $cleanQ,
                'limit' => 12,
                'lang' => 'en'
            ];
            if (!empty($lat) && !empty($lon) && is_numeric($lat) && is_numeric($lon)) {
                $params['lat'] = floatval($lat);
                $params['lon'] = floatval($lon);
            }

            $response = Http::timeout(1.2)->get('https://photon.komoot.io/api/', $params);

            if ($response->successful()) {
                $features = $response->json()['features'] ?? [];
                foreach ($features as $f) {
                    $props = $f['properties'] ?? [];
                    $city = $props['city'] ?? $props['name'] ?? '';
                    $state = $props['state'] ?? '';
                    $country = $props['country'] ?? '';
                    $countryCode = strtoupper($props['countrycode'] ?? '');
                    $postcode = $props['postcode'] ?? '';
                    $osmValue = strtolower($props['osm_value'] ?? '');
                    $nameLower = strtolower($props['name'] ?? '');

                    // Filter out non-locality POIs (churches, halls, bridges, cemeteries) unless user searched for them
                    $isJunkPoi = in_array($osmValue, ['place_of_worship', 'bridge', 'cemetery', 'monument', 'memorial', 'pitch'])
                        || (preg_match('/\b(kingdom hall|jehovah|church|bridge|graveyard)\b/i', $nameLower) && !preg_match('/\b(hall|church|bridge)\b/i', $cleanQ));

                    if ($isJunkPoi) {
                        continue;
                    }

                    // Filter out unhelpful geographical elements that lack city or country
                    if (empty($city) || empty($country)) {
                        continue;
                    }

                    $displayName = $city;
                    if (!empty($state) && strcasecmp($state, $city) !== 0) {
                        $displayName .= ", {$state}";
                    }
                    if (!empty($country)) {
                        $displayName .= ", {$country}";
                    }

                    $flag = $this->countryCodeToEmoji($countryCode);

                    // Check relevance to user's search text
                    $matchesSearchedCountry = (stripos($country, $cleanQ) !== false || stripos($countryCode, $cleanQ) !== false);
                    $matchesSearchedCity = (stripos($city, $cleanQ) !== false);

                    $priority = 50;
                    if ($matchesSearchedCountry || $matchesSearchedCity) {
                        $priority = 80;
                    } elseif ($countryCode === $biasCountry) {
                        $priority = 60;
                    }

                    $suggestions[] = [
                        'display_name' => $displayName,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'iso_code' => $countryCode,
                        'postal_code' => $postcode,
                        'flag' => $flag,
                        'is_local' => ($countryCode === $biasCountry),
                        'source' => 'geocoder',
                        '_priority' => $priority
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Photon geocoder lookup failed or timed out: ' . $e->getMessage());
        }

        // 5. If suggestions are empty and we haven't run AI yet, invoke OpenAI NLP with 1.5s timeout
        if (empty($suggestions) && !$isNaturalLanguage) {
            $aiSuggestion = $this->lookupWithOpenAI($cleanQ, $biasCountry, $lat, $lon);
            if (!empty($aiSuggestion)) {
                $suggestions[] = $aiSuggestion;
            }
        }

        // 6. De-duplicate
        $unique = [];
        $seen = [];
        foreach ($suggestions as $s) {
            $key = strtolower(trim($s['city']) . '|' . strtolower(trim($s['state'] ?? '')) . '|' . strtolower(trim($s['country'] ?? '')));
            if (!in_array($key, $seen)) {
                $seen[] = $key;
                $unique[] = $s;
            }
        }

        // 7. Sort by relevance priority score, then by exact match
        usort($unique, function ($a, $b) use ($cleanQ, $biasCountry) {
            $pA = $a['_priority'] ?? 50;
            $pB = $b['_priority'] ?? 50;

            if ($pA !== $pB) {
                return $pB <=> $pA;
            }

            // Substring position priority
            $posA = stripos($a['country'] ?? '', $cleanQ);
            $posB = stripos($b['country'] ?? '', $cleanQ);
            if ($posA === 0 && $posB !== 0) return -1;
            if ($posB === 0 && $posA !== 0) return 1;

            return 0;
        });

        // Strip internal _priority key before sending JSON
        return array_map(function ($item) {
            unset($item['_priority']);
            return $item;
        }, array_slice($unique, 0, 8));
    }


    /**
     * AI-Powered Natural Language Location Resolution using OpenAI.
     */
    private function lookupWithOpenAI(string $q, string $biasCountry, $lat = null, $lon = null): ?array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (empty($apiKey)) {
            return null;
        }

        $cacheKey = "openai_loc_" . md5("{$q}_{$biasCountry}");

        try {
            return Cache::driver('file')->remember($cacheKey, 2592000, function () use ($apiKey, $q, $biasCountry, $lat, $lon) {
                $systemPrompt = "You are an expert geographical address and freight logistics location parser for an international shipping platform (Freighteva). "
                    . "Given a user's location search text (which may be informal, conversational like 'grandma in Marysville', 'heathrow airport', or a street/landmark), "
                    . "extract and normalize the target location into standard shipping attributes: city, state/province, country, ISO 2-letter country code (uppercase), and postal_code. "
                    . "The user's current detected visitor country is '{$biasCountry}'. "
                    . "If the location is ambiguous (e.g. 'Marysville'), prefer the location in the user's detected country ('{$biasCountry}'). "
                    . "Respond strictly with a JSON object matching this schema: "
                    . "{\"city\": string, \"state\": string, \"country\": string, \"iso_code\": string, \"postal_code\": string, \"confidence\": string}. "
                    . "If completely unresolvable, return empty strings for the fields.";

                $response = Http::withToken($apiKey)
                    ->timeout(5)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $q]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.1,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $content = $json['choices'][0]['message']['content'] ?? '{}';
                    $data = json_decode($content, true);

                    if (!empty($data['city']) && !empty($data['country'])) {
                        $city = trim($data['city']);
                        $state = trim($data['state'] ?? '');
                        $country = trim($data['country']);
                        $isoCode = strtoupper(trim($data['iso_code'] ?? $biasCountry));
                        $postcode = trim($data['postal_code'] ?? '');

                        $displayName = $city;
                        if (!empty($state) && strcasecmp($state, $city) !== 0) {
                            $displayName .= ", {$state}";
                        }
                        if (!empty($country)) {
                            $displayName .= ", {$country}";
                        }

                        return [
                            'display_name' => $displayName,
                            'city' => $city,
                            'state' => $state,
                            'country' => $country,
                            'iso_code' => $isoCode,
                            'postal_code' => $postcode,
                            'flag' => $this->countryCodeToEmoji($isoCode),
                            'is_local' => ($isoCode === $biasCountry),
                            'source' => 'ai'
                        ];
                    }
                }
                return null;
            });
        } catch (\Exception $e) {
            Log::warning('OpenAI location lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Postal / Zip code specific lookup using Photon.
     */
    private function lookupPostalCode(string $postalCode, string $biasCountry): array
    {
        try {
            $response = Http::timeout(3)->get('https://photon.komoot.io/api/', [
                'q' => $postalCode,
                'limit' => 5,
                'lang' => 'en'
            ]);

            if ($response->successful()) {
                $features = $response->json()['features'] ?? [];
                $out = [];
                foreach ($features as $f) {
                    $props = $f['properties'] ?? [];
                    $city = $props['city'] ?? $props['name'] ?? '';
                    $state = $props['state'] ?? '';
                    $country = $props['country'] ?? '';
                    $countryCode = strtoupper($props['countrycode'] ?? $biasCountry);
                    $postcode = $props['postcode'] ?? $postalCode;

                    if (!empty($city) && !empty($country)) {
                        $displayName = "{$city}, {$state} {$postcode}, {$country}";
                        $out[] = [
                            'display_name' => $displayName,
                            'city' => $city,
                            'state' => $state,
                            'country' => $country,
                            'iso_code' => $countryCode,
                            'postal_code' => $postcode,
                            'flag' => $this->countryCodeToEmoji($countryCode),
                            'is_local' => ($countryCode === $biasCountry),
                            'source' => 'postal'
                        ];
                    }
                }

                usort($out, function ($a, $b) use ($biasCountry) {
                    $aLocal = ($a['iso_code'] === $biasCountry) ? 1 : 0;
                    $bLocal = ($b['iso_code'] === $biasCountry) ? 1 : 0;
                    return $bLocal <=> $aLocal;
                });

                return $out;
            }
        } catch (\Exception $e) {
            Log::warning('Postal code lookup failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Search database countries and shipment routes.
     */
    private function lookupDatabaseLocations(string $q, string $type): array
    {
        $results = [];
        $cleanQ = trim($q);

        // 1. Search Countries
        $countries = DB::table('countries')
            ->where('name', 'like', "{$cleanQ}%")
            ->orWhere('name', 'like', "%{$cleanQ}%")
            ->orWhere('iso_code_1', '=', strtoupper($cleanQ))
            ->orWhere('iso_code_2', '=', strtoupper($cleanQ))
            ->orWhere('capital', 'like', "{$cleanQ}%")
            ->limit(8)
            ->get();

        foreach ($countries as $c) {
            $isExactCountry = (strcasecmp($c->name, $cleanQ) === 0 || strcasecmp($c->iso_code_1, $cleanQ) === 0);
            $isPrefixCountry = (stripos($c->name, $cleanQ) === 0);

            $displayName = $c->name;
            $city = $c->capital ?: $c->name;

            if (stripos($c->capital, $cleanQ) === 0 && !empty($c->capital)) {
                $displayName = "{$c->capital}, {$c->name}";
                $city = $c->capital;
            }

            $priority = $isExactCountry ? 100 : ($isPrefixCountry ? 90 : 75);

            $results[] = [
                'id' => $c->id,
                'display_name' => $displayName,
                'city' => $city,
                'state' => '',
                'country' => $c->name,
                'iso_code' => $c->iso_code_1,
                'postal_code' => '',
                'flag' => $this->countryCodeToEmoji($c->iso_code_1),
                'is_local' => false,
                'source' => 'database',
                '_priority' => $priority
            ];
        }

        // 2. Search Zones (States, Provinces, Major Hubs)
        try {
            $zones = DB::table('zones')
                ->join('countries', 'zones.country_id', '=', 'countries.id')
                ->select(
                    'zones.id as zone_id',
                    'zones.name as zone_name',
                    'countries.id as country_id',
                    'countries.name as country_name',
                    'countries.iso_code_1 as country_code'
                )
                ->where('zones.name', 'like', "{$cleanQ}%")
                ->orWhere('zones.name', 'like', "%{$cleanQ}%")
                ->limit(6)
                ->get();

            foreach ($zones as $z) {
                $isPrefixZone = (stripos($z->zone_name, $cleanQ) === 0);
                $priority = $isPrefixZone ? 85 : 70;

                $results[] = [
                    'id' => $z->country_id,
                    'display_name' => "{$z->zone_name}, {$z->country_name}",
                    'city' => $z->zone_name,
                    'state' => $z->zone_name,
                    'country' => $z->country_name,
                    'iso_code' => $z->country_code,
                    'postal_code' => '',
                    'flag' => $this->countryCodeToEmoji($z->country_code),
                    'is_local' => false,
                    'source' => 'database_zone',
                    '_priority' => $priority
                ];
            }
        } catch (\Exception $e) {
            // Silently continue if zones not accessible
        }

        usort($results, function ($a, $b) {
            return ($b['_priority'] ?? 50) <=> ($a['_priority'] ?? 50);
        });

        return $results;
    }

    /**
     * Convert 2-letter ISO code to flag emoji.
     */
    private function countryCodeToEmoji(string $code): string
    {
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2) {
            return '🌐';
        }
        $first = ord($code[0]) - 65 + 0x1F1E6;
        $second = ord($code[1]) - 65 + 0x1F1E6;
        return mb_chr($first, 'UTF-8') . mb_chr($second, 'UTF-8');
    }
}
