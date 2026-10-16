<?php

namespace App\Listings\Types;

use App\Listings\ListingType;
use App\Models\Listing;
use App\Support\Format;

class FarmLandType extends ListingType
{
    public function key(): string { return 'farm_land'; }
    public function label(): string { return 'Farm Land'; }
    public function plural(): string { return 'Farm Land'; }
    public function slug(): string { return 'farm-land'; }
    public function icon(): string { return 'compass'; }
    public function requiresPatta(): bool { return true; }

    public function fields(): array
    {
        return [
            'extent' => ['label' => 'Extent', 'kind' => 'number', 'required' => true, 'min' => 0.01],
            'extent_unit' => ['label' => 'Extent unit', 'kind' => 'select', 'options' => ['acres' => 'acres', 'cents' => 'cents'], 'required' => true],
            'soil_type' => ['label' => 'Soil type', 'kind' => 'text', 'max' => 60],
            'water_source' => ['label' => 'Water source', 'kind' => 'select', 'options' => ['borewell' => 'Borewell', 'canal' => 'Canal', 'well' => 'Open well', 'none' => 'None']],
            'current_crop' => ['label' => 'Current crop / trees', 'kind' => 'text', 'max' => 120],
            'road_access' => ['label' => 'Road access', 'kind' => 'bool'],
            'road_width_ft' => ['label' => 'Road width', 'kind' => 'number', 'unit' => 'ft'],
            'eb_connection' => ['label' => 'EB connection', 'kind' => 'bool'],
            'classification' => ['label' => 'Dry / wet classification', 'kind' => 'select', 'options' => ['dry' => 'Dry (Punjai)', 'wet' => 'Wet (Nanjai)', 'mixed' => 'Mixed']],
        ];
    }

    public function searchColumns(array $d): array
    {
        $unit = $d['extent_unit'] ?? 'acres';

        return ['area_value' => $d['extent'] ?? null, 'area_unit' => $unit, 'area_sqft' => self::areaSqft($d['extent'] ?? null, $unit), 'bhk' => null, 'facing' => null];
    }

    public function specs(Listing $l): array
    {
        $d = $l->type_details ?? [];

        return array_values(array_filter([
            $l->area_value ? Format::num($l->area_value, fmod((float) $l->area_value, 1) ? 2 : 0).' '.$l->area_unit : null,
            isset($d['water_source']) && $d['water_source'] !== 'none' ? ucfirst($d['water_source']) : null,
            ! empty($d['eb_connection']) ? 'EB connection' : null,
        ]));
    }
}
