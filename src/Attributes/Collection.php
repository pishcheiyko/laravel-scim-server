<?php

namespace UniqKey\Laravel\SCIMServer\Attributes;

use Closure;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;

class Collection extends AttributeMapping
{
    /** @var array */
    protected $collection = [];

    /**
     * @param array $collection
     * @return $this
     */
    public function setStaticCollection(array $collection)
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @todo See 'TODO' below
     */
    public function add($value, &$object)
    {
        if (null === $object->id) {  // only for creation requests
            foreach ($value as $key => $v) {
                $this->getSubNode($key)->add($v, $object);
            }
        } else {
            foreach ($value as $key => $v) {
                $subnode = $this->getSubNode($key);

                if (null !== $subnode) {
                    $subnode->add($v, $object);
                } else {
                    // TODO: log ignore
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove($value, &$object)
    {
        foreach ($this->collection as $c) {
            foreach ($c as $k => $v) {
                $mapping = static::ensureAttributeMappingObject($v);

                if ($mapping->isWriteSupported()) {
                    $mapping->remove($value, $object);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function replace($value, &$object)
    {
        $this->remove($value, $object);

        $this->add($value, $object);
    }

    /**
     * {@inheritdoc}
     */
    public function getEloquentAttributes(): array
    {
        $result = $this->eloquentAttributes;

        foreach ($this->collection as $value) {
            $items = static::ensureAttributeMappingObject($value)->getEloquentAttributes();
            $result = array_merge($result, $items);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubNode($key, $schema = null)
    {
        if (null === $key) {
            return $this;
        } elseif (false === isset($this->collection[0][$key])) {
            return null;
        }

        $parent = $this;

        return (new CollectionValue())
            ->setEloquentAttributes($this->collection[0][$key]->getEloquentAttributes())
            ->setKey($key)
            ->setParent($this)
            ->setAdd(function ($value, &$object) use ($key, $parent) {
                $collection = Collection::filterCollection(
                    $parent->filter,
                    collect($parent->collection),
                    $object
                );

                $result = [];

                foreach ($collection as $o) {
                    $o[$key]->add($value, $object);
                }
            })->setRead(function (&$object) use ($parent) {
                $collection = Collection::filterCollection(
                    $parent->filter,
                    collect($parent->collection),
                    $object
                );

                $result = [];

                foreach ($collection as $o) {
                    $result = static::ensureAttributeMappingObject($o);
                }

                return $result;
            })->setSchema($schema);
    }

    /**
     * @param mixed $scimFilter
     * @param LaravelCollection $collection
     * @param Model $resourceObject
     * @return LaravelCollection
     * @throws SCIMException
     */
    public static function filterCollection(
        $scimFilter,
        LaravelCollection $collection,
        Model $resourceObject
    ): LaravelCollection {
        if (null === $scimFilter) {
            return $collection;
        }

        $attribute = $scimFilter->attributePath->attributeNames[0];
        $operator = $scimFilter->operator;
        $compareValue = $scimFilter->compareValue;

        $result = [];

        foreach ($collection->toArray() as $value) {
            $result[] = static::ensureAttributeMappingObject($value)->read($resourceObject);
        }

        $collectionOriginal = $collection;
        $collection = collect($result);

        switch ($operator) {
            case 'eq':
                $result = $collection->where($attribute, '==', $compareValue);
                break;
            case 'ne':
                $result = $collection->where($attribute, '<>', $compareValue);
                break;
            case 'pr':
                $result = $collection->where($attribute, '!=', null);
                break;
            case 'gt':
                $result = $collection->where($attribute, '>', $compareValue);
                break;
            case 'ge':
                $result = $collection->where($attribute, '>=', $compareValue);
                break;
            case 'lt':
                $result = $collection->where($attribute, '<', $compareValue);
                break;
            case 'le':
                $result = $collection->where($attribute, '<=', $compareValue);
                break;
            case 'co':
            case 'sw':
            case 'ew':
                throw (new SCIMException("'{$operator}' is not supported for attribute '{$attribute}'"))
                    ->setHttpCode(501);
                break;
            default:
                throw (new SCIMException("Not supported operator '{$operator}'"))
                    ->setHttpCode(501);
                break;
        }

        $allKeys = (array)$result->keys()->all();

        foreach ($collectionOriginal->keys()->all() as $key) {
            if (false === in_array($key, $allKeys)) {
                unset($collectionOriginal[$key]);
            }
        }

        return $collectionOriginal;
    }

    /**
     * Get an operator checker callback.
     *
     * @param string $key
     * @param string $operator
     * @param mixed|null $value
     * @return Closure
     */
    protected function operatorForWhere(
        string $key,
        string $operator,
        $value = null
    ): Closure {
        if (2 === func_num_args()) {
            $value = $operator;
            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            $strings = array_filter([$retrieved, $value,], function ($value) {
                return is_string($value)
                    || (is_object($value) && method_exists($value, '__toString'));
            });

            if (2 >= count($strings)
            &&  1 == count(array_filter([$retrieved, $value,], 'is_object'))) {
                return in_array($operator, ['!=', '<>', '!==',]);
            }

            switch ($operator) {
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
                case '=':
                case '==':
                default:
                    return $retrieved == $value;
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function applyWhereCondition(&$query, string $operator, $value)
    {
        throw (new SCIMException("Filter is not supported for attribute '{$this->getFullKey()}'"))
            ->setHttpCode(501);
    }
}
