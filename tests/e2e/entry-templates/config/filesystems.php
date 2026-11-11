<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private')],
        'uploads' => ['driver' => 'local', 'root' => public_path('uploads'), 'url' => '/uploads', 'visibility' => 'public'],
    ],
];
