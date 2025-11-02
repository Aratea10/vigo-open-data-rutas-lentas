<?php
/*
Vigo Open Data - Movilidad → rutas más saturadas
- Consulta el endpoint JSON del catálogo.
- Determina la ruta más lenta:
  * Preferencia: menor velocidad media (avg_speed/velocidad_media/vel_media).
  * Fallback: mayor duración (avg_duration/duracion_media/tiempo_medio/duration).
- Imprime por pantalla.
- Registra en log.txt la ruta más lenta con timestamp.
*/

function http_get_json(string $url): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException("Error cURL: $err");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("HTTP $code al llamar a $url");
    }

    $data = json_decode($body, true);
    if ($data === null) {
        throw new RuntimeException("Respuesta no es JSON válido");
    }
    return $data;
}

function extract_metrics(array $item): array
{
    $id = $item['id'] ?? $item['_id'] ?? $item['route_id'] ?? $item['id_ruta'] ?? null;
    $name = $item['name'] ?? $item['ruta'] ?? $item['descripcion'] ?? $item['description'] ?? "ruta_$id";

    $speedKeys = ['avg_speed', 'velocidad_media', 'vel_media', 'speed', 'media_speed'];
    $speed = null;
    foreach ($speedKeys as $k) {
        if (isset($item[$k]) && is_numeric($item[$k])) {
            $speed = floatval($item[$k]);
            break;
        }
    }

    $durKeys = ['avg_duration', 'duracion_media', 'tiempo_medio', 'duration', 'duracion'];
    $duration = null;
    foreach ($durKeys as $k) {
        if (isset($item[$k]) && is_numeric($item[$k])) {
            $duration = floatval($item[$k]);
            break;
        }
    }

    return ['id' => $id, 'name' => $name, 'speed' => $speed, 'duration' => $duration];
}

function pick_slowest(array $rows): array
{
    $withSpeed = array_values(array_filter($rows, fn($r) => $r['speed'] !== null));
    if ($withSpeed) {
        usort($withSpeed, fn($a, $b) => $a['speed'] <=> $b['speed']);
        return $withSpeed[0];
    }
    $withDur = array_values(array_filter($rows, fn($r) => $r['duration'] !== null));
    if ($withDur) {
        usort($withDur, fn($a, $b) => $b['duration'] <=> $a['duration']);
        return $withDur[0];
    }
    throw new RuntimeException("No hay campos de velocidad ni de duración en el dataset.");
}

function append_log(string $line, string $file = 'log.txt'): void
{
    $ts = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('c');
    file_put_contents($file, "[$ts] $line\n", FILE_APPEND | LOCK_EX);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$API_URL = 'https://datos.vigo.org/data/trafico/treal_congestion.json';

$error = null;
$result = null;

try {
    if (!$API_URL) {
        throw new InvalidArgumentException("La URL de la API no está configurada.");
    }

    $data = http_get_json($API_URL);

    $records = $data;
    if (isset($data['result']['records']) && is_array($data['result']['records'])) $records = $data['result']['records'];
    elseif (isset($data['records']) && is_array($data['records'])) $records = $data['records'];
    elseif (isset($data['data']) && is_array($data['data'])) $records = $data['data'];

    if (!is_array($records) || !$records) {
        throw new RuntimeException("No se encontraron registros en la respuesta de la API.");
    }

    $rows = array_map('extract_metrics', $records);
    $slowest = pick_slowest($rows);

    $metricStr = $slowest['speed'] !== null
        ? ("speed=" . $slowest['speed'])
        : ("duration=" . $slowest['duration']);
    $line = sprintf(
        "id=%s | name=%s | %s",
        strval($slowest['id']),
        strval($slowest['name']),
        $metricStr
    );
    append_log($line);

    $result = ['slowest' => $slowest, 'logged' => $line];
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Vigo — Ruta más lenta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui;
            margin: 2rem
        }

        code {
            background: #f6f6f6;
            padding: .15rem .35rem;
            border-radius: .25rem
        }

        .card {
            border: 1px solid #e5e5e5;
            border-radius: .5rem;
            padding: 1rem;
            max-width: 680px
        }
    </style>
</head>

<body>
    <h1>Vigo — Ruta más lenta</h1>
    <?php if ($error): ?>
        <p style="color:#c00"><strong>Error:</strong> <?= h($error) ?></p>
    <?php else: ?>
        <div class="card">
            <h2>Resultado</h2>
            <p><strong>Ruta más lenta:</strong></p>
            <ul>
                <li><strong>ID:</strong> <?= h(strval($result['slowest']['id'])) ?></li>
                <li><strong>Nombre:</strong> <?= h(strval($result['slowest']['name'])) ?></li>
                <li><strong>Métrica:</strong>
                    <?php if ($result['slowest']['speed'] !== null): ?>
                        velocidad = <?= h((string)$result['slowest']['speed']) ?> (menor es más lenta)
                    <?php else: ?>
                        duración = <?= h((string)$result['slowest']['duration']) ?> (mayor es más lenta)
                    <?php endif; ?>
                </li>
            </ul>
            <p><em>Registrado en</em> <code>log.txt</code>: <?= h($result['logged']) ?></p>
        </div>
    <?php endif; ?>
</body>

</html>