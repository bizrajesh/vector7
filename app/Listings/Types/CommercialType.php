<?php

namespace App\Listings\Types;

use App\Listings\ListingType;
use App\Models\Listing;
use App\Support\Format;

class CommercialType extends ListingType
{
    public function key(): string { return 'commercial'; }
    public function label(): string { return 'Commercial Space'; }
    public function plural(): string { return 'Commercial'; }
    public function slug(): string { return 'commercial'; }
    public function icon(): string { return 'briefcase'; }
    public function purposes(): array { return ['sale', 'rent']; }

    public function fields(): array
    {
        return [
            'sub_type' => ['label' => 'Space type', 'kind' => 'select', 'options' => ['shop' => 'Shop', 'office' => 'Office', 'showroom' => 'Showroom', 'warehouse' => 'Warehouse'], 'required' => true],
            'built_up_sqft' => ['label' => 'Built-up area', 'kind' => 'number', 'unit' => 'sq ft', 'required' => true, 'min' => 1],
            'floor_no' => ['label' => 'Floor', 'kind' => 'integer', 'max' => 200],
            'frontage_ft' => ['label' => 'Frontage', 'kind' => 'number', 'unit' => 'ft'],
            'parking' => ['label' => 'Parking', 'kind' => 'bool'],
            'washroom' => ['label' => 'Washroom', 'kind' => 'bool'],
            'power_kw' => ['label' => 'Power load', 'kind' => 'number', 'unit' => 'kW'],
        ];
    }

    public function searchColumns(array $d): array
    {
        return ['area_value' => $d['built_up_sqft'] ?? null, 'area_unit' => 'sqft', 'area_sqft' => $d['built_up_sqft'] ?? null, 'bhk' => null, 'facing' => null];
    }

    public function specs(Listing $l): array
    {
        $d = $l->type_details ?? [];

        return array_values(array_filter([
            isset($d['sub_type']) ? ucfirst($d['sub_type']) : null,
            $l->area_sqft ? Format::num($l->area_sqft).' sq ft' : null,
            isset($d['frontage_ft']) ? Format::num($d['frontage_ft']).' ft frontage' : null,
        ]));
    }
}
