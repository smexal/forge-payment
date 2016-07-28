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

        API::instance()->register('forge-payment', array($this, 'apiAdapter'));

        $this->settings();
    }

    private function settings() {
        if(! Auth::allowed("manage.settings")) {
            return;
        }

        Settings::addTab('forge-payment', i('Payment', 'forge-payment'));

        $this->settings->registerField(
            Fields::text(array(
            'key' => 'forge-payment-paypal-client-id',
            'label' => i('Paypal Client ID', 'forge-payment'),
            'hint' => i('Check official Paypal Developer Page for more information: https://goo.gl/8BkN8I', 'forge-payment')
        ), Settings::get('forge-payment-paypal-client-id')), 'forge-payment-paypal-client-id', 'left', 'forge-payment');

        $this->settings->registerField(
            Fields::text(array(
            'key' => 'forge-payment-paypal-secret',
            'label' => i('Paypal Secret', 'forge-payment'),
            'hint' => ''
        ), Settings::get('forge-payment-paypal-secret')), 'forge-payment-paypal-secret', 'left', 'forge-payment');
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
