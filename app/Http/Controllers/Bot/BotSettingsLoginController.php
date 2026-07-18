<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class BotSettingsLoginController extends Controller
{
    private const SESSION_KEY = 'bot-settings.authenticated';

    public function create(): View|RedirectResponse
    {
        if ((bool) session()->get(self::SESSION_KEY)) {
            return redirect()->route('bot.settings.edit');
        }

        return view('bot.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $key = 'bot-settings-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['username' => 'Demasiados intentos. Probá nuevamente en un minuto.'])->onlyInput('username');
        }

        $username = (string) config('services.bot_settings.username');
        $password = (string) config('services.bot_settings.password');

        if (! hash_equals($username, $credentials['username']) || ! hash_equals($password, $credentials['password'])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['username' => 'Usuario o contraseña incorrectos.'])->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, true);

        return redirect()->intended(route('bot.settings.edit'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('bot.settings.login');
    }
}
