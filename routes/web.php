<?php

use App\Http\Controllers\WhatsApp\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('whatsapp')->group(function (): void {
    Route::get('status', [WhatsAppWebhookController::class, 'status'])->name('whatsapp.status');
    Route::get('qr', [WhatsAppWebhookController::class, 'qr'])->name('whatsapp.qr');
    Route::post('webhook', [WhatsAppWebhookController::class, 'webhook'])->name('whatsapp.webhook');
});
