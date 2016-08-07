<?php 
class ForgePayView extends AbstractView {
    public $name = 'pay';

    public function content($parts = array()) {
        if($parts[0] == 'paypal') {
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
                $fpp = new ForgePaymentPaypal();
                $fpp->paypalCheckout();
            }
        }
    }
}