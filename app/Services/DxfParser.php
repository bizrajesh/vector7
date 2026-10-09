<?php

namespace App\Services;

/**
 * Minimal DXF (ASCII) reader: closed LWPOLYLINE / POLYLINE outlines measured with the shoelace formula,
 * plot numbers taken from TEXT / MTEXT labels that sit inside an outline.
 * Units: $INSUNITS 2 = feet (default), 1 = inches, 4 = mm, 5 = cm, 6 = metres.
 */
class DxfParser
{
    private const SQFT = [0 => 1.0, 1 => 1 / 144, 2 => 1.0, 4 => 0.0000107639, 5 => 0.00107639, 6 => 10.7639];

    private const LINEAR_FT = [0 => 1.0, 1 => 1 / 12, 2 => 1.0, 4 => 0.00328084, 5 => 0.0328084, 6 => 3.28084];

    /** @return array{units:int, polygons:array, texts:array} */
    public static function read(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $pairs = [];
        for ($i = 0; $i + 1 < count($lines); $i += 2) {
            $pairs[] = [(int) trim($lines[$i]), trim($lines[$i + 1])];
        }
        $units = 2;
        foreach ($pairs as $k => [$code, $val]) {
            if ($code === 9 && $val === '$INSUNITS' && isset($pairs[$k + 1])) {
                $units = (int) $pairs[$k + 1][1];
                break;
            }
        }

        $polygons = [];
        $texts = [];
        $n = count($pairs);
        for ($i = 0; $i < $n; $i++) {
            [$code, $val] = $pairs[$i];
            if ($code !== 0) {
                continue;
            }
            if ($val === 'LWPOLYLINE') {
                $pts = [];
                $closed = false;
                $x = null;
                for ($j = $i + 1; $j < $n && $pairs[$j][0] !== 0; $j++) {
                    [$c, $v] = $pairs[$j];
                    if ($c === 70) {
                        $closed = ((int) $v & 1) === 1;
                    } elseif ($c === 10) {
                        $x = (float) $v;
                    } elseif ($c === 20 && $x !== null) {
                        $pts[] = [$x, (float) $v];
                        $x = null;
                    }
                }
                if (! $closed && count($pts) > 3 && self::samePoint($pts[0], end($pts))) {
                    $closed = true;
                }
                if ($closed && count($pts) >= 3) {
                    $polygons[] = $pts;
                }
                $i = $j - 1;
            } elseif ($val === 'POLYLINE') {
                $closed = false;
                $pts = [];
                for ($j = $i + 1; $j < $n && ! ($pairs[$j][0] === 0 && $pairs[$j][1] === 'SEQEND'); $j++) {
                    [$c, $v] = $pairs[$j];
                    if ($c === 70 && empty($pts)) {
                        $closed = ((int) $v & 1) === 1;
                    }
                    if ($c === 0 && $v === 'VERTEX') {
                        $vx = null;
                        $vy = null;
                        for ($k = $j + 1; $k < $n && $pairs[$k][0] !== 0; $k++) {
                            if ($pairs[$k][0] === 10) {
                                $vx = (float) $pairs[$k][1];
                            } elseif ($pairs[$k][0] === 20) {
                                $vy = (float) $pairs[$k][1];
                            }
                        }
                        if ($vx !== null && $vy !== null) {
                            $pts[] = [$vx, $vy];
                        }
                        $j = $k - 1;
                    }
                }
                if ($closed && count($pts) >= 3) {
                    $polygons[] = $pts;
                }
                $i = $j;
            } elseif ($val === 'TEXT' || $val === 'MTEXT') {
                $tx = null;
                $ty = null;
                $str = '';
                for ($j = $i + 1; $j < $n && $pairs[$j][0] !== 0; $j++) {
                    [$c, $v] = $pairs[$j];
                    if ($c === 10) {
                        $tx = (float) $v;
                    } elseif ($c === 20) {
                        $ty = (float) $v;
                    } elseif ($c === 1 || $c === 3) {
                        $str .= $v;
                    }
                }
                $str = trim(preg_replace('/\\\\[A-Za-z][^;]*;|[{}]/', '', $str));
                if ($tx !== null && $str !== '') {
                    $texts[] = ['x' => $tx, 'y' => $ty, 'text' => $str];
                }
                $i = $j - 1;
            }
        }

        return ['units' => $units, 'polygons' => $polygons, 'texts' => $texts];
    }

