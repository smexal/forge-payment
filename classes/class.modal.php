<?

class PaymentModal {
    private static $instance = null;
    private $data = null;
    private $item = null;
    public $adapters = array('ForgePaymentPaypal');

    public function params($data = array()) {
        $this->data = $data;

        if(array_key_exists('collectionItem', $this->data)) {
            $this->item = new CollectionItem($this->data['collectionItem']);
        }
    }

    public function render() {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "modal", array(
            'pretitle' => $this->data['title'],
            'title' => Utils::formatAmount($this->getAmount()),
            'adapters' => $this->displayPaymentAdapters()
        ));
    }

    private function displayPaymentAdapters() {
        $daptis = array();
        foreach($this->adapters as $adapter) {
            $adapter = new $adapter();
            array_push($daptis, $adapter->infos());
        }
        return $daptis;
    }

    public function getAmount() {
        if(is_null($this->item)) {
            return 0;
        }
        if(! array_key_exists('priceField', $this->data)) {
            return 0;
        }
        return $this->item->getMeta($this->data['priceField']);
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