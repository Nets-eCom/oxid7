<?php

namespace NexiCheckout\Extend\Application\Controller\Admin;

use NexiCheckout\Application\Model\Api\Payment\PaymentCharge;
use NexiCheckout\Application\Model\Api\Payment\PaymentRetrieve;
use NexiCheckout\Application\Model\ResourceModel\NexiCheckoutTransactions;
use NexiCheckout\Core\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;
use NexiCheckout\Application\Model\PaymentStatus;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class OrderOverview extends OrderOverview_parent
{
    private ModuleSettingServiceInterface $setting;

    public function __construct()
    {
        $this->setting = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingServiceInterface::class);
    }

    /**
     * Returns current order
     *
     * @return Order|false
     */
    protected function getOrder()
    {
        $oOrder = oxNew(Order::class);
        if ($oOrder->load($this->getEditObjectId())) {
            return $oOrder;
        }
        return false;
    }

    public function getStatus()
    {
        $aReturn = false;
        $oOrder = $this->getOrder();
        if ($oOrder && $oOrder->isNexiCheckout()) {
            $oPaymentStatus = oxNew(PaymentStatus::class);
            $allStatus = $oPaymentStatus->checkEasyStatus($oOrder);
            $aReturn = [
                'payStatus' => $allStatus['payStatus'],
                'langStatus' => $allStatus['langStatus']
            ];
        }
        return $aReturn;
    }

    public function captureOrder()
    {
        $oOrder = $this->getOrder();
        if ($oOrder) {
            $oOrder->captureOrder();
        }
    }

    public function partialCaptureOrder()
    {
        $sCaptureReference = Registry::getRequest()->getRequestEscapedParameter('reference');
        $iCaptureQuantity = Registry::getRequest()->getRequestEscapedParameter('charge');
        $oOrder = $this->getOrder();
        if ($oOrder && !empty($sCaptureReference) && !empty($iCaptureQuantity)) {
            $oOrder->captureByReference($sCaptureReference, $iCaptureQuantity);
        }
    }

    public function refundOrder()
    {
        $oOrder = $this->getOrder();
        if ($oOrder) {
            $oOrder->refundOrder();
        }
    }

    public function partialRefundOrder()
    {
        $sRefundReference = Registry::getRequest()->getRequestEscapedParameter('reference');
        $iRefundQuantity = Registry::getRequest()->getRequestEscapedParameter('refund');
        $oOrder = $this->getOrder();
        if ($oOrder) {
            $oOrder->refundByReference($sRefundReference, $iRefundQuantity);
        }
    }

    public function cancelOrder()
    {
        $oOrder = $this->getOrder();
        if (!$oOrder) {
            return;
        }

        $oOrder->cancelOrder();
    }

    /**
     * Function to get list of partial charge/refund and reserved items list
     *
     * @return array
     */
    public function getPartialItemList()
    {
        $oOrder = $this->getOrder();
        
        $coupon = false;
        if ($oOrder->oxorder__oxvoucherdiscount->value != 0 || $oOrder->oxorder__oxdiscount->value != 0) {
            $coupon = true;
        }

        $oPaymentCharge = oxNew(PaymentCharge::class);
        $aOrderItems = $oPaymentCharge->getOrderItems($oOrder, $coupon);

        $aProducts = [];
        $aChargedItems = [];
        $aRefundedItems = [];
        $aItemList = [];
        foreach ($aOrderItems as $items) {
            $aProducts[$items['reference']] = [
                'name' => $items['name'],
                'quantity' => $items['quantity'],
                'price' => $items['unitPriceBrut']
            ];
        }

        $oPaymentInfo = oxNew(PaymentRetrieve::class);
        $aResponse = $oPaymentInfo->sendRequest($oOrder->oxorder__oxtransid->value);

        $A2A = $aResponse['payment']['paymentDetails']['paymentType'] == 'A2A' ? TRUE : FALSE;
        if ($A2A) {
            if (isset($aResponse['payment']['summary']['chargedAmount'])) {
                $aResponse['payment']['summary']['reservedAmount'] = $aResponse['payment']['summary']['chargedAmount'];
            }
        }
        if (!empty($aResponse['payment']['charges'])) {
            $aChargedItems = $this->getChargedItems($aResponse);
        }
        if (!empty($aResponse['payment']['refunds'])) {
            $aRefundedItems = $this->getRefundedItems($aResponse);
        }
        // get list of partial charged items and check with quantity and send list for charge rest of items
        foreach ($aProducts as $key => $prod) {
            if (array_key_exists($key, $aChargedItems)) {
                $qty = $prod['quantity'] - $aChargedItems[$key]['quantity'];
            } else {
                $qty = $prod['quantity'];
            }
            if (array_key_exists($key, $aChargedItems) && array_key_exists($key, $aRefundedItems)) {
                $qty = $aChargedItems[$key]['quantity'] - $aRefundedItems[$key]['quantity'];
                if ($qty > 0)
                    $aChargedItems[$key]['quantity'] = $qty;
            }
            if ($qty > 0) {
                $aItemList[] = [
                    'name' => $prod['name'],
                    'reference' => $key,
                    'quantity' => $qty,
                    'price' => number_format((float) ($prod['price']), 2, '.', '')
                ];
            }
            if (array_key_exists($key, $aChargedItems) && array_key_exists($key, $aRefundedItems)) {
                if ($prod['quantity'] == $aChargedItems[$key]['quantity'] && $aChargedItems[$key]['quantity'] == $aRefundedItems[$key]['quantity']) {
                    unset($aChargedItems[$key]);
                }
            }
            if ($aChargedItems[$key]['quantity'] > $prod['quantity']) {
                $aChargedItems[$key]['quantity'] = $prod['quantity'];
            }
        }
        $aLists = $this->getLists($aResponse, $aItemList, $aChargedItems, $aRefundedItems);
        // pass reserved, charged, refunded items list to frontend
        return $aLists;
    }

    /**
     * Get List of items to pass to frontend for charged, refunded items
     *
     * @param  array $response
     * @param  array $itemsList
     * @param  array $chargedItems
     * @param  array $refundedItems
     * @return array
     */
    public function getLists($response, $itemsList, $chargedItems, $refundedItems)
    {
        $reserved = $response['payment']['summary']['reservedAmount'];
        $charged = $response['payment']['summary']['chargedAmount'];
        if ($reserved != $charged) {
            if (count($itemsList) > 0) {
                $lists['reservedItems'] = $itemsList;
            }
        } else {
            if (count($chargedItems) > 0) {
                $lists['chargedItems'] = $chargedItems;
            }
        }
        $lists['chargedItemsOnly'] = $chargedItems;
        if (count($refundedItems) > 0) {
            $lists['refundedItems'] = $refundedItems;
        }
        return $lists;
    }

    /**
     * Function to enable debug mode
     *
     * @return bool
     */
    public function debugModeActive()
    {
        return $this->setting->getBoolean('nexi_checkout_blDebug_log', Module::ID);
    }

    /**
     * Function to get response
     *
     * @return string
     */
    public function getTransactionInfo()
    {
        $oOrder = $this->getOrder();

        $oPaymentInfo = oxNew(PaymentRetrieve::class);
        $aResponse = $oPaymentInfo->sendRequest($oOrder->oxorder__oxtransid->value);

        $result = json_encode($aResponse, JSON_PRETTY_PRINT);
        return $result;
    }

    /**
     * Function to get charged items list
     *
     * @param  array $response
     * @return array
     */
    public function getChargedItems($response)
    {
        $qty = 0;
        $price = 0;
        $chargedItems = [];
        foreach ($response['payment']['charges'] as $key => $values) {
            for ($i = 0; $i < count($values['orderItems']); $i ++) {
                if (array_key_exists($values['orderItems'][$i]['reference'], $chargedItems)) {
                    $aChargedItem = $chargedItems[$values['orderItems'][$i]['reference']];
                    $qty = $aChargedItem['quantity'] + $values['orderItems'][$i]['quantity'];
                    $price = ($aChargedItem['price'] * $aChargedItem['quantity']) + (float)($values['orderItems'][$i]['grossTotalAmount'] / 100);
                    $priceGross = $price / $qty;
                    $chargedItems[$values['orderItems'][$i]['reference']] = [
                        'name' => $values['orderItems'][$i]['name'],
                        'quantity' => $qty,
                        'price' => $priceGross
                    ];
                } else {
                    $priceOne = $values['orderItems'][$i]['grossTotalAmount'] / $values['orderItems'][$i]['quantity'];
                    $chargedItems[$values['orderItems'][$i]['reference']] = [
                        'name' => $values['orderItems'][$i]['name'],
                        'quantity' => $values['orderItems'][$i]['quantity'],
                        'price' => number_format((float) ($priceOne / 100), 2, '.', '')
                    ];
                }
            }
        }
        return $chargedItems;
    }

    /**
     * Function to get refund items list
     *
     * @param  array $response
     * @return array
     */
    public function getRefundedItems($response)
    {
        $qty = 0;
        $price = 0;
        $refundedItems = [];
        foreach ($response['payment']['refunds'] as $key => $values) {
            for ($i = 0; $i < count($values['orderItems']); $i ++) {
                if (array_key_exists($values['orderItems'][$i]['reference'], $refundedItems)) {
                    $qty = $refundedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                    $price = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                    $refundedItems[$values['orderItems'][$i]['reference']] = [
                        'name' => $values['orderItems'][$i]['name'],
                        'quantity' => $qty,
                        'price' => number_format((float) ($price / 100), 2, '.', '')
                    ];
                } else {
                    $refundedItems[$values['orderItems'][$i]['reference']] = [
                        'name' => $values['orderItems'][$i]['name'],
                        'quantity' => $values['orderItems'][$i]['quantity'],
                        'price' => number_format((float) ($values['orderItems'][$i]['grossTotalAmount'] / 100), 2, '.', '')
                    ];
                }
            }
        }
        return $refundedItems;
    }

    /**
     * Function to fetch payment id from database
     *
     * @return string
     */
    public function getTransactionId()
    {
        return NexiCheckoutTransactions::getTransactionIdByOrderId($this->getOrder()->getId());
    }

}
