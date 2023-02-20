<?php

namespace Corals\Modules\Payment\SagePay\Providers;

use Corals\Foundation\Providers\BaseInstallModuleServiceProvider;

class InstallModuleServiceProvider extends BaseInstallModuleServiceProvider
{
    protected function providerBooted()
    {
        $supported_gateways = \Payments::getAvailableGateways();

        $supported_gateways['SagePay'] = 'SagePay';
        
        \Payments::setAvailableGateways($supported_gateways);
    }
}
