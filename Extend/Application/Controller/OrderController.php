<?php

namespace NexiCheckout\Extend\Application\Controller;

use NexiCheckout\Application\Helper\Language;
use NexiCheckout\Application\Model\Api\Payment\PaymentRetrieve;
use NexiCheckout\Application\Model\ResourceModel\NexiCheckoutTransactions;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;
use NexiCheckout\Application\Helper\Api;
use NexiCheckout\Application\Helper\DebugLog;
use NexiCheckout\Application\Helper\Order as OrderHelper;

class OrderController extends OrderController_parent
{
     /**
     * calling parent::render() based on current data paased by embedded response
     * and redirecting to thankyou page if order already finalized
     * @return void
     */
    public function execute()
    {
        $pid = Registry::getRequest()->getRequestParameter('paymentId');
        $pidFail = Registry::getRequest()->getRequestParameter('paymentFailed');
        $paymnetdonefor_pid = Registry::getSession()->getVariable("paymnetdonefor_pid");


        $transaction = oxNew(NexiCheckoutTransactions::class);
        $iPaymentStatus = $transaction->getTransactionIdByPaymentId($pid);

        if ($pid && ($pid != $paymnetdonefor_pid) && ($pidFail != 'true')) {
            $oUser = $this->getUser();
            $sDeliveryAddress = $oUser->getEncodedDeliveryAddress();
            $_POST['sDeliveryAddressMD5'] = $sDeliveryAddress;

            DebugLog::getInstance()->log("nexi-checkout execute parent function for pid : ".json_encode($pid));
            Registry::getSession()->setVariable("paymnetdonefor_pid", $pid);
            return parent::execute();
        } elseif ($pid && !empty($iPaymentStatus) && ($pidFail != 'true')) {
            DebugLog::getInstance()->log("nexi-checkout redirect to thankyou page Pid : ".json_encode($pid));
            Registry::getUtils()->redirect(Registry::getRequest()->getCurrentShopUrl() . 'index.php?cl=thankyou&orderid=' . $iPaymentStatus[0]['oxorder_id'], true, 301);
            return false;
        } elseif ($pidFail == 'true') {
            DebugLog::getInstance()->log("nexi-checkout embedded case redirect to payment error PID : ".json_encode($pid));
            return $this->redirectToPaymentStepWithErrorMsg("NEXI_CHECKOUT_ERROR_TRANSACTIONID_MISSING");
        } else {
            DebugLog::getInstance()->log("nexi-checkout calling parent execute due to normal case ");
            return parent::execute();
        }

        DebugLog::getInstance()->log("nexi-checkout default calling parent execute ");
        return parent::execute();
    }
    /**
     * Load previously created order
     *
     * @return Order|false
     */
    protected function getOrder()
    {
        $sOrderId = Registry::getSession()->getVariable('sess_challenge');
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->isLoaded() === true) {
                return $oOrder;
            }
        }
        return false;
    }

    /**
     * Executes parent::render(), if basket is empty - redirects to main page
     * and exits the script (\OxidEsales\Eshop\Application\Model\Order::validateOrder()). Loads and passes payment
     * info to template engine. Refreshes basket articles info by additionally loading
     * each article object (\OxidEsales\Eshop\Application\Model\Order::getProdFromBasket()), adds customer
     * addressing/delivering data (\OxidEsales\Eshop\Application\Model\Order::getDelAddressInfo()) and delivery sets
     * info (\OxidEsales\Eshop\Application\Model\Order::getShipping()).
     *
     * @return string Returns name of template to render order::_sThisTemplate
     */
    public function render()
    {
        $pid = Registry::getRequest()->getRequestParameter('paymentId');
        $pidFail = Registry::getRequest()->getRequestParameter('paymentFailed');
        
        if (!$pid && $pidFail != 'true') {
            $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
            $blIsUserRedirected = Registry::getSession()->getVariable('nexiCheckoutCustomerIsRedirected');
            if (!empty($sSessChallenge) && $blIsUserRedirected === true) {
                OrderHelper::getInstance()->cancelCurrentOrder();
            }
            Registry::getSession()->deleteVariable('nexiCheckoutCustomerIsRedirected');

            $sParentReturn = parent::render();
            if ($this->getIsOrderStep() && $this->getPayment()->isNexiCheckout() && $this->isEmbedded()) {
                $this->prepareEmbeddedOrderProcess();
            }
            return $sParentReturn;
        }
    }

    protected function prepareEmbeddedOrderProcess()
    {
        $oUser = $this->getUser();
        $oBasket = Registry::getSession()->getBasket();
        $pay_method = Registry::getSession()->getVariable('paymentid');

        if ($oUser && $oBasket->getProductsCount()) {
            try {
                $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
                $oOrder->prepareDummyOrderForEmbeddedPayment($oBasket, $oUser);

                $aResponse = $oOrder->createNewPayment($pay_method);
                DebugLog::getInstance()->log("prepareEmbeddedOrderProcess try block " . json_encode($aResponse));

                Registry::getSession()->setVariable("nexi_checkout_embedded_payment_id", $aResponse['paymentId']);
            } catch (\Exception $exc) {
                DebugLog::getInstance()->log("NexiCheckoutOrderController, Error: " . $exc->getMessage());
            }
        }
    }

    /**
     * Function to get return data after hosted payment checkout is done
     *
     * @return string
     */
    public function returnHosted()
    {
        $oPayment = $this->getPayment();
        if ($oPayment && $oPayment->isNexiCheckout()) {
            Registry::getSession()->deleteVariable('nexiCheckoutCustomerIsRedirected');

            $oOrder = $this->getOrder();
            if (!$oOrder) {
                return $this->redirectToPaymentStepWithErrorMsg("NEXI_CHECKOUT_ERROR_ORDER_NOT_FOUND");
            }

            if (empty($oOrder->oxorder__oxtransid->value)) {
                return $this->redirectToPaymentStepWithErrorMsg("NEXI_CHECKOUT_ERROR_TRANSACTIONID_MISSING");
            }

            // validate payment with api
            $sPaymentId = $oOrder->oxorder__oxtransid->value;
            $oPaymentInfo = oxNew(PaymentRetrieve::class);
            $aResponse = $oPaymentInfo->sendRequest($sPaymentId);

            if (isset($aResponse['payment']['paymentId']) && $aResponse['payment']['paymentId'] == $sPaymentId) {
                $this->savePaymentDetails($aResponse, $sPaymentId);
            }
        }
        return parent::execute();
    }

    /**
     * Function to save payment details
     *
     * @param  array $aResponse
     * @param  string $sPaymentId
     * @return bool
     */
    protected function savePaymentDetails($aResponse, $sPaymentId)
    {
        foreach ($aResponse['payment']['charges'] as $aCharge) {
            foreach ($aCharge['orderItems'] as $aOrderItem) {
                if (isset($aCharge['chargeId'])) {
                    $transaction = oxNew(NexiCheckoutTransactions::class);
                    $transaction->createCharge($sPaymentId, $aCharge['chargeId'], $aOrderItem['reference'], $aOrderItem['quantity'], $aOrderItem['quantity']);
                }
            }
        }
        return true;
    }

    /**
     * Writes error-status to session and redirects to payment page
     *
     * @param string $sErrorLangIdent
     * @return false
     */
    protected function redirectToPaymentStepWithErrorMsg($sErrorLangIdent)
    {
        Registry::getSession()->setVariable('payerror', -50);
        Registry::getSession()->setVariable('payerrortext', Registry::getLang()->translateString($sErrorLangIdent));
        Registry::getUtils()->redirect(Registry::getRequest()->getCurrentShopUrl().'index.php?cl=payment');
        return false;
    }

    /**
     * Function to get checkout js url based on environment i.e live or test
     *
     * @return string
     */
    public function getCheckoutJs()
    {
        return Api::getInstance()->getCheckoutJs();
    }

    /**
     * Returns the payment id from the session
     *
     * @return string
     */
    public function getPaymentId()
    {
        return Registry::getSession()->getVariable("nexi_checkout_embedded_payment_id");
    }

    /**
     * Returns locale code for javascript widget
     *
     * @return string
     */
    public function getLocaleCode()
    {
        return Language::getInstance()->getLocaleCode();
    }

    /**
     * Function to check if it embedded checkout
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return Api::getInstance()->isEmbeddedMode();
    }

    /**
     * Function to fetch checkout key to pass in checkout js options based on environment live or test
     *
     * @return string
     */
    public function getCheckoutKey()
    {
        return Api::getInstance()->getCheckoutKey();
    }

    /**
     * Function to compile layout style file url for the embedded checkout type
     *
     * @return string
     */
    public function getLayoutJs()
    {
        return Api::getInstance()->getLayout();
    }
}
