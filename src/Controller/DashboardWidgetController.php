<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 02.01.15 - 18:11
 */

namespace Prooph\Link\SqlConnector\Controller;

use Prooph\Link\Dashboard\Controller\AbstractWidgetController;
use Prooph\Link\Dashboard\View\DashboardWidget;
use Prooph\Link\Application\Projection\ProcessingConfig;
use Prooph\Link\Application\Service\NeedsSystemConfig;

/**
 * Class DashboardWidgetController
 *
 * @package SqlConnector\src\Controller
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class DashboardWidgetController extends AbstractWidgetController
{
    /**
     * @return DashboardWidget
     */
    public function widgetAction()
    {
        if (! $this->systemConfig->isConfigured()) return false;

        $connectors = [];

        foreach ($this->systemConfig->getConnectors() as $connectorId => $connector) {
            if (strpos($connectorId, 'sqlconnector:::') !== false) $connectors[$connectorId] = $connector;
        }

        return DashboardWidget::initialize(
            $this->widgetConfig->get('template', 'prooph.link.sqlconnector/dashboard/widget'),
            $this->widgetConfig->get('title', 'Sql Table Connector'),
            $this->widgetConfig->get('cols', 4),
            ['processingConfig' => $this->systemConfig, 'sqlConnectors' => $connectors],
            $this->widgetConfig->get('group_title')
        );
    }
}
 