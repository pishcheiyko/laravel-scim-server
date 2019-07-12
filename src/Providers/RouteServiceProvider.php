<?php

namespace UniqKey\Laravel\SCIMServer\Providers;

use UniqKey\Laravel\SCIMServer\SCIMConfig;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Helper;
use UniqKey\Laravel\SCIMServer\Http\Middleware\SCIMHeaders;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as BaseServiceProvider;

class RouteServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @param Router $router
     */
    public function boot(Router $router)
    {
        parent::boot();

        // Match everything, except the Me routes
        $router->pattern('resourceType', '^((?!Me).)*$');

        $router->bind('resourceType', function ($name, $route) {
            $config = resolve(SCIMConfig::class)->getConfigForResource($name);

            if (null === $config) {
                throw (new SCIMException(sprintf('No resource "%s" found.', $name)))
                    ->setHttpCode(404);
            }

            return new ResourceType($name, $config);
        });

        $router->bind('resourceObject', function ($id, $route) {
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

            if (($matchIf = \request()->header('IF-Match'))) {
                $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                $currentVersion = Helper::getResourceObjectVersion($resourceObject);

                // if as version is '*' it is always ok
                if (!in_array($currentVersion, $versionsAllowed)
                &&  !in_array('*', $versionsAllowed)) {
                    throw (new SCIMException('Failed to update.  Resource changed on the server.'))
                        ->setHttpCode(412);
                }
            }

            return $resourceObject;
        });

        $router->middleware('SCIMHeaders', SCIMHeaders::class);
    }

    /**
     * Define the routes for the package.
     */
    public function map()
    {
        RouteProvider::routes();
    }
}
