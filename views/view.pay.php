<?php 
class ForgePayView extends AbstractView {
    public $name = 'pay';

    public function content($parts = array()) {
        if($parts[0] == 'paypal') {
            $fpp = new ForgePaymentPaypal();
            $fpp->paypalCheckout();
        }
    }
}