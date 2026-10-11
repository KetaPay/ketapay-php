<?php

// Router for `php -S`: a tiny HTTP server the transport tests talk to over a real socket. Tests pick a
// behaviour with the base URL, so any SDK call works: baseUrl `http://127.0.0.1:port/slow/3000`.
//   /echo/...       answers with the request it got (method, path, query, headers, body) as JSON
//   /slow/N/...     waits N milliseconds, then answers 200
//   /status/N/...   answers status N with an API error body and a request id

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
header('KetaPay-Request-Id: req_local');

if (str_starts_with((string) $path, '/echo/')) {
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        $headers[strtolower($name)] = $value;
    }
    echo json_encode([
        'object' => 'echo',
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => $path,
        'query' => $_SERVER['QUERY_STRING'] ?? '',
        'headers' => $headers,
        'body' => file_get_contents('php://input'),
        // php -S parses multipart bodies itself, so report the parts it found.
        'form' => $_POST,
        'files' => array_map(static fn(array $f): array => [
            'name' => $f['name'],
            'type' => $f['type'],
            'base64' => base64_encode((string) file_get_contents($f['tmp_name'])),
        ], $_FILES),
    ]);

    return;
}
if (preg_match('#^/slow/(\d+)/#', (string) $path, $m)) {
    usleep((int) $m[1] * 1000);
    echo '{"object":"slow"}';

    return;
}
if (preg_match('#^/status/(\d{3})/#', (string) $path, $m)) {
    http_response_code((int) $m[1]);
    echo json_encode(['error' => ['type' => 'api_error', 'code' => 'local_status', 'message' => 'local', 'request_id' => 'req_local']]);

    return;
}
http_response_code(404);
echo '{"error":{"type":"invalid_request_error","code":"resource_not_found","message":"no route"}}';
