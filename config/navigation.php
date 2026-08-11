<?php

$site = 'https://www.refugioagostinorocca.com';
$tarifas = $site.'/#tarifas';
$windguru = 'https://www.windguru.cz/95099';

$categories = [
    'rates' => 'Tarifas y menú', 'services' => 'Servicios', 'food' => 'Comidas', 'payments' => 'Medios de pago',
    'boat' => 'Regreso por lago Frías', 'trails' => 'Senderos y transporte', 'season' => 'Apertura y cierre',
    'weather' => 'Clima y equipamiento', 'missing' => 'Busco a una persona', 'claims' => 'Reclamos y solicitudes',
];

$items = [
    'rates' => [
        ['rates_link', 'Tarifas', "Consultá las tarifas y el menú actualizados en $tarifas"],
        ['guide_discount', 'Descuento para guías', 'Los guías de montaña socios de AAGM, con carnet al día, cuentan con 50% de descuento en el pernocte y 20% en productos elaborados. Al reservar se abona el pernocte total; el descuento se aplica al llegar y presentar el carnet. Podés usar el saldo para consumos o pedir la devolución.'],
        ['children_discount', 'Descuento para niños', 'Los menores de 10 años tienen un descuento del 50% en el pernocte y del 20% en productos elaborados en el refugio.'],
        ['cab_discount', 'Descuento socios del CAB', 'Los socios del CAB con seis meses de antigüedad, carnet y cuotas al día abonan un 20% del pernocte y tienen 30% de descuento en productos elaborados. Al reservar por la web recibirán el descuento si cumplen esas condiciones.'],
    ],
    'services' => [
        ['camping', 'Acampe', 'Parques Nacionales no permite acampar en la zona del refugio. Consultá las zonas habilitadas directamente con Parques Nacionales.'],
        ['hot_water', 'Agua caliente', 'El agua caliente para mate, té o infusiones es gratis.'],
        ['cooking', '¿Puedo cocinar?', 'Parques Nacionales no permite que los visitantes cocinen dentro del refugio. Podés cocinar afuera con tu propio calentador, por seguridad, y comer dentro.'],
        ['sleeping_bag', 'Bolsa de dormir', "Podés alquilar una bolsa de dormir en el refugio; hay para todos y no hace falta reservarla. Tarifas: $tarifas"],
        ['heating', 'Calefacción', 'Todas las habitaciones cuentan con calefacción.'],
        ['electricity', 'Electricidad', 'Vas a poder recargar tu teléfono celular.'],
        ['lockers', 'Lockers y zapatero', 'En el hall de entrada contamos con lockers para mochilas y zapateros individuales.'],
        ['rooms', 'Habitaciones', 'El refugio posee 10 habitaciones con ocho camas tipo cucheta cada una. Tienen colchón, almohada y fundas de sábana, pero no mantas: tenés que traer o alquilar bolsa de dormir. La reserva no garantiza que todo un grupo quede en la misma habitación, aunque priorizamos familias con niños pequeños y luego grupos.'],
        ['connectivity', 'Wifi y señal celular', 'En el refugio no tenemos señal de celular ni wifi.'],
        ['guides', 'Guías', 'No ofrecemos servicio de guías. Podés consultar con el Refugio Meiling o contactar a un guía asociado: https://www.aagm.com.ar/guias-asociados/'],
    ],
    'food' => [
        ['food_booking', '¿Reservar comidas?', 'No hace falta reservar las comidas: todos los ítems de la carta están disponibles y podés pedirlos al llegar.'],
        ['gluten_free', 'Comida sin gluten', 'Tenemos opciones sin gluten, pero no podemos garantizar que no exista contaminación cruzada. Si esto es un problema, llevá tu propia comida; en ese caso podemos ayudarte a cocinarla o calentarla.'],
        ['new_year', 'Cena de Año Nuevo', 'Los platos son a la carta y tienen la tarifa publicada. El refugio obsequia una mesa de dulces y un brindis, a los que quienes quieran pueden sumar algo para compartir.'],
        ['board', 'Media pensión/completa', 'No ofrecemos pensiones. Podés combinar tus consumos como quieras; todos los ítems de la carta están disponibles.'],
        ['veggie', 'Vegetariano y vegano', "Tenemos opciones vegetarianas y veganas. Consultá el menú en $tarifas"],
    ],
    'payments' => [
        ['dollars', 'Pago en dólares', 'Podés pagar con dólares. Solo recibimos billetes de USD 100 en perfecto estado, a la cotización del día. El vuelto se entrega en pesos.'],
        ['payment_methods', 'Formas de pago', 'Podés pagar en efectivo con descuento, QR con MODO o tarjetas de crédito.'],
    ],
    'boat' => [
        ['boat_tickets', 'Pasajes del barco', 'Los pasajes para regresar en barco desde Puerto Frías deben comprarse antes de subir al refugio: https://www.turisur.com.ar'],
        ['boat_schedule', 'Horario del barco', 'Normalmente el barco sale de Puerto Frías a las 14:30 y navega 20 minutos. Luego un minibús recorre 3 km hasta Puerto Blest y allí tomás el catamarán a Puerto Pañuelos (Llao Llao). Todos los tramos están incluidos. Confirmá los horarios, que pueden cambiar, directamente con Turisur: https://www.turisur.com.ar'],
        ['boat_departure', '¿A qué hora salir?', 'Salí del refugio con tiempo. Normalmente se calcula para el descenso dos horas más de lo que tardaste en subir.'],
    ],
    'trails' => [
        ['trail_status', 'Estado del sendero', "Última actualización del sendero: $site/#estado-del-sendero"],
        ['arrival', 'Cómo llegar', "Información para llegar al refugio: $site/#llegada"],
        ['ascent', 'Sendero de ascenso', 'Información del sendero Pampa Linda–Refugio Rocca: https://www.barilochetrekking.com/sendero-76/'],
        ['ascent_time', '¿Cuánto tardo en subir?', 'Son 14 km, con 600 m de desnivel y entre 4 y 6 horas estimadas para una persona con buen estado físico. Es solo una referencia: depende de cada persona. Más información: https://www.refugioagostinorocca.com'],
        ['trail_water', 'Agua en el sendero', 'En el km 3 cruzás el río Castaño Overa; del km 5 al 9 tenés cerca el río Alerce a tu derecha, y desde el km 9 cruzás varios cursos de agua para recargar.'],
        ['transport', 'Transporte a Pampa Linda', 'Travel Light: +54 9 294 4213932. Transitando lo Natural: +54 9 294 4608581.'],
        ['walks', 'Caminatas desde el refugio', "Información de las caminatas: $site/#trekkings"],
        ['maps', 'Mapas y distancias', "Encontrá mapas en $site y https://www.barilochetrekking.com. Senderos específicos: Ilón–Rocca https://barilochetrekking.com/sendero-129/ · Meiling–Rocca https://barilochetrekking.com/sendero-127/ · Paso de las Nubes https://barilochetrekking.com/sendero-77/"],
    ],
    'season' => [
        ['opening', 'Apertura de temporada', 'Normalmente apuntamos a abrir el 1 de noviembre. Del 1 al 15 de noviembre reservamos sin pago anticipado porque la nieve y el estado del sendero pueden demorar la apertura.'],
        ['closing', 'Cierre del refugio', 'Habitualmente intentamos llegar hasta el 3 de mayo, si las nevadas lo permiten. Del 15 de abril al 3 de mayo reservamos sin pago anticipado porque el cierre puede adelantarse. Avisaremos con anticipación en la web y las redes.'],
    ],
    'weather' => [
        ['equipment', 'Cómo equiparme', "Pronóstico: $windguru\nCon lluvia, llevá buen calzado, cubremochila y protegé el contenido con una bolsa gruesa. Llevá muda de ropa, campera y cubrepantalón impermeables, dos pares de guantes, gorro y calzado de trekking. Vestite en capas: una fina, otra abrigada y una exterior impermeable y cortaviento."],
        ['rain', '¿Puedo subir con lluvia?', 'Es posible, pero depende de las condiciones, tu equipo, experiencia y conocimiento. Si está abierto, el refugio siempre te esperará para protegerte y brindarte calor.'],
        ['recommendations', 'Recomendaciones', "Consultá todas las recomendaciones en $site/#recomendaciones"],
    ],
    'missing' => [
        ['missing_person', 'Persona demorada', 'En la zona no hay señal y el cerro Tronador está a 80 km de Bariloche. Ya sea que regresen por tierra o desde Puerto Blest, normalmente llegan después de las 20 y recién entonces recuperan señal. Si necesitás orientación urgente, contactá al WhatsApp oficial del refugio.'],
    ],
    'claims' => [
        ['booking_claim', 'Por reservas', "POLÍTICA DE CAMBIOS O MODIFICACIÓN DE LA RESERVA\nEN NINGÚN CASO EL PAGO POR LOS SERVICIOS CONTRATADOS TIENE DEVOLUCIÓN.\nPodrás cambiar la fecha del voucher avisando al menos tres días antes y reprogramar dentro de la misma temporada, sujeto a disponibilidad. El pago no es transferible. Si Parques Nacionales cierra fortuitamente el acceso sur, podrá dejarse abierta la reprogramación para futuras temporadas. Escribí al correo oficial publicado en la web, con asunto CIERRE FORTUITO, e indicá nombre completo, DNI, fecha y código de reserva."],
        ['missing_voucher', 'Pagué y no recibí voucher', 'En breve una persona se comunicará con vos.'],
        ['other_claim', 'Otros reclamos', 'Escribí al correo oficial publicado en https://www.refugioagostinorocca.com e indicá en el asunto tu solicitud. Incluí nombre completo, DNI, fecha y código de reserva, y número de habitación si te alojaste.'],
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
