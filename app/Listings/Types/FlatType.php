<?php

namespace App\Listings\Types;

use App\Listings\ListingType;

class FlatType extends ListingType
{
    public function key(): string { return 'flat'; }
    public function label(): string { return 'Flat / Apartment'; }
    public function plural(): string { return 'Flats'; }
    public function slug(): string { return 'flats'; }
    public function icon(): string { return 'building'; }
    public function purposes(): array { return ['sale', 'rent']; }

    public function fields(): array
    {
        return [
            'bhk' => ['label' => 'BHK', 'kind' => 'integer', 'required' => true, 'min' => 1, 'max' => 10],
            'built_up_sqft' => ['label' => 'Built-up area', 'kind' => 'number', 'unit' => 'sq ft', 'required' => true, 'min' => 1],
            'carpet_sqft' => ['label' => 'Carpet area', 'kind' => 'number', 'unit' => 'sq ft'],
            'floor_no' => ['label' => 'Floor no.', 'kind' => 'integer', 'max' => 200],
            'total_floors' => ['label' => 'Total floors', 'kind' => 'integer', 'max' => 200],
            'facing' => ['label' => 'Facing', 'kind' => 'select', 'options' => array_combine(self::FACINGS, self::FACINGS)],
            'furnishing' => ['label' => 'Furnishing', 'kind' => 'select', 'options' => self::FURNISHING],
            'car_parking' => ['label' => 'Car parking', 'kind' => 'integer', 'max' => 10],
            'age_years' => ['label' => 'Age of building', 'kind' => 'integer', 'unit' => 'years', 'max' => 200],
            'maintenance_month' => ['label' => 'Maintenance / month', 'kind' => 'number', 'unit' => '₹'],
        ];
    }

    public function searchColumns(array $d): array
    {
        return ['area_value' => $d['built_up_sqft'] ?? null, 'area_unit' => 'sqft', 'area_sqft' => $d['built_up_sqft'] ?? null, 'bhk' => $d['bhk'] ?? null, 'facing' => $d['facing'] ?? null];
    }
}
