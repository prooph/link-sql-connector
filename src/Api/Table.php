<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 26.01.15 - 00:19
 */

namespace Prooph\Link\SqlConnector\Api;

use Prooph\Link\Application\Service\AbstractRestController;
use Doctrine\DBAL\DriverManager;
use Prooph\Link\SqlConnector\Service\ConnectionManager;
use Prooph\Link\SqlConnector\Service\DbalConnectionCollection;
use ZF\ContentNegotiation\JsonModel;

/**
 * Class Table
 *
 * @package SqlConnector\Api
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class Table extends AbstractRestController
{
    /**
     * @var ConnectionManager
     */
    private $dbalConnections;

    /**
     * @param ConnectionManager $connections
     */
    public function __construct(ConnectionManager $connections)
    {
        $this->dbalConnections = $connections;
    }

    public function getList()
    {
        $connectionDb = $this->params('dbname');

        if (! $connection = $this->dbalConnections->findByDbName($connectionDb)) {
            return $this->getApiProblemResponse(404, 'Dbal connection can not be found');
        }

        $tables = $connection->connection()->getSchemaManager()->listTableNames();

        return new JsonModel([ 'payload' => array_map(function ($tablename) {
            return ["name" => $tablename];
        }, $tables)]);
    }
}
 