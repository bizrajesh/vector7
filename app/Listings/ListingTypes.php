<?php

namespace App\Listings;

use App\Listings\Types;
use InvalidArgumentException;

/** Registry of listing types. "layout" is not a listing — it is a launched project (full or direct). */
class ListingTypes
{
    public const CLASSES = [
        'plot' => Types\PlotType::class,
        'flat' => Types\FlatType::class,
        'farm_land' => Types\FarmLandType::class,
        'commercial' => Types\CommercialType::class,
        'villa' => Types\VillaType::class,
        'house' => Types\HouseType::class,
    ];

    public const PURPOSES = ['sale' => 'Sale', 'rent' => 'Rent / Lease'];

    /** URL purpose segment ↔ stored purpose. */
    public const PURPOSE_SLUGS = ['buy' => 'sale', 'rent' => 'rent'];

    public const LAYOUT_SLUG = 'layouts';

    public static function get(string $key): ListingType
    {
        if (! isset(self::CLASSES[$key])) {
            throw new InvalidArgumentException("Unknown listing type $key");
        }

        return app(self::CLASSES[$key]);
    }

    /** @return array<string, ListingType> */
    public static function all(): array
    {
        return collect(self::CLASSES)->map(fn ($c) => app($c))->all();
    }

    public static function options(): array
    {
        return collect(self::all())->map->label()->all();
    }

    public static function fromSlug(string $slug): ?string
    {
        foreach (self::all() as $key => $t) {
            if ($t->slug() === $slug) {
                return $key;
            }
        }

        return null;
    }

    /** Route pattern for the {type} URL segment. */
    public static function slugPattern(): string
    {
        return implode('|', array_merge(array_map(fn ($t) => $t->slug(), self::all()), [self::LAYOUT_SLUG]));
    }
}
