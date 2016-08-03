<?php
class ForgePaymentPaypal {
    private $data = null;
    private $item = null;

    public function __construct($data = null) {
        if(!is_null($data)) {
            $this->data = $data;
        }
    }

    public function infos() {
        return array(
            'label' => i('Pay with Paypal'),
            'desc' => false,
            'image' => WWW_ROOT.'modules/forge-payment/assets/images/paypal-logo.png',
            'url' => Utils::getUrl(array("pay", "paypal"), true, $this->getParameters())
        );
    }

    public function getParameters() {
        $params = array();
        foreach($this->data as $key => $value) {
            $params[$key] = urlencode($value);
        }
        return $params;
    }

    public function paypalCheckout() {
        $payment = new Payment($_GET, true);

        require_once(MOD_ROOT."forge-payment/externals/durani-paypal/DPayPal.php");
        $paypal = new DPayPal(
            Settings::get('forge-payment-paypal-api-username'), 
            Settings::get('forge-payment-paypal-api-password'), 
            Settings::get('forge-payment-paypal-signature'),
            Settings::get('forge-payment-paypal-sandbox-mode') === "on" ? true : false
        );

        $requestParams = array(
            'RETURNURL' => Utils::getAbsoluteUrlRoot().Utils::getUrl(array("pay", "paypal", "success")),
            'CANCELURL' => Utils::getAbsoluteUrlRoot().Utils::getUrl(array("pay", "paypal", "cancel"))
        );

        $orderParams = array(
            'LOGOIMG' => "", // You can paste here your website logo image which will be displayed to the customer on the PayPal page
            "MAXAMT" => "100", // Set max transaction amount
            "NOSHIPPING" => "1", // I do not want shipping
            "ALLOWNOTE" => "0", // I do not want to allow notes
            "BRANDNAME" => Settings::get('title_'.Localization::getCurrentLanguage()),
            "GIFTRECEIPTENABLE" => "0",
            "GIFTMESSAGEENABLE" => "0"
        );
        $item = array(
            'PAYMENTREQUEST_0_AMT' => $payment->getAmount(),
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'CHF',
            'PAYMENTREQUEST_0_ITEMAMT' => $payment->getAmount(),
            'L_PAYMENTREQUEST_0_NAME0' => $payment->item->getMeta('title'),
            'L_PAYMENTREQUEST_0_DESC0' => $payment->item->getMeta('description'),
            'L_PAYMENTREQUEST_0_AMT0' => $payment->getAmount(),
            'L_PAYMENTREQUEST_0_QTY0' => '1',
            // "PAYMENTREQUEST_0_INVNUM" => $transaction->id - This field is useful if you want to send your internal transaction ID
        );

         // Send request and wait for response
         // Now we will call SetExpressCheckout API operation. 

        $response = $paypal->SetExpressCheckout($requestParams + $orderParams + $item);

        if (is_array($response) && $response['ACK'] == 'Success') { //Request successful
            //Now we have to redirect user to the PayPal
            //This is the point where user will be redirected to the PayPal page in order to provide Login details
            //After providing Login details, and after he confirms order in PayPal, user will be redirected to the page which you specified in RETURNURL field
            $token = $response['TOKEN'];
            $sandbox = '';
            if(Settings::get('forge-payment-paypal-sandbox-mode') === "on") {
                $sandbox = "sandbox.";
            }

            $payment->create("paypal", $token);

            $_SESSION['redirectCancel'] = $payment->data['redirectCancel'];
            $_SESSION['redirectSuccess'] = $payment->data['redirectSuccess'];

            header('Location: https://www.'.$sandbox.'paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . urlencode($token));
        } else if (is_array($response) && $response['ACK'] == 'Failure') {
            var_dump($response);
            exit;
        }
    }
}

?>