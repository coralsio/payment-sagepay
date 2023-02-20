<?php

namespace Corals\Modules\Payment\SagePay\Message;

/**
 * Sage Pay Direct Purchase Request
 */
class DirectPurchaseRequest extends DirectAuthorizeRequest
{
    public function getTxType()
    {
        return static::TXTYPE_PAYMENT;
    }
}
