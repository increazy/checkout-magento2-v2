<?php
namespace Increazy\CheckoutV2\Controller\Address;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Remove extends Controller
{
    /**
     * @var Address
     */
    private $address;
    /**
     * @var Customer
     */
    private $customer;

    public function __construct(
        Context $context,
        Address $address,
        Customer $customer,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->customer = $customer;
        $this->address = $address;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->address_id) && isset($body->token);
    }

    public function action($body)
    {
        $this->address->load($body->address_id)->delete();

        $all = new All($this->context, $this->customer, $this->store, $this->scopeConfig);
        return $all->action($body);
    }
}
