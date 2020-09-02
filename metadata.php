<?php

/**
 * @category    Payment
 * @author      Global Payments Europe s.r.o. (emailgpwebpay@gpe.cz)
 */

$sMetadataVersion = '1.2';

/**
 * Module information
 */
$aModule = array(
    'id' => 'gpwebpay-oxid6',
    'title' => 'GP webpay',
    'version' => '1.0.3',
    'author' => 'GP webpay',
    'url' => 'https://www.gpwebpay.cz/',
    'email' => 'gpwebpay@gpe.cz',
    'thumbnail' => 'img/gp-webpay-logo.png',
    'description'  => array(
        'cz' =>'GP webpay platby',
        'en' =>'GP webpay online payments',
    ),
    'extend' => array(
        'order' => 'gpwebpay/gpwebpay-oxid6/controllers/gpwebpayOrder',
        'oxpaymentgateway' => 'gpwebpay/gpwebpay-oxid6/models/gpwebpayPaymentGateway',
    ),
    'files' => array(
        'gpwebpay_events' => 'gpwebpay/gpwebpay-oxid6/core/gpwebpay_events.php',
    ),
    'blocks' => array(),
    'settings' => array(
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_url_gateway', 'type' => 'str', 'value' => ''),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_merchant_number', 'type' => 'str', 'value' => ''),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_public_key_filename', 'type' => 'str', 'value' => ''),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_private_key_filename', 'type' => 'str', 'value' => ''),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_private_key_password', 'type' => 'str', 'value' => ''),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_transfer_type', 'type' => 'select', 'value' => '0', 'constraints' => '0|1'),
        array('group' => 'gpwebpay_config', 'name' => 'gpwebpay_order_id_from', 'type' => 'str', 'value' => ''),
    ),
    'templates' => array(),
    'events'       => array(
        'onActivate'   => 'gpwebpay_events::onActivate',
        'onDeactivate' => 'gpwebpay_events::onDeactivate'
    ),
);
