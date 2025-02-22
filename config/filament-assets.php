<?php

return [
    'asset_disk' => 's3',
    'storage' => [
        'disk' => 's3',
        'prefix' => 'filament',
    ],
    'temporary_files' => [
        'disk' => 's3',
        'directory' => 'tmp',
    ],
];