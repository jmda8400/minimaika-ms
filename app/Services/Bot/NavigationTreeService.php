<?php

namespace App\Services\Bot;

class NavigationTreeService
{
    public const WELCOME = "Bienvenido al BOT del refugio Agostino Rocca\nEste número vas a encontrar toda la información para organizar tu visita.\nPODRÁS REALIZAR TUS RESERVAS DESDE EL 1 DE OCTUBRE\nPara gestionar reservas o reprogramar: https://www.refugioagostinorocca.com\nEstado del sendero: https://www.refugioagostinorocca.com/#estado-del-sendero\nPronóstico: https://www.windguru.cz/95099\nMás información en nuestro sitio web https://www.refugioagostinorocca.com";

    /** @return array{kind:string,text?:string,title?:string,button?:string,rows?:array,parent?:string} */
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

        return ['kind' => 'answer', 'text' => $node['answer'], 'parent' => $node['parent']];
    }

    public function welcome(): string
    {
        return self::WELCOME;
    }

    /** @param array<int,array{id:string,title:string,description:string}> $rows */
    public function textMenu(string $title, array $rows): string
    {
        $options = array_map(
            static fn (array $row, int $index): string => sprintf('%d. %s', $index + 1, $row['title']),
            $rows,
            array_keys($rows),
        );

        return $title."\n\n".implode("\n", $options)."\n\nRespondé con el número de la opción.";
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
            $rows[] = ['id' => 'nav:'.$childId, 'title' => $child['title'], 'description' => $child['description'] ?? 'Seleccionar'];
        }
        if ($id !== 'main') {
            $rows[] = ['id' => 'nav:main', 'title' => '🏠 Menú principal', 'description' => 'Volver al inicio'];
        }

        return ['kind' => 'menu', 'title' => $id === 'main' ? 'Por favor selecciona una opción para continuar' : $node['title'], 'button' => 'Ver opciones', 'rows' => $rows];
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
        return config('navigation.nodes');
    }
}
