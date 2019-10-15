<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\App;
use \Forge\Core\App\Auth;
use \Forge\Core\Classes\Mail;
use \Forge\Core\Classes\Localization;
use \Forge\Core\Classes\Settings;
use \Forge\Core\Classes\CollectionItem;
use \Forge\Core\Classes\User;
use \Forge\Core\Classes\Utils;
use \Forge\Core\Classes\Logger;



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

    public static function getOrders($collectionItem = false) {
        $db = App::instance()->db;
        $returnOrders = [];
        $db->orderBy("order_date","desc");
        $orders = $db->get("forge_payment_orders");
        foreach($orders as $o) {
            $order = Payment::getOrder($o['id']);
            $add = false;
            if(!is_object($order->data['paymentMeta']))
                continue;
            foreach($order->data['paymentMeta']->{'items'} as $item) {
                if($item->collection == $collectionItem || $collectionItem == false) {
                    $add = true;
                }
            }
            if($add) {
                $returnOrders[] = $order;
            }
        }
        return $returnOrders;
    }

    public function getMeta() {
        return $this->data['paymentMeta'];
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

    public static function getCurrency() {
        $defaultCurrency = Settings::get('forge-payment-default-currency');
        $userCurrency = App::instance()->user->getMeta('currency');

        if($userCurrency) {
            return $userCurrency;
        }
        if($defaultCurrency) {
            return $defaultCurrency;
        }
        return 'USD';
    }

    public function getCurrencySign() {
        $currency = self::getCurrency();
        $currencySigns = [
            'USD' => '$',
            'EUR' => '€',
            'CHF' => 'CHF'
        ];
        return $currencySigns[$currency];
    }

    public static function setUserCurrency($currency) {
        if(! in_array($currency, ['USD', 'EUR', 'CHF']))
            return;
        if(! Auth::any())
            return;

        App::instance()->user->setMeta('currency', $currency);
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

    public static function clearDrafts() {
        if(! Auth::allowed("manage.forge-payment.orders.edit")) {
            return;
        }

        $orders = Payment::getOrders();
        foreach($orders as $order) {
            if($order->data['status'] == 'draft') {
                Payment::deleteOrder($order->data['id']);
            }
        }
    }

    public function getDetailView() {
        $meta = $this->getMeta();

        $return = '';
        // items
        $return.= '<h3>'.i('Items', 'forge-payment').'</h3>';
        $return.= '<table class="default small">';
        $return.= '<tr>';
        $return.= '<th>'.i('Amount', 'forge-payment').'</th>';
        $return.= '<th>'.i('Product', 'forge-payment').'</th>';
        $return.= '<th style="text-align: right">'.i('Price', 'forge-payment').'</th>';
        $return.= '</tr>';
        $total = 0;
        foreach ($meta->items as $item) {
            if($item->collection == 'delivery_cost') {
                $total+= Settings::get('forge-fixed-fee-delivery');
            } else {
                $collectionitem = new CollectionItem($item->collection);
                $total+= $item->amount * $collectionitem->getMeta('price');
            }

            $return.= '<tr>';
            $return.= '<td>'.$item->amount.'</td>';
            $return.= '<td>'.$item->title.'</td>';
            $return.= '<td style="text-align: right">'.$item->price.'</td>';
            $return.= '</tr>';
        }
        $return.= '<tr>';
        $return.= '<th colspan="100" style="text-align:right">'.Utils::formatAmount($total).'</th>';
        $return.= '</tr>';
        $return.= '</table>';

        $return.= '<hr />';

        if(property_exists($meta, 'address')) {
            $return.= '<h3>'.i('Address', 'forge-payment').'</h3>';
            $return.= '<dl class="horizontal">';
            $return.= '<dt>'.i('Salutation', 'forge-payment').'</dt><dd>'.$meta->address->salutation.'</dd>';
            $return.= '<dt>'.i('Forename', 'forge-payment').'</dt><dd>'.$meta->address->forename.'</dd>';
            $return.= '<dt>'.i('Name', 'forge-payment').'</dt><dd>'.$meta->address->name.'</dd>';
            $return.= '<dt>'.i('Street', 'forge-payment').'</dt><dd>'.$meta->address->street.'</dd>';
            $return.= '<dt>'.i('ZIP', 'forge-payment').'</dt><dd>'.$meta->address->zip.'</dd>';
            $return.= '<dt>'.i('Country', 'forge-payment').'</dt><dd>'.$meta->address->country.'</dd>';
            $return.= '<dt>'.i('E-Mail', 'forge-payment').'</dt><dd>'.$meta->address->email.'</dd>';
            $return.= '</dl>';
        }

        if(property_exists($meta, 'delivery')) {
            $return.= '<hr />';
            $return.= '<h3>'.i('Delivery', 'forge-payment').'</h3>';
            $return.= '<dl class="horizontal">';
            $return.= '<dt>'.i('Type', 'forge-payment').'</dt><dd>'.$meta->delivery->type.'</dd>';
            if(strlen($meta->delivery->address_name) > 0)
                $return.= '<dt>'.i('Name', 'forge-payment').'</dt><dd>'.$meta->delivery->address_name.'</dd>';

            if(strlen($meta->delivery->address_street) > 0)
                $return.= '<dt>'.i('Street', 'forge-payment').'</dt><dd>'.$meta->delivery->address_street.'</dd>';

            if(strlen($meta->delivery->address_place) > 0)
                $return.= '<dt>'.i('Place', 'forge-payment').'</dt><dd>'.$meta->delivery->address_place.'</dd>';

            if(strlen($meta->delivery->address_country) > 0)
                $return.= '<dt>'.i('Country', 'forge-payment').'</dt><dd>'.$meta->delivery->address_country.'</dd>';

            $return.= '</dl>';
        }

        if(property_exists($meta, 'payment_method')) {
            $return.= '<hr />';
            $return.= '<h3>'.i('Payment Method', 'forge-payment').'</h3>';
            $return.= '<p>'.$meta->payment_method->payment_method.'</p>';
        }  

        return $return;
    }

    public static function acceptOrder($order) {
        App::instance()->db->where('id', $order);
        App::instance()->db->update('forge_payment_orders', array(
            "status" => "success",
            "order_confirmed" => App::instance()->db->now()
        ));

        $order = self::getOrder($order);

        // send mail with payment information
        $mail = new Mail();
        $user = new User($order->data['user']);
        $mail->recipient($user->get('email'));
        $mail->subject(Settings::get('title_'.Localization::getCurrentLanguage()).' - '.
            sprintf(i('Your order has been completed (%s)', 'forge-payment'), $order->getId()));

        $text = Settings::get(Localization::getCurrentLanguage().'_forge-payment-accepted-email');
        $text = str_replace('{items}', $order->itemList('text'), $text);
        $text = str_replace('{user}', $user->get('username'), $text);
        $text = str_replace('{total}', Utils::formatAmount($order->data['price']), $text);
        $text = str_replace('{orderid}', $order->getId(), $text);

        $mail->addMessage($text);
        $mail->send();
    }

    private function itemList($type = 'html') {
        $list = '';
        if($type == 'html') {
            $list.='<ul>';
        }
        foreach($this->data['paymentMeta']->{'items'} as $item) {
            $col = new CollectionItem($item->collection);
            $itemPrice = $col->getMeta('price');
            $total = $itemPrice * $item->amount;
            if($type=='html') {
                $list.= '<li>';
            }
            $list.= $item->amount.'x '.$col->getMeta('title').' ('.Utils::formatAmount($total).')';
            if($type=='html') {
                $list.= '</li>';
            }
        }
        if($type == 'html') {
            $list.='</ul>';
        }
        return $list;
    }

    public function setType($type, $token = '') {
        $db = App::instance()->db;
        $db->where('id', $this->orderId);
        $db->where('payment_type', '');
        $db->update('forge_payment_orders', array(
            "token" => $token,
            "payment_type" => $type,
            "status" => "open"
        ));
    }


    public function create($type = '', $token='') {
        $_SESSION['redirectCancel'] = $this->data['redirectCancel'];
        $_SESSION['redirectSuccess'] = $this->data['redirectSuccess'];

        $data = array(
            "user" => Auth::any() ? App::instance()->user->get('id') : false,
            "price" => $this->getTotalAmount(),
            "token" => $token,
            "payment_type" => $type
        );
        if(array_key_exists("paymentMeta", $this->data)) {
            $data['meta'] = urlencode(json_encode($this->data['paymentMeta']));
        }
        $this->orderId = App::instance()->db->insert("forge_payment_orders", $data);
        return $this->orderId;
    }

    public function addMeta($key, $values = []) {
        $db = App::instance()->db;
        $db->where('id', $this->orderId);
        $meta = $db->getOne('forge_payment_orders');
        $meta = json_decode(urldecode($meta['meta']));
        $meta->$key = $values;
        $db->where('id', $this->orderId);
        $db->update('forge_payment_orders', [
            'meta' => urlencode(json_encode($meta))
        ]);
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

    public function getTotalAmount($inCents = false, $delivery = false) {
        $total = 0;
        $this->decodeData();

        foreach($this->data['paymentMeta']->{'items'} as $item) {
            $col = new CollectionItem($item->collection);
            $itemPrice = $col->getMeta('price');
            $total += $itemPrice * $item->amount;
        }
        if($delivery && is_numeric(Settings::get('forge-fixed-fee-delivery'))) {
            $total+= Settings::get('forge-fixed-fee-delivery');
        }

        if($inCents) {
            return $total*100;
        }
        return $total;
    }

    public function getDate() {
        return $this->data['order_date'];
    }

    public static function getStatus($paymentId) {
        $db = App::instance()->db;
        $db->where('id', $paymentId);
        $data = $db->getOne('forge_payment_orders');
        return $data['status'];
    }

    public function getItemAmount() {
        $amt = 0;
        $tips = '';
        foreach($this->data['paymentMeta']->{'items'} as $item) {
            $tip = [];
            $amt += $item->amount;
            if(property_exists($item, 'collection')) {
                $col = new CollectionItem($item->collection);
                $tip[] = $col->getName();
            }
            if(property_exists($item, 'user')) {
                $u = new User($item->user);
                $tip[] = $u->get('username');
            }
            $tips.= implode(" / ", $tip).'<br />';
        }
        // only one item... return tip text directly
        if($amt == 1) {
            return '<span>'.$tips.'</span>';
        }
        return '<span title="'.$tips.'" class="tipster">'.sprintf(i('%1$s items', 'forge-payment'), $amt).'</span>';
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
        if(!array_key_exists('class', $args)) {
            $args['class'] = 'btn-discreet';
        }
        if(!array_key_exists('delivery', $args)) {
            $args['delivery'] = false;
        }

        return '<a href="#" class="btn '.$args['class'].' payment-trigger"
                    data-redirect-success="'.$args['success'].'"
                    data-redirect-cancel="'.$args['cancel'].'"
                    data-payment-meta="'.urlencode(
                        json_encode(["items" => $args['items']])
                    ).'"
                    data-title="'.$args['title'].'"
                    data-delivery="'.$args['delivery'].'"
                    data-api="'.Utils::getHomeUrl()."api/".'">'.$args['label'].'</a>';
    }

}

?>
