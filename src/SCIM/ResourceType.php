<?php

namespace UniqKey\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class ResourceType implements Jsonable, Arrayable
{
    /** @var string */
    protected $id;
    /** @var string */
    protected $name;
    /** @var string */
    protected $plural;
    /** @var string|null */
    protected $description;
    /** @var string */
    protected $schema;
    /** @var array */
    protected $schemaExtensions;

    /**
     * @param string $id
     * @param string $name
     * @param string $plural
     * @param string|null $description
     * @param string $schema
     * @param array $schemaExtensions
     */
    public function __construct(
        string $id,
        string $name,
        string $plural,
        ?string $description,
        string $schema,
        array $schemaExtensions = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->plural = $plural;
        $this->description = $description;
        $this->schema = $schema;
        $this->schemaExtensions = $schemaExtensions;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType',],
            'id' => $this->id,
            'name' => $this->name,
            'endpoint' => route('scim.resources', ['resourceType' => $this->plural,]),
            'description' => $this->description,
            'schema' => $this->schema,
            'schemaExtensions' => $this->schemaExtensions,
            'meta' => [
                'location' => route('scim.resourcetype', ['id' => $this->id,]),
                'resourceType' => 'ResourceType',
            ],
        ];
    }
}
