<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel import/export with OpenSpout. Downloads are streamed, never stored on the server. */
class Excel
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows, ?string $title = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $title) {
            $w = new Writer;
            $w->openToFile('php://output');
            if ($title) {
                $w->addRow(Row::fromValuesWithStyle([$title], (new Style)->withFontBold(true)->withFontSize(13)));
                $w->addRow(Row::fromValues(['Generated '.now()->format('d-m-Y H:i').' · vector7']));
                $w->addRow(Row::fromValues(['']));
            }
            $w->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0B1B33')));
            foreach ($rows as $r) {
                $w->addRow(Row::fromValues(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d-m-Y') : (is_bool($v) ? ($v ? 'Yes' : 'No') : $v), array_values($r))));
            }
            $w->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
    }

    /**
     * Read the first sheet of a CSV/XLSX file as associative rows keyed by (lower-cased, trimmed) header.
     *
     * @return array{headers: array<int,string>, rows: array<int, array<string, mixed>>}
     */
    public static function read(string $path, string $ext): array
    {
        $reader = strtolower($ext) === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);
        $headers = null;
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $n = 1;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                if ($headers === null) {
                    $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);

                    continue;
                }
                $n++;
                if (count(array_filter($cells, fn ($c) => $c !== null && $c !== '')) === 0) {
                    continue;
                }
                $assoc = ['_row' => $n];
                foreach ($headers as $i => $h) {
                    $v = $cells[$i] ?? null;
                    $assoc[$h] = $v instanceof \DateTimeInterface ? $v->format('d-m-Y') : (is_string($v) ? trim($v) : $v);
                }
                $rows[] = $assoc;
            }
            break;
        }
        $reader->close();

        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    /** Parse pasted CSV text. */
    public static function parseCsvText(string $text): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'v7csv');
        file_put_contents($tmp, trim($text)."\n");
        try {
            return self::read($tmp, 'csv');
        } finally {
            @unlink($tmp);
        }
    }
}
