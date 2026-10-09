<?php

namespace App\Services;

use App\Models\Plot;
use App\Models\Project;
use App\Models\StorageFile;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use smalot\PdfParser\Parser as PdfParser;

/**
 * Plot loading for project launch: CSV text, CSV/Excel upload, or AI extraction (DXF / PDF / photo).
 * Fixed header names; row-by-row validation; nothing is imported until every error is fixed.
 * Re-importing matches on plot_no and updates those plots.
 */
class PlotImporter
{
    public const COLUMNS = [
        'plot_no' => 'Yes', 'patta_number' => 'Yes', 'size_sqft' => 'Yes', 'length_ft' => 'No', 'width_ft' => 'No', 'facing' => 'Yes',
        'east_boundary' => 'No', 'west_boundary' => 'No', 'north_boundary' => 'No', 'south_boundary' => 'No', 'road_width_ft' => 'No',
        'corner_plot' => 'No', 'rate_per_sqft' => 'Yes', 'offer' => 'No', 'offer_rate_per_sqft' => 'No', 'offer_valid_till' => 'No', 'status' => 'Yes',
    ];

    public const SAMPLE = [
        ['1', '1234', '1200', '40', '30', 'East', '30 ft road', 'Plot 2', 'Plot 10', 'Plot 12', '30', 'Y', '1250', 'Diwali offer – ₹100/sq ft off', '1150', '', 'Available'],
        ['2', '1234/2', '1500', '50', '30', 'North', 'Plot 1', 'Plot 3', '23 ft road', 'Plot 13', '23', 'N', '1200', '', '', '', 'Available'],
        ['3', '1235/1', '1800', '60', '30', 'South-East', 'Plot 2', 'Plot 4', 'Plot 14', '30 ft road', '30', 'N', '1180', '', '', '', 'Blocked'],
    ];

