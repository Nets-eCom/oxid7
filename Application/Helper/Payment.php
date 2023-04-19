<?php

namespace Es\NetsEasy\Application\Helper;

class Payment
{
    const METHOD_EASY = "nets_easy";

    /**
     * @var Payment
     */
    protected static $oInstance = null;

    /**
     * Create singleton instance of current helper class
     *
     * @return Payment
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Array with Nets payment method information
     *
     * @var string[][]
     */
    protected $aNetsPaymentTypes = [
        self::METHOD_EASY => [
            'option_name' => 'nets_easy_active',
            'descEN' => 'Nets Easy',
            'descDE' => 'Nets Easy',
            'shortdesc' => 'Nets Easy'
        ],
        'nets_easy_card'=>[
            'option_name' => 'nets_easy_active_card',
            'descEN' => 'Nets Easy - Cards',
            'descDE' => 'Nets Easy - Cards',
            'shortdesc' => 'Nets Easy card'
        ],
        'nets_easy_sofort'=>[
            'option_name' => 'nets_easy_active_sofort',
            'descEN' => 'Nets Easy - Sofort',
            'descDE' => 'Nets Easy - Sofort',
            'shortdesc' => 'Nets Easy sofort'
        ],
        'nets_easy_ratepay_invoice'=>[
            'option_name' => 'nets_easy_active_ratepay_invoice',
            'descEN' => 'Nets Easy - Ratepay Invoice',
            'descDE' => 'Nets Easy - Ratepay Rechnungskauf',
            'shortdesc' => 'Nets Easy ratepay invoice'
        ],
        'nets_easy_afterpay_invoice'=>[
            'option_name' => 'nets_easy_active_afterpay_invoice',
            'descEN' => 'Nets Easy - Riverty Invoice',
            'descDE' => 'Nets Easy - Riverty Rechnungskauf',
            'shortdesc' => 'Nets Easy afterpay invoice'
        ],
        'nets_easy_afterpay_instalment'=>[
            'option_name' => 'nets_easy_active_afterpay_instalment',
            'descEN' => 'Nets Easy - Riverty Installment',
            'descDE' => 'Nets Easy - Riverty Ratenkauf',
            'shortdesc' => 'Nets Easy afterpay instalment'
        ],
        'nets_easy_paypal'=>[
            'option_name' => 'nets_easy_active_paypal',
            'descEN' => 'Nets Easy - PayPal',
            'descDE' => 'Nets Easy - PayPal',
            'shortdesc' => 'Nets Easy paypal'
        ]
    ];

    /**
     * Returns nets payment methods
     *
     * @return string[][]
     */
    public function getNetsPaymentTypes()
    {
        return $this->aNetsPaymentTypes;
    }

    /**
     * Check if given payment type is a Nets payment type
     *
     * @param  string $sPaymentType
     * @return bool
     */
    public function isNetsPayment($sPaymentType)
    {
        if (isset($this->aNetsPaymentTypes[$sPaymentType])) {
            return true;
        }
        return false;
    }

    /**
     * Function to get Nets Payment Description
     *
     * @param  string $sPaymentId The payment id
     * @return bool
     */
    public function getNetsPaymentDesc($sPaymentId)
    {
        if (isset($this->aNetsPaymentTypes[$sPaymentId]['desc'])) {
            return $this->aNetsPaymentTypes[$sPaymentId]['desc'];
        }
        return false;
    }

    /**
     * Function to get Nets Payment Short Description
     *
     * @param  string $sPaymentId The payment id
     * @return bool
     */
    public function getNetsPaymentShortDesc($sPaymentId)
    {
        if (isset($this->aNetsPaymentTypes[$sPaymentId]['shortdesc'])) {
            return $this->aNetsPaymentTypes[$sPaymentId]['shortdesc'];
        }
        return false;
    }
}
