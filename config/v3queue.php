
<?php
#queue.php
// config/queue.php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        
        // 'redis' => [
        //     'driver' => 'redis',
        //     'connection' => 'default',
        //     'queue' => env('REDIS_QUEUE', 'default'),
        //     'retry_after' => 90,
        //     'block_for' => null,
        // ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];