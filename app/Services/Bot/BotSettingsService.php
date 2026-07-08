<?php

namespace App\Services\Bot;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BotSettingsService
{
    private const PATH = 'bot-settings.json';

    /**
     * @return array{response_mode:string,default_message:string,respond_to_groups:bool}
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
        ];
    }

    /**
     * @param array{response_mode:string,default_message:string,respond_to_groups:bool} $settings
     */
    public function save(array $settings): void
    {
        File::ensureDirectoryExists(Storage::path(''));

        Storage::put(self::PATH, json_encode([
            'response_mode' => $settings['response_mode'] === 'default' ? 'default' : 'bot',
            'default_message' => trim($settings['default_message']),
            'respond_to_groups' => $settings['respond_to_groups'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{response_mode:string,default_message:string,respond_to_groups:bool}
     */
    private function defaults(): array
    {
        return [
            'response_mode' => 'bot',
            'default_message' => 'Gracias por escribirnos. Recibimos tu mensaje y te responderemos a la brevedad.',
            'respond_to_groups' => false,
        ];
    }
}
