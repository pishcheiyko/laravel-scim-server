<?php

namespace UniqKey\Laravel\SCIMServer;

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\SubstituteBindings;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\Http\Middleware\SCIMHeaders;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ResourceController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\MeController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\SchemaController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ServiceProviderController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ResourceTypesController;

class SCIMRoutes
{
    /**
     * @return array
     */
    public function getOptions(): array
    {


        return [
            'prefix' => request()->has('organization') ? request()->get('organization') . '/scim' : 'scim',
            'middleware' => [SubstituteBindings::class,],
            'v2' => [
                'middleware' => [SCIMHeaders::class,],
            ],
        ];
    }

    /**
     * Generate the URL to a named route.
     *
     * @param  array|string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    public function route($name, $parameters = [], $absolute = true): string
    {
        return \route($name, $parameters, $absolute);
    }

    public function allRoutes()
    {
        $this->meRoutes();
        $this->publicRoutes();
        $this->resourceRoutes();

        Route::post('/Bulk', ResourceController::class . '@notImplemented');
        Route::post('.search', ResourceController::class . '@notImplemented');
        Route::fallback(ResourceController::class . '@notImplemented');
    }

    protected function publicRoutes()
    {
        Route::get('/ServiceProviderConfig', ServiceProviderController::class . '@index')
            ->name('scim.serviceproviderconfig');

        Route::get('/Schemas', SchemaController::class . '@index')->name('scim.schemas');
        Route::get('/Schemas/{id}', SchemaController::class . '@show')->name('scim.schema');

        Route::get('/ResourceTypes', ResourceTypesController::class . '@index');
        Route::get('/ResourceTypes/{id}', ResourceTypesController::class . '@show')
            ->name('scim.resourcetype');
    }

    /**
     * Use a policy instance to control an access to this ones..
     */
    protected function meRoutes()
    {
        Route::get('/Me', MeController::class . '@show')->name('scim.me.get');
        Route::post('/Me', MeController::class . '@create')->name('scim.me.post');
        Route::put('/Me', MeController::class . '@replace')->name('scim.me.put');
        Route::patch('/Me', MeController::class . '@update')->name('scim.me.patch');

        Route::delete('/Me', MeController::class . '@notImplemented');
    }

    /**
     * Use a policy instance to control an access to this ones..
     */
    protected function resourceRoutes()
    {
        Route::get('/{resourceType}/{resourceObject}', ResourceController::class . '@show')
            ->name('scim.resource');
        Route::get('/{resourceType}', ResourceController::class . '@index')
            ->name('scim.resources');

        Route::post('/{resourceType}', ResourceController::class . '@create');

        Route::put('/{resourceType}/{resourceObject}', ResourceController::class . '@replace');
        Route::patch('/{resourceType}/{resourceObject}', ResourceController::class . '@update');
        Route::delete('/{resourceType}/{resourceObject}', ResourceController::class . '@delete');
    }
}