    /** @return array{rows: array<int, array>, errors: array<int, string>, header_error: ?string} */
    public static function fromTable(array $table): array
    {
        $missing = array_diff(array_keys(array_filter(self::COLUMNS, fn ($r) => $r === 'Yes')), $table['headers']);
        if ($missing) {
            return ['rows' => [], 'errors' => [], 'header_error' => 'The header row must use these exact names. Missing: '.implode(', ', $missing).'. Download the sample file.'];
        }
        $rows = [];
        foreach ($table['rows'] as $r) {
            $row = ['_row' => $r['_row']];
            foreach (array_keys(self::COLUMNS) as $c) {
                $v = $r[$c] ?? null;
                $row[$c] = is_float($v) && floor($v) == $v ? (string) (int) $v : (is_null($v) ? '' : (string) $v);
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => [], 'header_error' => null];
    }

    public static function parseDate(?string $v): ?Carbon
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat('!'.$fmt, $v);
                if ($d && $d->format($fmt) === $v) {
                    return $d;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private static function num(?string $v): ?float
    {
        $v = str_replace([',', '₹', ' '], '', trim((string) $v));

        return $v === '' ? null : (is_numeric($v) ? (float) $v : NAN);
    }

    /**
     * Validate rows. Returns [errors (row => [messages]), warnings, normalised rows].
     */
    public static function validate(Project $project, array $rows, array $skipStatusCheck = []): array
    {
        $errors = [];
        $seen = [];
        $existing = Plot::where('project_id', $project->id)->get()->keyBy(fn ($p) => strtoupper($p->plot_no));
        $facings = array_map('strtolower', Plot::FACINGS);
        $clean = [];
        foreach ($rows as $i => $r) {
            $line = $r['_row'] ?? ($i + 2);
            $e = [];
            $no = strtoupper(trim((string) ($r['plot_no'] ?? '')));
            if ($no === '') {
                $e[] = 'plot_no is required';
            } elseif (! preg_match('/^[A-Z0-9\-]{1,20}$/', $no)) {
                $e[] = 'plot_no may contain only letters, digits and "-"';
            } elseif (isset($seen[$no])) {
                $e[] = "duplicate plot_no $no (also on line {$seen[$no]})";
            }
            $seen[$no] = $line;
            $patta = trim((string) ($r['patta_number'] ?? ''));
            if ($patta === '') {
                $e[] = 'patta_number is required';
            } elseif (mb_strlen($patta) > 40) {
                $e[] = 'patta_number is too long';
            }
            $size = self::num($r['size_sqft'] ?? '');
            if ($size === null || is_nan($size) || $size <= 0) {
                $e[] = 'size_sqft must be a number greater than 0';
            }
            foreach (['length_ft', 'width_ft', 'road_width_ft'] as $c) {
                $v = self::num($r[$c] ?? '');
                if ($v !== null && (is_nan($v) || $v < 0)) {
                    $e[] = "$c must be a number";
                }
            }
            $facing = trim((string) ($r['facing'] ?? ''));
            $fi = array_search(strtolower($facing), $facings, true);
            if ($fi === false) {
                $e[] = 'facing must be one of '.implode(' / ', Plot::FACINGS);
            }
            $corner = strtoupper(trim((string) ($r['corner_plot'] ?? '')));
            if (! in_array($corner, ['', 'Y', 'N', 'YES', 'NO'], true)) {
                $e[] = 'corner_plot must be Y or N';
            }
            $rate = self::num($r['rate_per_sqft'] ?? '');
            if ($rate === null || is_nan($rate) || $rate <= 0) {
                $e[] = 'rate_per_sqft must be a number greater than 0';
            }
            $offerText = trim((string) ($r['offer'] ?? ''));
            $offerRate = self::num($r['offer_rate_per_sqft'] ?? '');
            $tillRaw = trim((string) ($r['offer_valid_till'] ?? ''));
            $till = self::parseDate($tillRaw);
            if ($offerRate !== null && (is_nan($offerRate) || $offerRate <= 0)) {
                $e[] = 'offer_rate_per_sqft must be a number greater than 0';
            } elseif ($offerRate !== null && $rate && ! is_nan($rate) && $offerRate >= $rate) {
                $e[] = 'offer price must be lower than the actual price (offer_rate_per_sqft < rate_per_sqft)';
            }
            if (($offerText !== '' || $offerRate !== null) && $tillRaw === '') {
                $e[] = 'offer_valid_till (DD-MM-YYYY) is required when an offer is given';
            } elseif ($tillRaw !== '' && ! $till) {
                $e[] = 'offer_valid_till must be a date in DD-MM-YYYY format';
            } elseif ($till && $till->lt(today())) {
                $e[] = 'offer_valid_till is in the past';
            }
            if ($offerText !== '' && $offerRate === null) {
                $e[] = 'offer_rate_per_sqft is required when an offer is given';
            }
            $status = strtolower(trim((string) ($r['status'] ?? '')));
            if (! in_array($status, ['available', 'blocked'], true)) {
                $e[] = 'status must be Available or Blocked (other statuses are set by the system)';
            }
            $current = $existing[$no] ?? null;
            if ($current && ! in_array($current->status, ['available', 'blocked'], true) && $status !== '' && ! in_array($no, $skipStatusCheck, true)) {
                $e[] = "plot $no is already {$current->statusLabel()} — it cannot be changed by import";
            }
            if ($e) {
                $errors[$line] = $e;

                continue;
            }
            $clean[] = [
                'plot_no' => $no,
                'patta_number' => $patta,
                'size_sqft' => $size,
                'length_ft' => self::num($r['length_ft'] ?? ''),
                'width_ft' => self::num($r['width_ft'] ?? ''),
                'facing' => Plot::FACINGS[$fi],
                'east_boundary' => self::text($r['east_boundary'] ?? ''),
                'west_boundary' => self::text($r['west_boundary'] ?? ''),
                'north_boundary' => self::text($r['north_boundary'] ?? ''),
                'south_boundary' => self::text($r['south_boundary'] ?? ''),
                'road_width_ft' => self::num($r['road_width_ft'] ?? ''),
                'is_corner' => in_array($corner, ['Y', 'YES'], true),
                'rate_per_sqft' => $rate,
                'offer_text' => $offerText ?: null,
                'offer_rate_per_sqft' => $offerRate,
                'offer_valid_till' => $till?->toDateString(),
                'offer_active' => $offerRate !== null && $till !== null,
                'status' => $status,
                'map_polygon' => $r['map_polygon'] ?? null,
            ];
        }
        $warnings = [];
        $total = array_sum(array_column($clean, 'size_sqft'));
        $estimate = $project->estimate;
        $sellable = $estimate && $estimate->sellable_sqft > 0 ? (float) $estimate->sellable_sqft : $project->totalSqft() * (float) ($estimate->sellable_pct ?? 55) / 100;
        if ($sellable > 0 && $total > 0 && abs($total - $sellable) / $sellable > 0.05) {
            $warnings[] = 'Total plot area '.number_format($total).' sq ft differs from the sellable area '.number_format($sellable).' sq ft by '.round(abs($total - $sellable) / $sellable * 100, 1).'% (more than 5%). Check the sizes.';
        }

        return [$errors, $warnings, $clean];
    }

    private static function text(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, 100);
    }

    /** Import validated rows (single transaction). Re-import updates matching plot numbers. */
    public static function import(Project $project, array $clean, int $userId): array
    {
        return DB::transaction(function () use ($project, $clean, $userId) {
            $created = 0;
            $updated = 0;
            $existing = Plot::where('project_id', $project->id)->lockForUpdate()->get()->keyBy(fn ($p) => strtoupper($p->plot_no));
            foreach ($clean as $row) {
                $poly = $row['map_polygon'];
                unset($row['map_polygon']);
                if ($plot = $existing[$row['plot_no']] ?? null) {
                    $status = $row['status'];
                    unset($row['status']);
                    $plot->fill($row + ['updated_by' => $userId]);
                    if ($poly) {
                        $plot->map_polygon = $poly;
                    }
                    $plot->save();
                    if (in_array($plot->status, ['available', 'blocked'], true)) {
                        $plot->moveTo($status, 'Re-imported');
                    }
                    $updated++;
                } else {
                    Plot::create($row + ['tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'map_polygon' => $poly, 'created_by' => $userId]);
                    $created++;
                }
            }
            if ($project->estimate) {
                ProjectService::recalcEstimate($project->estimate()->with('lines')->first(), $project);
            }
            $sum = (float) Plot::where('project_id', $project->id)->sum('size_sqft');
            if ($project->totalSqft() > 0) {
                $project->update(['sellable_sqft' => $sum, 'sellable_pct' => round(min(100, $sum / $project->totalSqft() * 100), 2)]);
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /** AI / parser extraction from DXF, PDF or a photo of the approved layout. Returns editable rows. */
    public static function extract(StorageFile $file, Project $project): array
    {
        $ext = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        $bytes = FileStore::contents($file);
        $tenant = $project->tenantRel;
        $defaults = ['patta_number' => '', 'facing' => '', 'east_boundary' => '', 'west_boundary' => '', 'north_boundary' => '', 'south_boundary' => '',
            'road_width_ft' => '', 'corner_plot' => 'N', 'rate_per_sqft' => '', 'offer' => '', 'offer_rate_per_sqft' => '', 'offer_valid_till' => '', 'status' => 'Available'];
        $mrp = $project->estimate?->mrp_per_sqft;
        if ($mrp > 0) {
            $defaults['rate_per_sqft'] = (string) round((float) $mrp);
        }

        if ($ext === 'dxf') {
            $rows = DxfParser::plots($bytes);

            return array_map(fn ($r) => array_merge($defaults, [
                'plot_no' => $r['plot_no'], 'size_sqft' => (string) $r['size_sqft'], 'length_ft' => (string) $r['length_ft'], 'width_ft' => (string) $r['width_ft'], 'map_polygon' => $r['map_polygon'],
            ]), $rows);
        }

        $instructions = 'You read approved residential plot layout drawings from Tamil Nadu, India. Return ONLY a JSON array, one object per plot, '
            .'with keys: plot_no (string), size_sqft (number, convert from sq m or cents if needed: 1 sq m = 10.7639 sq ft, 1 cent = 435.6 sq ft), '
            .'length_ft (number or null), width_ft (number or null), facing (East/West/North/South/North-East/North-West/South-East/South-West or null), '
            .'east_boundary, west_boundary, north_boundary, south_boundary (short text such as "30 ft road" or "Plot 12", or null), road_width_ft (number or null), corner_plot ("Y" or "N"). '
            .'Do not invent plots that are not in the drawing.';

        if ($ext === 'pdf') {
            $text = '';
            try {
                $text = (new PdfParser)->parseContent($bytes)->getText();
            } catch (\Throwable) {
                $text = '';
            }
            $text = trim(preg_replace('/[ \t]+/', ' ', $text));
            if ($text === '') {
                throw new \RuntimeException('No text could be read from this PDF (it may be a scanned image). Upload a photo/PNG of the layout instead so it can be read visually.');
            }
            $reply = ClaudeClient::ask('plot_extraction_pdf', $instructions."\n\nLayout text:\n".mb_substr($text, 0, 60000), null, [], 8000, $tenant);
        } else {
            $img = self::cleanImage($bytes);
            $reply = ClaudeClient::ask('plot_extraction_image', $instructions, null, [['data' => base64_encode($img), 'media_type' => 'image/jpeg']], 8000, $tenant);
        }
        $data = ClaudeClient::json($reply);
        if (! is_array($data)) {
            throw new \RuntimeException('The AI reply could not be read as a plot list. Try a clearer file.');
        }
        $rows = [];
        foreach ($data as $p) {
            if (! is_array($p) || empty($p['plot_no'])) {
                continue;
            }
            $rows[] = array_merge($defaults, [
                'plot_no' => (string) $p['plot_no'],
                'size_sqft' => isset($p['size_sqft']) ? (string) round((float) $p['size_sqft'], 2) : '',
                'length_ft' => isset($p['length_ft']) ? (string) $p['length_ft'] : '',
                'width_ft' => isset($p['width_ft']) ? (string) $p['width_ft'] : '',
                'facing' => (string) ($p['facing'] ?? ''),
                'east_boundary' => (string) ($p['east_boundary'] ?? ''),
                'west_boundary' => (string) ($p['west_boundary'] ?? ''),
                'north_boundary' => (string) ($p['north_boundary'] ?? ''),
                'south_boundary' => (string) ($p['south_boundary'] ?? ''),
                'road_width_ft' => isset($p['road_width_ft']) ? (string) $p['road_width_ft'] : '',
                'corner_plot' => strtoupper((string) ($p['corner_plot'] ?? 'N')) === 'Y' ? 'Y' : 'N',
                'map_polygon' => null,
            ]);
        }

        return $rows;
    }

    /** GD clean-up before vision: orientation-safe resize (max 1600 px), greyscale, contrast, sharpen. */
    public static function cleanImage(string $bytes): string
    {
        $im = @imagecreatefromstring($bytes);
        if (! $im) {
            throw new \RuntimeException('This image could not be opened. Upload a JPG or PNG.');
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1, 1600 / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im);
            $im = $dst;
        }
        imagefilter($im, IMG_FILTER_GRAYSCALE);
        imagefilter($im, IMG_FILTER_CONTRAST, -25);
        imageconvolution($im, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0);
        ob_start();
        imagejpeg($im, null, 88);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    public static function sampleCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_keys(self::COLUMNS), ',', '"', '');
        foreach (self::SAMPLE as $i => $row) {
            if ($i === 0) {
                $row[15] = today()->addDays(30)->format('d-m-Y');
            }
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);
    }

    public static function readUpload(string $path, string $ext): array
    {
        return self::fromTable(Excel::read($path, $ext));
    }
}
