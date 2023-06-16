<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Add extends Controller
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
        $product = $this->product->load($body->product_id);
		$prodDt = $product->getData();

        $requestInfo = new \Magento\Framework\DataObject(array_merge([
	    'product' => $body->product_id,
        ], json_decode(json_encode($body->request_info ?? []), true)));

	if(isset($body->qty)) {
            $requestInfo['qty'] = $body->qty;
        }

        if(isset($body->super_attribute)) {
        	$requestInfo['super_attribute'] = (array) $body->super_attribute;
        }

	if(isset($body->options)) {
            $requestInfo['options'] = (array) $body->options;
            $requestInfo->setOptions((array) $body->options);
        }

        $this->quote->addProduct($product, $requestInfo);
        $this->quote->setStoreId($body->store);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }
}
