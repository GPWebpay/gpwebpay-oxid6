<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

if (!class_exists("gpwebpayPaymentTypes")) {
    class gpwebpayPaymentTypes {
        static $gpwebpay_payment_types = Array(
            Array(
                'payment_id' => 'gpwebpay_webpay',
                'payment_type' => 'creditcard',
                'payment_option_name' => 'gpwebpay_webpay_active',
                'payment_desc' => 'GP webpay',
                'payment_shortdesc' => 'GP webpay',
                'onAccepted_setOrderPaid' => true,
                'can_check_delivery_adress' => false,
            )
        );

        static function getGpwebpayPaymentType($payment_id) {

            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    return $type['payment_type'];
                }
            }

            return false;
        }

        static function getOxidPaymentId($payment_type) {

            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_type'] == $payment_type) {
                    return $type['payment_id'];
                }
            }

            return false;
        }

        static function getGpwebpayPaymentOptionName($payment_id) {

            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    return $type['payment_option_name'];
                }
            }
            return false;
        }

        static function getGpwebpayPaymentDesc($payment_id) {
            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    return $type['payment_desc'];
                }
            }
            return false;
        }

        static function getGpwebpayPaymentShortDesc($payment_id) {
            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    return $type['payment_shortdesc'];
                }
            }
            return false;
        }

        static function isOnAccepted_setOrderPaid($payment_id) {
            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    if (isset($type['onAccepted_setOrderPaid']) && !empty($type['onAccepted_setOrderPaid'])) {
                        return $type['onAccepted_setOrderPaid'];
                    } else {
                        return false;
                    }
                }
            }
            return false;
        }

        static function getGpwebpayCheckDeliveryOptionName($payment_id) {
            foreach (self::$gpwebpay_payment_types as $type) {
                if ($type['payment_id'] == $payment_id) {
                    if ($type['can_check_delivery_adress']) {
                        return $type['delivery_adress_option_name'];
                    } else {
                        return false;
                    }
                }
            }
            return false;
        }
    }
}