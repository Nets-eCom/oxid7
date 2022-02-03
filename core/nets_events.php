<?php
require_once getShopBasePath() . 'modules/nets/api/nets_api.php';
require_once getShopBasePath() . 'modules/nets/api/netsPaymentTypes.php';

class nets_events extends oxUBase
{

    static $nets_log = true;

    static $nets_table_names = Array(
        'oxnets'
    );

    static $nets_oxnets_coulmn_names = "
		'oxnets_id',
		'req_data',
		'ret_data',
		'payment_method',
		'transaction_id',
		'charge_id',
        'product_ref',
        'charge_qty',
        'charge_left_qty',
		'oxordernr',
		'oxorder_id',
		'partial_amount',
		'amount',
		'updated',
		'payment_status',
		'hash',
		'created',
		'timestamp'
	";

    static $nets_payment_types_active = array();

    static function onActivate()
    {
        nets_log::log(self::$nets_log, "nets_events::onActivate()");
        $payment_types = netsPaymentTypes::$nets_payment_types;
        foreach ($payment_types as $payment_type) {
            self::checkPayment($payment_type['payment_id']);
            self::activatePayment($payment_type['payment_id'], 1);
        }
        self::checkTableStructure();
    }

    static function onDeactivate()
    {
        nets_log::log(self::$nets_log, "nets_events::onDeactivate()");
        $payment_types = netsPaymentTypes::$nets_payment_types;
        foreach ($payment_types as $payment_type) {
            self::activatePayment($payment_type['payment_id'], 0);
        }
    }

    private static function checkPayment($payment_id)
    {
        try {
            $oDB = oxDb::getDb(true);
            $payment_id_exists = $oDB->getOne("SELECT oxid FROM oxpayments WHERE oxid = ?", [
                $payment_id
            ]);
            if (! $payment_id_exists) {
                return self::createPayment($payment_id);
            }
        } catch (Exception $e) {
            nets_log::log(self::$nets_log, "nets_events, Exception:", $e->getMessage());
            nets_log::log(self::$nets_log, "nets_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function activatePayment($payment_id, $active = 1)
    {
        try {
            $oDB = oxDb::getDb(true);
            $oDB->execute("UPDATE oxpayments SET oxactive = ? WHERE oxid = ?", [
                $active,
                $payment_id
            ]);
        } catch (Exception $e) {
            nets_log::log(self::$nets_log, "nets_events, Exception:", $e->getMessage());
            nets_log::log(self::$nets_log, "nets_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function createPayment($payment_id)
    {
        try {
            $desc = netsPaymentTypes::getNetsPaymentDesc($payment_id);
            if (isset($desc) && $desc) {
                $oDB = oxDb::getDb(true);
                $sSql = "
					INSERT INTO oxpayments (
						`OXID`, `OXACTIVE`, `OXDESC`, `OXADDSUM`, `OXADDSUMTYPE`, `OXFROMBONI`, `OXFROMAMOUNT`, `OXTOAMOUNT`,
						`OXVALDESC`, `OXCHECKED`, `OXDESC_1`, `OXVALDESC_1`, `OXDESC_2`, `OXVALDESC_2`,
						`OXDESC_3`, `OXVALDESC_3`, `OXLONGDESC`, `OXLONGDESC_1`, `OXLONGDESC_2`, `OXLONGDESC_3`, `OXSORT`
					) VALUES (
						?, 1, ?, 0, 'abs', 0, 0, 1000000, '', 0, ?, '', '', '', '', '', '', '', '', '', 0
					)
				";
                $oDB->execute($sSql, [
                    $payment_id,
                    $desc,
                    $desc
                ]);
                return true;
            } else {
                nets_log::log(self::$nets_log, "nets_events, createPayment, desc missing");
                return false;
            }
        } catch (Exception $e) {
            nets_log::log(self::$nets_log, "nets_events, Exception:", $e->getMessage());
            nets_log::log(self::$nets_log, "nets_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function checkTableStructure()
    {
        try {
            $oDB = oxDb::getDb(true);
            foreach (self::$nets_table_names as $table_name) {
                $table_exists = $oDB->getOne("SHOW TABLES LIKE '" . $table_name . "'");
                if (! isset($table_exists) || ! $table_exists) {
                    self::createTableStructure($table_name);
                } else {
                    switch ($table_name) {
                        case 'oxnets':
                            // check columns of table oxnets
                            $sSql_columns = 'SHOW COLUMNS FROM oxnets WHERE Field IN (' . self::$nets_oxnets_coulmn_names . ');';
                            nets_log::log(self::$nets_log, "nets_events, checkTableStructure, columns do not match for COUNT " . count($oDB->getAll($sSql_columns)));
                            $columns_match = (count($oDB->getAll($sSql_columns)) == 18) ? true : false;
                            nets_log::log(self::$nets_log, "nets_events, checkTableStructure, columns do not match for COUNT match " . $columns_match);
                            break;

                        default:
                            nets_log::log(self::$nets_log, "nets_events, checkTableStructure, structure unkown for table '" . $table_name . "'");
                    }

                    if (isset($columns_match) && $columns_match == false) {
                        nets_log::log(self::$nets_log, "nets_events, checkTableStructure, columns do not match for " . $table_name);
                        $backup_table_name = $table_name . '_backup_' . uniqid();
                        nets_log::log(self::$nets_log, "nets_events, checkTableStructure, rename '" . $table_name . "' to '" . $backup_table_name . "'");
                        $sSql_rename = "RENAME TABLE " . $table_name . " TO " . $backup_table_name . ";";
                        $oDB->execute($sSql_rename);
                        nets_log::log(self::$nets_log, "nets_events, checkTableStructure, create '" . $table_name . "'");
                        self::createTableStructure($table_name);
                    }
                }
            }
        } catch (Exception $e) {
            nets_log::log(self::$nets_log, "nets_events, Exception:", $e->getMessage());
            nets_log::log(self::$nets_log, "nets_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function createTableStructure($table_name = 'oxnets')
    {
        try {
            $oDB = oxDb::getDb(true);
            switch ($table_name) {
                case 'oxnets':
                    // table oxnets
                    $sSql = "
					CREATE TABLE `oxnets` (
						`oxnets_id` int(10) unsigned NOT NULL auto_increment,
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
						PRIMARY KEY  (`oxnets_id`)
					) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
				";
                    $oDB->execute($sSql);
                    break;
                default:
                    nets_log::log(self::$nets_log, "nets_events, createTableStructure, unknown tablename: " . $table_name);
            }
        } catch (Exception $e) {
            nets_log::log(self::$nets_log, "nets_events, Exception:", $e->getMessage());
            nets_log::log(self::$nets_log, "nets_events, Exception Trace:", $e->getTraceAsString());
        }
    }
}
