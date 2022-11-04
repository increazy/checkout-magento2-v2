<?php
namespace Increazy\CheckoutV2\Controller\Auth;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;

class Bridge extends Action
{
    /**
     * @var Customer
     */
    private $customer;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var StoreManagerInterface
     */
    private $store;

    public function __construct(
        Context $context,
        Customer $customer,
        Session $session,
        StoreManagerInterface $store
    )
    {
        $this->customer = $customer;
        $this->session = $session;
        $this->store = $store;
        parent::__construct($context);
    }

    public function execute()
    {
        $body = $this->getRequest()->getParams();
        $this->store->setCurrentStore($body['store']);

        $customerID = $this->hashToStr($body['token']);
        $customer = $this->customer->load($customerID);
        $this->session->setCustomerAsLoggedIn($customer);

        echo $customer->getId();
    }

    private function hashToStr($str)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
        $hash = $scopeConfig->getValue('increazy/general/hash', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $token = base64_decode($hash);
        $parts = explode(':', $token);
        $key = substr(hash('sha256', $parts[0]), 0, 32);
        $iv = substr(hash('sha256', $parts[1]), 0, 16);

        $str = base64_decode($str);
        $data = openssl_decrypt($str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $data;
    }
}
