<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;

/** Web app manifest for the tenant portal (spec 8.14), named after the organization. */
class ManifestController extends Controller
{
    public function __invoke(CurrentOrganization $current): JsonResponse
    {
        $organization = $current->get();
        $name = $organization->name ?? (string) config('rentora.name');
        $theme = $organization->primary_color ?? (string) config('rentora.defaults.primary_color');

        return response()->json([
            'name' => $name,
            'short_name' => mb_strlen($name) > 12 ? (string) config('rentora.name') : $name,
            'description' => (string) config('rentora.tagline'),
            'id' => '/portal',
            'start_url' => '/portal',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $theme,
            'background_color' => '#FFFFFF',
            'version' => (string) config('rentora.version'),
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
