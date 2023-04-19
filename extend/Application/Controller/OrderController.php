<?php

namespace Es\NetsEasy\extend\Application\Controller;

use Es\NetsEasy\Application\Helper\Language;
use Es\NetsEasy\Application\Model\Api\Payment\PaymentRetrieve;
use Es\NetsEasy\Application\Model\ResourceModel\NetsTransactions;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;
use Es\NetsEasy\Application\Helper\Api;
use Es\NetsEasy\Application\Helper\DebugLog;
use Es\NetsEasy\Application\Helper\Order as OrderHelper;

/**
 * Class controls nets payment process
 * It also shows the nets embedded checkout window
 */
class OrderController extends OrderController_parent
{
     /**
     * calling parent::render() based on current data paased by embedded response
     * and redirecting to thankyou page if order already finalized
     * @return void
     */
    public function execute()
    {
        DebugLog::getInstance()->log("nets execute excute called GET " . json_encode($_GET) . ' post ' . json_encode($_POST));

        $pid = Registry::getConfig()->getRequestParameter('paymentId');
        $pidFail = Registry::getConfig()->getRequestParameter('paymentFailed');
        $paymnetdonefor_pid = Registry::getSession()->getVariable("paymnetdonefor_pid");


        $oNetsTransaction = oxNew(NetsTransactions::class);
        $iPaymentStatus = $oNetsTransaction->getTransactionIdByPaymentId($pid);

        if ($pid && ($pid != $paymnetdonefor_pid) && ($pidFail != 'true')) {
            $oUser = $this->getUser();
            $sDeliveryAddress = $oUser->getEncodedDeliveryAddress();
            $_POST['sDeliveryAddressMD5'] = $sDeliveryAddress;

            DebugLog::getInstance()->log("nets execute parent function for pid : ".json_encode($pid));
            Registry::getSession()->setVariable("paymnetdonefor_pid", $pid);
            return parent::execute();
        } elseif ($pid && !empty($iPaymentStatus) && ($pidFail != 'true')) {
            DebugLog::getInstance()->log("nets redirect to thankyou page Pid : ".json_encode($pid));
            Registry::getUtils()->redirect(Registry::getConfig()->getCurrentShopUrl() . 'index.php?cl=thankyou&orderid=' . $iPaymentStatus[0]['oxorder_id'], true, 301);
            return false;
        } elseif ($pidFail == 'true') {
            DebugLog::getInstance()->log("nets embedded case redirect to payment error PID : ".json_encode($pid));
            return $this->redirectToPaymentStepWithErrorMsg("NETS_ERROR_TRANSACTIONID_MISSING");
        } else {
            DebugLog::getInstance()->log("nets calling parent execute due to normal case ");
            return parent::execute();
        }

        DebugLog::getInstance()->log("nets default calling parent execute ");
        return parent::execute();
    }
    /**
     * Load previously created order
     *
     * @return Order|false
     */
    protected function netsGetOrder()
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
        $pid = Registry::getConfig()->getRequestParameter('paymentId');
        $pidFail = Registry::getConfig()->getRequestParameter('paymentFailed');
        
