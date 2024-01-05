<?php

namespace NexiCheckout\Application\Model\Api\Payment;

class ReferenceInformationUpdate extends \NexiCheckout\Application\Model\Api\BaseRequest
{
    /**
     * @var string|null
     */
    protected $sEndpoint = "/v1/payments/{paymentId}/referenceinformation";

    /**
     * @var string|null
     */
    protected $sRequestMethod = self::METHOD_PUT;

    /**
     * Sends ReferenceInformationUpdate request
     *
     * @param  string $sPaymentId
     * @param  array $aParams
     * @return string
     * @throws \Exception
     */
    public function sendRequest($sPaymentId, $aParams)
    {
        $this->setUrlParameters([
            '{paymentId}' => $sPaymentId,
        ]);
        return $this->sendCurlRequest($aParams);
    }
}
