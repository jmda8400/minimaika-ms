<?php

namespace App\Services\Bot;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BotSettingsService
{
    private const PATH = 'bot-settings.json';

    /**
     * @return array{response_mode:string,default_message:string,respond_to_groups:bool,use_generative_ai:bool,notify_on_fallback:bool,fallback_alert_phone:string}
     */
    public function get(): array
    {
        $settings = $this->defaults();

        if (Storage::exists(self::PATH)) {
            $storedSettings = json_decode(Storage::get(self::PATH) ?: '[]', true);

            if (is_array($storedSettings)) {
                $settings = array_merge($settings, Arr::only($storedSettings, array_keys($settings)));
            }
        }

        return [
            'response_mode' => $settings['response_mode'] === 'default' ? 'default' : 'bot',
            'default_message' => trim((string) $settings['default_message']),
            'respond_to_groups' => (bool) $settings['respond_to_groups'],
            'use_generative_ai' => (bool) $settings['use_generative_ai'],
            'notify_on_fallback' => (bool) $settings['notify_on_fallback'],
            'fallback_alert_phone' => trim((string) $settings['fallback_alert_phone']),
        ];
    }

    /**
     * @param array{response_mode:string,default_message:string,respond_to_groups:bool,use_generative_ai:bool,notify_on_fallback:bool,fallback_alert_phone:string} $settings
     */
    public function save(array $settings): void
    {
        File::ensureDirectoryExists(Storage::path(''));

        Storage::put(self::PATH, json_encode([
            'response_mode' => $settings['response_mode'] === 'default' ? 'default' : 'bot',
            'default_message' => trim($settings['default_message']),
            'respond_to_groups' => $settings['respond_to_groups'],
            'use_generative_ai' => $settings['use_generative_ai'],
            'notify_on_fallback' => $settings['notify_on_fallback'],
            'fallback_alert_phone' => trim($settings['fallback_alert_phone']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{response_mode:string,default_message:string,respond_to_groups:bool,use_generative_ai:bool,notify_on_fallback:bool,fallback_alert_phone:string}
     */
    private function defaults(): array
    {
        return [
            'response_mode' => 'bot',
            'default_message' => 'Gracias por escribirnos. Recibimos tu mensaje y te responderemos a la brevedad.',
            'respond_to_groups' => false,
            'use_generative_ai' => true,
            'notify_on_fallback' => true,
            'fallback_alert_phone' => '+54 2944360712',
        ];
    }
}
