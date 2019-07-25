<?php

namespace UniqKey\Laravel\SCIMServer;

use UniqKey\Laravel\SCIMServer\SCIMConfig;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Helper;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;

class SCIMRouteBinding
{
    /**
     * @return callable
     */
    public function createResourceTypeBinding(): callable
    {
        return function ($name, $route) {
            $config = resolve(SCIMConfig::class)->getConfigForResource($name);

            if (null === $config) {
                throw (new SCIMException(sprintf('No resource "%s" found.', $name)))
                    ->setHttpCode(404);
            }

            return new ResourceType($name, $config);
        };
    }

    /**
     * @return callable
     */
    public function createResourceObjectBinding(): callable
    {
        return function ($id, $route) {
            $resourceType = $route->parameter('resourceType');

            if (!$resourceType) {
                throw (new SCIMException('ResourceType not provided'))
                    ->setHttpCode(404);
            }

            $class = $resourceType->getClass();

            $resourceObject = $class::with($resourceType->getWithRelations())->find($id);

            if (null === $resourceObject) {
                throw (new SCIMException(sprintf('Resource "%s" not found', $id)))
                    ->setHttpCode(404);
            }

            if (($matchIf = request()->header('IF-Match'))) {
                $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                $currentVersion = Helper::getResourceObjectVersion($resourceObject);

                // if as version is '*' it is always ok
                if (false === in_array($currentVersion, $versionsAllowed)
                &&  false === in_array('*', $versionsAllowed)) {
                    throw (new SCIMException('Failed to update. Resource changed on the server.'))
                        ->setHttpCode(412);
                }
            }

            return $resourceObject;
        };
    }
}
