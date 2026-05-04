<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortenLinkRequest;
use App\Models\Link;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\JsonResponse;

class LinkController extends Controller
{
    public function __construct(public ShortCodeGenerator $shortCodeGenerator)
    {
    }

    public function store(StoreShortenLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $link = Link::create([
            'short_code' => $this->shortCodeGenerator->generate(),
            'original_url' => $validated['url'],
            'user_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'short_url' => url($link->short_code),
        ]);
    }

    public function stats(string $code): JsonResponse
    {
        $link = Link::where('short_code', $code)->firstOrFail();

        return response()->json([
            'short_code' => $link->short_code,
            'original_url' => $link->original_url,
            'click_count' => $link->click_count,
            'created_at' => $link->created_at,
            'expires_at' => $link->expires_at,
        ]);
    }
}
