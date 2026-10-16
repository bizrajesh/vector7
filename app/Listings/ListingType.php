<?php

namespace App\Listings;

use App\Models\Listing;

/**
 * One class per listing type (spec 17.2). Type-specific fields live in listings.type_details (JSON) and are
 * validated here; the fields used for search (area, BHK, facing) are copied to real, indexed columns.
 * To add a type later: create a subclass and register it in ListingTypes::CLASSES — no new tables needed.
 */
abstract class ListingType
{
    public const FACINGS = ['East', 'West', 'North', 'South', 'North-East', 'North-West', 'South-East', 'South-West'];

    public const FURNISHING = ['unfurnished' => 'Unfurnished', 'semi' => 'Semi-furnished', 'full' => 'Fully furnished'];

    abstract public function key(): string;

    abstract public function label(): string;

    /** Plural label used in navigation ("Flats"). */
    abstract public function plural(): string;

    /** URL segment: /buy/{slug}/{city}/… */
    abstract public function slug(): string;

    /**
     * Field definitions: name => [label, kind (number|integer|text|select|bool), options?, unit?, required?, min?, max?]
     *
     * @return array<string, array<string, mixed>>
     */
    abstract public function fields(): array;

    /** Purposes allowed: sale, and rent for flats, houses, villas and commercial space. */
    public function purposes(): array
    {
        return ['sale'];
    }

    public function requiresPatta(): bool
    {
        return false;
    }

    /** Icon name (App\Support\Icons). */
    public function icon(): string
    {
        return 'building';
    }

    /** Laravel validation rules for type_details.* */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields() as $name => $f) {
            $r = [($f['required'] ?? false) ? 'required' : 'nullable'];
            switch ($f['kind']) {
                case 'number':
                    $r[] = 'numeric';
                    $r[] = 'min:'.($f['min'] ?? 0);
                    if (isset($f['max'])) {
                        $r[] = 'max:'.$f['max'];
                    }
                    break;
                case 'integer':
                    $r[] = 'integer';
                    $r[] = 'min:'.($f['min'] ?? 0);
                    $r[] = 'max:'.($f['max'] ?? 1000);
                    break;
                case 'select':
                    $r[] = 'in:'.implode(',', array_keys($f['options']));
                    break;
                case 'bool':
                    $r[] = 'boolean';
                    break;
                default:
                    $r[] = 'string';
                    $r[] = 'max:'.($f['max'] ?? 150);
            }
            $rules["details.$name"] = $r;
        }

        return $rules;
    }

    public function attributeNames(): array
    {
        return collect($this->fields())->mapWithKeys(fn ($f, $n) => ["details.$n" => strtolower($f['label'])])->all();
    }

    /** Keep only known keys, cast values. */
    public function clean(array $details): array
    {
        $out = [];
        foreach ($this->fields() as $name => $f) {
            $v = $details[$name] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $out[$name] = match ($f['kind']) {
                'number' => (float) $v,
                'integer' => (int) $v,
                'bool' => (bool) $v,
                default => trim((string) $v),
            };
        }

        return $out;
    }

    /**
     * Indexed search columns derived from the details: area_value, area_unit, area_sqft, bhk, facing.
     *
     * @return array{area_value:?float, area_unit:string, area_sqft:?float, bhk:?int, facing:?string}
     */
    public function searchColumns(array $d): array
    {
        return ['area_value' => null, 'area_unit' => 'sqft', 'area_sqft' => null, 'bhk' => $d['bhk'] ?? null, 'facing' => $d['facing'] ?? null];
    }

    /** Short key specs for cards, e.g. ["2 BHK", "1,050 sq ft"]. */
    public function specs(Listing $l): array
    {
        return array_values(array_filter([
            $l->bhk ? $l->bhk.' BHK' : null,
            $l->area_sqft ? \App\Support\Format::num($l->area_sqft).' sq ft' : null,
            $l->facing ? $l->facing.' facing' : null,
        ]));
    }

    /** Label => value rows for the detail page "key specs" table. */
    public function specTable(Listing $l): array
    {
        $rows = [];
        $d = $l->type_details ?? [];
        foreach ($this->fields() as $name => $f) {
            if (! array_key_exists($name, $d)) {
                continue;
            }
            $v = $d[$name];
            $rows[$f['label']] = match ($f['kind']) {
                'bool' => $v ? 'Yes' : 'No',
                'select' => $f['options'][$v] ?? $v,
                'number' => \App\Support\Format::num($v, fmod((float) $v, 1) ? 2 : 0).(isset($f['unit']) ? ' '.$f['unit'] : ''),
                'integer' => $v.(isset($f['unit']) ? ' '.$f['unit'] : ''),
                default => $v,
            };
        }

        return $rows;
    }

    protected static function areaSqft(?float $value, string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        return round(match ($unit) {
            'cents' => $value * 435.6,
            'acres' => $value * 43560,
            'sqm' => $value * 10.7639,
            default => $value,
        }, 2);
    }
}
