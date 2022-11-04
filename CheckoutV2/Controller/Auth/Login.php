<?php
namespace Increazy\CheckoutV2\Controller\Auth;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Login extends Controller
{
    /**
     * @var Customer
     */
    private $customer;

    public function __construct(
        Context $context,
        Customer $customer,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->customer = $customer;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->email) && isset($body->password);
    }

    public function action($body)
    {
        $this->customer->setWebsiteId($this->store->getStore()->getWebsiteId());
        $this->customer->loadByEmail($body->email);
        $logged = $this->customer->authenticate($body->email, $body->password);

        if (!$logged) {
            $this->error('customer.credentials');
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($this->customer->getId());
        $customerSession = $objectManager->create('Magento\Customer\Model\Session');
        $customerSession->setCustomerAsLoggedIn($customer);

        return [
            'customer' => $this->customer->getData(),
            'token'    => $this->hashEncode($this->customer->getId()),
            'id'       => $this->customer->getId(),
            'test'     => $this->hashEncode('17'),
        ];
    }
}
