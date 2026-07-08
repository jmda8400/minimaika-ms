<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Services\Bot\BotSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotSettingsController extends Controller
{
    public function __construct(private readonly BotSettingsService $settingsService)
    {
    }

    public function edit(): View
    {
        return view('bot.settings', [
            'settings' => $this->settingsService->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'response_mode' => ['required', 'in:bot,default'],
            'default_message' => ['required', 'string', 'max:1000'],
            'respond_to_groups' => ['nullable', 'boolean'],
        ]);

        $this->settingsService->save([
            'response_mode' => $validated['response_mode'],
            'default_message' => $validated['default_message'],
            'respond_to_groups' => $request->boolean('respond_to_groups'),
        ]);

        return redirect()->route('bot.settings.edit')->with('status', 'Configuración guardada.');
    }
}
