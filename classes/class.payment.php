<?php

class Payment {
    public $data = null;
    private $orderId = null;

    public static function getOrder($orderId) {
        return new self(false, false, $orderId);
    }

    public static function deleteOrder($id) {
        App::instance()->db->where("id", $id);
        App::instance()->db->delete('forge_payment_orders');
    }

    public static function getOrders($collectionItem) {
        $db = App::instance()->db;
        $returnOrders = [];
        $db->orderBy("order_date","desc");
        $orders = $db->get("forge_payment_orders");
        foreach($orders as $o) {
            $order = Payment::getOrder($o['id']);
            $add = false;
            foreach($order->data['paymentMeta']->{'items'} as $item) {
                if($item->collection == $collectionItem) {
                    $add = true;
                }
            }
            if($add) {
                $returnOrders[] = $order;
            }
        }
        return $returnOrders;
    }

    public function __construct($data = false, $decode = false, $id = false) {
        if($data) {
            $this->data = $data;
        }
        if($decode) {
            $this->decodeData();
        }
        if($id) {
            $this->orderId = $id;
            App::instance()->db->where('id', $this->orderId);
            $order = App::instance()->db->getOne('forge_payment_orders');
            $this->data = array();
            $this->data = $order;
            $this->data['paymentMeta'] = $order['meta'];
            $this->decodeData();
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

    public static function acceptOrder($order) {
        App::instance()->db->where('id', $order);
        App::instance()->db->update('forge_payment_orders', array(
            "status" => "success",
            "order_confirmed" => App::instance()->db->now()
        ));
    }

    public function setType($type, $token = '') {
        $db = App::instance()->db;
        $db->where('id', $this->orderId);
        $db->update('forge_payment_orders', array(
            "token" => $token,
            "payment_type" => $type,
            "status" => "open"
        ));
    }


    public function create($type = '', $token='') {
        $_SESSION['redirectCancel'] = $this->data['redirectCancel'];
        $_SESSION['redirectSuccess'] = $this->data['redirectSuccess'];

        $db = App::instance()->db;
        $data = array(
            "user" => App::instance()->user->get('id'),
            "price" => $this->getTotalAmount(),
            "token" => $token,
            "payment_type" => $type
        );
        if(array_key_exists("paymentMeta", $this->data)) {
            $data['meta'] = urlencode(json_encode($this->data['paymentMeta']));
        }
        $this->orderId = $db->insert("forge_payment_orders", $data);
        return $this->orderId;
    }

    private function decodeData() {
        if(is_array($this->data)) {
            foreach($this->data as $key => $value) {
                if(is_string($value)) {
                    $value = urldecode($value);
                }
                if($key == 'paymentMeta' && ! is_object($value)) {
                    $value = json_decode($value);
                }
                $this->data[$key] = $value;
            }
        } else {
            $this->data['paymentMeta'] = urldecode($this->data['paymentMeta']);
        }
    }

    public function getId() {
        return $this->orderId;
    }

    public function getTotalAmount() {
        $total = 0;
        $this->decodeData();

        foreach($this->data['paymentMeta']->{'items'} as $item) {
            $col = new CollectionItem($item->collection);
            $itemPrice = $col->getMeta('price');
            $total += $itemPrice * $item->amount;
        }
        return $total;
    }

    public function getDate() {
        return $this->data['order_date'];
    }

    public function getItemAmount() {
        $amt = 0;
        foreach($this->data['paymentMeta']->{'items'} as $item) {
            $amt += $item->amount;
        }
        return $amt;
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
                    data-title="'.$args['title'].'"
                    data-api="'.Utils::getHomeUrl()."api/".'">'.$args['label'].'</a>';
    }

}

?>