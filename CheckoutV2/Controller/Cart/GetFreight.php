<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class GetFreight extends Controller
{
    /**
     * @var Quote
     */
    private $quote;


    public function __construct(
        Context $context,
        Quote $quote,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->quote = $quote;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->postcode) && isset($body->quote_id) && isset($body->country_id);
    }

    public function action($body)
    {
        $this->quote->load($body->quote_id);
        $this->quote->setStore($this->store->getStore());

        $this->quote->getShippingAddress()->setCountryId($body->country_id);
        $this->quote->getShippingAddress()->setPostcode($body->postcode);
        $this->quote->getShippingAddress()->setCollectShippingRates(true);


        $this->quote->load($body->quote_id);
        $this->quote->collectTotals();
        $this->quote->save();

        $rates = $this->quote->getShippingAddress()->getGroupedAllShippingRates();
        $result = [];

        foreach ($rates as $code => $_rates){
            foreach ($_rates as $_rate){
                $result[] = [
                    'method' => $_rate->getMethodTitle() ?? null,
                    'value' => $_rate->getPrice() ?? null,
                    'code'    => $_rate->getCode() ?? null,
                    'carrier' => $_rate->getCarrierTitle() ?? null,
                    'error'   => $_rate->getErrorMessage() ?? null,
                    'order'   => $_rate->getRateId() ?? null
                ];
            }
        }

        return $result;
    }
}
