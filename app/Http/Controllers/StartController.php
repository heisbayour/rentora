<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entry route for the Android app and shortcuts: owners, managers and
 * caretakers go to the console, tenants to the portal, guests to login.
 */
class StartController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return redirect()->to(LoginResponse::homeFor($user));
    }
}
