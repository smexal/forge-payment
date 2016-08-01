<?

class ForgePayment extends Module {

  public function setup() {
        $this->settings = Settings::instance();
        $this->id = "forge-payment";
        $this->name = i('Payments for Forge', 'forge-payment');
        $this->description = i('Payment Adapters for Forge.', 'forge-payment');
        $this->image = $this->url().'assets/images/module-image.png';
  }

    public function start() {
        // frontend
        App::instance()->tm->theme->addScript($this->url()."assets/forge-payment.js", true);
        App::instance()->tm->theme->addStyle(MOD_ROOT."forge-payment/assets/forge-payment.less");

        Loader::instance()->loadDirectory(MOD_ROOT."forge-payment/classes/");
        Loader::instance()->loadDirectory(MOD_ROOT."forge-payment/views/");

        API::instance()->register('forge-payment', array($this, 'apiAdapter'));

        $this->settings();
    }

    private function settings() {
        if(! Auth::allowed("manage.settings", true)) {
            return;
        }

        Settings::addTab('forge-payment', i('Payment', 'forge-payment'));

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
