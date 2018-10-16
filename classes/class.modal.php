<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\ModifyHandler;
use \Forge\Core\App\App;
use \Forge\Core\Classes\Utils;
use \Forge\Core\Classes\Fields;
use \Forge\Modules\ForgePayment\ForgePaymentPaypal;
use \Forge\Modules\ForgePayment\ForgePaymentTransaction;

class PaymentModal {
    private static $instance = null;
    private $item = null;
    private $payment = null;
    public $adapters = array();
    private $delivery = false;

    public function params($data = array()) {
        if($data['delivery'] == '1') {
            $this->delivery = true;
        }
        $this->payment = new Payment($data);
        $this->payment->create();
    }

    public function render() {
        if($this->delivery) {
            return $this->renderDeliveryModal();
        }
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal", array(
            'pretitle' => $this->payment->data['title'],
            'title' => Utils::formatAmount($this->payment->getTotalAmount()),
            'adapters' => $this->payment->getId() == 0 ? $this->errorAdapter() : $this->displayPaymentAdapters()
        ));
    }

    private function renderDeliveryModal() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal-delivery", array(
            'pretitle' => Utils::formatAmount($this->payment->getTotalAmount()),
            'title' => i('Checkout Process', 'forge-payment'),
            'inv_addr' => [
                'info_text' => i('Leave empty, if same as delivery address.', 'forge-payment'),
                'title' => i('Invoice Address', 'forge-payment'),
                'form' => $this->getAddressForm('invoice')
            ],
            'del_addr' => [
                'title' => i('Delivery Address', 'forge-payment'),
                'form' => $this->getAddressForm('delivery')
            ],
            'method' => [
                'title' => i('Payment Method', 'forge-payment'),
            ]
        ));
    }

    private function getAddressForm($prefix='') {
        $form = '';
        $form.= Fields::select([
            'key' => $prefix.'_salutation',
            'label' => i('Salutation', 'core'),
            'values' => [
                'mr' => i('Mr.', 'core'),
                'mrs' => i('Mrs.', 'core')
            ]
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_forename',
            'label' => i('Forename', 'core'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Name', 'core'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_street',
            'label' => i('Street & No.', 'core'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_zip',
            'label' => i('ZIP & Place', 'core'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_country',
            'label' => i('Country', 'core'),
        ], '');
        return $form;
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
