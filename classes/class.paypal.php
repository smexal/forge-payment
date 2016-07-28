<?php

class ForgePaymentPaypal {
    private $apiContext = false;
    public function __construct() {
        require_once(MOD_ROOT."/forge-payment/external/paypal-sdk/autoload.php");

        $this->apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                Settings::get('forge-payment-paypal-client-id'),     // ClientID
                Settings::get('forge-payment-paypal-secret')      // ClientSecret
            )
        );
    }

    public function infos() {
        return array(
            'label' => i('Pay with Paypal'),
            'desc' => false,
            'image' => WWW_ROOT.'modules/forge-payment/assets/images/paypal-logo.png'
        );
    }
}

?>