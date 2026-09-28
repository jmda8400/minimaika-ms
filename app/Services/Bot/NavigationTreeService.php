<?php

namespace App\Services\Bot;

class NavigationTreeService
{
    public const WELCOME = "Bienvenido al BOT del refugio Agostino Rocca\nEste número vas a encontrar toda la información para organizar tu visita.\nPODRÁS REALIZAR TUS RESERVAS DESDE EL 1 DE OCTUBRE\nPara gestionar reservas o reprogramar: https://www.refugioagostinorocca.com\nEstado del sendero: https://www.refugioagostinorocca.com/#estado-del-sendero\nPronóstico: https://www.windguru.cz/95099\nMás información en nuestro sitio web https://www.refugioagostinorocca.com";

    private ?array $resolvedNodes = null;

    private ?array $resolvedTexts = null;

    public function __construct(private readonly BotSettingsService $settingsService)
    {
    }

    /** @return array{kind:string,id?:string,text?:string,title?:string,button?:string,rows?:array,parent?:string} */
    public function navigate(string $selection): array
    {
        $id = $this->selectionId($selection);

        if ($id === 'main' || ! isset($this->nodes()[$id])) {
            return $this->menu('main');
        }

        $node = $this->nodes()[$id];
        if (isset($node['children'])) {
            return $this->menu($id);
        }

        return ['kind' => 'answer', 'id' => $id, 'text' => $node['answer'], 'parent' => $node['parent']];
    }

    public function welcome(): string
    {
        return $this->texts()['welcome'];
    }

    /** @param array<int,array{id:string,title:string,description:string}> $rows */
    public function textMenu(string $title, array $rows): string
    {
        $options = array_map(
            static fn (array $row, int $index): string => sprintf('%d. %s', $index + 1, $row['title']),
            $rows,
            array_keys($rows),
        );

        return $title."\n\n".implode("\n", $options)."\n\n".$this->texts()['select_prompt'];
    }

    /** @param array<int,string> $optionIds */
    public function resolveNumber(string $selection, array $optionIds): string
    {
        $selection = trim($selection);
        if (! preg_match('/^(?:(?:opci[oó]n)\s+)?(\d+)(?!.*\d)(?:\s*[.)]|\s*[-–—:]\s*[^\d].*)?$/iu', $selection, $matches)) {
            return $selection;
        }

        $index = (int) $matches[1] - 1;

        return $optionIds[$index] ?? $selection;
    }

    /** @return array{kind:string,title:string,button:string,rows:array<int,array{id:string,title:string,description:string}>} */
    public function menu(string $id): array
    {
        $node = $this->nodes()[$id];
        $rows = [];
        foreach ($node['children'] as $childId) {
            $child = $this->nodes()[$childId];
            $rows[] = ['id' => 'nav:'.$childId, 'title' => $child['title'], 'description' => $child['description'] ?? $this->texts()['option_description']];
        }
        if ($id !== 'main') {
            $rows[] = ['id' => 'nav:main', 'title' => $this->texts()['back_title'], 'description' => $this->texts()['back_description']];
        }

        return ['kind' => 'menu', 'title' => $id === 'main' ? $this->texts()['main_menu_title'] : $node['title'], 'button' => $this->texts()['menu_button'], 'rows' => $rows];
    }

    private function selectionId(string $selection): string
    {
        $selection = trim($selection);
        if (str_starts_with($selection, 'nav:')) {
            return substr($selection, 4);
        }

        $normalized = mb_strtolower($selection);
        foreach ($this->nodes() as $id => $node) {
            if (mb_strtolower($node['title']) === $normalized) {
                return $id;
            }
        }

        return 'main';
    }

    private function nodes(): array
    {
        if ($this->resolvedNodes !== null) {
            return $this->resolvedNodes;
        }

        $nodes = ['main' => ['title' => 'Menú principal', 'children' => []]];
        foreach ($this->settingsService->get()['navigation'] as $category) {
            $nodes['main']['children'][] = $category['id'];
            $nodes[$category['id']] = ['title' => $category['title'], 'children' => []];
            foreach ($category['options'] as $option) {
                $nodes[$category['id']]['children'][] = $option['id'];
                $nodes[$option['id']] = ['title' => $option['title'], 'answer' => $option['answer'], 'parent' => $category['id']];
            }
        }

        return $this->resolvedNodes = $nodes;
    }

    /** @return array<string,string> */
    private function texts(): array
    {
        return $this->resolvedTexts ??= $this->settingsService->get()['bot_texts'];
    }

    public function followUp(): string
    {
        return $this->texts()['follow_up'];
    }
}
