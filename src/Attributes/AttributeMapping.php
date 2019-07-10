<?php

namespace UniqKey\Laravel\SCIMServer\Attributes;

use Illuminate\Support\Carbon;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\Helper;

class AttributeMapping
{
    public $read;
    public $add;
    public $replace;
    public $remove;
    public $writeAfter;
    public $getSubNode;
    public $id = null;
    public $parent = null;
    public $filter = null;
    public $key = null;
    protected $readEnabled = true;
    protected $writeEnabled = true;
    protected $sortAttribute = null;
    public $relationship = null;
    protected $mappingAssocArray = null;
    public $eloquentAttributes = [];
    public $eloquentReadAttribute = null;
    protected $defaultSchema = null;
    protected $schema = null;

    /**
     * Can be always, never, default, request
     */
    public $returned = 'always';

    public const RETURNED_ALWAYS = 'always';
    public const RETURNED_NEVER = 'never';
    public const RETURNED_DEFAULT = 'default';
    public const RETURNED_REQUEST = 'request';

    public static function noMapping($parent = null) : AttributeMapping
    {
        return (new AttributeMapping())->disableWrite()->ignoreRead()->setParent($parent);
    }

    public static function arrayOfObjects(array $mapping, $parent = null) : AttributeMapping
    {
        return (new Collection())->setStaticCollection($mapping)->setRead(
            function (&$object) use ($mapping, $parent) {
                $result = [];

                foreach ($mapping as $key => $o) {
                    $element = static::ensureAttributeMappingObject($o)->setParent($parent)->read($object);

                    if (null !== $element) {
                        $result[] = $element;
                    }
                }

                return empty($result) ? null : $result;
            }
        );
    }

    public static function object($mapping, $parent = null) : AttributeMapping
    {
        return (new AttributeMapping())->setMappingAssocArray($mapping)->setRead(function (&$object) use ($mapping, $parent) {
            $result = [];

            foreach ($mapping as $key => $value) {
                $result[$key] = static::ensureAttributeMappingObject($value)->setParent($parent)->read($object);

                if (empty($result[$key]) && !is_bool($result[$key])) {
                    unset($result[$key]);
                }
            }

            return empty($result) ? null : $result;
        });
    }

    public static function constant($text, $parent = null) : AttributeMapping
    {
        return (new AttributeMapping())->disableWrite()->setParent($parent)->setRead(function (&$object) use ($text) {
            return $text;
        });
    }

    public static function eloquent($eloquentAttribute, $parent = null) : AttributeMapping
    {
        return (new EloquentAttributeMapping())->setEloquentReadAttribute($eloquentAttribute)->setParent($parent)->setAdd(function ($value, &$object) use ($eloquentAttribute) {
            $object->{$eloquentAttribute} = $value;
        })->setReplace(function ($value, &$object) use ($eloquentAttribute) {
            $object->{$eloquentAttribute} = $value;
        })->setSortAttribute($eloquentAttribute)->setEloquentAttributes([$eloquentAttribute]);
    }

