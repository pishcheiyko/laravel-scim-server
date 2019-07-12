<?php

namespace UniqKey\Laravel\SCIMServer\Providers;

use Illuminate\Support\Facades\Route;
use UniqKey\Laravel\SCIMServer\Http\Middleware\SCIMHeaders;
use UniqKey\Laravel\SCIMServer\Exceptions\SCIMException;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ResourceController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\MeController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\SchemaController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ServiceProviderController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ResourceTypesController;

/**
 * Helper class for the URL shortener
 */
class RouteProvider
{
    /**
     * @param array $options
     */
    public static function routes(array $options = [])
    {
        Route::prefix('scim')->group(function () use ($options) {
            Route::prefix('v1')
                ->group(function () {
                    Route::fallback(function () {
                        $url = url('scim/v2');
                        throw (new SCIMException("Only SCIM v2 is supported. Accessible under {$url}"))
                            ->setHttpCode(501)
                            ->setScimType('invalidVers');
                    });
                });

            Route::prefix('v2')->middleware([SCIMHeaders::class])
                ->group(function () use ($options) {
                    static::allRoutes($options);
                });
        });
    }

    /**
     * @param array $options
     */
    public static function publicRoutes(array $options = [])
    {
        Route::get('/ServiceProviderConfig', ServiceProviderController::class . '@index')
            ->name('scim.serviceproviderconfig');

        Route::get('/Schemas', SchemaController::class . '@index');
        Route::get('/Schemas/{id}', SchemaController::class . '@show')->name('scim.schemas');

        Route::get('/ResourceTypes', ResourceTypesController::class . '@index');
        Route::get('/ResourceTypes/{id}', ResourceTypesController::class . '@show')
            ->name('scim.resourcetype');
    }

    /**
     * Use a policy instance to control an access to this ones..
     *
     * @param array $options
     */
    public static function meRoutes(array $options = [])
    {
        Route::get('/Me', MeController::class . '@show')->name('scim.me.get');
        Route::post('/Me', MeController::class . '@create')->name('scim.me.post');
        Route::put('/Me', MeController::class . '@replace')->name('scim.me.put');
        Route::patch('/Me', MeController::class . '@update')->name('scim.me.patch');

        Route::delete('/Me', MeController::class . '@notImplemented');
    }

    /**
     * Use a policy instance to control an access to this ones..
     *
     * @param array $options
     */
    public static function resourceRoutes(array $options = [])
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

    /**
     * @param array $options
     */
    protected static function allRoutes(array $options = [])
    {
        static::resourceRoutes($options);
        // static::meRoutes($options);
        // static::publicRoutes($options);

        Route::post('/Bulk', ResourceController::class . '@notImplemented');
        Route::post('.search', ResourceController::class . '@notImplemented');
        Route::fallback(ResourceController::class . '@notImplemented');
    }
}
