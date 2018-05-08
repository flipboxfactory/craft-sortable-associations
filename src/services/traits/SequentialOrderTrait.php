<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\services\traits;

use craft\helpers\ArrayHelper;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.1
 */
trait SequentialOrderTrait
{
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
        $this->ensureSequential($sourceArray);

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

    /**
     * @param array $sourceArray
     */
    private function ensureSequential(array &$sourceArray)
    {
        $ct = 1;
        foreach ($sourceArray as $key => &$sortOrder) {
            $sortOrder = (int) $sortOrder ?: $ct++;

            if ($sortOrder > $ct) {
                $ct = $sortOrder + 1;
            }
        }
    }
}
