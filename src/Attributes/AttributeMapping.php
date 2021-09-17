<?php

namespace UniqKey\Laravel\SCIMServer\Attributes;

use Illuminate\Support\Carbon;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\SCIMHelper;

class AttributeMapping
{
    /** @var string|null */
    protected $id = null;
    /** @var AttributeMapping|null */
    protected $parent = null;
    /** @var string|null */
    protected $key = null;
    /** @var callable */
    protected $read;
    /** @var callable */
    protected $add;
    /** @var callable */
    protected $replace;
    /** @var callable */
    protected $remove;
    /** @var callable */
    protected $writeAfter;
    /** @var callable */
    protected $getSubNode;
    /** @var bool */
    protected $readEnabled = true;
    /** @var bool */
    protected $writeEnabled = true;
    /** @var string|null */
    protected $sortAttribute = null;
    /** @var array|null */
    protected $mappingAssocArray = null;
    /** @var array */
    public $eloquentAttributes = [];
    /** @var string|null */
    public $eloquentReadAttribute = null;
    /** @var string|null */
    protected $defaultSchema = null;
    /** @var string|null */
    protected $schema = null;
    /** @var mixed|null */
    protected $filter = null;
    /** @var mixed|null */
    protected $relationship = null;

    /**
     * Can be always, never, default, request
     */
    public $returned = 'always';

    public const RETURNED_ALWAYS = 'always';
    public const RETURNED_NEVER = 'never';
    public const RETURNED_DEFAULT = 'default';
    public const RETURNED_REQUEST = 'request';

    /**
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function noMapping(AttributeMapping $parent = null): AttributeMapping
    {
        return (new AttributeMapping())
            ->ignoreWrite()
            ->ignoreRead()
            ->setParent($parent);
    }

    /**
     * @param array $mapping
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function arrayOfObjects(
        array $mapping,
        AttributeMapping $parent = null
    ): AttributeMapping {
        return (new Collection())
            ->setStaticCollection($mapping)
            ->setRead(function (&$object) use ($mapping, $parent) {
                $result = [];

                foreach ($mapping as $key => $o) {
                    $element = static::ensureAttributeMappingObject($o)
                        ->setParent($parent)
                        ->read($object);

                    if (null !== $element) {
                        $result[] = $element;
                    }
                }

                return empty($result) ? null : $result;
            });
    }

    /**
     * @param array $mapping
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function object(
        array $mapping,
        AttributeMapping $parent = null
    ): AttributeMapping {
        return (new AttributeMapping())
            ->setMappingAssocArray($mapping)
            ->setRead(function (&$object) use ($mapping, $parent) {
                $result = [];

                foreach ($mapping as $key => $value) {
                    $result[$key] = static::ensureAttributeMappingObject($value)
                        ->setParent($parent)
                        ->read($object);

                    if (empty($result[$key])
                    &&  false === is_bool($result[$key])) {
                        unset($result[$key]);
                    }
                }

                return empty($result) ? null : $result;
            });
    }

    /**
     * @param mixed $constant
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function constant(
        $constant,
        AttributeMapping $parent = null
    ): AttributeMapping {
        return (new AttributeMapping())
            ->disableWrite()
            ->setParent($parent)
            ->setRead(function (&$object) use ($constant) {
                return $constant;
            });
    }

    /**
     * @param string $eloquentAttribute
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function eloquent(
        string $eloquentAttribute,
        AttributeMapping $parent = null
    ): AttributeMapping {
        return (new EloquentAttributeMapping())
            ->setEloquentReadAttribute($eloquentAttribute)
            ->setParent($parent)
            ->setAdd(function ($value, &$object) use ($eloquentAttribute) {
                $object->{$eloquentAttribute} = $value;
            })
            ->setReplace(function ($value, &$object) use ($eloquentAttribute) {
                $object->{$eloquentAttribute} = $value;
            })
            ->setSortAttribute($eloquentAttribute)
            ->setEloquentAttributes([$eloquentAttribute,]);
    }

    /**
     * @param string $eloquentAttribute
     * @param AttributeMapping|null $parent
     * @return AttributeMapping
     */
    public static function eloquentCollection(
        string $eloquentAttribute,
        AttributeMapping $parent = null
    ): AttributeMapping {
        return (new AttributeMapping())
            ->setParent($parent)
            ->setRead(function (&$object) use ($eloquentAttribute) {
                $result = $object->{$eloquentAttribute};
                return static::eloquentAttributeToString($result);
            })
            ->setAdd(function ($value, &$object) use ($eloquentAttribute) {
                if (false === is_array($value)) {
                    $value = [$value];
                }
                $object->{$eloquentAttribute}()
                    ->attach(collect($value)->pluck('value'));
            })
            ->setReplace(function ($value, &$object) use ($eloquentAttribute) {
                $object->{$eloquentAttribute}()
                    ->sync(collect($value)->pluck('value'));
            })
            ->setSortAttribute($eloquentAttribute)
            ->setEloquentAttributes([$eloquentAttribute,]);
    }

