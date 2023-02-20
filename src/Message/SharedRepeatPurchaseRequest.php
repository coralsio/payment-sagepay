<?php

namespace Corals\Modules\Payment\SagePay\Message;

/**
 * Sage Pay Direct Repeat Authorize Request
 */
class SharedRepeatPurchaseRequest extends SharedRepeatAuthorizeRequest
{
    public function getTxType()
    {
        return static::TXTYPE_REPEAT;
    }
}
