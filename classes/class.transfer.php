<?php
class ForgePaymentTransaction {
    private $data = null;
    private $item = null;

    public function __construct($data = null) {
        if(!is_null($data)) {
            $this->data = $data;
        }
    }

    public function infos() {
        return array(
            'label' => i('Pay in advance.', 'forge-payment'),
            'desc' => i('You will receive a email with the transaction information.', 'forge-payment'),
            'image' => false,
            'url' => Utils::getUrl(array("pay", "transfer"), true, $this->getParameters())
        );
    }

    public function getParameters() {
        $params = array();
        foreach($this->data as $key => $value) {
            $params[$key] = urlencode($value);
        }
        return $params;
    }
}

?>