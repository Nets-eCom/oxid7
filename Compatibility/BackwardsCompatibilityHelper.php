<?php

namespace Es\NetsEasy\Compatibility;

use OxidEsales\Eshop\Core\ShopVersion;

class BackwardsCompatibilityHelper
{
    /**
     * Determines the version where oxid changed its query builder classes
     *
     * @var string
     */
    const NEW_QUERY_BUILDER_VERSION = '6.2';

    /**
     * @return bool
     */
    protected static function isOldQueryBuilderUsed()
    {
        if (version_compare(ShopVersion::getVersion(), self::NEW_QUERY_BUILDER_VERSION, '<')) {
            return true;
        }
        return false;
    }

    /**
     * Returns the correct query builder object for the used oxid version
     *
     * @return mixed
     */
    public static function getQueryBuilder()
    {
        if (self::isOldQueryBuilderUsed() === true) {
            return \OxidEsales\EshopCommunity\Internal\Application\ContainerFactory::getInstance()
                ->getContainer()
                ->get(\OxidEsales\EshopCommunity\Internal\Common\Database\QueryBuilderFactoryInterface::class)
                ->create();
        }
        return \OxidEsales\EshopCommunity\Internal\Container\ContainerFactory::getInstance()
            ->getContainer()
            ->get(\OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface::class)
            ->create();
    }

    /**
     * The old query builder doesn't support fetchOne
     * This helper method bridges that problem
     *
     * @param $result
     * @param $fetchField
     * @return mixed|null
     */
    public static function fetchOne($result, $fetchField)
    {
        if (self::isOldQueryBuilderUsed() === true) {
            if (!empty($result->fetch()[$fetchField])) {
                return $result->fetch()[$fetchField];
            }
            return null;
        }
        return $result->fetchOne();
    }
}
