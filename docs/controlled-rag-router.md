# Router RAG controlado

## Flujo de recuperación y clasificación

El flujo de producción parte de `ApprovedResponseCatalog`. Cada registro tiene un ID,
un tema, una pregunta canónica, alias y una respuesta aprobada. La recuperación indexa
solamente la pregunta, el tema y cada alias como campos separados. La respuesta
aprobada no participa de la búsqueda ni se envía a Groq.

Actualmente este router no genera ni consulta embeddings externos. La recuperación
local combina cobertura y precisión de tokens normalizados, similitud de caracteres y
una bonificación por frase. Se conservan los mejores `RAG_INITIAL_TOP_K` candidatos;
si el mejor no alcanza `RAG_RETRIEVAL_MIN_SCORE`, la consulta pasa al fallback seguro.
No se exige una diferencia mínima entre el primero y el segundo: Groq recibe ambos
para resolver intenciones cercanas.

Groq recibe los IDs, temas y preguntas canónicas recuperadas, además de los controles
de aclaración y fallback. Debe devolver JSON con `action`, el campo de ID correspondiente
y `confidence`. El ID debe pertenecer al conjunto enviado, estar activo y superar
`RAG_CONFIDENCE_THRESHOLD`. Cualquier JSON inválido, ID ajeno/inactivo o confianza
baja termina en `fallback.unknown`. La respuesta al usuario siempre se obtiene del
catálogo a partir del ID validado; Groq nunca redacta la respuesta final.

## Configuración

| Variable | Valor por defecto | Función |
| --- | ---: | --- |
| `RAG_INITIAL_TOP_K` | `12` | Cantidad de candidatos recuperados antes de agregar controles. |
| `RAG_RETRIEVAL_MIN_SCORE` | `0.12` | Puntaje mínimo del mejor candidato para invocar al clasificador. |
| `RAG_CONFIDENCE_THRESHOLD` | `0.55` | Confianza mínima declarada por Groq. |
| `GROQ_MODEL` | `llama-3.1-8b-instant` | Modelo utilizado por el clasificador. |
| `GROQ_API_KEY` | — | Credencial de Groq. |
| `GROQ_BASE_URL` | API de Groq | Endpoint compatible con chat completions. |

## Observabilidad

Cada salida escribe un evento `controlled_rag_classification` con el mensaje original
y normalizado, IDs/títulos/puntajes recuperados, IDs enviados a Groq, respuesta cruda
de Groq, intención elegida, confianza y motivo de fallback. Esto permite distinguir
fallas de recuperación, de clasificación y de validación sin registrar respuestas
finales generadas (porque no existen).
