<?php

namespace NexiCheckout\Application\Helper;

use OxidEsales\Eshop\Core\Registry;

class Language
{
    /**
     * @var string[]
     */
    protected $localeMap = [
        "en" => "en-GB",
        "de" => "de-DE",
        "dk" => "da-DK",
        "se" => "sv-SE",
        "no" => "nb-NO",
        "fi" => "fi-FI",
        "pl" => "pl-PL",
        "nl" => "nl-NL",
        "fr" => "fr-FR",
        "es" => "es-ES",
    ];

    /**
     * @var Language
     */
    protected static $oInstance = null;

    /**
     * Create singleton instance of current helper class
     *
     * @return Language
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Returns locale code for javascript widget
     *
     * @return string
     */
    public function getLocaleCode()
    {
        $sAbbreviation = Registry::getLang()->getLanguageAbbr();
        if (isset($this->localeMap[$sAbbreviation])) {
            return $this->localeMap[$sAbbreviation];
        }
        return "en-GB"; // fallback value
    }
}
