<?php 
class ForgeOrdersView extends AbstractView {
    public $name = 'orders';
    public $allowNavigation = true;

    public function content($parts = array()) {
        return App::instance()->render(MOD_ROOT."forge-payment/templates/", "orders", array(
            'title' => i('Your orders', 'forge-payment')
        ));
    }
}