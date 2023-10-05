<?php

namespace Es\NetsEasy\Application\Model\Api\Payment;

use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Price;
use Es\NetsEasy\Application\Helper\Api;
use Es\NetsEasy\Application\Helper\Countrylist;

/**
 * Class PaymentCreate
 *
 * Documentation for the API call: https://developers.nets.eu/nets-easy/en-EU/api/payment-v1/#v1-payments-post
 *
 * @package Es\NetsEasy\Application\Model\Api
 */
class PaymentCreate extends \Es\NetsEasy\Application\Model\Api\OrderItemRequest
{
    /**
     * @var string|null
     */
    protected $sEndpoint = "/v1/payments";

    /**
     * Returns the countries iso3 code by given country id
     *
     * @param string $sCountryId
     * @return string
     */
    protected function getCountryCodeById($sCountryId)
    {
        $oCountry = oxNew(Country::class);
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha3->value;
    }

    /**
     * Returns consumer data array for api request
     *
     * @param  Order $oOrder
     * @return array
     */
    protected function getConsumerData($oOrder)
    {
        $sUserID = $oOrder->oxorder__oxuserid->value;
        $oUser = oxNew("oxuser", "core");
        $oUser->Load($sUserID);

        $aConsumer = [
            'email' => $oOrder->oxorder__oxbillemail->value,
            'shippingAddress' => [
                'addressLine1' => !empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelstreet->value." ".$oOrder->oxorder__oxdelstreetnr->value : $oOrder->oxorder__oxbillstreet->value." ".$oOrder->oxorder__oxbillstreetnr->value,
                'postalCode'   => !empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelzip->value : $oOrder->oxorder__oxbillzip->value,
                'city'         => !empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelcity->value : $oOrder->oxorder__oxbillcity->value,
                'country'      => $this->getCountryCodeById(!empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelcountryid->value : $oOrder->oxorder__oxbillcountryid->value),
            ],
        ];

        $telephone = $oUser->oxuser__oxfon->value;
        $cellphone = $oUser->oxuser__oxmobfon->value;

        $phonenumber = '';
        if (!empty($telephone)) {
            $phonenumber = $telephone;
        } elseif (!empty($cellphone)) {
            $phonenumber = $cellphone;
        }

        if (!empty($phonenumber)) {
            $oCountrylist = oxNew(Countrylist::class);
            $countrylist = $oCountrylist->getList();
            $local = $countrylist[$this->getCountryCodeById(!empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelcountryid->value : $oOrder->oxorder__oxbillcountryid->value)];

            if (isset($local)) {
                $prefix ='+'.$local['phone'];
                $replace_array =array('/','-',' ',$prefix);
                $aConsumer['phoneNumber'] = [
                    'prefix' => $prefix,
                    'number' =>  str_replace( $replace_array, '', $phonenumber),
                ];
            }
        }

        if (!empty($oOrder->oxorder__oxdelcountryid->value) && !empty($oOrder->oxorder__oxdelcompany->value)) {
            $aConsumer['company'] = [
                'name' => $oOrder->oxorder__oxdelcompany->value,
                'contact' => [
                    'firstName' => $oOrder->oxorder__oxdelfname->value,
                    'lastName' => $oOrder->oxorder__oxdellname->value
                ]
            ];
        } elseif (!empty($oOrder->oxorder__oxbillcompany->value)) {
            $aConsumer['company'] = [
                'name' => $oOrder->oxorder__oxbillcompany->value,
                'contact' => [
                    'firstName' => $oOrder->oxorder__oxbillfname->value,
                    'lastName' => $oOrder->oxorder__oxbilllname->value
                ]
            ];
        } else {
            $aConsumer['privatePerson'] = [
                'firstName' => !empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdelfname->value : $oOrder->oxorder__oxbillfname->value,
                'lastName'  => !empty($oOrder->oxorder__oxdelcountryid->value) ? $oOrder->oxorder__oxdellname->value : $oOrder->oxorder__oxbilllname->value,
            ];
        }
        return $aConsumer;
    }

