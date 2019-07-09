<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;

class ListResponse implements Jsonable, Arrayable
{
    /** @var Arrayable */
    protected $resourceObjects;
    /** @var int */
    protected $startIndex;
    /** @var int */
    protected $totalResults;
    /** @var array */
    protected $attributes;
    /** @var array */
    protected $excludedAttributes;
    /** @var ResourceType|null */
    protected $resourceType;

    /**
     * @param Arrayable $resourceObjects
     * @param int $startIndex
     * @param int $totalResults
     * @param array $attributes
     * @param array $excludedAttributes
     * @param ResourceType|null $resourceType
    */
    public function __construct(
        Arrayable $resourceObjects,
        int $startIndex = 1,
        int $totalResults = 10,
        array $attributes = [],
        array $excludedAttributes = [],
        ResourceType $resourceType = null
    ) {
        $this->resourceObjects = $resourceObjects;
        $this->startIndex = $startIndex;
        $this->totalResults = $totalResults;
        $this->attributes = $attributes;
        $this->excludedAttributes = $excludedAttributes;
        $this->resourceType = $resourceType;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            'totalResults' => $this->totalResults,
            'itemsPerPage' => count($this->resourceObjects->toArray()),
            'startIndex' => $this->startIndex,
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse',],
            'Resources' => Helper::prepareReturn($this->resourceObjects, $this->resourceType),
        ];
    }
}
