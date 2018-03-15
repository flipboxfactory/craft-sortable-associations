<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-sortable-associations/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-sortable-associations
 */

namespace flipbox\craft\sortable\associations\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Db;
use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use yii\base\Component;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
abstract class SortableFields extends Component
{
    /**
     * The source attribute name
     */
    const SOURCE_ATTRIBUTE = '';

    /**
     * The target attribute name
     */
    const TARGET_ATTRIBUTE = '';

    /**
     * The table alias
     */
    const TABLE_ALIAS = '';

    /**
     * @param FieldInterface $field
     * @param ElementInterface|null $element
     * @return SortableAssociationQueryInterface
     */
    abstract protected function getQuery(
        FieldInterface $field,
        ElementInterface $element = null
    ): SortableAssociationQueryInterface;

    /**
     * @param FieldInterface $field
     * @param $value
     * @param int $sortOrder
     * @param ElementInterface|null $element
     * @return SortableAssociationInterface
     */
    abstract protected function normalizeQueryInputValue(
        FieldInterface $field,
        $value,
        int &$sortOrder,
        ElementInterface $element = null
    ): SortableAssociationInterface;

    /*******************************************
     * MODIFY ELEMENT QUERY
     *******************************************/

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(
        FieldInterface $field,
        ElementQueryInterface $query,
        $value
    ) {
        if ($value === null || !$query instanceof ElementQuery) {
            return null;
        }

        if ($value === false) {
            return false;
        }

        if (is_string($value)) {
            $this->modifyElementsQueryForStringValue($field, $query, $value);
            return null;
        }

        $this->modifyElementsQueryForTargetValue($field, $query, $value);
        return null;
    }

    /**
     * @param FieldInterface $field
     * @param ElementQuery $query
     * @param string $value
     */
    protected function modifyElementsQueryForStringValue(
        FieldInterface $field,
        ElementQuery $query,
        string $value
    ) {
        if ($value === 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value === ':notempty:' || $value === ':empty:') {
            $this->modifyElementsQueryForEmptyValue($field, $query, $value);
            return;
        }

        $this->modifyElementsQueryForTargetValue($field, $query, $value);
    }

    /**
     * @param FieldInterface $field
     * @param ElementQuery $query
     * @param $value
     */
    protected function modifyElementsQueryForTargetValue(
        FieldInterface $field,
        ElementQuery $query,
        $value
    ) {
        $alias = static::TABLE_ALIAS;
        $name = '{{%' . static::TABLE_ALIAS . '}}';

        $joinTable = "{$name} {$alias}";
        $query->query->innerJoin($joinTable, "[[{$alias}." . static::SOURCE_ATTRIBUTE . "]] = [[subquery.elementsId]]");
        $query->subQuery->innerJoin($joinTable, "[[{$alias}." . static::SOURCE_ATTRIBUTE . "]] = [[elements.id]]");

        $query->subQuery->andWhere(
            Db::parseParam($alias . '.fieldId', $field->id)
        );

        $query->subQuery->andWhere(
            Db::parseParam($alias . '.' . static::TARGET_ATTRIBUTE, $value)
        );

        $query->query->distinct(true);
    }

    /**
     * @param FieldInterface $field
     * @param ElementQuery $query
     * @param string $value
     */
    protected function modifyElementsQueryForEmptyValue(
        FieldInterface $field,
        ElementQuery $query,
        string $value
    ) {
        $operator = ($value === ':notempty:' ? '!=' : '=');
        $query->subQuery->andWhere(
            $this->emptyValueSubSelect(
                $field,
                static::TABLE_ALIAS,
                '{{%' . static::TABLE_ALIAS . '}}',
                $operator
            )
        );
    }

    /**
     * @param FieldInterface $field
     * @param string $alias
     * @param string $name
     * @param string $operator
     * @return string
     */
    protected function emptyValueSubSelect(
        FieldInterface $field,
        string $alias,
        string $name,
        string $operator
    ): string {
        return "(select count([[{$alias}." . static::SOURCE_ATTRIBUTE . "]]) from " .
            $name .
            " {{{$alias}}} where [[{$alias}." . static::SOURCE_ATTRIBUTE .
            "]] = [[elements.id]] and [[{$alias}.fieldId]] = {$field->id}) {$operator} 0";
    }


    /*******************************************
     * NORMALIZE VALUE
     *******************************************/

    /**
     * @param FieldInterface $field
     * @param $value
     * @param ElementInterface|null $element
     * @return SortableAssociationQueryInterface
     */
    public function normalizeValue(
        FieldInterface $field,
        $value,
        ElementInterface $element = null
    ): SortableAssociationQueryInterface {
        if ($value instanceof SortableAssociationQueryInterface) {
            return $value;
        }
        $query = $this->getQuery($field, $element);
        $this->normalizeQueryValue($field, $query, $value, $element);
        return $query;
    }

    /**
     * @param FieldInterface $field
     * @param SortableAssociationQueryInterface $query
     * @param ElementInterface|null $element
     */
    protected function normalizeQuery(
        FieldInterface $field,
        SortableAssociationQueryInterface $query,
        ElementInterface $element = null
    ) {
        $query->{static::SOURCE_ATTRIBUTE} = (
            $element === null || $element->getId() === null
        ) ? false : $element->getId();
    }

    /**
     * @param FieldInterface $field
     * @param SortableAssociationQueryInterface $query
     * @param $value
     * @param ElementInterface|null $element
     */
    protected function normalizeQueryValue(
        FieldInterface $field,
        SortableAssociationQueryInterface $query,
        $value,
        ElementInterface $element = null
    ) {
        $this->normalizeQuery($field, $query, $element);

        if (is_array($value)) {
            $this->normalizeQueryInputValues($field, $query, $value, $element);
            return;
        }

        if ($value === '') {
            $this->normalizeQueryEmptyValue($field, $query);
            return;
        }
    }

    /**
     * @param FieldInterface $field
     * @param SortableAssociationQueryInterface $query
     * @param array $value
     * @param ElementInterface|null $element
     */
    protected function normalizeQueryInputValues(
        FieldInterface $field,
        SortableAssociationQueryInterface $query,
        array $value,
        ElementInterface $element = null
    ) {
        $models = [];
        $sortOrder = 1;
        foreach ($value as $val) {
            $models[] = $this->normalizeQueryInputValue($field, $val, $sortOrder, $element);
        }
        $query->setCachedResult($models);
    }

    /**
     * @param FieldInterface $field
     * @param SortableAssociationQueryInterface $query
     */
    protected function normalizeQueryEmptyValue(
        FieldInterface $field,
        SortableAssociationQueryInterface $query
    ) {
        $query->setCachedResult([]);
    }

    /**
     * Returns the site ID that target elements should have.
     *
     * @param ElementInterface|Element|null $element
     *
     * @return int
     */
    protected function targetSiteId(ElementInterface $element = null): int
    {
        /** @var Element $element */
        if (Craft::$app->getIsMultiSite() === true && $element !== null) {
            return $element->siteId;
        }

        return Craft::$app->getSites()->currentSite->id;
    }
}
