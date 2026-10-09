<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class PlanLimitException extends Exception
{
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'upgrade' => true], 402);
        }

        return back()->withInput()->with('error', $this->getMessage())->with('upgrade', true);
    }
}