        if (!$pid && $pidFail != 'true') {
            $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
            $blIsUserRedirected = Registry::getSession()->getVariable('netsCustomerIsRedirected');
            if (!empty($sSessChallenge) && $blIsUserRedirected === true) {
                OrderHelper::getInstance()->cancelCurrentOrder();
            }
            Registry::getSession()->deleteVariable('netsCustomerIsRedirected');

            $sParentReturn = parent::render();
            if ($this->getIsOrderStep() && $this->getPayment()->netsIsNetsPaymentUsed() && $this->netsIsEmbedded()) {
                $this->netsPrepareEmbeddedOrderProcess();
            }
            return $sParentReturn;
        }
    }

    /**
     * Creates a fake order and sends an API request to Nets
     *
     * @return void
     */
    protected function netsPrepareEmbeddedOrderProcess()
    {
        $oUser = $this->getUser();
        $oBasket = $this->getSession()->getBasket();
        $pay_method = Registry::getSession()->getVariable('paymentid');

        if ($oUser && $oBasket->getProductsCount()) {
            try {
                $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
                $oOrder->netsPrepareDummyOrderForEmbeddedPayment($oBasket, $oUser);

                $aResponse = $oOrder->netsCreateNewNetsPayment($pay_method);
                DebugLog::getInstance()->log("netsPrepareEmbeddedOrderProcess try block " . json_encode($aResponse));

                Registry::getSession()->setVariable("nets_embedded_payment_id", $aResponse['paymentId']);
            } catch (\Exception $exc) {
                DebugLog::getInstance()->log("NetsOrderController, Error: " . $exc->getMessage());
            }
        }
    }

    /**
     * Function to get error message displayed on template file
     *
     * @return string
     */
    public function netsGetErrorMsg()
    {
        return Registry::getSession()->getVariable('nets_err_msg');
    }

    /**
     * Function to get return data after hosted payment checkout is done
     *
     * @return string
     */
    public function netsReturnHosted()
    {
        $oPayment = $this->getPayment();
        if ($oPayment && $oPayment->netsIsNetsPaymentUsed()) {
            Registry::getSession()->deleteVariable('netsCustomerIsRedirected');

            $oOrder = $this->netsGetOrder();
            if (!$oOrder) {
                return $this->redirectToPaymentStepWithErrorMsg("NETS_ERROR_ORDER_NOT_FOUND");
            }

            if (empty($oOrder->oxorder__oxtransid->value)) {
                return $this->redirectToPaymentStepWithErrorMsg("NETS_ERROR_TRANSACTIONID_MISSING");
            }

            // validate payment with api
            $sPaymentId = $oOrder->oxorder__oxtransid->value;
            $oPaymentInfo = oxNew(PaymentRetrieve::class);
            $aResponse = $oPaymentInfo->sendRequest($sPaymentId);

            if (isset($aResponse['payment']['paymentId']) && $aResponse['payment']['paymentId'] == $sPaymentId) {
                $this->netsSavePaymentDetails($aResponse, $sPaymentId);
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
    protected function netsSavePaymentDetails($aResponse, $sPaymentId)
    {
        foreach ($aResponse['payment']['charges'] as $aCharge) {
            foreach ($aCharge['orderItems'] as $aOrderItem) {
                if (isset($aCharge['chargeId'])) {
                    $oNetsTransaction = oxNew(NetsTransactions::class);
                    $oNetsTransaction->createCharge($sPaymentId, $aCharge['chargeId'], $aOrderItem['reference'], $aOrderItem['quantity'], $aOrderItem['quantity']);
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
        Registry::getUtils()->redirect(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=payment');
        return false;
    }

    /**
     * Function to get checkout js url based on environment i.e live or test
     *
     * @return string
     */
    public function netsGetCheckoutJs()
    {
        return Api::getInstance()->getCheckoutJs();
    }

    /**
     * Returns the payment id from the session
     *
     * @return string
     */
    public function netsGetPaymentId()
    {
        return Registry::getSession()->getVariable("nets_embedded_payment_id");
    }

    /**
     * Returns locale code for javascript widget
     *
     * @return string
     */
    public function netsGetLocaleCode()
    {
        return Language::getInstance()->netsGetLocaleCode();
    }

    /**
     * Function to check if it embedded checkout
     *
     * @return bool
     */
    public function netsIsEmbedded()
    {
        return Api::getInstance()->isEmbeddedMode();
    }

    /**
     * Function to fetch checkout key to pass in checkout js options based on environment live or test
     *
     * @return string
     */
    public function netsGetCheckoutKey()
    {
        return Api::getInstance()->getCheckoutKey();
    }

    /**
     * Function to compile layout style file url for the embedded checkout type
     *
     * @return string
     */
    public function netsGetLayoutJs()
    {
        return Api::getInstance()->getLayout();
    }
}
