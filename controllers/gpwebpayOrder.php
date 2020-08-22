<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayLog.php';
require_once getShopBasePath() . 'modules/gpwebpay/api/gpwebpayPaymentTypes.php';
require_once getShopBasePath() . 'modules/gpwebpay/controllers/gpmuzo.php';

class gpwebpayOrder extends gpwebpayOrder_parent {

    protected $_gpwebpay_log = false;

    /**
     * Important function that returns next step in payment process, calls parent function
     *
     * @return string iSuccess
     */
    protected function _getNextStep($iSuccess) {
        $payment = $this->getPayment();
        
        if (isset($payment)) {
            $payment_id = $this->getPayment()->getId();
        }
        gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayOrder, _getNextStep payment_id: ", $payment_id);
        $payment_type = gpwebpayPaymentTypes::getGpwebpayPaymentType($payment_id);

        if (isset($payment_type) && $payment_type) {
            gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder, _getNextStep iSuccess: ' . $iSuccess);
            if ($iSuccess === 'gpwebpayOK') {
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOK - 1');

                $oOrder = oxNew( 'oxorder' );
                $oBasket  = $this->getSession()->getBasket();
                $oUser= $this->getUser();

                $iSuccess = $oOrder->finalizeOrder( $oBasket, $oUser );

                $this->getSession()->deleteVariable('gpwebpay_in_progress_orderid');
                $this->getSession()->deleteVariable('gpwebpay_in_progress_transactionid');

                // performing special actions after user finishes order (assignment to special user groups)
                $oUser->onOrderExecute( $oBasket, $iSuccess );

                // proceeding to next view
                return $this->_getNextStep( $iSuccess );
            }
            if ($iSuccess === 'gpwebpayCancel') {
                $oLang = oxRegistry::getLang();
                $iSuccess = $oLang->translateString( 'GPWEBPAY_PAYMENT_CANCEL_MESSAGE' );
            }
            if ($iSuccess === 'gpwebpayError') {
                $oLang = oxRegistry::getLang();
                $iSuccess = $oLang->translateString( 'GPWEBPAY_PAYMENT_ERROR_MESSAGE' );
            }
        }

        return parent::_getNextStep($iSuccess);
    }
    
    private function getOrderNumber($orderId) {
        $oDB = oxDb::getDb(true);

        $sSql = "select oxordernr from oxorder where oxid = ? AND NOT ISNULL(oxordernr)";
        $oNumber = $oDB->getOne($sSql, [$orderId]);

        if (!empty($oNumber)) {
            return $oNumber;
        } else {
            gpwebpay_log::log($this->_gpwebpay_log, "gpwebpay_push, getOrder - oNumber empty");
            return false;
        }
    }
    
    private function getMaxTransactionNumber() {
        $oDB = oxDb::getDb(true);

        $sSql = "select max(gwOrderNumber) from oxgpwebpay_transactions where 1 ";
        $maxNumber = $oDB->getOne($sSql);

        if (empty($maxNumber)) {
            return 0;
        } else {
            return $maxNumber;
        }
    }

