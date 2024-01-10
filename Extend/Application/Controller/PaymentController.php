<?php

namespace NexiCheckout\Extend\Application\Controller;

use NexiCheckout\Application\Helper\Order as OrderHelper;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class defines description of nexi checkout payment
 */
class PaymentController extends PaymentController_parent
{
    /**
     * Function to initialize the class 
     *
     * @return void
     */
    public function init()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blIsUserRedirected = Registry::getSession()->getVariable('nexiCheckoutCustomerIsRedirected');
        if (!empty($sSessChallenge) && $blIsUserRedirected === true) {
            OrderHelper::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('nexiCheckoutCustomerIsRedirected');

        parent::init();
    }
}
