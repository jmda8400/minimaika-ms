<?php

namespace App\Services\Bot;

class IntentDetectorService
{
    /**
     * @return array{intent:string,response:?string,use_rag:bool,show_auto_source:bool,prepend_response:?string}|null
     */
    public function detect(string $message): ?array
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return null;
        }

        if ($this->matchesAny($normalized, ['chau', 'adios', 'nos vemos', 'hasta luego', 'gracias chau', 'salir'])) {
            return [
                'intent' => 'despedida',
                'response' => '¡Gracias por comunicarte con el Refugio Agostino Rocca! Que tengas una excelente jornada de montaña.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['muchas gracias', 'perfecto gracias', 'ok gracias', 'genial gracias', 'gracias'])) {
            return [
                'intent' => 'agradecimiento',
                'response' => '¡De nada! Si necesitás algo más sobre el refugio, reservas, acceso o caminatas, escribime.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['sin gluten', 'gluten', 'celiaco', 'celiaca', 'celiacos', 'celiacas'])) {
            return [
                'intent' => 'comida_sin_gluten',
                'response' => 'Tenemos opciones sin gluten en la carta, pero no podemos garantizar contaminación cruzada. Si eso es un problema, recomendamos que traigas tu propia comida. En ese caso, consultanos en el refugio para ver cómo cocinar o calentar algo.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['vegetariano', 'vegetariana', 'vegetarianos', 'vegetarianas', 'vegano', 'vegana', 'veganos', 'veganas'])) {
            return [
                'intent' => 'restricciones_alimentarias',
                'response' => null,
                'use_rag' => true,
                'show_auto_source' => false,
                'prepend_response' => null,
            ];
        }

        if ($this->isGreetingOnly($normalized)) {
            return [
                'intent' => 'saludo',
                'response' => '¡Hola! Te comunicaste con el asistente virtual del Refugio Agostino Rocca. Puedo ayudarte con reservas, ubicación, acceso, horarios, servicios, caminatas, pagos y preguntas frecuentes. ¿En qué puedo ayudarte?',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['cancelar reserva', 'dar de baja reserva', 'anular reserva', 'no voy a ir', 'quiero cancelar'])) {
            return [
                'intent' => 'cancel_reservation',
                'response' => null,
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['reprogramar', 'cambiar fecha', 'modificar reserva', 'cambiar mi reserva', 'mover la reserva', 'no puedo ir en esa fecha', 'quiero ir otro dia'])) {
            return [
                'intent' => 'reschedule_reservation',
                'response' => null,
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['reembolso', 'devolucion', 'devolver plata', 'reintegro', 'me devuelven'])) {
            return [
                'intent' => 'refund_request',
                'response' => null,
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['no entiendo', 'no me quedo claro', 'explicame mejor', 'la verdad no entiendo', 'podes aclarar'])) {
            return [
                'intent' => 'clarification',
                'response' => null,
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['tengo una reserva', 'quiero reservar', 'hacer reserva', 'reservar', 'mi reserva', 'modificar reserva', 'cancelar reserva', 'reserva para', 'reservar alojamiento'])) {
            return [
                'intent' => 'reserva',
                'response' => 'Para alojarte en el Refugio Agostino Rocca es obligatorio contar con reserva previa. La reserva se realiza desde el motor de reservas de la web oficial. Puedo ayudarte con información sobre pagos, fechas, pernocte, servicios o políticas de reserva.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->isPriceQuestion($normalized)) {
            return [
                'intent' => 'precio_tarifas',
                'response' => null,
                'use_rag' => true,
                'show_auto_source' => false,
                'prepend_response' => 'Los valores de pernocte, comidas y servicios deben consultarse en la sección de tarifas de la web oficial del refugio. No tengo permitido inventar precios. También puedo contarte qué servicios tienen costo aparte, como la ducha.',
            ];
        }

        if ($this->matchesAny($normalized, ['donde queda', 'ubicacion', 'como llegar', 'pampa linda', 'camino', 'senda', 'acceso'])) {
            return [
                'intent' => 'ubicacion_acceso',
                'response' => null,
                'use_rag' => true,
                'show_auto_source' => false,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['wifi', 'wi fi', 'internet', 'senal', 'celular', 'telefono'])) {
            return [
                'intent' => 'senal_wifi',
                'response' => 'En el refugio no hay señal de teléfono ni WiFi.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        if ($this->matchesAny($normalized, ['acampar', 'acampe', 'carpa', 'camping'])) {
            return [
                'intent' => 'acampe',
                'response' => null,
                'use_rag' => true,
                'show_auto_source' => false,
                'prepend_response' => null,
            ];
        }

        if ($this->looksConversational($normalized)) {
            return [
                'intent' => 'fallback_conversacional',
                'response' => 'Puedo ayudarte con información sobre reservas, ubicación, acceso desde Pampa Linda, horarios del camino, servicios, pagos, caminatas y preguntas frecuentes del Refugio Agostino Rocca.',
                'use_rag' => false,
                'show_auto_source' => true,
                'prepend_response' => null,
            ];
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @param array<int,string> $needles */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksConversational(string $text): bool
    {
        return $this->matchesAny($text, ['hola', 'buen', 'gracias', 'chau', 'adios', 'como estas', 'que tal', 'necesito', 'quiero', 'ayuda', 'consulta']);
    }

    private function isGreetingOnly(string $text): bool
    {
        return in_array($text, ['hola', 'hola buenas', 'hola buen dia', 'hola buenas tardes', 'hola buenas noches', 'buenas', 'buen dia', 'buenas tardes', 'buenas noches', 'que tal', 'como estas'], true);
    }

    private function isPriceQuestion(string $text): bool
    {
        if ($this->matchesAny($text, ['precio', 'precios', 'tarifa', 'tarifas', 'cuanto cuesta', 'cuanto sale', 'valor', 'valores'])) {
            return true;
        }

        if ($this->matchesAny($text, ['pernocte', 'ducha'])) {
            return true;
        }

        return $this->matchesAny($text, ['costo comida', 'costo comidas', 'comida cuesta', 'comidas cuestan', 'valor comida', 'valor comidas']);
    }
}
