<?php

namespace UniqKey\Laravel\SCIMServer\Providers;

use UniqKey\Laravel\SCIMServer\Http\Controllers\MeController;
use UniqKey\Laravel\SCIMServer\Http\Controllers\ResourceController;
use UniqKey\Laravel\SCIMServer\Policies\MePolicy;
use UniqKey\Laravel\SCIMServer\Policies\ResourcePolicy;
use UniqKey\Laravel\SCIMServer\Contracts\PolicyInterface;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot()
    {
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
                return resolve(MePolicy::class);
            });

        $this->app->when(ResourceController::class)
            ->needs(PolicyInterface::class)
            ->give(function () {
                return resolve(ResourcePolicy::class);
            });
    }
}
