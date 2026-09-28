<?php

namespace Tests\Unit;

use App\Services\Bot\BotSettingsService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BotSettingsServiceTest extends TestCase
{
    public function test_option_alert_flag_is_saved_and_loaded(): void
    {
        Storage::fake('local');
        $service = app(BotSettingsService::class);
        $settings = $service->get();
        $settings['navigation'][0]['options'][0]['send_alert'] = true;

        $service->save($settings);

        $stored = json_decode(Storage::get('bot-settings.json'), true);
        $this->assertTrue($stored['navigation'][0]['options'][0]['send_alert']);
        $this->assertTrue($service->get()['navigation'][0]['options'][0]['send_alert']);
    }

    public function test_options_without_a_flag_default_to_no_alert(): void
    {
        Storage::fake('local');
        Storage::put('bot-settings.json', json_encode([
            'navigation' => [[
                'id' => 'new_category',
                'title' => 'Nueva categoría',
                'options' => [[
                    'id' => 'new_option',
                    'title' => 'Nueva opción',
                    'answer' => 'Nueva respuesta',
                ]],
            ]],
        ]));

        $this->assertFalse(app(BotSettingsService::class)->get()['navigation'][0]['options'][0]['send_alert']);
    }
}
