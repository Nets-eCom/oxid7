<?php

namespace NexiCheckout\Application\Helper;

use NexiCheckout\Core\Module;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ShopVersion;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class Api
{
    const API_LIVE = 'https://api.dibspayment.eu/';
    const API_TEST = 'https://test.api.dibspayment.eu/';

    const JS_ENDPOINT_LIVE = "https://checkout.dibspayment.eu/v1/checkout.js";
    const JS_ENDPOINT_TEST = "https://test.checkout.dibspayment.eu/v1/checkout.js";

    const REPORTING_API = 'https://ps17.sokoni.it/module/api/enquiry';

    const EMBEDDED = "EmbeddedCheckout";
    const HOSTED = "HostedPaymentPage";

    /**
     * @var Api
     */
    protected static ?Api $instance = null;

    private ModuleSettingServiceInterface $setting;

    public function __construct()
    {
        $this->setting = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingServiceInterface::class);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = oxNew(self::class);
        }

        return self::$instance;
    }

    /**
     * Function to fetch secret key to pass as authorization
     *
     * @return string
     */
    public function getSecretKey()
    {
        if ($this->setting->getString('nexi_checkout_blMode', Module::ID)->toString() !== "0") {
            return $this->setting->getString("nexi_checkout_secret_key_live", Module::ID);
        }

        return $this->setting->getString("nexi_checkout_secret_key_test", Module::ID);
    }

    /**
     * Returns checkout key
     *
     * @return string
     */
    public function getCheckoutKey()
    {
        if ($this->setting->getString('nexi_checkout_blMode', Module::ID)->toString() !== "0") {
            return $this->setting->getString('nexi_checkout_checkout_key_live', Module::ID);
        }

        return $this->setting->getString('nexi_checkout_checkout_key_test', Module::ID);
    }

    /**
     * Function to get payment api url based on environment i.e live or test
     *
     * @return string
     */
    public function getApiUrl()
    {
        if ($this->setting->getString('nexi_checkout_blMode', Module::ID)->toString() !== "0") {
            return self::API_LIVE;
        }

        return self::API_TEST;
    }

    /**
     * Returns checkout javascipt url
     *
     * @return string
     */
    public function getCheckoutJs()
    {
        if ($this->setting->getString('nexi_checkout_blMode', Module::ID)->toString() !== "0") {
            return self::JS_ENDPOINT_LIVE;
        }

        return self::JS_ENDPOINT_TEST;
    }

    /**
     * Returns integration type
     *
     * @return string
     */
    public function getIntegrationType()
    {
        if ($this->setting->getString('nexi_checkout_checkout_mode', Module::ID)->toString() !== 'hosted') {
            return self::EMBEDDED;
        }

        return self::HOSTED;
    }

    /**
     * Returns if embedded mode is configured
     *
     * @return bool
     */
    public function isEmbeddedMode()
    {
        return $this->getIntegrationType() === self::EMBEDDED;
    }

    /**
     * Returns if hosted mode is configured
     *
     * @return bool
     */
    public function isHostedMode()
    {
        return $this->getIntegrationType() === self::HOSTED;
    }

    /**
     * Returns reporting api url
     *
     * @return string
     */
    public function getReportingApiUrl()
    {
        return self::REPORTING_API;
    }

    /**
     * Formats price for API
     *
     * @param  float $dPrice
     * @return float
     */
    public function formatPrice($dPrice)
    {
        return round(round($dPrice, 2) * 100);
    }

    /**
     * Function to compile layout style file url for the embedded checkout type
     *
     * @return string
     */
    public function getLayout()
    {
        return Registry::getConfig()->getActiveView()->getViewConfig()->getModuleUrl(Module::ID, "src/js/").'layout.js';
    }

    /**
     * Returns the oxid shop identifier
     *
     * @return string
     */
    public function getShopIdentifier()
    {
        return Registry::getConfig()->getActiveShop()->oxshops__oxedition->value."_".ShopVersion::getVersion();
    }
}
