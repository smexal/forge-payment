<?php
namespace Forge\Modules\ForgePayment;

use Forge\Core\App\App;
use Forge\Core\App\ModifyHandler;
use Forge\Core\Classes\User;
use Forge\Core\Classes\Utils;


class OrderTable {
    public $tableId = "orderTables";
    public $displayIds = true;
    public $displayActions = true;
    public $displayStatus = ['draft', 'open', 'success'];
    public $filterByUser = false;

    public function __construct() {
    }


    public function draw() {
        $ths = [];

        if($this->displayIds) {
            $ths[] = Utils::tableCell(i('id', 'forge-payment'));
        }
        $ths[] = Utils::tableCell(i('Order Date', 'forge-payment'));
        if(! $this->filterByUser) {
            $ths[] = Utils::tableCell(i('User', 'forge-payment'));
        }
        $ths[] = Utils::tableCell(i('Typ', 'forge-payment'));
        $ths[] = Utils::tableCell(i('Status', 'forge-payment'));
        $ths[] = Utils::tableCell(i('Total Amount', 'forge-payment'));
        $ths[] = Utils::tableCell(i('Items', 'forge-payment'));
        if($this->displayActions) {
            $ths[] = Utils::tableCell(i('Actions'));
        }
        $ths = ModifyHandler::instance()->trigger(
            'modify_order_table_th',
            $ths
        );

        return App::instance()->render(CORE_TEMPLATE_DIR."assets/", "table", array(
            'id' => $this->tableId,
            'th' => $ths,
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
        $ordersEnriched = [];
        foreach($orders as $order) {
            $row = new \stdClass();
            $user = new User($order->data['user']);

            $td = [];
            if($this->displayIds) {
                $td[] = Utils::tableCell($order->data['id']);
            }
            $td[] = Utils::tableCell(Utils::dateFormat($order->getDate(), true));
            if(! $this->filterByUser) {
                $td[] = Utils::tableCell($user->get('username'), false, false, false);
            }
            /*
                i('transaction', 'forge-payment')
                i('paypal', 'forge-payment')
             */
            $td[] = Utils::tableCell(i($order->data['payment_type'], 'forge-payment'));
            /*
                i('draft', 'forge-payment')
                i('open', 'forge-payment')
                i('success', 'forge-payment')
             */
            $td[] = Utils::tableCell(i($order->data['status'], 'forge-payment'));
            $td[] = Utils::tableCell(Utils::formatAmount($order->data['price']));
            $td[] = Utils::tableCell($order->getItemAmount());
            if($this->displayActions) {
                $td[] = Utils::tableCell($this->actions($order));
            }

            $td = ModifyHandler::instance()->trigger(
                'modify_order_table_td',
                $td,
                ['order' => $order->data['id']]
            );

            $row->tds = $td;

            if(in_array($order->data['status'], $this->displayStatus)) {
                if(!$this->filterByUser || 
                    ($this->filterByUser && $user->get('id') == $this->filterByUser)) {
                    array_push($ordersEnriched, $row);
                }
            }
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
                "icon" => "check",
                "name" => i('Accept order', 'forge-payment'),
                "ajax" => true,
                "confirm" => false
            ]);
        }

        array_push($actions['actions'], [
            "url" => $deleteUrl,
            "icon" => "delete_forever",
            "name" => i('Delete order', 'forge-payment'),
            "ajax" => true,
            "confirm" => false
        ]);

        return App::instance()->render(CORE_TEMPLATE_DIR."assets/", "table.actions", $actions);
    }
}

?>
