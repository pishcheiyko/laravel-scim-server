<?php

namespace UniqKey\Laravel\SCIMServer;

use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\DB;
use Tmilos\ScimFilterParser\Ast\ComparisonExpression;
use Tmilos\ScimFilterParser\Ast\Negation;
use Tmilos\ScimFilterParser\Ast\Conjunction;
use Tmilos\ScimFilterParser\Ast\Disjunction;
use Tmilos\ScimFilterParser\Ast\ValuePath;
use Tmilos\ScimFilterParser\Ast\Factor;
use Tmilos\ScimFilterParser\Parser;
use Tmilos\ScimFilterParser\Mode;
use Tmilos\ScimFilterParser\Ast\Path;
use Tmilos\ScimFilterParser\Ast\AttributePath;
use UniqKey\Laravel\SCIMServer\SCIM\Schema;
use UniqKey\Laravel\SCIMServer\Attributes\AttributeMapping;
use UniqKey\Laravel\SCIMServer\Attributes\Collection;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMHelper
{
    /**
     * @return string
     */
    public static function getAuthUserClass(): string
    {
        return config('auth.providers.users.model');
    }

    /**
     * @param Arrayable $object
     * @param ResourceType|null $resourceType
     * @return array
     */
    public static function prepareReturn(
        Arrayable $object,
        ResourceType $resourceType = null
    ): array {
        $object = $object->toArray();

        if (!empty($object) && isset($object[0]) && is_object($object[0])) {
            $result = [];

            foreach ($object as $key => $value) {
                $result[] = static::objectToSCIMArray($value, $resourceType);
            }

            return $result;
        } else {
            return $object;
        }
    }

    /**
     * @todo Auto map eloquent attributes with scim naming to the correct attributes
     *
     * @param Arrayable $object
     * @param ResourceType|null $resourceType
     * @return array
     */
    public static function objectToSCIMArray(
        Arrayable $object,
        ResourceType $resourceType = null
    ): array {
        $data = $object->toArray();

        // If the getDates-method exists, ensure proper formatting of date attributes
        if (method_exists($object, 'getDates')) {
            $dateAttributes = $object->getDates();

            foreach ($dateAttributes as $dateAttribute) {
                if (isset($data[$dateAttribute])) {
                    $data[$dateAttribute] = $object->getAttribute($dateAttribute)->format('c');
                }
            }
        }

        if (null === $resourceType) {
            return $data;
        }

        $result = [];

        $mapping = $resourceType->getMapping();

        $uses = $mapping->getEloquentAttributes();

        $result = $mapping->read($object);

        foreach ($uses as $key) {
            unset($data[$key]);
        }

        if (false === empty($data)
        &&  (($resourceType->getConfiguration()['map_unmapped']) ?? false)) {
            $namespace = $resourceType->getConfiguration()['unmapped_namespace'] ?? null;

            if (null !== $namespace) {
                if (!isset($result[$namespace])) {
                    $result[$namespace] = [];
                }

                $parent = &$result[$namespace];
            } else {
                $parent = &$result;
            }

            foreach ($data as $key => $value) {
                $parent[$key] = AttributeMapping::eloquentAttributeToString($value);
            }
        }

        return $result;
    }

    /**
     * @param Model $object
     * @return string
     */
    public static function getResourceObjectVersion(Model $object): string
    {
        if (method_exists($object, 'getSCIMVersion')) {
            $version = $object->getSCIMVersion();
        } else {
            $version = sha1("{$object->getKey()}{$object->updated_at}{$object->created_at}");
        }

        // Entity tags uniquely representing the requested resources.
        // They are a string of ASCII characters placed between double quotes.
        return sprintf('W/"%s"', $version);
    }

    /**
     * @param Model $object
     * @param ResourceType|null $resourceType
     * @return Response
     */
    public static function objectToSCIMResponse(
        Model $object,
        ResourceType $resourceType = null
    ): Response {
        return response(static::objectToSCIMArray($object, $resourceType))
            ->setEtag(static::getResourceObjectVersion($object));
    }

    /**
     * @param Model $object
     * @param ResourceType|null $resourceType
     * @return Response
     */
    public static function objectToSCIMCreateResponse(
        Model $object,
        ResourceType $resourceType = null
    ): Response {
        return static::objectToSCIMResponse($object, $resourceType)
            ->setStatusCode(201);
    }

    /**
     * See https://tools.ietf.org/html/rfc7644#section-3.4.2.2
     *
     * @param ResourceType $resourceType
     * @param mixed $query
     * @param mixed $node
     *
     * @throws SCIMException
     * @throws \RuntimeException
     */
    public static function scimFilterToLaravelQuery(ResourceType $resourceType, &$query, $node)
    {
        if ($node instanceof Negation) {
            throw (new SCIMException('Negation filters are not supported'))
                ->setHttpCode(400)
                ->setScimType('invalidFilter');
        } elseif ($node instanceof ComparisonExpression) {
            $operator = strtolower($node->operator);

            $attributeConfig = $resourceType->getMapping()->getSubNodeWithPath($node);
            $attributeConfig->applyWhereCondition($query, $operator, $node->compareValue);
        } elseif ($node instanceof Conjunction) {
            foreach ($node->getFactors() as $factor) {
                $query->where(function ($query) use ($factor, $resourceType) {
                    static::scimFilterToLaravelQuery($resourceType, $query, $factor);
                });
            }
        } elseif ($node instanceof Disjunction) {
            foreach ($node->getTerms() as $term) {
                $query->orWhere(function ($query) use ($term, $resourceType) {
                    static::scimFilterToLaravelQuery($resourceType, $query, $term);
                });
            }
        } elseif ($node instanceof ValuePath) {
            $getFilter = function () {
                return $this->filter;
            };
            $scimFilter = $getFilter->call($node);
            $attributePath = $scimFilter->attributePath;
            $key = $attributePath->attributeNames[0];
            $compareValue = $scimFilter->compareValue;
            $operator = $scimFilter->operator;

            $attributeConfig = $resourceType->getMapping()->getSubNodeWithPath($node);
            $attributeConfig->applyWhereCondition($query, $operator, $compareValue, ['key' => $key]);
        } elseif ($node instanceof Factor) {
            throw (new SCIMException('Factors are not supported'))
                ->setHttpCode(400);
        }
    }

    /**
     * @param string $scimAttribute
     * @return mixed
     */
    public static function getPath(string $scimAttribute)
    {
        $parser = new Parser(Mode::PATH());

        $scimAttribute = preg_replace('/\.[0-9]+$/', '', $scimAttribute);
        $scimAttribute = preg_replace('/\.[0-9]+\./', '.', $scimAttribute);

        $path = $parser->parse($scimAttribute);

        // TODO: FIX this. If $scimAttribute is a schema-indication,
        //       it should be considered as a schema.
        if (Schema::SCHEMA_USER == $scimAttribute
        ||  Schema::SCHEMA_GROUP == $scimAttribute) {
            $attributePath = new AttributePath();
            $attributePath->schema = $scimAttribute;

            $path = Path::fromAttributePath($attributePath);
        }

        return $path;
    }

    /**
     * $scimAttribute could be
     * - urn:ietf:params:scim:schemas:core:2.0:User.userName
     * - userName == $scimAttribute
     * - urn:ietf:params:scim:schemas:core:2.0:User.userName.name.formatted
     * - urn:ietf:params:scim:schemas:core:2.0:User.emails.value
     * - emails.value
     * - emails.0.value
     * - schemas.0
     *
     * @param ResourceType $resourceType
     * @param string $scimAttribute
     * @return AttributeMapping|null
     */
    public static function getAttributeConfig(
        ResourceType $resourceType,
        string $scimAttribute
    ): ?AttributeMapping {
        $path = static::getPath($scimAttribute);
        return $resourceType->getMapping()->getSubNodeWithPath($path);
    }

    /**
     * @param ResourceType $resourceType
     * @param string $scimAttribute
     * @return AttributeMapping
     * @throws SCIMException
     */
    public static function getAttributeConfigOrFail(
        ResourceType $resourceType,
        string $scimAttribute
    ): AttributeMapping {
        $result = static::getAttributeConfig($resourceType, $scimAttribute);

        if (null === $result) {
            throw (new SCIMException(sprintf('Unknown attribute "%s"', $scimAttribute)))
                ->setHttpCode(400);
        }

        return $result;
    }

    /**
     * @param ResourceType $resourceType
     * @param string $scimAttribute
     * @return string|null
     * @throws SCIMException
     */
    public static function getEloquentSortAttribute(
        ResourceType $resourceType,
        string $scimAttribute
    ): ?string {
        $mapping = static::getAttributeConfig($resourceType, $scimAttribute);

        if (null === $mapping || null === $mapping->getSortAttribute()) {
            throw (new SCIMException('Invalid sort property'))
                ->setHttpCode(400)
                ->setScimType('invalidFilter');
        }

        return $mapping->getSortAttribute();
    }

    /**
     * @param array $parts
     * @param array $schemas
     * @return string
     * @throws SCIMException
     */
    public static function getFlattenKey(array $parts, array $schemas): string
    {
        $partsCopy = $parts;

        $first = array_first($partsCopy);

        if (null === $first) {
            throw (new SCIMException('Unknown error. ' . json_encode($partsCopy)))
                ->setHttpCode(500);
        }

        if (in_array($first, $schemas)) {
            $result = "{$first}:";
            array_shift($partsCopy);
        } else { // If no schema is provided, use the first schema as its schema.
            $result = "{$schemas[0]}:";
        }

        $result .= implode('.', $partsCopy);

        return $result;
    }

    /**
     * @param array $array
     * @param array $schemas
     * @param array $parts
     * @return array
     */
    public static function flatten(array $array, array $schemas, array $parts = []): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $final = static::getFlattenKey($parts, $schemas);

                if (!isset($result[$final])) {
                    $result[$final] = [];
                }

                $result[$final][$key] = $value;
            } elseif (is_array($value)) {
                // Empty values do matter, e.g. in case of empty-ing a multi-valued attribute via PUT/replace
                if (empty($value)) {
                    $partsCopy = $parts;
                    $partsCopy[] = $key;
                    $final = static::getFlattenKey($partsCopy, $schemas);
                    $result[$final] = $value;
                } else {
                    $result = $result + static::flatten($value, $schemas, array_merge($parts, [$key]));
                }
            } else {
                $partsCopy = $parts;
                $partsCopy[] = $key;

                $result[static::getFlattenKey($partsCopy, $schemas)] = $value;
            }
        }

        return $result;
    }
}
