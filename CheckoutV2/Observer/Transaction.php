<?php
namespace Increazy\CheckoutV2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Transaction implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order->getIncreazyTransactionId()) {
            $payment = $order->getPayment();
            $additionalData = $payment->getAdditionalInformation();

            if (isset($additionalData['infos']['order'])) {
                $order->setIncreazyTransactionId($additionalData['infos']['order']);
            }
        }
    }

}
