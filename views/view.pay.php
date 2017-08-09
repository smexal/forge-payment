<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\ModifyHandler;
use \Forge\Core\Abstracts\View;

class PayView extends View {
    public $name = 'pay';

    public function content($parts = array()) {
        $theAdapters = ModifyHandler::instance()->trigger(
            'modify_forge_payment_adapters',
            ForgePayment::$adapters
        );
        foreach($theAdapters as $adapter) {
            if($parts[0] == $adapter::$id) {
                return $adapter::payView($parts);
            }
        }
        return '<h1>Payment Adapter not Found: '.$parts[0].'</h1>';
    }
}
