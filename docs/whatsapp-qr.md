# Integración WhatsApp Web con QR

Esta integración evita agregar un puente Node.js dentro de Laravel. Laravel se conecta por HTTP a un gateway compatible con WhatsApp Web por QR (por ejemplo, Evolution API u otro servicio equivalente) y expone endpoints bajo la URL configurada en `APP_URL`.

Para probar primero en local, usar:

```env
APP_URL=http://localhost:8001
```

Con esa URL, los endpoints quedan:

- `http://localhost:8001/whatsapp/settings`
- `http://localhost:8001/whatsapp/status`
- `http://localhost:8001/whatsapp/webhook`

En producción, al cambiar `APP_URL=https://bot.refugioagostinorocca.com`, las mismas rutas pasan a:

- `https://bot.refugioagostinorocca.com/whatsapp/settings`
- `https://bot.refugioagostinorocca.com/whatsapp/status`
- `https://bot.refugioagostinorocca.com/whatsapp/webhook`

## Variables de entorno

```env
APP_URL=http://localhost:8001
WHATSAPP_WEB_BASE_URL=http://localhost:8080
WHATSAPP_WEB_API_KEY=secret
WHATSAPP_WEB_INSTANCE=refugio-agostino-rocca
WHATSAPP_WEB_WEBHOOK_SECRET=otro-secret-opcional
WHATSAPP_WEB_TIMEOUT=15
WHATSAPP_WEB_MENU_STRATEGY=text
```

`WHATSAPP_WEB_WEBHOOK_SECRET` es opcional. Si se configura, el gateway debe enviar el mismo valor en el header `X-Webhook-Secret` cuando llame a `/whatsapp/webhook`.

## Flujo local

1. Levantar Laravel en Docker Compose; el servicio publica la app en `http://localhost:8001`.
2. Levantar o configurar el gateway compatible con WhatsApp Web por QR y apuntar `WHATSAPP_WEB_BASE_URL` a su URL HTTP.
3. Configurar en el gateway el webhook local:
   `http://localhost:8001/whatsapp/webhook`.
4. Abrir `http://localhost:8001/whatsapp/settings` en el navegador para ver la configuración y el QR.
5. Escanear el QR desde WhatsApp en el celular, entrando a **Dispositivos vinculados**.
6. Revisar `http://localhost:8001/whatsapp/status` para confirmar el estado de conexión.

Cuando llega un mensaje entrante, Laravel extrae la selección, recorre el árbol determinístico y responde con texto aprobado y menús numerados. El usuario puede responder `1`, `2`, `opción 2` o `2 - Servicios`.

`WHATSAPP_WEB_MENU_STRATEGY=text` es el valor predeterminado y recomendado. Los menús numerados funcionan sin depender del protocolo interactivo privado de WhatsApp. `buttons` habilita experimentalmente los botones de respuesta y conserva el menú textual como fallback cuando el gateway devuelve un error. Una respuesta HTTP exitosa del gateway no garantiza que WhatsApp renderice el botón correctamente.

## Nota sobre webhooks locales

`http://localhost:8001/whatsapp/webhook` sirve si el gateway corre en la misma máquina o tiene acceso a ese host. Si el gateway corre fuera de tu computadora, no va a poder llamar a `localhost`; en ese caso hay que usar una URL pública temporal, por ejemplo un túnel HTTPS, y poner esa URL en `APP_URL` mientras dure la prueba.

## Notas

- Esta opción usa una conexión tipo WhatsApp Web por QR, no la WhatsApp Business Cloud API oficial.
- Para producción comercial, la opción más robusta sigue siendo WhatsApp Business Cloud API.
- El gateway debe exponer endpoints compatibles con:
  - `GET /instance/connect/{instance}` para obtener QR.
  - `GET /instance/connectionState/{instance}` para ver estado.
  - `POST /message/sendText/{instance}` para enviar mensajes.
  - `POST /message/sendButtons/{instance}` solo si se configura la estrategia experimental `buttons`. Como WhatsApp admite hasta tres botones de respuesta por mensaje, los menús largos se dividen en grupos de tres. Si el gateway o su versión rechazan este endpoint, el bot registra el error y envía automáticamente el mismo menú como una lista numerada.

Las selecciones numéricas quedan asociadas durante dos horas al último menú enviado a cada número. De este modo el fallback sigue recorriendo exactamente el mismo árbol, sin clasificación de texto libre. Después de un rechazo del gateway, el bot evita nuevos intentos interactivos durante diez minutos. Los menús de más de diez filas usan directamente el formato numerado para respetar el límite habitual de WhatsApp.

## Evaluación de gateways alternativos

No se recomienda desplegar una imagen personalizada solamente porque los botones parezcan funcionar. Antes de cambiar el gateway:

1. Verificar que el repositorio, el Dockerfile y la licencia sean auditables.
2. Fijar una versión inmutable o un digest de imagen; no usar tags móviles en producción.
3. Revisar el historial de mantenimiento, las actualizaciones de seguridad y el procedimiento de rollback.
4. Probar envío, visualización y respuesta en Android, iPhone, WhatsApp Web y WhatsApp Desktop. Un HTTP 200 solo confirma que el gateway aceptó el pedido.
5. Mantener `WHATSAPP_WEB_MENU_STRATEGY=text` disponible como contingencia.

Las implementaciones por QR —incluidas variantes de Evolution API o gateways escritos en Go— dependen de protocolos no oficiales que pueden cambiar. Para una operación comercial con garantías contractuales, debe evaluarse la API oficial de WhatsApp; si no se desea usarla, el menú numerado es la alternativa más estable.
