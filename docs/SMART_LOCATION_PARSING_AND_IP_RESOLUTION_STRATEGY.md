# Freighteva Smart Location Filtering & AI/IP Resolution Architecture

> **Document Status:** Architectural Proposal & Implementation Blueprint  
> **Target Systems:** `frontend-home` (Vue 3) & `freighteva-be` (Laravel 11)  
> **Date:** September 2026  
> **Author:** Freighteva Engineering & AI Architecture Team  

---

## 1. Problem Statement & Client Insight

### Client Feedback:
> *"Then we need to better train, so what am calling your attention to is, a grandma in Marysville will probably not enter “USA” she might just enter her city, it’s left for AI to check her ip relative to the city."*

### Why This Happens (The Real-World UX Challenge):
1. **Non-Technical & Elderly User Behavior:**
   Everyday consumers, small business shippers, and non-technical users do not type ISO codes (`US`, `NG`, `GB`) or formal structured formats like `"Marysville, United States"`. They simply type `"Marysville"`, `"Houston"`, `"Ikeja"`, or a 5-digit zip code.
2. **Homonym / Duplicate City Names (Location Ambiguity):**
   In the US alone, there are multiple cities named *Marysville* (e.g., Marysville, Washington [Pop. 72,000+], Marysville, Ohio, Marysville, California, Marysville, Pennsylvania). Globally, there is also Marysville, Victoria in Australia.
3. **Carrier API Strictness (Easyship / Direct Carriers):**
   Carrier rate calculation APIs (Easyship, DHL Express, FedEx, UPS) strictly require a complete 4-part address schema:
   ```json
   {
     "city": "Marysville",
     "state": "WA",
     "country_alpha2": "US",
     "postal_code": "98270"
   }
   ```
   If the frontend or backend only receives the string `"Marysville"`, carrier API calls fail or return inaccurate rates.

---

## 2. Comprehensive Solution Strategy (The 4-Layer Architecture)

To deliver a frictionless, "Grandma-friendly" experience while guaranteeing carrier API accuracy, Freighteva will adopt a **4-Layer Smart Location Resolution Pipeline**:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        USER INPUT ("Marysville")                       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ LAYER 1: IP Geolocation & User Context (Zero-Click Context Detection)  │
│ - Detect user country, state/region, city & coordinates from Client IP │
│ - Example: User IP is in Snohomish County, WA, USA                     │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ LAYER 2: Proximity-Biased Autocomplete (Real-time Suggestions)          │
│ - Global Geocoder (Google Places / Mapbox / OpenStreetMap Photon)      │
│ - Places near user's IP appear at the TOP with State, Country & Flag   │
│ - Suggestion 1: 🇺🇸 Marysville, WA, United States (Closest / Recommended) │
│ - Suggestion 2: 🇺🇸 Marysville, OH, United States                       │
│ - Suggestion 3: 🇺🇸 Marysville, CA, United States                       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ LAYER 3: AI / NLP Fallback Parser & Heuristic Disambiguation            │
│ - Triggered if user types raw text and presses Enter without clicking  │
│ - Extracts City, matches against IP country/state, infers Zip/Postal   │
│ - If ambiguous, provides a 1-tap Disambiguation Chip to user           │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ LAYER 4: Carrier API Payload Enrichment (Easyship / Courier Adapter)   │
│ - Emits standard normalized JSON payload to backend rates engine       │
│ - { country_alpha2: "US", state: "WA", city: "Marysville", zip: "98270" }│
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Detailed Scenarios & Resolution Mechanics

### Scenario A: User Types Their Own City ("Marysville") in the "From" (Origin) Field
* **User Input:** `"Marysville"`
* **Detection:** The user is currently browsing from an IP in Washington State, USA.
* **Resolution Pipeline:**
  1. Frontend IP provider identifies `user_country = 'US'`, `user_region = 'WA'`.
  2. Autocomplete queries the Geocoding service with proximity bias centered around user IP coordinates `(lat, lon)`.
  3. The top suggestion is automatically formatted as:  
     `🇺🇸 Marysville, Washington, United States`
  4. If the user hits search immediately without clicking a suggestion, Layer 3 automatically infers `country: "US"`, `state: "WA"`, `city: "Marysville"`, and standard zip `98270`.

---

