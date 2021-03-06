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
     */
    public function add($value, &$object)
    {
        if (null === $object->id) {  // only for creation requests
            foreach ($value as $key => $v) {
                $this->getSubNode($key)
                    ->add($v, $object);
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
    public function getSubNode(?string $key, string $schema = null): ?AttributeMapping
    {
        if (null === $key) {
            return $this;
        }

        if (true === empty($this->collection)
        ||  false === is_array($this->collection[0])
        ||  false === array_key_exists($key, $this->collection[0])) {
            return $this;
        }

        $parent = $this;

        $eloquentAttributes = static::ensureAttributeMappingObject(
            $this->collection[0][$key]
        )->getEloquentAttributes();

        return (new CollectionValue())
            ->setEloquentAttributes($eloquentAttributes)
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
            $result[] = static::ensureAttributeMappingObject($value)
                ->read($resourceObject);
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
                    ->setHttpCode(400);
                break;
            default:
                throw (new SCIMException("Not supported operator '{$operator}'"))
                    ->setHttpCode(400);
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
     *
     * @throws SCIMException
     */
    public function applyWhereCondition(&$query, string $operator, $value, array $extra = [])
    {
        if (false === isset($extra['key'])  // JIC
        ||  true === empty($this->collection)
        ||  false === is_array($this->collection[0])
        ||  false === array_key_exists($extra['key'], $this->collection[0])) {
            throw (new SCIMException('Internal error'))
                ->setHttpCode(500);
        }

        $key = $extra['key'];
        $mapping = static::ensureAttributeMappingObject($this->collection[0][$key]);
        return $mapping->applyWhereCondition($query, $operator, $value);
    }
}
