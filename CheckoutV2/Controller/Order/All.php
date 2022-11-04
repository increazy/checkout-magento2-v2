<?php
namespace Increazy\CheckoutV2\Controller\Order;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class All extends Controller
{
    /**
     * @var CollectionFactory
     */
    private $collection;

    public function __construct(
        Context $context,
        CollectionFactory  $collection,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->collection = $collection;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->token) && isset($body->page) && isset($body->per_page);
    }

    public function action($body)
    {
        $customerId = $this->hashDecode($body->token);
        if (!$customerId) {
            return [];
        }

        $orders = $this->collection->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $customerId)
            ->setPageSize($body->per_page)
            ->setCurPage($body->page)
        ->setOrder('created_at', 'desc');

        $orders = $orders->toArray();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderFinder = $objectManager->get('Magento\Sales\Model\Order');

        $orders['items'] = array_map(function ($order) use($orderFinder) {
            $orderFinder->loadByIncrementId($order['increment_id']);
            $order['status_label'] = $orderFinder->getStatusLabel();

            return $order;
        }, $orders['items']);

        return $orders;
    }
}
