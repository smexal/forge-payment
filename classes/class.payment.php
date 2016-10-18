<?php

class Payment {
    public $item = null;
    public $data = null;

    public function __construct($data, $decode = false) {
        $this->data = $data;
        if($decode) {
            $this->decodeData();
        }

        if(array_key_exists('collectionItem', $this->data)) {
            $this->item = new CollectionItem($this->data['collectionItem']);
        }
    }

    public static function cancel($condition) {
        if(array_key_exists('token', $condition)) {
            App::instance()->db->where('token', $condition['token']);
            App::instance()->db->update('forge_payment_orders', array(
                "status" => "cancel"
            ));
        }
    }

    public static function success($condition) {
        if(array_key_exists('token', $condition)) {
            App::instance()->db->where('token', $condition['token']);
            App::instance()->db->update('forge_payment_orders', array(
                "status" => "success",
                "order_confirmed" => App::instance()->db->now()
            ));
        }
    }

    public function create($type, $token='') {
        $db = App::instance()->db;
        $data = array(
            "user" => App::instance()->user->get('id'),
            "collection_item" => $this->data['collectionItem'],
            "price" => $this->getAmount(),
            "token" => $token,
            "payment_type" => $type
        );
        if(array_key_exists("paymentMeta", $this->data)) {
            $data['meta'] = $this->data['paymentMeta'];
        }
        $db->insert("forge_payment_orders", $data);
    }

    private function decodeData() {
        foreach($this->data as $key => $value) {
            $this->data[$key] = urldecode($value);
        }
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

    public static function getPayments($user) {
        $db = App::instance()->db;
        $db->where('user', $user);
        $db->where('status', 'success');
        $orders = $db->get('forge_payment_orders');
        for($index = 0; $index < count($orders); $index++) {
            $orders[$index]['meta'] = json_decode(urldecode($orders[$index]['meta']));
        }
        return $orders;
    }

    public static function button($args) {
        if(!array_key_exists('success', $args)) {
            $args['success'] = Utils::getCurrentUrl();
        }
        if(!array_key_exists('cancel', $args)) {
            $args['cancel'] = Utils::getCurrentUrl();
        }
        if(!array_key_exists('priceField', $args)) {
            $args['priceField'] = "price";
        }
        if(!array_key_exists('title', $args)) {
            $args['title'] = i('Payment', 'forge-payment');
        }
        if(!array_key_exists('label', $args)) {
            $args['label'] = '';
        }

        return '<a href="#" class="btn btn-discreet payment-trigger" 
                    data-redirect-success="'.$args['success'].'"
                    data-redirect-cancel="'.$args['cancel'].'"
                    data-payment-meta="'.urlencode(json_encode(array(
                        "items" => $args['items']
                    ))).'"
                    data-price-field="'.$args['priceField'].'"
                    data-title="'.$args['title'].'"
                    data-api="'.Utils::getHomeUrl()."api/".'">'.$args['label'].'</a>';
    }

}

?>