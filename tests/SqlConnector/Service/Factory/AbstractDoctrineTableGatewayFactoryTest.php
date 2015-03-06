<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 09.01.15 - 00:23
 */

namespace ProophTest\Link\SqlConnector\Service\Factory;

use ProophTest\Link\SqlConnector\Bootstrap;
use ProophTest\Link\SqlConnector\Mock\TableGatewayListenerAggregate;
use ProophTest\Link\SqlConnector\TestCase;

/**
 * Class AbstractDoctrineTableGatewayFactoryTest
 *
 * @package SqlConnectorTest\Service\Factory
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class AbstractDoctrineTableGatewayFactoryTest extends TestCase
{
    protected function tearDown()
    {
        TableGatewayListenerAggregate::resetAttachedFlag();
    }

    /**
     * @test
     */
    public function it_creates_a_doctrine_table_gateway()
    {
        $this->assertFalse(TableGatewayListenerAggregate::isAttached());

        $tableGateway = Bootstrap::getServiceManager()->get('sqlconnector:::processing_test_users');

        $this->assertInstanceOf('Prooph\Link\SqlConnector\Service\DoctrineTableGateway', $tableGateway);

        $this->assertTrue(TableGatewayListenerAggregate::isAttached());
    }
}
 