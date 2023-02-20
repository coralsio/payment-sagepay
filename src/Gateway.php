<?php

/**
 * SagePay Gateway.
 */

namespace Corals\Modules\Payment\SagePay;

use Corals\Modules\Payment\Common\AbstractGateway;
use Corals\Modules\Payment\Common\CreditCard;
use Corals\Modules\Payment\SagePay\Message\DirectAuthorizeRequest;
use Corals\Modules\Payment\SagePay\Message\DirectCompleteAuthorizeRequest;
use Corals\Modules\Payment\SagePay\Message\DirectPurchaseRequest;
use Corals\Modules\Payment\SagePay\Message\DirectTokenRegistrationRequest;
use Corals\Modules\Payment\SagePay\Message\SharedAbortRequest;
use Corals\Modules\Payment\SagePay\Message\SharedCaptureRequest;
use Corals\Modules\Payment\SagePay\Message\SharedRefundRequest;
use Corals\Modules\Payment\SagePay\Message\SharedRepeatAuthorizeRequest;
use Corals\Modules\Payment\SagePay\Message\SharedRepeatPurchaseRequest;
use Corals\Modules\Payment\SagePay\Message\SharedTokenRemovalRequest;
use Corals\Modules\Payment\SagePay\Message\SharedVoidRequest;
use Corals\Modules\Payment\SagePay\Traits\GatewayParamsTrait;
use Corals\Settings\Facades\Settings;
use Corals\User\Models\User;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LVR\CreditCard\CardCvc;
use LVR\CreditCard\CardExpirationMonth;
use LVR\CreditCard\CardExpirationYear;
use LVR\CreditCard\CardNumber;

/**
 * Class Gateway
 * @package Corals\Modules\Payment\SagePay
 */
class Gateway extends AbstractGateway
{
    use GatewayParamsTrait;
    use ValidatesRequests;

    /**
     * Examples for language: EN, DE and FR.
     * Also supports a locale format.
     */
    public function getDefaultParameters()
    {
        return [
            'vendor' => null,
            'testMode' => false,
            'referrerId' => null,
            'language' => null,
            'useOldBasketFormat' => false,
            'exitOnResponse' => false,
            'apply3DSecure' => null,
            'useAuthenticate' => null,
            'accountType' => null,
        ];
    }

    public function getName()
    {
        return 'SagePay';
    }

    public function setVendor($value)
    {
        return $this->setParameter('vendor', $value);
    }

    public function getVendor($value)
    {
        return $this->getParameter('vendor');
    }

    public function setClientIp($value)
    {
        return $this->setParameter('clientIp', $value);
    }

    public function getClientIp()
    {
        return $this->getParameter('clientIp');
    }

    public function setAuthentication()
    {
        $testMode = Settings::get('payment_sagepay_test_mode', 'true');

        $this->setVendor(Settings::get('payment_sagepay_vendor'));

        $this->setClientIp(Settings::get('payment_sagepay_ip_address'));

        $this->setTestMode($testMode == 'true');

        $this->setApply3DSecure(Settings::get('payment_sagepay_threeds_option', 0));
    }

    /**
     * Direct methods.
     */

    /**
     * Authorize and handling of return from 3D Secure or PayPal redirection.
     */
    public function authorize(array $parameters = [])
    {
        return $this->createRequest(DirectAuthorizeRequest::class, $parameters);
    }

    public function completeAuthorize(array $parameters = [])
    {
        return $this->createRequest(DirectCompleteAuthorizeRequest::class, $parameters);
    }

    /**
     * Purchase and handling of return from 3D Secure or PayPal redirection.
     */
    public function createCharge(array $parameters = [])
    {
        return $this->purchase($parameters);
    }

