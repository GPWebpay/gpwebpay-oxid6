<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayLog.php';
require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayPaymentTypes.php';

class gpwebpayPaymentGateway extends gpwebpayPaymentGateway_parent {

    var $_interface;
    var $_mode;
    protected $_gpwebpay_log = true;

    public function executePayment($dAmount, &$oOrder) {
        $ox_payment_id = $this->getSession()->getBasket()->getPaymentId();
        $payment_type = gpwebpayPaymentTypes::getGpwebpayPaymentType($ox_payment_id);
        gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayPaymentGateway executePayment: " . $payment_type);

        if (!isset($payment_type) || !$payment_type) {
            gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayPaymentGateway executePayment, parent");
            return parent::executePayment($dAmount, $oOrder);
        }
        
        gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayPaymentGateway executePayment - autosuccess");
        return true;
    }
}
