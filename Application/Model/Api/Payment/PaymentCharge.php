<?php

namespace NexiCheckout\Application\Model\Api\Payment;

use NexiCheckout\Application\Model\Api\OrderItemRequest;
use OxidEsales\Eshop\Application\Model\Order;

class PaymentCharge extends OrderItemRequest
{
    /**
     * @var string|null
     */
    protected $sEndpoint = "/v1/payments/{paymentId}/charges";

    /**
     * @var string|null
     */
    protected $sRequestMethod = self::METHOD_POST;

    /**
     * Sends PaymentCharge request
     *
     * @param  Order $oOrder
     * @return string
     * @throws \Exception
     */
    public function sendRequest($oOrder, $sCaptureReference = false, $iQuantity = false)
    {
        $aOrderItems = $this->getOrderItems($oOrder, false);
        if ($sCaptureReference !== false && $iQuantity !== false) {
            $aOrderItems = $this->getPartialOrderItems($aOrderItems, $sCaptureReference, $iQuantity);
        }
        $aParams = [
            'orderItems' => $aOrderItems,
            'amount' => $this->getTotalAmountFromOrderItems($aOrderItems),
        ];
        $this->setUrlParameters([
            '{paymentId}' => $oOrder->oxorder__oxtransid->value,
        ]);
        return $this->sendCurlRequest($aParams);
    }
}
