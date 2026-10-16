<?php

namespace App\Listings\Types;

use App\Listings\ListingType;

class HouseType extends ListingType
{
    public function key(): string { return 'house'; }
    public function label(): string { return 'Independent House'; }
    public function plural(): string { return 'Houses'; }
    public function slug(): string { return 'houses'; }
    public function icon(): string { return 'home'; }
    public function purposes(): array { return ['sale', 'rent']; }

    public function fields(): array
    {
        return [
            'bhk' => ['label' => 'BHK', 'kind' => 'integer', 'required' => true, 'min' => 1, 'max' => 15],
            'plot_area_sqft' => ['label' => 'Plot area', 'kind' => 'number', 'unit' => 'sq ft', 'min' => 1],
            'built_up_sqft' => ['label' => 'Built-up area', 'kind' => 'number', 'unit' => 'sq ft', 'required' => true, 'min' => 1],
            'floors' => ['label' => 'Floors', 'kind' => 'integer', 'max' => 10],
            'facing' => ['label' => 'Facing', 'kind' => 'select', 'options' => array_combine(self::FACINGS, self::FACINGS)],
            'furnishing' => ['label' => 'Furnishing', 'kind' => 'select', 'options' => self::FURNISHING],
            'car_parking' => ['label' => 'Car parking', 'kind' => 'integer', 'max' => 10],
            'age_years' => ['label' => 'Age of building', 'kind' => 'integer', 'unit' => 'years', 'max' => 200],
        ];
    }

    public function searchColumns(array $d): array
    {
        return ['area_value' => $d['built_up_sqft'] ?? null, 'area_unit' => 'sqft', 'area_sqft' => $d['built_up_sqft'] ?? null, 'bhk' => $d['bhk'] ?? null, 'facing' => $d['facing'] ?? null];
    }
}
