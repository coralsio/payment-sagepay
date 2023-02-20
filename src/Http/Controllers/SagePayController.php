<?php

namespace Corals\Modules\Payment\SagePay\Http\Controllers;

use Corals\Foundation\Http\Controllers\PublicBaseController;
use Corals\Modules\Payment\Classes\Payments;
use Illuminate\Http\Request;

class SagePayController extends PublicBaseController
{
    protected $gateway;
    /**
     * @var Payments
     */
    protected Payments $payments;

    protected function initGateway()
    {
        $payments = new Payments('SagePay');

        $this->payments = $payments;
        $this->gateway = $payments->gateway;
        $this->gateway->setAuthentication();
    }

    public function clientRedirect(Request $request)
    {
        $objectData = $request->all();

        if (!$request->filled(['transactionId', 'handler'])) {
            abort(404);
        }

        $this->initGateway();

        $request = $this->gateway->completeAuthorize(['transactionId' => $request->get('transactionId')]);

        $response = $request->send();

        if ($response->isSuccessful()) {
            $payment_status = 'paid';
            $payment_reference = $response->getChargeReference();
        } else {
            $payment_status = 'canceled';
            $payment_reference = $response->getChargeReference();
            $objectData['message'] = $response->getMessage();
        }

        $objectData['gateway'] = $this->gateway->getName();

        return $objectData['handler']($objectData, $payment_reference, $payment_status);
    }
}
