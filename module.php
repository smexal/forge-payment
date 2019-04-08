<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\Abstracts\Module;
use \Forge\Core\App\API;
use \Forge\Core\App\App;
use \Forge\Core\App\Auth;
use \Forge\Core\Classes\Logger;
use \Forge\Core\Classes\Fields;
use \Forge\Core\Classes\Settings;
use \Forge\Core\Classes\Localization;
use \Forge\Core\Classes\Utils;
use \Forge\Core\App\ModifyHandler;



class ForgePayment extends Module {
    public $defaultSettingsView = 'orders';

    public static $adapters = [
        '\Forge\Modules\ForgePayment\ForgePaymentTransaction'
    ];

    public function setup() {
        $this->settings = Settings::instance();
        $this->id = "forge-payment";
        $this->name = i('Payments', 'forge-payment');
        $this->description = i('Payment Adapters for Forge.', 'forge-payment');
        $this->image = $this->url().'assets/images/module-image.png';
    }

    public function start() {
        Auth::registerPermissions("manage.forge-payment");
        Auth::registerPermissions("manage.forge-payment.orders.edit");

        $this->install();

        $this->settingsViews = [
            [
                'callable' => 'orders',
                'title' => i("Orders", 'forge-payment'),
                'url' => 'orders'
            ]
        ];

        // frontend
        App::instance()->tm->theme->addScript($this->url()."assets/forge-payment.js", true);
        App::instance()->tm->theme->addStyle(MOD_ROOT."forge-payment/assets/forge-payment.less");

        API::instance()->register('forge-payment', array($this, 'apiAdapter'));

        $this->settings();
    }

    public function orders() {
        if (Auth::allowed("manage.forge-payment.orders.edit")) {
            $uri = Utils::getUriComponents();
            if(count($uri) > 4 && $uri[4] == 'detail' && is_numeric($uri[5])) {
                ModifyHandler::instance()->add('modify_module_settings_template_directory', function($args) {
                    return CORE_TEMPLATE_DIR."views/parts/";
                });

                ModifyHandler::instance()->add('modify_module_settings_template_name', function($args) {
                    return "crud.modify";
                });

                $order = Payment::getOrder($uri[5]);
                ModifyHandler::instance()->add('modify_module_settings_render_args', function() use(&$order) {
                    return [
                        'title' => i('Order Details', 'forge-payment'),
                        'message' => '',
                        'form' => $order->getDetailView()
                    ];
                });
            }

            if (array_key_exists('accept-order', $_GET)) {
                $orderTable = new OrderTable();
                Payment::acceptOrder($_GET['accept-order']);
            }
            if (array_key_exists('delete-order', $_GET)) {
                $orderTable = new OrderTable();
                Payment::deleteOrder($_GET['delete-order']);
            }
            if (array_key_exists('clear-drafts', $_GET)) {
                Payment::clearDrafts();
            }
        }

        $orders = new OrderTable();
        return $orders->draw();
    }


    public function ordersActions() {
        if (! Auth::allowed("manage.forge-payment.orders.edit", true)) {
            return;
        }
        $url = Utils::getUrl(
            ['manage', 'module-settings', 'forge-payment', 'orders'],
            true,
            [
                'clear-drafts' => "true"
            ]
        );
        return '<a class="ajax btn btn-primary btn-xs" href="'.$url.'">'.i('Clear drafts', 'forge-events').'</a>';
    }


