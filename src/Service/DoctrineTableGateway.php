<?php
/*
* This file is part of prooph/link.
 * (c) prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 02.01.15 - 22:37
 */

namespace Prooph\Link\SqlConnector\Service;

use Prooph\Common\Event\ActionEventDispatcher;
use Prooph\Common\Event\ActionEventListenerAggregate;
use Prooph\Link\Application\DataType\SqlConnector\TableRow;
use Prooph\Link\Application\SharedKernel\MessageMetadata;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Prooph\Link\SqlConnector\Helper\Table;
use Prooph\Processing\Functional\Iterator\MapIterator;
use Prooph\Processing\Message\AbstractWorkflowMessageHandler;
use Prooph\Processing\Message\ProcessingMessage;
use Prooph\Processing\Message\LogMessage;
use Prooph\Processing\Message\WorkflowMessage;
use Prooph\Processing\Type\Description\Description;
use Prooph\Processing\Type\Description\NativeType;
use Prooph\Processing\Type\Prototype;
use Prooph\Processing\Type\Type;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Zend\Stdlib\ErrorHandler;
use Zend\XmlRpc\Value\AbstractCollection;

/**
 * Class DoctrineTableGateway
 *
 * @package SqlConnector\src\Service
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class DoctrineTableGateway extends AbstractWorkflowMessageHandler
{
    /**
     * Key in collect-data single result metadata. If set to TRUE the TableGateway uses the identifier name of the processing type to find a result
     */
    const META_IDENTIFIER = 'identifier';

    /**
     * Key in collect-data metadata. Key-Value-Pairs of filters. Multiple filters are combined to a And-Filer.
     * If key is a table column and value a scalar value a key = value (aka column = value) filter is applied.
     * If value is an array it should contain a operand and value definition and optional a column definition if key is not the column name.
     * The latter is useful when specifying a between filter:
     *
     * filter = [
     *   'age_min' => [
     *     'column' => 'age',
     *     'operand' => '>=',
     *     'value' => 21
     *   ],
     *   'age_max' => [
     *     'column' => 'age',
     *     'operand' => '<=',
     *     'value' => 120
     *   ]
     * [
     */
    const META_FILTER = "filter";

    /**
     * Filter keys. See example above
     */
    const FILTER_COLUMN = "column";
    const FILTER_OPERAND = "operand";
    const FILTER_VALUE  = "value";

    /**
     * Available operands for a filter.
     */
    const OPERAND_EQ      = "=";
    const OPERAND_LT      = "<";
    const OPERAND_LTE     = "<=";
    const OPERAND_GTE     = ">=";
    const OPERAND_GT      = ">";
    const OPERAND_LIKE    = "like";
    const OPERAND_LIKE_CI = "ilike";

    /**
     * Set the query offset.
     */
    const META_OFFSET = MessageMetadata::OFFSET;

    /**
     * Set the query limit
     */
    const META_LIMIT = MessageMetadata::LIMIT;

    /**
     * Set order_by like you would do in a SQL query.
     *
     * example: "age DESC,name ASC"
     */
    const META_ORDER_BY = "order_by";

    /**
     * Flag to empty table before insert
     */
    const META_EMPTY_TABLE = "empty_table";

    /**
     * Activate update or insert processing
     *
     * This will take much longer than empty table and insert,
     * so use it only for delta updates.
     */
    const META_TRY_UPDATE = "try_update";

    /**
     * Commit insert, even if not all inserts were possible.
     *
     * An insert can fail due to duplicate keys or invalid data.
     * With ignore_errors set to true all valid inserts are committed, otherwise everything is rolled back.
     */
    const META_IGNORE_ERRORS = "ignore_errors";

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $table;

    /**
     * @var ActionEventDispatcher
     */
    private $actions;

    /**
     * @var bool
     */
    private $triggerActions = false;

    /**
     * @param Connection $connection
     * @param string $table
     * @param ActionEventDispatcher $eventDispatcher
     * @param ActionEventListenerAggregate[] $listeners
     */
    public function __construct(Connection $connection, $table, ActionEventDispatcher $eventDispatcher = null, array $listeners = null)
    {
        $this->connection = $connection;
        $this->table      = $table;
        $this->actions    = $eventDispatcher;
        $this->triggerActions = ! is_null($eventDispatcher);

        if ($eventDispatcher && $listeners) {
            foreach ($listeners as $listenerAggregate) {
                $this->actions->attachListenerAggregate($listenerAggregate);
            }
        }
    }

    /**
     * If workflow message handler receives a collect-data message it forwards the message to this
     * method and uses the returned ProcessingMessage as response
     *
     * @param WorkflowMessage $workflowMessage
     * @return ProcessingMessage
     */
    protected function handleCollectData(WorkflowMessage $workflowMessage)
    {
        try {
            return $this->collectData($workflowMessage);
        } catch (\Exception $ex) {
            ErrorHandler::stop();
            return LogMessage::logException($ex, $workflowMessage);
        }
    }

    /**
     * If workflow message handler receives a process-data message it forwards the message to this
     * method and uses the returned ProcessingMessage as response
     *
     * @param WorkflowMessage $workflowMessage
     * @return ProcessingMessage
     */
    protected function handleProcessData(WorkflowMessage $workflowMessage)
    {
        try {
            return $this->processData($workflowMessage);
        } catch (\Exception $ex) {
            ErrorHandler::stop();
            return LogMessage::logException($ex, $workflowMessage);
        }
    }

    /**
     * @param WorkflowMessage $workflowMessage
     * @return WorkflowMessage
     */
    private function collectData(WorkflowMessage $workflowMessage)
    {
        $processingType = $workflowMessage->payload()->getTypeClass();

        /** @var $desc Description */
        $desc = $processingType::buildDescription();

        switch ($desc->nativeType()) {
            case NativeType::COLLECTION:
                return $this->collectResultSet($workflowMessage);
                break;
            case NativeType::DICTIONARY:
                return $this->collectSingleResult($workflowMessage);
                break;
            default:
                return LogMessage::logUnsupportedMessageReceived($workflowMessage);
        }
    }

    /**
     * @param WorkflowMessage $workflowMessage
     * @return WorkflowMessage
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function collectSingleResult(WorkflowMessage $workflowMessage)
    {
        $itemType = $workflowMessage->payload()->getTypeClass();

        $metadata = $workflowMessage->metadata();

        if (! method_exists($itemType, 'fromDatabaseRow')) throw new \InvalidArgumentException(sprintf("Item type %s does not provide a static fromDatabaseRow factory method", $itemType));
        if (! method_exists($itemType, 'toDbColumnName')) throw new \InvalidArgumentException(sprintf("Item type %s does not provide a static toDbColumnName method", $itemType));

        $query = $this->buildQueryFromMetadata($itemType, $metadata);

        if (isset($metadata[self::META_IDENTIFIER])) {
            if (! $itemType::prototype()->typeDescription()->hasIdentifier()) throw new \InvalidArgumentException(sprintf("Item type %s has no identifier", $itemType));

            $identifierName = $itemType::prototype()->typeDescription()->identifierName();

            $this->addFilter($query, $itemType::toDbColumnName($identifierName), '=', $metadata['identifier'], 1000);
        }

        if ($this->triggerActions) {
            $action = $this->actions->getNewActionEvent('collect_single_result', $this, [
                'workflow_message' => $workflowMessage,
                'item_type' => $itemType,
                'query' => $query
            ]);

            $this->actions->dispatch($action);

            //Maybe item type or query were changed by a listener?
            $itemType = $action->getParam('item_type');
            $query = $action->getParam('query', $query);
        }

        $itemData = $query->execute()->fetch();

        if (is_null($itemData)) {
            throw new \RuntimeException(sprintf("No %s found using metadata %s", $itemType, json_encode($workflowMessage->metadata())));
        }

        return $workflowMessage->answerWith($itemType::fromDatabaseRow($itemData));
    }

    private function collectResultSet(WorkflowMessage $workflowMessage)
    {
        /** @var $collectionType AbstractCollection */
        $collectionType = $workflowMessage->payload()->getTypeClass();

        $itemType = $collectionType::prototype()->typeProperties()['item']->typePrototype()->of();

        if (! method_exists($itemType, 'fromDatabaseRow')) throw new \InvalidArgumentException(sprintf("Item type %s does not provide a static fromDatabaseRow factory method", $itemType));
        if (! method_exists($itemType, 'toDbColumnName')) throw new \InvalidArgumentException(sprintf("Item type %s does not provide a static toDbColumnName method", $itemType));

        $count = $this->countRows($itemType, $workflowMessage->metadata());

        $query = $this->buildQueryFromMetadata($itemType, $workflowMessage->metadata());

        if ($this->triggerActions) {
            $action = $this->actions->getNewActionEvent('collect_result_set', $this, [
                'workflow_message' => $workflowMessage,
                'item_type' => $itemType,
                'collection_type' => $collectionType,
                'query' => $query
            ]);

            $this->actions->dispatch($action);

            //Maybe item/collection type or query were changed by a listener?
            $itemType = $action->getParam('item_type');
            $collectionType = $action->getParam('collection_type');
            $query = $action->getParam('query', $query);
        }

        $stmt = $query->execute();

        //Premap row, so that factory fromDatabaseRow is used to construct the TableRow type
        $mapIterator = new MapIterator($stmt, function ($item) use ($itemType) {
            return $itemType::fromDatabaseRow($item);
        });

        $collection = $collectionType::fromNativeValue($mapIterator);

        return $workflowMessage->answerWith($collection, [MessageMetadata::TOTAL_ITEMS => $count]);
    }

    /**
     * @param string $itemType
     * @param array $metadata
     * @return int
     */
    private function countRows($itemType, array $metadata)
    {
        $query = $this->buildQueryFromMetadata($itemType, $metadata, true);

        if ($this->triggerActions) {
            $action = $this->actions->getNewActionEvent('count_rows', $this, [
                'item_type' => $itemType,
                'query' => $query
            ]);

            $this->actions->dispatch($action);

            //Maybe query was changed by a listener?
            $query = $action->getParam('query', $query);
        }

        return $query->execute()->fetchColumn();
    }

    /**
     * @param string $itemType
     * @param array $metadata
     * @param bool $countMode
     * @throws \InvalidArgumentException
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function buildQueryFromMetadata($itemType, array $metadata, $countMode = false)
    {
        ErrorHandler::start();

        $query = $this->connection->createQueryBuilder();

        if ($countMode) {
            $query->select('COUNT(*)');
        } else {
            $query->select('*');
        }

        $query->from($this->table, 'main');

        if (isset($metadata[self::META_FILTER]) && is_array($metadata[self::META_FILTER])) {
            $filterCount = 0;

            foreach ($metadata[self::META_FILTER] as $column => $value) {
                $filterCount++;

                if (is_array($value)) {
                    if (! array_key_exists(self::FILTER_OPERAND, $value)) throw new \InvalidArgumentException(sprintf('Missing operand in filter array for column %s', $column));
                    if (! array_key_exists(self::FILTER_VALUE, $value)) throw new \InvalidArgumentException(sprintf('Missing value in filter array for column %s', $column));

                    if (isset($value[self::FILTER_COLUMN])) {
                        $column = $value[self::FILTER_COLUMN];
                    }

                    $column = $itemType::toDbColumnName($column);

                    $this->addFilter($query, $column, $value[self::FILTER_OPERAND], $value[self::FILTER_VALUE], $filterCount);
                } else {
                    $this->addFilter($query, $column, '=', $value, $filterCount);
                }
            }
        }

        if (! $countMode) {
            if (isset($metadata[MessageMetadata::OFFSET])) {
                $query->setFirstResult($metadata[MessageMetadata::OFFSET]);
            }

            if (isset($metadata[MessageMetadata::LIMIT])) {
                $query->setMaxResults($metadata[MessageMetadata::LIMIT]);
            }

            if (isset($metadata[self::META_ORDER_BY])) {
                $orderByArr = explode(",", $metadata[self::META_ORDER_BY]);

                foreach ($orderByArr as $orderBy) {
                    $orderBy = trim($orderBy);

                    $orderByParts = explode(" ", $orderBy);

                    $order = isset($orderByParts[1])? $orderByParts[1] : 'ASC';

                    $query->addOrderBy($orderByParts[0], $order);
                }
            }
        }

        ErrorHandler::stop(true);

        return $query;
    }

    /**
     * @param QueryBuilder $query
     * @param string $column
     * @param string $operand
     * @param mixed $value
     * @param int $filterCount
     */
    private function addFilter(QueryBuilder $query, $column, $operand, $value, $filterCount)
    {
        switch ($operand) {
            case self::OPERAND_EQ:
                $query->andWhere($query->expr()->eq($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_LT:
                $query->andWhere($query->expr()->lt($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_LTE:
                $query->andWhere($query->expr()->lte($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_GT:
                $query->andWhere($query->expr()->gt($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_GTE:
                $query->andWhere($query->expr()->gte($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_LIKE:
                $query->andWhere($query->expr()->like($column, ':__filter' . $filterCount));
                break;
            case self::OPERAND_LIKE_CI:
                $query->andWhere($query->expr()->like('LOWER(' . $column . ')', ':__filter' . $filterCount));
                $value = strtolower($value);
                break;

        }

        $query->setParameter('__filter' . $filterCount, $value);
    }

    /**
     * @param WorkflowMessage $message
     * @return LogMessage|WorkflowMessage
     */
    private function processData(WorkflowMessage $message)
    {
        $metadata = $message->metadata();

        $copiedTableName = null;
        $orgTableName = $this->table;

        if (isset($metadata[self::META_EMPTY_TABLE]) && $metadata[self::META_EMPTY_TABLE]) {
            $copiedTableName = $this->copyTable();
            //We override the target table to insert into the copy table first
            $this->table = $copiedTableName;
        }

        $forceInsert = true;

        if (is_null($copiedTableName) && isset($metadata[self::META_TRY_UPDATE]) && $metadata[self::META_TRY_UPDATE]) {
            $forceInsert = false;
        }

        $message = $this->updateOrInsertPayload($message, $forceInsert);

        $ignoreErrors = (isset($metadata[self::META_IGNORE_ERRORS]))? (bool)$metadata[self::META_IGNORE_ERRORS] : false;


        if (! is_null($copiedTableName)) {
            $this->table = $orgTableName;

            if (!$ignoreErrors && $message instanceof LogMessage) {
                $this->dropCopyTable($orgTableName);
                return $message;
            }

            $this->replaceOrgTableWithCopy($orgTableName, $copiedTableName);
        }

        return $message;
    }

    /**
     * @param WorkflowMessage $message
     * @param bool $forceInsert
     * @return LogMessage|WorkflowMessage
     */
    private function updateOrInsertPayload(WorkflowMessage $message, $forceInsert = false)
    {
        $processingType = $message->payload()->getTypeClass();

        /** @var $desc Description */
        $desc = $processingType::buildDescription();

        $successful = 0;
        $failed = 0;
        $failedMessages = [];

        if ($desc->nativeType() == NativeType::COLLECTION) {

            /** @var $prototype Prototype */
            $prototype = $processingType::prototype();

            $itemProto = $prototype->typeProperties()['item']->typePrototype();

            $typeObj = $message->payload()->toType();

            if ($typeObj) {
                $this->connection->beginTransaction();

                $insertStmt = null;

                /** @var $tableRow TableRow */
                foreach ($typeObj as $i => $tableRow) {
                    if (! $tableRow instanceof TableRow) {
                        return LogMessage::logUnsupportedMessageReceived($message);
                    }

                    try {
                        $insertStmt = $this->updateOrInsertTableRow($tableRow, $forceInsert, $insertStmt);

                        $successful++;
                    } catch (\Exception $e) {
                        $datasetIndex = ($tableRow->description()->hasIdentifier())?
                            $tableRow->description()->identifierName() . " = " . $tableRow->property($tableRow->description()->identifierName())->value()
                            : $i;

                        $failed++;
                        $failedMessages[] = sprintf(
                            'Dataset %s: %s',
                            $datasetIndex,
                            $e->getMessage()
                        );
                    }
                }

                $this->connection->commit();
            }

            $report = [
                MessageMetadata::SUCCESSFUL_ITEMS => $successful,
                MessageMetadata::FAILED_ITEMS => $failed,
                MessageMetadata::FAILED_MESSAGES => $failedMessages
            ];

            if ($failed > 0) {
                return LogMessage::logItemsProcessingFailed(
                    $successful,
                    $failed,
                    $failedMessages,
                    $message
                );
            } else {
                return $message->answerWithDataProcessingCompleted($report);
            }
        } else {
            $tableRow = $message->payload()->toType();

            if (! $tableRow instanceof TableRow) {
                return LogMessage::logUnsupportedMessageReceived($message);
            }

            $this->updateOrInsertTableRow($tableRow, $forceInsert);

            return $message->answerWithDataProcessingCompleted();
        }
    }

    private function updateOrInsertTableRow(TableRow $data, $forceInsert = false, Statement $insertStmt = null)
    {
        $id = false;
        $pk = null;

        if ($data->description()->hasIdentifier()) {
            $pk = $data::toDbColumnName($data->description()->identifierName());

            $id = $data->property($data->description()->identifierName())->value();
        }

        $dbTypes = $this->getDbTypesForProperties($data);

        $itemType = $data->prototype()->of();

        $data = $this->convertToDbData($data);

        //In try update mode we try to delete the table row first and then insert it again
        if (! $forceInsert) {
            $query = $this->connection->createQueryBuilder();

            //Due to Sqlite error when using an alias we don't assign one here, so delete queries are limit to one table
            //However, a action event listener can rebuild the query and use a platform specific delete with joins if required
            $query->delete($this->table);

            if ($id) {
                $query->where(
                    $query->expr()->eq(
                        $pk,
                        ':identifier'
                    )
                );

                $query->setParameter('identifier', $id, $dbTypes[$pk]);
            } elseif ($this->triggerActions) {
                $actionEvent = $this->actions->getNewActionEvent('delete_table_row', $this, [
                    'query' => $query,
                    'item_type' => $itemType,
                    'item_db_data' => $data,
                    'item_db_types' => $dbTypes,
                    'skip_row' => false
                ]);

                $this->actions->dispatch($actionEvent);

                if ($actionEvent->getParam('skip_row')) {
                    return $insertStmt;
                }

                $query = $actionEvent->getParam('query');

                if ($query && empty($query->getQueryPart('where'))) {
                    $query = null;
                }
            } else {
                $query = null;
            }

            //We only perform the delete query if it has at least one condition set.
            if ($query) {
                $query->execute();
            }
        }

        return $this->performInsert($data, $dbTypes, $insertStmt);
    }

    /**
     * @param array $data
     * @param Statement $stmt
     * @return Statement
     */
    private function performInsert(&$data, &$dbTypes, Statement $stmt = null)
    {
        if (is_null($stmt)) {
            $query = $this->connection->createQueryBuilder();

            $query->insert($this->table)->values(
                array_combine(array_keys($data), array_fill(0, count($data), '?'))
            );

            $stmt = $this->connection->prepare($query->getSQL());
        }

        $bindIndex = 1;

        foreach ($data as $column => $value) {
            $type = \Doctrine\DBAL\Types\Type::getType($dbTypes[$column]);

            $stmt->bindValue($bindIndex, $value, $type->getBindingType());

            $bindIndex++;
        }

        $stmt->execute();

        return $stmt;
    }

    private function convertToDbData(TableRow $tableRow)
    {
        $data = [];

        /** @var $prop Type */
        foreach ($tableRow->value() as $propName => $prop) {
            $data[$tableRow::toDbColumnName($propName)] = $prop->value();
        }

        return $data;
    }

    /**
     * Db types need to be an array in the same order as the properties
     * @param TableRow $tableRow
     * @return array
     */
    private function getDbTypesForProperties(TableRow $tableRow)
    {
        $dbTypes = [];

        foreach ($tableRow->properties() as $propName => $prop) {
            $dbTypes[$tableRow::toDbColumnName($propName)] = $tableRow::getDbTypeForProperty($propName);
        }

        return $dbTypes;
    }

    private function copyTable()
    {
        $sm = $this->connection->getSchemaManager();

        $fromSchema = $sm->createSchema();

        $orgTable = $fromSchema->getTable($this->table);

        $copyTableName = '__' . $this->table. '1';
        $count = 1;
        while ($fromSchema->hasTable($copyTableName)) {
            $copyTableName .= ++$count;
        }

        $copyTable = clone $orgTable;

        Table::rename($copyTable, $copyTableName);

        $sm->createTable($copyTable);

        return $copyTableName;
    }

    private function dropCopyTable($copiedTableName)
    {
        $this->connection->getSchemaManager()->dropTable($copiedTableName);
    }

    private function replaceOrgTableWithCopy($orgTableName, $copiedTableName)
    {
        $this->connection->getSchemaManager()->dropTable($orgTableName);

        $this->connection->getSchemaManager()->renameTable($copiedTableName, $orgTableName);
    }
}
 