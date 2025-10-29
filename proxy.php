<?php
// proxy.php
// Proxy para PWS / Observations com cache simples
// CONFIGURE AQUI
$PROVIDER = 'weathercom'; // 'weathercom' ou 'openweathermap' - escolha seu provedor abaixo
$API_KEY   = 'YOUR_API_KEY'; // <- substitua pela sua chave
$API_UNITS = 'm';            // 'm' para métrico (se aplicável)
$cacheDir  = __DIR__ . '/cache';
$cacheTtl  = 55; // segundos

// sanitize station parameter
$station = isset($_GET['station']) ? preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['station']) : '';
if (!$station) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Parâmetro station obrigatório. Ex: ?station=ISOPED25']);
    exit;
}

// create cache dir if needed
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . $station . '.json';

// serve cache if fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    readfile($cacheFile);
    exit;
}

// build request depending on provider
if ($PROVIDER === 'weathercom') {
    // The Weather Company / weather.com PWS endpoint
    $API_BASE = 'https://api.weather.com/v2/pws/observations/current';
    $query = http_build_query([
        'stationId' => $station,
        'format'    => 'json',
        'units'     => $API_UNITS,
        'apiKey'    => $API_KEY
    ]);
    $url = $API_BASE . '?' . $query;
} elseif ($PROVIDER === 'openweathermap') {
    // OpenWeatherMap: aqui precisamos de lat/lon do PWS; tentamos usar station como "lat,lon" (ex: -22.54896,-47.91417)
    // Se você tiver chave do OWM e quer esse provedor, envie station como lat,lon ou substitua a lógica
    $coords = explode(',', $station);
    if (count($coords) !== 2) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Para OpenWeatherMap, use station como "lat,lon" (ex: ?station=-22.54896,-47.91417)']);
        exit;
    }
    $lat = trim($coords[0]);
    $lon = trim($coords[1]);
    $API_BASE = 'https://api.openweathermap.org/data/2.5/weather';
    $query = http_build_query([
        'lat' => $lat,
        'lon' => $lon,
        'appid' => $API_KEY
    ]);
    $url = $API_BASE . '?' . $query;
} else {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Provider inválido no proxy. Configure $PROVIDER.']);
    exit;
}

// fetch with cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || !$response) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $err = ['error' => 'Erro ao conectar à API externa', 'detail' => $curlErr];
    echo json_encode($err);
    exit;
}

$data = json_decode($response, true);
if ($data === null) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['error' => 'Resposta inválida da API externa', 'raw' => substr($response, 0, 2000)]);
    exit;
}

// Normaliza para { observations: [ ... ] }
$out = [];
if ($PROVIDER === 'weathercom') {
    if (isset($data['observations']) && is_array($data['observations'])) {
        $out['observations'] = $data['observations'];
    } else {
        $out['observations'] = [$data];
    }
} elseif ($PROVIDER === 'openweathermap') {
    // Mapa simples para OWM -> obs formato aproximado para frontend entender
    $obs = [];
    $obs['obsTimeLocal'] = isset($data['dt']) ? date('c', $data['dt']) : null;
    $obs['humidity'] = $data['main']['humidity'] ?? null;
    $obs['metric'] = [
        'temp' => isset($data['main']['temp']) ? $data['main']['temp'] - 273.15 : null, // K -> C
        'windSpeed' => isset($data['wind']['speed']) ? $data['wind']['speed'] * 3.6 : null, // m/s -> km/h
        'precipTotal' => null,
        'pressure' => $data['main']['pressure'] ?? null
    ];
    $obs['winddir'] = $data['wind']['deg'] ?? null;
    $out['observations'] = [$obs];
}

// save cache on success
if ($httpCode >= 200 && $httpCode < 300) {
    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

// return
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo json_encode($out);

