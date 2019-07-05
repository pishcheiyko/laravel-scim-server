<?php

namespace ArieTimmerman\Laravel\SCIMServer\Contracts;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;

interface PolicyDecisionPointInterface
{
    const OPERATION_GET = 'GET';
    const OPERATION_POST = 'POST';
    const OPERATION_DELETE = 'DELETE';
    const OPERATION_PATCH = 'PATCH';
    const OPERATION_PUT = 'PUT';

    /**
     * @param Request $request
     * @param string $operation
     * @param array $attributes
     * @param ResourceType $resourceType
     * @param Model|null $resourceObject
     * @return bool
     */
    public function isAllowed(
        Request $request,
        string $operation,
        array $attributes,
        ResourceType $resourceType,
        ?Model $resourceObject
    ): bool;
}
