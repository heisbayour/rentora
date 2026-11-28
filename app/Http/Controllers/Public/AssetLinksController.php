<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Digital Asset Links for the Android app (FINISH_BUILD 8.2). The app has been
 * a Capacitor WebView since 1.0.2 and no longer depends on this file; it stays
 * for Google Play and app links. Empty until both values are configured.
 */
class AssetLinksController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $package = (string) config('rentora.android.package');
        $fingerprints = (array) config('rentora.android.fingerprints');

        $statements = $package === '' || $fingerprints === [] ? [] : [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => array_values($fingerprints),
            ],
        ]];

        return response()->json($statements, 200, ['Content-Type' => 'application/json'], JSON_UNESCAPED_SLASHES);
    }
}
