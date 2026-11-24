<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Digital Asset Links for the Android app (FINISH_BUILD 8.2). With the right
 * package name and signing fingerprint here, the Trusted Web Activity opens
 * full screen without a browser bar. Empty until both are configured.
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
