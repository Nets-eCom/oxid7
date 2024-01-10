<?php

namespace NexiCheckout\Application\Model\Api\Payment;

use NexiCheckout\Application\Model\Api\OrderItemRequest;
use NexiCheckout\Core\Module;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Price;
use NexiCheckout\Application\Helper\Api;
use NexiCheckout\Application\Helper\Countrylist;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class PaymentCreate extends OrderItemRequest
{
    private ModuleSettingServiceInterface $setting;

    public function __construct()
    {
        $this->setting = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingServiceInterface::class);
    }

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
            'termsUrl' => $this->setting->getString('nexi_checkout_terms_url', Module::ID),
            'merchantTermsUrl' => $this->setting->getString('nexi_checkout_merchant_terms_url', Module::ID),
            'merchantHandlesConsumerData' => true,
            'consumer' => $this->getConsumerData($oOrder),
        ];

        if (Api::getInstance()->isEmbeddedMode()) {
            $stoken = '&stoken='.Registry::getSession()->getSessionChallengeToken();
            $aCheckout['url'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=execute'.$stoken);
        } else {
            $aCheckout['returnUrl'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=returnHosted'.$this->getReturnParameters());
            $aCheckout['cancelUrl'] = urldecode(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=payment');
        }

        if ($this->setting->getBoolean('nexi_checkout_autocapture', Module::ID)) {
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
        return [
            'order' => [
                'items' => $this->getOrderItems($oOrder),
                'amount' => Api::getInstance()->formatPrice($oOrder->oxorder__oxtotalordersum->value),
                'currency' => $oOrder->oxorder__oxcurrency->value,
                'reference' => $oOrder->getId()
            ],
            'checkout' => $this->getCheckoutData($oOrder),
        ];
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
