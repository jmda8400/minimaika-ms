# Integración WhatsApp Web con QR

Esta integración evita agregar un puente Node.js dentro de Laravel. Laravel se conecta por HTTP a un gateway compatible con WhatsApp Web por QR (por ejemplo, Evolution API u otro servicio equivalente) y expone endpoints públicos bajo la raíz:

- `https://bot.refugioagostinorocca.com/whatsapp/qr`
- `https://bot.refugioagostinorocca.com/whatsapp/status`
- `https://bot.refugioagostinorocca.com/whatsapp/webhook`

## Variables de entorno

```env
WHATSAPP_WEB_BASE_URL=https://gateway.example.com
WHATSAPP_WEB_API_KEY=secret
WHATSAPP_WEB_INSTANCE=refugio-agostino-rocca
WHATSAPP_WEB_WEBHOOK_SECRET=otro-secret-opcional
WHATSAPP_WEB_TIMEOUT=15
```

`WHATSAPP_WEB_WEBHOOK_SECRET` es opcional. Si se configura, el gateway debe enviar el mismo valor en el header `X-Webhook-Secret` cuando llame a `/whatsapp/webhook`.

## Flujo de conexión

1. Configurar el gateway con la instancia definida en `WHATSAPP_WEB_INSTANCE`.
2. Configurar en el gateway el webhook público:
   `https://bot.refugioagostinorocca.com/whatsapp/webhook`.
3. Abrir `https://bot.refugioagostinorocca.com/whatsapp/qr` en el navegador.
4. Escanear el QR desde WhatsApp en el celular, entrando a **Dispositivos vinculados**.
5. Revisar `https://bot.refugioagostinorocca.com/whatsapp/status` para confirmar el estado de conexión.

Cuando llega un mensaje entrante, Laravel extrae el texto del payload, llama a `App\Services\Rag\RagBotService` y responde por el gateway usando el número remoto.

## Notas

- Esta opción usa una conexión tipo WhatsApp Web por QR, no la WhatsApp Business Cloud API oficial.
- Para producción comercial, la opción más robusta sigue siendo WhatsApp Business Cloud API.
- El gateway debe exponer endpoints compatibles con:
  - `GET /instance/connect/{instance}` para obtener QR.
  - `GET /instance/connectionState/{instance}` para ver estado.
  - `POST /message/sendText/{instance}` para enviar mensajes.
