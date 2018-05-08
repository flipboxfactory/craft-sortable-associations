<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\services;

use Craft;
use craft\helpers\ArrayHelper;
use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use yii\base\Component;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
abstract class SortableAssociations extends Component
{
    use traits\SequentialOrderTrait;

    /**
     * The source attribute name
     * @return string
     */
    const SOURCE_ATTRIBUTE = '';

    /**
     * The source attribute name
     * @return string
     */
    const TARGET_ATTRIBUTE = '';

    /**
     * The sort order attribute name
     * @return string
     */
    const SORT_ORDER_ATTRIBUTE = 'sortOrder';

    /**
     * @return string
     */
    abstract protected static function tableAlias(): string;

    /**
     * @param array $config
     * @return SortableAssociationQueryInterface
     */
    abstract public function getQuery($config = []): SortableAssociationQueryInterface;

    /**
     * @param SortableAssociationInterface $record
     * @return SortableAssociationQueryInterface|ActiveQuery
     */
    abstract protected function associationQuery(
        SortableAssociationInterface $record
    ): SortableAssociationQueryInterface;

    /**
     * @param SortableAssociationQueryInterface $query
     * @return array
     */
    abstract protected function existingAssociations(
        SortableAssociationQueryInterface $query
    ): array;

    /**
     * @param SortableAssociationQueryInterface $query
     * @return bool
     * @throws \Exception
     */
    public function save(
        SortableAssociationQueryInterface $query
    ): bool {
        if (null === ($associations = $query->getCachedResult())) {
            return true;
        }

        $existingAssociations = $this->existingAssociations($query);

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $newOrder = [];
            if (!$this->associateAll($associations, $existingAssociations, $newOrder) ||
                !$this->dissociateAll($existingAssociations)
            ) {
                $transaction->rollBack();
                return false;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->commit();
        return true;
    }

    /*******************************************
     * ASSOCIATE / DISSOCIATE
     *******************************************/

    /**
     * @param SortableAssociationInterface $record
     * @param bool $reOrder
     * @return bool
     * @throws \Exception
     */
    public function associate(
        SortableAssociationInterface $record,
        bool $reOrder = true
    ): bool {
        if (true === $this->existingAssociation($record)) {
            $reOrder = true;
        }

        if ($record->save() === false) {
            return false;
        }

        if ($reOrder === true) {
            return $this->applySortOrder($record);
        }

        return true;
    }

    /**
     * @param SortableAssociationInterface $record
     * @param bool $reOrder
     * @return bool
     * @throws \yii\db\Exception
     */
    public function dissociate(
        SortableAssociationInterface $record,
        bool $reOrder = true
    ) {
        if (false === $this->existingAssociation($record, false)) {
            return true;
        }

        if ($record->delete() === false) {
            return false;
        }

        if ($reOrder === true) {
            $this->autoReOrder($record);
        }

        return true;
    }


    /*******************************************
     * ASSOCIATE / DISSOCIATE MANY
     *******************************************/

    /**
     * @param array $associations
     * @param array $currentModels
     * @param array $newOrder
     * @return bool
     * @throws \Exception
     */
    protected function associateAll(
        array $associations,
        array &$currentModels,
        array &$newOrder
    ): bool {
        /** @var SortableAssociationInterface $association */
        $ct = 1;
        foreach ($associations as $association) {
            $target = $association->{static::TARGET_ATTRIBUTE};
            $newOrder[$target] = $ct++;

            ArrayHelper::remove($currentModels, $target);

            if (!$this->associate($association, false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $targets
     * @return bool
     * @throws \Exception
     */
    protected function dissociateAll(
        array $targets
    ): bool {
        /** @var SortableAssociationInterface $target */
        foreach ($targets as $target) {
            if (!$this->dissociate($target)) {
                return false;
            }
        }

        return true;
    }


    /*******************************************
     * RECORD SORT ORDER
     *******************************************/

    /**
     * @param SortableAssociationInterface $current
     * @param SortableAssociationInterface|null $existing
     */
    private function ensureSortOrder(
        SortableAssociationInterface $current,
        SortableAssociationInterface $existing = null
    ) {
        if ($current->{static::SORT_ORDER_ATTRIBUTE} !== null) {
            return;
        }

        $current->{static::SORT_ORDER_ATTRIBUTE} = $existing ?
            $existing->{static::SORT_ORDER_ATTRIBUTE} :
            $this->nextSortOrder($current);
    }

    /**
     * @param SortableAssociationInterface $record
     * @return bool
     * @throws \Exception
     */
    private function applySortOrder(
        SortableAssociationInterface $record
    ): bool {
        $sortOrder = $this->sortOrder($record);

        if (count($sortOrder) < $record->{static::SORT_ORDER_ATTRIBUTE}) {
            $record->{static::SORT_ORDER_ATTRIBUTE} = count($sortOrder);
        }

        $order = $this->insertIntoOrder($record, $sortOrder);

        if ($order === true || $order === false) {
            return $order;
        }

        return $this->reOrder(
            $record->getPrimaryKey(true),
            (array)$order
        );
    }

    /**
     * @param SortableAssociationInterface $record
     * @param array $sortOrder
     * @return array|bool
     * @throws \Exception
     */
    private function insertIntoOrder(
        SortableAssociationInterface $record,
        array $sortOrder
    ) {

        if ($record->{static::SORT_ORDER_ATTRIBUTE} !== null) {
            return true;
        }

        $order = $this->insertSequential(
            $sortOrder,
            $record->{static::TARGET_ATTRIBUTE},
            $record->{static::SORT_ORDER_ATTRIBUTE} ?: 1
        );

        if ($order === false) {
            return $this->associate($record);
        }

        if ($order === true) {
            return true;
        }

        return $order;
    }

    /**
     * @param SortableAssociationInterface $record
     * @return bool
     * @throws \yii\db\Exception
     */
    protected function autoReOrder(
        SortableAssociationInterface $record
    ): bool {
        $sortOrder = $this->sortOrder($record);

        if (empty($sortOrder)) {
            return true;
        }

        return $this->reOrder(
            $record->getPrimaryKey(true),
            array_flip(array_combine(
                range(1, count($sortOrder)),
                array_keys($sortOrder)
            ))
        );
    }

    /**
     * @param SortableAssociationInterface $record
     * @return array
     */
    protected function sortOrder(
        SortableAssociationInterface $record
    ): array {
        return $this->associationQuery($record)
            ->indexBy(static::TARGET_ATTRIBUTE)
            ->select([static::SORT_ORDER_ATTRIBUTE])
            ->column();
    }

    /**
     * @param SortableAssociationInterface $record
     * @return int
     */
    private function nextSortOrder(
        SortableAssociationInterface $record
    ): int {
        $maxSortOrder = $this->associationQuery($record)
            ->max(static::SORT_ORDER_ATTRIBUTE);

        return ++$maxSortOrder;
    }

    /*******************************************
     * RAW SORT ORDER
     *******************************************/

    /**
     * @param array $condition
     * @param array $sortOrder
     * @return bool
     * @throws \yii\db\Exception
     */
    protected function reOrder(
        array $condition,
        array $sortOrder
    ): bool {
        foreach ($sortOrder as $target => $order) {
            Craft::$app->getDb()->createCommand()
                ->update(
                    '{{%' . $this->tableAlias() . '}}',
                    [static::SORT_ORDER_ATTRIBUTE => $order],
                    array_merge(
                        $condition,
                        [
                            static::TARGET_ATTRIBUTE => $target
                        ]
                    )
                )
                ->execute();
        }

        return true;
    }


    /*******************************************
     * EXISTING / SYNC
     *******************************************/

    /**
     * @param SortableAssociationInterface|ActiveRecord $record
     * @param bool $ensureSortOrder
     * @return bool
     */
    protected function existingAssociation(
        SortableAssociationInterface $record,
        bool $ensureSortOrder = true
    ): bool {
        if (null !== ($existing = $this->lookupAssociation($record))) {
            $record->setOldAttributes(
                $existing->getOldAttributes()
            );
        }

        if (true === $ensureSortOrder) {
            $this->ensureSortOrder($record, $existing);
        }

        return $existing !== null;
    }

    /**
     * @param SortableAssociationInterface $record
     * @return SortableAssociationInterface|ActiveRecord|null
     */
    protected function lookupAssociation(
        SortableAssociationInterface $record
    ) {
        $model = $this->associationQuery($record)
            ->andWhere([
                static::TARGET_ATTRIBUTE => $record->{static::TARGET_ATTRIBUTE},
            ])
            ->one();

        return $model instanceof SortableAssociationInterface ? $model : null;
    }

    /*******************************************
     * UTILITIES
     *******************************************/

    /**
     * @param SortableAssociationQueryInterface $query
     * @param string $attribute
     * @return null|string
     */
    protected function resolveStringAttribute(
        SortableAssociationQueryInterface $query,
        string $attribute
    ) {
        $value = $query->{$attribute};

        if ($value !== null && (is_string($value) || is_numeric($value))) {
            return (string)$value;
        }

        return null;
    }
}
