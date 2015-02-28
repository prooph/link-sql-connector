<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 25.01.15 - 21:55
 */

namespace Prooph\Link\SqlConnector\Api;

use Prooph\Link\Application\Service\AbstractRestController;
use Prooph\Link\SqlConnector\Service\ConnectionManager;

/**
 * Class Connection
 *
 * @package SqlConnector\Api
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class Connection extends AbstractRestController
{
    /**
     * @var ConnectionManager
     */
    private $connectionManager;

    /**
     * @param ConnectionManager $connectionManager
     */
    public function __construct(ConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function create(array $data)
    {
        $this->connectionManager->addConnection($data);

        return $data;
    }

    public function update($id, array $data)
    {
        $this->connectionManager->updateConnection($data);

        return $data;
    }
}
 