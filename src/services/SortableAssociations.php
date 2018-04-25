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
        if ($current->sortOrder !== null) {
            return;
        }

        $current->sortOrder = $existing ?
            $existing->sortOrder :
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

        if (count($sortOrder) < $record->sortOrder) {
            $record->sortOrder = count($sortOrder);
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
        $order = $this->insertSequential(
            $sortOrder,
            $record->{static::TARGET_ATTRIBUTE},
            $record->sortOrder
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
            ->select(['sortOrder'])
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
            ->max('[[sortOrder]]');

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
                    ['sortOrder' => $order],
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

    /**
     * @param array $sourceArray The source array which the target is to be inserted into.  The
     * key represents a unique identifier, while the value is the sort order.
     *
     * As an example if this is the $sourceArray
     *
     * ```
     * [
     *      111 => 1,
     *      343 => 2,
     *      545 => 3,
     *      'foo' => 4,
     *      'bar' => 5
     * ]
     * ```
     *
     * And your $targetKey is 'fooBar' with a $targetOrder of 4, the result would be
     *
     * ```
     * [
     *      111 => 1,
     *      343 => 2,
     *      545 => 3,
     *      'fooBar' => 4,
     *      'foo' => 5,
     *      'bar' => 6
     * ]
     * ```
     *
     * @param string|int $targetKey
     * @param int $targetOrder
     * @return array|bool
     */
    protected function insertSequential(array $sourceArray, $targetKey, int $targetOrder)
    {
        // Append exiting types after position
        if (false === ($indexPosition = array_search($targetKey, array_keys($sourceArray)))) {
            return false;
        }

        // Determine the furthest affected index
        $affectedIndex = $indexPosition >= $targetOrder ? ($targetOrder - 1) : $indexPosition;

        // All that are affected by re-ordering
        $affectedTypes = array_slice($sourceArray, $affectedIndex, null, true);

        // Remove the current (we're going to put it back in later)
        $currentPosition = (int)ArrayHelper::remove($affectedTypes, $targetKey);

        // Already in that position?
        if ($currentPosition === $targetOrder) {
            return true;
        }

        $startingSortOrder = $targetOrder;
        if ($affectedIndex++ < $targetOrder) {
            $startingSortOrder = $affectedIndex;
        }

        // Prepend current type
        $order = [$targetKey => $targetOrder];

        // Assemble order
        if (false !== ($position = array_search($targetOrder, array_values($affectedTypes)))) {
            if ($indexPosition < $targetOrder) {
                $position++;
            }

            if ($position > 0) {
                $order = array_slice($affectedTypes, 0, $position, true) + $order;
            }

            $order += array_slice($affectedTypes, $position, null, true);
        }

        return array_flip(array_combine(
            range($startingSortOrder, count($order)),
            array_keys($order)
        ));
    }
}
