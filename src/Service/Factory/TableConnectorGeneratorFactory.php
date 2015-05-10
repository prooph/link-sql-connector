<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 1/27/15 - 9:27 PM
 */
namespace Prooph\Link\SqlConnector\Service\Factory;

use Prooph\Link\SqlConnector\Service\TableConnectorGenerator;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class TableConnectorGeneratorFactory
 *
 * @package SqlConnector\Service\Factory
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class TableConnectorGeneratorFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new TableConnectorGenerator(
            $serviceLocator->get('prooph.link.sqlconnector.dbal_connections'),
            $serviceLocator->get('prooph.link.app.data_type_location'),
            $serviceLocator->get('prooph.link.app.config_location'),
            $serviceLocator->get('processing_config'),
            $serviceLocator->get('proophessor.command_bus'),
            $serviceLocator->get("config")['prooph.link.sqlconnector']['doctrine_processing_type_map']
        );
    }
}