    /**
     * @param array $mapping
     * @return $this
     */
    public function setMappingAssocArray(array $mapping): AttributeMapping
    {
        $this->mappingAssocArray = $mapping;
        return $this;
    }

    /**
     * @param string|null $schema
     * @return $this
     */
    public function setSchema(?string $schema): AttributeMapping
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @param string|null $schema
     * @return $this
     */
    public function setDefaultSchema(?string $schema): AttributeMapping
    {
        $this->defaultSchema = $schema;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefaultSchema(): ?string
    {
        return $this->defaultSchema;
    }

    /**
     * @param string $attribute
     * @return $this
     */
    public function setEloquentReadAttribute(string $attribute): AttributeMapping
    {
        $this->eloquentReadAttribute = $attribute;
        return $this;
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function setEloquentAttributes(array $attributes): AttributeMapping
    {
        $this->eloquentAttributes = $attributes;
        return $this;
    }

    /**
     * @return array
     */
    public function getEloquentAttributes(): array
    {
        $result = $this->eloquentAttributes;

        if ($this->mappingAssocArray) {
            foreach ($this->mappingAssocArray as $key => $value) {
                $attributes = static::ensureAttributeMappingObject($value)
                    ->setParent($this)
                    ->getEloquentAttributes();

                foreach ($attributes as $attribute) {
                    $result[] = $attribute;
                }
            }
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function disableRead(): AttributeMapping
    {
        $this->read = function (&$object) {
            return null; // disabled
        };

        $this->readEnabled = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreRead(): AttributeMapping
    {
        $this->read = function (&$object) {
            return null;
        };

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreWrite(): AttributeMapping
    {
        $ignore = function ($value, &$object) {
            // do nothing
        };

        $this->add = $ignore;
        $this->replace = $ignore;
        $this->remove = $ignore;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableWrite(): AttributeMapping
    {
        $disable = function ($value, &$object) {
            throw (new SCIMException(sprintf('Write to "%s" is not supported', $this->getFullKey())))
                ->setHttpCode(400)
                ->setScimType('mutability');
        };

        $this->add = $disable;
        $this->replace = $disable;
        $this->remove = $disable;

        $this->writeEnabled = false;

        return $this;
    }

    /**
     * @param string $returned
     * @return $this
     */
    public function setReturned(string $returned): AttributeMapping
    {
        $this->returned = $returned;
        return $this;
    }

    /**
     * @param callable $read
     * @return $this
     */
    public function setRead(callable $read): AttributeMapping
    {
        $this->read = $read;
        return $this;
    }

    /**
     * @param callable $write
     * @return $this
     */
    public function setAdd(callable $write): AttributeMapping
    {
        $this->add = $write;
        return $this;
    }

    /**
     * @param callable $write
     * @return $this
     */
    public function setRemove(callable $write): AttributeMapping
    {
        $this->remove = $write;
        return $this;
    }

    /**
     * @param callable $replace
     * @return $this
     */
    public function setReplace(callable $replace): AttributeMapping
    {
        $this->replace = $replace;
        return $this;
    }

    /**
     * @param callable $writeAfter
     * @return $this
     */
    public function setWriteAfter(callable $writeAfter): AttributeMapping
    {
        $this->writeAfter = $writeAfter;
        return $this;
    }

    /**
     * @param AttributeMapping|null $parent
     * @return $this
     */
    public function setParent(?AttributeMapping $parent): AttributeMapping
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @param mixed $parent
     * @return $this
     */
    public function setFilter($filter): AttributeMapping
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @param mixed $parent
     * @return $this
     */
    public function withFilter($filter): AttributeMapping
    {
        return $this->setFilter($filter);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey(string $key): AttributeMapping
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getFullKey(): string
    {
        $parent = $this->parent;

        $fullKey = [];

        while (null !== $parent) {
            array_unshift($fullKey, $parent->getKey());
            $parent = $parent->parent;
        }

        $fullKey[] = $this->getKey();

        // There is an ugly hack.
        $fullKey = array_filter($fullKey, function ($value) {
            return !empty($value);
        });

        return resolve(SCIMHelper::class)->getFlattenKey(
            $fullKey,
            [$this->getSchema() ?? $this->getDefaultSchema(),]
        );
    }

    /**
     * @param mixed $object
     * @throws SCIMException
     */
    public function readNotImplemented($object)
    {
        throw (new SCIMException(sprintf('Read is not implemented for "%s"', $this->getFullKey())))
            ->setHttpCode(500);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     * @throws SCIMException
     */
    public function replaceNotImplemented($value, &$object)
    {
        throw (new SCIMException(sprintf('Replace is not implemented for "%s"', $this->getFullKey())))
            ->setHttpCode(500);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     * @throws SCIMException
     */
    public function writeNotImplemented($value, &$object)
    {
        throw (new SCIMException(sprintf('Write is not implemented for "%s"', $this->getFullKey())))
            ->setHttpCode(500);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     */
    public function writeAfterIgnore($value, &$object)
    {
        // do nothing
    }

    /**
     * @param mixed $value
     * @param mixed $object
     */
    public function defaultRemove($value, &$object)
    {
        // do nothing
    }

    /**
     * @param string|null $attribute
     * @return $this
     */
    public function setSortAttribute(?string $attribute): AttributeMapping
    {
        $this->sortAttribute = $attribute;
        return $this;
    }

    /**
     * @return string|null
     * @throws SCIMException
     */
    public function getSortAttribute(): ?string
    {
        if (false === $this->readEnabled) {
            throw (new SCIMException(sprintf('Can\'t sort on unreadable attribute "%s"', $this->getFullKey())))
                ->setHttpCode(400);
        }

        return $this->sortAttribute;
    }

    /**
     * @param mixed $object
     * @return mixed
     */
    public function read(&$object)
    {
        if (null !== $this->read) {
            return ($this->read)($object);
        } else {
            return $this->readNotImplemented($object);
        }
    }

    /**
     * @param mixed $value
     * @param mixed $object
     * @return mixed
     */
    public function add($value, &$object)
    {
        if (null !== $this->add) {
            return ($this->add)($value, $object);
        } else {
            return $this->writeNotImplemented($value, $object);
        }
    }

    /**
     * @param mixed $value
     * @param mixed $object
     * @return mixed
     */
    public function writeAfter($value, &$object)
    {
        if (null !== $this->writeAfter) {
            return ($this->writeAfter)($value, $object);
        } else {
            return $this->writeAfterIgnore($value, $object);
        }
    }

    /**
     * @todo Really implement replace ...???
     *
     * @param mixed $value
     * @param mixed $object
     * @return mixed
     */
    public function replace($value, &$object)
    {
        if (null !== $this->replace) {
            return ($this->replace)($value, $object);
        }
        if(null !== $this->parent && null !== $this->parent->collection){
            if(is_array($this->parent->collection) && count($this->parent->collection)){
                foreach ($this->parent->collection as $item){
                    ($item['value']->replace)($value, $object);
                }
                return true;
            }
        }

        return $this->replaceNotImplemented($value, $object);
    }

    /**
     * @todo Implement remove for multi valued attributes
     *
     * @param mixed $value
     * @param mixed $object
     * @return mixed
     */
    public function remove($value, &$object)
    {
        if (null !== $this->remove) {
            return ($this->remove)($value, $object);
        } else {
            return $this->defaultRemove($value, $object);
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function eloquentAttributeToString($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            $value = $value->format('c');
        }

        return $value;
    }

    /**
     * @return bool
     */
    public function isReadSupported(): bool
    {
        return $this->readEnabled;
    }

    /**
     * @return bool
     */
    public function isWriteSupported(): bool
    {
        return $this->writeEnabled;
    }

    /**
     * @param mixed $attributeMapping
     * @param AttributeMapping|null $parent
     * @return AttributeMapping|null
     * @throws SCIMException
     */
    public static function ensureAttributeMappingObject(
        $attributeMapping,
        AttributeMapping $parent = null
    ): ?AttributeMapping {
        $result = null;

        if (null === $attributeMapping) {
            $result = static::noMapping($parent);
        } elseif (is_array($attributeMapping)) {
            if (!empty($attributeMapping) && isset($attributeMapping[0])) {
                $result = static::arrayOfObjects($attributeMapping, $parent);
            } else {
                $result = static::object($attributeMapping, $parent);
            }
        } elseif ($attributeMapping instanceof AttributeMapping) {
            $result = $attributeMapping->setParent($parent);
        } else {
            throw (new SCIMException(sprintf('Found unknown attribute "%s"', $attributeMapping)))
                ->setHttpCode(400);
        }

        return $result;
    }

    /**
     * @param mixed $relationship
     * @return AttributeMapping
     */
    public function setRelationship($relationship): AttributeMapping
    {
        $this->relationship = $relationship;
        return $this;
    }

    /**
     * @param callable $closure
     * @return AttributeMapping
     */
    public function setGetSubNode(callable $closure): AttributeMapping
    {
        $this->getSubNode = $closure;
        return $this;
    }

    /**
     * Returns the AttributeMapping for a specific value.
     * Uses for example for creating queries ... and sorting
     *
     * @param string|null $key
     * @param string|null $schema
     * @return AttributeMapping|null
     * @throws SCIMException
     */
    public function getSubNode(?string $key, string $schema = null): ?AttributeMapping
    {
        if (null !== $this->getSubNode) {
            return ($this->getSubNode)($key, $schema);
        }

        if (null === $key) {
            return $this;
        }

        if (null !== $this->mappingAssocArray
        &&  true === array_key_exists($key, $this->mappingAssocArray)) {
            return static::ensureAttributeMappingObject($this->mappingAssocArray[$key])
                ->setParent($this)
                ->setKey($key)
                ->setSchema($schema);
        } else {
            return $this->noMapping($this);
        }
    }

    /**
     * @param mixed $path
     * @return AttributeMapping|null
     */
    public function getSubNodeWithPath($path): ?AttributeMapping
    {
        if (null === $path) {
            return $this;
        }

        /**
         * https://www.php.net/manual/en/closure.call.php
         */
        $getAttributePath = function () {
            return $this->attributePath;
        };
        $getValuePath = function () {
            return $this->valuePath;
        };
        $getFilter = function () {
            return $this->filter;
        };

        $first = @$getAttributePath->call($getValuePath->call($path));
        $filter = @$getFilter->call($getValuePath->call($path));
        $last = $getAttributePath->call($path);

        return $this->getNode($first)
            ->withFilter($filter)
            ->getNode($last);
    }

    /**
     * @param mixed $attributePath
     * @return AttributeMapping|null
     * @throws SCIMException
     */
    public function getNode($attributePath): ?AttributeMapping
    {
        if (true === empty($attributePath)) {
            return $this;
        }

        /**
         * The first schema should be the default one.
         */
        $schema = $attributePath->schema ?? $this->getDefaultSchema();

        if (false === empty($schema)
        &&  false === empty($this->getSchema())
        &&  $this->getSchema() != $schema) {
            throw (new SCIMException(sprintf(
                'Trying to get attribute for schema "%s". But schema is already "%s"',
                $attributePath->schema,
                $this->getSchema()
            )))
            ->setHttpCode(400)
            ->setScimType('noTarget');
        }

        $elements = [];

        /**
         * The attribute mapping MUST include the schema.
         * Therefore, add the schema to the first element.
         */
        if (empty($attributePath->attributeNames) && !empty($schema)) {
            $elements[] = $schema;
        } elseif (empty($this->getSchema()) && !in_array($attributePath->attributeNames[0], Schema::ATTRIBUTES_CORE)) {
            $elements[] = $schema ?? $this->getDefaultSchema();
        }

        foreach ($attributePath->attributeNames as $a) {
            $elements[] = $a;
        }

        /** @var AttributeMapping */
        $node = $this;

        foreach ($elements as $element) {
            $node = $node->getSubNode($element, $schema);
        }

        return $node;
    }

    /**
     * @param mixed $attribute
     * @param mixed $query
     * @param string $operator
     * @param mixed $value
     * @throws SCIMException
     */
    public function applyWhereConditionDirect($attribute, &$query, string $operator, $value)
    {
        switch ($operator) {
            case 'eq':
                $query->where($attribute, $value);
                break;
            case 'ne':
                $query->where($attribute, '<>', $value);
                break;
            case 'co':
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_') . '%');
                break;
            case 'sw':
                $query->where($attribute, 'like', addcslashes($value, '%_') . '%');
                break;
            case 'ew':
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_'));
                break;
            case 'pr':
                $query->whereNotNull($attribute);
                break;
            case 'gt':
                $query->where($attribute, '>', $value);
                break;
            case 'ge':
                $query->where($attribute, '>=', $value);
                break;
            case 'lt':
                $query->where($attribute, '<', $value);
                break;
            case 'le':
                $query->where($attribute, '<=', $value);
                break;
            default:
                throw (new SCIMException("Not supported operator \"{$operator}\""))
                    ->setHttpCode(400);
                break;
        }
    }

    /**
     * @param mixed $query
     * @param string $operator
     * @param mixed $value
     * @param array $extra  some extra data, e.g. Collection uses this one..
     * @throws SCIMException
     */
    public function applyWhereCondition(&$query, string $operator, $value, array $extra = [])
    {
        //only filter on OWN eloquent attributes
        if (empty($this->eloquentAttributes)) {
            throw (new SCIMException("Can't filter on \"{$this->getFullKey()}\""))
                ->setHttpCode(400);
        }

        $attribute = $this->eloquentAttributes[0];

        if (null !== $this->relationship) {
            $query->whereHas($this->relationship, function ($query) use ($attribute, $operator, $value) {
                $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
            })->get();
        } else {
            $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
        }
    }
}
