<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\db;

/**
 * Interface AssociationQueryInterface
 * @package flipbox\craft\sortable\associations\db
 *
 * @property int $siteId
 * @property int $sortOrder
 */
interface SortableAssociationQueryInterface
{
    /**
     * Returns the resulting records set by [[setCachedResult()]], if the criteria params haven’t changed since then.
     *
     * @return array|null The resulting records, or null if setCachedResult() was never called or the criteria has
     * changed
     * @see setCachedResult()
     */
    public function getCachedResult();

    /**
     * Sets the resulting records.
     *
     * If this is called, [[all()]] will return these records rather than initiating a new SQL query,
     * as long as none of the parameters have changed since setCachedResult() was called.
     *
     * @param array $objects The resulting objects.
     *
     * @see getCachedResult()
     */
    public function setCachedResult(array $objects);
}
