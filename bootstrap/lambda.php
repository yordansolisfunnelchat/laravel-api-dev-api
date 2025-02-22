<?php

/*
|--------------------------------------------------------------------------
| Lambda Bootstrap
|--------------------------------------------------------------------------
|
| Este archivo se ejecuta cuando la aplicación arranca en Lambda.
| Aquí configuramos los directorios temporales y realizamos
| otras inicializaciones necesarias.
*/

// Aseguramos que los directorios temporales existan
$storagePath = '/tmp/storage';
$directories = [
    $storagePath,
    "$storagePath/framework/views",
    "$storagePath/framework/cache",
    "$storagePath/framework/sessions",
    "$storagePath/logs",
    "$storagePath/app/public",
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Configurar permisos para storage
chmod($storagePath, 0755);

// Crear enlace simbólico para storage público si no existe
$publicStoragePath = '/tmp/storage/app/public';
$publicPath = base_path('public/storage');

if (!file_exists($publicPath)) {
    symlink($publicStoragePath, $publicPath);
}

// Limpiar archivos antiguos del storage temporal
$files = glob('/tmp/storage/framework/views/*');
$now = time();
foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 3600) { // Archivos más antiguos de 1 hora
            unlink($file);
        }
    }
}