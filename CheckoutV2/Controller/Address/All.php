<?php
namespace Increazy\CheckoutV2\Controller\Address;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class All extends Controller
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
        return isset($body->token);
    }

    public function action($body)
    {
        $customerId = $this->hashDecode($body->token);
        if (!$customerId) {
            return [];
        }
        $this->customer->load($customerId);

        return array_values(array_map(function ($address) {
            return $address->toArray();
        }, $this->customer->getAddresses()));
    }
}
