<?

class ForgePayment extends Module {
    public static $adapters = ['ForgePaymentPaypal', 'ForgePaymentTransaction'];

    public function setup() {
        $this->settings = Settings::instance();
        $this->id = "forge-payment";
        $this->name = i('Payments', 'forge-payment');
        $this->description = i('Payment Adapters for Forge.', 'forge-payment');
        $this->image = $this->url().'assets/images/module-image.png';
    }

    public function start() {
        Auth::registerPermissions("manage.forge-payment.orders.edit");

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

        Loader::instance()->loadDirectory(MOD_ROOT."forge-payment/classes/");
        Loader::instance()->loadDirectory(MOD_ROOT."forge-payment/views/");

        API::instance()->register('forge-payment', array($this, 'apiAdapter'));

        $this->settings();
    }

    public function orders() {
        if(Auth::allowed("manage.forge-payment.orders.edit")) {
            if(array_key_exists('accept-order', $_GET)) {
                $orderTable = new OrderTable();
                Payment::acceptOrder($_GET['accept-order']);
            }
            if(array_key_exists('delete-order', $_GET)) {
                $orderTable = new OrderTable();
                Payment::deleteOrder($_GET['delete-order']);
            }
            if(array_key_exists('clear-drafts', $_GET)) {
                Payment::clearDrafts();
            }
        }

        $orders = new OrderTable();
        return $orders->draw();
    }

    
    public function ordersActions() {
        if(! Auth::allowed("manage.forge-payment.orders.edit")) {
            return;
        }
        $url = Utils::getUrl(
            ['manage', 'module-settings', 'forge-payment', 'orders'],
            true,
            [
                'clear-drafts' => "true"
            ]
        );
        return '<a class="ajax btn btn-xs" href="'.$url.'">'.i('Clear drafts', 'forge-events').'</a>';
    }
    

    private function settings() {
        if(! Auth::allowed("manage.settings", true)) {
            return;
        }

        /*
         * PAYPAL
         */

        $this->settings->registerField(
            Fields::text(array(
            'key' => 'forge-payment-paypal-api-username',
            'label' => i('Paypal API Username', 'forge-payment'),
            'hint' => i('Check official Paypal Developer Page for more information: https://goo.gl/8BkN8I', 'forge-payment')
        ), Settings::get('forge-payment-paypal-api-username')), 'forge-payment-paypal-api-username', 'left', 'forge-payment');

        $this->settings->registerField(
            Fields::text(array(
            'key' => 'forge-payment-paypal-api-password',
            'label' => i('Paypal API Password', 'forge-payment'),
            'hint' => ''
        ), Settings::get('forge-payment-paypal-api-password')), 'forge-payment-paypal-api-password', 'left', 'forge-payment');

        $this->settings->registerField(
            Fields::text(array(
            'key' => 'forge-payment-paypal-signature',
            'label' => i('Paypal Signature', 'forge-payment'),
            'hint' => ''
        ), Settings::get('forge-payment-paypal-signature')), 'forge-payment-paypal-signature', 'left', 'forge-payment');

        $this->settings->registerField(
            Fields::checkbox(array(
            'key' => 'forge-payment-paypal-sandbox-mode',
            'label' => i('Use Sandbox Mode'),
            'hint' => i('If this setting is enabled, paypal sandbox domain will be used.'),
        ), Settings::get('forge-payment-paypal-sandbox-mode')), 'forge-payment-paypal-sandbox-mode', 'left', 'forge-payment');

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
    }

    public function apiAdapter($query) {

        if($query == 'modal') {
            $modal = PaymentModal::instance();
            $modal->params($_POST);
            return json_encode(array("content" => $modal->render()));
        }

    }
}

?>
