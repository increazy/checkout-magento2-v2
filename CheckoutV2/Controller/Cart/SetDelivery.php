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
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Checkout\Model\Session;

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
    /**
     * @var Rate
     */
    private $shippingRate;
    /**
     * @var Session
     */
    private $checkoutSession;

    public function __construct(
        Context $context,
        Address $address,
        QuoteAddress $quoteAddress,
        Quote $quote,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig,
        Rate $shippingRate,
        Session $checkoutSession
    ) {
        $this->address = $address;
        $this->quote = $quote;
        $this->quoteAddress = $quoteAddress;
        $this->shippingRate = $shippingRate;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->quote_id) && isset($body->address_id) && isset($body->shipping_method);
    }

    public function action($body)
    {
        if ($this->isFreteRapidoEnabled()) {
            return $this->actionFreteRapido($body);
        }

        $this->address->load($body->address_id);
        $this->quoteAddress->setData($this->address->getData());

        $this->quote->load($body->quote_id)
            ->setBillingAddress($this->quoteAddress)
            ->setShippingAddress($this->quoteAddress)
        ->save();

        $this->quote->getShippingAddress()->setShippingMethod($body->shipping_method);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }

    private function actionFreteRapido($body)
    {
        $this->quote->load($body->quote_id);
        $this->quote->setStoreId($body->store);
        $this->address->load($this->quote->getShippingAddress()->getId());
        $this->quoteAddress->setData($this->address->getData());

        $this->quote
            ->setBillingAddress($this->quoteAddress)
            ->setShippingAddress($this->quoteAddress)
            ->save();

        $this->shippingRate
            ->setCode($body->shipping_method)
            ->getPrice(1);

        $shippingExtract = explode('_', $body->shipping_method);

        if (!empty($shippingExtract)) {
            $this->quote->setShippingMethodIncreazy($shippingExtract[0]);

            if ($shippingExtract[0] == 'freterapido')
                $this->quote->setShippingMethodOptionIncreazy($shippingExtract[2]);
        }

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($body->shipping_method);
        $this->quote->getShippingAddress()->addShippingRate($this->shippingRate);
        $this->quote->save();
        $this->quote->load($body->quote_id);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote, true);
    }
}