    /*Continue after return from 3Dsecure*/
    public function continuePayment()
    {    
        $iSuccess = 'gpwebpayError';
        $orderID = $this->getSession()->getVariable('gpwebpay_in_progress_orderid');
        $transactionID = $this->getSession()->getVariable('gpwebpay_in_progress_transactionid');
        if(isset($_GET['orderID']) && $_GET['orderID'] == $orderID)
        {
            //get returned data
            if(isset($_GET['PRCODE']) && $_GET['PRCODE'] == 0)
            {
                //OK all is fine
                gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayOrder, Success payment OrderId = ".$orderID.", TransactionId = ".$transactionID." ");
                $oOrder = oxnew("oxOrder");
                $oOrder->load($orderID); 
                $oOrder->oxorder__oxtransstatus = new oxField('OK'); 
                $oOrder->oxorder__oxpaid = new oxField(date('Y-m-d H:i:s', time())); 
                $oOrder->save(); 
                
                $oDB = oxDb::getDb(true);
                $sQuery = "
                UPDATE oxgpwebpay_transactions SET 
                  orderStatus = 1
                  WHERE gwOrderNumber = ".$transactionID."";

                $oDB->execute($sQuery);
                
                $oBasket = $this->getBasket();    
                $oUser = $this->getUser(); 
                
                //$iSuccess = $oOrder->sendAlertPayOrderByEmail($oUser, $oBasket);
                $iSuccess = 'gpwebpayOK';
                $this->getSession()->setVariable('gpwebpay_success',true);
            }
            else if(isset($_GET['PRCODE']) && $_GET['PRCODE'] == 50)
            {
                $oOrder = oxnew("oxOrder");
                $oOrder->load($orderID); 
                $oOrder->oxorder__oxtransstatus = new oxField('NOT_FINISHED'); //OK
                $oOrder->save(); 
                oxRegistry::get("oxUtilsView")->addErrorToDisplay( "Payment cancelled");
                $iSuccess = 'gpwebpayCancel';
                $this->getSession()->setVariable('gpwebpay_success',false);
            }
            else
            {
                $oOrder = oxnew("oxOrder");
                $oOrder->load($orderID); 
                $oOrder->oxorder__oxtransstatus = new oxField('ERROR');
                $oOrder->save(); 
                oxRegistry::get("oxUtilsView")->addErrorToDisplay( "Payment error PR=".$_GET['PRCODE']." SR=".$_GET['SRCODE']." RESULTTEXT=".@$_GET['RESULTTEXT']."" );
                $iSuccess = 'gpwebpayError';
                $this->getSession()->setVariable('gpwebpay_success',false);
            }
        }
        
        return $this->_getNextStep($iSuccess);
    }
    
    /**
     * Function that executes the payment
     */
    public function execute() {
        $oDB = oxDb::getDb(true);
        $payment_id = $this->getPayment()->getId();
        gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayOrder, execute ", $payment_id);
        $payment_type = gpwebpayPaymentTypes::getGpwebpayPaymentType($payment_id);
        
        $sUserID = $this->getSession()->getVariable("usr");
        $oUser = oxNew("oxuser", "core");
        $oUser->Load($sUserID);
        $webPayEmail = $oUser->oxuser__oxusername->value;
        
        if (isset($payment_type) && $payment_type)
        {
            $orderNumber = null;
            $orderID = $this->getOrderId();
            $basketAmount = $this->getBasketAmount();

            if(is_null($orderID)){
                //need order number. Autosucces order
                $oOrder = oxNew( 'oxorder' );
                $oBasket  = $this->getSession()->getBasket();
                $oUser= $this->getUser();

                $iSuccess = $oOrder->finalizeOrder( $oBasket, $oUser );
                $orderID = $this->getOrderId();
                if(empty($orderID)) {
                    $sGetChallenge = $this->getSession()->getVariable( 'sess_challenge' );
                    $orderID = $sGetChallenge;
                    gpwebpay_log::log($this->_gpwebpay_log, "gpwebpayOrder, get oID from Session: ", $oID);
                }
            }
            
            $orderNumber = $this->getOrderNumber($orderID);
            
            $basePath = getShopBasePath();

            $errorData = false;
            $mySession = $this->getSession();
            $oBasket = $mySession->getBasket();
            $currency = $oBasket->getBasketCurrency();
            
            $oConfig = $this->getConfig();
            $eshopName = $oConfig->getActiveShop()->oxshops__oxname->value;

            $currencyCode = array(  'CZK' => '203', //Česká koruna 
                                    'EUR' => '978', //Euro
                                    'GBP' => '826', //Pound sterling
                                    'USD' => '840', //US dollar     
                                    'HRK' => '191', //Chorvatská kuna
                                    'CAD' => '124', //Canadian Dollar
                                    'SEK' => '752', //Swedish Krona 
                                    'RUB' => '643', //Russian ruble  
                                    'PLN' => '985', //Polish zloty  
                                    'RSD' => '941', //Serbian Dinar  
                                    'NOK' => '578', //Norwegian Krone
                                    'BGN' => '975', //Bulgarian Lev
                                    'HUF' => '348', //Forint
                                    'CHF' => '756', //Swiss Franc
                                    'DKK' => '208', //Danish Krone
                                    'RON' => '946', //Romanian new leu
                                    'TRY' => '949'  //Turkish Lira
                            );
            
            $currencyCodeIso4217 = null; 

            $gateway = oxRegistry::getConfig()->getConfigParam('gpwebpay_url_gateway');
            $merchantNumber = oxRegistry::getConfig()->getConfigParam('gpwebpay_merchant_number');
            $publicKeyFilename = oxRegistry::getConfig()->getConfigParam('gpwebpay_public_key_filename');
            $privateKeyFilename = oxRegistry::getConfig()->getConfigParam('gpwebpay_private_key_filename');
            $privateKeyPassword = oxRegistry::getConfig()->getConfigParam('gpwebpay_private_key_password');
            $transferType = oxRegistry::getConfig()->getConfigParam('gpwebpay_transfer_type');
            $orderIdFrom = oxRegistry::getConfig()->getConfigParam('gpwebpay_order_id_from');

            if(strlen($currencyCode[$currency->name]) == 0)
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: no iso4217 for currency ', $currency->name);
            }
            else
            {
                $currencyCodeIso4217 = $currencyCode[$currency->name];
            }

            if(empty($gateway))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: empty gateway ');
            }

