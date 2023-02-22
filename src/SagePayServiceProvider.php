<?php

namespace Corals\Modules\Payment\SagePay;

use Corals\Foundation\Providers\BasePackageServiceProvider;
use Corals\Modules\Payment\SagePay\Providers\SagePayRouteServiceProvider;
use Corals\Settings\Facades\Modules;

class SagePayServiceProvider extends BasePackageServiceProvide
{
    /**
     * @var
     */
    protected $defer = false;
    /**
     * @var
     */
    protected $packageCode = 'corals-payment-sagepay';
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function bootPackage()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function registerPackage()
    {
        $this->app->register(SagePayRouteServiceProvider::class);
    }

    public function registerModulesPackages()
    {
        Modules::addModulesPackages('corals/payment-sagepay');
    }
}
