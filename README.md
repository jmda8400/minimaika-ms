# minimaika-ms

Sistema en PHP + Laravel para consultas tipo RAG con base de conocimiento local.

## Objetivo de esta etapa

Implementar un bot de terminal llamado **Minimaika MS** que:

- lea documentos locales (`.md` y `.txt`),
- divida el contenido en fragmentos,
- recupere fragmentos relevantes por coincidencia de palabras,
- responda **sin usar LLMs/SLMs/APIs externas**.

## Estructura agregada

- `app/Services/Rag/KnowledgeBaseService.php`
  - Carga archivos de `storage/knowledge`.
  - Chunquea por párrafos/oraciones.
  - Normaliza texto (minúsculas, ASCII, limpieza de símbolos).
  - Tokeniza y calcula score por coincidencia/frecuencia/cobertura.
- `app/Services/Rag/RagBotService.php`
  - Orquesta búsqueda y arma respuesta simple basada en fragmentos.
- `app/Console/Commands/MinimaikaChatCommand.php`
  - Comando interactivo: `minimaika:chat`.
- `app/Console/Commands/MinimaikaKnowledgeTestCommand.php`
  - Comando de depuración: `minimaika:knowledge-test`.
- `storage/knowledge/*.md`
  - Archivos de ejemplo para pruebas iniciales.

## Registro de comandos en Laravel

Asegurate de registrar los comandos en `app/Console/Kernel.php` (si aún no están):

```php
protected $commands = [
    \App\Console\Commands\MinimaikaChatCommand::class,
    \App\Console\Commands\MinimaikaKnowledgeTestCommand::class,
];
```

> Si tu proyecto usa auto-discovery de comandos por carpeta, este paso puede no ser necesario.

## Cómo probar en Docker

### Chat interactivo

```bash
docker compose exec app php artisan minimaika:chat
```

Salir con: `salir`, `exit` o `quit`.

### Test de recuperación

```bash
docker compose exec app php artisan minimaika:knowledge-test "¿Dónde queda el refugio?"
```

Opcional:

```bash
docker compose exec app php artisan minimaika:knowledge-test "¿Cuáles son los horarios?" --limit=5
```

## Alcance actual

- ✅ Solo terminal.
- ✅ Solo base de conocimiento local en archivos.
- ✅ Sin WhatsApp/webhooks todavía.
- ✅ Sin base de datos para índice.
- ✅ Sin embeddings externos ni modelos generativos.
