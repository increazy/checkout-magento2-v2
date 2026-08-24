<?php
namespace Increazy\CheckoutV2\Controller\Order;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class All extends Controller
{
    /**
     * @var CollectionFactory
     */
    private $collection;
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    public function __construct(
        Context $context,
        CollectionFactory  $collection,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig,
        OrderFactory $orderFactory
    )
    {
        $this->collection = $collection;
        $this->orderFactory = $orderFactory;

        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->token) && isset($body->page) && isset($body->per_page);
    }

    /**
     * Store ids do website da store enviada no payload.
     * Retorna null quando a store não vier (ou for inválida) => sem filtro de escopo.
     */
    private function resolveStoreIds($body)
    {
        if (!isset($body->store) || $body->store === '' || $body->store === null) {
            return null;
        }

        try {
            $store = $this->store->getStore($body->store);

            $storeIds = [];
            foreach ($this->store->getWebsite($store->getWebsiteId())->getStores() as $websiteStore) {
                $storeIds[] = (int) $websiteStore->getId();
            }

            return !empty($storeIds) ? $storeIds : [(int) $store->getId()];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function action($body)
    {
        $customerId = $this->hashDecode($body->token);
        if (!$customerId) {
            return [];
        }

        $orders = $this->collection->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $customerId);

        // sem store no payload => comportamento antigo (todos os pedidos do cliente)
        $storeIds = $this->resolveStoreIds($body);
        if ($storeIds !== null) {
            $orders->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        $orders
            ->setPageSize($body->per_page)
            ->setCurPage($body->page)
        ->setOrder('created_at', 'desc');

        $orders = $orders->toArray();

        $orders['items'] = array_map(function ($order) {
            $orderModel = $this->orderFactory->create();
            $orderModel->loadByIncrementId($order['increment_id']);
            $order['status_label'] = $orderModel->getStatusLabel();

            return $order;
        }, $orders['items']);

        return $orders;
    }
}