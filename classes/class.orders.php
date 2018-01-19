<?php
namespace Forge\Modules\ForgePayment;

use Forge\Core\App\App;
use Forge\Core\App\ModifyHandler;
use Forge\Core\Classes\User;
use Forge\Core\Classes\Utils;
use \Forge\Core\Classes\TableBar;


class OrderTable {
    public $tableId = "orderTables";
    public $displayIds = true;
    public $displayActions = true;
    public $displayBar = true;
    public $displayStatus = ['draft', 'open', 'success'];
    public $filterByUser = false;

    private $statusFilter = false;
    private $searchTerm = false;

    public function __construct() {
    }

    public function handleQuery($action) {
        if(array_key_exists('t', $_GET)) {
            $this->searchTerm = $_GET['t'];
        }
        if(array_key_exists('filter__payment_status', $_GET)) {
            $this->statusFilter = $_GET['filter__payment_status'];
        }
        return json_encode([
            'newTable' => App::instance()->render(
                CORE_TEMPLATE_DIR.'assets/',
                'table-rows',
                ['td' => $this->getOrderRows()]
            )
        ]);
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

        if($this->displayBar) {
            $bar = new TableBar(Utils::url(['api', 'forge-payment', 'orders']), $this->tableId);
            $bar->enableSearch();

            $bar->addDirectFilter([
                'label' => i('Status', 'forge-payment'),
                'field' => 'payment_status',
                'values' => [
                    'draft' => i('Draft', 'forge-payment'),
                    'open' => i('Open', 'forge-payment'),
                    'success' => i('Success', 'forge-payment'),
                ]
            ]);
            $bar = $bar->render();
        } else {
            $bar = '';
        }

        return $bar.App::instance()->render(CORE_TEMPLATE_DIR."assets/", "table", array(
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

            if($this->searchTerm) {
                $found = false;
                if(strstr($user->get('username'), $this->searchTerm)) {
                    $found = true;
                }
                if(strstr($order->data['id'], $this->searchTerm)) {
                    $found = true;
                }
                if(strstr($order->data['status'], $this->searchTerm)) {
                    $found = true;
                }

                if(! $found) {
                    continue;
                }
            }
            if($this->statusFilter) {
                if($order->data['status'] !== $this->statusFilter) {
                    continue;
                }
            }

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
                $userOccurs = false;
                if($this->filterByUser) {
                    foreach($order->data['paymentMeta']->{'items'} as $item) {
                        if($item->user == $this->filterByUser) {
                            $userOccurs = true;
                        }
                    }
                }
                if(!$this->filterByUser ||
                    ($this->filterByUser && ($user->get('id') == $this->filterByUser || $userOccurs))) {
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
