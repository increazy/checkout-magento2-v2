<?php
namespace Increazy\CheckoutV2\Block;

use Magento\Checkout\Block\Onepage\Link;
use Magento\Store\Model\ScopeInterface;

class CheckoutButton extends Link
{
    public function getTemplate()
    {
        if (!$this->_scopeConfig->isSetFlag('increazy_checkoutv2/button/active', ScopeInterface::SCOPE_STORE)) {
            return 'Magento_Checkout::onepage/link.phtml';
        }

        return parent::getTemplate();
    }
}
