<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\db;

use craft\helpers\Db;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use flipbox\ember\db\CacheableActiveQuery;
use flipbox\ember\db\traits\AuditAttributes;
use flipbox\ember\db\traits\FixedOrderBy;

/**
 * @method SortableAssociationInterface[] getCachedResult()
 */
abstract class SortableAssociationQuery extends CacheableActiveQuery implements SortableAssociationQueryInterface
{
    use AuditAttributes,
        FixedOrderBy;

    /**
     * @var bool Whether results should be returned in the order specified by [[domain]].
     */
    public $fixedOrder = false;

    /**
     * @inheritdoc
     */
    public $orderBy = 'sortOrder';

    /**
     * @var int|null Sort order
     */
    public $sortOrder;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->select === null) {
            $this->select = ['*'];
        }
    }

    /**
     * @inheritdoc
     * return static
     */
    public function fixedOrder(bool $value = true)
    {
        $this->fixedOrder = $value;
        return $this;
    }

    /**
     * @inheritdoc
     * return static
     */
    public function sortOrder($value)
    {
        $this->sortOrder = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function prepare($builder)
    {
        if ($this->sortOrder !== null) {
            $this->andWhere(Db::parseParam('sortOrder', $this->sortOrder));
        }

        $this->applyAuditAttributeConditions();
        $this->applyOrderByParams($builder->db);

        return parent::prepare($builder);
    }
}
