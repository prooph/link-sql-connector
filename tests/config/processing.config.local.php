<?php
return [
    'system_config_dir' => __DIR__,
    'processing' => [
        'node_name' => 'localhost',
        'plugins' => [],
        'processes' => [],
        'channels' => [
            'local' => [
                'targets' => [
                    0 => 'localhost',
                ],
                'utils' => [],
            ],
        ],
        'connectors' => [
            'sqlconnector:::processing_test_users' => [
                'name' => 'Processing Test Source DB',
                'allowed_messages' => [
                    0 => 'collect-data',
                ],
                'allowed_types' => [
                    0 => 'ProophTest\Link\SqlConnector\DataType\TestUser',
                    1 => 'ProophTest\Link\SqlConnector\DataType\TestUserCollection',
                ],
                'metadata' => [
                    'identifier' => true,
                ],
                'dbal_connection' => 'processing_test_source',
                'table' => 'users',
                'ui_metadata_key' => 'SqlconnectorMetadata',
            ],
        ]
    ],
    'prooph.link.sqlconnector' => [
        'connections' => [
            'processing_test_source' => [
                'driver' => 'pdo_sqlite',
                'memory' => true
            ]
        ]
    ]
];