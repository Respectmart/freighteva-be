<?php

namespace App\Services\Routing;

class GeocodingService
{
    /**
     * Known Metro / Zone Postal Mapping Dictionary (USA, UK, CA, AU, NG, etc.)
     */
    protected array $knownPostcodes = [
        // USA
        '60601' => ['lat' => 41.8853, 'lng' => -87.6216, 'city' => 'Chicago', 'zone' => 'US-ZONE-2-MIDWEST', 'country' => 'US'],
        '60602' => ['lat' => 41.8831, 'lng' => -87.6298, 'city' => 'Chicago', 'zone' => 'US-ZONE-2-MIDWEST', 'country' => 'US'],
        '60616' => ['lat' => 41.8448, 'lng' => -87.6324, 'city' => 'Chicago', 'zone' => 'US-ZONE-2-MIDWEST', 'country' => 'US'],
        '10001' => ['lat' => 40.7501, 'lng' => -73.9996, 'city' => 'New York', 'zone' => 'US-ZONE-1-NORTHEAST', 'country' => 'US'],
        '90001' => ['lat' => 33.9736, 'lng' => -118.249, 'city' => 'Los Angeles', 'zone' => 'US-ZONE-4-WEST', 'country' => 'US'],
        '77001' => ['lat' => 29.7604, 'lng' => -95.3698, 'city' => 'Houston', 'zone' => 'US-ZONE-3-SOUTH', 'country' => 'US'],
        '30301' => ['lat' => 33.7490, 'lng' => -84.3880, 'city' => 'Atlanta', 'zone' => 'US-ZONE-3-SOUTH', 'country' => 'US'],

        // UK
        'E1 6AN' => ['lat' => 51.5200, 'lng' => -0.0750, 'city' => 'London', 'zone' => 'UK-ZONE-1-SOUTHEAST', 'country' => 'GB'],
        'EC1A 1BB' => ['lat' => 51.5200, 'lng' => -0.1000, 'city' => 'London', 'zone' => 'UK-ZONE-1-SOUTHEAST', 'country' => 'GB'],
        'M1 1AE' => ['lat' => 53.4808, 'lng' => -2.2426, 'city' => 'Manchester', 'zone' => 'UK-ZONE-2-NORTH', 'country' => 'GB'],
        'B1 1AA' => ['lat' => 52.4862, 'lng' => -1.8904, 'city' => 'Birmingham', 'zone' => 'UK-ZONE-3-MIDLANDS', 'country' => 'GB'],
        'G1 1AA' => ['lat' => 55.8642, 'lng' => -4.2518, 'city' => 'Glasgow', 'zone' => 'UK-ZONE-4-SCOTLAND', 'country' => 'GB'],

        // Australia
        '2000' => ['lat' => -33.8688, 'lng' => 151.2093, 'city' => 'Sydney', 'zone' => 'AU-ZONE-2-EAST-SOUTH', 'country' => 'AU'],
        '2150' => ['lat' => -33.8150, 'lng' => 151.0011, 'city' => 'Parramatta', 'zone' => 'AU-ZONE-2-EAST-SOUTH', 'country' => 'AU'],
        '3000' => ['lat' => -37.8136, 'lng' => 144.9631, 'city' => 'Melbourne', 'zone' => 'AU-ZONE-3-SOUTHEAST', 'country' => 'AU'],
        '4000' => ['lat' => -27.4698, 'lng' => 153.0251, 'city' => 'Brisbane', 'zone' => 'AU-ZONE-1-EAST-NORTH', 'country' => 'AU'],
        '6000' => ['lat' => -31.9505, 'lng' => 115.8605, 'city' => 'Perth', 'zone' => 'AU-ZONE-4-WEST-NORTH', 'country' => 'AU'],

        // Canada
        'M5V 2T6' => ['lat' => 43.6426, 'lng' => -79.3871, 'city' => 'Toronto', 'zone' => 'CA-ZONE-2-ONTARIO', 'country' => 'CA'],
        'H2Z 1A4' => ['lat' => 45.5017, 'lng' => -73.5673, 'city' => 'Montreal', 'zone' => 'CA-ZONE-1-QUEBEC', 'country' => 'CA'],
        'V6B 1A1' => ['lat' => 49.2827, 'lng' => -123.1207, 'city' => 'Vancouver', 'zone' => 'CA-ZONE-4-PACIFIC', 'country' => 'CA'],

        // Nigeria
        '100001' => ['lat' => 6.5244, 'lng' => 3.3792, 'city' => 'Lagos', 'zone' => 'NG-ZONE-1-SOUTHWEST', 'country' => 'NG'],
        '900001' => ['lat' => 9.0765, 'lng' => 7.3986, 'city' => 'Abuja', 'zone' => 'NG-ZONE-2-NORTHCENTRAL', 'country' => 'NG'],
    ];

