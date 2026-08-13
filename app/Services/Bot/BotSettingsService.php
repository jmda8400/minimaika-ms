<?php

namespace App\Services\Bot;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BotSettingsService
{
    private const PATH = 'bot-settings.json';

    /**
     * @return array<string,mixed>
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
            'bot_texts' => $settings['bot_texts'],
            'navigation' => $settings['navigation'],
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function save(array $settings): void
    {
        File::ensureDirectoryExists(Storage::path(''));

        Storage::put(self::PATH, json_encode([
            'response_mode' => $settings['response_mode'] === 'default' ? 'default' : 'bot',
            'default_message' => trim($settings['default_message']),
            'respond_to_groups' => $settings['respond_to_groups'],
            'bot_texts' => $settings['bot_texts'],
            'navigation' => $settings['navigation'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string,mixed>
     */
    private function defaults(): array
    {
        return [
            'response_mode' => 'bot',
            'default_message' => 'Gracias por escribirnos. Recibimos tu mensaje y te responderemos a la brevedad.',
            'respond_to_groups' => false,
            'bot_texts' => [
                'welcome' => NavigationTreeService::WELCOME,
                'main_menu_title' => 'Por favor selecciona una opción para continuar',
                'menu_button' => 'Ver opciones',
                'select_prompt' => 'Respondé con el número de la opción.',
                'follow_up' => '¿Querés consultar algo más?',
                'back_title' => '🏠 Menú principal',
                'back_description' => 'Volver al inicio',
                'option_description' => 'Seleccionar',
            ],
            'navigation' => $this->defaultNavigation(),
        ];
    }

    /** @return array<int,array{id:string,title:string,options:array<int,array{id:string,title:string,answer:string}>}> */
    private function defaultNavigation(): array
    {
        $nodes = config('navigation.nodes', []);
        $categories = [];

        foreach ($nodes['main']['children'] ?? [] as $categoryId) {
            $category = $nodes[$categoryId];
            $options = [];
            foreach ($category['children'] ?? [] as $optionId) {
                $option = $nodes[$optionId];
                $options[] = ['id' => $optionId, 'title' => $option['title'], 'answer' => $option['answer']];
            }
            $categories[] = ['id' => $categoryId, 'title' => $category['title'], 'options' => $options];
        }

        return $categories;
    }
}
