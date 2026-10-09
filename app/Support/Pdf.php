<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

/** dompdf wrapper: estimates, receipts, registration packs, reports. */
class Pdf
{
    public static function render(string $view, array $data, string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([public_path(), storage_path('app')]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    public static function download(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return response(self::render($view, $data, 'a4', $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function inline(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return response(self::render($view, $data, 'a4', $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
