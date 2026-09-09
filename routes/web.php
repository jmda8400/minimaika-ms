<?php

use App\Http\Controllers\Bot\BotSettingsController;
use App\Http\Controllers\Bot\BotSettingsLoginController;
use App\Http\Controllers\WhatsApp\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('bot.settings.auth');

Route::redirect('bot/settings', '/whatsapp/settings');

Route::get('whatsapp/login', [BotSettingsLoginController::class, 'create'])->name('bot.settings.login');
Route::post('whatsapp/login', [BotSettingsLoginController::class, 'store'])->name('bot.settings.login.store');
Route::post('whatsapp/logout', [BotSettingsLoginController::class, 'destroy'])->name('bot.settings.logout');

Route::prefix('whatsapp')->group(function (): void {
    Route::middleware('bot.settings.auth')->group(function (): void {
        Route::get('settings', [BotSettingsController::class, 'edit'])->name('bot.settings.edit');
        Route::put('settings', [BotSettingsController::class, 'update'])->name('bot.settings.update');
        Route::post('settings/forget-session', [BotSettingsController::class, 'forgetSession'])->name('bot.settings.forget-session');
        Route::post('settings/test-notification', [BotSettingsController::class, 'testNotification'])->name('bot.settings.test-notification');
    });
    Route::get('status', [WhatsAppWebhookController::class, 'status'])->name('whatsapp.status');
    Route::redirect('qr', '/whatsapp/settings');
    Route::post('webhook', [WhatsAppWebhookController::class, 'webhook'])->name('whatsapp.webhook');
});
