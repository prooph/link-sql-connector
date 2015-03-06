<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 1/26/15 - 9:58 PM
 */
namespace Prooph\Link\SqlConnector\Service;

use Assert\Assertion;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Class DbalConnectionCollection
 *
 * @method DbalConnection get($key)
 *
 * @package SqlConnector\Service
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class DbalConnectionCollection extends ArrayCollection
{
    public static function fromConnectionConfigs(array $connectionConfigs)
    {
        return new self(
            array_map(
                function ($config) { return DbalConnection::fromConfiguration($config); },
                $connectionConfigs
            )
        );
    }

    /**
     * @param DbalConnection $connection
     * @param null|string $alternativeKey If null then the dbname is used as key
     * @return void
     */
    public function add($connection, $alternativeKey = null)
    {
        if (is_null($alternativeKey)) {
            $alternativeKey = $connection->config()['dbname'];
        }
        $this->set($alternativeKey, $connection);
    }

    /**
     * @param string $key
     * @param DbalConnection $connection
     */
    public function set($key, $connection)
    {
        Assertion::string($key, "Dbal connection key should be a string");
        Assertion::isInstanceOf($connection, 'Prooph\Link\SqlConnector\Service\DbalConnection');

        parent::set($key, $connection);
    }

    /**
     * Only returns connection configs indexed by dbname
     *
     * @return array
     */
    public function toArray()
    {
        $connections = array();

        /** @var $connection DbalConnection */
        foreach ($this as $key => $connection)
        {
            $connections[$key] = $connection->config();
        }

        return $connections;
    }
} 