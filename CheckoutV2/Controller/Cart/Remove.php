<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

class Remove extends Controller
{
    /**
     * @var Quote
     */
    private $quote;
    /**
     * @var Product
     */
    private $product;

    public function __construct(
        Context $context,
        Quote $quote,
        Product $product,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->quote = $quote;
        $this->product = $product;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->product_id) && isset($body->quote_id);
    }

    public function action($body)
    {
        $this->quote->load($body->quote_id);
        $this->quote->setStore($this->store->getStore());
        $this->quote->removeItem($body->item_id);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }
}