    public static function eloquentCollection($eloquentAttribute, $parent = null) : AttributeMapping
    {
        return (new AttributeMapping())->setParent($parent)->setRead(function (&$object) use ($eloquentAttribute) {
            $result = $object->{$eloquentAttribute};
            return static::eloquentAttributeToString($result);
        })->setAdd(function ($value, &$object) use ($eloquentAttribute) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $object->{$eloquentAttribute}()->attach(collect($value)->pluck('value'));
        })->setReplace(function ($value, &$object) use ($eloquentAttribute) {
            $object->{$eloquentAttribute}()->sync(collect($value)->pluck('value'));
        })->setSortAttribute($eloquentAttribute)->setEloquentAttributes([$eloquentAttribute]);
    }

    public function setMappingAssocArray($mapping) : AttributeMapping
    {
        $this->mappingAssocArray = $mapping;
        return $this;
    }

    public function setSchema($schema) : AttributeMapping
    {
        $this->schema = $schema;
        return $this;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function setDefaultSchema($schema) : AttributeMapping
    {
        $this->defaultSchema = $schema;
        return $this;
    }

    public function getDefaultSchema()
    {
        return $this->defaultSchema;
    }

    public function setEloquentReadAttribute($attribute)
    {
        $this->eloquentReadAttribute = $attribute;
        return $this;
    }

    public function setEloquentAttributes(array $attributes)
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
                foreach (static::ensureAttributeMappingObject($value)->setParent($this)->getEloquentAttributes() as $attribute) {
                    $result[] = $attribute;
                }
            }
        }

        return $result;
    }

    public function disableRead()
    {
        $parent = $this;

        $this->read = function (&$object) {
            return null; // "disabled!!";
        };

        $this->readEnabled = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreRead()
    {
        $this->read = function (&$object) {
            return null;
        };

        return $this;
    }

    /**
     * @return $this
     */
    public function ignoreWrite()
    {
        $ignore = function ($value, &$object) {
            //ignore
        };

        $this->add = $ignore;
        $this->replace = $ignore;
        $this->remove = $ignore;

        return $this;
    }

    public function disableWrite()
    {
        $disable = function ($value, &$object) {
            throw (new SCIMException(sprintf('Write to "%s" is not supported', $this->getFullKey())))->setHttpCode(500)->setScimType('mutability');
        };

        $this->add = $disable;
        $this->replace = $disable;
        $this->remove = $disable;

        $this->writeEnabled = false;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRead($read) : AttributeMapping
    {
        $this->read = $read;

        return $this;
    }

    public function setAdd($write)
    {
        $this->add = $write;

        return $this;
    }

    public function setRemove($write)
    {
        $this->remove = $write;

        return $this;
    }

    public function setReturned($returned)
    {
        $this->returned = $returned;

        return $this;
    }

    public function setReplace($replace)
    {
        $this->replace = $replace;

        return $this;
    }

    public function setWriteAfter($writeAfter)
    {
        $this->writeAfter = $writeAfter;

        return $this;
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getFullKey()
    {
        $parent = $this->parent;

        $fullKey = [];

        while ($parent != null) {
            array_unshift($fullKey, $parent->getKey());
            $parent = $parent->parent;
        }

        $fullKey[]  = $this->getKey();

        //ugly hack
        $fullKey = array_filter($fullKey, function ($value) {
            return !empty($value);
        });

        return Helper::getFlattenKey($fullKey, [$this->getSchema() ?? $this->getDefaultSchema()]);
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function readNotImplemented($object)
    {
        throw new SCIMException(sprintf('Read is not implemented for "%s"', $this->getFullKey()));
    }

    public function writeNotImplemented($object)
    {
        throw new SCIMException(sprintf('Write is not implemented for "%s"', $this->getFullKey()));
    }

    public function writeAfterIgnore($value, &$object)
    {
    }

    public function replaceNotImplemented($value, &$object)
    {
        throw new SCIMException(sprintf('Replace is not implemented for "%s"', $this->getFullKey()));
    }

    public function defaultRemove($value, &$object)
    {
    }

    public function __construct()
    {
    }

    public function setSortAttribute($attribute)
    {
        $this->sortAttribute = $attribute;

        return $this;
    }

    public function getSortAttribute()
    {
        if (!$this->readEnabled) {
            throw new SCIMException(sprintf('Can\'t sort on unreadable attribute "%s"', $this->getFullKey()));
        }

        return $this->sortAttribute;
    }

    public function withFilter($filter)
    {
        return $this->setFilter($filter);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     */
    public function add($value, &$object)
    {
        return $this->add ? ($this->add)($value, $object) : $this->writeNotImplemented($object);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     */
    public function replace($value, &$object)
    {
        $current = $this->read($object);

        //TODO: Really implement replace ...???
        return $this->replace ? ($this->replace)($value, $object) : $this->replaceNotImplemented($value, $object);
    }

    /**
     * @param mixed $value
     * @param mixed $object
     */
    public function remove($value, &$object)
    {
        //TODO: implement remove for multi valued attributes
        return $this->remove ? ($this->remove)($value, $object) : $this->defaultRemove($value, $object);
    }

    public function writeAfter($value, &$object)
    {
        return $this->writeAfter ? ($this->writeAfter)($value, $object) : $this->writeAfterIgnore($value, $object);
    }

    public function read(&$object)
    {
        return $this->read ? ($this->read)($object) : $this->readNotImplemented($object);
    }

    public static function eloquentAttributeToString($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            $value = $value->format('c');
        }

        return $value;
    }

    public function isReadSupported()
    {
        return $this->readEnabled;
    }

    public function isWriteSupported()
    {
        return $this->writeEnabled;
    }

    public static function ensureAttributeMappingObject($attributeMapping, $parent = null) : ?AttributeMapping
    {
        $result = null;

        if (null === $attributeMapping) {
            $result = static::noMapping($parent);
        } elseif (is_array($attributeMapping) && !empty($attributeMapping) && isset($attributeMapping[0])) {
            $result = static::arrayOfObjects($attributeMapping, $parent);
        } elseif (is_array($attributeMapping)) {
            $result = static::object($attributeMapping, $parent);
        } elseif ($attributeMapping instanceof AttributeMapping) {
            $result = $attributeMapping->setParent($parent);
        } else {
            throw (new SCIMException(sprintf('Found unknown attribute "%s"', $attributeMapping)))
                ->setHttpCode(500);
        }

        return $result;
    }

    /**
     * Returns the AttributeMapping for a specific value. Uses for example for creating queries ... and sorting
     *
     * @param mixed $key
     * @param mixed|null $schema
     * @return $this|null
     * @throws SCIMException
     */
    public function getSubNode($key, $schema = null)
    {
        if ($this->getSubNode != null) {
            return ($this->getSubNode)($key, $schema);
        }

        if ($key == null) {
            return $this;
        }

        if ($this->mappingAssocArray != null && array_key_exists($key, $this->mappingAssocArray)) {
            return static::ensureAttributeMappingObject($this->mappingAssocArray[$key])->setParent($this)->setKey($key)->setSchema($schema);
        } else {
            throw new SCIMException(sprintf('No mapping for "%s" in "%s"', $key, $this->getFullKey()));
        }
    }

    public function setGetSubNode($closure)
    {
        $this->getSubNode = $closure;

        return $this;
    }

    public function setRelationship($relationship)
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getNode($attributePath)
    {
        if (empty($attributePath)) {
            return $this;
        }

        //The first schema should be the default one
        $schema = $attributePath->schema ?? $this->getDefaultSchema()[0];

        if (!empty($schema) && !empty($this->getSchema()) && $this->getSchema() != $schema) {
            throw (new SCIMException(sprintf('Trying to get attribute for schema "%s". But schema is already "%s"', $attributePath->schema, $this->getSchema())))->setHttpCode(500)->setScimType('noTarget');
        }

        $elements = [];

        // The attribute mapping MUST include the schema. Therefore, add the schema to the first element.
        if (empty($attributePath->attributeNames) && !empty($schema)) {
            $elements[] = $schema;
        } elseif (empty($this->getSchema()) && !in_array($attributePath->attributeNames[0], Schema::ATTRIBUTES_CORE)) {
            $elements[] = $schema ?? (is_array($this->getDefaultSchema()) ? $this->getDefaultSchema()[0] : $this->getDefaultSchema());
        }

        foreach ($attributePath->attributeNames as $a) {
            $elements[] = $a;
        }

        /** @var AttributeMapping */
        $node = $this;

        foreach ($elements as $element) {
            try {
                $node = $node->getSubNode($element, $schema);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $node;
    }

    public function getSubNodeWithPath($path)
    {
        if ($path == null) {
            return $this;
        } else {
            // TODO: This code is wrong!!!

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

            return $this->getNode($first)->withFilter($filter)->getNode($last);
        }
    }

    public function applyWhereConditionDirect($attribute, &$query, $operator, $value)
    {
        switch ($operator) {
            case "eq":
                $query->where($attribute, $value);
                break;
            case "ne":
                $query->where($attribute, '<>', $value);
                break;
            case "co":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_') . '%');
                break;
            case "sw":
                $query->where($attribute, 'like', addcslashes($value, '%_') . '%');
                break;
            case "ew":
                $query->where($attribute, 'like', '%' . addcslashes($value, '%_'));
                break;
            case "pr":
                $query->whereNotNull($attribute);
                break;
            case "gt":
                $query->where($attribute, '>', $value);
                break;
            case "ge":
                $query->where($attribute, '>=', $value);
                break;
            case "lt":
                $query->where($attribute, '<', $value);
                break;
            case "le":
                $query->where($attribute, '<=', $value);
                break;
            default:
                throw new \RuntimeException("Not supported operator '{$operator}'");
                break;
        }
    }

    /**
     * @param mixed $query
     * @param string $operator
     * @param mixed $value
     * @throws SCIMException
     */
    public function applyWhereCondition(&$query, string $operator, $value)
    {
        //only filter on OWN eloquent attributes
        if (empty($this->eloquentAttributes)) {
            throw new SCIMException("Can't filter on . " + $this->getFullKey());
        }

        $attribute = $this->eloquentAttributes[0];

        if ($this->relationship != null) {
            $query->whereHas($this->relationship, function ($query) use ($attribute, $operator, $value) {
                $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
            })->get();
        } else {
            $this->applyWhereConditionDirect($attribute, $query, $operator, $value);
        }
    }
}
