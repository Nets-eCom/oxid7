<?php

namespace NexiCheckout\Application\Model\Api\Payment;

class PaymentRetrieve extends \NexiCheckout\Application\Model\Api\BaseRequest
{
    /**
     * @var string|null
     */
    protected $sEndpoint = "/v1/payments/{paymentId}";

    /**
     * @var string|null
     */
    protected $sRequestMethod = self::METHOD_GET;

    /**
     * Sends PaymentRetrieve request
     *
     * @param  string $sPaymentId
     * @return string
     * @throws \Exception
     */
    public function sendRequest($sPaymentId)
    {
        $this->setUrlParameters([
            '{paymentId}' => $sPaymentId,
        ]);
        return $this->sendCurlRequest();
    }
}
