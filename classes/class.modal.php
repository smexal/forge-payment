<?

namespace Forge\Modules\ForgePayment;

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
            'adapters' => $this->displayPaymentAdapters()
        ));
    }

    private function displayPaymentAdapters() {
        $daptis = array();
        foreach(ForgePayment::$adapters as $adapter) {
            $adapter = __NAMESPACE__ .'\\'. $adapter;
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
