<?php
namespace Forge\Modules\ForgePayment;

use \Forge\Core\App\App;
use \Forge\Core\Classes\User;
use \Forge\Core\Classes\Utils;



class OrderTable {
    public $tableId = "orderTables";

    public function __construct() {
    }


    public function draw() {
        return App::instance()->render(CORE_TEMPLATE_DIR."assets/", "table", array(
            'id' => $this->tableId,
            'th' => array(
                Utils::tableCell(i('id', 'forge-events')),
                Utils::tableCell(i('Order Date', 'forge-events')),
                Utils::tableCell(i('User', 'forge-events')),
                Utils::tableCell(i('Typ', 'forge-events')),
                Utils::tableCell(i('Status', 'forge-events')),
                Utils::tableCell(i('Total Amount', 'forge-events')),
                Utils::tableCell(i('Items', 'forge-events')),
                Utils::tableCell(i('Actions'))
            ),
            'td' => $this->getOrderRows()
        ));
    }

    public function removeDrafts() {
        $orders = Payment::getOrders();
        foreach($orders as $order) {
            if($order->data['status'] == 'draft') {
                Payment::deleteOrder($order->data['id']);
            }
        }
    }

    private function getOrderRows() {
        $orders = Payment::getOrders();
        $ordersEnriched = array();
        foreach($orders as $order) {
            $user = new User($order->data['user']);

            array_push($ordersEnriched, array(
                Utils::tableCell($order->data['id']),
                Utils::tableCell(Utils::dateFormat($order->getDate(), true)),
                Utils::tableCell($user->get('username')),
                Utils::tableCell($order->data['payment_type']),
                Utils::tableCell($order->data['status']),
                Utils::tableCell(Utils::formatAmount($order->data['price'])),
                Utils::tableCell($order->getItemAmount()),
                Utils::tableCell($this->actions($order))
            ));
        }
        if(count($ordersEnriched) == 0) {
            array_push($ordersEnriched, [
                Utils::tableCell(i('No Orders found', 'forge-payment')),
                Utils::tableCell(""),
                Utils::tableCell(""),
                Utils::tableCell(""),
                Utils::tableCell(""),
                Utils::tableCell(""),
                Utils::tableCell(""),
                Utils::tableCell("")
            ]);
        }
        return $ordersEnriched;
    }

    private function actions($order) {
        $deleteUrl = Utils::getUrl(
            ['manage', 'module-settings', 'forge-payment', 'orders'],
            true,
            [
                'delete-order' => $order->data['id']
            ]
        );

        $acceptUrl = Utils::getUrl(
            ['manage', 'module-settings', 'forge-payment', 'orders'],
            true,
            [
                'accept-order' => $order->data['id']
            ]
        );

        $actions = [
            'actions' => []
        ];

        if($order->data['status'] != 'success') {
            array_push($actions['actions'], [
                "url" => $acceptUrl,
                "icon" => "ok",
                "name" => i('Accept order', 'forge-events'),
                "ajax" => true,
                "confirm" => false
            ]);
        }

        array_push($actions['actions'], [
            "url" => $deleteUrl,
            "icon" => "trash",
            "name" => i('Delete order', 'forge-events'),
            "ajax" => true,
            "confirm" => false
        ]);

        return App::instance()->render(CORE_TEMPLATE_DIR."assets/", "table.actions", $actions);
    }
}

?>