            if(empty($merchantNumber))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: no merchant number for currency ', $currency->name);
            }

            if(empty($publicKeyFilename))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: public filename is empty ');
            }
            else if(!file_exists($basePath."modules/gpwebpay/cert/".$publicKeyFilename))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: public filename not exist ', $basePath.'modules/gpwebpay/cert/'.$publicKeyFilename);
            }

            if(empty($privateKeyFilename))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: private filename is empty ');
            }
            else if(!file_exists($basePath."modules/gpwebpay/cert/".$privateKeyFilename))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: private filename not exist ', $basePath.'modules/gpwebpay/cert/'.$privateKeyFilename);
            }

            if(empty($privateKeyPassword))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: private key password is empty ');
            }

            if(strlen($transferType) == 0)
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: transfer type is empty ');
            }

            if(empty($orderIdFrom))
            {
                $errorData = true;
                gpwebpay_log::log($this->_gpwebpay_log, 'gpwebpayOrder: order id from is empty ');
            }

            if($errorData) 
            {
                return;
            }

            //create url
            $oLang = oxRegistry::getLang();
            $translateOrder = $oLang->translateString( 'GPWEBPAY_ORDER' );

            $description = $this->toASCII($translateOrder.' '.$orderNumber." - ".$eshopName);
            
            //save attempt (only one use allowed)
            $maxTransactionNumber = $this->getMaxTransactionNumber();
            if($maxTransactionNumber == 0) {
                $maxTransactionNumber = $orderIdFrom;
            }
            else
            {
                $maxTransactionNumber++;
            }
            
            $sQuery = "
                INSERT oxgpwebpay_transactions SET 
                  uniModulName = 'oxid',
                  gwOrderNumber = '{$maxTransactionNumber}',
                  shopOrderNumber = '{$orderNumber}',
                  shopPairingInfo = '{$orderID}',
                  orderStatus = 0,
                  dateCreated = NOW()";

            $oDB->execute($sQuery);
            
            $returnUrl = $this->getViewConfig()->getCurrentHomeDir()."index.php?lang=1&cl=gpwebpayOrder&fnc=continuePayment&orderID=".$orderID."&transactionID=".$maxTransactionNumber;
            
            $orderUrl = GpMuzo_CreateOrder(// funkce presmeruje browser s pozadavkem na server Muzo     
                    $gateway, // adresa kam posilat pozadavek do Muzo
                    $returnUrl, // adresa kam ma Muzo presmerovat odpoved
                    $basePath."modules/gpwebpay/cert/".$privateKeyFilename, // soubor s privatnim klicem
                    $privateKeyPassword, // heslo privatniho klice
                    $merchantNumber, // cislo obchodnika
                    $maxTransactionNumber, // cislo objednavky
                    $basketAmount, // hodnota objednavky v halerich
                    $currencyCodeIso4217, // kod meny, CZK..203, EUR..978, GBP..826, USD..840, povolene meny zalezi na smlouve s bankou
                    $transferType, // uhrada okamzite "1", nebo uhrada az z admin rozhrani
                    $orderNumber, // identifikace objednavky pro obchodnika
                    $description, // popis nakupu, pouze ASCII
                    "X", // data obchodnika, pouze ASCII
                    '', //language
                    $webPayEmail //email
            );

            $this->getSession()->setVariable('gpwebpay_in_progress_orderid', $orderID);
            $this->getSession()->setVariable('gpwebpay_in_progress_transactionid', $maxTransactionNumber);
            
            oxRegistry::getUtils()->redirect($orderUrl , false);
        }
        else 
        {
            return parent::execute();
        }
    }

    protected function getOrderId() {
        $mySession = $this->getSession();
        $oBasket = $mySession->getBasket();
        return $oBasket->getOrderId();
    }

    protected function getBasketAmount() {
        $mySession = $this->getSession();
        $oBasket = $mySession->getBasket();
        return intval(strval(($oBasket->getPrice()->getBruttoPrice() * 100)));
    }

    public function should_gpwebpay_warn_delivery() {
        $payment = $this->getPayment();
        if (isset($payment)) {
            $payment_id = $this->getPayment()->getId();
            $delivery_adress_option_name = gpwebpayPaymentTypes::getGpwebpayCheckDeliveryOptionName($payment_id);
            if ($delivery_adress_option_name && $this->getConfig()->getConfigParam($delivery_adress_option_name) == 2) {
                $oBasket = $this->getSession()->getBasket();
                $oID = $oBasket->getOrderId();
                $oOrder = oxnew("oxOrder");
                $oOrder->load($oID);
                $oDelAd = $oOrder->getDelAddressInfo();

                if ($oDelAd) {
                    return true;
                }
            }
        }
        return false;
    }

    public function toASCII($string) {
        $string = strtr($string, array(
            'ä' => 'a', 'Ä' => 'A', 'á' => 'a', 'Á' => 'A', 'à' => 'a', 'À' => 'A', 'ã' => 'a', 'Ã' => 'A', 'â' => 'a', 'Â' => 'A', 'č' => 'c', 'Č' => 'C', 'ć' => 'c', 'Ć' => 'C', 'ď' => 'd', 'Ď' => 'D', 'ě' => 'e', 'Ě' => 'E', 'é' => 'e', 'É' => 'E', 'ë' => 'e', 'Ë' => 'E', 'è' => 'e', 'È' => 'E', 'ê' => 'e', 'Ê' => 'E', 'í' => 'i', 'Í' => 'I', 'ï' => 'i', 'Ï' => 'I', 'ì' => 'i', 'Ì' => 'I', 'î' => 'i', 'Î' => 'I', 'ľ' => 'l', 'Ľ' => 'L', 'ĺ' => 'l', 'Ĺ' => 'L', 'ń' => 'n', 'Ń' => 'N', 'ň' => 'n', 'Ň' => 'N', 'ñ' => 'n', 'Ñ' => 'N', 'ó' => 'o', 'Ó' => 'O', 'ö' => 'o', 'Ö' => 'O', 'ô' => 'o', 'Ô' => 'O', 'ò' => 'o', 'Ò' => 'O', 'õ' => 'o', 'Õ' => 'O', 'ő' => 'o', 'Ő' => 'O', 'ř' => 'r', 'Ř' => 'R', 'ŕ' => 'r', 'Ŕ' => 'R', 'š' => 's', 'Š' => 'S', 'ś' => 's', 'Ś' => 'S', 'ť' => 't', 'Ť' => 'T', 'ú' => 'u', 'Ú' => 'U', 'ů' => 'u', 'Ů' => 'U', 'ü' => 'u', 'Ü' => 'U', 'ù' => 'u', 'Ù' => 'U', 'ũ' => 'u', 'Ũ' => 'U', 'û' => 'u', 'Û' => 'U', 'ý' => 'y', 'Ý' => 'Y', 'ž' => 'z', 'Ž' => 'Z', 'ź' => 'z', 'Ź' => 'Z',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'jj', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'eh', 'ю' => 'ju', 'я' => 'ja',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'JO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'JJ', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'KH', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'EH', 'Ю' => 'JU', 'Я' => 'JA',
        ));
        $orig_lc_ctype = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'en_GB');
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $string = str_replace("'", "", $string);
        setlocale(LC_CTYPE, $orig_lc_ctype);

        return $string;
    }
}
