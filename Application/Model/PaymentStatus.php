<?php

namespace NexiCheckout\Application\Model;

use NexiCheckout\Application\Model\Api\Payment\PaymentRetrieve;
use NexiCheckout\Application\Model\ResourceModel\NexiCheckoutTransactions;
use OxidEsales\Eshop\Application\Model\Order;

class PaymentStatus
{
    /**
     * Function to check the nexi checkout payment status and display in admin order list backend page
     *
     * @param  Order $oOrder
     * @return array
     */
    public function checkEasyStatus($oOrder)
    {
        if (empty($oOrder->oxorder__oxtransid->value)) {
            $transaction = oxNew(NexiCheckoutTransactions::class);
            $transaction->updatePaymentStatus(NexiCheckoutTransactions::STATUS_CANCELLED, $oOrder->getId());

            $oOrder->cancelOrder();
            return ["paymentErr" => "Order is cancelled. Payment not found."];
        }

        $transaction = oxNew(NexiCheckoutTransactions::class);
        $iPaymentStatus = $transaction->getPaymentStatusByOrderId($oOrder->getId());
        // if order is cancelled and payment is not updated as cancelled, call nexi checkout cancel payment api
        if ($oOrder->oxorder__oxstorno->value == 1 && $iPaymentStatus != NexiCheckoutTransactions::STATUS_CANCELLED) {
            try {
                $oOrder->cancelOrder();
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        try {
            // Get payment status from nexi checkout payments api
            $oPaymentInfo = oxNew(PaymentRetrieve::class);
            $aResponse = $oPaymentInfo->sendRequest($oOrder->oxorder__oxtransid->value);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $allStatus = $this->getPaymentStatus($aResponse, $oOrder);

        $transaction = oxNew(NexiCheckoutTransactions::class);
        $transaction->updatePaymentStatus($allStatus['dbPayStatus'], $oOrder->getId());

        return $allStatus;
    }

    /**
     * Function to get payment status
     *
     * @param  array $response
     * @param  Order $oOrder
     * @return array
     */
    public function getPaymentStatus($response, $oOrder)
    {
        $transaction = oxNew(NexiCheckoutTransactions::class);

        $dbPayStatus = '';
        $pending = '';
        $cancelledAmount = isset($response['payment']['summary']['cancelledAmount']) ? $response['payment']['summary']['cancelledAmount'] : '0';
        $reservedAmount = isset($response['payment']['summary']['reservedAmount']) ? $response['payment']['summary']['reservedAmount'] : '0';
        $chargedAmount = isset($response['payment']['summary']['chargedAmount']) ? $response['payment']['summary']['chargedAmount'] : '0';
        $refundedAmount = isset($response['payment']['summary']['refundedAmount']) ? $response['payment']['summary']['refundedAmount'] : '0';
        if (isset($response['payment']['refunds'])) {
            if (in_array("Pending", array_column($response['payment']['refunds'], 'state'))) {
                $pending = "Pending";
            }
        }
        $partialChargedAmount = $reservedAmount - $chargedAmount;
        $partialRefundedAmount = $reservedAmount - $refundedAmount;
        $chargeid = isset($response['payment']['charges'][0]['chargeId']) ? $response['payment']['charges'][0]['chargeId'] : '';
        $chargedate = isset($response['payment']['charges'][0]['created']) ? $response['payment']['charges'][0]['created'] : date('Y-m-d');
        $A2A = $response['payment']['paymentDetails']['paymentType'] == 'A2A' ? TRUE : FALSE;
        if ($A2A) {
            $reservedAmount = isset($response['payment']['summary']['chargedAmount']) ? $response['payment']['summary']['chargedAmount'] : '0';
        }
        if ($reservedAmount) {
            if ($cancelledAmount) {
                $langStatus = "cancel";
                $paymentStatus = "Cancelled";
                $dbPayStatus = NexiCheckoutTransactions::STATUS_CANCELLED; // For payment status as cancelled in nexi_checkout_transactions db table
            } elseif ($chargedAmount) {
                if ($reservedAmount != $chargedAmount) {
                    $paymentStatus = "Partial Charged";
                    $langStatus = "partial_charge";
                    $dbPayStatus = NexiCheckoutTransactions::STATUS_PARTIALLY_CHARGED; // For payment status as Partial Charged in nexi_checkout_transactions db table

                    $transaction->updatePartialAmountAndChargeId($partialChargedAmount, $chargeid, $oOrder->getId());

                    $oOrder->markAsPaid($chargedate);
                } elseif ($pending) {
                    $paymentStatus = "Refund Pending";
                    $langStatus = "refund_pending";
                } elseif ($refundedAmount) {
                    if ($reservedAmount != $refundedAmount) {
                        $paymentStatus = "Partial Refunded";
                        $langStatus = "partial_refund";
                        $dbPayStatus = NexiCheckoutTransactions::STATUS_PARTIALLY_REFUNDED; // For payment status as Partial Charged in nexi_checkout_transactions db table

                        $transaction->updatePartialAmountAndChargeId($partialChargedAmount, $chargeid, $oOrder->getId());

                        $oOrder->markAsPaid($chargedate);
                    } else {
                        $paymentStatus = "Refunded";
                        $langStatus = "refunded";
                        $dbPayStatus = NexiCheckoutTransactions::STATUS_REFUNDED; // For payment status as Refunded in nexi_checkout_transactions db table
                    }
                } else {
                    $paymentStatus = "Charged";
                    $langStatus = "charged";
                    $dbPayStatus = NexiCheckoutTransactions::STATUS_CHARGED; // For payment status as Charged in nexi_checkout_transactions db table
                }
            } else {
                $paymentStatus = 'Reserved';
                $langStatus = "reserved";
                $dbPayStatus = NexiCheckoutTransactions::STATUS_AUTHORIZED; // For payment status as Authorized in nexi_checkout_transactions db table
            }
        } else {
            $paymentStatus = "Failed";
            $langStatus = "failed";
            $dbPayStatus = NexiCheckoutTransactions::STATUS_FAILED; // For payment status as Failed in nexi_checkout_transactions db table
        }
        return ["payStatus" => $paymentStatus, "langStatus" => $langStatus, "dbPayStatus" => $dbPayStatus];
    }
}
