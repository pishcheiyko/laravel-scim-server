<?php

namespace ArieTimmerman\Laravel\SCIMServer;

use ArieTimmerman\Laravel\SCIMServer\Attributes\AttributeMapping;

class ResourceType
{
    /** @var array */
    protected $configuration;
    /** @var string */
    protected $name;

    /**
     * @param string $name
     * @param array $configuration
     */
    public function __construct(string $name, array $configuration)
    {
        $this->name = $name;
        $this->configuration = $configuration;
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return AttributeMapping
     */
    public function getMapping(): AttributeMapping
    {
        $mapping = AttributeMapping::object($this->configuration['mapping'] ?? [])
            ->setDefaultSchema($this->configuration['schema']);

        return $mapping;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getSchema()
    {
        return $this->configuration['schema'];
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->configuration['class'];
    }

    /**
     * @return array
     */
    public function getValidations(): array
    {
        return $this->configuration['validations'];
    }

    /**
     * @return array
     */
    public function getWithRelations(): array
    {
        return $this->configuration['withRelations'] ?? [];
    }

    /**
     * @return ResourceType
     */
    public static function user(): ResourceType
    {
        return new ResourceType('Users', resolve(SCIMConfig::class)->getUserConfig());
    }

    /**
     * @return array
     */
    public function getAllAttributeConfigs($mapping = -1): array
    {
        $result = [];

        if ($mapping == -1) {
            $mapping = $this->getMapping();
        }

        foreach ($mapping as $key => $value) {
            if ($value instanceof AttributeMapping && $value != null) {
                $result[] = $value;
            } elseif (is_array($value)) {
                $extra = $this->getAllAttributeConfigs($value);

                if (!empty($extra)) {
                    $result = array_merge($result, $extra);
                }
            }
        }

        return $result;
    }
}
