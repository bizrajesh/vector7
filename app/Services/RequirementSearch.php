<?php

namespace App\Services;

use App\Models\Plot;
use App\Models\Project;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Post a requirement": plain words → filters (location, budget, size, facing, approval type) → matching available plots.
 * Claude does the parsing when AI is configured; a built-in parser handles common phrasing otherwise.
 */
class RequirementSearch
{
    public const KEYS = ['location', 'budget_min', 'budget_max', 'size_min', 'size_max', 'facing', 'approval_type', 'corner'];

    public static function parse(string $text): array
    {
        $filters = null;
        if (ClaudeClient::available()) {
            try {
                $locations = self::knownLocations()->implode(', ');
                $reply = ClaudeClient::ask('requirement_search',
                    "Turn this plot-buying requirement from India into JSON filters. Requirement: \"$text\"\n"
                    ."Known locations: $locations\n"
                    .'Return ONLY JSON with keys: location (string or null; prefer a known location), budget_min, budget_max (rupees as numbers; 1 lakh = 100000, 1 crore = 10000000), '
                    .'size_min, size_max (sq ft; 1 cent = 435.6 sq ft; for one size give ±10%), facing (East/West/North/South/North-East/North-West/South-East/South-West or null), '
                    .'approval_type (Village/Town/City or null), corner (true/false/null).', null, [], 400);
                $filters = ClaudeClient::json($reply);
            } catch (\Throwable) {
                $filters = null;
            }
        }
        if (! is_array($filters)) {
            $filters = self::parseLocally($text);
        }

        return self::clean($filters);
    }

    public static function parseLocally(string $text): array
    {
        $t = strtolower(str_replace([',', '₹'], ['', ' '], $text));
        $f = [];
        $money = function (string $num, string $unit): float {
            $n = (float) $num;

            return match (true) {
                str_starts_with($unit, 'cr') => $n * 10000000,
                str_starts_with($unit, 'l') => $n * 100000,
                str_starts_with($unit, 'k') => $n * 1000,
                default => $n,
            };
        };
        if (preg_match('/(?:under|below|less than|upto|up to|within|max(?:imum)?|budget(?: of)?)\s*(?:rs\.?|inr)?\s*([\d.]+)\s*(crores?|cr|lakhs?|lacs?|l\b|k\b)?/', $t, $m)) {
            $f['budget_max'] = $money($m[1], $m[2] ?? '');
        } elseif (preg_match('/([\d.]+)\s*(crores?|cr|lakhs?|lacs?|l\b)/', $t, $m)) {
            $f['budget_max'] = $money($m[1], $m[2]);
        }
        if (preg_match('/between\s*([\d.]+)\s*(crores?|cr|lakhs?|lacs?|l)?\s*(?:and|-|to)\s*([\d.]+)\s*(crores?|cr|lakhs?|lacs?|l)/', $t, $m)) {
            $f['budget_min'] = $money($m[1], $m[2] ?: $m[4]);
            $f['budget_max'] = $money($m[3], $m[4]);
        }
        if (preg_match('/([\d.]+)\s*(?:sq\.?\s*ft|sqft|square feet|sft)/', $t, $m)) {
            $f['size_min'] = round((float) $m[1] * 0.9);
            $f['size_max'] = round((float) $m[1] * 1.1);
        } elseif (preg_match('/([\d.]+)\s*cents?/', $t, $m)) {
            $sq = (float) $m[1] * 435.6;
            $f['size_min'] = round($sq * 0.9);
            $f['size_max'] = round($sq * 1.1);
        }
        foreach (['north-east', 'north-west', 'south-east', 'south-west', 'north east', 'north west', 'south east', 'south west', 'east', 'west', 'north', 'south'] as $dir) {
            if (preg_match('/\b'.preg_quote($dir, '/').'\b(?:[\s-]*facing)?/', $t)) {
                $f['facing'] = ucwords(str_replace(' ', '-', $dir), '-');
                break;
            }
        }
        foreach (['village', 'town', 'city'] as $a) {
            if (str_contains($t, $a.' approv')) {
                $f['approval_type'] = ucfirst($a);
            }
        }
        if (str_contains($t, 'corner')) {
            $f['corner'] = true;
        }
        foreach (self::knownLocations() as $loc) {
            if ($loc !== '' && str_contains($t, strtolower($loc))) {
                $f['location'] = $loc;
                break;
            }
        }

        return $f;
    }

    private static function clean(array $f): array
    {
        $out = [];
        foreach (self::KEYS as $k) {
            $v = $f[$k] ?? null;
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            $out[$k] = match ($k) {
                'location' => mb_substr((string) $v, 0, 100),
                'facing' => in_array($v, Plot::FACINGS, true) ? $v : null,
                'approval_type' => in_array($v, Project::APPROVAL_TYPES, true) ? $v : null,
                'corner' => (bool) $v,
                default => is_numeric($v) ? (float) $v : null,
            };
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    public static function knownLocations(): Collection
    {
        return app(Tenancy::class)->withoutScope(fn () => Project::launched()->select('location', 'district')->get()
            ->flatMap(fn ($p) => [$p->location, $p->district])->filter()->unique()->values());
    }

    /** Available plots in launched projects matching the filters. */
    public static function matches(array $f, int $limit = 60): Collection
    {
        return app(Tenancy::class)->withoutScope(function () use ($f, $limit) {
            $q = Plot::query()->where('status', 'available')
                ->whereHas('project', fn (Builder $p) => $p->launched()
                    ->when($f['location'] ?? null, fn ($w, $loc) => $w->where(fn ($x) => $x->where('location', 'like', "%$loc%")->orWhere('district', 'like', "%$loc%")->orWhere('address', 'like', "%$loc%")))
                    ->when($f['approval_type'] ?? null, fn ($w, $a) => $w->where('approval_type', $a)))
                ->when($f['size_min'] ?? null, fn ($w, $v) => $w->where('size_sqft', '>=', $v))
                ->when($f['size_max'] ?? null, fn ($w, $v) => $w->where('size_sqft', '<=', $v))
                ->when($f['facing'] ?? null, fn ($w, $v) => $w->where('facing', $v))
                ->when($f['corner'] ?? null, fn ($w) => $w->where('is_corner', true))
                ->with('project');
            $plots = $q->limit(500)->get()->filter(function (Plot $p) use ($f) {
                $price = $p->currentPrice();

                return (! isset($f['budget_max']) || $price <= $f['budget_max']) && (! isset($f['budget_min']) || $price >= $f['budget_min']);
            });

            return $plots->sortBy(fn ($p) => $p->currentPrice())->take($limit)->values();
        });
    }

    public static function describe(array $f): array
    {
        $chips = [];
        if (isset($f['location'])) {
            $chips[] = 'Near '.$f['location'];
        }
        if (isset($f['budget_min']) || isset($f['budget_max'])) {
            $chips[] = 'Budget '.(isset($f['budget_min']) ? \App\Support\Format::inrShort($f['budget_min']).' – ' : 'up to ').\App\Support\Format::inrShort($f['budget_max'] ?? 0);
        }
        if (isset($f['size_min']) || isset($f['size_max'])) {
            $chips[] = \App\Support\Format::num($f['size_min'] ?? 0).'–'.\App\Support\Format::num($f['size_max'] ?? 0).' sq ft';
        }
        if (isset($f['facing'])) {
            $chips[] = $f['facing'].' facing';
        }
        if (isset($f['approval_type'])) {
            $chips[] = $f['approval_type'].' approval';
        }
        if (! empty($f['corner'])) {
            $chips[] = 'Corner plot';
        }

        return $chips;
    }
}
