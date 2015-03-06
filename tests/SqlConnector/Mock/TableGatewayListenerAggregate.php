<?php
/*
 * This file is part of prooph/link.
 * (c) 2014-2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 3/6/15 - 3:58 PM
 */
namespace ProophTest\Link\SqlConnector\Mock;

use Doctrine\DBAL\Query\QueryBuilder;
use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Event\ActionEventDispatcher;
use Prooph\Common\Event\ActionEventListenerAggregate;
use Prooph\Common\Event\DetachAggregateHandlers;
use ProophTest\Link\SqlConnector\DataType\TestUser;

/**
 * Class TableGatewayListenerAggregate
 *
 * @package Prooph\Link\Application\DataType\SqlConnector\TestDb
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class TableGatewayListenerAggregate implements ActionEventListenerAggregate
{
    use DetachAggregateHandlers;

    /**
     * @var bool
     */
    private static $isAttached = false;

    public static function resetAttachedFlag()
    {
        self::$isAttached = false;
    }

    /**
     * @return bool
     */
    public static function isAttached()
    {
        return self::$isAttached;
    }


    /**
     * @param ActionEventDispatcher $dispatcher
     */
    public function attach(ActionEventDispatcher $dispatcher)
    {
        //Used within the test:
        //DoctrineTableGatewayTest::it_filters_users_by_a_between_filter_set_by_an_action_event_listener
        $this->trackHandler($dispatcher->attachListener('collect_result_set', [$this, 'onCollectResultSet']));
        $this->trackHandler($dispatcher->attachListener('count_rows', [$this, 'onCountRows']));

        //Used within the test:
        //DoctrineTableGatewayTest::it_does_not_perform_the_delete_query_when_no_listener_adds_a_condition
        $this->trackHandler($dispatcher->attachListener('delete_table_row', [$this, 'onDeleteTableRow']));

        self::$isAttached = true;
    }

    /**
     * Add a between filter
     *
     * @param ActionEvent $event
     */
    public function onCollectResultSet(ActionEvent $event)
    {
        if ($event->getParam('item_type') === TestUser::class) {
            /** @var $query QueryBuilder */
            $query = $event->getParam('query');

            $this->addBetweenFilter($query);
        }
    }

    /**
     * Add a between filter
     *
     * @param ActionEvent $event
     */
    public function onCountRows(ActionEvent $event)
    {
        if ($event->getParam('item_type') === TestUser::class) {
            /** @var $query QueryBuilder */
            $query = $event->getParam('query');

            $this->addBetweenFilter($query);
        }
    }

    /**
     * @param ActionEvent $event
     */
    public function onDeleteTableRow(ActionEvent $event)
    {
        if ($event->getParam('item_type') === TestUser::class) {
            /** @var $query QueryBuilder */
            $query = $event->getParam('query');
            $data  = $event->getParam('item_db_data');

            $query->where($query->expr()->eq('name', ':name'))->setParameter('name', $data['name']);
        }
    }

    private function addBetweenFilter(QueryBuilder $query)
    {
        $query->andWhere($query->expr()->gte('main.age', ':age_min'))
            ->andWhere($query->expr()->lt('main.age', ':age_max'))
            ->setParameter('age_min', 30)
            ->setParameter('age_max', 50);
    }
}