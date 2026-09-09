<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración del bot</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#111827;color:#e5e7eb}.wrap{max-width:980px;margin:0 auto;padding:40px 20px}.card{background:#1f2937;border:1px solid #374151;border-radius:20px;box-shadow:0 16px 45px #00000052;padding:32px}.eyebrow{color:#9ca3af;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}h1{font-size:34px;line-height:1.1;margin:8px 0 12px}.field{border-top:1px solid #374151;margin-top:26px;padding-top:26px}label{display:block;font-weight:750;margin-bottom:8px}.hint{color:#cbd5e1;font-size:14px;line-height:1.5;margin:6px 0 0}.options{display:grid;gap:12px;margin-top:14px}.option{background:#111827;border:1px solid #4b5563;border-radius:14px;padding:16px;display:flex;gap:12px;align-items:flex-start}.option input{margin-top:4px}textarea,input[type=text],input[type=time],select{width:100%;box-sizing:border-box;background:#111827;color:#e5e7eb;border:1px solid #4b5563;border-radius:14px;padding:14px}textarea{min-height:120px;resize:vertical}textarea:focus,input:focus,select:focus{outline:2px solid #6b7280;outline-offset:2px}.checkbox{display:flex;gap:12px;align-items:flex-start}.actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:28px}button{background:#4b5563;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;cursor:pointer}button:hover{background:#6b7280}.button-danger{background:#7f1d1d}.button-danger:hover{background:#991b1b}.button-link{display:inline-block;background:#4b5563;color:white;border:0;border-radius:999px;padding:12px 20px;font-weight:800;text-decoration:none}.button-link:hover{background:#6b7280}.link{color:#d1d5db;text-decoration:none;font-weight:700}.alert{background:#263244;color:#e5e7eb;border:1px solid #4b5563;border-radius:14px;padding:14px;margin:20px 0}.error{color:#fca5a5;font-size:14px;margin-top:8px}.summary{background:#111827;border:1px solid #374151;border-radius:16px;padding:16px;margin-top:22px}.summary strong{display:block;margin-bottom:6px}.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.status-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:#374151;color:#f3f4f6;padding:8px 12px;font-weight:800}.status-dot{width:9px;height:9px;border-radius:50%;background:#6ee7b7}.status-dot.offline{background:#f59e0b}.qr{max-width:300px;width:100%;height:auto;display:block;margin:16px 0}.code{box-sizing:border-box;max-width:100%;font:700 22px ui-monospace,Menlo,monospace;letter-spacing:2px;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;background:#0f172a;border:1px solid #374151;border-radius:12px;padding:12px;color:#f8fafc}.json{background:#0f172a;color:#f7fafc;border:1px solid #374151;border-radius:14px;overflow:auto;padding:14px;white-space:pre-wrap}.inline-form{display:inline}.top-actions{display:flex;gap:12px;align-items:center;margin-bottom:26px;padding-bottom:26px;border-bottom:1px solid #374151}.top-actions .button-link,.top-actions button{white-space:nowrap}.text-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.text-grid .wide{grid-column:1/-1}.category{background:#111827;border:1px solid #4b5563;border-radius:18px;padding:20px;margin-top:18px}.category-head,.answer-head{display:flex;gap:12px;align-items:end}.category-head>div,.answer-head>div{flex:1}.answers{display:grid;gap:14px;margin-top:16px}.answer{border-left:3px solid #4b5563;padding-left:16px}.small-button{padding:9px 14px;font-size:13px}.secondary{background:#334155}.section-title{margin:0 0 6px}.meta{display:grid;gap:5px;margin-top:14px}@media(max-width:700px){.text-grid{grid-template-columns:1fr}.category-head,.answer-head{align-items:stretch;flex-direction:column}.card{padding:22px}.top-actions{align-items:stretch;flex-direction:column}}
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
                    @if ($settings['notifications_enabled'] && $settings['notification_group_id'] !== '')
                        <form class="inline-form" method="post" action="{{ route('bot.settings.test-notification') }}">
                            @csrf
                            <button class="secondary" type="submit">Probar grupo de alertas</button>
                        </form>
                    @endif
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
                    <h2 class="section-title">Alertas administrativas</h2>
                    <p class="hint">Elegí el grupo que recibirá el estado diario del bot y los avisos por voucher o reserva. Esto no habilita respuestas automáticas dentro de grupos.</p>
                    <label class="checkbox">
                        <input type="checkbox" name="notifications_enabled" value="1" @checked(old('notifications_enabled', $settings['notifications_enabled']))>
                        <span><strong>Enviar alertas al grupo seleccionado</strong></span>
                    </label>

                    <div class="text-grid">
                        <div class="wide">
                            <label for="notification_group_id">Grupo de WhatsApp</label>
                            @if ($groups['available'] && count($groups['items']) > 0)
                                <select id="notification_group_id" name="notification_group_id">
                                    <option value="">Seleccionar un grupo</option>
                                    @if ($settings['notification_group_id'] !== '' && ! collect($groups['items'])->contains('id', $settings['notification_group_id']))
                                        <option value="{{ $settings['notification_group_id'] }}" data-name="{{ $settings['notification_group_name'] }}" selected>{{ $settings['notification_group_name'] ?: $settings['notification_group_id'] }} (guardado)</option>
                                    @endif
                                    @foreach ($groups['items'] as $group)
                                        <option value="{{ $group['id'] }}" data-name="{{ $group['name'] }}" @selected(old('notification_group_id', $settings['notification_group_id']) === $group['id'])>{{ $group['name'] }} — {{ $group['id'] }}</option>
                                    @endforeach
                                </select>
                                <p class="hint">La lista se actualiza al recargar esta página.</p>
                            @else
                                <input id="notification_group_id" type="text" name="notification_group_id" value="{{ old('notification_group_id', $settings['notification_group_id']) }}" placeholder="120363000000000000@g.us">
                                <p class="hint">{{ $groups['error'] ?? 'La cuenta vinculada no informó grupos. Ingresá el identificador terminado en @g.us.' }}</p>
                            @endif
                            <input id="notification_group_name" type="hidden" name="notification_group_name" value="{{ old('notification_group_name', $settings['notification_group_name']) }}">
                            @error('notification_group_id')<div class="error">Ingresá un identificador de grupo válido, terminado en @g.us.</div>@enderror
                        </div>
                        <div>
                            <label for="heartbeat_time">Hora del estado diario</label>
                            <input id="heartbeat_time" type="time" name="heartbeat_time" value="{{ old('heartbeat_time', $settings['heartbeat_time']) }}" required>
                        </div>
                        <div>
                            <label for="heartbeat_timezone">Zona horaria</label>
                            <select id="heartbeat_timezone" name="heartbeat_timezone" required>
                                @foreach (['America/Argentina/Buenos_Aires', 'Etc/UTC'] as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('heartbeat_timezone', $settings['heartbeat_timezone']) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="heartbeat_enabled" value="1" @checked(old('heartbeat_enabled', $settings['heartbeat_enabled']))>
                        <span><strong>Enviar confirmación diaria de sesión conectada</strong></span>
                    </label>
                    <div class="meta hint">
                        <span>Último estado enviado: {{ $notificationStatus['last_success'] ?: 'todavía no registrado' }}</span>
                        @if ($notificationStatus['last_error'])
                            <span>Último inconveniente: {{ $notificationStatus['last_error']['message'] ?? 'error no especificado' }} ({{ $notificationStatus['last_error']['at'] ?? 'sin fecha' }})</span>
                        @endif
                    </div>
                </div>

                <div class="field">
                    <h2 class="section-title">Textos generales del bot</h2>
                    <p class="hint">Editá los mensajes fijos que acompañan los menús y respuestas.</p>
                    <div class="text-grid">
                        @foreach ([
                            'welcome' => ['Mensaje de bienvenida', true],
                            'main_menu_title' => ['Título del menú principal', false],
                            'menu_button' => ['Texto del botón', false],
                            'select_prompt' => ['Indicación para elegir una opción', false],
                            'follow_up' => ['Pregunta después de una respuesta', false],
                            'back_title' => ['Opción para volver al inicio', false],
                            'back_description' => ['Descripción para volver', false],
                            'option_description' => ['Descripción de cada opción', false],
                        ] as $key => [$label, $wide])
                            <div class="{{ $wide ? 'wide' : '' }}">
                                <label for="text_{{ $key }}">{{ $label }}</label>
                                @if ($wide)
                                    <textarea id="text_{{ $key }}" name="bot_texts[{{ $key }}]" required>{{ old("bot_texts.$key", $settings['bot_texts'][$key]) }}</textarea>
                                @else
                                    <input id="text_{{ $key }}" type="text" name="bot_texts[{{ $key }}]" value="{{ old("bot_texts.$key", $settings['bot_texts'][$key]) }}" required>
                                @endif
                                @error("bot_texts.$key")<div class="error">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <h2 class="section-title">Opciones y respuestas</h2>
                    <p class="hint">Podés cambiar títulos y respuestas, sumar categorías y agregar o eliminar opciones. El orden de esta lista será el orden que verá la persona en WhatsApp.</p>
                    <div id="categories">
                        @foreach (old('navigation', $settings['navigation']) as $categoryIndex => $category)
                            <article class="category">
                                <input type="hidden" data-field="category-id" value="{{ $category['id'] ?? '' }}">
                                <div class="category-head">
                                    <div><label>Categoría</label><input type="text" data-field="category-title" value="{{ $category['title'] }}" required></div>
                                    <button class="button-danger small-button remove-category" type="button">Eliminar categoría</button>
                                </div>
                                <div class="answers">
                                    @foreach ($category['options'] as $option)
                                        <div class="answer">
                                            <input type="hidden" data-field="option-id" value="{{ $option['id'] ?? '' }}">
                                            <div class="answer-head">
                                                <div><label>Nombre de la opción</label><input type="text" data-field="option-title" value="{{ $option['title'] }}" required></div>
                                                <button class="button-danger small-button remove-option" type="button">Eliminar</button>
                                            </div>
                                            <label>Respuesta</label>
                                            <textarea data-field="option-answer" required>{{ $option['answer'] }}</textarea>
                                        </div>
                                    @endforeach
                                </div>
                                <button class="secondary small-button add-option" type="button">+ Agregar opción</button>
                            </article>
                        @endforeach
                    </div>
                    @error('navigation')<div class="error">{{ $message }}</div>@enderror
                    @error('navigation.*')<div class="error">Revisá que todas las categorías tengan al menos una opción completa.</div>@enderror
                    <div class="actions"><button class="secondary" id="add-category" type="button">+ Agregar categoría</button></div>
                </div>

                <div class="summary">
                    <strong>Estado actual</strong>
                    Modo: {{ $settings['response_mode'] === 'default' ? 'mensaje por defecto' : 'bot' }} · Navegación: árbol determinístico · Grupos: {{ $settings['respond_to_groups'] ? 'habilitados' : 'ignorados' }}
                </div>

                <div class="actions">
                    <button type="submit">Guardar configuración</button>
                </div>
            </form>
        </section>
    </main>
</body>
<script>
    const categories = document.getElementById('categories');
    const answerHtml = () => `<div class="answer"><input type="hidden" data-field="option-id" value=""><div class="answer-head"><div><label>Nombre de la opción</label><input type="text" data-field="option-title" required></div><button class="button-danger small-button remove-option" type="button">Eliminar</button></div><label>Respuesta</label><textarea data-field="option-answer" required></textarea></div>`;
    const categoryHtml = () => `<article class="category"><input type="hidden" data-field="category-id" value=""><div class="category-head"><div><label>Categoría</label><input type="text" data-field="category-title" required></div><button class="button-danger small-button remove-category" type="button">Eliminar categoría</button></div><div class="answers">${answerHtml()}</div><button class="secondary small-button add-option" type="button">+ Agregar opción</button></article>`;
    function renameFields() {
        categories.querySelectorAll('.category').forEach((category, categoryIndex) => {
            category.querySelector('[data-field="category-id"]').name = `navigation[${categoryIndex}][id]`;
            category.querySelector('[data-field="category-title"]').name = `navigation[${categoryIndex}][title]`;
            category.querySelectorAll('.answer').forEach((answer, optionIndex) => {
                answer.querySelector('[data-field="option-id"]').name = `navigation[${categoryIndex}][options][${optionIndex}][id]`;
                answer.querySelector('[data-field="option-title"]').name = `navigation[${categoryIndex}][options][${optionIndex}][title]`;
                answer.querySelector('[data-field="option-answer"]').name = `navigation[${categoryIndex}][options][${optionIndex}][answer]`;
            });
        });
    }
    document.getElementById('add-category').addEventListener('click', () => { categories.insertAdjacentHTML('beforeend', categoryHtml()); renameFields(); });
    categories.addEventListener('click', event => {
        if (event.target.matches('.add-option')) event.target.closest('.category').querySelector('.answers').insertAdjacentHTML('beforeend', answerHtml());
        if (event.target.matches('.remove-option') && event.target.closest('.answers').children.length > 1) event.target.closest('.answer').remove();
        if (event.target.matches('.remove-category') && categories.children.length > 1) event.target.closest('.category').remove();
        renameFields();
    });
    renameFields();
    const groupSelect = document.getElementById('notification_group_id');
    const groupName = document.getElementById('notification_group_name');
    if (groupSelect?.tagName === 'SELECT') {
        const syncGroupName = () => { groupName.value = groupSelect.selectedOptions[0]?.dataset.name || ''; };
        groupSelect.addEventListener('change', syncGroupName);
        syncGroupName();
    }
</script>
</html>
