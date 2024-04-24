<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Customer\Model\Address;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Store\Model\StoreManagerInterface;

class SetDelivery extends Controller
{
    /**
     * @var Address
     */
    private $address;
    /**
     * @var Quote
     */
    private $quote;
    /**
     * @var QuoteAddress
     */
    private $quoteAddress;

    public function __construct(
        Context $context,
        Address $address,
        QuoteAddress $quoteAddress,
        Quote $quote,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->address = $address;
        $this->quote = $quote;
        $this->quoteAddress = $quoteAddress;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->quote_id) && isset($body->address_id) && isset($body->shipping_method);
    }

    public function action($body)
    {
        $this->address->load($body->address_id);
        $this->quoteAddress->setData($this->address->getData());

        $this->quote->load($body->quote_id)
            ->setBillingAddress($this->quoteAddress)
            ->setShippingAddress($this->quoteAddress)
        ->save();

        $this->quote->getShippingAddress()->setShippingMethod($body->shipping_method);

        $this->quote->load($body->quote_id);
        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }
}
