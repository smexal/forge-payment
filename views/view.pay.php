<?php 

namespace Forge\Modules\ForgePayment;

use Forge\Core\Abstracts as Abstracts;

class ForgePayView extends Abstracts\View {
    public $name = 'pay';

    public function content($parts = array()) {
        foreach(ForgePayment::$adapters as $adapter) {
            if($parts[0] == $adapter::$id) {
                return $adapter::payView($parts);
            }
        }
    }
}