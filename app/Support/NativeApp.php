<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The Android app (a Capacitor WebView) appends "RentoraApp" to its user
 * agent. Inside it, browser-only features such as the "Install app" card
 * are hidden.
 */
final class NativeApp
{
    public const USER_AGENT_MARKER = 'RentoraApp';

    public static function is(?Request $request = null): bool
    {
        $request ??= request();

        return str_contains((string) $request->userAgent(), self::USER_AGENT_MARKER);
    }
}
