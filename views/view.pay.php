<?php 
class ForgePayView extends AbstractView {
    public $name = 'pay';

    public function content($parts = array()) {
        foreach(ForgePayment::$adapters as $adapter) {
            if($parts[0] == $adapter::$id) {
                return $adapter::payView($parts);
            }
        }
    }
}