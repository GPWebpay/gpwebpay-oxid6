<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayLog.php';
require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayPaymentTypes.php';

class gpwebpay_events extends oxUBase {

    static $gpwebpay_log = true;
    static $gpwebpay_table_names = Array(
        //'oxgpwebpay',
        'oxgpwebpay_transactions'
        
    );
    static $gpwebpay_oxgpwebpay_transactions_coulmn_names = 
        "'transactionPK',
        'uniModulName',
        'gwOrderNumber',
        'shopOrderNumber',
        'shopPairingInfo',
        'uniModulData',
        'uniAdapterData',
        'forexNote',
        'orderStatus',
        'dateCreated',
        'dateModified',
        'gwPairingInfo',
        'gwAccount'";

    static $gpwebpay_payment_types_active = array();

    static function onActivate() {
        gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events::onActivate()");
        $payment_types = gpwebpayPaymentTypes::$gpwebpay_payment_types;
        foreach ($payment_types as $payment_type) {
            self::checkPayment($payment_type['payment_id']);
            self::activatePayment($payment_type['payment_id'], 1);
        }
        self::checkTableStructure();
    }

    static function onDeactivate() {
        gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events::onDeactivate()");
        $payment_types = gpwebpayPaymentTypes::$gpwebpay_payment_types;
        foreach ($payment_types as $payment_type) {
            self::activatePayment($payment_type['payment_id'], 0);
        }
    }

    private static function checkPayment($payment_id) {
        try {
            $oDB = oxDb::getDb(true);

            $payment_id_exists = $oDB->getOne("SELECT oxid FROM oxpayments WHERE oxid = ?", [$payment_id]);

            if (isset($payment_id_exists) && !$payment_id_exists) {
                self::createPayment($payment_id);
            }
        } catch (Exception $e) {
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception:", $e->getMessage());
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function activatePayment($payment_id, $active = 1) {
        try {
            $oDB = oxDb::getDb(true);

            $oDB->execute("UPDATE oxpayments SET oxactive = ? WHERE oxid = ?", [$active, $payment_id]);
        } catch (Exception $e) {
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception:", $e->getMessage());
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function createPayment($payment_id) {
        try {
            $desc = gpwebpayPaymentTypes::getGpwebpayPaymentDesc($payment_id);

            if (isset($desc) && $desc) {
                $oDB = oxDb::getDb(true);
                $sSql = "INSERT INTO oxpayments (
                    `OXID`, `OXACTIVE`, `OXDESC`, `OXADDSUM`, `OXADDSUMTYPE`, `OXFROMBONI`, `OXFROMAMOUNT`, `OXTOAMOUNT`,
                    `OXVALDESC`, `OXCHECKED`, `OXDESC_1`, `OXVALDESC_1`, `OXDESC_2`, `OXVALDESC_2`,
                    `OXDESC_3`, `OXVALDESC_3`, `OXLONGDESC`, `OXLONGDESC_1`, `OXLONGDESC_2`, `OXLONGDESC_3`, `OXSORT`
                ) VALUES (
                    ?, 1, ?, 0, 'abs', 0, 0, 1000000, '', 0, ?, '', '', '', '', '', '', '', '', '', 0
                )";

                $oDB->execute($sSql, [$payment_id, $desc, $desc]);
            } else {
                gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, createPayment, desc missing");
            }
        } catch (Exception $e) {
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception:", $e->getMessage());
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function checkTableStructure() {
        try {
            $oDB = oxDb::getDb(true);

            foreach (self::$gpwebpay_table_names as $table_name) {
                $table_exists = $oDB->getOne("SHOW TABLES LIKE '".$table_name."'");

                if (!isset($table_exists) || !$table_exists) {
                    self::createTableStructure($table_name);
                } else {
                    switch ($table_name) {
                        case 'oxgpwebpay_transactions':
                            //check columns of table oxgpwebpay_transactions
                            $sSql_columns = 'SHOW COLUMNS FROM oxgpwebpay_transactions WHERE Field IN ('.self::$gpwebpay_oxgpwebpay_transactions_coulmn_names.');';
                            $columns_match = count($oDB->getAll($sSql_columns)) == 13;
                            break;
                        default :
                            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, checkTableStructure, structure unkown for table '" . $table_name . "'");
                    }

                    if (isset($columns_match) && !$columns_match) {
                        gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, checkTableStructure, columns do not match for " . $table_name);
                        $backup_table_name = $table_name .'_backup_'. uniqid();
                        gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, checkTableStructure, rename '" . $table_name . "' to '" . $backup_table_name . "'");
                        $sSql_rename = "RENAME TABLE " . $table_name . " TO " . $backup_table_name . ";";
                        $oDB->execute($sSql_rename);
                        gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, checkTableStructure, create '" . $table_name . "'");
                        self::createTableStructure($table_name);
                    }
                }
            }
        } catch (Exception $e) {
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception:", $e->getMessage());
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception Trace:", $e->getTraceAsString());
        }
    }

    private static function createTableStructure($table_name = 'oxgpwebpay_transactions') {
        try {
            $oDB = oxDb::getDb(true);
            switch ($table_name) {
                case 'oxgpwebpay_transactions':
                    //table oxgpwebpay
                    $sSql = "CREATE TABLE `oxgpwebpay_transactions` (
                    `transactionPK` int(11) unsigned NOT NULL auto_increment,
                    `uniModulName` varchar(30) collate utf8_general_ci not NULL,
                    `gwOrderNumber` varchar(30) collate utf8_general_ci default NULL,
                    `shopOrderNumber` varchar(30) collate utf8_general_ci default NULL,
                    `shopPairingInfo` varchar(100) collate utf8_general_ci default NULL,                    
                    `uniModulData` blob default NULL,
                    `uniAdapterData` blob default NULL,
                    `forexNote` varchar(200) collate utf8_general_ci default NULL,
                    `orderStatus` int(11) unsigned NOT NULL,
                    `dateCreated` datetime NOT NULL,
                    `dateModified` datetime default NULL,
                    `gwPairingInfo` varchar(80) collate utf8_general_ci default NULL,
                    `gwAccount` varchar(80) collate utf8_general_ci default NULL,
                    PRIMARY KEY  (`transactionPK`)
                  ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
                    $oDB->execute($sSql);
                    break;
                default :
                    gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, createTableStructure, unknown tablename: " . $table_name);
            }
        } catch (Exception $e) {
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception:", $e->getMessage());
            gpwebpay_log::log(self::$gpwebpay_log, "gpwebpay_events, Exception Trace:", $e->getTraceAsString());
        }
    }
    
}