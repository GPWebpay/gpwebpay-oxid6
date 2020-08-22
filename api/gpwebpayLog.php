<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

if (!class_exists("gpwebpay_log")) {
    class gpwebpay_log {

        static function log($log) {

            if (!$log) {
                return;
            }

            $date = date("r");
            $logfile = getShopBasePath() . "log/gpwebpay.log";
            $x = 0;
            foreach (func_get_args() as $val) {
                $x++;
                if ($x == 1) {
                    continue;
                }
                if (is_string($val) || is_numeric($val)) {
                    file_put_contents($logfile, "[$date] $val\n", FILE_APPEND);
                } else {
                    file_put_contents($logfile, "[$date] " . print_r($val, true) . "\n", FILE_APPEND);
                }
            }
        }
    }
}
