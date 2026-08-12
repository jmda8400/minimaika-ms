<?php

$site = 'https://www.refugioagostinorocca.com';
$tarifas = $site.'/#tarifas';
$windguru = 'https://www.windguru.cz/95099';

$categories = [
    'rates' => 'Tarifas y menú', 'services' => 'Servicios', 'food' => 'Comidas', 'payments' => 'Medios de pago',
    'boat' => 'Regreso con el barco por lago Frías', 'trails' => 'Sendero, transporte, travesías', 'season' => 'Fecha de apertura y cierre',
    'weather' => 'Clima, lluvia, equipamiento y vestimenta sugerida', 'missing' => 'Estoy buscando a una persona perdida', 'claims' => 'Reclamos y solicitudes',
];

$items = [
    'rates' => [
        ['rates_link', 'Tarifas', $tarifas],
        ['guide_discount', 'Descuentos guías', 'Los guías de montaña socios de AAGM y con su carnet al día, cuentan con 50% de descuento en el pernocte y 20% en productos elaborados. Al reservar es necesario abonar el pernocte total y se aplica el descuento al llegar al refugio, presentando el carnet de guía. Podés utilizar el saldo para otros consumos o pedir la devolución correspondiente, según prefieras.'],
        ['children_discount', 'Descuentos para niños', 'Los menores de 10 años se benefician con un descuento del 50% en pernocte y un descuento del 20% en productos elaborados en el refugio.'],
        ['cab_discount', 'Descuentos socios de CAB', 'Los socios del CAB (con seis meses de antigüedad, con carnet y comprobante de cuotas al día), abonan un 20% del pernocte y se benefician con un descuento del 30% en productos elaborados en el refugio. Cuando realicen la reserva del pernocte por la web recibirán el descuento en el importe a pagar siempre y cuando cumplan con las condiciones descriptas.'],
    ],
    'services' => [
        ['camping', 'Acampe', 'En la zona del refugio Parques Nacionales no permite el acampe, para consultar las zonas de acampe comunicate con Parques Nacionales.'],
        ['hot_water', 'Agua caliente', 'En el refugio el agua caliente para mate, té o infusiones es gratis.'],
        ['cooking', '¿Puedo cocinar en el refugio?', "Desde la pandemia Parques Nacionales prohibió la cocina para visitantes en los refugios de montaña. Si querés cocinarte tu comida tenés que hacerlo con tu propio calentador.\nEsto se realiza afuera del refugio por cuestiones de seguridad.\nPodrás cocinar afuera y comer dentro del refugio."],
        ['sleeping_bag', 'Alquiler de bolsa de dormir', "Vas a poder alquilar tu bolsa de dormir arriba en el refugio, no es necesario que la reserves con anticipación (hay para todos). $tarifas"],
        ['heating', 'Calefacción', 'Todas las habitaciones cuentan con calefacción.'],
        ['electricity', 'Electricidad', 'Vas a poder recargar tu teléfono celular.'],
        ['lockers', 'Lockers y zapatero', 'En nuestro hall de entrada contamos con lockers para las mochilas y zapateros individuales.'],
        ['rooms', 'Habitaciones', "El refugio posee 10 habitaciones con ocho camas en cada una.\nLas camas son tipo cuchetas, cuatro camas arriba y cuatro abajo.\nTodas las camas tienen colchón y almohada con sus respectivas fundas de sábana. No hay mantas, tenés que traer tu bolsa de dormir o alquilarla arriba en el refugio.\nCuando realizás una reserva, la misma no te garantiza que puedas estar con tu grupo en la misma habitación.\nIgualmente nosotros siempre intentamos que de ser posible eso sea así.\n\nNo podemos asegurarlo pero siempre damos prioridad a las familias con niños pequeños y luego a los grupos, pondremos siempre nuestra mejor voluntad."],
        ['connectivity', 'Wifi y señal de celular', 'En el refugio no tenemos señal de celular ni wifi.'],
        ['guides', 'Guías', 'Nosotros no ofrecemos el servicio de guías, pero podés consultar con el Refugio Meiling o contactar algún guía de este link: https://www.aagm.com.ar/guias-asociados/'],
    ],
    'food' => [
        ['food_booking', '¿Es necesario reservar las comidas?', 'No es necesario que reserves las comidas, las mismas están disponibles siempre. Cuando llegás al refugio podés pedirlas sin problema.'],
        ['gluten_free', 'Comida sin gluten', 'Tenemos opciones sin gluten en nuestra carta, pero no podemos garantizar la contaminación cruzada. En caso de que esto sea un problema solemos pedirles si pueden llevarse su propia comida. Si necesitás cocinar/calentar alguna cosa también se podría en este caso.'],
        ['new_year', 'Cena de año nuevo', 'Para la cena de año nuevo los platos son a la carta con la misma tarifa publicada en la web. Luego nosotros desde el refugio obsequiamos una mesa de dulces y un brindis, donde quienes lo deseen pueden sumar algo para compartir.'],
        ['board', 'Media pensión/completa', 'Nosotros no ofrecemos pensiones, podés combinar tus consumos como vos quieras, siempre están disponibles todos los ítems de la carta.'],
        ['veggie', 'Opciones vegetarianas y veganas', "Tenemos opciones para ambos casos, podés consultar el menú que está en $tarifas"],
    ],
    'payments' => [
        ['dollars', 'Pago en dólares', 'Podés pagar con dólares. Solo se reciben billetes de USD 100 en perfecto estado a la cotización del día. El vuelto se da en pesos.'],
        ['payment_methods', 'Formas de pago', 'Podés pagar en efectivo con descuento, QR con Modo o tarjetas de crédito.'],
    ],
    'boat' => [
        ['boat_tickets', 'Pasajes del barco', 'Los pasajes para el regreso en barco por Puerto Frías tenés que sacarlos antes de subir al refugio. https://www.turisur.com.ar'],
        ['boat_schedule', 'Horario de regreso del barco de Paso de las Nubes', "Normalmente el barco desde Puerto Frías sale a las 14:30 hs y navega durante 20 minutos. Luego, en un minibús te llevan los 3 km hasta Puerto Blest, donde tomás el catamarán hasta Puerto Pañuelos (Llao Llao). Consultá en Turisur el horario de llegada: https://www.turisur.com.ar. En el precio están incluidos todos estos tramos. Es importante que consultes y confirmes estos horarios directamente con la empresa Turisur cuando saques el pasaje, ya que pueden sufrir modificaciones: https://www.turisur.com.ar"],
        ['boat_departure', '¿A qué hora salir por la mañana desde el refugio para llegar al barco?', 'Desde el refugio te aconsejamos que salgas con tiempo, normalmente se calcula dos horas más de lo que tardaste en subir.'],
    ],
    'trails' => [
        ['trail_status', 'Estado del sendero', "En este link vas a encontrar la última actualización acerca del estado del sendero: $site/#estado-del-sendero"],
        ['arrival', 'Cómo llegar al refugio', "Podés ver información de cómo llegar al refugio en $site/#llegada"],
        ['ascent', 'Sendero de ascenso al refugio', 'https://www.barilochetrekking.com/sendero-76/'],
        ['ascent_time', '¿Cuánto tardo en subir?', "La distancia hasta el refugio es de 14 km.\nEl desnivel es de 600 metros.\nTiempo estimado de marcha entre 4 y 6 horas, hay que aclarar que esto es una estimación tomada para una persona con buen estado físico y es solo una referencia, ya que para cada persona esto puede ser diferente según su condición física.\nMás información en https://www.refugiorocca.com"],
        ['trail_water', '¿Hay agua en el sendero de subida?', 'En el km 3 del sendero cruzás el río Castaño Overa, luego desde el km 5 hasta el km 9 del trayecto de subida hacia el refugio siempre tenés muy cerca el río Alerce a tu derecha. Desde el km 9 hacia arriba vas a cruzar varios puntos donde pasan cursos de agua para recargar.'],
        ['transport', 'Transportes a Pampa Linda', 'Travel Light +54 9 2944213932 / Transitando lo Natural +54 9 2944608581'],
        ['walks', '¿Qué caminatas se pueden hacer desde el refugio?', "Podés ver información de las distintas caminatas desde el refugio en este link: $site/#trekkings"],
        ['maps', 'Senderos, mapas, distancias', "Podés encontrar mapas de la zona del refugio en $site. Te recomiendo también https://www.barilochetrekking.com, ahí podés encontrar información y mapas de otros senderos del parque y la zona.\nInformación específica senderos zona Tronador:\nILÓN - ROCCA (Paso La Marca) https://barilochetrekking.com/sendero-129/\nMEILING - ROCCA: https://barilochetrekking.com/sendero-127/\nPASO DE LAS NUBES: https://barilochetrekking.com/sendero-77/"],
    ],
    'season' => [
        ['opening', 'Apertura e inicio de temporada', 'Normalmente apuntamos a abrir el refugio el 1 de noviembre. Desde el 1 al 15 de noviembre reservamos tu lugar sin pago anticipado debido a que las condiciones de acceso pueden ser aún complicadas por la cantidad de nieve y estado del sendero y esto demore la apertura.'],
        ['closing', 'Cierre del refugio', 'Habitualmente intentamos llegar hasta el 3 de mayo, pero esto siempre depende de que las primeras nevadas nos lo permitan. Desde el 15 de abril al 3 de mayo reservamos tu lugar sin pago anticipado ya que la fecha de cierre del refugio es un poco incierta debido a que en esa época ya pueden comenzar las primeras nevadas obligándonos a cerrar de forma imprevista. Siempre vamos a avisar con cierta anticipación el cierre del refugio a través de nuestra web y redes.'],
    ],
    'weather' => [
        ['equipment', 'Cómo equiparme', "Para consultar el estado del clima en la zona del refugio: $windguru\nCuando llueve en el sendero de acceso al refugio, el mismo suele estar con agua en alguno de los tramos, por lo que es aconsejable llevar buen calzado.\nTambién es importante llevar cubremochila impermeable y que todas las cosas dentro de la mochila estén dentro de una bolsa de nylon gruesa.\nEs importante contar con una muda de ropa de repuesto y además contar con la indumentaria adecuada para la ocasión: campera impermeable, cubrepantalón impermeable, guantes (en lo posible 2 pares), gorro, calzado adecuado tipo trekking y abrigarse en “capas”. Esto quiere decir que debemos ponernos una primera capa más fina, una segunda capa un poco más abrigada y la última capa debería ser la impermeable y que además corte el viento, de esta forma podemos ir regulando según la necesidad intentando que nuestro cuerpo no pase frío pero que no transpire."],
        ['rain', '¿Puedo subir con lluvia?', 'Poder se puede... PEROOOO esta es una respuesta muy difícil de dar, es algo muy personal, ya que depende no solo de las condiciones climáticas sino de tu equipamiento y sobre todo tu experiencia y conocimiento, el refugio si está abierto siempre te va a esperar para protegerte y brindarte calor...'],
        ['recommendations', 'Recomendaciones', "Más información en $site/#recomendaciones"],
    ],
    'missing' => [
        ['missing_person', 'Estoy buscando a una persona perdida', 'Tenés que tener en cuenta que en la zona no hay señal y que el cerro Tronador está a 80 km de Bariloche, así que ya sea que regresen vía terrestre con su auto o el bus o regresen con el barco desde Puerto Blest, normalmente el horario de llegada va a ser después de las 20 hs y es probable que recién en ese horario tengan señal de celular. Si necesitás contactar con una persona que te va a orientar, contactá al WhatsApp del refugio.'],
    ],
    'claims' => [
        ['booking_claim', 'Por reservas', "Te recordamos los términos de la política de cambio que fueron informados y aceptados en el momento de realizar la reserva:\n\nPOLÍTICA DE CAMBIOS O MODIFICACIÓN DE LA RESERVA\nEN NINGÚN CASO EL PAGO POR LOS SERVICIOS CONTRATADOS TIENE DEVOLUCIÓN.\nPodrás cambiar la fecha consignada en tu voucher, siempre que lo hicieras con una anticipación mínima de tres días a la fecha de tu llegada, y reprogramarla durante esta temporada hasta el cierre del refugio. Este cambio quedará sujeto a disponibilidad.\nEl pago realizado no es transferible para ser usado por otras personas para ningún tipo de servicio o consumos.\nEn caso que por razones fortuitas ajenas al concesionario del refugio, Parques Nacionales cierre el acceso a la zona sur del Parque y no sea posible llegar al refugio, se tendrá en consideración esta situación atípica y especial, dando la posibilidad al cliente de extender las fechas de reprogramación dejándola abierta para futuras temporadas. Para gestionar dicha reprogramación deberás contactarte a XXXX@gmail.com colocando en el asunto CIERRE FORTUITO, indicando:\n• Nombre completo\n• DNI\n• Fecha y código de reserva"],
        ['missing_voucher', 'Pagué y no recibí voucher', 'En breve una persona se comunicará con vos.'],
        ['other_claim', 'Otros', "Contactate a XXXX@gmail.com colocando en el asunto tu solicitud, indicando:\n• Nombre completo\n• DNI\n• Fecha y código de reserva\n• Número de habitación en la que dormiste"],
    ],
];

$nodes = ['main' => ['title' => 'Menú principal', 'children' => array_keys($categories)]];
foreach ($categories as $id => $title) {
    $nodes[$id] = ['title' => $title, 'children' => array_column($items[$id], 0)];
    foreach ($items[$id] as [$itemId, $itemTitle, $answer]) {
        $nodes[$itemId] = ['title' => $itemTitle, 'answer' => $answer, 'parent' => $id];
    }
}

return ['nodes' => $nodes];
