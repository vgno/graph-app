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
    'neo4j' => [
        'transport' => 'vg-neo4j-01',
        'port' => 7474,
    ],
    'cors' => [
        'allowOrigin' => implode(' ', [
            'https://publish-stage.vgnett.no',
            'https://publish.vg.no',
            'https://kribrabr.vgnett.no'
        ])
    ],
];
