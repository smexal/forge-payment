<?php

namespace Forge\Modules\ForgePayment;

use \Forge\Core\App;
use \Forge\Core\Classes\Mail;
use \Forge\Core\Classes\Localization;
use \Forge\Core\Classes\Settings;
use \Forge\Core\Classes\User;
use \Forge\Core\Classes\Utils;



class ForgePaymentTransaction {
    public static $id = 'transaction';
    private $orderId = null;
    private $item = null;

    public function __construct($orderId) {
        $this->orderId = $orderId;
    }

    public static function payView($parts) {
        // set order status to open
        $order = Payment::getOrder($_GET['order']);
        $order->setType(self::$id);


        // send mail with payment information
        $mail = new Mail();
        $user = new User($order->data['user']);
        $mail->recipient($user->get('email'));
        $mail->subject(Settings::get('title_'.Localization::getCurrentLanguage()).' - '.
            sprintf(i('Confirmation for Order %s', 'forge-payment'), $_GET['order']));

        $text = Settings::get(Localization::getCurrentLanguage().'_forge-payment-transaction-email');
        $text = str_replace('{user}', $user->get('username'), $text);
        $text = str_replace('{total}', Utils::formatAmount($order->data['price']), $text);
        $text = str_replace('{orderid}', $_GET['order'], $text);

        $mail->addMessage($text);
        $mail->send();

        App\App::instance()->addMessage(i('We just sent you an e-mail with more information.', 'forge-payment'), "success");
        // redirect back to payment
        if(array_key_exists('redirectSuccess', $_SESSION)) {
            App\App::instance()->redirect($_SESSION['redirectSuccess']);
        } else {
            App\App::instance()->redirect(array());
        }
    }

    public function infos() {
        return array(
            'label' => i('Pay in advance', 'forge-payment'),
            'desc' => i('You will receive a email with the transaction information.', 'forge-payment'),
            'image' => false,
            'url' => Utils::getUrl(array("pay", "transaction"), true, ['order' => $this->orderId])
        );
    }
}

?>
