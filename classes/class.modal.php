<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\ModifyHandler;
use \Forge\Core\App\App;
use \Forge\Core\App\Auth;
use \Forge\Core\Classes\Utils;
use \Forge\Core\Classes\User;
use \Forge\Core\Classes\Fields;
use \Forge\Core\Classes\Settings;
use \Forge\Core\Classes\Mail;
use \Forge\Core\Classes\Localization;
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
        $_SESSION['orderId'] = $this->payment->getId();
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
        if($_POST['curstep'] == 'delivery' && ! array_key_exists('delivery_custom_address', $_POST)) {
            return json_encode(['status' => 'data-complete']);
        }
        $data = $_POST;
        foreach($_POST as $field) {
            if(strlen($field) == 0) {
                return json_encode(['status' => 'data-incomplete']);
            }
        }
        return json_encode(['status' => 'data-complete']);
    }

    public static function handleDeliveryTypeSubmit() {
        $data = $_POST;
        if(! is_numeric($_SESSION['orderId'])) {
            return;
        }
        $order = Payment::getOrder($_SESSION['orderId']);
        $delivery = [];
        $delivery['type'] = $_POST['delivery_method'];
        $delivery['address_name'] = $_POST['delivery_name'];
        $delivery['address_street'] = $_POST['delivery_street'];
        $delivery['address_place'] = $_POST['delivery_place'];
        $delivery['address_country'] = $_POST['delivery_country'];
        $order->addMeta('delivery', $delivery);

        return json_encode([
            'data' => 'saved',
            'active_step' => 'payment',
            'new_data' => self::getDeliveryPayment()
        ]);
    }

    public static function getDeliveryPayment() {
        $content = '<form data-api="'.Utils::getUrl(['api']).'">';
        $content.= '<div class="delivery-fields">';
        $content.= Fields::hidden([
            'name' => 'curstep',
            'value' => 'payment'
        ]);
        $content.= Fields::radio([
            'key' => 'payment_method',
            'label' => i('Prepayment', 'forge-payment'),
            'hint' => i('Your delivery will be sent after we get your payment.', 'forge-payment'),
            'active' => 'payment_method_prepayment',
        ], 'payment_method_prepayment');
        $content.= self::getDeliveryTypeActions();
        $content.= '</form>';
        return $content;
    }

    public static function handleAddressCheck() {
        $data = $_POST;
        if(! is_numeric($_SESSION['orderId'])) {
            return;
        }
        $order = Payment::getOrder($_SESSION['orderId']);
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
            'active_step' => 'delivery',
            'new_data' => self::getDeliveryAddress()
        ]);
    }

    public static function handleDeliveryPaymentSubmit() {
        $data = $_POST;
        if(! is_numeric($_SESSION['orderId'])) {
            return;
        }
        $order = Payment::getOrder($_SESSION['orderId']);
        $payment = [];
        $payment['payment_method'] = $_POST['payment_method'];
        $order->addMeta('payment_method', $payment);
        $order->setType($_POST['payment_method']);

        return json_encode([
            'data' => 'saved',
            'active_step' => 'confirmation',
            'new_data' => self::getDeliveryConfirmation()
        ]);
    }

    public static function getDeliveryConfirmation() {
        if(! is_numeric($_SESSION['orderId'])) {
            return;
        }
        $confirmation = '';
        $confirmation.= '<h2>'.i('Thank you for your order', 'forge-payment').'</h2>';
        $confirmation.= '<p>'.i('We just sent you an email as confirmation. We will get in contact as soon as possible.', 'forge-payment');


        self::sendDeliveryUserMail($_SESSION['orderId']);
        self::sendDeliveryAdminMail($_SESSION['orderId']);
        \Forge\Modules\ForgeShoppingcart\Cart::clear();

        return $confirmation;
    }

    public static function sendDeliveryAdminMail($orderId) {
        // send mail with payment information
        $order = Payment::getOrder($orderId);

        $meta = $order->getMeta();
        $recipient = Settings::get('forge-payment-order-admin-address');
        $name = $meta->address->forename.' '.$meta->address->forename;


        $mail = new Mail();
        $mail->recipient($recipient);
        $mail->subject(Settings::get('title_'.Localization::getCurrentLanguage()).' - '.
            sprintf(i('New Order with ID %s', 'forge-payment'), $orderId));

        $text = Settings::get(Localization::getCurrentLanguage().'_forge-payment-order-admin-email');
        $text = str_replace('{user}', $name, $text);
        $text = str_replace('{items}', self::getEmailItemList($meta), $text);
        $text = str_replace('{total}', Utils::formatAmount($order->data['price']), $text);
        $text = str_replace('{orderid}', $orderId, $text);

        $mail->addMessage($text);
        $mail->send();
    }

    public static function sendDeliveryUserMail($orderId) {
        // send mail with payment information
        $order = Payment::getOrder($orderId);

        $meta = $order->getMeta();
        $recipient = $meta->address->email;
        $name = $meta->address->forename.' '.$meta->address->forename;


        $mail = new Mail();
        $mail->recipient($recipient);
        $mail->subject(Settings::get('title_'.Localization::getCurrentLanguage()).' - '.
            sprintf(i('Confirmation for Order %s', 'forge-payment'), $orderId));

        $total = $order->data['price'];
        if(Settings::get('forge-fixed-fee-delivery')) {
            $total += Settings::get('forge-fixed-fee-delivery');
        }

        $text = Settings::get(Localization::getCurrentLanguage().'_forge-payment-order-user-email');
        $text = str_replace('{user}', $name, $text);
        $text = str_replace('{items}', self::getEmailItemList($meta), $text);
        $text = str_replace('{total}', Utils::formatAmount($total), $text);
        $text = str_replace('{orderid}', $orderId, $text);

        $mail->addMessage($text);
        $mail->send();
    }

    public static function getEmailItemList($meta) {
        $return = '';

        foreach($meta->items as $item) {
            $return.= $item->amount.'x '.$item->title.' ('.$item->price.')'."\r\n";
        }

        return $return;
    }

    public static function getDeliveryAddress() {
        $content = '<form data-api="'.Utils::getUrl(['api']).'">';

        $prefix = 'delivery';
        $content.= '<div class="delivery-fields">';
        $content.= Fields::hidden([
            'name' => 'curstep',
            'value' => 'delivery'
        ]);
        $content.= Fields::checkbox([
            'key' => $prefix.'_custom_address',
            'label' => i('Custom Delivery Address?', 'forge-payment'),
        ], '');
        $content.= '<div class="collapsed-address-fields">';
        $content.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Name & Forename', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_street',
            'label' => i('Street & No.', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_place',
            'label' => i('ZIP & Place', 'forge-payment'),
        ], '');
        $content.= Fields::text([
            'key' => $prefix.'_country',
            'label' => i('Country', 'forge-payment'),
        ], '');
        $content.='</div>';
        $content.='</div>';
        $content.='<hr />';
        $content.='<h3>'.i('Choose your desired delivery method', 'forge-payment').'</h3>';

        $content.= Fields::radio([
            'key' => $prefix.'_method',
            'label' => i('Postal delivery', 'forge-payment'),
            'active' => 'postal_delivery',
            'hint' => i('Delivery within the next 5 workdays.', 'forge-payment')
        ], 'postal_delivery');


        $content.= self::getAddressActions();
        $content.= '</form>';
        return $content;
    }

    private function renderDeliveryModal() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal-delivery", array(
            'pretitle' => Utils::formatAmount($this->payment->getTotalAmount(false, $this->delivery)),
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
            ],
            'confirmation' => [
                'title' => i('4. Confirmation', 'forge-payment'),
            ]
        ));
    }

    public static function getDeliveryTypeActions() {
        $actions = '<div class="actions">';
        //$actions.= Fields::button(i('Back', 'forge-payment'), 'discreet');
        $actions.= Fields::button(i('Complete order', 'forge-payment'),'primary');
        $actions.= '</div>';
        return $actions;
    }

    public static function getAddressActions() {
        $actions = '<div class="actions">';
        //$actions.= Fields::button(i('Back', 'forge-payment'), 'discreet');
        $actions.= Fields::button(i('Continue', 'forge-payment'),'primary');
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
        $user = false;
        if(Auth::any()) {
            $user = App::instance()->user;
        }

        $form = '';
        $form.= Fields::select([
            'key' => $prefix.'_salutation',
            'label' => i('Salutation', 'forge-payment'),
            'values' => [
                'mr' => i('Mr.', 'forge-payment'),
                'mrs' => i('Mrs.', 'forge-payment')
            ]
        ], '');
        $form.= Fields::hidden([
            'name' => 'curstep',
            'value' => 'address'
        ]);
        $form.= Fields::text([
            'key' => $prefix.'_forename',
            'label' => i('Forename', 'forge-payment'),
        ], $user ? $user->getMeta('forename') : '');
        $form.= Fields::text([
            'key' => $prefix.'_name',
            'label' => i('Name', 'forge-payment'),
        ], $user ? $user->getMeta('lastname') : '');
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
        ], $user ? $user->get('email') : '');
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
