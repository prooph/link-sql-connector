<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 25.01.15 - 22:23
 */

namespace Prooph\Link\SqlConnector\Service;
use Assert\Assertion;
use Prooph\Link\Application\SharedKernel\ConfigLocation;
use Prooph\Link\Application\Model\ConfigWriter;

/**
 * Class ConnectionManager
 *
 * Stores dbal connection configs in <config_location>/prooph.link.sqlconnector.local.php
 *
 * @package SqlConnector\Service
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class ConnectionManager 
{
    const FILE_NAME = "prooph.link.sqlconnector.local.php";

    /**
     * @var ConfigLocation
     */
    private $configLocation;

    /**
     * @var ConfigWriter
     */
    private $configWriter;

    /**
     * @var DbalConnectionCollection
     */
    private $connections;

    /**
     * @param ConfigLocation $configLocation
     * @param ConfigWriter $configWriter
     * @param DbalConnectionCollection $connections
     */
    public function __construct(ConfigLocation $configLocation, ConfigWriter $configWriter, DbalConnectionCollection $connections)
    {
        $this->configLocation = $configLocation;
        $this->configWriter = $configWriter;
        $this->connections = $connections;
    }

    /**
     * @param array $connection
     * @param null|string $alternativeKey
     * @throws \InvalidArgumentException
     */
    public function addConnection(array $connection, $alternativeKey = null)
    {
        if ($this->findByDbName($connection['dbname'])) throw new \InvalidArgumentException(sprintf('A connection for DB %s already exists', $connection['dbname']));

        if (is_null($alternativeKey)) {
            $alternativeKey = $connection['dbname'];
        }

        Assertion::string($alternativeKey);

        if ($this->connections->containsKey($alternativeKey)) {
            throw new \InvalidArgumentException("A connection for key $alternativeKey is already defined");
        }
        
        $this->connections->add(DbalConnection::fromConfiguration($connection), $alternativeKey);

        $this->saveConnections(true);
    }

    /**
     * @param string $dbName
     * @return DbalConnection|null
     */
    public function findByDbName($dbName)
    {
        foreach ($this->connections as $connection) {
            if ($connection->config()['dbname'] === $dbName) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * @param $key
     * @return DbalConnection
     */
    public function get($key)
    {
        if ($this->connections->containsKey($key)) {
            return $this->connections->get($key);
        }

        return null;
    }

    /**
     * @param array $connection
     * @param null|string $alternativeKey
     * @throws \InvalidArgumentException
     */
    public function updateConnection(array $connection, $alternativeKey = null)
    {
        if (is_null($alternativeKey)) {
            if (! isset($connection['dbname'])) {
                throw new \InvalidArgumentException('Missing dbname key in connection configuration');
            }

            $connectionObj = $this->findByDbName($connection['dbname']);

            if (! $connectionObj) {
                throw new \InvalidArgumentException('No connection found for db: ' . $connection['dbname']);
            }

            $alternativeKey = $this->connections->indexOf($connectionObj);
        }

        Assertion::string($alternativeKey);

        if (! $this->connections->containsKey($alternativeKey)) throw new \InvalidArgumentException(sprintf('Connection for DB %s can not be found', $connection['dbname']));

        $this->connections->set($alternativeKey, DbalConnection::fromConfiguration($connection));

        $this->saveConnections();
    }

    private function saveConnections($checkFile = false)
    {
        $path = $this->configLocation->toString() . DIRECTORY_SEPARATOR . self::FILE_NAME;

        $config = [
            'prooph.link.sqlconnector' => [
                'connections' => $this->connections->toArray()
            ]
        ];

        if ($checkFile && ! file_exists($path)) {
            $this->configWriter->writeNewConfigToDirectory($config, $path);
        } else {
            $this->configWriter->replaceConfigInDirectory($config, $path);
        }
    }
}
 