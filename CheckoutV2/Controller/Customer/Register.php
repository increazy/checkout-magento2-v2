<?php
namespace Increazy\CheckoutV2\Controller\Customer;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;

class Register extends Controller
{
    /**
     * Customer account sharing scope: 0 = global, 1 = per website
     */
    const XML_PATH_ACCOUNT_SHARE = 'customer/account_share/scope';

    /**
     * Campos do payload que não são atributos do customer
     */
    const SKIP_FIELDS = [
        'store', 'token', 'v', 'id', 'no_password',
        'password_confirmation', 'website_id', 'store_id', 'entity_id',
    ];

    /**
     * @var Customer
     */
    private $customer;
    /**
     * @var Encryptor
     */
    private $encryptor;
    /**
     * @var CollectionFactory
     */
    private $customerCollectionFactory;
    /**
     * @var ScopeConfigInterface
     */
    private $config;


    public function __construct(
        Context $context,
        Customer $customer,
        StoreManagerInterface $store,
        Encryptor $encryptor,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $customerCollectionFactory
    )
    {
        $this->customer = $customer;
        $this->encryptor = $encryptor;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->config = $scopeConfig;

        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->email) && isset($body->password) &&
            isset($body->firstname) && isset($body->lastname) &&
            isset($body->taxvat)
        ;
    }

    /**
     * Resolve a store enviada no payload; fallback para a store corrente.
     */
    private function resolveStore($body)
    {
        if (isset($body->store) && $body->store !== '' && $body->store !== null) {
            try {
                return $this->store->getStore($body->store);
            } catch (\Exception $e) {
                // store inválida => cai no fallback
            }
        }

        return $this->store->getStore();
    }

    public function action($body)
    {
        $store = $this->resolveStore($body);
        $websiteId = (int) $store->getWebsiteId();

        $taxvats = array_values(array_unique([
            $body->taxvat,
            str_replace(['.', '-', '/'], '', $body->taxvat),
        ]));

        $collection = $this->customerCollectionFactory->create()
            ->addAttributeToFilter('taxvat', ['in' => $taxvats]);

        // só restringe por website quando as contas são separadas por site
        if ((int) $this->config->getValue(self::XML_PATH_ACCOUNT_SHARE) === 1) {
            $collection->addAttributeToFilter('website_id', $websiteId);
        }

        if ($collection->getSize() > 0) {
            return $this->error('CPF já cadastrado');
        }

        $this->customer->setWebsiteId($websiteId);
        $this->customer->setStoreId((int) $store->getId());

        foreach ($body as $key => $value) {
            if (in_array($key, self::SKIP_FIELDS, true)) {
                continue;
            }

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