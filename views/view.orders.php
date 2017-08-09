<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\ModifyHandler;
use \Forge\Core\Abstracts\View;
use \Forge\Core\App\App;
use \Forge\Modules\ForgePayment\OrderTable;



class OrdersView extends View {
    public $name = 'orders';
    public $allowNavigation = true;

    public function content($parts = array()) {
        $oTable = new OrderTable();
        $oTable->displayIds = false;
        $oTable->displayActions = false;
        $oTable->displayStatus = ['open', 'success'];
        $oTable->filterByUser = App::instance()->user->get('id');

        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "orders", array(
            'title' => i('Your orders', 'forge-payment'),
            'table' => $oTable->draw()
        ));
    }
}
