<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 06.12.14 - 22:26
 */
return array(
    'prooph.link.dashboard' => [
        'sqlconnector_config_widget' => [
            'controller' => 'Prooph\Link\SqlConnector\Controller\DashboardWidget',
            'order' => 90 //50 - 99 connectors range
        ]
    ],
    'router' => [
        'routes' => [
            'prooph.link' => [
                'child_routes' => [
                    'sql_connector' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/sql-connector',
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'configurator' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/sql-manager',
                                    'defaults' => [
                                        'controller' => 'Prooph\Link\SqlConnector\Controller\SqlManager',
                                        'action' => 'start-app'
                                    ]
                                ]
                            ],
                            'api' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/api',
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'connector' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/connectors[/:id]',
                                            'constraints' => array(
                                                'id' => '.+',
                                            ),
                                            'defaults' => [
                                                'controller' => 'Prooph\Link\SqlConnector\Api\Connector',
                                            ]
                                        ]
                                    ],
                                    'test-connection' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/test-connections[/:id]',
                                            'constraints' => array(
                                                'id' => '.+',
                                            ),
                                            'defaults' => [
                                                'controller' => 'Prooph\Link\SqlConnector\Api\TestConnection',
                                            ]
                                        ]
                                    ],
                                    'connection' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/connections[/:id]',
                                            'constraints' => array(
                                                'id' => '.+',
                                            ),
                                            'defaults' => [
                                                'controller' => 'Prooph\Link\SqlConnector\Api\Connection',
                                            ]
                                        ]
                                    ],
                                    'table' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/connections/:dbname/tables[/:name]',
                                            'constraints' => array(
                                                'dbname' => '.+',
                                                'name' => '.+',
                                            ),
                                            'defaults' => [
                                                'controller' => 'Prooph\Link\SqlConnector\Api\Table',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'prooph.link.sqlconnector' => [
        //Placeholder for configured connections. The UI creates a prooph.link.sqlconnector.local.php in config/autoload and puts
        //all connections there. The connections are aliased and only the alias is put in the metadata
        //of a connector definition. This ensures that sensitive connection params are not available in the UI except the
        //sqlconnector UI itself.
        'connections' => [],
        //Doctrine type to ProcessingType map
        'doctrine_processing_type_map' => [
            'string' => 'Prooph\Processing\Type\String',
            'text' => 'Prooph\Processing\Type\String',
            'binary' => 'Prooph\Processing\Type\String',
            'blob' => 'Prooph\Processing\Type\String',
            'guid' => 'Prooph\Processing\Type\String',
            'integer' => 'Prooph\Processing\Type\Integer',
            'smallint' => 'Prooph\Processing\Type\Integer',
            'bigint' => 'Prooph\Processing\Type\String',
            'float' => 'Prooph\Processing\Type\Float',
            'decimal' => 'Prooph\Processing\Type\Float',
            'boolean' => 'Prooph\Processing\Type\Boolean',
            'datetime' => 'Prooph\Processing\Type\DateTime',
            'datetimetz' => 'Prooph\Processing\Type\DateTime',
            'date' => 'Prooph\Processing\Type\DateTime',
            'time' => 'Prooph\Processing\Type\DateTime',
        ]
    ],
    'view_manager' => array(
        'template_map' => [
            'prooph.link.sqlconnector/dashboard/widget' => __DIR__ . '/../view/sqlconnector/dashboard/widget.phtml',
            'prooph.link.sqlconnector/partials/pm-metadata-config' => __DIR__ . '/../view/sqlconnector/partials/pm-metadata-config.phtml',
            'prooph.link.sqlconnector/sql-manager/app' => __DIR__ . '/../view/sqlconnector/sql-manager/app.phtml',
            'prooph.link.sqlconnector/sql-manager/partial/sidebar-left' => __DIR__ . '/../view/sqlconnector/sql-manager/partial/sidebar-left.phtml',
            //riot tags
            'prooph.link.sqlconnector/sql-manager/riot-tag/sql-manager' => __DIR__ . '/../view/sqlconnector/sql-manager/riot-tag/sql-manager.phtml',
            'prooph.link.sqlconnector/sql-manager/riot-tag/connector-list' => __DIR__ . '/../view/sqlconnector/sql-manager/riot-tag/connector-list.phtml',
            'prooph.link.sqlconnector/sql-manager/riot-tag/connector-details' => __DIR__ . '/../view/sqlconnector/sql-manager/riot-tag/connector-details.phtml',
            'prooph.link.sqlconnector/sql-manager/riot-tag/connection-config' => __DIR__ . '/../view/sqlconnector/sql-manager/riot-tag/connection-config.phtml',
            'prooph.link.sqlconnector/pm/riot-tag/sqlconnector-metadata' => __DIR__ . '/../view/sqlconnector/pm/riot-tag/sqlconnector-metadata.phtml'
        ],
    ),
    'asset_manager' => array(
        'resolver_configs' => array(
            //Riot tags are resolved by the Application\Service\RiotTagCollectionResolver
            'riot-tags' => [
                'js/prooph/link/sqlconnector/app.js' => [
                    'prooph.link.sqlconnector/sql-manager/riot-tag/sql-manager',
                    'prooph.link.sqlconnector/sql-manager/riot-tag/connector-list',
                    'prooph.link.sqlconnector/sql-manager/riot-tag/connector-details',
                    'prooph.link.sqlconnector/sql-manager/riot-tag/connection-config',
                ],
                //Inject process manager metadata configurator for sql connectors
                'js/prooph/link/process-config/app.js' => [
                    'prooph.link.sqlconnector/pm/riot-tag/sqlconnector-metadata',
                ],
            ],
        ),
    ),
    'service_manager' => [
        'factories' => [
            'prooph.link.sqlconnector.dbal_connections' => 'Prooph\Link\SqlConnector\Service\Factory\ConnectionsProvider',
            'prooph.link.sqlconnector.connection_manager' => 'Prooph\Link\SqlConnector\Service\Factory\ConnectionManagerFactory',
            'prooph.link.sqlconnector.table_connector_generator' => 'Prooph\Link\SqlConnector\Service\Factory\TableConnectorGeneratorFactory',
        ],
        'abstract_factories' => [
            'Prooph\Link\SqlConnector\Service\Factory\AbstractDoctrineTableGatewayFactory'
        ]
    ],
    'controllers' => array(
        'invokables' => [
            'Prooph\Link\SqlConnector\Api\TestConnection' => 'Prooph\Link\SqlConnector\Api\TestConnection',
            'Prooph\Link\SqlConnector\Controller\DashboardWidget' => 'Prooph\Link\SqlConnector\Controller\DashboardWidgetController',
        ],
        'factories' => [
            'Prooph\Link\SqlConnector\Controller\SqlManager' => 'Prooph\Link\SqlConnector\Controller\Factory\SqlManagerControllerFactory',
            'Prooph\Link\SqlConnector\Api\Connector'        => 'Prooph\Link\SqlConnector\Api\Factory\ConnectorResourceFactory',
            'Prooph\Link\SqlConnector\Api\Connection'        => 'Prooph\Link\SqlConnector\Api\Factory\ConnectionResourceFactory',
            'Prooph\Link\SqlConnector\Api\Table'             => 'Prooph\Link\SqlConnector\Api\Factory\TableResourceFactory',
        ]
    ),
    'zf-content-negotiation' => [
        'controllers' => [
            'Prooph\Link\SqlConnector\Api\TestConnection' => 'Json',
            'Prooph\Link\SqlConnector\Api\Connection' => 'Json',
            'Prooph\Link\SqlConnector\Api\Connector' => 'Json',
            'Prooph\Link\SqlConnector\Api\Table' => 'Json',
        ],
        'accept_whitelist' => [
            'Prooph\Link\SqlConnector\Api\TestConnection' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Connection' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Connector' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Table' => ['application/json'],
        ],
        'content_type_whitelist' => [
            'Prooph\Link\SqlConnector\Api\TestConnection' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Connection' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Connector' => ['application/json'],
            'Prooph\Link\SqlConnector\Api\Table' => ['application/json'],
        ],
    ]
);