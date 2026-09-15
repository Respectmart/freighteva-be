<?php

namespace Database\Seeders;

use App\Models\MerchantShippingCapability;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class FreightevaRoutingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Update Existing Tenants with Geolocation & Zone Attributes
        $tenants = [
            // Tenant 2: Enitan Shipping Inc (Chicago, USA)
            [
                'id' => 2,
                'name' => 'enitan shipping inc',
                'subdomain' => 'oneil',
                'custom_domain' => 'eohtllc.com',
                'company_name' => 'Enitan Shipping Inc',
                'latitude' => 41.8818,
                'longitude' => -87.6231,
                'country_code' => 'US',
                'zone_code' => 'US-ZONE-2-MIDWEST',
                'metro_area' => 'Chicago',
                'service_radius_km' => 35,
                'is_verified' => true,
                'rating_score' => 4.90,
                'booking_success_rate' => 99.20,
            ],
            // Tenant 1: PostDoorman (London, UK)
            [
                'id' => 1,
                'name' => 'abidexpress',
                'subdomain' => 'abidexpress',
                'custom_domain' => 'postdoorman.com',
                'company_name' => 'PostDoorman UK Logistics',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'country_code' => 'GB',
                'zone_code' => 'UK-ZONE-1-SOUTHEAST',
                'metro_area' => 'London',
                'service_radius_km' => 40,
                'is_verified' => true,
                'rating_score' => 4.85,
                'booking_success_rate' => 98.50,
            ],
            // Tenant 4: Adeoyo Freight (Sydney, Australia)
            [
                'id' => 4,
                'name' => 'usman',
                'subdomain' => 'usman',
                'custom_domain' => 'adeoyoclinic.com',
                'company_name' => 'Adeoyo Express Cargo',
                'latitude' => -33.8688,
                'longitude' => 151.2093,
                'country_code' => 'AU',
                'zone_code' => 'AU-ZONE-2-EAST-SOUTH',
                'metro_area' => 'Sydney',
                'service_radius_km' => 30,
                'is_verified' => true,
                'rating_score' => 4.75,
                'booking_success_rate' => 97.80,
            ],
        ];

        foreach ($tenants as $tData) {
            $t = Tenant::find($tData['id']);
            if ($t) {
                $t->update([
                    'latitude' => $tData['latitude'],
                    'longitude' => $tData['longitude'],
                    'country_code' => $tData['country_code'],
                    'zone_code' => $tData['zone_code'],
                    'metro_area' => $tData['metro_area'],
                    'service_radius_km' => $tData['service_radius_km'],
                    'is_verified' => $tData['is_verified'],
                    'rating_score' => $tData['rating_score'],
                    'booking_success_rate' => $tData['booking_success_rate'],
                ]);
            }
        }

        // 2. Populate Merchant Shipping Capabilities
        MerchantShippingCapability::truncate();

        $capabilities = [
            // Tenant 2 (Enitan - Chicago): Air & Ocean to Nigeria, UK, Ghana
            [
                'tenant_id' => 2,
                'origin_country_code' => 'US',
                'destination_country_code' => 'NG',
                'destination_city' => 'Lagos',
                'freight_mode' => 'air',
                'min_weight_kg' => 0.50,
                'max_weight_kg' => 500.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 10.00,
                'min_transit_days' => 3,
                'max_transit_days' => 5,
                'corridor_specialization_tier' => 5, // Top Specialist Corridor
                'is_active' => true,
            ],
            [
                'tenant_id' => 2,
                'origin_country_code' => 'US',
                'destination_country_code' => 'NG',
                'destination_city' => null,
                'freight_mode' => 'ocean',
                'min_weight_kg' => 20.00,
                'max_weight_kg' => 10000.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 3.50,
                'min_transit_days' => 25,
                'max_transit_days' => 35,
                'corridor_specialization_tier' => 5,
                'is_active' => true,
            ],
            [
                'tenant_id' => 2,
                'origin_country_code' => 'US',
                'destination_country_code' => 'GB',
                'destination_city' => 'London',
                'freight_mode' => 'air',
                'min_weight_kg' => 0.50,
                'max_weight_kg' => 300.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 12.50,
                'min_transit_days' => 2,
                'max_transit_days' => 4,
                'corridor_specialization_tier' => 4,
                'is_active' => true,
            ],

            // Tenant 1 (PostDoorman - London): Air & Ocean to US, Nigeria, Canada
            [
                'tenant_id' => 1,
                'origin_country_code' => 'GB',
                'destination_country_code' => 'US',
                'destination_city' => 'New York',
                'freight_mode' => 'air',
                'min_weight_kg' => 0.50,
                'max_weight_kg' => 500.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 14.00,
                'min_transit_days' => 3,
                'max_transit_days' => 5,
                'corridor_specialization_tier' => 5,
                'is_active' => true,
            ],
            [
                'tenant_id' => 1,
                'origin_country_code' => 'GB',
                'destination_country_code' => 'NG',
                'destination_city' => 'Lagos',
                'freight_mode' => 'air',
                'min_weight_kg' => 1.00,
                'max_weight_kg' => 400.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 8.50,
                'min_transit_days' => 4,
                'max_transit_days' => 7,
                'corridor_specialization_tier' => 4,
                'is_active' => true,
            ],

            // Tenant 4 (Adeoyo - Sydney, AU): Air & Ocean to Nigeria, US, UK, NZ
            [
                'tenant_id' => 4,
                'origin_country_code' => 'AU',
                'destination_country_code' => 'NG',
                'destination_city' => 'Lagos',
                'freight_mode' => 'air',
                'min_weight_kg' => 0.50,
                'max_weight_kg' => 500.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 18.00,
                'min_transit_days' => 5,
                'max_transit_days' => 9,
                'corridor_specialization_tier' => 5,
                'is_active' => true,
            ],
            [
                'tenant_id' => 4,
                'origin_country_code' => 'AU',
                'destination_country_code' => 'US',
                'destination_city' => 'Los Angeles',
                'freight_mode' => 'air',
                'min_weight_kg' => 1.00,
                'max_weight_kg' => 300.00,
                'pickup_available' => true,
                'dropoff_available' => true,
                'base_rate_per_kg' => 16.50,
                'min_transit_days' => 4,
                'max_transit_days' => 7,
                'corridor_specialization_tier' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($capabilities as $cap) {
            MerchantShippingCapability::create($cap);
        }
    }
}
