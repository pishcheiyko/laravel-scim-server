<?php

namespace UniqKey\Laravel\SCIMServer\Policies;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;

class ResourcePolicy implements PolicyInterface
{
    /**
     * {@inheritdoc}
     */
    public function isAllowed(
        Request $request,
        string $operation,
        array $attributes,
        ResourceType $resourceType,
        Model $resourceObject = null
    ): bool {
        return true;
    }
}
