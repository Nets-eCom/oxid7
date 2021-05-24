<?php
require_once getShopBasePath() . 'modules/nets/api/nets_api.php';

/**
 * order_overview.php override
 * Nets Order Overview class - In use for admin order list customization
 * Cancel, Capture, Refund and Partial nets payments
 */
class Nets_Order_Overview extends Nets_Order_Overview_parent
{

    const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';

    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';

    const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';

    const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';

    const RESPONSE_TYPE = "application/json";

    private $client;

    protected $_nets_log;

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client();
        $this->_nets_log = $this->getConfig()->getConfigParam('nets_blDebug_log');
        nets_log::log($this->_nets_log, "Nets_Order_Overview, constructor");
    }

    /**
     * Function to check the nets payment status and display in admin order list backend page
     *
     * @return Payment Status
     */
    public function is_easy($oxoder_id)
    {
        $payMethod = $this->getPaymentMethod($oxoder_id);
        if ($payMethod == 'nets_easy') {
            $payment_id = $this->getPaymentId($oxoder_id);
            if (empty($payment_id)) {
                $oDb = oxDb::getDb();
                $oDb->execute("UPDATE oxnets SET payment_status = ? WHERE transaction_id = ? ", [
                    1,
                    $this->getPaymentId($oxoder_id)
                ]);
                $oDb->execute("UPDATE oxorder SET oxstorno = ? WHERE oxid = ? ", [
                    1,
                    $oxoder_id
                ]);
                return array(
                    "paymentErr" => "Order is cancelled. Payment not found."
                );
            }

            // Get order db status from oxorder if cancelled
            $oDB = oxDb::getDb(true);
            $sSQL_select = "SELECT oxstorno FROM oxorder WHERE oxid = ? LIMIT 1";
            $orderCancel = $oDB->getOne($sSQL_select, [
                $oxoder_id
            ]);

            // Get nets payment db status from oxnets if cancelled
            $sSQL_select = "SELECT payment_status FROM oxnets WHERE oxorder_id = ? LIMIT 1";
            $payStatusDb = $oDB->getOne($sSQL_select, [
                $oxoder_id
            ]);

            // if order is cancelled and payment is not updated as cancelled, call nets cancel payment api
            if ($orderCancel && $payStatusDb != 1) {
                $data = $this->getOrderItems($oxoder_id, false);

                // call cancel api here
                $cancelUrl = $this->getVoidPaymentUrl($payment_id);
                $cancelBody = [
                    'amount' => $data['totalAmt'],
                    'orderItems' => $data['items']
                ];

                try {
                    $this->client->request('POST', $cancelUrl, [
                        'headers' => $this->getHeaders(),
                        'body' => json_encode($cancelBody)
                    ]);
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }

            try {
                // Get payment status from nets payments api
                $api_return = $this->client->request('GET', $this->getApiUrl() . $this->getPaymentId($oxoder_id), [
                    'headers' => $this->getHeaders()
                ]);
                $response = json_decode($api_return->getBody(), true);

                // echo "<pre>";
                // print_r($response);
            } catch (Exception $e) {
                return $e->getMessage();
            }

            $dbPayStatus = '';
            $paymentStatus = '';
            $cancelled = $response['payment']['summary']['cancelledAmount'];
            $reserved = $response['payment']['summary']['reservedAmount'];
            $charged = $response['payment']['summary']['chargedAmount'];
            $refunded = $response['payment']['summary']['refundedAmount'];
            $pending = $response['payment']['refunds'][0]['state'] == "Pending";
            $partialc = $reserved - $charged;
            $partialr = $reserved - $refunded;
            $chargeid = $response['payment']['charges'][0]['chargeId'];
            $chargedate = $response['payment']['charges'][0]['created'];
            if ($reserved) {
                if ($cancelled) {
                    $langStatus = "cancel";
                    $paymentStatus = "Cancelled";
                    $dbPayStatus = 1; // For payment status as cancelled in oxnets db table
                } else if ($charged) {
                    if ($reserved != $charged) {
                        $paymentStatus = "Partial Charged";
                        $langStatus = "partial_charge";
                        $dbPayStatus = 3; // For payment status as Partial Charged in oxnets db table
                        $oDB = oxDb::getDb(true);
                        $oDB->Execute("UPDATE oxnets SET partial_amount = '{$partialc}' WHERE oxorder_id = '{$oxoder_id}'");
                        $oDB->Execute("UPDATE oxnets SET charge_id = '{$chargeid}' WHERE oxorder_id = '{$oxoder_id}'");
                        $oDB->Execute("UPDATE oxorder SET oxpaid = '{$chargedate}' WHERE oxid = '{$oxoder_id}'");
                    } else if ($refunded) {
                        if ($reserved != $refunded) {
                            $paymentStatus = "Partial Refunded";
                            $langStatus = "partial_refund";
                            $dbPayStatus = 5; // For payment status as Partial Charged in oxnets db table
                            $oDB = oxDb::getDb(true);
                            $oDB->Execute("UPDATE oxnets SET partial_amount = '{$partialr}' WHERE oxorder_id = '{$oxoder_id}'");
                            $oDB->Execute("UPDATE oxnets SET charge_id = '{$chargeid}' WHERE oxorder_id = '{$oxoder_id}'");
                            $oDB->Execute("UPDATE oxorder SET oxpaid = '{$chargedate}' WHERE oxid = '{$oxoder_id}'");
                        } else {
                            $paymentStatus = "Refunded";
                            $langStatus = "refunded";
                            $dbPayStatus = 6; // For payment status as Refunded in oxnets db table
                        }
                    } else {
                        $paymentStatus = "Charged";
                        $langStatus = "charged";
                        $dbPayStatus = 4; // For payment status as Charged in oxnets db table
                    }
                } else if ($pending) {
                    $paymentStatus = "Refund Pending";
                    $langStatus = "refund_pending";
                } else {
                    $paymentStatus = 'Reserved';
                    $langStatus = "reserved";
                    $dbPayStatus = 2; // For payment status as Authorized in oxnets db table
                }
            } else {
                $paymentStatus = "Failed";
                $langStatus = "failed";
                $dbPayStatus = 0; // For payment status as Failed in oxnets db table
            }
            $oDb = oxDb::getDb();
            $oDb->execute("UPDATE oxnets SET payment_status = ? WHERE transaction_id = ? ", [
                $dbPayStatus,
                $this->getPaymentId($oxoder_id)
            ]);
            return array(
                'payStatus' => $paymentStatus,
                'langStatus' => $langStatus
            );
        }
    }

    /*
     * Function to capture nets transaction - calls Charge API
     * redirects to admin overview listing page
     */
    public function getOrderCharged()
    {
        $stoken = oxRegistry::getConfig()->getRequestParameter('stoken');
        $admin_sid = oxRegistry::getConfig()->getRequestParameter('force_admin_sid');
        $oxorder = oxRegistry::getConfig()->getRequestParameter('oxorderid');
        $orderno = oxRegistry::getConfig()->getRequestParameter('orderno');
        $data = $this->getOrderItems($oxorder);
        $payment_id = $this->getPaymentId($oxorder);

        // call charge api here
        $chargeUrl = $this->getChargePaymentUrl($payment_id);
        $ref = oxRegistry::getConfig()->getRequestParameter('reference');
        $chargeQty = oxRegistry::getConfig()->getRequestParameter('charge');

        if (isset($ref) && isset($chargeQty)) {
            $totalAmount = 0;
            foreach ($data['items'] as $key => $value) {
                if (in_array($ref, $value)) {
                    $value['quantity'] = $chargeQty;
                    $prodPrice = $value['oxbprice']; // product price incl. VAT in DB format
                    $tax = (int) $value['taxRate'] / 100; // Tax rate in DB format
                    $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
                    $unitPrice = round(round(($prodPrice * 100) / $taxFormat, 2) * 100);
                    $netAmount = round($chargeQty * $unitPrice);
                    $grossAmount = round($chargeQty * ($prodPrice * 100));
                    $value['netTotalAmount'] = $netAmount;
                    $value['grossTotalAmount'] = $grossAmount;
                    $value['taxAmount'] = $grossAmount - $netAmount;
                    unset($value['oxbprice']);
                    $itemList[] = $value;
                    $totalAmount += $grossAmount;
                }
            }
            $body = [
                'amount' => $totalAmount,
                'orderItems' => $itemList
            ];
        } else {
            $body = [
                'amount' => $data['totalAmt'],
                'orderItems' => $data['items']
            ];
        }
        nets_log::log($this->_nets_log, "Nets_Order_Overview" . json_encode($body));
        $api_return = $this->client->request('POST', $chargeUrl, [
            'headers' => $this->getHeaders(),
            'body' => json_encode($body)
        ]);
        $response = json_decode($api_return->getBody(), true);
        nets_log::log($this->_nets_log, "Nets_Order_Overview" . $response);
        $oDB = oxDb::getDb(true);
        $dt = date("Y-m-d H:i:s");
        $oDB->Execute("UPDATE oxorder SET oxpaid = '{$dt}'
		WHERE oxid = '{$oxorder}'");

        oxRegistry::getUtils()->redirect($this->getConfig()
            ->getSslShopUrl() . 'admin/index.php?cl=admin_order&force_admin_sid' . $admin_sid . '&stoken=' . $stoken);
    }

    /*
     * Function to capture nets transaction - calls Refund API
     * redirects to admin overview listing page
     */
    public function getOrderRefund()
    {
        $stoken = oxRegistry::getConfig()->getRequestParameter('stoken');
        $admin_sid = oxRegistry::getConfig()->getRequestParameter('force_admin_sid');
        $oxorder = oxRegistry::getConfig()->getRequestParameter('oxorderid');
        $orderno = oxRegistry::getConfig()->getRequestParameter('orderno');
        $data = $this->getOrderItems($oxorder);
        $charge_id = $this->getChargeId($oxorder);

        // call refund api here
        $refundUrl = $this->getRefundPaymentUrl($charge_id);
        $ref = oxRegistry::getConfig()->getRequestParameter('reference');
        $refundQty = oxRegistry::getConfig()->getRequestParameter('refund');

        if (isset($ref) && isset($refundQty)) {
            $totalAmount = 0;
            foreach ($data['items'] as $key => $value) {
                if (in_array($ref, $value)) {
                    $value['quantity'] = $refundQty;
                    $prodPrice = $value['oxbprice']; // product price incl. VAT in DB format
                    $tax = (int) $value['taxRate'] / 100; // Tax rate in DB format
                    $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
                    $unitPrice = round(round(($prodPrice * 100) / $taxFormat, 2) * 100);
                    $netAmount = round($refundQty * $unitPrice);
                    $grossAmount = round($refundQty * ($prodPrice * 100));
                    $value['netTotalAmount'] = $netAmount;
                    $value['grossTotalAmount'] = $grossAmount;
                    $value['taxAmount'] = $grossAmount - $netAmount;
                    unset($value['oxbprice']);
                    $itemList[] = $value;
                    $totalAmount += $grossAmount;
                }
            }
            $body = [
                'amount' => $totalAmount,
                'orderItems' => $itemList
            ];
        } else {
            $body = [
                'amount' => $data['totalAmt'],
                'orderItems' => $data['items']
            ];
        }
        nets_log::log($this->_nets_log, "Nets_Order_Overview getorder refund" . json_encode($body));
        $api_return = $this->client->request('POST', $refundUrl, [
            'headers' => $this->getHeaders(),
            'body' => json_encode($body)
        ]);
        $response = json_decode($api_return->getBody(), true);

        oxRegistry::getUtils()->redirect($this->getConfig()
            ->getSslShopUrl() . 'admin/index.php?cl=admin_order&force_admin_sid' . $admin_sid . '&stoken=' . $stoken);
    }

    /*
     * Function to capture nets transaction - calls Cancel API
     * redirects to admin overview listing page
     */
    public function getOrderCancel()
    {
        $stoken = oxRegistry::getConfig()->getRequestParameter('stoken');
        $admin_sid = oxRegistry::getConfig()->getRequestParameter('force_admin_sid');
        $oxorder = oxRegistry::getConfig()->getRequestParameter('oxorderid');
        $orderno = oxRegistry::getConfig()->getRequestParameter('orderno');
        $data = $this->getOrderItems($oxorder);
        $payment_id = $this->getPaymentId($oxorder);

        // call cancel api here
        $cancelUrl = $this->getVoidPaymentUrl($payment_id);
        $body = [
            'amount' => $data['totalAmt'],
            'orderItems' => $data['items']
        ];
        $api_return = $this->client->request('POST', $cancelUrl, [
            'headers' => $this->getHeaders(),
            'body' => json_encode($body)
        ]);
        $response = json_decode($api_return->getBody(), true);

        oxRegistry::getUtils()->redirect($this->getConfig()
            ->getSslShopUrl() . 'admin/index.php?cl=admin_order&force_admin_sid' . $admin_sid . '&stoken=' . $stoken);
    }

    /*
     * Function to get order items to pass capture, refund, cancel api
     * @param $oxorder oxid order id alphanumeric
     * @param $orderno order no numeric
     * @return array order items and amount
     */
    public function getOrderItems($oxorder, $blExcludeCanceled = true)
    {
        $sSelect = "
			SELECT `oxorderarticles`.* FROM `oxorderarticles`
			WHERE `oxorderarticles`.`oxorderid` = '" . $oxorder . "'" . ($blExcludeCanceled ? "
			AND `oxorderarticles`.`oxstorno` != 1 " : " ") . "
			ORDER BY `oxorderarticles`.`oxartid`, `oxorderarticles`.`oxselvariant`, `oxorderarticles`.`oxpersparam`
		";

        // order articles
        $oArticles = oxNew('oxlist');
        $oArticles->init('oxorderarticle');
        $oArticles->selectString($sSelect);
        $totalOrderAmt = 0;
        foreach ($oArticles as $listitem) {
            $items[] = [
                'reference' => $listitem->oxorderarticles__oxartnum->value,
                'name' => $listitem->oxorderarticles__oxtitle->value,
                'quantity' => $listitem->oxorderarticles__oxamount->rawValue,
                'unit' => 'pcs',
                'taxRate' => $this->prepareAmount($listitem->oxorderarticles__oxvat->rawValue),
                'unitPrice' => $this->prepareAmount($listitem->oxorderarticles__oxnprice->rawValue),
                'taxAmount' => $this->prepareAmount($listitem->oxorderarticles__oxvatprice->rawValue),
                'grossTotalAmount' => $this->prepareAmount($listitem->oxorderarticles__oxbrutprice->rawValue),
                'netTotalAmount' => $this->prepareAmount($listitem->oxorderarticles__oxnetprice->rawValue),
                'oxbprice' => $listitem->oxorderarticles__oxbprice->rawValue
            ];
            $totalOrderAmt += $this->prepareAmount($listitem->oxorderarticles__oxbrutprice->rawValue);
        }

        // echo "<pre>";
        // print_r($items);

        return array(
            "items" => $items,
            "totalAmt" => $totalOrderAmt
        );
    }

    /*
     * Function to get list of partial charge/refund and reserved items list
     * @param oxorder id
     * @return array of reserved, partial charged,partial refunded items
     */
    public function checkPartialItems($oxid)
    {
        $prodItems = $this->getOrderItems($oxid);
        $products = [];
        foreach ($prodItems['items'] as $items) {
            $products[$items['reference']] = array(
                'name' => $items['name'],
                'quantity' => $items['quantity'],
                'price' => $items['oxbprice']
            );
        }

        // print_r($products);

        $api_return = $this->client->request('GET', $this->getApiUrl() . $this->getPaymentId($oxid), [
            'headers' => $this->getHeaders()
        ]);
        $response = json_decode($api_return->getBody(), true);

        // echo "<pre>";
        // print_r($response);

        if (! empty($response['payment']['charges'])) {
            $qty = 0;
            $price = 0;
            $chargedItems = [];
            foreach ($response['payment']['charges'] as $key => $values) {

                // echo $key;
                // print_r($values);

                for ($i = 0; $i < count($values['orderItems']); $i ++) {
                    if (array_key_exists($values['orderItems'][$i]['reference'], $chargedItems)) {
                        $qty = $chargedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $price = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'price' => number_format((float) ((($values['orderItems'][$i]['unitPrice'] / 100) * ('1.' . str_pad(number_format((float) $values['orderItems'][$i]['taxRate'], 2, '.', ''), 5, '0', STR_PAD_LEFT)))), 2, '.', '')
                        );
                    } else {
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'price' => number_format((float) ((($values['orderItems'][$i]['unitPrice'] / 100) * ('1.' . str_pad(number_format((float) $values['orderItems'][$i]['taxRate'], 2, '.', ''), 5, '0', STR_PAD_LEFT)))), 2, '.', '')
                        );
                    }
                }
            }
        }

        if (! empty($response['payment']['refunds'])) {
            $qty = 0;
            $price = 0;
            $refundedItems = [];
            foreach ($response['payment']['refunds'] as $key => $values) {
                for ($i = 0; $i < count($values['orderItems']); $i ++) {
                    if (array_key_exists($values['orderItems'][$i]['reference'], $refundedItems)) {
                        $qty = $refundedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $price = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'price' => number_format((float) ($price / 100), 2, ',', '')
                        );
                    } else {
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'price' => number_format((float) ($values['orderItems'][$i]['grossTotalAmount'] / 100), 2, ',', '')
                        );
                    }

                    // echo "$i == <pre>";
                    // print_r($refundedItems);
                }
            }
        }

        // echo "<pre>";
        // print_r($refundedItems);

        // get list of partial charged items and check with quantity and send list for charge rest of items
        foreach ($products as $key => $prod) {
            if (array_key_exists($key, $chargedItems)) {
                $qty = $prod['quantity'] - $chargedItems[$key]['quantity'];
            } else {
                $qty = $prod['quantity'];
            }

            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                $qty = $chargedItems[$key]['quantity'] - $refundedItems[$key]['quantity'];
                if ($qty > 0)
                    $chargedItems[$key]['quantity'] = $qty;
            }

            if ($qty > 0) {
                $itemsList[] = array(
                    'name' => $prod['name'],
                    'reference' => $key,
                    'quantity' => $qty,
                    'price' => number_format((float) ($prod['price']), 2, '.', '')
                );
            }

            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                if ($chargedItems[$key]['quantity'] == $refundedItems[$key]['quantity']) {
                    unset($chargedItems[$key]);
                }
            }

            if ($chargedItems[$key]['quantity'] > $prod['quantity']) {
                $chargedItems[$key]['quantity'] = $prod['quantity'];
            }
        }

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

        // echo "<pre>";
        // print_r($lists);

        // pass reserved, charged, refunded items list to frontend
        return $lists;
    }

    /*
     * Fetch partial amount
     */
    public function getPartial($oxoder_id)
    {
        $oDB = oxDb::getDb(true);
        $sSQL_select = "SELECT partial_amount FROM oxnets WHERE oxorder_id = ? LIMIT 1";
        $partial_amount = $oDB->getOne($sSQL_select, [
            $oxoder_id
        ]);
        return $partial_amount;
    }

    public function debugMode()
    {
        $debug = $this->getConfig()->getConfigParam('nets_blDebug_log');
        return $debug;
    }

    public function getResponse($oxoder_id)
    {
        $api_return = $this->client->request('GET', $this->getApiUrl() . $this->getPaymentId($oxoder_id), [
            'headers' => $this->getHeaders()
        ]);
        $response = json_decode($api_return->getBody(), true);
        $result = json_encode($response, JSON_PRETTY_PRINT);
        return $result;
    }

    /*
     * Function to fetch headers to be passed in guzzle http request
     * @return headers array
     */
    private function getHeaders()
    {
        return [
            'Content-Type' => self::RESPONSE_TYPE,
            'Accept' => self::RESPONSE_TYPE,
            'Authorization' => $this->getSecretKey()
        ];
    }

    private function prepareAmount($amount = 0)
    {
        return (int) round($amount * 100);
    }

    /*
     * Function to fetch payment id from databse table oxnets
     * @param $oxorder_id
     * @return nets payment id
     */
    public function getPaymentId($oxoder_id)
    {
        $oDB = oxDb::getDb(true);
        $sSQL_select = "SELECT transaction_id FROM oxnets WHERE oxorder_id = ? LIMIT 1";
        $payment_id = $oDB->getOne($sSQL_select, [
            $oxoder_id
        ]);
        return $payment_id;
    }

    /*
     * Function to fetch payment method type from databse table oxorder
     * @param $oxorder_id
     * @return payment method
     */
    public function getPaymentMethod($oxoder_id)
    {
        $oDB = oxDb::getDb(true);
        $sSQL_select = "SELECT OXPAYMENTTYPE FROM oxorder WHERE oxid = ? LIMIT 1";
        $payMethod = $oDB->getOne($sSQL_select, [
            $oxoder_id
        ]);
        return $payMethod;
    }

    /*
     * Function to fetch charge id from databse table oxnets
     * @param $oxorder_id
     * @return nets charge id
     */
    private function getChargeId($oxoder_id)
    {
        // Get charge id from nets payments api
        $api_return = $this->client->request('GET', $this->getApiUrl() . $this->getPaymentId($oxoder_id), [
            'headers' => $this->getHeaders()
        ]);
        $response = json_decode($api_return->getBody(), true);
        return $response['payment']['charges'][0]['chargeId'];
    }

    /*
     * Function to fetch secret key to pass as authorization
     * @return secret key
     */
    public function getSecretKey()
    {
        if ($this->getConfig()->getConfigParam('nets_blMode') == 0) {
            return $this->getConfig()->getConfigParam('nets_secret_key_test');
        } else {
            return $this->getConfig()->getConfigParam('nets_secret_key_live');
        }
    }

    /*
     * Function to fetch payment api url
     *
     * @return payment api url
     */
    public function getApiUrl()
    {
        if ($this->getConfig()->getConfigParam('nets_blMode') == 0) {
            return self::ENDPOINT_TEST;
        } else {
            return self::ENDPOINT_LIVE;
        }
    }

    /*
     * Function to fetch charge api url
     * @param $paymentId
     * @return charge api url
     */
    public function getChargePaymentUrl(string $paymentId)
    {
        return ($this->getConfig()->getConfigParam('nets_blMode') == 1) ? self::ENDPOINT_LIVE . $paymentId . '/charges' : self::ENDPOINT_TEST . $paymentId . '/charges';
    }

    /*
     * Function to fetch cancel api url
     * @param $paymentId
     * @return cancel api url
     */
    public function getVoidPaymentUrl(string $paymentId)
    {
        return ($this->getConfig()->getConfigParam('nets_blMode') == 1) ? self::ENDPOINT_LIVE . $paymentId . '/cancels' : self::ENDPOINT_TEST . $paymentId . '/cancels';
    }

    /*
     * Function to fetch refund api url
     * @param $chargeId
     * @return refund api url
     */
    public function getRefundPaymentUrl($chargeId)
    {
        return ($this->getConfig()->getConfigParam('nets_blMode') == 1) ? self::ENDPOINT_LIVE_CHARGES . $chargeId . '/refunds' : self::ENDPOINT_TEST_CHARGES . $chargeId . '/refunds';
    }
}
