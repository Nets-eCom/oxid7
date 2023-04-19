<?php

namespace Es\NetsEasy\extend\Application\Controller;

use OxidEsales\Eshop\Core\Registry;

/**
 * Class Extending thank you controller for adding payment id in front end
 */
class ThankyouController extends ThankyouController_parent
{
    /**
     * Get payment id from database to display in thank you page.
     *
     * @return string
     */
    public function netsGetTransactionId()
    {
            return $this->getOrder()->oxorder__oxtransid->value;
    }

    public function init()
    {
        $orderid = Registry::getConfig()->getRequestParameter('orderid');
        
        if ($this->_oOrder === null && $orderid) {
            $this->_oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
            $this->_oOrder->load($orderid);
        }
        return parent::init();
    }

}
