<?php
namespace Increazy\CheckoutV2\Controller\Auth;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Connect extends Controller
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
        $this->customer->load($customerId);

        return [
            'customer' => $this->customer->getData(),
            'token'    => $body->token,
        ];
    }
}
