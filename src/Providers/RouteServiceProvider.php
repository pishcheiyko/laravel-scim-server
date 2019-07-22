<?php

namespace UniqKey\Laravel\SCIMServer\Providers;

use UniqKey\Laravel\SCIMServer\SCIMRouteBinding;
use UniqKey\Laravel\SCIMServer\SCIMRoutes;
use UniqKey\Laravel\SCIMServer\ResourceType;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as BaseServiceProvider;

class RouteServiceProvider extends BaseServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        parent::boot();

        $routeBinding = resolve(SCIMRouteBinding::class);

        // Match everything, except the Me routes
        Route::pattern('resourceType', '^((?!Me).)*$');

        Route::bind('resourceType', $routeBinding->createResourceTypeBinding());
        Route::bind('resourceObject', $routeBinding->createResourceObjectBinding());
    }

    /**
     * Define the routes for the package.
     */
    public function map()
    {
        $options = resolve(SCIMRoutes::class)->getOptions();

        Route::prefix($options['prefix'])
            ->middleware($options['middleware'])
            ->group(function () {
                $options = resolve(SCIMRoutes::class)->getOptions();

                Route::prefix('v2')
                    ->middleware($options['v2']['middleware'])
                    ->group(function () {
                        resolve(SCIMRoutes::class)->allRoutes();
                    });

                Route::prefix('v1')
                    ->group(function () {
                        Route::fallback(function () {
                            $options = resolve(SCIMRoutes::class)->getOptions();
                            $url = url("{$options['prefix']}/v2");

                            throw (new SCIMException("Only SCIM v2 is supported. Accessible under {$url}"))
                                ->setHttpCode(501)
                                ->setScimType('invalidVers');
                        });
                    });
            });
    }
}