    private function settings() {
        if (! Auth::allowed("manage.forge-payment.orders.edit", true)) {
            return;
        }

        /*
         * TRANSACTION
         */
        $transMailKey = Localization::getCurrentLanguage().'_forge-payment-transaction-email';
        $this->settings->registerField(
            Fields::textarea(array(
            'key' => $transMailKey,
            'label' => i('Transaction E-Mail', 'forge-payment'),
            'hint' => i('Use the following variables: {user} {total} {orderid}, which get replaced by actual values.', 'forge-payment')
        ), Settings::get($transMailKey)), $transMailKey, 'right', 'forge-payment');

        /*
         * ORDER ACCEPTED
         */
        $transMailKey = Localization::getCurrentLanguage().'_forge-payment-accepted-email';
        $this->settings->registerField(
            Fields::textarea(array(
            'key' => $transMailKey,
            'label' => i('Transaction E-Mail', 'forge-payment'),
            'hint' => i('Use the following variables: {user} {total} {orderid} {items}, which get replaced by actual values.', 'forge-payment')
        ), Settings::get($transMailKey)), $transMailKey, 'right', 'forge-payment');


        /*
         * USER EMAIL FOR SHOP ORDER
         */
        $orderUserMailKey = Localization::getCurrentLanguage().'_forge-payment-order-user-email';
        $this->settings->registerField(
            Fields::textarea(array(
            'key' => $orderUserMailKey,
            'label' => i('User E-Mail for delivery order.', 'forge-payment'),
            'hint' => i('Use the following variables: {user} {total} {orderid} {items}, which get replaced by actual values.', 'forge-payment')
        ), Settings::get($orderUserMailKey)), $orderUserMailKey, 'right', 'forge-payment');

        /*
         * ADMIN EMAIL FOR SHOP ORDER
         */
        $orderAdminMailKey = Localization::getCurrentLanguage().'_forge-payment-order-admin-email';
        $this->settings->registerField(
            Fields::textarea(array(
            'key' => $orderAdminMailKey,
            'label' => i('Administrator E-Mail for delivery order.', 'forge-payment'),
            'hint' => i('Use the following variables: {user} {total} {orderid} {items}, which get replaced by actual values.', 'forge-payment')
        ), Settings::get($orderAdminMailKey)), $orderAdminMailKey, 'right', 'forge-payment');

        /*
         * ADMIN EMAIL ADDRESS FOR SHOP ORDERS
         */
        $orderAdminAddressKey = 'forge-payment-order-admin-address';
        $this->settings->registerField(
            Fields::text(array(
            'key' => $orderAdminAddressKey,
            'label' => i('Administrator E-Mail for delivery order.', 'forge-payment'),
            'hint' => i('Use the following variables: {user} {total} {orderid} {items}, which get replaced by actual values.', 'forge-payment')
        ), Settings::get($orderAdminAddressKey)), $orderAdminAddressKey, 'right', 'forge-payment');
    }

    public function apiAdapter($data) {
        if ($data == 'modal') {
            $modal = PaymentModal::instance();
            $modal->params($_POST);
            return json_encode(array("content" => $modal->render()));
        }
        if($data == 'delivery-check') {
            return PaymentModal::handleDeliveryCheck($data);
        }
        if($data == 'submit-delivery') {
            if($_POST['curstep'] == 'address') {
                return PaymentModal::handleAddressCheck();    
            } elseif($_POST['curstep'] == 'delivery' ) {
                return PaymentModal::handleDeliveryTypeSubmit();
            } elseif($_POST['curstep'] == 'payment') {
                return PaymentModal::handleDeliveryPaymentSubmit();
            }
            return;
        }
        if($data['query'][0] == 'orders') {
            if( ! Auth::allowed('manage.forge-payment', true)) {
                return '';
            }
            $oTable = new OrderTable();
            return $oTable->handleQuery($data);
        }
    }

    private function install() {
        if (Settings::get($this->name . ".installed")) {
            return;
        }

        App::instance()->db->rawQuery(
            'CREATE TABLE IF NOT EXISTS `forge_payment_orders` (
              `id` int(7) NOT NULL AUTO_INCREMENT,
              `user` int(7) NOT NULL,
              `price` float NOT NULL,
              `token` varchar(150) NOT NULL,
              `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `order_confirmed` timestamp NULL NULL,
              `payment_type` varchar(250) NOT NULL,
              `status` varchar(100) NOT NULL DEFAULT \'draft\',
              `meta` text,
              PRIMARY KEY (`id`),
              KEY `user` (`user`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
        );

        Settings::set($this->name . ".installed", 1);
    }
}

?>
