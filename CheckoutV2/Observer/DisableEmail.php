<?php
namespace Increazy\CheckoutV2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DisableEmail implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $order->setCanSendNewEmailFlag(0);
        $order->setEmailSent(0);
    }

}
