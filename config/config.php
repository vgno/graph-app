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
        'allowOrigin' => implode(' ', [
            'https://publish-stage.vgnett.no',
            'https://publish.vg.no',
            'https://kribrabr.vgnett.no'
        ])
    ],
];