<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración del bot</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f7f7f3;color:#1d1d1b}.wrap{max-width:860px;margin:0 auto;padding:40px 20px}.card{background:#fff;border-radius:20px;box-shadow:0 16px 45px #00000014;padding:32px}.eyebrow{color:#6f6a55;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}h1{font-size:34px;line-height:1.1;margin:8px 0 12px}.intro{color:#5d5a4d;line-height:1.6;max-width:680px}.field{border-top:1px solid #ece7d9;margin-top:26px;padding-top:26px}label{display:block;font-weight:750;margin-bottom:8px}.hint{color:#706d61;font-size:14px;line-height:1.5;margin:6px 0 0}.options{display:grid;gap:12px;margin-top:14px}.option{border:1px solid #ded7c5;border-radius:14px;padding:16px;display:flex;gap:12px;align-items:flex-start}.option input{margin-top:4px}textarea{width:100%;box-sizing:border-box;border:1px solid #cbc3ae;border-radius:14px;padding:14px;min-height:150px;resize:vertical}.checkbox{display:flex;gap:12px;align-items:flex-start}.actions{display:flex;gap:12px;align-items:center;margin-top:28px}button{background:#2f5d50;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;cursor:pointer}.link{color:#2f5d50;text-decoration:none;font-weight:700}.alert{background:#e6f4ea;color:#24533f;border:1px solid #b8dfc7;border-radius:14px;padding:14px;margin:20px 0}.error{color:#9b1c1c;font-size:14px;margin-top:8px}.summary{background:#fbfaf6;border:1px solid #ece7d9;border-radius:16px;padding:16px;margin-top:22px}.summary strong{display:block;margin-bottom:6px}
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <p class="eyebrow">WhatsApp</p>
            <h1>Configuración del bot</h1>
            <p class="intro">Definí cómo responderá el bot a los mensajes entrantes. Por defecto se priorizan conversaciones de clientes directos y se ignoran grupos de WhatsApp.</p>

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
                            <span><strong>Responder con el bot</strong><br><span class="hint">Usa la base de conocimiento y el modelo configurado para generar la respuesta.</span></span>
                        </label>
                        <label class="option">
                            <input type="radio" name="response_mode" value="default" @checked(old('response_mode', $settings['response_mode']) === 'default')>
                            <span><strong>Responder con mensaje por defecto</strong><br><span class="hint">Evita que responda la IA y envía siempre el texto configurado abajo.</span></span>
                        </label>
                    </div>
                    @error('response_mode')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="default_message">Mensaje por defecto</label>
                    <textarea id="default_message" name="default_message" required>{{ old('default_message', $settings['default_message']) }}</textarea>
                    <p class="hint">Este texto se enviará cuando selecciones “Responder con mensaje por defecto”.</p>
                    @error('default_message')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="respond_to_groups" value="1" @checked(old('respond_to_groups', $settings['respond_to_groups']))>
                        <span><strong>Responder también en grupos de WhatsApp</strong><br><span class="hint">Dejalo desactivado para responder solo a mensajes directos de clientes.</span></span>
                    </label>
                    @error('respond_to_groups')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="summary">
                    <strong>Estado actual</strong>
                    Modo: {{ $settings['response_mode'] === 'default' ? 'mensaje por defecto' : 'bot' }} · Grupos: {{ $settings['respond_to_groups'] ? 'habilitados' : 'ignorados' }}
                </div>

                <div class="actions">
                    <button type="submit">Guardar configuración</button>
                    <a class="link" href="{{ route('whatsapp.qr') }}">Ver QR de WhatsApp</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
