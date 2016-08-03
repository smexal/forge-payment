<?

class PaymentModal {
    private static $instance = null;
    private $item = null;
    private $payment = null;
    public $adapters = array('ForgePaymentPaypal');

    public function params($data = array()) {
        $this->payment = new Payment($data);
    }

    public function render() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal", array(
            'pretitle' => $this->payment->data['title'],
            'title' => Utils::formatAmount($this->payment->getAmount()),
            'adapters' => $this->displayPaymentAdapters()
        ));
    }

    private function displayPaymentAdapters() {
        $daptis = array();
        foreach($this->adapters as $adapter) {
            $adapter = new $adapter($this->payment->data);
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