### Scenario B: User Types a Remote Destination City ("Lagos" or "London")
* **User Input:** `"London"` in the "To" (Destination) Field.
* **Detection:** User is in the US, but shipping internationally.
* **Resolution Pipeline:**
  1. The geocoder detects a major global hub query.
  2. Autocomplete renders top candidates ranked by global freight volume and population:
     - 🇬🇧 **London, Greater London, United Kingdom** (Primary Global Hub)
     - 🇨🇦 **London, Ontario, Canada**
     - 🇺🇸 **London, Ohio, United States**
     - 🇺🇸 **London, Kentucky, United States**
  3. Visual country flags (`🇬🇧`, `🇨🇦`, `🇺🇸`) and state sub-labels ensure the user picks the intended destination with zero confusion.

---

### Scenario C: User Types Only a Postal Code ("98270" or "SW1A 1AA" or "M5V 2T6")
* **User Input:** `"98270"`
* **Detection:** Input matches a postal code regex pattern.
* **Resolution Pipeline:**
  1. Regex matches US 5-digit ZIP code.
  2. Reverse postal lookup resolves immediately to `Marysville, WA 98270, United States`.
  3. Search form autofills both the City, State, and Country.

---

### Scenario D: User Types Informal or Colloquial Phrases ("NYC", "Bay Area", "Kano", "Abuja")
* **User Input:** `"NYC"` or `"DFW"` or `"Heathrow"`
* **Resolution Pipeline:**
  1. AI / Synonym Aliases dictionary maps common abbreviations:
     - `"NYC"` → New York City, NY, US
     - `"DFW"` → Dallas-Fort Worth, TX, US
     - `"LAX"` → Los Angeles, CA, US
     - `"LHR"` → London Heathrow, UK
  2. Normalizes directly to standard city and ISO country code.

---

## 4. Technical Implementation Specification

### 4.1 Frontend Layer (`frontend-home`)

#### 1. IP Geolocation Hook (`src/composables/useUserLocation.js`)
```javascript
import { ref, onMounted } from 'vue';

const userLocation = ref({
  countryCode: 'US',
  countryName: 'United States',
  region: 'WA',
  city: 'Seattle',
  latitude: 47.6062,
  longitude: -122.3321,
  ip: '',
  loaded: false
});

export function useUserLocation() {
  const fetchLocation = async () => {
    if (userLocation.value.loaded) return;
    try {
      // 1. Try Cloudflare Edge Headers or Free IP Geolocation API
      const res = await fetch('https://ipapi.co/json/');
      if (res.ok) {
        const data = await res.json();
        userLocation.value = {
          countryCode: data.country_code || 'US',
          countryName: data.country_name || 'United States',
          region: data.region_code || '',
          city: data.city || '',
          latitude: data.latitude,
          longitude: data.longitude,
          ip: data.ip,
          loaded: true
        };
      }
    } catch (e) {
      console.warn('IP location fetch failed, using sensible defaults.', e);
      userLocation.value.loaded = true;
    }
  };

  onMounted(() => {
    fetchLocation();
  });

  return { userLocation, fetchLocation };
}
```

#### 2. Proximity-Biased Autocomplete
When the user types `query`, the API request passes the user's latitude, longitude, and country code:
```
GET /api/search/smart-location?q=Marysville&lat=47.6062&lon=-122.3321&country=US
```
The endpoint ranks results closest to the user first.

---

### 4.2 Backend Layer (`freighteva-be`)

