<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBotSettingsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $request->session()->get('bot-settings.authenticated')) {
            return redirect()->guest(route('bot.settings.login'));
        }

        return $next($request);
    }
}
