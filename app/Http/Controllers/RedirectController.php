<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    public function redirect(string $code): RedirectResponse
    {
        $link = Link::where('short_code', $code)->firstOrFail();

        if ($link->expires_at && $link->expires_at->isPast()) {
            abort(410);
        }

        $link->increment('click_count');

        return redirect($link->original_url, 301);
    }
}
