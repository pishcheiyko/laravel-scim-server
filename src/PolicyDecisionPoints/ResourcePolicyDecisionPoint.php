<?php

namespace ArieTimmerman\Laravel\SCIMServer\PolicyDecisionPoint;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\Contracts\PolicyDecisionInterface;

class ResourcePolicyDecisionPoint implements PolicyDecisionInterface
{
    /**
     * {@inheritdoc}
     */
    public function isAllowed(
        Request $request,
        string $operation,
        array $attributes,
        ResourceType $resourceType,
        ?Model $resourceObject
    ): bool {
        return true;
    }
}
