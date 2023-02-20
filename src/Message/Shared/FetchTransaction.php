<?php

namespace Corals\Modules\Payment\SagePay\Message\Shared;

/**
 * Sage Pay fetch a transaction.
 * Reporting command: getTransactionDetail
 */

use Corals\Modules\Payment\SagePay\Message\AbstractRequest;

class FetchTransaction extends AbstractRequest
{
    // TODO: this is an XML interface completely different to the payments APIs.
    public function getData()
    {
        // TODO: Implement getData() method.
    }
}
