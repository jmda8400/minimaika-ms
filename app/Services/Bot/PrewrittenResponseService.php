<?php

namespace App\Services\Bot;

class PrewrittenResponseService
{
    /**
     * @return array<string, array{title:string,response:string,keywords:array<int,string>}>
     */
    public function all(): array
    {
        return [
            'pasajes_barco' => [
                'title' => 'PASAJES BARCO',
                'response' => "Los pasajes para el regreso en barco por Puerto Frías tenés que sacarlos antes de subir al refugio.\nwww.turisur.com.ar",
                'keywords' => ['pasajes barco', 'barco', 'puerto frias', 'puerto frías', 'turisur', 'catamaran', 'catamarán', 'regreso en barco'],
            ],
            'clima' => [
                'title' => 'CLIMA',
                'response' => 'Te comparto el link que usamos para ver el pronóstico puntual del refugio: www.windguru.cz/489881',
                'keywords' => ['clima', 'pronostico', 'pronóstico', 'windguru', 'tiempo', 'meteorologico', 'meteorológico'],
            ],
            'horario_camino_pampa_linda' => [
                'title' => 'HORARIO DEL CAMINO A PAMPA LINDA',
                'response' => "Horario del camino a Pampa Linda:\nSubida únicamente: de 10:30 a 14 hs desde Los Rápidos.\nBajada únicamente: 16 a 18 hs.\nDoble mano: de 19:30 a 9 hs.\nProhibido subir: entre las 14 y las 19:30 hs.",
                'keywords' => ['horario camino', 'horario del camino', 'camino pampa linda', 'los rapidos', 'los rápidos', 'subida camino', 'bajada camino', 'doble mano'],
            ],
            'reserva_inicio_temporada' => [
                'title' => 'RESERVA INICIO DE TEMPORADA',
                'response' => 'Del 1 al 15 de noviembre reservamos tu lugar sin pago anticipado debido a que las condiciones de acceso pueden ser aún complicadas por la cantidad de nieve y estado del sendero, y esto puede demorar la apertura.',
                'keywords' => ['inicio temporada', 'noviembre', '1 al 15 de noviembre', 'sin pago anticipado', 'apertura temporada'],
            ],
            'reserva_final_temporada' => [
                'title' => 'RESERVA FINAL DE TEMPORADA',
                'response' => 'Del 15 de abril al 3 de mayo reservamos tu lugar sin pago anticipado ya que la fecha de cierre del refugio es un poco incierta: en esa época pueden comenzar las primeras nevadas y obligarnos a cerrar de forma imprevista. Siempre vamos a avisar con cierta anticipación el cierre del refugio a través de nuestra web y redes.',
                'keywords' => ['final temporada', 'fin de temporada', '15 de abril', '3 de mayo', 'cierre temporada'],
            ],
            'estado_sendero' => [
                'title' => 'ESTADO DEL SENDERO',
                'response' => 'En este link vas a encontrar la última actualización acerca del estado del sendero: https://www.refugioagostinorocca.com/#estado-del-sendero',
                'keywords' => ['estado sendero', 'sendero abierto', 'sendero cerrado', 'estado del camino', 'estado senda'],
            ],
            'guias' => [
                'title' => 'GUIAS',
                'response' => 'Nosotros no ofrecemos el servicio de guías, pero podés consultar con el Refugio Meiling o contactar algún guía de este link: https://www.aagm.com.ar/guias-asociados/',
                'keywords' => ['guia', 'guía', 'guias', 'guías', 'aagm', 'servicio de guia', 'servicio de guía'],
            ],
            'registro_trekking' => [
                'title' => 'REGISTRO DE TREKKING',
                'response' => 'En esta dirección vas a encontrar el formulario para el registro de trekking: https://nahuelhuapi.gov.ar/',
                'keywords' => ['registro trekking', 'registro de trekking', 'apn', 'nahuel huapi', 'parques nacionales'],
            ],
            'cierre_refugio' => [
                'title' => 'CIERRE DEL REFUGIO',
                'response' => 'El refugio estará abierto hasta que llegue la primera nevada que ya complica el acceso. Habitualmente eso sucede a fines de abril o principios de mayo. Siempre vamos a avisar con cierta anticipación el cierre del refugio a través de nuestra web y redes.',
                'keywords' => ['cierre refugio', 'cuando cierra', 'hasta cuando abierto', 'primera nevada'],
            ],
            'como_llegar_refugio' => [
                'title' => 'COMO LLEGAR AL REFUGIO',
                'response' => 'Podés ver información de cómo llegar al refugio en https://www.refugioagostinorocca.com/#llegada',
                'keywords' => ['como llegar', 'cómo llegar', 'llegada', 'ubicacion', 'ubicación', 'acceso refugio', 'llegar a pampa linda'],
            ],
            'preguntas_frecuentes' => [
                'title' => 'PREGUNTAS FRECUENTES',
                'response' => 'Podés revisar las preguntas frecuentes en https://www.refugioagostinorocca.com/#faq',
                'keywords' => ['preguntas frecuentes', 'faq', 'dudas frecuentes'],
            ],
            'caminatas_desde_refugio' => [
                'title' => 'QUE CAMINATAS SE PUEDEN REALIZAR DESDE EL REFUGIO',
                'response' => 'Podés ver información de las distintas caminatas desde el refugio en este link: https://www.refugioagostinorocca.com/#trekkings',
                'keywords' => ['caminatas desde el refugio', 'trekkings desde', 'excursiones desde', 'que caminatas', 'qué caminatas'],
            ],
            'senderos_mapas_info' => [
                'title' => 'SENDEROS MAPAS, DISTANCIAS ETC INFO GENERAL',
                'response' => 'Podés encontrar mapas de la zona del refugio en https://www.refugioagostinorocca.com. Te recomiendo también https://www.barilochetrekking.com; ahí podés encontrar información y mapas de otros senderos del parque y la zona.',
                'keywords' => ['mapas', 'distancias', 'senderos', 'bariloche trekking', 'info general senderos'],
            ],
            'senderos_tronador' => [
                'title' => 'INFORMACION ESPECIFICA DE SENDEROS DE LA ZONA TRONADOR',
                'response' => "Información específica de senderos de la zona Tronador:\nILON - ROCCA (Paso La Marca): https://barilochetrekking.com/sendero-129/\nMEILING - ROCCA: https://barilochetrekking.com/sendero-127/\nPASO DE LAS NUBES: https://barilochetrekking.com/sendero-77/",
                'keywords' => ['tronador', 'paso la marca', 'ilon rocca', 'ilón rocca', 'meiling rocca', 'paso de las nubes'],
            ],
            'reclamo_reserva_terminos' => [
                'title' => 'RECLAMO POR LA RESERVA, TERMINOS Y CONDICIONES',
                'response' => "Ante el reclamo o consulta que estás realizando, te recordamos que al momento de hacer tu reserva aceptaste la política de cambios o modificación de la reserva.\n\nPOLÍTICA DE CAMBIOS O MODIFICACIÓN DE LA RESERVA\nEN NINGÚN CASO EL PAGO POR LOS SERVICIOS CONTRATADOS TIENE DEVOLUCIÓN.\nPodrás cambiar la fecha consignada en tu voucher, siempre que lo hicieras con una anticipación mínima de tres días a la fecha de tu llegada, y reprogramarla durante esta temporada hasta el cierre del refugio. Este cambio quedará sujeto a disponibilidad.\nEl pago realizado no es transferible para ser usado por otras personas para ningún tipo de servicio o consumos.\nEn caso de que por razones fortuitas ajenas al concesionario del refugio Parques Nacionales cierre el acceso a la zona sur del Parque y no sea posible llegar al refugio, se tendrá en consideración esta situación atípica y especial, dando la posibilidad de extender las fechas de reprogramación dejándola abierta para futuras temporadas. Para gestionar dicha reprogramación deberán contactarse al WhatsApp colocando las palabras CIERRE FORTUITO.",
                'keywords' => ['reclamo reserva', 'terminos condiciones', 'términos condiciones', 'devolucion', 'devolución', 'reembolso', 'politica cambios', 'política cambios'],
            ],
            'comida_sin_gluten' => [
                'title' => 'COMIDA SIN GLUTEN',
                'response' => 'Tenemos opciones sin gluten en nuestra carta, pero no podemos garantizar la contaminación cruzada. En caso de que esto sea un problema, solemos pedirles si pueden llevarse su propia comida. Si necesitás cocinar o calentar algo, también se podría en este caso.',
                'keywords' => ['sin gluten', 'gluten', 'sin tacc', 'tacc', 'sin tac', 'tac', 'celiaco', 'celíaco', 'celiaca', 'celíaca', 'apto celiaco', 'apto celíaco'],
            ],
            'descuentos_guias' => [
                'title' => 'DESCUENTOS PARA GUIAS',
                'response' => 'Los guías de montaña socios de AAGM y con su carnet al día cuentan con 50% de descuento en el pernocte y 20% en productos elaborados. Al reservar es necesario abonar el pernocte total y se aplica el descuento al llegar al refugio, presentando el carnet de guía. Podés utilizar el saldo para otros consumos o pedir la devolución correspondiente, según prefieras.',
                'keywords' => ['descuento guia', 'descuento guía', 'descuentos guias', 'descuentos guías', 'guia aagm', 'guía aagm'],
            ],
            'cierre_inesperado_senderos' => [
                'title' => 'CIERRE INESPERADO DE LOS SENDEROS DEL PARQUE NACIONAL',
                'response' => 'La verdad esto es bastante día a día. Parques Nacionales no tiene una manera muy eficiente de ver el cierre o apertura del sendero, solamente con el registro de trekking. Si te permite hacerlo el día de la subida quiere decir que está abierto. Nosotros también lo vemos por ahí; no tenemos otra información.',
                'keywords' => ['cierre inesperado', 'senderos parque nacional', 'parque cerro', 'sendero cerrado parques', 'apertura sendero'],
            ],
            'horario_barco_regreso' => [
                'title' => 'HORARIO DEL BARCO DE REGRESO POR PASO DE LAS NUBES',
                'response' => 'Normalmente el barco desde Puerto Frías sale a las 14:30 hs y navega durante 20 minutos. Luego, en un minibús te llevan los 3 km hasta Puerto Blest, donde tomás el catamarán hasta Puerto Pañuelos (Llao Llao). Es importante que consultes y confirmes estos horarios directamente con Turisur cuando saques el pasaje, ya que pueden sufrir modificaciones: www.turisur.com.ar',
                'keywords' => ['horario barco', 'barco puerto frias', 'puerto blest', 'puerto pañuelos', 'puerto panuelos', '14:30', 'paso de las nubes barco'],
            ],
            'salir_para_llegar_barco' => [
                'title' => 'A QUE HORA SALIR POR LA MAÑANA DESDE EL REFUGIO PARA LLEGAR AL BARCO',
                'response' => 'Desde el refugio te aconsejamos que salgas con tiempo. Normalmente se calcula dos horas más de lo que tardaste en subir.',
                'keywords' => ['hora salir barco', 'llegar al barco', 'salir del refugio al barco', 'cuanto antes salir'],
            ],
            'reservar_comidas' => [
                'title' => 'ES NECESARIO RESERVAR LAS COMIDAS',
                'response' => 'No es necesario que reserves las comidas, están disponibles siempre. Cuando llegás al refugio podés pedirlas sin problema.',
                'keywords' => ['reservar comida', 'reservar comidas', 'reserva comida', 'comida al llegar', 'pedir comida'],
            ],
            'calendario_verde_no_reserva' => [
                'title' => 'PORQUE SI ESTA VERDE EL CALENDARIO NO PUEDO RESERVAR',
                'response' => 'Seguramente estás solicitando una reserva para un cupo mayor de personas del que tenemos disponible para ese día.',
                'keywords' => ['calendario verde', 'no puedo reservar', 'cupo disponible', 'disponibilidad calendario'],
            ],
            'agua_sendero_subida' => [
                'title' => 'HAY AGUA EN EL SENDERO DE SUBIDA',
                'response' => 'En el km 3 del sendero cruzás el río Castaño Overa. Luego, desde el km 5 hasta el km 9 del trayecto de subida hacia el refugio, siempre tenés muy cerca el río Alerce a tu derecha. Desde el km 9 hacia arriba vas a cruzar varios puntos donde pasan cursos de agua para recargar.',
                'keywords' => ['agua sendero', 'agua durante el sendero', 'hay agua durante el sendero', 'agua subida', 'recargar agua', 'rio castaño overa', 'río castaño overa', 'rio alerce', 'río alerce'],
            ],
            'acampe' => [
                'title' => 'ACAMPE, ACAMPAR, CAMPING',
                'response' => 'En la zona del refugio Parques Nacionales no permite el acampe. Para consultar las zonas de acampe, comunicate con Parques Nacionales.',
                'keywords' => ['acampe', 'acampar', 'camping', 'carpa'],
            ],
            'reprogramar_reserva' => [
                'title' => 'REPROGRAMAR LA FECHA DE LA RESERVA',
                'response' => 'En nuestra página web, al lado del botón para reservar, tenés el botón de reprogramación. En el voucher que recibiste por mail vas a encontrar el “código de reserva” grupal o individual. Con este código, desde el botón de reprogramar vas a poder hacerlo. Podés reprogramar hasta un día antes de la fecha de tu reserva.',
                'keywords' => ['reprogramar', 'cambiar fecha', 'modificar reserva', 'cambio de fecha', 'codigo reserva', 'código reserva'],
            ],
            'cocina_visitantes' => [
                'title' => 'COCINA DE VISITANTES, PUEDO COCINAR, DONDE PUEDO COCINAR',
                'response' => 'Desde la pandemia Parques Nacionales prohibió la cocina para visitantes en los refugios de montaña. Si querés cocinarte tu comida, tenés que hacerlo con tu propio calentador. Esto se realiza afuera del refugio por cuestiones de seguridad. Podrás cocinar afuera y comer dentro del refugio.',
                'keywords' => ['cocina visitantes', 'puedo cocinar', 'donde cocinar', 'dónde cocinar', 'calentador', 'cocinar comida'],
            ],
            'agua_caliente' => [
                'title' => 'AGUA CALIENTE',
                'response' => 'En el refugio el agua caliente para mate, té o infusiones es gratis.',
                'keywords' => ['agua caliente', 'mate', 'te', 'té', 'infusiones'],
            ],
            'contacto_buses_pampa_linda' => [
                'title' => 'CONTACTO DE BUSES A PAMPA LINDA',
                'response' => "Contactos de buses a Pampa Linda:\nTRAVEL LIGHT 2944213932 WSP\nTRANSITANDO LO NATURAL 2944608581 WSP",
                'keywords' => ['bus pampa linda', 'buses pampa linda', 'colectivo pampa linda', 'travel light', 'transitando lo natural', 'transporte pampa linda'],
            ],
            'formas_pago_refugio' => [
                'title' => 'FORMAS DE PAGO EN EL REFUGIO',
                'response' => "En el refugio podés pagar con:\n- Efectivo con descuento\n- Tarjetas de crédito y débito\n- Mercado Pago\n- Transferencia",
                'keywords' => ['formas de pago', 'medio de pago', 'medios de pago', 'tarjeta', 'mercado pago', 'transferencia', 'efectivo'],
            ],
            'pagar_dolares' => [
                'title' => 'PAGAR CON DOLARES',
                'response' => 'Podés pagar con dólares. Sólo se reciben billetes de USD 100 en perfecto estado a la cotización del día. El vuelto se da en pesos.',
                'keywords' => ['dolares', 'dólares', 'usd', 'pagar dolares', 'pagar dólares'],
            ],
            'cargar_celular' => [
                'title' => 'PUEDO CARGAR EL CELULAR',
                'response' => 'Sí, tenés enchufes en el comedor y en las habitaciones.',
                'keywords' => ['cargar celular', 'enchufes', 'cargar telefono', 'cargar teléfono', 'electricidad'],
            ],
            'agua_potable' => [
                'title' => 'EL AGUA ES POTABLE',
                'response' => 'Sí, el agua es potable.',
                'keywords' => ['agua potable', 'agua refugio', 'agua en el refugio', 'hay agua en el refugio', 'tomar agua', 'agua se puede tomar'],
            ],
            'calefaccion' => [
                'title' => 'CALEFACCION',
                'response' => 'Es necesario traer tu bolsa de dormir. Las habitaciones del refugio tienen calefacción, por lo que no es necesario para dormir tener una bolsa de dormir demasiado abrigada.',
                'keywords' => ['calefaccion', 'calefacción', 'frio habitacion', 'frío habitación', 'bolsa abrigada'],
            ],
            'habitaciones_camas' => [
                'title' => 'HABITACIONES, CAMAS, COLCHONES, SÁBANAS',
                'response' => 'El refugio posee 10 habitaciones con 8 camas en cada una. Las camas son tipo cuchetas: 4 camas arriba y 4 abajo. Todas las camas tienen colchón y almohada con sus respectivas fundas de sábana. No hay mantas: tenés que traer tu bolsa de dormir o alquilarla arriba en el refugio.',
                'keywords' => ['habitaciones', 'camas', 'colchones', 'sabanas', 'sábanas', 'cuchetas', 'mantas'],
            ],
            'alquilar_bolsa_dormir' => [
                'title' => 'ALQUILAR BOLSA DE DORMIR',
                'response' => 'Si necesitás alquilar una bolsa de dormir, la pedís cuando llegás al refugio. No es necesario reservarla con anticipación.',
                'keywords' => ['alquilar bolsa', 'bolsa de dormir', 'alquiler bolsa', 'reservar bolsa'],
            ],
            'cv' => [
                'title' => 'CV',
                'response' => 'Si querés enviar un CV podés hacerlo a este WhatsApp.',
                'keywords' => ['cv', 'curriculum', 'currículum', 'trabajo', 'trabajar'],
            ],
            'cena_ano_nuevo' => [
                'title' => 'CENA DE AÑO NUEVO',
                'response' => 'Para la cena de Año Nuevo los platos son a la carta con la misma tarifa publicada en la web. Luego nosotros desde el refugio obsequiamos una mesa de dulces y un brindis, donde por lo general todos aportan también lo que trajeron y se comparte entre todos.',
                'keywords' => ['año nuevo', 'ano nuevo', 'cena año nuevo', 'cena ano nuevo', '31 de diciembre'],
            ],
            'descuentos_ninos' => [
                'title' => 'DESCUENTOS PARA NIÑOS',
                'response' => 'Los menores de 10 años se benefician con un descuento del 50% en pernocte y un descuento del 20% en productos elaborados en el refugio.',
                'keywords' => ['descuento niños', 'descuento ninos', 'menores de 10', 'niños', 'ninos'],
            ],
            'descuentos_socios_cab' => [
                'title' => 'DESCUENTOS SOCIOS DEL CLUB ANDINO CAB',
                'response' => 'Los socios del CAB, con seis meses de antigüedad, carnet y comprobante de cuotas al día, abonan un 20% menos del pernocte y se benefician con un descuento del 30% en productos elaborados en el refugio. Cuando realicen la reserva del pernocte por la web recibirán el descuento en el importe a pagar siempre y cuando cumplan con las condiciones descriptas.',
                'keywords' => ['socios cab', 'club andino', 'descuento cab', 'descuento socios'],
            ],
            'cambiar_pago_consumos_persona' => [
                'title' => 'CAMBIAR EL PAGO POR EL MONTO DE LA RESERVA PARA SER USADO PARA CONSUMOS O PARA OTRA PERSONA',
                'response' => 'No es posible realizar este cambio. Ante el reclamo o consulta que estás realizando, te recordamos que al momento de hacer tu reserva aceptaste la política de cambios o modificación de la reserva.',
                'keywords' => ['usar pago consumos', 'transferir reserva', 'otra persona', 'usar reserva para consumos', 'cambiar pago'],
            ],
            'misma_habitacion' => [
                'title' => 'PODEMOS ESTAR JUNTOS EN LA MISMA HABITACION',
                'response' => 'Cuando realizás una reserva, la misma no te garantiza que puedas estar con tu grupo en la misma habitación. Igualmente nosotros siempre intentamos que, de ser posible, eso sea así. No podemos asegurarlo, pero siempre damos prioridad a las familias con niños pequeños y luego a los grupos, poniendo siempre nuestra mejor voluntad.',
                'keywords' => ['misma habitacion', 'misma habitación', 'juntos habitacion', 'grupo misma habitacion', 'familia habitacion'],
            ],
            'tiempo_subida' => [
                'title' => 'CUANTO TIEMPO TARDO EN SUBIR',
                'response' => 'La distancia hasta el refugio es de 14 km, con un desnivel de 600 metros. El tiempo estimado de marcha es entre 4 y 6 horas. Es una estimación para una persona con buen estado físico y solo una referencia, ya que puede variar según cada condición física. Más información en www.refugiorocca.com',
                'keywords' => ['tiempo subir', 'cuanto tardo', 'cuánto tardo', 'distancia refugio', '14 km', 'desnivel', '4 y 6 horas'],
            ],
            'tarifas_precios' => [
                'title' => 'TARIFAS O PRECIOS DEL REFUGIO',
                'response' => 'Podés ver las tarifas en www.refugiorocca.com',
                'keywords' => ['tarifas', 'precios', 'precio', 'cuanto cuesta', 'cuánto cuesta', 'cuanto sale', 'cuánto sale'],
            ],
            'recomendaciones' => [
                'title' => 'RECOMENDACIONES',
                'response' => 'Más información en https://www.refugioagostinorocca.com/#recomendaciones',
                'keywords' => ['recomendaciones', 'que llevar', 'qué llevar', 'equipamiento recomendado'],
            ],
            'persona_perdida' => [
                'title' => 'ESTOY BUSCANDO A UNA PERSONA PERDIDA',
                'response' => 'Tené en cuenta que en la zona no hay señal y que el cerro Tronador está a 80 km de Bariloche. Ya sea que regresen vía terrestre con su auto o bus, o regresen con el barco desde Puerto Blest, normalmente el horario de llegada va a ser después de las 20 hs y es probable que recién en ese horario tengan señal de celular. También podés consultar en Parques Nacionales llamando o por WhatsApp al teléfono del ICE: +5492944303219.',
                'keywords' => ['persona perdida', 'buscando persona', 'no aparece', 'no volvió', 'no volvio', 'emergencia', 'ice'],
            ],
            'clima_lluvia_ropa' => [
                'title' => 'CLIMA, LLUVIA, ROPA O INDUMENTARIA ADECUADA',
                'response' => 'Para consultar el estado del clima en la zona del refugio usamos www.windguru.cz/489881. Cuando llueve en el sendero de acceso, suele haber agua en algunos tramos, por lo que es aconsejable llevar buen calzado, cubremochila impermeable y guardar las cosas dentro de una bolsa de nylon gruesa. También es importante llevar muda de ropa de repuesto e indumentaria adecuada: campera impermeable, cubrepantalón impermeable, guantes, gorro, calzado de trekking y abrigo por capas.',
                'keywords' => ['lluvia', 'ropa', 'indumentaria', 'que ropa', 'qué ropa', 'impermeable', 'equipamiento lluvia'],
            ],
            'subir_con_lluvia' => [
                'title' => 'PUEDO SUBIR CON LLUVIA',
                'response' => 'Poder se puede, pero es una respuesta muy personal: depende no solo de las condiciones climáticas, sino de tu equipamiento, experiencia y conocimiento. Si el refugio está abierto, siempre te va a esperar para protegerte y brindarte calor.',
                'keywords' => ['subir con lluvia', 'llueve puedo subir', 'lloviendo subir', 'lluvia subir'],
            ],
        ];
    }

    public function get(string $key): ?string
    {
        $responses = $this->all();

        return $responses[$key]['response'] ?? null;
    }

    public function detect(string $message): ?string
    {
        $normalized = $this->normalize($message);
        $bestKey = null;
        $bestScore = 0;

        foreach ($this->all() as $key => $item) {
            $score = 0;
            foreach ($item['keywords'] as $keyword) {
                $needle = $this->normalize($keyword);
                if ($needle !== '' && str_contains($normalized, $needle)) {
                    $score += max(1, substr_count($needle, ' ') + 1);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        return $bestScore > 0 ? $bestKey : null;
    }

    public function keysForPrompt(): string
    {
        return implode("\n", array_map(
            static fn (string $key, array $item): string => "- {$key}: {$item['title']}",
            array_keys($this->all()),
            $this->all()
        ));
    }

    public function isValidKey(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
