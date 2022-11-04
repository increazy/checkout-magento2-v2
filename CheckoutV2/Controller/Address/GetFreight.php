<?php
namespace Increazy\CheckoutV2\Controller\Address;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Customer\Model\Address;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Store\Model\StoreManagerInterface;

class GetFreight extends Controller
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
        return isset($body->quote_id) && isset($body->address_id);
    }

    public function action($body)
    {
        $this->address->load($body->address_id);

        $this->address->setIsDefaultBilling('1')
            ->setIsDefaultShipping('1');
        // ->setSaveInAddressBook('1');
        $this->address->save();

        $this->quoteAddress->setData($this->address->getData());

        $this->quote->load($body->quote_id)
            ->setBillingAddress($this->quoteAddress)
            ->setShippingAddress($this->quoteAddress)
        ->save();

        $address = $this->quote->getShippingAddress();
        $address->setCollectShippingRates(true);
        $this->quote->collectTotals()->save();

        $rates = [];
        foreach($address->getGroupedAllShippingRates() as $carrier) {
            foreach($carrier as $method) {
                $rates[] = [
                    'code'    => $method->getCode(),
                    'carrier' => $method->getCarrierTitle(),
                    'method'  => $method->getMethodTitle(),
                    'error'   => $method->getErrorMessage(),
                    'price'   => $method->getPrice(),
                    'order'   => $method->getRateId()
                ];
            }
        }

        return $rates;
    }
}
