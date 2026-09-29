<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function done(string $message, ?string $route = null, mixed $params = []): RedirectResponse
    {
        return ($route ? redirect()->route($route, $params) : back())->with('status', $message);
    }
}
