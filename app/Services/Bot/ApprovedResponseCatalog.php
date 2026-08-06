<?php

namespace App\Services\Bot;

class ApprovedResponseCatalog
{
    /**
     * This is the only source of user-visible bot copy. Every searchable record is
     * one approved response; no scraped pages or multi-FAQ fragments are included.
     *
     * @return array<string,array{id:string,intent:string,name:string,description:string,example_questions:array<int,string>,answer:string,is_active:bool,topic:string,canonical_question:string,aliases:array<int,string>,approved_answer:string,active:bool,metadata?:array<string,string>}>
     */
    public function all(): array
    {
        $records = [
            $this->record('answer.greeting', 'greeting', 'Saludo', ['hola', 'hola buenas', 'hola buen dia', 'hola buenas tardes', 'hola buenas noches', 'buenas', 'buen dia', 'buenas tardes', 'buenas noches', 'que tal', 'como estas', 'cómo están', 'hola cómo están', 'saludos'], '¡Hola! Te comunicaste con el asistente virtual del Refugio Agostino Rocca. Puedo ayudarte con reservas, ubicación, acceso, horarios, servicios, caminatas, pagos y preguntas frecuentes. ¿En qué puedo ayudarte?', true, [], 'El usuario saluda, pregunta cómo estamos o inicia la conversación de manera cordial.'),
            $this->record('answer.trail_water', 'trail_water', '¿Hay agua en el sendero?', ['hay agua durante el sendero', 'agua en el sendero', 'en el sendero'], 'En el km 3 del sendero cruzás el río Castaño Overa. Desde el km 5 hasta el km 9 tenés cerca el río Alerce y, desde el km 9, hay varios cursos de agua para recargar.'),
            $this->record('answer.refuge_drinking_water', 'refuge_drinking_water', '¿Hay agua potable en el refugio?', ['hay agua para tomar', 'agua potable', 'potable', 'agua en el refugio'], 'Sí, el agua del refugio es potable.'),
            $this->record('answer.hot_water', 'hot_water', '¿Hay agua caliente?', ['hay agua para el mate', 'agua caliente para mate', 'caliente', 'mate'], 'En el refugio el agua caliente para mate, té o infusiones es gratis.'),
            $this->record('answer.meal_reservation', 'meal_reservation', '¿Tengo que reservar comida?', ['tengo que reservar comidas', 'hay que reservar comida', 'reservar comida'], 'No es necesario que reserves las comidas: están disponibles siempre y podés pedirlas cuando llegás al refugio.'),
            $this->record('answer.lodging_reservation', 'lodging_reservation', '¿Tengo que reservar para dormir?', ['hay que reservar para dormir', 'reservar alojamiento', 'reservar pernocte', 'como hago para reservar', 'como reservar', 'quiero hacer una reserva'], 'Sí, para dormir en el refugio tenés que reservar el pernocte con anticipación desde www.refugiorocca.com.'),
            $this->record('answer.gluten_free', 'gluten_free', '¿Hay comida para celíacos?', ['comida sin gluten', 'sin tacc', 'comida para celiacos'], 'Tenemos opciones sin gluten en nuestra carta, pero no podemos garantizar la contaminación cruzada. Si eso es un problema, te pedimos que lleves tu propia comida; en ese caso podemos ayudarte a cocinarla o calentarla.'),
            $this->record('clarify.water_type', 'clarification', '¿A qué tipo de agua te referís?', ['hay agua', 'agua'], '¿Te referís a agua en el sendero, agua potable en el refugio o agua caliente?', true, ['risk' => 'ambiguity']),
            $this->record('fallback.unknown', 'fallback', 'Consulta no reconocida', [], 'No pude entender tu consulta. ¿Podés darme un poco más de detalle?', true, ['category' => 'safe_fallback']),
            // No existe todavía texto oficial aprobado para cancelar una reserva.
            // La estructura queda preparada, pero inactiva para impedir que el bot
            // envíe una respuesta vacía o inventada.
            $this->record('answer.cancelar_reserva', 'cancelar_reserva', 'Cancelar reserva', ['quiero cancelar mi reserva', 'cómo doy de baja una reserva', 'tenía una reserva y no voy a poder ir', 'quiero anular mi reserva'], '', false, ['status' => 'pending_approved_answer'], 'El usuario quiere cancelar, anular o dar de baja una reserva existente.'),
        ];

        // Preserve the existing, human-approved FAQ copy as individual records.
        foreach ((new PrewrittenResponseService())->all() as $id => $item) {
            $records[] = $this->record('answer.'.$id, $id, $item['title'], $item['keywords'], $item['response']);
        }

        $indexed = [];
        foreach ($records as $record) {
            $indexed[$record['id']] = $record;
        }

        return $indexed;
    }

    public function active(string $id): ?array
    {
        $record = $this->all()[$id] ?? null;

        return ($record['active'] ?? false) ? $record : null;
    }

    /** @return array<int,array{id:string,name:string,description:string,examples:array<int,string>}> */
    public function classifierEntries(?int $limit = null): array
    {
        $entries = [];
        foreach ($this->all() as $record) {
            if (! $record['is_active'] || $record['answer'] === '' || $record['id'] === 'fallback.unknown') {
                continue;
            }
            $entries[] = [
                'id' => (string) $record['id'],
                'name' => $record['name'],
                'description' => $record['description'],
                'examples' => $record['example_questions'],
            ];
        }

        return $limit === null || $limit <= 0 ? $entries : array_slice($entries, 0, $limit);
    }

    private function record(string $id, string $topic, string $question, array $aliases, string $answer, bool $active = true, array $metadata = [], ?string $description = null): array
    {
        $description ??= 'Corresponde cuando el usuario consulta sobre '.mb_strtolower(str_replace('_', ' ', $topic)).'. '.$question;
        $record = [
            'intent' => $topic, 'name' => $question, 'description' => $description,
            'example_questions' => $aliases, 'answer' => $answer, 'is_active' => $active,
            'id' => $id, 'topic' => $topic, 'canonical_question' => $question,
            'aliases' => $aliases, 'approved_answer' => $answer, 'active' => $active,
        ];

        // Required schema fields must remain present even when their value is an
        // empty array. In particular, fallback.unknown intentionally has no
        // aliases and is still indexed by the router.
        if ($metadata !== []) {
            $record['metadata'] = $metadata;
        }

        return $record;
    }
}
