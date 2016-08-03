<?php 
class ForgePayView extends AbstractView {
    public $name = 'pay';

    public function content($parts = array()) {
        if($parts[0] == 'paypal') {
            if(count($parts) > 1) {
                if($parts[1] == 'cancel') {
                    if(array_key_exists('token', $_GET)) {
                        Payment::cancel(array('token' => $_GET['token']));
                        App::instance()->addMessage(i('Your payment has been canceled.'));
                        if(array_key_exists('redirectCancel', $_SESSION)) {
                            App::instance()->redirect($_SESSION['redirectCancel']);
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