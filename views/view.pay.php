<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\Abstracts\View;

class PayView extends View {
    public $name = 'pay';

    public function content($parts = array()) {
        foreach(ForgePayment::$adapters as $adapter) {
            $adapter = __NAMESPACE__ .'\\'. $adapter;
            if($parts[0] == $adapter::$id) {
                return $adapter::payView($parts);
            }
        }
    }
}
