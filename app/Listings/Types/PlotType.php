<?php

namespace App\Listings\Types;

use App\Listings\ListingType;
use App\Models\Listing;
use App\Support\Format;

class PlotType extends ListingType
{
    public function key(): string { return 'plot'; }
    public function label(): string { return 'Individual Plot'; }
    public function plural(): string { return 'Plots'; }
    public function slug(): string { return 'plots'; }
    public function icon(): string { return 'grid'; }
    public function requiresPatta(): bool { return true; }

    public function fields(): array
    {
        return [
            'area' => ['label' => 'Plot area', 'kind' => 'number', 'required' => true, 'min' => 1],
            'area_unit' => ['label' => 'Area unit', 'kind' => 'select', 'options' => ['sqft' => 'sq ft', 'cents' => 'cents'], 'required' => true],
            'length_ft' => ['label' => 'Length', 'kind' => 'number', 'unit' => 'ft'],
            'width_ft' => ['label' => 'Width', 'kind' => 'number', 'unit' => 'ft'],
            'facing' => ['label' => 'Facing', 'kind' => 'select', 'options' => array_combine(self::FACINGS, self::FACINGS), 'required' => true],
            'road_width_ft' => ['label' => 'Road width', 'kind' => 'number', 'unit' => 'ft'],
            'corner' => ['label' => 'Corner plot', 'kind' => 'bool'],
            'east_boundary' => ['label' => 'East boundary', 'kind' => 'text', 'max' => 100],
            'west_boundary' => ['label' => 'West boundary', 'kind' => 'text', 'max' => 100],
            'north_boundary' => ['label' => 'North boundary', 'kind' => 'text', 'max' => 100],
            'south_boundary' => ['label' => 'South boundary', 'kind' => 'text', 'max' => 100],
        ];
    }

    public function searchColumns(array $d): array
    {
        $unit = $d['area_unit'] ?? 'sqft';

        return ['area_value' => $d['area'] ?? null, 'area_unit' => $unit, 'area_sqft' => self::areaSqft($d['area'] ?? null, $unit), 'bhk' => null, 'facing' => $d['facing'] ?? null];
    }

    public function specs(Listing $l): array
    {
        $d = $l->type_details ?? [];

        return array_values(array_filter([
            $l->area_sqft ? Format::num($l->area_sqft).' '.__('sq ft').' ('.Format::num($l->area_sqft / 435.6, 1).' '.__('cents').')' : null,
            $l->facing ? __(':facing facing', ['facing' => __($l->facing)]) : null,
            ! empty($d['corner']) ? __('Corner') : null,
        ]));
    }
}
