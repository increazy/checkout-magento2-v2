<?php
namespace Increazy\CheckoutV2\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class DisableEmail implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        if (!$this->scopeConfig->isSetFlag('increazy_general/order_email/disable', ScopeInterface::SCOPE_STORE)) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        $order->setCanSendNewEmailFlag(0);
        $order->setEmailSent(0);
    }

}
