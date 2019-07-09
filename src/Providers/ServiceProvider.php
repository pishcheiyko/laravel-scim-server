<?php

namespace ArieTimmerman\Laravel\SCIMServer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use ArieTimmerman\Laravel\SCIMServer\Http\Middleware\SCIMHeaders;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Http\Controllers\MeController;
use ArieTimmerman\Laravel\SCIMServer\Http\Controllers\ResourceController;
use ArieTimmerman\Laravel\SCIMServer\Policies\MePolicy;
use ArieTimmerman\Laravel\SCIMServer\Policies\ResourcePolicy;
use ArieTimmerman\Laravel\SCIMServer\Contracts\PolicyInterface;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @param Router $router
     */
    public function boot(Router $router)
    {
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
     * Register any package services.
     */
    public function register()
    {
        $this->registerPolicies();
    }

    protected function registerPolicies()
    {
        $this->app->when(MeController::class)
          ->needs(PolicyInterface::class)
          ->give(function () {
              return new MePolicy();
          });

        $this->app->when(ResourceController::class)
          ->needs(PolicyInterface::class)
          ->give(function () {
              return new ResourcePolicy();
          });
    }
}
