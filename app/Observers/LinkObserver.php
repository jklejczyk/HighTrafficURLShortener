<?php

namespace App\Observers;

use App\Models\Link;
use App\Services\LinkCacheService;

class LinkObserver
{
    public function __construct(public LinkCacheService $linkCacheService) {}

    public function created(Link $link): void
    {
        $this->linkCacheService->store($link);
    }

    public function updated(Link $link): void
    {
        $this->linkCacheService->forget($link->short_code);
    }

    public function deleted(Link $link): void
    {
        $this->linkCacheService->forget($link->short_code);
    }
}
