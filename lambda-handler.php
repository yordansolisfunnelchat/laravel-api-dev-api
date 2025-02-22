<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

// Función para manejar eventos de Lambda
function handler($event, $context) {
    global $kernel;
    
    // Convertir evento de API Gateway a Request de Laravel
    $method = $event['requestContext']['http']['method'] ?? 'GET';
    $path = $event['rawPath'] ?? '/';
    $headers = $event['headers'] ?? [];
    $body = $event['body'] ?? '';

    // Crear request
    $request = Request::create($path, $method, [], [], [], $headers, $body);

    try {
        $response = $kernel->handle($request);
        
        return [
            'statusCode' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $response->getContent()
        ];
    } catch (Exception $e) {
        return [
            'statusCode' => 500,
            'body' => json_encode(['error' => $e->getMessage()])
        ];
    }
}

// Si se ejecuta localmente, iniciar servidor de desarrollo
if (php_sapi_name() !== 'cli') {
    $response = $kernel->handle(Request::capture());
    $response->send();
    $kernel->terminate(Request::capture(), $response);
} else {
    // En desarrollo local, ejecutar servidor PHP
    if (isset($argv[0]) && basename($argv[0]) === 'lambda-handler.php') {
        echo "Iniciando servidor de desarrollo en http://0.0.0.0:8080\n";
        exec('php -S 0.0.0.0:8080 ' . __DIR__ . '/public/index.php');
    }
}