    /**
     * Collect all safety parameters that are needed for jumping right back in the same checkout session
     *
     * @return string
     */
    protected function getReturnParameters()
    {
        $sReturnParams = '';

        $aUrlCopyParameters = ['stoken', 'sDeliveryAddressMD5', 'oxdownloadableproductsagreement', 'oxserviceproductsagreement'];
        foreach ($aUrlCopyParameters as $sParamName) {
            $sValue = Registry::getRequest()->getRequestEscapedParameter($sParamName);
            if (!empty($sValue)) {
                $sReturnParams .= '&'.$sParamName.'='.$sValue;
            }
        }

        if (!Registry::getRequest()->getRequestEscapedParameter('stoken')) {
            $sReturnParams .= '&stoken='.Registry::getSession()->getSessionChallengeToken();
        }

        $sSid = Registry::getSession()->sid(true);
        if ($sSid != '') {
            $sReturnParams .= '&'.$sSid;
        }

        $sReturnParams .= '&ord_agb=1';
        $sReturnParams .= '&rtoken='.Registry::getSession()->getRemoteAccessToken();

        return $sReturnParams;
    }

    /**
     * Returns checkout data for api request
     *
     * @param  Order $oOrder
     * @return array
     */
    protected function getCheckoutData($oOrder)
    {
        $aCheckout = [
            'integrationType' => Api::getInstance()->getIntegrationType(),
            'termsUrl' => Registry::getConfig()->getConfigParam('nets_terms_url'),
            'merchantTermsUrl' => Registry::getConfig()->getConfigParam('nets_merchant_terms_url'),
            'merchantHandlesConsumerData' => true,
            'consumer' => $this->getConsumerData($oOrder),
        ];

        if (Api::getInstance()->isEmbeddedMode()) {
            $stoken = '&stoken='.Registry::getSession()->getSessionChallengeToken();
            $aCheckout['url'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=execute'.$stoken);
        } else {
            $aCheckout['returnUrl'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=netsReturnHosted'.$this->getReturnParameters());
            $aCheckout['cancelUrl'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=payment');
        }

        if (Registry::getConfig()->getConfigParam('nets_autocapture')) {
            $aCheckout['charge'] = true;
        }
        return $aCheckout;
    }

    /**
     * @param  Order $oOrder
     * @return array
     */
    protected function getParamsFromOrder($oOrder, $pay_method)
    {
        $aParams = [
            'order' => [
                'items' => $this->getOrderItems($oOrder),
                'amount' => Api::getInstance()->formatPrice($oOrder->oxorder__oxtotalordersum->value),
                'currency' => $oOrder->oxorder__oxcurrency->value,
                'reference' => $oOrder->getId()
            ],
            'checkout' => $this->getCheckoutData($oOrder),
        ];
        if (!is_null($pay_method) && $pay_method !='nets_easy') {
            $pay_name = '';

            if ($pay_method == 'nets_easy_card') {
                $pay_name = 'Card';
            } elseif ($pay_method =='nets_easy_sofort') {
                $pay_name = 'Sofort';
            } elseif ($pay_method == 'nets_easy_ratepay_invoice') {
                $pay_name = 'RatePayInvoice';
            } elseif ($pay_method == 'nets_easy_afterpay_invoice') {
                $pay_name = 'EasyInvoice';
            } elseif ($pay_method == 'nets_easy_afterpay_instalment') {
                $pay_name = 'EasyInstallment';
            } elseif ($pay_method == 'nets_easy_paypal') {
                $pay_name = 'Paypal';
            }


            if ($pay_name !== '') {
                $aParams['paymentMethodsConfiguration'] = [
                array(
                    'name' => $pay_name,
                    'enabled' => true
                )
                ];
            }
        }

        return $aParams;
    }

    /**
     * Sends CreatePayment request
     *
     * @param  Order $oOrder
     * @return string
     * @throws \Exception
     */
    public function sendRequest($oOrder, $pay_method = null)
    {
        $aParams = $this->getParamsFromOrder($oOrder, $pay_method);
        return $this->sendCurlRequest($aParams);
    }
}
