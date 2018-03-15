<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\records;

use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use yii\db\ActiveRecordInterface;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @property int $sortOrder
 *
 * @method static SortableAssociationQueryInterface find()
 */
interface SortableAssociationInterface extends ActiveRecordInterface
{
    /**
     * Save association and preform a subsequent order routine.
     *
     * @param bool $autoReorder
     * @return bool
     */
    public function associate(bool $autoReorder = true): bool;

    /**
     * Delete association and preform a subsequent order routine.
     *
     * @param bool $autoReorder
     * @return bool
     */
    public function dissociate(bool $autoReorder = true): bool;
}
