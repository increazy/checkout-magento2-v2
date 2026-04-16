<?php
namespace Increazy\CheckoutV2\Observer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class FrontendRedirect implements ObserverInterface
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
        if (!$this->scopeConfig->isSetFlag('increazy_general/frontend_redirect/active', ScopeInterface::SCOPE_STORE)) {
            return;
        }

        $url = $this->scopeConfig->getValue('increazy_general/frontend_redirect/url', ScopeInterface::SCOPE_STORE);

        if (empty($url) || !preg_match('#^https?://#', $url)) {
            return;
        }

        $controller = $observer->getControllerAction();
        if (!$controller) {
            return;
        }

        $controller->getActionFlag()->set('', Action::FLAG_NO_DISPATCH, true);
        $controller->getResponse()->setRedirect($url);
    }
}
