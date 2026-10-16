<?php

namespace Database\Seeders;

use App\Models\FeatureMaster;
use Illuminate\Database\Seeder;

/** App-level Features Master (spec 17.4). Tenants can add their own on top. Safe to run again. */
class FeatureSeeder extends Seeder
{
    /** [group, name, icon, types] — types: list of listing types, or 'all'. */
    public const FEATURES = [
        ['Legal', 'Clear title', 'shield', 'all'],
        ['Legal', 'DTCP approved', 'check', ['plot', 'layout', 'villa', 'house']],
        ['Legal', 'RERA registered', 'check', ['plot', 'layout', 'flat', 'villa', 'commercial']],
        ['Legal', 'Bank loan available', 'rupee', 'all'],
        ['Legal', 'Patta ready', 'doc', ['plot', 'layout', 'farm_land', 'house', 'villa']],
        ['Basic', 'Gated community', 'lock', ['plot', 'layout', 'flat', 'villa', 'house']],
        ['Basic', 'Compound wall', 'layers', ['plot', 'layout', 'farm_land', 'villa', 'house']],
        ['Basic', 'Black-top roads', 'map', ['layout', 'plot']],
        ['Basic', 'Storm-water drains', 'layers', ['layout', 'plot']],
        ['Basic', 'Street lights', 'sparkles', ['layout', 'plot']],
        ['Basic', 'Car parking', 'cart', ['flat', 'villa', 'house', 'commercial']],
        ['Basic', 'Lift', 'arrow-right', ['flat', 'commercial']],
        ['Security', 'CCTV', 'eye', ['layout', 'flat', 'villa', 'commercial']],
        ['Security', '24×7 security', 'shield', ['layout', 'flat', 'villa', 'commercial']],
        ['Water & Power', '24×7 water', 'refresh', 'all'],
        ['Water & Power', 'Borewell', 'compass', ['plot', 'layout', 'farm_land', 'villa', 'house']],
        ['Water & Power', 'EB connection', 'sparkles', 'all'],
        ['Water & Power', 'Power backup', 'sparkles', ['flat', 'villa', 'commercial']],
        ['Water & Power', 'Overhead tank', 'layers', ['layout', 'flat', 'villa', 'house']],
        ['Lifestyle', 'Park / children\'s play area', 'star', ['layout', 'flat', 'villa']],
        ['Lifestyle', 'Temple nearby', 'location', 'all'],
        ['Lifestyle', 'Gym', 'star', ['flat', 'villa']],
        ['Lifestyle', 'Swimming pool', 'star', ['flat', 'villa']],
        ['Lifestyle', 'Coconut / fruit trees', 'star', ['farm_land', 'house', 'villa']],
        ['Connectivity', 'Bus stop within 1 km', 'location', 'all'],
        ['Connectivity', 'School nearby', 'location', 'all'],
        ['Connectivity', 'Hospital nearby', 'location', 'all'],
        ['Connectivity', 'Main road frontage', 'map', ['plot', 'layout', 'farm_land', 'commercial']],
        ['Connectivity', 'Railway station nearby', 'location', 'all'],
    ];

    public function run(): void
    {
        foreach (self::FEATURES as $i => [$group, $name, $icon, $types]) {
            $types = $types === 'all' ? ['all'] : $types;
            foreach ($types as $type) {
                FeatureMaster::firstOrCreate(['tenant_id' => null, 'listing_type' => $type, 'name' => $name], ['group' => $group, 'icon' => $icon, 'sort' => $i, 'is_active' => true]);
            }
        }
    }
}
