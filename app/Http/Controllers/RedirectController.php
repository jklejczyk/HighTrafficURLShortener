<?php

namespace App\Http\Controllers;

use App\Services\LinkCacheService;
use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    public function __construct(public LinkCacheService $linkCacheService) {}

    public function redirect(string $code): RedirectResponse
    {
        $url = $this->linkCacheService->get($code) ?? abort(404);

        // TODO: move incrementation to redis
        //        $link->increment('click_count');

        return redirect($url, 302);
    }
}