    /**
     * Resolve latitude, longitude, city, and Freighteva Zone from postcode/address.
     */
    public function resolveLocation(string $postcode, ?string $countryCode = null, ?string $address = null): array
    {
        $cleanPostcode = strtoupper(trim($postcode));
        $cleanCountry = strtoupper(trim($countryCode ?? 'US'));

        // 1. Direct dictionary match
        if (isset($this->knownPostcodes[$cleanPostcode])) {
            return $this->knownPostcodes[$cleanPostcode];
        }

        // 2. Partial UK Outward Postcode match (e.g. 'E1' from 'E1 6AN')
        $outward = explode(' ', $cleanPostcode)[0];
        foreach ($this->knownPostcodes as $pc => $data) {
            if (str_starts_with($pc, $outward) && $data['country'] === $cleanCountry) {
                return $data;
            }
        }

        // 3. Fallback coordinate calculation based on country defaults
        $fallbackCoordinates = [
            'US' => ['lat' => 41.8818, 'lng' => -87.6231, 'city' => 'Chicago', 'zone' => 'US-ZONE-2-MIDWEST', 'country' => 'US'],
            'GB' => ['lat' => 51.5074, 'lng' => -0.1278, 'city' => 'London', 'zone' => 'UK-ZONE-1-SOUTHEAST', 'country' => 'GB'],
            'UK' => ['lat' => 51.5074, 'lng' => -0.1278, 'city' => 'London', 'zone' => 'UK-ZONE-1-SOUTHEAST', 'country' => 'GB'],
            'CA' => ['lat' => 43.6532, 'lng' => -79.3832, 'city' => 'Toronto', 'zone' => 'CA-ZONE-2-ONTARIO', 'country' => 'CA'],
            'AU' => ['lat' => -33.8688, 'lng' => 151.2093, 'city' => 'Sydney', 'zone' => 'AU-ZONE-2-EAST-SOUTH', 'country' => 'AU'],
            'NG' => ['lat' => 6.5244, 'lng' => 3.3792, 'city' => 'Lagos', 'zone' => 'NG-ZONE-1-SOUTHWEST', 'country' => 'NG'],
            'DE' => ['lat' => 52.5200, 'lng' => 13.4050, 'city' => 'Berlin', 'zone' => 'DE-ZONE-1-EAST', 'country' => 'DE'],
            'FR' => ['lat' => 48.8566, 'lng' => 2.3522, 'city' => 'Paris', 'zone' => 'FR-ZONE-1-ILE-DE-FRANCE', 'country' => 'FR'],
            'NL' => ['lat' => 52.3676, 'lng' => 4.9041, 'city' => 'Amsterdam', 'zone' => 'NL-ZONE-1-RANDSTAD-NORTH', 'country' => 'NL'],
        ];

        return $fallbackCoordinates[$cleanCountry] ?? [
            'lat' => 41.8818,
            'lng' => -87.6231,
            'city' => 'Unknown',
            'zone' => 'GLOBAL-ZONE',
            'country' => $cleanCountry,
        ];
    }
}
