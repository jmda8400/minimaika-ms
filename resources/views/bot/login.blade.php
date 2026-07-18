<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar a configuración del bot</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#111827;color:#e5e7eb}.card{width:min(100% - 40px,400px);box-sizing:border-box;background:#1f2937;border:1px solid #374151;border-radius:20px;box-shadow:0 16px 45px #00000052;padding:32px}.eyebrow{color:#9ca3af;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}h1{font-size:30px;line-height:1.15;margin:8px 0 12px}.hint{color:#cbd5e1;font-size:14px;line-height:1.5;margin:0 0 24px}label{display:block;font-weight:750;margin:18px 0 8px}input{width:100%;box-sizing:border-box;background:#111827;color:#e5e7eb;border:1px solid #4b5563;border-radius:12px;padding:12px}input:focus{outline:2px solid #6b7280;outline-offset:2px}.error{background:#45212a;color:#fecaca;border:1px solid #7f1d1d;border-radius:12px;padding:12px;margin-bottom:16px;font-size:14px}button{width:100%;margin-top:26px;background:#4b5563;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;cursor:pointer}button:hover{background:#6b7280}
    </style>
</head>
<body>
    <main class="card">
        <p class="eyebrow">WhatsApp</p>
        <h1>Configuración del bot</h1>
        <p class="hint">Ingresá tus credenciales para administrar el bot de WhatsApp.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('bot.settings.login.store') }}">
            @csrf
            <label for="username">Usuario</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" required autofocus>

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">Ingresar</button>
        </form>
    </main>
</body>
</html>