    public function purchase(array $parameters = [])
    {
        return $this->createRequest(DirectPurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = [])
    {
        return $this->completeAuthorize($parameters);
    }

    /**
     * Shared methods (identical for Direct and Server).
     */

    /**
     * Capture an authorization.
     */
    public function capture(array $parameters = [])
    {
        return $this->createRequest(SharedCaptureRequest::class, $parameters);
    }

    /**
     * Void a paid transaction.
     */
    public function void(array $parameters = [])
    {
        return $this->createRequest(SharedVoidRequest::class, $parameters);
    }

    /**
     * Abort an authorization.
     */
    public function abort(array $parameters = [])
    {
        return $this->createRequest(SharedAbortRequest::class, $parameters);
    }

    /**
     * Void a completed (captured) transation.
     */
    public function refund(array $parameters = [])
    {
        return $this->createRequest(SharedRefundRequest::class, $parameters);
    }

    /**
     * Create a new authorization against a previous payment.
     */
    public function repeatAuthorize(array $parameters = [])
    {
        return $this->createRequest(SharedRepeatAuthorizeRequest::class, $parameters);
    }

    /**
     * Create a new purchase against a previous payment.
     */
    public function repeatPurchase(array $parameters = [])
    {
        return $this->createRequest(SharedRepeatPurchaseRequest::class, $parameters);
    }

    /**
     * Accept card details from a user and return a token, without any
     * authorization against that card.
     * i.e. standalone token creation.
     * Standard Omnipay function.
     */
    public function createCard(array $parameters = [])
    {
        return $this->registerToken($parameters);
    }

    /**
     * Accept card details from a user and return a token, without any
     * authorization against that card.
     * i.e. standalone token creation.
     */
    public function registerToken(array $parameters = [])
    {
        return $this->createRequest(DirectTokenRegistrationRequest::class, $parameters);
    }

    /**
     * Remove a card token from the account.
     * Standard Omnipay function.
     */
    public function deleteCard(array $parameters = [])
    {
        return $this->removeToken($parameters);
    }

    /**
     * Remove a card token from the account.
     */
    public function removeToken(array $parameters = [])
    {
        return $this->createRequest(SharedTokenRemovalRequest::class, $parameters);
    }

    /**
     * @deprecated use repeatAuthorize() or repeatPurchase()
     */
    public function repeatPayment(array $parameters = [])
    {
        return $this->createRequest(SharedRepeatPurchaseRequest::class, $parameters);
    }

    public function prepareCreateChargeParameters($order, User $user, $checkoutDetails)
    {
        $transactionId = Str::random();

        return [
            'amount' => $order->amount,
            'currency' => $order->currency,
            'card' => $this->getCardObject($order, $user, $checkoutDetails),
            'clientIp' => $this->getClientIp(),
            'transactionId' => $transactionId,
            'description' => 'Order #' . $order->id,
        ];
    }

    public function prepareCreateRefundParameters($order, $amount)
    {
        return [
            'amount' => $amount,
            'transactionReference' => $order->billing['payment_reference'],
            'currency' => $order->currency,
            'description' => $order->order_number . ' Refund',
            'transactionId' => Str::random(),
        ];
    }


    public function prepareCreateMultiOrderChargeParameters($orders, User $user, $checkoutDetails)
    {
        $amount = 0;

        $description = "Order # ";

        $currency = "";

        foreach ($orders as $order) {
            $amount += $order->amount;
            $currency = $order->currency;
            $description .= $order->order_number . ",";
        }

        $order = current($orders);

        $transactionId = Str::random();

        return [
            'amount' => $amount,
            'currency' => $currency,
            'card' => $this->getCardObject($order, $user, $checkoutDetails),
            'clientIp' => $this->getClientIp(),
            'transactionId' => $transactionId,
            'description' => $description,
        ];
    }

    protected function getCardObject($order, $user, $checkoutDetails)
    {
        $payment_details = data_get($checkoutDetails, 'payment_details');

        $billing = data_get($order->billing, 'billing_address');
        $shipping = data_get($order->shipping, 'shipping_address');

        $userBilling = $user->address('billing');
        $userShipping = $user->address('shipping');

        $cardBilling = [
            'billingFirstName' => data_get($billing, 'first_name', $user->name),
            'billingLastName' => data_get($billing, 'last_name', $user->last_name),
            'billingAddress1' => data_get($billing, 'address_1', data_get($userBilling, 'address_1')),
            'billingAddress2' => data_get($billing, 'address_2', data_get($userBilling, 'address_2')),
            'billingState' => data_get($billing, 'state', data_get($userBilling, 'state')),
            'billingCity' => data_get($billing, 'city', data_get($userBilling, 'city')),
            'billingPostcode' => data_get($billing, 'zip', data_get($userBilling, 'zip')),
            'billingCountry' => data_get($billing, 'country', data_get($userBilling, 'country')),
            'billingPhone' => data_get($billing, 'phone_number', $user->phone),
        ];

        $cardShipping = [
            'shippingFirstName' => data_get($shipping, 'first_name', $cardBilling['billingFirstName']),
            'shippingLastName' => data_get($shipping, 'last_name', $cardBilling['billingLastName']),

            'shippingAddress1' => data_get($shipping, 'address_1',
                $userShipping ? data_get($userShipping, 'address_1') : $cardBilling['billingAddress1']),

            'shippingAddress2' => data_get($shipping, 'address_2',
                $userShipping ? data_get($userShipping, 'address_2') : $cardBilling['billingAddress2']),

            'shippingState' => data_get($shipping, 'state',
                $userShipping ? data_get($userShipping, 'state') : $cardBilling['billingState']),

            'shippingCity' => data_get($shipping, 'city',
                $userShipping ? data_get($userShipping, 'city') : $cardBilling['billingCity']),

            'shippingPostcode' => data_get($shipping, 'zip',
                $userShipping ? data_get($userShipping, 'zip') : $cardBilling['billingPostcode']),

            'shippingCountry' => data_get($shipping, 'country',
                $userShipping ? data_get($userShipping, 'country') : $cardBilling['billingCountry']),

            'shippingPhone' => data_get($shipping, 'phone_number', $user->phone),
        ];

        return new CreditCard(array_merge([
            'firstName' => $cardBilling['billingFirstName'],
            'lastName' => $cardBilling['billingLastName'],
            'email' => data_get($billing, 'email', $user->email),
            'number' => data_get($payment_details, 'number'),
            'expiryMonth' => data_get($payment_details, 'expiryMonth'),
            'expiryYear' => data_get($payment_details, 'expiryYear'),
            'CVV' => data_get($payment_details, 'cvv'),
        ], $cardBilling, $cardShipping));
    }

    public function getPaymentViewName($type = null)
    {
        return 'SagePay::card';
    }


    public function preparePaymentTokenParameters($amount, $currency, $description, $params = [])
    {
        $parameters = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'confirm' => true,
            'paymentMethod' => $params['payment_method_id']
        ];

        return $parameters;
    }


