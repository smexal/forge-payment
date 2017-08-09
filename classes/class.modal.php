<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\ModifyHandler;
use \Forge\Core\App\App;
use \Forge\Core\Classes\Utils;
use \Forge\Modules\ForgePayment\ForgePaymentPaypal;
use \Forge\Modules\ForgePayment\ForgePaymentTransaction;

class PaymentModal {
    private static $instance = null;
    private $item = null;
    private $payment = null;
    public $adapters = array();

    public function params($data = array()) {
        $this->payment = new Payment($data);
        $this->payment->create();
    }

    public function render() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal", array(
            'pretitle' => $this->payment->data['title'],
            'title' => Utils::formatAmount($this->payment->getTotalAmount()),
            'adapters' => $this->payment->getId() == 0 ? $this->errorAdapter() : $this->displayPaymentAdapters()
        ));
    }

    private function errorAdapter() {
        return [[
            'image' => false,
            'label' => i('Error on Order Creation', 'forge-payment'),
            'desc' => i('There was an error on creating the order.', 'forge-payment'),
            'url' => '#'
        ]];
    }

    private function displayPaymentAdapters() {
        $daptis = array();
        $theAdapters = ModifyHandler::instance()->trigger(
            'modify_forge_payment_adapters',
            ForgePayment::$adapters
        );
        foreach($theAdapters as $adapter) {
            $adapter = new $adapter($this->payment->getId());
            array_push($daptis, $adapter->infos());
        }
        return $daptis;
    }

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct(){}
    private function __clone(){}
}

?>
