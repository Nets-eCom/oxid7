<?php

namespace NexiCheckout\Application\Model\Api\Payment;

use NexiCheckout\Application\Model\Api\OrderItemRequest;
use OxidEsales\Eshop\Application\Model\Order;

class PaymentCancel extends OrderItemRequest
{
    /**
     * @var string|null
     */
    protected $sEndpoint = "/v1/payments/{paymentId}/cancels";

    /**
     * @var string|null
     */
    protected $sRequestMethod = self::METHOD_POST;

    /**
     * Sends PaymentCancel request
     *
     * @param  Order $oOrder
     * @return string
     * @throws \Exception
     */
    public function sendRequest($oOrder)
    {
        $aOrderItems = $this->getOrderItems($oOrder, false);
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
