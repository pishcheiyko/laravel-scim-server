<?php

namespace UniqKey\Laravel\SCIMServer\Contracts;

use Illuminate\Database\Eloquent\Model;
use UniqKey\Laravel\SCIMServer\ResourceType;

interface PolicyInterface
{
    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return bool
     */
    public function isGettingAllowed(
        ResourceType $resourceType,
        Model $resourceObject
    ): bool;

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @return bool
     */
    public function isDeletingAllowed(
        ResourceType $resourceType,
        Model $resourceObject
    ): bool;

    /**
     * @param ResourceType $resourceType
     * @param array $attributes
     * @return bool
     */
    public function isCreatingAllowed(
        ResourceType $resourceType,
        array $attributes
    ): bool;

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @param array $attributes
     * @return bool
     */
    public function isReplacingAllowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $attributes
    ): bool;

    /**
     * @param ResourceType $resourceType
     * @param Model $resourceObject
     * @param array $attributes
     * @return bool
     */
    public function isUpdatingAllowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $attributes
    ): bool;
}
