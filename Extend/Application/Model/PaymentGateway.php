<?php

namespace NexiCheckout\Extend\Application\Model;

use NexiCheckout\Application\Helper\Api;
use NexiCheckout\Application\Helper\DebugLog;
use NexiCheckout\Application\Helper\Language;
use NexiCheckout\Application\Model\Api\Payment\PaymentRetrieve;
use NexiCheckout\Application\Model\Api\Payment\ReferenceInformationUpdate;
use NexiCheckout\Application\Model\ResourceModel\NexiCheckoutTransactions;
use NexiCheckout\Core\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;

class PaymentGateway extends PaymentGateway_parent
{
    /**
     * @param  int $dAmount
     * @param  object $oOrder
     * @return bool
     */
    public function executePayment($dAmount, &$oOrder)
    {
        DebugLog::getInstance()->log("check if this payment method is valid in nexi-checkout : " .$oOrder->oxorder__oxpaymenttype->value );
        
        if (!Module::isNexiCheckout($oOrder->oxorder__oxpaymenttype->value)) {
            return parent::executePayment($dAmount, $oOrder);
        }
        DebugLog::getInstance()->log("payment method check pass : ".$oOrder->oxorder__oxpaymenttype->value);
        return $this->handlePayment($dAmount, $oOrder);
    }

    /**
     * @param  double $dAmount
     * @param  Order $oOrder
     * @return bool
     */
    protected function handlePayment($dAmount, $oOrder)
    {
        $oOrder->setOrderNumber();

        try {
            if (Api::getInstance()->isHostedMode()) {
                $this->handleHostedMode($dAmount, $oOrder);
            } elseif (Api::getInstance()->isEmbeddedMode()) {
                $this->handleEmbeddedMode($dAmount, $oOrder);
            }
        } catch(\Exception $exc) {
            $this->_iLastErrorNo = $exc->getCode();
            $this->_sLastError = $exc->getMessage();
            return false;
        }
        return true;
    }

    /**
     * @param  double $dAmount
     * @param  Order $oOrder
     * @return void
     */
    protected function handleHostedMode($dAmount, $oOrder)
    {
        $pay_method = Registry::getSession()->getVariable('paymentid');
        $aResponse = $oOrder->createNewPayment($pay_method);

        Registry::getSession()->setVariable('nexiCheckoutCustomerIsRedirected', true);
        Registry::getUtils()->redirect($aResponse["hostedPaymentPageUrl"]."&language=".Language::getInstance()->getLocaleCode());
    }


    /**
     * @param  double $dAmount
     * @param  Order $oOrder
     * @return void 
     */
    protected function handleEmbeddedMode($dAmount, $oOrder)
    {
        DebugLog::getInstance()->log("handleEmbeddedMode called ".json_encode($oOrder->getId()));
        $sPaymentId = Registry::getSession()->getVariable("nexi_checkout_embedded_payment_id");
        $transaction = oxNew(NexiCheckoutTransactions::class);
        $transaction->updateTransactionWithOrderInfo($sPaymentId, $oOrder->getId(), $oOrder->oxorder__oxordernr->value);

        $oOrder->setTransactionId($sPaymentId);

        $oPaymentInfo = oxNew(PaymentRetrieve::class);
        $aResponse = $oPaymentInfo->sendRequest($sPaymentId);

        DebugLog::getInstance()->log("payment api status NexiCheckoutOrder, response checkout url ".$aResponse['payment']['checkout']['url']);

        if (isset($aResponse['payment']['charges'])) {
            foreach ($aResponse['payment']['charges'] as $ky => $val) {
                foreach ($val['orderItems'] as $key => $value) {
                    if (isset($val['chargeId'])) {
                        $transaction = oxNew(NexiCheckoutTransactions::class);
                        $transaction->createCharge($sPaymentId, $val['chargeId'], $value['reference'], $value['quantity'], $value['quantity']);
                    }
                }
            }
        }

        $aRefUpdateData = [
            'reference' => $oOrder->oxorder__oxordernr->value,
            'checkoutUrl' => $aResponse['payment']['checkout']['url']
        ];
        $oReferenceInformationUpdate = oxNew(ReferenceInformationUpdate::class);
        $oReferenceInformationUpdate->sendRequest($sPaymentId, $aRefUpdateData);
    }
}
