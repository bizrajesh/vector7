<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\Layout;
use App\Models\Plot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * CSV bulk import of plots (section 6.1). Every row is validated; nothing is
 * written unless the caller commits the valid rows.
 */
class PlotImporter
{
    public const COLUMNS = [
        'plot_no', 'survey_no', 'size_sqft', 'rate_sqft', 'plot_cost', 'status', 'facing',
        'dimensions', 'boundary_north', 'boundary_south', 'boundary_east', 'boundary_west',
    ];

    public const MAX_ROWS = 2000;

    public function __construct(private readonly PlanLimits $limits) {}

    /** @return array{rows: array<int, array>, errors: array<int, string>} */
    public function parse(UploadedFile $file, Layout $layout): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);

        if (array_diff(['plot_no', 'size_sqft', 'rate_sqft'], $header)) {
            fclose($handle);

            return ['rows' => [], 'errors' => [0 => 'Header must include plot_no, size_sqft and rate_sqft. Download the template.']];
        }

        $existing = Plot::query()->where('layout_id', $layout->id)->pluck('plot_no')->map(fn ($p) => strtolower($p))->all();
        $seen = [];
        $rows = [];
        $errors = [];
        $line = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            if ($line - 1 > self::MAX_ROWS) {
                $errors[$line] = 'Too many rows; import at most '.self::MAX_ROWS.' at a time.';
                break;
            }
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $column) {
                if (in_array($column, self::COLUMNS, true)) {
                    $row[$column] = isset($raw[$i]) ? trim((string) $raw[$i]) : null;
                }
            }
            $row['status'] = strtolower($row['status'] ?? '') ?: 'available';
            $row['facing'] = strtolower(str_replace([' ', '-'], '_', $row['facing'] ?? '')) ?: null;

            $validator = Validator::make($row, [
                'plot_no' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/]+$/'],
                'survey_no' => ['nullable', 'string', 'max:40'],
                'size_sqft' => ['required', 'numeric', 'min:1', 'max:1000000'],
                'rate_sqft' => ['required', 'numeric', 'min:0', 'max:10000000'],
                'plot_cost' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', Rule::in([PlotStatus::Available->value, PlotStatus::Reserved->value])],
                'facing' => ['nullable', Rule::in(['north', 'south', 'east', 'west', 'north_east', 'north_west', 'south_east', 'south_west'])],
                'dimensions' => ['nullable', 'string', 'max:40'],
                'boundary_north' => ['nullable', 'string', 'max:120'],
                'boundary_south' => ['nullable', 'string', 'max:120'],
                'boundary_east' => ['nullable', 'string', 'max:120'],
                'boundary_west' => ['nullable', 'string', 'max:120'],
            ]);

            if ($validator->fails()) {
                $errors[$line] = $validator->errors()->first();

                continue;
            }

            $key = strtolower($row['plot_no']);
            if (in_array($key, $existing, true) || isset($seen[$key])) {
                $errors[$line] = "Plot {$row['plot_no']} already exists.";

                continue;
            }
            $seen[$key] = true;

            $computed = round((float) $row['size_sqft'] * (float) $row['rate_sqft'], 2);
            $row['cost'] = $row['plot_cost'] !== null && $row['plot_cost'] !== '' ? round((float) $row['plot_cost'], 2) : $computed;
            $row['cost_mismatch'] = abs($row['cost'] - $computed) > 1;
            unset($row['plot_cost']);
            $rows[] = $row;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    public function commit(Layout $layout, array $rows): int
    {
        $this->limits->ensureCanAddPlots(count($rows));

        return DB::transaction(function () use ($layout, $rows) {
            foreach ($rows as $row) {
                $status = $row['status'];
                unset($row['status'], $row['cost_mismatch']);
                $plot = new Plot($row);
                $plot->layout_id = $layout->id;
                $plot->status = PlotStatus::from($status);
                $plot->save();
            }

            return count($rows);
        });
    }
}
