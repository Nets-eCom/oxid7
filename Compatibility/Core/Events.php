<?php

/**
 * This file is part of OXID NETS module.
 *
 */

namespace Es\NetsEasy\Compatibility\Core;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Application\Model\Payment;
use Es\NetsEasy\Application\Helper\Payment as NetsPayment;
use Es\NetsEasy\Application\Helper\DebugLog;

/**
 * Class defines what module does on Shop events.
 */
class Events
{
    /**
     * Function to execute action on activate event
     * @return void
     */
    public static function onActivate()
    {
        self::createTables();
        self::addPaymentMethods();
    }

    /**
     * Function to execute action on deactivate event
     * @return void
     */
    public static function onDeactivate()
    {

    }

    /**
     * Execute necessary module migrations on activate event
     * @return void
     */
    protected static function createTables()
    {
        self::addTable('nets_transactions', self::getCreateTableSql());
    }

    /**
     * Create a new table
     *
     * @param  string $sTableName
     * @param  string $sQuery
     * @return bool
     */
    protected static function addTable($sTableName, $sQuery)
    {
        $aTables = DatabaseProvider::getDb()->getAll("SHOW TABLES LIKE '{$sTableName}'");
        if (empty($aTables)) {
            DatabaseProvider::getDb()->Execute($sQuery);
            return true;
        }
        return false;
    }

    /**
     * Add necessary payment method on activate event
     * @return void
     * @throws DatabaseConnectionException
     */
    protected static function addPaymentMethods()
    {
        DebugLog::getInstance()->log("addPaymentMethods called");
        $db = DatabaseProvider::getDb();

        foreach (NetsPayment::getInstance()->getNetsPaymentTypes() as $sPaymentId => $aPaymentType) {
            //check if nets payment is completed
            $sPaymentOxid = $db->execute('SELECT OXID FROM oxpayments WHERE oxid = :paymentId', ['paymentId' => $sPaymentId]);
            if (empty($sPaymentOxid)) {
                DebugLog::getInstance()->log("payment method added for : " . json_encode($aPaymentType));
                //create payment
                $sDesc = $aPaymentType['descEN'];
                $sDescDE = $aPaymentType['descDE'];
                if (!empty($sDesc)) {
                    $oPayment = oxNew(Payment::class);
                    $oPayment->assign([
                        'OXID'         => $sPaymentId, //0
                        'OXACTIVE'     => 0, // 1
                        'OXDESC'       => $sDescDE, // 2
                        'OXADDSUM'     => 0, // 3
                        'OXADDSUMTYPE' => 'abs', // 4
                        'OXFROMBONI'   => 0, //5
                        'OXFROMAMOUNT' => 0, // 6
                        'OXTOAMOUNT'   => 1000000, // 7
                        'OXVALDESC'    => '', // 8
                        'OXCHECKED'    => 0, // 9
                        'OXDESC_1'     => $sDesc, // 10
                        'OXVALDESC_1'  => '', // 11
                        'OXDESC_2'     => '', // 12
                        'OXVALDESC_2'  => '', // 13
                        'OXDESC_3'     => '', // 14
                        'OXVALDESC_3'  => '',
                        'OXLONGDESC'   => '',
                        'OXLONGDESC_1' => '',
                        'OXLONGDESC_2' => '',
                        'OXLONGDESC_3' => '',
                        'OXSORT'       => '',
                    ]);
                    $oPayment->save();
                }
            }
        }
    }

    protected static function getCreateTableSql() {
        $sTableSql = "
                CREATE TABLE IF NOT EXISTS `nets_transactions` (
                    `oxid` int(10) unsigned NOT NULL auto_increment,
                    `req_data` text collate latin1_general_ci,
                    `ret_data` text collate latin1_general_ci,
                    `payment_method` varchar(255) collate latin1_general_ci default NULL,
                    `transaction_id` varchar(50)  default NULL,
                    `charge_id` varchar(50)  default NULL,
                    `product_ref` varchar(55) collate latin1_general_ci default NULL,
                    `charge_qty` int(11) default NULL,
                    `charge_left_qty` int(11) default NULL,
                    `oxordernr` int(11) default NULL,
                    `oxorder_id` char(32) default NULL,
                    `amount` varchar(255) collate latin1_general_ci default NULL,
                    `partial_amount` varchar(255) collate latin1_general_ci default NULL,
                    `updated` int(2) unsigned default '0',
                    `payment_status` int (2) default '2' Comment '0-Failed,1-Cancelled, 2-Authorized,3-Partial Charged,4-Charged,5-Partial Refunded,6-Refunded',
                    `hash` varchar(255) default NULL,
                    `created` datetime NOT NULL,
                    `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                    PRIMARY KEY  (`oxid`)
                ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
            ";
        return $sTableSql;
    }
}
