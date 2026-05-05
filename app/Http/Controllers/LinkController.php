<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortenLinkRequest;
use App\Http\Requests\UpdateShortenLinkRequest;
use App\Models\Link;
use App\Services\LinkCacheService;
use App\Services\ShortCodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LinkController extends Controller
{
    public function __construct(public ShortCodeGeneratorService $shortCodeGeneratorService, public LinkCacheService $linkCacheService) {}

    public function store(StoreShortenLinkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $link = Link::create([
            'short_code' => $this->shortCodeGeneratorService->generate(),
            'original_url' => $validated['url'],
            'user_id' => $request->user()?->id,
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'short_url' => url($link->short_code),
        ]);
    }

    public function update(UpdateShortenLinkRequest $request, string $code): JsonResponse
    {
        $link = Link::where('short_code', $code)->firstOrFail();

        Gate::authorize('update', $link);

        $validated = $request->validated();

        if (isset($validated['url'])) {
            $validated['original_url'] = $validated['url'];
            unset($validated['url']);
        }

        $link->update($validated);

        return response()->json([
            'short_code' => $link->short_code,
            'original_url' => $link->original_url,
            'expires_at' => $link->expires_at,
        ]);
    }

    public function destroy(string $code): JsonResponse
    {
        $link = Link::where('short_code', $code)->firstOrFail();

        Gate::authorize('delete', $link);

        $link->delete();

        return response()->json([], 204);
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
