<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use ArieTimmerman\Laravel\SCIMServer\SCIM\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\SCIM\Schema;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;

class ResourceTypesController extends BaseController
{
    protected $resourceTypes = null;

    public function __construct()
    {
        $config = resolve(SCIMConfig::class)->getConfig();

        $resourceTypes = [];

        foreach ($config as $key => $value) {
            $resourceTypes[] = new ResourceType(
                $value['singular'],
                $key,
                $key,
                $value['description'] ?? null,
                Schema::SCHEMA_USER
            );
        }

        $this->resourceTypes = collect($resourceTypes);
    }

    public function index()
    {
        return new ListResponse($this->resourceTypes, 1, $this->resourceTypes->count());
    }

    public function show(Request $request, $id = null)
    {
        $result = $this->resourceTypes->first(function ($value, $key) use ($id) {
            return $value->getId() == $id;
        });

        if ($result == null) {
            throw (new SCIMException("Resource not found"))->setHttpCode(404);
        }

        return $result;
    }
}
