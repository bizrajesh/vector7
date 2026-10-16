<?php

namespace App\Http\Controllers;

use App\Services\HelpCenter;
use Illuminate\Http\Request;

/** Built-in help pages (resources/help/*.md) for every workspace screen; also the workspace bot's knowledge base. */
class HelpController extends Controller
{
    public function index(Request $request)
    {
        return view('help.index', ['articles' => HelpCenter::all($request->user()), 'q' => (string) $request->query('q'),
            'results' => $request->query('q') ? HelpCenter::search((string) $request->query('q'), $request->user(), 10) : null]);
    }

    public function show(Request $request, string $slug)
    {
        $article = HelpCenter::find($slug);
        abort_unless($article, 404);

        return view('help.show', ['article' => $article, 'html' => HelpCenter::html($article), 'articles' => HelpCenter::all($request->user())]);
    }
}
