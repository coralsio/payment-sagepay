<?php

namespace Corals\Modules\Payment\SagePay\Providers;

use Corals\Foundation\Providers\BaseUpdateModuleServiceProvider;

class UpdateModuleServiceProvider extends BaseUpdateModuleServiceProvider
{
    protected $module_code = 'corals-payment-sagepay';
    protected $batches_path = __DIR__ . '/../update-batches/*.php';
}