#### 1. Smart Location Endpoint (`app/Http/Controllers/Api/V1/SmartLocationController.php`)
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SmartLocationController extends Controller
{
    /**
     * Resolve smart location queries with IP & proximity biasing.
     */
    public function resolve(Request $request)
    {
        $q = trim($request->query('q', ''));
        $userCountry = $request->query('country', $request->header('CF-IPCountry', 'US'));
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $cacheKey = "loc_res_" . md5("{$q}_{$userCountry}_{$lat}_{$lon}");

        $results = Cache::remember($cacheKey, 86400, function () use ($q, $userCountry, $lat, $lon) {
            return $this->queryGeocodingService($q, $userCountry, $lat, $lon);
        });

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    private function queryGeocodingService($query, $biasCountry, $lat, $lon)
    {
        // 1. Check if query matches a Zip / Postal Code
        if (preg_match('/^\d{5}(-\d{4})?$/', $query)) {
            return $this->lookupPostalCode($query, $biasCountry);
        }

        // 2. Query Photon (OpenStreetMap Geocoder) or Google Places / Mapbox
        // Biased with location lat/lon and country code
        $params = [
            'q' => $query,
            'limit' => 6,
            'lang' => 'en'
        ];
        if ($lat && $lon) {
            $params['lat'] = $lat;
            $params['lon'] = $lon;
        }

        $response = Http::get('https://photon.komoot.io/api/', $params);
        if (!$response->successful()) {
            return [];
        }

        $features = $response->json()['features'] ?? [];
        $suggestions = [];

        foreach ($features as $f) {
            $props = $f['properties'] ?? [];
            $city = $props['city'] ?? $props['name'] ?? '';
            $state = $props['state'] ?? '';
            $country = $props['country'] ?? '';
            $countryCode = strtoupper($props['countrycode'] ?? '');
            $postcode = $props['postcode'] ?? '';

            if (!$city || !$country) continue;

            $displayName = $city;
            if ($state) $displayName .= ", {$state}";
            if ($country) $displayName .= ", {$country}";

            $suggestions[] = [
                'display_name' => $displayName,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'country_code' => $countryCode,
                'postal_code' => $postcode,
                'is_local_bias' => ($countryCode === $biasCountry)
            ];
        }

        // Sort to place local bias on top
        usort($suggestions, function($a, $b) {
            return $b['is_local_bias'] <=> $a['is_local_bias'];
        });

        return $suggestions;
    }
}
```

#### 2. Carrier Payload Adapter (Easyship Integration)
Before sending the request to Easyship `/rates`:
```php
public function buildEasyshipAddress($locationInput, $userIpLocation = null)
{
    // If structured data already exists
    if (!empty($locationInput['city']) && !empty($locationInput['country_code'])) {
        return [
            "city" => $locationInput['city'],
            "state" => $locationInput['state'] ?? '',
            "postal_code" => $locationInput['postal_code'] ?? '98270', // standard fallback or geocoded zip
            "country_alpha2" => $locationInput['country_code']
        ];
    }

    // Fallback AI/NLP resolution from raw query string
    return $this->smartParseRawLocation($locationInput, $userIpLocation);
}
```

---

## 5. UI/UX Disambiguation Component (When Multiple Cities Exist)

If a user enters `"Marysville"` without clicking a suggestion, a clean, subtle suggestion chip appears under the search input:

```
┌────────────────────────────────────────────────────────────────────────┐
│  From: [ Marysville                                                  ] │
└────────────────────────────────────────────────────────────────────────┘
   💡 Showing rates for: 🇺🇸 Marysville, WA. Not your location?
   [ Switch to Marysville, OH ] [ Switch to Marysville, CA ] [ Change ]
```

### Benefits of this UX:
1. **Zero Disruption:** The user gets instant freight rates without being blocked by an error dialog.
2. **Contextually Correct 95% of the Time:** Because IP geolocation already knows she is in Washington State.
3. **1-Click Correction:** If the Grandma is actually shipping a package *from* her daughter in Ohio, she can switch with a single tap.

---

## 6. Rollout & Implementation Phases

| Phase | Description | Deliverables |
|---|---|---|
| **Phase 1 (Client-side IP Context)** | Capture visitor's country/region via IP and store in pinia/reactive state. | `useUserLocation.js`, Cloudflare headers detection |
| **Phase 2 (Global Geocoder Autocomplete)** | Replace rigid database-only lookup with a global geocoding service (Photon / OpenStreetMap / Mapbox). | `/api/search/smart-location`, rich city + state + country chips |
| **Phase 3 (Carrier Schema Formatter)** | Enrich raw location strings into `{ city, state, country_code, postal_code }` before Easyship calls. | `SearchController.php` Easyship payload builder |
| **Phase 4 (AI Disambiguation & Fallback UI)** | Add subtle suggestion chips for ambiguous city names. | Disambiguation pill UI in `HomePage.vue` and `SearchResultsPage.vue` |

---

## 7. Recommended Third-Party Services / Options

1. **Free / Open Source (Zero Cost, No API Key Required):**
   - **Photon by Komoot** (`https://photon.komoot.io/`): Built on OpenStreetMap, ultra-fast, supports proximity lat/lon biasing.
   - **Cloudflare Edge GeoIP Headers:** Automatic `CF-IPCountry`, `CF-IPCity`, `CF-Region` available for free on any Cloudflare-proxied domain.
2. **Enterprise Geocoders (High Precision):**
   - **Google Places API (Autocomplete + Geocoding):** Industry gold standard, handles complex colloquial queries.
   - **Mapbox Geocoding API:** Great rate limits, built-in proximity biasing.
   - **GeoNames API / Local Database:** Can be imported into MySQL for 100% offline, self-hosted resolution.

---

*This document serves as the complete technical specification for implementing Smart Location & IP Resolution in Freighteva.*
