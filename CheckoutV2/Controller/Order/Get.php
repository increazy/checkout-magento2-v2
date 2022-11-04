<?php
namespace Increazy\CheckoutV2\Controller\Order;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Customer\Model\Backend\Customer;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class Get extends Controller
{
    /**
     * @var Product
     */
    private $product;
    /**
     * @var Order
     */
    private $order;
    /**
     * @var ReadHandler
     */
    private $handler;
    /**
     * @var Category
     */
    private $category;

    public function __construct(
        Context $context,
        Product $product,
        Category $category,
        ReadHandler $handler,
        Order $order,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->order = $order;
        $this->category = $category;
        $this->product = $product;
        $this->handler = $handler;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->order_id) && isset($body->token);
    }

    public function action($body)
    {
        $this->order->loadByIncrementId($body->order_id);

        $items = array_map(function ($item) {
            $product = $this->product->load($item->getProductId());
            $productData = $product->getData();

            return array_merge($productData, $item->getData(), [
                'image'      => CompleteQuote::getProductImage($product),
                'categories' => $product->getCategoryCollection()->toArray(),
            ]);

        }, $this->order->getAllVisibleItems());

        $tracksCollection = $this->order->getTracksCollection();
        $trackNumbers = [];

        foreach ($tracksCollection->getItems() as $track) {
            $trackNumbers[] = $track->getTrackNumber();
        }

        return array_merge($this->order->getData(), [
            'items'          => $items,
            'shipping'       => $this->order->getShippingAddress()->getData(),
            'billing'        => $this->order->getBillingAddress()->getData(),
            'delivery_name'  => $this->order->getShippingDescription(),
            'delivery_price' => $this->order->getShippingAmount(),
            'discount'       => $this->order->getDiscountAmount(),
            'total'          => $this->order->getGrandTotal(),
            'tax'            => $this->order->getTaxAmount(),
            'status_label'   => $this->order->getStatusLabel(),
            'track'          => $trackNumbers,
            'payment_method' => [
                'additional_info' => $this->order->getPayment()->getAdditionalInformation(),
                'additional_data' => $this->order->getPayment()->getAdditionalData(),
                'method'          => $this->order->getPayment()->getMethod(),
            ],
        ]);
    }
}
