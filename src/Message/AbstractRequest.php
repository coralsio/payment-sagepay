<?php

namespace Corals\Modules\Payment\SagePay\Message;

/**
 * Sage Pay Abstract Request.
 * Base for Sage Pay Server and Sage Pay Direct.
 */

use Corals\Modules\Payment\Common\Exception\InvalidRequestException;
use Corals\Modules\Payment\SagePay\Traits\GatewayParamsTrait;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractRequest extends \Corals\Modules\Payment\Common\Message\AbstractRequest implements
    ConstantsInterface
{
    use GatewayParamsTrait;

    const BROWSER_JAVASCRIPT_YES = 1;
    const BROWSER_JAVASCRIPT_NO = 0;
    const BROWSER_LANGUAGE = 'en-GB';


    /**
     * Dimensions of the challenge window to be displayed to the cardholder.
     *
     * 01 = 250 x 400
     * 02 = 390 x 400
     * 03 = 500 x 600
     * 04 = 600 x 400
     * 05 = Full screen
     *
     * @var string
     */
    const CHALLENGE_WINDOW_SIZE_01 = '01';
    const CHALLENGE_WINDOW_SIZE_02 = '02';
    const CHALLENGE_WINDOW_SIZE_03 = '03';
    const CHALLENGE_WINDOW_SIZE_04 = '04';
    const CHALLENGE_WINDOW_SIZE_05 = '05';
    /**
     * @var string The service name, used in the endpoint URL.
     */
    protected $service;

    /**
     * @var string The protocol version number.
     */
    protected $VPSProtocol = '4.00';

    /**
     * @var string Endpoint base URLs.
     */
    protected $liveEndpoint = 'https://live.sagepay.com/gateway/service';
    protected $testEndpoint = 'https://test.sagepay.com/gateway/service';

    /**
     * Convenience method to switch iframe mode on or off.
     * This sets the profile parameter.
     *
     * @param bool $value True to use an iframe profile for hosted forms.
     * @return $this
     */
    public function setIframe($value)
    {
        $profile = ((bool)$value ? static::PROFILE_LOW : static::PROFILE_NORMAL);

        return $this->setParameter('profile', $profile);
    }

    /**
     * The name of the service used in the endpoint to send the message.
     * For MANY services, the URL fragment will be the lower case version
     * of the action.
     *
     * @return string Sage Pay endpoint service name.
     */
    public function getService()
    {
        return strtolower($this->getTxType());
    }

    /**
     * If it is used, i.e. needed for an enpoint, then it must be defined.
     *
     * @return string the transaction type.
     * @throws InvalidRequestException
     */
    public function getTxType()
    {
        throw new InvalidRequestException('Transaction type not defined.');
    }

    /**
     * @return array
     * @throws InvalidRequestException
     */
    protected function getBaseData()
    {
        $data = array();

        $data['VPSProtocol'] = $this->VPSProtocol;
        $data['TxType'] = $this->getTxType();
        $data['Vendor'] = $this->getVendor();
        $data['AccountType'] = $this->getAccountType() ?: static::ACCOUNT_TYPE_E;

        if ($language = $this->getLanguage()) {
            // Although documented as ISO639, the gateway expects
            // the code to be upper case.

            $language = strtoupper($language);

            // If a locale has been passed in instead, then just take the first part.
            // e.g. both "en" and "en-gb" becomes "EN".

            list($language) = preg_split('/[-_]/', $language);

            $data['Language'] = $language;
        }

        return $data;
    }

    /**
     * Get either the billing or the shipping address from
     * the card object, mapped to Sage Pay field names.
     *
     * @param string $type 'Billing' or 'Shipping'
     * @return array
     */
    protected function getAddressData($type = 'Billing')
    {
        $card = $this->getCard();

        // Mapping is Sage Pay name => Omnipay Name

        $mapping = [
            'Firstnames' => 'FirstName',
            'Surname' => 'LastName',
            'Address1' => 'Address1',
            'Address2' => 'Address2',
            'City' => 'City',
            'PostCode' => 'Postcode',
            'State' => 'State',
            'Country' => 'Country',
            'Phone' => 'Phone',
        ];

        $data = [];

        foreach ($mapping as $sagepayName => $omnipayName) {
            $data[$sagepayName] = call_user_func([$card, 'get' . $type . $omnipayName]);
        }

        // The state must not be set for non-US countries.

        if ($data['Country'] !== 'US') {
            $data['State'] = '';
        }

        return $data;
    }

    /**
     * Add the billing address details to the data.
     *
     * @param array $data
     * @return array $data
     */
    protected function getBillingAddressData(array $data = [])
    {
        $address = $this->getAddressData('Billing');

        foreach ($address as $name => $value) {
            $data['Billing' . $name] = $value;
        }

        return $data;
    }

    /**
     * Add the delivery (shipping) address details to the data.
     * Use the Billing address if the billingForShipping option is set.
     *
     * @param array $data
     * @return array $data
     */
    protected function getDeliveryAddressData(array $data = [])
    {
        $address = $this->getAddressData(
            (bool)$this->getBillingForShipping() ? 'Billing' : 'Shipping'
        );

        foreach ($address as $name => $value) {
            $data['Delivery' . $name] = $value;
        }

        return $data;
    }

    /**
     * Send data to the remote gateway, parse the result into an array,
     * then use that to instantiate the response object.
     *
     * @param array
     * @return Response The reponse object initialised with the data returned from the gateway.
     */
    public function sendData($data)
    {
        // Issue #20 no data values should be null.

        array_walk($data, function (&$value) {
            if (!isset($value)) {
                $value = '';
            }
        });

        $httpResponse = $this
            ->httpClient
            ->request(
                'POST',
                $this->getEndpoint(),
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query($data)
            );

        // We might want to check $httpResponse->getStatusCode()

        $responseData = static::parseBodyData($httpResponse);

        return $this->createResponse($responseData);
    }

    /**
     * The payload consists of name=>value pairs, each on a separate line.
     *
     * @param ResponseInterface $httpResponse
     * @return array
     */
    public static function parseBodyData(ResponseInterface $httpResponse)
    {
        $bodyText = (string)$httpResponse->getBody();

        // Split the bodyText into lines.

        $lines = preg_split('/[\n\r]+/', $bodyText);

        $responseData = [];

        foreach ($lines as $line) {
            $line = explode('=', $line, 2);

            if (!empty($line[0])) {
                $responseData[trim($line[0])] = isset($line[1]) ? trim($line[1]) : '';
            }
        }

        return $responseData;
    }

    /**
     * @return string URL for the test or live gateway, as appropriate.
     */
    public function getEndpoint()
    {
        return sprintf(
            '%s/%s.vsp',
            $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint,
            $this->getService()
        );
    }

    /**
     * Indicates whether a NORMAL or LOW profile page is to be used
     * for hosted forms.
     *
     * @return string|null
     */
    public function getProfile()
    {
        return $this->getParameter('profile');
    }

    /**
     * @param string $value One of static::PROFILE_NORMAL or static::PROFILE_LOW
     * @return $this
     */
    public function setProfile($value)
    {
        return $this->setParameter('profile', $value);
    }

    /**
     * @return string The custom vendor data.
     */
    public function getVendorData()
    {
        return $this->getParameter('vendorData');
    }

    /**
     * Set custom vendor data that will be stored against the gateway account.
     *
     * @param string $value ASCII alphanumeric and spaces, max 200 characters.
     */
    public function setVendorData($value)
    {
        return $this->setParameter('vendorData', $value);
    }

    /**
     * Use this flag to indicate you wish to have a token generated and stored in the Sage Pay
     * database and returned to you for future use.
     * Values set in constants CREATE_TOKEN_*
     *
     * @param bool|int $createToken 0 = This will not create a token from the payment (default).
     * @return $this
     */
    public function setCreateToken($value)
    {
        return $this->setParameter('createToken', $value);
    }

    /**
     * @return int static::CREATE_TOKEN_YES or static::CREATE_TOKEN_NO
     */
    public function getCreateToken()
    {
        return $this->getParameter('createToken');
    }

    /**
     * Alias for setCreateToken()
     */
    public function setCreateCard($value)
    {
        return $this->setCreateToken($value);
    }

    /**
     * Alias for getCreateToken()
     */
    public function getCreateCard()
    {
        return $this->getCreateToken();
    }

    /**
     * An optional flag to indicate if you wish to continue to store the
     * Token in the SagePay token database for future use.
     * Values set in contants SET_TOKEN_*
     *
     * Note: this is just an override method. It is best to leave this unset,
     * and use either setToken or setCardReference. This flag will then be
     * set automatically.
     *
     * @param bool|int|null $value Will be cast to bool when used
     * @return $this
     */
    public function setStoreToken($value)
    {
        return $this->setParameter('storeToken', $value);
    }

    /**
     * @return bool|int|null
     */
    public function getStoreToken()
    {
        return $this->getParameter('storeToken');
    }

    /**
     * @param string the original VPS transaction ID; used to capture/void
     * @return $this
     */
    public function setVpsTxId($value)
    {
        return $this->setParameter('vpsTxId', $value);
    }

    /**
     * @return string
     */
    public function getVpsTxId()
    {
        return $this->getParameter('vpsTxId');
    }

    /**
     * @param string the original SecurityKey; used to capture/void
     * @return $this
     */
    public function setSecurityKey($value)
    {
        return $this->setParameter('securityKey', $value);
    }

    /**
     * @return string
     */
    public function getSecurityKey()
    {
        return $this->getParameter('securityKey');
    }

    /**
     * @param string the original txAuthNo; used to capture/void
     * @return $this
     */
    public function setTxAuthNo($value)
    {
        return $this->setParameter('txAuthNo', $value);
    }

    /**
     * @return string
     */
    public function getTxAuthNo()
    {
        return $this->getParameter('txAuthNo');
    }

    /**
     * @param string the original txAuthNo; used to capture/void
     * @return $this
     */
    public function setRelatedTransactionId($value)
    {
        return $this->setParameter('relatedTransactionId', $value);
    }

    /**
     * @return string
     */
    public function getRelatedTransactionId()
    {
        return $this->getParameter('relatedTransactionId');
    }

    /**
     * @return int static::ALLOW_GIFT_AID_YES or static::ALLOW_GIFT_AID_NO
     */
    public function getAllowGiftAid()
    {
        return $this->getParameter('allowGiftAid');
    }

    /**
     * This flag allows the gift aid acceptance box to appear for this transaction
     * on the payment page. This only appears if your vendor account is Gift Aid enabled.
     *
     * Values defined in static::ALLOW_GIFT_AID_* constant.
     *
     * @param bool|int $allowGiftAid value that casts to boolean
     * @return $this
     */
    public function setAllowGiftAid($value)
    {
        $this->setParameter('allowGiftAid', $value);
    }

    /**
     * Return the Response object, initialised with the parsed response data.
     * @param array $data The data parsed from the response gateway body.
     * @return Response
     */
    protected function createResponse($data)
    {
        return $this->response = new Response($this, $data);
    }

    /**
     * Filters out any characters that SagePay does not support from the item name.
     *
     * Believe it or not, SagePay actually have separate rules for allowed characters
     * for item names and discount names, hence the need for two separate methods.
     *
     * @param string $name
     * @return string
     */
    protected function filterItemName($name)
    {
        $standardChars = '0-9a-zA-Z';
        $allowedSpecialChars = " +'/\\&:,.-{}";
        $pattern = '`[^' . $standardChars . preg_quote($allowedSpecialChars, '/') . ']`';
        $name = trim(substr(preg_replace($pattern, '', $name), 0, 100));

        return $name;
    }

    /**
     * Filters out any characters that SagePay does not support from the item name for
     * the non-xml basket integration
     *
     * @param string $name
     * @return string
     */
    protected function filterNonXmlItemName($name)
    {
        $standardChars = '0-9a-zA-Z';
        $allowedSpecialChars = " +'/\\,.-{};_@()^\"~$=!#?|[]";
        $pattern = '`[^' . $standardChars . preg_quote($allowedSpecialChars, '/') . ']`';
        $name = trim(substr(preg_replace($pattern, '', $name), 0, 100));

        return $name;
    }

    /**
     * Filters out any characters that SagePay does not support from the discount name.
     *
     * Believe it or not, SagePay actually have separate rules for allowed characters
     * for item names and discount names, hence the need for two separate methods.
     *
     * @param string $name
     * @return string
     */
    protected function filterDiscountName($name)
    {
        $standardChars = "0-9a-zA-Z";
        $allowedSpecialChars = " +'/\\:,.-{};_@()^\"~[]$=!#?|";
        $pattern = '`[^' . $standardChars . preg_quote($allowedSpecialChars, '/') . ']`';
        $name = trim(substr(preg_replace($pattern, '', $name), 0, 100));

        return $name;
    }

    /**
     * A JSON transactionReference passed in is split into its
     * component parts.
     *
     * @param string $value original transactionReference in JSON format.
     */
    public function setTransactionReference($value)
    {
        $reference = json_decode($value, true);

        if (json_last_error() === 0) {
            if (isset($reference['VendorTxCode'])) {
                $this->setRelatedTransactionId($reference['VendorTxCode']);
            }

            if (isset($reference['VPSTxId'])) {
                $this->setVpsTxId($reference['VPSTxId']);
            }

            if (isset($reference['SecurityKey'])) {
                $this->setSecurityKey($reference['SecurityKey']);
            }

            if (isset($reference['TxAuthNo'])) {
                $this->setTxAuthNo($reference['TxAuthNo']);
            }
        }

        return parent::setTransactionReference($value);
    }


    public function setThreeDSNotificationURL($value)
    {
        return $this->setParameter('ThreeDSNotificationURL', $value);
    }

    public function getThreeDSNotificationURL()
    {
        return $this->getParameter('ThreeDSNotificationURL');
    }

    public function setBrowserJavascriptEnabled($value)
    {
        return $this->setParameter('BrowserJavascriptEnabled', $value);
    }

    public function getBrowserJavascriptEnabled()
    {
        return $this->getParameter('BrowserJavascriptEnabled');
    }

    public function setBrowserLanguage($value)
    {
        return $this->setParameter('BrowserLanguage', $value);
    }

    public function getBrowserLanguage()
    {
        return $this->getParameter('BrowserLanguage');
    }

    public function setChallengeWindowSize($value)
    {
        return $this->setParameter('ChallengeWindowSize', $value);
    }

    public function getChallengeWindowSize()
    {
        return $this->getParameter('ChallengeWindowSize');
    }

    public function setBrowserJavaEnabled($value)
    {
        return $this->setParameter('BrowserJavaEnabled', $value);
    }

    public function getBrowserJavaEnabled()
    {
        return $this->getParameter('BrowserJavaEnabled');
    }

    public function setBrowserColorDepth($value)
    {
        return $this->setParameter('BrowserColorDepth', $value);
    }

    public function getBrowserColorDepth()
    {
        return $this->getParameter('BrowserColorDepth');
    }

    public function setBrowserScreenHeight($value)
    {
        return $this->setParameter('BrowserScreenHeight', $value);
    }

    public function getBrowserScreenHeight()
    {
        return $this->getParameter('BrowserScreenHeight');
    }

    public function setBrowserScreenWidth($value)
    {
        return $this->setParameter('BrowserScreenWidth', $value);
    }

    public function getBrowserScreenWidth()
    {
        return $this->getParameter('BrowserScreenWidth');
    }

    public function setBrowserTZ($value)
    {
        return $this->setParameter('BrowserTZ', $value);
    }

    public function getBrowserTZ()
    {
        return $this->getParameter('BrowserTZ');
    }
}
