<?php
namespace Increazy\CheckoutV2\Controller\Customer;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;

class Update extends Controller
{
    /**
     * @var Customer
     */
    private $customer;
    /**
     * @var Customer
     */
    private $compare;
    /**
     * @var Encryptor
     */
    private $encryptor;


    public function __construct(
        Context $context,
        Customer $customer,
        Customer $compare,
        StoreManagerInterface $store,
        Encryptor $encryptor,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->customer = $customer;
        $this->compare = $compare;
        $this->encryptor = $encryptor;

        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->email) && isset($body->taxvat) &&
            isset($body->token) &&  isset($body->firstname) &&
            isset($body->lastname)
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
        $responseID = count($response) > 0 ? $response[0]['entity_id'] : null;

        $this->customer->setWebsiteId($this->store->getStore()->getWebsiteId());
        $this->compare->setWebsiteId($this->store->getStore()->getWebsiteId());

        $this->compare->loadByEmail($body->email);

        if ($this->compare->getId() != $responseID || $this->customer->getId() != $responseID) {
            if ($responseID !== null) {
                return $this->error('CPF já cadastrado');
            }
        }

        if ($this->customer->getId() !== $this->compare->getId()) {
            $this->error('customer.exists');
        }

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
