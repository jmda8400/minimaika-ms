<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración del bot</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#111827;color:#e5e7eb}.wrap{max-width:860px;margin:0 auto;padding:40px 20px}.card{background:#1f2937;border:1px solid #374151;border-radius:20px;box-shadow:0 16px 45px #00000052;padding:32px}.eyebrow{color:#9ca3af;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}h1{font-size:34px;line-height:1.1;margin:8px 0 12px}.field{border-top:1px solid #374151;margin-top:26px;padding-top:26px}label{display:block;font-weight:750;margin-bottom:8px}.hint{color:#cbd5e1;font-size:14px;line-height:1.5;margin:6px 0 0}.options{display:grid;gap:12px;margin-top:14px}.option{background:#111827;border:1px solid #4b5563;border-radius:14px;padding:16px;display:flex;gap:12px;align-items:flex-start}.option input{margin-top:4px}textarea,input[type=text]{width:100%;box-sizing:border-box;background:#111827;color:#e5e7eb;border:1px solid #4b5563;border-radius:14px;padding:14px}textarea{min-height:150px;resize:vertical}textarea:focus,input[type=text]:focus{outline:2px solid #6b7280;outline-offset:2px}.checkbox{display:flex;gap:12px;align-items:flex-start}.actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:28px}button{background:#4b5563;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;cursor:pointer}button:hover{background:#6b7280}.button-danger{background:#7f1d1d}.button-danger:hover{background:#991b1b}.button-link{display:inline-block;background:#4b5563;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;text-decoration:none}.button-link:hover{background:#6b7280}.link{color:#d1d5db;text-decoration:none;font-weight:700}.alert{background:#263244;color:#e5e7eb;border:1px solid #4b5563;border-radius:14px;padding:14px;margin:20px 0}.error{color:#fca5a5;font-size:14px;margin-top:8px}.summary{background:#111827;border:1px solid #374151;border-radius:16px;padding:16px;margin-top:22px}.summary strong{display:block;margin-bottom:6px}.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.status-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:#374151;color:#f3f4f6;padding:8px 12px;font-weight:800}.status-dot{width:9px;height:9px;border-radius:50%;background:#6ee7b7}.status-dot.offline{background:#f59e0b}.qr{max-width:300px;width:100%;height:auto;display:block;margin:16px 0}.code{box-sizing:border-box;max-width:100%;font:700 22px ui-monospace,Menlo,monospace;letter-spacing:2px;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;background:#0f172a;border:1px solid #374151;border-radius:12px;padding:12px;color:#f8fafc}.json{background:#0f172a;color:#f7fafc;border:1px solid #374151;border-radius:14px;overflow:auto;padding:14px;white-space:pre-wrap}.inline-form{display:inline}.top-actions{display:flex;gap:12px;align-items:center;margin-bottom:26px;padding-bottom:26px;border-bottom:1px solid #374151}.top-actions .button-link,.top-actions button{white-space:nowrap}
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="top-actions">
                <a class="button-link" href="https://www.refugioagostinorocca.com/admin">Volver a Administracion</a>
                <a class="button-link" href="{{ route('bot.settings.edit') }}">Actualizar estado</a>
                <form class="inline-form" method="post" action="{{ route('bot.settings.logout') }}">
                    @csrf
                    <button type="submit">Cerrar sesión</button>
                </form>
            </div>

            <p class="eyebrow">WhatsApp</p>
            <h1>Configuración del bot</h1>
            <div class="summary">
                <strong>Estado de WhatsApp</strong>
                <div class="status-grid">
                    <span class="status-pill"><span class="status-dot {{ $whatsApp['connected'] ? '' : 'offline' }}"></span>{{ $whatsApp['label'] }}</span>
                    <span class="hint">{{ $whatsApp['detail'] }}</span>
                </div>

                @if (! $whatsApp['connected'])
                    <div class="field">
                        <strong>Conectar con QR</strong>
                        @if ($whatsApp['qr']['image'] !== '')
                            <img class="qr" src="{{ $whatsApp['qr']['image'] }}" alt="Código QR de WhatsApp">
                        @else
                            <p class="hint">{{ $whatsApp['qr']['error'] ?? 'El gateway todavía no devolvió una imagen QR.' }}</p>
                        @endif
                        @if ($whatsApp['qr']['code'] !== '')
                            <p class="hint">Código:</p>
                            <p class="code">{{ $whatsApp['qr']['code'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="actions">
                    <form class="inline-form" method="post" action="{{ route('bot.settings.forget-session') }}">
                        @csrf
                        <button class="button-danger" type="submit">Olvidar sesión y pedir QR nuevo</button>
                    </form>
                </div>

                <div class="field">
                    <strong>Respuesta de /whatsapp/status</strong>
                    <pre class="json">{{ json_encode($whatsApp['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>

            @if (session('status'))
                <div class="alert">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('bot.settings.update') }}">
                @csrf
                @method('put')

                <div class="field">
                    <label>Modo de respuesta</label>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="response_mode" value="bot" @checked(old('response_mode', $settings['response_mode']) === 'bot')>
                            <span><strong>Responder con el bot</strong></span>
                        </label>
                        <label class="option">
                            <input type="radio" name="response_mode" value="default" @checked(old('response_mode', $settings['response_mode']) === 'default')>
                            <span><strong>Responder con mensaje por defecto</strong></span>
                        </label>
                    </div>
                    @error('response_mode')<div class="error">{{ $message }}</div>@enderror
                </div>


                <div class="field">
                    <label for="default_message">Mensaje por defecto</label>
                    <textarea id="default_message" name="default_message" required>{{ old('default_message', $settings['default_message']) }}</textarea>
                    @error('default_message')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="respond_to_groups" value="1" @checked(old('respond_to_groups', $settings['respond_to_groups']))>
                        <span><strong>Responder también en grupos de WhatsApp</strong></span>
                    </label>
                    @error('respond_to_groups')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="notify_on_fallback" value="1" @checked(old('notify_on_fallback', $settings['notify_on_fallback']))>
                        <span><strong>Reenviar mensajes que el bot no pudo interpretar</strong><span class="hint">Cuando el bot no pueda identificar la intención o el significado de un mensaje, se enviará una alerta al número indicado.</span></span>
                    </label>
                    @error('notify_on_fallback')<div class="error">{{ $message }}</div>@enderror

                    <div style="margin-top:16px">
                        <label for="fallback_alert_phone">Número que recibe las alertas</label>
                        <input id="fallback_alert_phone" name="fallback_alert_phone" type="text" value="{{ old('fallback_alert_phone', $settings['fallback_alert_phone']) }}" placeholder="+54 2944360712">
                        <p class="hint">La alerta dirá: “No he podido descifrar la intencion del siguiente mensaje:” y luego incluirá el mensaje del cliente.</p>
                        @error('fallback_alert_phone')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="summary">
                    <strong>Estado actual</strong>
                    Modo: {{ $settings['response_mode'] === 'default' ? 'mensaje por defecto' : 'bot' }} · Respuestas: predefinidas, con encauzamiento automático cuando haga falta · Grupos: {{ $settings['respond_to_groups'] ? 'habilitados' : 'ignorados' }}
                </div>

                <div class="actions">
                    <button type="submit">Guardar configuración</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
