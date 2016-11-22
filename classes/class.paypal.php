<?php
class ForgePaymentPaypal {
    public static $id = 'paypal';
    private $order = null;
    private $item = null;

    public function __construct($orderId) {
        $this->order = Payment::getOrder($orderId);
    }

    public function infos() {
        return array(
            'label' => i('Pay with Paypal', 'forge-payment'),
            'desc' => i('You will be redirected to the paypal payment terminal.', 'forge-payment'),
            'image' => WWW_ROOT.'modules/forge-payment/assets/images/paypal-logo.png',
            'url' => Utils::getUrl(array("pay", "paypal"), true, array('order' => $this->order->getId()))
        );
    }

    public static function payView($parts) {
        if(count($parts) > 1) {
            if($parts[1] == 'cancel') {
                if(array_key_exists('token', $_GET)) {
                    Payment::cancel(array('token' => $_GET['token']));
                    App::instance()->addMessage(i('Your payment has been canceled.', 'forge-payment'));
                    if(array_key_exists('redirectCancel', $_SESSION)) {
                        App::instance()->redirect($_SESSION['redirectCancel']);
                    } else {
                        App::instance()->redirect(Utils::getUrl(''));
                    }
                }
            }
            if($parts[1] == 'success') {
                if(array_key_exists('token', $_GET)) {
                    Payment::success(array("token" => $_GET['token']));
                    App::instance()->addMessage(i('Your payment has been confirmed.', 'forge-payment'), "success");
                     if(array_key_exists('redirectSuccess', $_SESSION)) {
                        App::instance()->redirect($_SESSION['redirectSuccess']);
                    } else {
                        App::instance()->redirect(Utils::getUrl(''));
                    }
                }
            }
            return $parts[1];
        } else {
            $fpp = new ForgePaymentPaypal($_GET['order']);
            $fpp->paypalCheckout();
        }
    }

    public function paypalCheckout() {
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
            'PAYMENTREQUEST_0_AMT' => $this->order->getTotalAmount(),
            'PAYMENTREQUEST_0_CURRENCYCODE' => 'CHF',
            'PAYMENTREQUEST_0_ITEMAMT' => $this->order->getTotalAmount(),
            //'L_PAYMENTREQUEST_0_NAME0' => $this->order->item->getMeta('title'),
            //'L_PAYMENTREQUEST_0_DESC0' => $this->order->item->getMeta('description'),
            //'L_PAYMENTREQUEST_0_AMT0' => $this->order->getAmount(),
            //'L_PAYMENTREQUEST_0_QTY0' => '1',
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

            $this->order->setType("paypal", $token);

            header('Location: https://www.'.$sandbox.'paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . urlencode($token));
        } else if (is_array($response) && $response['ACK'] == 'Failure') {
            var_dump($response);
            exit;
        }
    }
}

?>