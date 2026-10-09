<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Services\SeoService;

class SeoController extends Controller
{
    public function sitemap()
    {
        return response(SeoService::sitemap(), 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots()
    {
        return response(SeoService::robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
