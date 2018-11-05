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

    public static function handleDeliveryCheck($data) {
        $data = $_POST;
        foreach($_POST as $field) {
            if(strlen($field) == 0) {
                return json_encode(['status' => 'data-incomplete']);
            }
        }
        return json_encode(['status' => 'data-complete']);
    }

    public static function handleAddressCheck() {
        $data = $_POST;
        if(! is_numeric($_POST['order'])) {
            return;
        }
        $order = Payment::getOrder($_POST['order']);
        $address = [];
        $address['salutation'] = $_POST['address_salutation'];
        $address['forename'] = $_POST['address_forename'];
        $address['name'] = $_POST['address_name'];
        $address['street'] = $_POST['address_street'];
        $address['zip'] = $_POST['address_zip'];
        $address['country'] = $_POST['address_country'];
        $address['email'] = $_POST['address_email'];
        $order->addMeta('address', $address);

        return json_encode([
            'data' => 'saved',
            'new_data' => self::getDeliveryAddress()
        ]);
    }

    public static function getDeliveryAddress() {
        $content = '<form data-api="'.Utils::getUrl(['api']).'">';

        $prefix = 'delivery';
        $content.= '<div class="delivery-fields">';
        $content.= Fields::checkbox([
            'key' => $prefix.'_custom_address',
            'label' => i('Custom Delivery Address?', 'forge-payment'),
        ], '');

        $content.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Name & Forename', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Street & No.', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_place',
            'label' => i('ZIP & Place', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_place',
            'label' => i('ZIP & Place', 'forge-payment'),
        ], '');
        $content.='</div>';
        $content.='<hr />';
        $content.='<h3>'.i('Choose your desired delivery method', 'forge-payment').'</h3>';

        $content.= Fields::checkbox([
            'key' => $prefix.'_delivery_method',
            'label' => i('Postal delivery', 'forge-payment'),
            'hint' => i('Delivery within the next 5 workdays.', 'forge-payment')
        ], 'delivery_method_1');

        $content.= Fields::checkbox([
            'key' => $prefix.'_delivery_method',
            'label' => i('Postal delivery', 'forge-payment'),
            'hint' => i('Delivery within the next 5 workdays.', 'forge-payment')
        ], 'delivery_method_2');


        $content.= self::getAddressActions();
        $content.= '</form>';
        return $content;
    }

    private function renderDeliveryModal() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal-delivery", array(
            'pretitle' => Utils::formatAmount($this->payment->getTotalAmount()),
            'title' => i('Checkout', 'forge-payment'),
            'address' => [
                'title' => i('1. Address', 'forge-payment'),
                'form' => '<form data-api="'.Utils::getUrl(['api']).'">'.$this->getAddressForm('address'),
                'action' => $this->getDeliveryActions().'</form>'
            ],
            'delivery' => [
                'title' => i('2. Delivery', 'forge-payment'),
            ],
            'payment' => [
                'title' => i('3. Payment', 'forge-payment'),
            ]
        ));
    }

    public static function getAddressActions() {
        $actions = '<div class="actions">';
        $actions.= Fields::button(i('Back', 'forge-payment'),'secondary');
        $actions.= Fields::button(i('Continue', 'forge-payment'),'primary', false, true);
        $actions.= '</div>';
        return $actions;
    }

    private function getDeliveryActions() {
        $actions = '<div class="actions">';
        $actions.= Fields::button(i('Continue', 'forge-payment'),'primary', false, true);
        $actions.= '</div>';
        return $actions;
    }

    private function getAddressForm($prefix='') {
        $form = '';
        $form.= Fields::select([
            'key' => $prefix.'_salutation',
            'label' => i('Salutation', 'forge-payment'),
            'values' => [
                'mr' => i('Mr.', 'forge-payment'),
                'mrs' => i('Mrs.', 'forge-payment')
            ]
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_forename',
            'label' => i('Forename', 'forge-payment'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Name', 'forge-payment'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_street',
            'label' => i('Street & No.', 'forge-payment'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_zip',
            'label' => i('ZIP & Place', 'forge-payment'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_country',
            'label' => i('Country', 'forge-payment'),
        ], '');
        $form.= Fields::text([
            'key' => $prefix.'_email',
            'label' => i('E-Mail Address', 'forge-payment'),
        ], '');
        $form.= Fields::hidden([
            'key' => 'order',
            'value' => $this->payment->getId()
        ]);
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