    public function prepareCheckPaymentTokenParameters($params = [])
    {
        $parameters = [
            'paymentIntentReference' => $params['payment_intent_id']
        ];

        return $parameters;
    }


    public function checkPaymentToken(array $parameters = array())
    {
    }

    public function confirmPaymentToken(array $parameters = array())
    {
    }

    public function loadScripts()
    {
        return view("SagePay::scripts")->render();
    }

    public function validateRequest($request)
    {
        return $this->validate($request, [
            'payment_details.number' => ['required', new CardNumber()],
            'payment_details.expiryYear' => [
                'required',
                new CardExpirationYear($request->input('payment_details.expiryMonth', ''))
            ],
            'payment_details.expiryMonth' => [
                'required',
                new CardExpirationMonth($request->input('payment_details.expiryYear', ''))
            ],
            'payment_details.cvv' => [
                'required',
                new CardCvc($request->input('payment_details.number', ''))
            ],
        ], [], [
            'payment_details.number' => trans('SagePay::attributes.card_number'),
            'payment_details.expiryYear' => trans('SagePay::attributes.expYear'),
            'payment_details.expiryMonth' => trans('SagePay::attributes.expMonth'),
            'payment_details.cvv' => trans('SagePay::attributes.cvv'),
        ]);
    }

    public function requireRedirect()
    {
        return true;
    }

    public function getPaymentRedirectContent($data = [])
    {
        tap(Validator::make($data, [
            'redirectHandler' => 'required',
            'paymentPurpose' => 'required',
            'transactionId' => 'required',
            "amount" => 'required|gt:0',
            "currency" => 'required',
        ]), function (\Illuminate\Contracts\Validation\Validator $validator) {
            $validator->validate();
        });

        $this->setAuthentication();

        $checkoutDetails = [
            'token' => \ShoppingCart::get('default')->getAttribute('checkoutToken'),
            'payment_details' => \ShoppingCart::getAttribute('payment_details')
        ];

        $user = user() ?? new User();

        $orders = [
            (object)[
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'order_number' => $data['paymentPurpose'],
                'billing' => ['billing_address' => \ShoppingCart::get('default')->getAttribute('billing_address') ?? []],
                'shipping' => ['shipping_address' => \ShoppingCart::get('default')->getAttribute('shipping_address') ?? []],
            ]
        ];

        $parameters = $this->prepareCreateMultiOrderChargeParameters($orders, $user, $checkoutDetails);

        $parameters['ThreeDSNotificationURL'] = 'handler=' . $data['redirectHandler'];

        $parameters['returnUrl'] = route('ThreeDSNotificationURL') . '?transactionId=' . $parameters['transactionId'] . '&' . $parameters['ThreeDSNotificationURL'];

        $request = $this->createCharge($parameters);

        $response = $request->send();

        if ($response->isRedirect()) {
            return view('SagePay::payment_page')->with([
                'url' => $response->getRedirectUrl(),
                'redirect_data' => $response->getRedirectData(),
            ]);
        } elseif ($response->isSuccessful()) {
            $payment_status = 'paid';
            $payment_reference = $response->getChargeReference();
        } else {
            $payment_status = 'canceled';
            $payment_reference = $response->getChargeReference();
        }

        $objectData['gateway'] = $this->getName();

        return $data['redirectHandler']($objectData, $payment_reference, $payment_status);
    }
}
