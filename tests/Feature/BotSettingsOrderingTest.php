<?php

namespace Tests\Feature;

use App\Services\Bot\BotSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BotSettingsOrderingTest extends TestCase
{
    public static function menuStrategies(): array
    {
        return [['text'], ['buttons']];
    }

    #[DataProvider('menuStrategies')]
    public function test_saved_category_and_option_order_is_used_by_whatsapp(string $strategy): void
    {
        Storage::fake('local');
        config()->set('services.whatsapp_web.base_url', 'http://gateway.test');
        config()->set('services.whatsapp_web.api_key', 'test-key');
        config()->set('services.whatsapp_web.instance', 'rocca');
        config()->set('services.whatsapp_web.menu_strategy', $strategy);
        Http::fake(['*' => Http::response(['ok' => true])]);

        $settings = app(BotSettingsService::class)->get();
        $settings['navigation'] = [
            [
                'id' => 'first_category',
                'title' => 'Primera categoría',
                'options' => [['id' => 'first_answer', 'title' => 'Primera opción', 'answer' => 'Primera respuesta', 'send_alert' => false]],
            ],
            [
                'id' => 'second_category',
                'title' => 'Segunda categoría',
                'options' => [
                    ['id' => 'second_answer', 'title' => 'Segunda opción', 'answer' => 'Segunda respuesta', 'send_alert' => false],
                    ['id' => 'third_answer', 'title' => 'Tercera opción', 'answer' => 'Tercera respuesta', 'send_alert' => true],
                ],
            ],
        ];
        app(BotSettingsService::class)->save($settings);

        // Submit the order produced when the second category and its last option move up.
        $settings['navigation'] = array_reverse($settings['navigation']);
        $settings['navigation'][0]['options'] = array_reverse($settings['navigation'][0]['options']);

        $this->withSession(['bot-settings.authenticated' => true])
            ->put(route('bot.settings.update'), $settings)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bot.settings.edit'));

        $stored = json_decode(Storage::get('bot-settings.json'), true);
        $this->assertSame($settings['navigation'], $stored['navigation']);
        $this->assertSame($settings['navigation'], app(BotSettingsService::class)->get()['navigation']);

        $this->postJson(route('whatsapp.webhook'), $this->message('ordered-main', 'Hola'))
            ->assertOk()->assertJson(['status' => 'sent']);
        $this->assertMenuOrder($strategy, ['nav:second_category', 'nav:first_category'], ['Segunda categoría', 'Primera categoría']);

        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->postJson(route('whatsapp.webhook'), $this->message('ordered-category', '1'))
            ->assertOk()->assertJson(['status' => 'sent']);
        $this->assertMenuOrder($strategy, ['nav:third_answer', 'nav:second_answer', 'nav:main'], ['Tercera opción', 'Segunda opción', $settings['bot_texts']['back_title']]);

        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->postJson(route('whatsapp.webhook'), $this->message('ordered-answer', '1'))
            ->assertOk()->assertJson(['status' => 'sent']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendText/') && $request['text'] === 'Tercera respuesta');
    }

    private function assertMenuOrder(string $strategy, array $ids, array $titles): void
    {
        if ($strategy === 'text') {
            $numberedTitles = array_map(fn (string $title, int $index): string => ($index + 1).'. '.$title, $titles, array_keys($titles));
            Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendText/') && str_contains($request['text'], implode("\n", $numberedTitles)));

            return;
        }

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendButtons/')
            && array_column($request['buttons'], 'id') === $ids
            && array_column($request['buttons'], 'displayText') === $titles);
    }

    private function message(string $id, string $text): array
    {
        return [
            'instanceId' => 'rocca',
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net', 'id' => $id],
                'message' => ['conversation' => $text],
            ],
        ];
    }
}
