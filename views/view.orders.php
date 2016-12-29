<?php 

namespace Forge\Modules\ForgePayment;

use Forge\Core\Abstracts as Abstracts;

class ForgeOrdersView extends Abstracts\View {
    public $name = 'orders';
    public $allowNavigation = true;

    public function content($parts = array()) {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "orders", array(
            'title' => i('Your orders', 'forge-payment')
        ));
    }
}