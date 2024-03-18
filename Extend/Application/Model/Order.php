<?php

namespace NexiCheckout\Extend\Application\Model;

use Doctrine\DBAL\Query\QueryBuilder;
use NexiCheckout\Core\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;
use NexiCheckout\Application\Model\Api\Payment\ChargeRefund;
use NexiCheckout\Application\Model\Api\Payment\PaymentCancel;
use NexiCheckout\Application\Model\Api\Payment\PaymentCharge;
use NexiCheckout\Application\Model\Api\Payment\PaymentCreate;
use NexiCheckout\Application\Model\Api\Payment\PaymentRetrieve;
use NexiCheckout\Application\Model\ResourceModel\NexiCheckoutTransactions;
use NexiCheckout\Application\Helper\DebugLog;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class Order extends Order_parent
{
    private ModuleSettingServiceInterface $setting;

    public function __construct()
    {
        parent::__construct();

        $this->setting = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingServiceInterface::class);
    }

    protected $isHostedModeReturn = false;

    protected $recalculateOrderMode = null;

    protected $isFinalizeDummy = false;

    /**
     * @return bool
     */
    public function IsFinalizeDummy()
    {
        return $this->isFinalizeDummy;
    }

    /**
     * @param  bool $blIsFinalizeDummy
     * @return void
     */
    public function setIsFinalizeDummy($blIsFinalizeDummy)
    {
        $this->isFinalizeDummy = $blIsFinalizeDummy;
    }

    public function isNexiCheckout()
    {
        return Module::isNexiCheckout($this->oxorder__oxpaymenttype->value);
    }

    /**
     * Determines if this is a hosted mode return call
     *
     * @return bool
     */
    protected function isHostedModeReturn()
    {
        if (Registry::getRequest()->getRequestEscapedParameter('fnc') == 'returnHosted') {
            return true;
        }
        return false;
    }

    /**
     * Order number has to be set before payment to have it for API communication
     * The original method is protected so this is used to make it usable anyways
     *
     * @return void
     */
    public function setOrderNumber()
    {
        if (!$this->oxorder__oxordernr->value) {
            $this->setNumber();
        }
    }

    public function setTransactionId($sTransactionId)
    {
        $oQueryBuilder = $this->getQueryBuilder();
        $oQueryBuilder->update('oxorder')
            ->set('oxtransid', ':transId')
            ->where('oxid = :orderId')
            ->setParameter('transId', $sTransactionId)
            ->setParameter('orderId', $this->getId());
        $oQueryBuilder->execute();

        $this->oxorder__oxtransid = new Field($sTransactionId);
    }

    /**
     * Mark order as paid
     *
     * @return void
     */
    public function markAsPaid($sDate = false)
    {
        if (strlen($sDate) == 10) { // only a date given as parameter
            $sDate = date('Y-m-d H:i:s', strtotime($sDate));
        }

        if ($sDate === false) {
            $sDate = date('Y-m-d H:i:s');
        }

        $oQueryBuilder = $this->getQueryBuilder();
        $oQueryBuilder->update('oxorder')
            ->set('oxpaid', ':paidDate')
            ->where('oxid = :orderId')
            ->setParameter('paidDate', $sDate)
            ->setParameter('orderId', $this->getId());
        $oQueryBuilder->execute();

        $this->oxorder__oxpaid = new Field($sDate);
    }

    public function createNewPayment($pay_method = null)
    {
        $oCreatePayment = oxNew(PaymentCreate::class);
        //pass payment method name for hosted
        $aResponse = $oCreatePayment->sendRequest($this,$pay_method);

        if (!empty($aResponse['paymentId']) && $this->IsFinalizeDummy() === false) {
            $this->setTransactionId($aResponse['paymentId']);
        }

        $aRequestParams = $oCreatePayment->getRequestParameters();

        $oTransactionModel = oxNew(NexiCheckoutTransactions::class);
        $oTransactionModel->createEntry($aRequestParams, $aResponse, $this->getId(), $aResponse['paymentId'], $aRequestParams['order']['amount'], $this->oxorder__oxordernr->value);

        return $aResponse;
    }

    /**
     * @return void
     */
    public function captureOrder()
    {
        $oPaymentCharge = oxNew(PaymentCharge::class);
        $aResponse = $oPaymentCharge->sendRequest($this);

        if (!empty($aResponse['chargeId'])) {
            $this->markAsPaid();

            // save charge details in db for refund
            $transaction = oxNew(NexiCheckoutTransactions::class);

            $aRequestParams = $oPaymentCharge->getRequestParameters();
            foreach ($aRequestParams['orderItems'] as $aItem) {
                $transaction->createCharge($this->oxorder__oxtransid->value, $aResponse['chargeId'], $aItem['reference'], $aItem['quantity'], $aItem['quantity']);
            }
        }
    }

    /**
     * Sends partial capture to API via item reference and quantity
     *
     * @param  string $sCaptureReference
     * @param  int $iQuantity
     * @return void
     */
    public function captureByReference($sCaptureReference, $iQuantity)
    {
        $oPaymentCharge = oxNew(PaymentCharge::class);
        $aResponse = $oPaymentCharge->sendRequest($this, $sCaptureReference, $iQuantity);

        if (!empty($aResponse['chargeId'])) {
            // Should the order really be set to paid in a partial capture??? Behavior taken from old implementation
            $this->markAsPaid();

            // save charge details in db for partial refund
            $transaction = oxNew(NexiCheckoutTransactions::class);
            $transaction->createCharge($this->oxorder__oxtransid->value, $aResponse['chargeId'], $sCaptureReference, $iQuantity, $iQuantity);
        }
    }

    /**
     * @return void
     */
    public function refundOrder()
    {
        $oPaymentInfo = oxNew(PaymentRetrieve::class);
        $aResponse = $oPaymentInfo->sendRequest($this->oxorder__oxtransid->value);

        foreach ($aResponse['payment']['charges'] as $aCharge) {
            $oChargeRefund = oxNew(ChargeRefund::class);
            $oChargeRefund->sendRequestByCharge($aCharge);

            $transaction = oxNew(NexiCheckoutTransactions::class);
            $transaction->updateChargeQtyLeft(0, $this->oxorder__oxtransid->value, $aCharge['chargeId']);
        }
    }

    /**
     * @param  string $sRefundReference
     * @param  int $iRefundQuantity
     * @return void
     */
    public function refundByReference($sRefundReference, $iRefundQuantity)
    {
        $transaction = oxNew(NexiCheckoutTransactions::class);
        $aRefundQuantities = [];
        $blBreakloop = false;

        $oPaymentInfo = oxNew(PaymentRetrieve::class);
        $aResponse = $oPaymentInfo->sendRequest($this->oxorder__oxtransid->value);
        foreach ($aResponse['payment']['charges'] as $aCharge) {
            if (in_array($sRefundReference, array_column($aCharge['orderItems'], 'reference'))) {
                $aChargeInfo = $transaction->getChargeInfo($this->oxorder__oxtransid->value, $aCharge['chargeId'], $sRefundReference);
                if (count($aChargeInfo) > 0) {
                    $iChargeLeftQuantity = $aChargeInfo[0]['charge_left_qty'];
                    $aRefundQuantities[$aCharge['chargeId']] = $aChargeInfo[0]['charge_left_qty'];
                }
                if ($iRefundQuantity <= array_sum($aRefundQuantities)) {
                    $iQuantityLeftFromArray = array_sum($aRefundQuantities) - $iRefundQuantity;
                    $iQuantityLeft = $iChargeLeftQuantity - $iQuantityLeftFromArray;
                    $aRefundQuantities[$aCharge['chargeId']] = $iQuantityLeft;
                    $blBreakloop = true;
                }
                if ($blBreakloop) {
                    foreach ($aRefundQuantities as $sChargeId => $iQuantity) {
                        $oChargeRefund = oxNew(ChargeRefund::class);
                        $oChargeRefund->sendRequestByReference($this, $sChargeId, $sRefundReference, $iQuantity);

                        $aChargeInfo = $transaction->getChargeInfo($this->oxorder__oxtransid->value, $aCharge['chargeId'], $sRefundReference);
                        if (count($aChargeInfo) > 0) {
                            $iChargeLeftQuantity = $aChargeInfo[0]['charge_left_qty'];
                        }
                        $iChargeLeftQuantity = $iQuantity - $iChargeLeftQuantity;
                        if ($iChargeLeftQuantity < 0) {
                            $iChargeLeftQuantity = - $iChargeLeftQuantity;
                        }

                        $transaction->updateChargeQtyLeft($iChargeLeftQuantity, $this->oxorder__oxtransid->value, $sChargeId, $sRefundReference);
                    }
                    break;
                }
            }
        }
    }

    /**
     * Sends a cancel request to the API
     *
     * @return void
     */
    public function cancelOrder()
    {
        $oPaymentCancel = oxNew(PaymentCancel::class);
        $oPaymentCancel->sendRequest($this);

        $this->cancelOrder();
    }

    /**
     * This method includes some of the tasks the full finalizeOrder method does
     * But there is no order created, only the basket data is transfered to the order object
     * This is needed for the embedded mode, since a payment/transaction has to be created before the order is generated
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket
     * @param \OxidEsales\Eshop\Application\Model\User   $oUser
     * @return void
     */
    public function prepareDummyOrderForEmbeddedPayment(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUser)
    {
        $this->setIsFinalizeDummy(true);

        // check if this order is already stored
        $orderId = \OxidEsales\Eshop\Core\Registry::getSession()->getVariable('sess_challenge');
        DebugLog::getInstance()->log("prepareDummyOrderForEmbeddedPayment called ".$orderId);

        // use this ID
        $this->setId($orderId);

        // copies user info
        $this->setUser($oUser);

        $this->assignUserInformation($oUser);

        // copies basket info
        $this->loadFromBasket($oBasket);
    }

    /**
     * Order checking, processing and saving method.
     * Before saving performed checking if order is still not executed (checks in
     * database oxorder table for order with know ID), if yes - returns error code 3,
     * if not - loads payment data, assigns all info from basket to new Order object
     * and saves full order with error status. Then executes payment. On failure -
     * deletes order and returns error code 2. On success - saves order (\OxidEsales\Eshop\Application\Model\Order::save()),
     * removes article from wishlist (\OxidEsales\Eshop\Application\Model\Order::_updateWishlist()), updates voucher data
     * (\OxidEsales\Eshop\Application\Model\Order::_markVouchers()). Finally sends order confirmation email to customer
     * (\OxidEsales\Eshop\Core\Email::SendOrderEMailToUser()) and shop owner (\OxidEsales\Eshop\Core\Email::SendOrderEMailToOwner()).
     * If this is order recalculation, skipping payment execution, marking vouchers as used
     * and sending order by email to shop owner and user
     * Mailing status (1 if OK, 0 on error) is returned.
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket              Basket object
     * @param object                                     $oUser                Current User object
     * @param bool                                       $blRecalculatingOrder Order recalculation
     * @return integer
     */
    public function finalizeOrder(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUser, $blRecalculatingOrder = false)
    {
        $this->recalculateOrderMode = $blRecalculatingOrder;
        if (Module::isNexiCheckout($oBasket->getPaymentId()) === true && $this->isHostedModeReturn() === true) {
            $this->isHostedModeReturn = true;
        }

        $iReturn = parent::finalizeOrder($oBasket, $oUser, $blRecalculatingOrder);

        if (in_array($iReturn, [self::ORDER_STATE_OK, self::ORDER_STATE_MAILINGERROR])) { // finalize was successful
            if ($this->setting->getBoolean('nexi_checkout_autocapture', Module::ID)) {
                $this->markAsPaid();
            }
            $this->clearSessionFlags();
        }
        return $iReturn;
    }

    protected function clearSessionFlags()
    {
        Registry::getSession()->deleteVariable("nexi_checkout_embedded_payment_id");
    }

    /**
     * Function which checks if article stock is valid.
     * If not displays error and returns false.
     *
     * @param object $oBasket basket object
     *
     * @throws \OxidEsales\Eshop\Core\Exception\NoArticleException
     * @throws \OxidEsales\Eshop\Core\Exception\ArticleInputException
     * @throws \OxidEsales\Eshop\Core\Exception\OutOfStockException
     */
    public function validateStock($oBasket)
    {
        if ($this->isHostedModeReturn === false) {
            return parent::validateStock($oBasket);
        }
    }

    /**
     * Gathers and assigns to new oxOrder object customer data, payment, delivery
     * and shipping info, customer order remark, currency, voucher, language data.
     * Additionally stores general discount and wrapping. Sets order status to "error"
     * and creates oxOrderArticle objects and assigns to them basket articles.
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket Shopping basket object
     */
    protected function loadFromBasket(\OxidEsales\Eshop\Application\Model\Basket $oBasket) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if ($this->isHostedModeReturn === false) {
            return parent::loadFromBasket($oBasket);
        }
        $this->load(Registry::getSession()->getVariable('sess_challenge'));
    }

    /**
     * Creates and returns user payment.
     *
     * @param string $sPaymentid used payment id
     * @return \OxidEsales\Eshop\Application\Model\UserPayment
     */
    protected function setPayment($sPaymentid) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if ($this->isHostedModeReturn === false) {
            return parent::setPayment($sPaymentid);
        }
        $oUserPayment = oxNew(\OxidEsales\Eshop\Application\Model\UserPayment::class);
        $oUserPayment->load($this->oxorder__oxpaymentid->value);
        return $oUserPayment;
    }

    /**
     * Updates order transaction status. Faster than saving whole object
     *
     * @param string $sStatus order transaction status
     */
    protected function setOrderStatus($sStatus) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if ($this->mollieRecalculateOrder === true && $this->oxorder__oxtransstatus->value == "NOT_FINISHED" && $this->isNexiCheckout()) {
            return;
        }
        parent::setOrderStatus($sStatus);
    }

    /**
     * Executes payment. Additionally loads oxPaymentGateway object, initiates
     * it by adding payment parameters (oxPaymentGateway::setPaymentParams())
     * and finally executes it (oxPaymentGateway::executePayment()). On failure -
     * deletes order and returns * error code 2.
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket      basket object
     * @param object                                     $oUserpayment user payment object
     * @return  integer 2 or an error code
     */
    protected function executePayment(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUserpayment) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if ($this->isHostedModeReturn === false) {
            return parent::executePayment($oBasket, $oUserpayment);
        }
        return true;
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return ContainerFactory::getInstance()->getContainer()->get(QueryBuilderFactoryInterface::class)->create();
    }

    /**
     * @inheritDoc
     */
    protected function checkOrderExist($sOxId = null): bool
    {
        if ($this->blIsNetsHostedModeReturn === false) {
            return parent::checkOrderExist($sOxId);
        }
        return false; // In hosted mode the parent finalizeOrder method is run again so the order DOES exist, but thats ok
    }
}
