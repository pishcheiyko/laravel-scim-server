<?php

namespace UniqKey\Laravel\SCIMServer\Policies;

use Illuminate\Database\Eloquent\Model;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;

class BasePolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function isGettingAllowed(
        ResourceType $resourceType,
        Model $resourceObject
    ): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDeletingAllowed(
        ResourceType $resourceType,
        Model $resourceObject
    ): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCreatingAllowed(
        ResourceType $resourceType,
        array $attributes
    ): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isReplacingAllowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $attributes
    ): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isUpdatingAllowed(
        ResourceType $resourceType,
        Model $resourceObject,
        array $attributes
    ): bool {
        return true;
    }
}
