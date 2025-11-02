# Vigo Open Data — Ruta más lenta (Movilidad)
Script PHP que consulta el dataset "Movilidad → rutas más saturadas" del catálogo de datos abiertos de Vigo, determina la ruta más lenta e imprime el resultado. Además, guarda la ruta más lenta en `log.txt` en cada consulta.

## Requisitos
- PHP 8.x con cURL habilitado

## Configuración
- Si el API requiere clave, crea `.env` con:
  VIGO_API_URL=https://<URL_DEL_ENDPOINT_JSON_O_API>
  VIGO_API_KEY=<tu_clave_si_aplica>

Si no hay clave, basta con definir `VIGO_API_URL`.

## Ejecutar
```bash
php -S localhost:8000
```

Navega a http:/localhost:8000/index.php

## Nota sobre el endpoint
El script acepta respuestas JSON con campos de velocidad/duración habituales. Detecta automáticamente:
- Velocidad media: `avg_speed`, `velocidad_media`, `tiempo_medio`, `duration`
- Duración media: `avg_duration`, `duracion_media`, `tiempo_medio`, `duration`

La "ruta más lenta" se define como:
- La de menor velocidad media si existe ese dato 
- En su defecto, la de mayor duración

Cada ejecución añade una línea a `log.txt`con marca de tiempo, identificador y métrica.