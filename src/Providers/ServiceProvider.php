<?php

namespace ArieTimmerman\Laravel\SCIMServer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider
use ArieTimmerman\Laravel\SCIMServer\Http\Middleware\SCIMHeaders;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(Router $router)
    {
        // Match everything, except the Me routes
        $router->pattern('resourceType', '^((?!Me).)*$');

        $router->bind('resourceType', function ($name, $route) {
            $config = resolve(SCIMConfig::class)->getConfigForResource($name);

            if ($config == null) {
                throw (new SCIMException(sprintf('No resource "%s" found.', $name)))
                    ->setCode(404);
            }

            return new ResourceType($name, $config);
        });

        $router->bind('resourceObject', function ($id, $route) {
            $resourceType = $route->parameter('resourceType');

            if (!$resourceType) {
                throw (new SCIMException('ResourceType not provided'))
                    ->setCode(404);
            }

            $class = $resourceType->getClass();

            $resourceObject = $class::with($resourceType->getWithRelations())->find($id);

            if ($resourceObject == null) {
                throw (new SCIMException(sprintf('Resource "%s" not found', $id)))
                    ->setCode(404);
            }

            if (($matchIf = \request()->header('IF-Match'))) {
                $versionsAllowed = preg_split('/\s*,\s*/', $matchIf);
                $currentVersion = Helper::getResourceObjectVersion($resourceObject);

                // if as version is '*' it is always ok
                if (!in_array($currentVersion, $versionsAllowed)
                &&  !in_array('*', $versionsAllowed)) {
                    throw (new SCIMException('Failed to update.  Resource changed on the server.'))
                        ->setCode(412);
                }
            }

            return $resourceObject;
        });

        $router->middleware('SCIMHeaders', SCIMHeaders::class);
    }

    /**
     * Register any package services.
     */
    public function register()
    {

//$this->app->alias('bugsnag.multi', \Illuminate\Contracts\Logging\Log::class);
//!!!
// bind ArieTimmerman\Laravel\SCIMServer\PolicyDecisionPoint\ResourcePolicyDecisionPoint
// bind ArieTimmerman\Laravel\SCIMServer\PolicyDecisionPoint\MePolicyDecisionPoint

    }
}
