<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\records;

use flipbox\ember\helpers\ModelHelper;
use flipbox\ember\records\ActiveRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
abstract class SortableAssociation extends ActiveRecord implements SortableAssociationInterface
{
    /**
     * @inheritdoc
     */
    const TARGET_ATTRIBUTE = '';

    /**
     * @inheritdoc
     */
    const SOURCE_ATTRIBUTE = '';

    /**
     * @return array
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            $this->auditRules(),
            [
                [
                    [
                        static::SOURCE_ATTRIBUTE,
                        static::TARGET_ATTRIBUTE,
                    ],
                    'required'
                ],
                [
                    [
                        'sortOrder'
                    ],
                    'number',
                    'integerOnly' => true
                ],
                [
                    [
                        static::SOURCE_ATTRIBUTE,
                        static::TARGET_ATTRIBUTE,
                        'sortOrder'
                    ],
                    'safe',
                    'on' => [
                        ModelHelper::SCENARIO_DEFAULT
                    ]
                ]
            ]
        );
    }
}
