<?php
namespace Increazy\CheckoutV2\Controller\Customer;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;

class Update extends Controller
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
     * @var Customer
     */
    private $compare;
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
        Customer $compare,
        StoreManagerInterface $store,
        Encryptor $encryptor,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $customerCollectionFactory
    )
    {
        $this->customer = $customer;
        $this->compare = $compare;
        $this->encryptor = $encryptor;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->config = $scopeConfig;

        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->email) && isset($body->taxvat) &&
            isset($body->token) &&  isset($body->firstname) &&
            isset($body->lastname)
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

    /**
     * Identifica o cliente a ser atualizado: token > id > email.
     */
    private function resolveCustomerId($body)
    {
        if (isset($body->token) && $body->token !== '' && $body->token !== '0') {
            $decoded = $this->hashDecode($body->token);
            if (is_numeric($decoded) && (int) $decoded > 0) {
                return (int) $decoded;
            }
        }

        if (isset($body->id) && (int) $body->id > 0) {
            return (int) $body->id;
        }

        return null;
    }

    public function action($body)
    {
        $store = $this->resolveStore($body);
        $websiteId = (int) $store->getWebsiteId();

        // ---- carrega o cliente que será atualizado ----
        $customerId = $this->resolveCustomerId($body);

        $this->customer->setWebsiteId($websiteId);

        if ($customerId) {
            $this->customer->load($customerId);
        }

        if (!$this->customer->getId()) {
            $this->customer->setWebsiteId($websiteId);
            $this->customer->loadByEmail($body->email);
        }

        if (!$this->customer->getId()) {
            return $this->error('customer.not_found');
        }

        $currentId = (int) $this->customer->getId();

        // ---- CPF em uso por OUTRO cliente? ----
        $taxvats = array_values(array_unique([
            $body->taxvat,
            str_replace(['.', '-', '/'], '', $body->taxvat),
        ]));

        $taxvatCheck = $this->customerCollectionFactory->create()
            ->addAttributeToFilter('taxvat', ['in' => $taxvats])
            ->addAttributeToFilter('entity_id', ['neq' => $currentId]);

        if ((int) $this->config->getValue(self::XML_PATH_ACCOUNT_SHARE) === 1) {
            $taxvatCheck->addAttributeToFilter('website_id', $websiteId);
        }

        if ($taxvatCheck->getSize() > 0) {
            return $this->error('CPF já cadastrado');
        }

        // ---- e-mail em uso por OUTRO cliente? ----
        if (strcasecmp((string) $body->email, (string) $this->customer->getEmail()) !== 0) {
            $this->compare->setWebsiteId($websiteId);
            $this->compare->loadByEmail($body->email);

            if ($this->compare->getId() && (int) $this->compare->getId() !== $currentId) {
                return $this->error('customer.exists');
            }
        }

        // ---- aplica os dados ----
        foreach ($body as $key => $value) {
            if (in_array($key, self::SKIP_FIELDS, true)) {
                continue;
            }

            if ($key === 'password') {
                if ($value === null || $value === '') {
                    continue; // não sobrescreve senha com vazio
                }
                $key = 'password_hash';
                $value = $this->encryptor->getHash($value);
            }

            $this->customer->setData($key, $value);
        }

        $this->customer->setWebsiteId($websiteId);
        $this->customer->save();

        return [
            'customer' => $this->customer->getData(),
            'token'    => $this->hashEncode($this->customer->getId()),
        ];
    }
}