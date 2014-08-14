<?php

return [
    'silex' => [
        'debug' => true
    ],
    'memcache' => [
        'hosts' => [
            ['memcached01.int.vgnett.no', 11211]
        ]
    ],
    'cors' => [
        'allowOrigin' => 'https://publish-stage.vgnett.no',
        'allowOrigin' => 'https://publish.vg.no',
    ],
];