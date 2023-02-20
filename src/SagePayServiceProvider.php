<?php

namespace Corals\Modules\Payment\SagePay;

use Corals\Modules\Payment\SagePay\Providers\SagePayRouteServiceProvider;
use Illuminate\Support\ServiceProvider;
use Corals\Settings\Facades\Modules;

class SagePayServiceProvider extends ServiceProvider
{
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerModulesPackages();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(SagePayRouteServiceProvider::class);
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/payment-sagepay');
    }
}
