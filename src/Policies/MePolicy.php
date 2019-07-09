<?php

namespace ArieTimmerman\Laravel\SCIMServer\Policies;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\Contracts\PolicyInterface;

class MePolicy implements PolicyInterface
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
