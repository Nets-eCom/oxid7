<?php
/**
 * Nets Order Controller class
 */
require_once getShopBasePath() . 'modules/nets/api/nets_api.php';
require_once getShopBasePath() . 'modules/nets/api/netsPaymentTypes.php';

/**
 * Class controls nets payment process
 * It also shows the nets embedded checkout window
 */
class netsOrder extends netsOrder_parent
{

    const EMBEDDED = "EmbeddedCheckout";

    const HOSTED = "HostedPaymentPage";

    const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';

    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';

    const JS_ENDPOINT_TEST = "https://test.checkout.dibspayment.eu/v1/checkout.js";

    const JS_ENDPOINT_LIVE = "https://checkout.dibspayment.eu/v1/checkout.js";

    const RESPONSE_TYPE = "application/json";

    const MODULE_NAME = "nets_easy";

    protected $_nets_log = false;

    protected $client;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_nets_log = $this->getConfig()->getConfigParam('nets_blDebug_log');
        nets_log::log($this->_nets_log, "netsOrder, constructor");
    }

    /**
     * Important function that returns next step in payment process, calls parent function
     *
     * @return string iSuccess
     */
    protected function _getNextStep($iSuccess)
    {
        nets_log::log($this->_nets_log, "netsOrder, _getNextStep");
        $nextStep = parent::_getNextStep($iSuccess);
        return $nextStep;
    }

    /**
     * Function that executes the payment
     */
    public function execute()
    {
        nets_log::log($this->_nets_log, "netsOrder, execute");
        $oBasket = $this->getSession()->getBasket();
        $oUser = $this->getUser();
        if (! $oUser) {
            return 'user';
        }
        if ($oBasket->getProductsCount()) {
            try {
                if ($this->is_embedded()) {
                    $sess_id = $this->getSession()->getVariable('sess_challenge');
                    $oDB = oxDb::getDb(true);
                    $sSQL_select = "SELECT oxorder_id FROM oxnets WHERE oxorder_id = ? LIMIT 1";
                    $order_id = $oDB->getOne($sSQL_select, [
                        $sess_id
                    ]);
                    if (! empty($order_id)) {
                        $orderId = \OxidEsales\Eshop\Core\UtilsObject::getInstance()->generateUID();
                        // $this->save();
                        \OxidEsales\Eshop\Core\Registry::getSession()->setVariable("sess_challenge", $orderId);
                    }

                    // finalizing ordering process (validating, storing order into DB, executing payment, setting status ...)
                    $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
                    $iSuccess = $oOrder->finalizeOrder($oBasket, $oUser);
                    $orderNr = $oOrder->oxorder__oxordernr->value;
                    $paymentId = $this->getSession()->getVariable('payment_id');
                    $this->getSession()->setVariable('orderNr', $orderNr);
                    nets_log::log($this->_nets_log, " refupdate netsOrder, order nr", $oOrder->oxorder__oxordernr->value);
                    $oDb = oxDb::getDb();

                    $oDb->execute("UPDATE oxnets SET oxordernr = ?,  hash = ?, oxorder_id = ? WHERE transaction_id = ? ", [
                        $orderNr,
                        $this->getSession()
                            ->getVariable('sess_challenge'),
                        $this->getSession()
                            ->getVariable('sess_challenge'),
                        $paymentId
                    ]);

                    $api_return = $this->getCurlResponse($this->getApiUrl() . $paymentId, "GET");
                    $response = json_decode($api_return, true);
                    nets_log::log($this->_nets_log, " payment api status netsOrder, response", $response);
                    $refUpdate = [
                        'reference' => $orderNr,
                        'checkoutUrl' => $response['payment']['checkout']['url']
                    ];
                    nets_log::log($this->_nets_log, " refupdate netsOrder, order nr", $oOrder->oxorder__oxordernr->value);
                    nets_log::log($this->_nets_log, " payment api status netsOrder, response checkout url", $response['payment']['checkout']['url']);
                    nets_log::log($this->_nets_log, " refupdate netsOrder, response", $refUpdate);
                    $this->getCurlResponse($this->getUpdateRefUrl($paymentId), 'PUT', json_encode($refUpdate));
                    oxRegistry::getUtils()->redirect($this->getConfig()
                        ->getSslShopUrl() . 'index.php?cl=thankyou');
                } else {
                    $this->getPaymentApiResponse();
                }
            } catch (\OxidEsales\Eshop\Core\Exception\OutOfStockException $oEx) {
                $oEx->setDestination('basket');
                Registry::getUtilsView()->addErrorToDisplay($oEx, false, true, 'basket');
            } catch (\OxidEsales\Eshop\Core\Exception\NoArticleException $oEx) {
                Registry::getUtilsView()->addErrorToDisplay($oEx);
            } catch (\OxidEsales\Eshop\Core\Exception\ArticleInputException $oEx) {
                Registry::getUtilsView()->addErrorToDisplay($oEx);
            }
        }
    }

    /* Function to set headers for http client request */
    private function getHeaders()
    {
        return [
            "Content-Type: " . self::RESPONSE_TYPE,
            "Accept: " . self::RESPONSE_TYPE,
            "Authorization: " . $this->getSecretKey()
        ];
    }

    /* Function to get error message displayed on template file */
    public function getErrorMsg()
    {
        return $this->getSession()->getVariable('nets_err_msg');
    }

    /* Function to get current order from basket */
    protected function getOrderId()
    {
        $mySession = $this->getSession();
        $oBasket = $mySession->getBasket();
        return $oBasket->getOrderId();
    }

    /* Function to get basket amount */
    protected function getBasketAmount()
    {
        $mySession = $this->getSession();
        $oBasket = $mySession->getBasket();
        return intval(strval(($oBasket->getPrice()->getBruttoPrice() * 100)));
    }

    /**
     * Function to update order no in oxnets table
     *
     * @param
     *            $hash
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     * @return $oOrderrnr
     */
    protected function update_ordernr($hash)
    {
        $oID = $this->getOrderId();
        $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
        $oOrder->load($oID);
        $oOrdernr = $oOrder->oxorder__oxordernr->value;
        nets_log::log($this->_nets_log, "netsOrder, update_ordernr: " . $oOrdernr . " for hash " . $hash);

        if (is_numeric($oOrdernr) && ! empty($hash)) {
            $oDb = oxDb::getDb();
            $oDb->execute("UPDATE oxnets SET oxordernr = ? WHERE hash = ?", [
                $oOrdernr,
                $hash
            ]);
            nets_log::log($this->_nets_log, "netsOrder, in if update_ordernr: " . $oOrdernr . " for hash " . $hash);
        }
        return $oOrdernr;
    }

    /**
     * Function to create transaction and call nets payment api
     *
     * @param
     *            $oOrder
     *
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function createNetsTransaction($oOrder)
    {
        $this->_nets_log = $this->getConfig()->getConfigParam('nets_blDebug_log');
        $this->getSession()->deleteVariable('nets_err_msg');
        nets_log::log($this->_nets_log, "netsOrder createNetsTransaction");
        $items = [];
        $oDB = oxDb::GetDB();
        $integrationType = self::HOSTED;

        $sUserID = $this->getSession()->getVariable("usr");
        $oUser = oxNew("oxuser", "core");
        $oUser->Load($sUserID);

        $mySession = $this->getSession();
        $oBasket = $mySession->getBasket();

        $oID = $this->update_ordernr($this->getSession()
            ->getVariable('sess_challenge'));
        nets_log::log($this->_nets_log, 'oID: ', $oOrder->oxorder__oxordernr->value);

        // if oID is empty, use session value
        if (empty($oID)) {
            $sGetChallenge = $this->getSession()->getVariable('sess_challenge');
            $oID = $sGetChallenge;
            nets_log::log($this->_nets_log, "netsOrder, get oID from Session: ", $oID);
        }

        nets_log::log($this->_nets_log, 'oID: ', $oID);
        $modus = $this->getConfig()->getConfigParam('nets_blMode');

        $oLang = oxRegistry::getLang();
        $iLang = $oLang->getTplLanguage();
        if (! isset($iLang)) {
            $iLang = $oLang->getBaseLanguage();
            if (! isset($iLang)) {
                $iLang = 0;
            }
        }
        try {
            $sTranslation = $oLang->translateString($oUser->oxuser__oxsal->value, $iLang, isAdmin());
        } catch (oxLanguageException $oEx) {
            // is thrown in debug mode and has to be caught here, as smarty hangs otherwise!
        }

        if ($modus == 0) {
            $apiUrl = self::ENDPOINT_TEST;
        } else {
            $apiUrl = self::ENDPOINT_LIVE;
        }

        $daten['checkout_type'] = $this->getConfig()->getConfigParam('nets_checkout_mode');

        $lang_abbr = $oLang->getLanguageAbbr($iLang);
        if (isset($lang_abbr) && $lang_abbr === 'en') {
            $daten['language'] = 'en_US';
        } else if (isset($lang_abbr) && $lang_abbr === 'de') {
            $daten['language'] = 'de_DE';
        }

        $daten['title'] = $sTranslation;
        $daten['name_affix'] = $oUser->oxuser__oxaddinfo->value;
        $sCountryId = $oUser->oxuser__oxcountryid->value;
        $daten['telephone'] = $oUser->oxuser__oxfon->value;
        $daten['dob'] = $oUser->oxuser__oxbirthdate->value;
        $daten['email'] = $oUser->oxuser__oxusername->value;
        $daten['amount'] = intval(strval($oBasket->getPrice()->getBruttoPrice() * 100));
        $daten['currency'] = $oBasket->getBasketCurrency()->name;

        $basketcontents = $oBasket->getContents();
        $wrapCost = $greetCardAmt = $shippingCost = $payCost = 0;
        $shippingCost = $oBasket->getDeliveryCost();

        if ($shippingCost) {
            $shipCostAmt = $oBasket->isCalculationModeNetto() ? $shippingCost->getNettoPrice() : $shippingCost->getBruttoPrice();
        }

        if ($shipCostAmt > 0) {
            $shipCostAmt = round(round($shipCostAmt, 2) * 100);
            $items[] = [
                'reference' => 'shipping',
                'name' => 'shipping',
                'quantity' => 1,
                'unit' => 'units',
                'unitPrice' => $shipCostAmt,
                'taxAmount' => 0,
                'grossTotalAmount' => $shipCostAmt,
                'netTotalAmount' => $shipCostAmt
            ];
        }

        $payCost = $oBasket->getPaymentCost();
        if ($payCost) {
            $payCostAmt = $oBasket->isCalculationModeNetto() ? $payCost->getNettoPrice() : $payCost->getBruttoPrice();
        }

        if ($payCostAmt > 0) {
            $payCostAmt = round(round($payCostAmt, 2) * 100);
            $items[] = [
                'reference' => 'payment costs',
                'name' => 'payment costs',
                'quantity' => 1,
                'unit' => 'units',
                'unitPrice' => $payCostAmt,
                'taxAmount' => 0,
                'grossTotalAmount' => $payCostAmt,
                'netTotalAmount' => $payCostAmt
            ];
        }

        $discAmount = $this->getDiscountSum($oBasket);
        if ($discAmount > 0) {
            $items[] = [
                'reference' => 'discount',
                'name' => 'discount',
                'quantity' => 1,
                'unit' => 'units',
                'unitPrice' => - $discAmount,
                'taxAmount' => 0,
                'grossTotalAmount' => - $discAmount,
                'netTotalAmount' => - $discAmount
            ];
        }

        /* gift wrap and greeting card amount to be added in total amount */
        $wrappingCostAmt = $oBasket->getCosts('oxwrapping');
        if ($wrappingCostAmt) {
            $wrapCost = $oBasket->isCalculationModeNetto() ? $wrappingCostAmt->getNettoPrice() : $wrappingCostAmt->getBruttoPrice();
            $wrapCost = round(round($wrapCost, 2) * 100);
        }

        $greetingCardAmt = $oBasket->getCosts('oxgiftcard');
        if ($greetingCardAmt) {
            $greetCardAmt = $oBasket->isCalculationModeNetto() ? $greetingCardAmt->getNettoPrice() : $greetingCardAmt->getBruttoPrice();
            $greetCardAmt = round(round($greetCardAmt, 2) * 100);
        }

        if ($wrapCost > 0) {
            $items[] = [
                'reference' => 'Gift Wrapping',
                'name' => 'Gift Wrapping',
                'quantity' => 1,
                'unit' => 'units',
                'unitPrice' => $wrapCost,
                'taxAmount' => 0,
                'grossTotalAmount' => $wrapCost,
                'netTotalAmount' => $wrapCost
            ];
        }

        if ($greetCardAmt > 0) {
            $items[] = [
                'reference' => 'Greeting Card',
                'name' => 'Greeting Card',
                'quantity' => 1,
                'unit' => 'units',
                'unitPrice' => $greetCardAmt,
                'taxAmount' => 0,
                'grossTotalAmount' => $greetCardAmt,
                'netTotalAmount' => $greetCardAmt
            ];
        }

        $sumAmt = 0;
        foreach ($basketcontents as $item) {
            $quantity = $item->getAmount();
            $prodPrice = $item->getArticle()
                ->getPrice(1)
                ->getBruttoPrice(); // product price incl. VAT in DB format
            $tax = $item->getPrice()->getVat(); // Tax rate in DB format
            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($prodPrice * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($prodPrice * 100));

            $items[] = [
                'reference' => $item->getArticle()->oxarticles__oxartnum->value,
                'name' => $item->getArticle()->oxarticles__oxtitle->value,
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $item->getPrice()->getVat() * 100,
                'taxAmount' => $grossAmount - $netAmount,
                'grossTotalAmount' => $grossAmount,
                'netTotalAmount' => $netAmount
            ];

            $sumAmt += $grossAmount;
        }

        $sumAmt = $sumAmt + $wrapCost + $greetCardAmt + $shipCostAmt + $payCostAmt;
        // print_r($items);

        $oDelAd = $oOrder->getDelAddressInfo();
        if ($oDelAd) {
            $delivery_address = new stdClass();
            $delivery_address->firstname = $oDelAd->oxaddress__oxfname->value;
            $delivery_address->lastname = $oDelAd->oxaddress__oxlname->value;
            $delivery_address->street = $oDelAd->oxaddress__oxstreet->value;
            $delivery_address->housenumber = $oDelAd->oxaddress__oxstreetnr->value;
            $delivery_address->zip = $oDelAd->oxaddress__oxzip->value;
            $delivery_address->city = $oDelAd->oxaddress__oxcity->value;
            $sDelCountry = $oDelAd->oxaddress__oxcountryid->value;
            $delivery_address->country = $oDB->getOne("SELECT oxisoalpha3 FROM oxcountry WHERE oxid = ?", [
                $sDelCountry
            ]);
            $delivery_address->company = $oDelAd->oxaddress__oxcompany->value;
            $daten['delivery_address'] = $delivery_address;
        } else {
            $delivery_address = new stdClass();
            $delivery_address->firstname = $oUser->oxuser__oxfname->value;
            $delivery_address->lastname = $oUser->oxuser__oxlname->value;
            $delivery_address->street = $oUser->oxuser__oxstreet->value;
            $delivery_address->housenumber = $oUser->oxuser__oxstreetnr->value;
            $delivery_address->zip = $oUser->oxuser__oxzip->value;
            $delivery_address->city = $oUser->oxuser__oxcity->value;
            $delivery_address->country = $oDB->getOne("SELECT oxisoalpha3 FROM oxcountry WHERE oxid = ?", [
                $sCountryId
            ]);
            $delivery_address->company = $oUser->oxuser__oxcompany->value;
            $daten['delivery_address'] = $delivery_address;
        }

        // create order to be passed to nets api
        $data = [
            'order' => [
                'items' => $items,
                'amount' => $sumAmt,
                'currency' => $oBasket->getBasketCurrency()->name,
                'reference' => $oID
            ]
        ];

        if ($this->getConfig()->getConfigParam('nets_checkout_mode') == 'embedded') {
            $integrationType = self::EMBEDDED;
        }

        $data['checkout']['integrationType'] = $integrationType;

        if ($this->getConfig()->getConfigParam('nets_checkout_mode') == 'embedded') {
            $data['checkout']['url'] = urldecode(oxRegistry::getConfig()->getShopUrl() . 'index.php?cl=thankyou');
        } else {
            $data['checkout']['returnUrl'] = urldecode(oxRegistry::getConfig()->getShopUrl() . 'index.php?cl=thankyou&paymentid=' . $paymentId);
            $data['checkout']['cancelUrl'] = urldecode(oxRegistry::getConfig()->getShopUrl() . 'index.php?cl=order');
        }

        // if autocapture is enabled in nets module settings, pass it to nets api
        if ($this->getConfig()->getConfigParam('nets_autocapture')) {
            $data['checkout']['charge'] = true;
        }
        $data['checkout']['termsUrl'] = $this->getConfig()->getConfigParam('nets_terms_url');
        $data['checkout']['merchantTermsUrl'] = $this->getConfig()->getConfigParam('nets_merchant_terms_url');
        $data['checkout']['merchantHandlesConsumerData'] = true;
        $data['checkout']['consumer'] = [
            'email' => $daten['email'],
            'shippingAddress' => [
                'addressLine1' => $delivery_address->housenumber,
                'addressLine2' => $delivery_address->street,
                'postalCode' => $delivery_address->zip,
                'city' => $delivery_address->city,
                'country' => $delivery_address->country
            ]
        ];

        if (empty($delivery_address->company)) {
            $data['checkout']['consumer']['privatePerson'] = [
                'firstName' => $delivery_address->firstname,
                'lastName' => $delivery_address->lastname
            ];
        } else {
            $data['checkout']['consumer']['company'] = [
                'name' => $delivery_address->company,
                'contact' => [
                    'firstName' => $delivery_address->firstname,
                    'lastName' => $delivery_address->lastname
                ]
            ];
        }

        try {
            $params['headers']['commercePlatformTag'] = "Oxid6";
            nets_log::log($this->_nets_log, "netsOrder, api request data here 2 : ", json_encode(utf8_ensure($params)));
            $api_return = $this->getCurlResponse($apiUrl, 'POST', json_encode($data));
            $response = json_decode($api_return, true);

            nets_log::log($this->_nets_log, "netsOrder, api return data create trans: ", json_decode($api_return, true));
            // create entry in oxnets table for transaction
            nets_table::createTransactionEntry(json_encode(utf8_ensure($params)), $api_return, $this->getOrderId(), $response['paymentId'], $oID, intval(strval($oBasket->getPrice()->getBruttoPrice() * 100)));

            // Set language for hosted payment page
            $language = oxRegistry::getLang()->getLanguageAbbr();
            if ($language == 'en') {
                $lang = 'en-GB';
            }
            if ($language == 'de') {
                $lang = 'de-DE';
            }
            if ($language == 'dk') {
                $lang = 'da-DK';
            }
            if ($language == 'se') {
                $lang = 'sv-SE';
            }
            if ($language == 'no') {
                $lang = 'nb-NO';
            }
            if ($language == 'fi') {
                $lang = 'fi-FI';
            }
            if ($language == 'pl') {
                $lang = 'pl-PL';
            }
            if ($language == 'nl') {
                $lang = 'nl-NL';
            }
            if ($language == 'fr') {
                $lang = 'fr-FR';
            }
            if ($language == 'es') {
                $lang = 'es-ES';
            }

            if ($integrationType == self::HOSTED) {
                oxRegistry::getUtils()->redirect($response["hostedPaymentPageUrl"] . "&language=$lang");
            } else {
                $this->getSession()->setVariable('payment_id', $response['paymentId']);
                return $response['paymentId'];
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            nets_log::log($this->_nets_log, "netsOrder, api exception : ", $e->getMessage());

            nets_log::log($this->_nets_log, "netsOrder, $error_message");
            if (empty($error_message)) {
                $error_message = 'Payment Api Parameter issue';
            }
            $this->getSession()->setVariable('nets_err_msg', $error_message);
            oxRegistry::getUtils()->redirect($this->getConfig()
                ->getSslShopUrl() . 'index.php?cl=order');
        }
    }

    /*
     * Function to get all type of discounts altogether and pass it to nets api
     */
    public function getDiscountSum($basket)
    {
        $discount = 0.0;
        $totalDiscount = $basket->getTotalDiscount();

        if ($totalDiscount) {
            $discount += $totalDiscount->getBruttoPrice();
        }

        // if payment costs are negative, adding them to discount
        if (($costs = $basket->getPaymentCosts()) < 0) {
            $discount += ($costs * - 1);
        }

        // vouchers, coupons
        $vouchers = (array) $basket->getVouchers();
        foreach ($vouchers as $voucher) {
            $discount += round($voucher->dVoucherdiscount, 2);
        }

        // final discount amount
        return round(round($discount, 2) * 100);
    }

    /*
     * Function to check if it embedded checkout
     */
    public function is_embedded()
    {
        $mode = $this->getConfig()->getConfigParam('nets_checkout_mode');
        $oDB = oxDb::getDb(true);
        $sSQL_select = "SELECT OXACTIVE FROM oxpayments WHERE oxid = ? LIMIT 1";
        $payMethod = $oDB->getOne($sSQL_select, [
            self::MODULE_NAME
        ]);
        if ($mode == "embedded" && $payMethod == 1) {
            return true;
        }
        return false;
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
     * Function to fetch checkout key to pass in checkout js options based on environment live or test
     * @return checkout key
     */
    public function getCheckoutKey()
    {
        if ($this->getConfig()->getConfigParam('nets_blMode') == 0) {
            return $this->getConfig()->getConfigParam('nets_checkout_key_test');
        } else {
            return $this->getConfig()->getConfigParam('nets_checkout_key_live');
        }
    }

    /*
     * Function to get payment api url based on environment i.e live or test
     * return payment api url
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
     * Function to get update reference api url based on environment i.e live or test
     * return update reference api url
     */
    public function getUpdateRefUrl($paymentId)
    {
        if ($this->getConfig()->getConfigParam('nets_blMode') == 0) {
            return self::ENDPOINT_TEST . $paymentId . '/referenceinformation';
        } else {
            return self::ENDPOINT_LIVE . $paymentId . '/referenceinformation';
        }
    }

    /*
     * Function to get checkout js url based on environment i.e live or test
     * return checkout js url
     */
    public function getCheckoutJs()
    {
        if ($this->getConfig()->getConfigParam('nets_blMode') == 0) {
            return self::JS_ENDPOINT_TEST;
        } else {
            return self::JS_ENDPOINT_LIVE;
        }
    }

    /*
     * Function to get payment api response and pass it to template
     * @return payment id
     */
    public function getPaymentApiResponse()
    {
        // additional user check
        $oUser = $this->getUser();
        if (! $oUser) {
            return 'user';
        }

        $oBasket = $this->getSession()->getBasket();
        if ($oBasket->getProductsCount()) {
            $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);

            // finalizing ordering process (validating, storing order into DB, executing payment, setting status ...)
            $iSuccess = $oOrder->finalizeOrder($oBasket, $oUser);

            // performing special actions after user finishes order (assignment to special user groups)
            $oUser->onOrderExecute($oBasket, $iSuccess);

            $response = $this->createNetsTransaction($oOrder);
            return $response;
        }
    }

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
     * Function to compile layout style file url for the embedded checkout type
     * @return layout style
     */
    public function getLayout()
    {
        return oxRegistry::getConfig()->getActiveView()
            ->getViewConfig()
            ->getModuleUrl("nets", "out/src/js/") . $this->getConfig()->getConfigParam('nets_layout_mode') . '.js';
    }

    /* curl request to execute api calls */
    public function getCurlResponse($url, $method = "POST", $bodyParams = NULL)
    {
        $result = '';

        // initiating curl request to call api's
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->getHeaders());
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $bodyParams);
        }

        $result = curl_exec($oCurl);

        $info = curl_getinfo($oCurl);

        switch ($info['http_code']) {
            case 401:
                $error_message = 'NETS Easy authorization filed. Check your secret/checkout keys';
                break;
            case 400:
                $error_message = 'NETS Easy Bad request: Please check request params/headers ';
                break;
            case 500:
                $error_message = 'Unexpected error';
                break;
        }
        if (! empty($error_message)) {
            nets_log::log($this->_nets_log, "netsOrder Curl request error, $error_message");
        }
        curl_close($oCurl);

        return $result;
    }
}
