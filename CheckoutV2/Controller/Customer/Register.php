<?php
namespace Increazy\CheckoutV2\Controller\Customer;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;

class Register extends Controller
{
    /**
     * @var Customer
     */
    private $customer;
    /**
     * @var Encryptor
     */
    private $encryptor;


    public function __construct(
        Context $context,
        Customer $customer,
        StoreManagerInterface $store,
        Encryptor $encryptor,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->customer = $customer;
        $this->encryptor = $encryptor;

        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->email) && isset($body->password) &&
            isset($body->firstname) && isset($body->lastname) &&
            isset($body->taxvat)
        ;
    }

    public function action($body)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerObj = $objectManager->create('Magento\Customer\Model\ResourceModel\Customer\Collection');
        $collection = $customerObj
            ->addAttributeToSelect('*')
            ->addAttributeToFilter([
                ['attribute' => 'taxvat', 'eq' => $body->taxvat],
                ['attribute' => 'taxvat', 'eq' => str_replace(['.', '-', '/'], '', $body->taxvat)],
            ])
        ->load();

        $response = $collection->getData();

        if (count($response) > 0) {
            return $this->error('CPF já cadastrado');
        }

        $this->customer->setWebsiteId($this->store->getStore()->getWebsiteId());
        foreach ($body as $key => $value) {
            if ($key === 'password') {
                $key = 'password_hash';
                $value = $this->encryptor->getHash($value);
            }

            $this->customer->setData($key, $value);
        }

        $this->customer->save();

        return [
            'customer' => $this->customer->getData(),
            'token'    => $this->hashEncode($this->customer->getId()),
        ];
    }
}
