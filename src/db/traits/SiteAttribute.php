<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/domains/license
 * @link       https://www.flipboxfactory.com/software/domains/
 */

namespace flipbox\craft\sortable\associations\db\traits;

use Craft;
use craft\helpers\Db;
use yii\db\Expression;

trait SiteAttribute
{
    /**
     * @var int|int[]|false|null The site ID(s). Prefix IDs with "not " to exclude them.
     */
    public $siteId;

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the `AND` operator.
     * @param string|array|Expression $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see where()
     * @see orWhere()
     */
    abstract public function andWhere($condition, $params = []);

    /**
     * @param $value
     * @return static
     */
    public function siteId($value)
    {
        $this->siteId = $value;
        return $this;
    }

    /**
     * @param $value
     * @return static
     */
    public function site($value)
    {
        return $this->siteId($value);
    }

    /**
     * Apply attribute conditions
     */
    protected function applySiteConditions()
    {
        if ($this->siteId !== null) {
            $this->andWhere(Db::parseParam('siteId', $this->siteId));
        } else {
            $this->andWhere(Db::parseParam('siteId', Craft::$app->getSites()->currentSite->id));
        }
    }
}
