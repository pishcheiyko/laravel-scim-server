<?php

namespace UniqKey\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use UniqKey\Laravel\SCIMServer\SCIMHelper;
use UniqKey\Laravel\SCIMServer\ResourceType;

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
            'schemas' => [Schema::SCHEMA_LIST_RESPONSE,],
            'Resources' => resolve(SCIMHelper::class)->prepareReturn(
                $this->resourceObjects,
                $this->resourceType
            ),
        ];
    }
}