    /**
     * Turn the drawing into plot rows: area (sq ft), length/width (sq ft units), plot number label,
     * and a normalised outline (0–100 %) for the interactive map.
     */
    public static function plots(string $content): array
    {
        $d = self::read($content);
        $areaFactor = self::SQFT[$d['units']] ?? 1.0;
        $lenFactor = self::LINEAR_FT[$d['units']] ?? 1.0;
        $candidates = [];
        foreach ($d['polygons'] as $pts) {
            $area = abs(self::area($pts)) * $areaFactor;
            if ($area < 100 || $area > 200000) {
                continue; // too small to be a plot, or the whole site boundary
            }
            $label = null;
            foreach ($d['texts'] as $t) {
                if (self::inside([$t['x'], $t['y']], $pts) && preg_match('/^(?:plot\s*(?:no\.?)?\s*)?([A-Za-z]?\d{1,4}[A-Za-z]?)$/i', $t['text'], $m)) {
                    $label = strtoupper($m[1]);
                    break;
                }
            }
            $edges = [];
            for ($k = 0, $c = count($pts); $k < $c; $k++) {
                $a = $pts[$k];
                $b = $pts[($k + 1) % $c];
                $edges[] = hypot($b[0] - $a[0], $b[1] - $a[1]) * $lenFactor;
            }
            rsort($edges);
            $candidates[] = ['pts' => $pts, 'area' => round($area, 2), 'label' => $label, 'length' => round($edges[0] ?? 0, 2), 'width' => round($edges[min(2, count($edges) - 1)] ?? 0, 2)];
        }
        // Prefer labelled outlines when the drawing has labels.
        if (collect($candidates)->whereNotNull('label')->count() > 0) {
            $candidates = array_values(array_filter($candidates, fn ($c) => $c['label'] !== null));
        }
        if (! $candidates) {
            return [];
        }
        $all = array_merge(...array_column($candidates, 'pts'));
        $minX = min(array_column($all, 0));
        $maxX = max(array_column($all, 0));
        $minY = min(array_column($all, 1));
        $maxY = max(array_column($all, 1));
        $w = max(1e-9, $maxX - $minX);
        $h = max(1e-9, $maxY - $minY);
        $rows = [];
        foreach ($candidates as $i => $c) {
            $rows[] = [
                'plot_no' => $c['label'] ?? (string) ($i + 1),
                'size_sqft' => $c['area'],
                'length_ft' => $c['length'],
                'width_ft' => $c['width'],
                'map_polygon' => array_map(fn ($p) => [round(($p[0] - $minX) / $w * 100, 3), round(($maxY - $p[1]) / $h * 100, 3)], $c['pts']),
            ];
        }
        usort($rows, fn ($a, $b) => strnatcmp($a['plot_no'], $b['plot_no']));

        return $rows;
    }

    public static function area(array $pts): float
    {
        $s = 0.0;
        for ($i = 0, $n = count($pts); $i < $n; $i++) {
            $a = $pts[$i];
            $b = $pts[($i + 1) % $n];
            $s += $a[0] * $b[1] - $b[0] * $a[1];
        }

        return $s / 2;
    }

    public static function inside(array $p, array $poly): bool
    {
        $in = false;
        for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
            if ((($poly[$i][1] > $p[1]) !== ($poly[$j][1] > $p[1]))
                && ($p[0] < ($poly[$j][0] - $poly[$i][0]) * ($p[1] - $poly[$i][1]) / (($poly[$j][1] - $poly[$i][1]) ?: 1e-12) + $poly[$i][0])) {
                $in = ! $in;
            }
        }

        return $in;
    }

    private static function samePoint(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) < 1e-6 && abs($a[1] - $b[1]) < 1e-6;
    }
}
