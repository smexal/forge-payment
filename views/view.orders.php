<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\Abstracts\View;
use \Forge\Core\App\App;



class OrdersView extends View {
    public $name = 'orders';
    public $allowNavigation = true;

    public function content($parts = array()) {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "orders", array(
            'title' => i('Your orders', 'forge-payment')
        ));
    }
}
