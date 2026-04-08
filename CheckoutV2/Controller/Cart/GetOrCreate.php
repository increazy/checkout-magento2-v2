<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Checkout\Model\Session;

class GetOrCreate extends Controller
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;
    /**
     * @var Quote
     */
    private $quote;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customer;

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
        QuoteFactory $quoteFactory,
        Quote $quote,
        CustomerRepositoryInterface $customer,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig,
        Rate $shippingRate,
        Session $checkoutSession
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quote = $quote;
        $this->customer = $customer;
        $this->shippingRate = $shippingRate;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return true;
    }

    public function action($body)
    {
        $quote = null;

        if (($body->quote_id ?? '') !== '') {
         $this->quote->load($body->quote_id);
         $quote = $this->quote;
        } else {
            $quote = $this->quoteFactory->create();
        }

        if ($this->isFreteRapidoEnabled()) {
            if ($this->quote->getShippingMethodIncreazy() == 'freterapido') {
                $rates = $quote->getShippingAddress()->getShippingRatesCollection();
                $forceMethod = false;

                foreach ($rates as $rate) {
                    if ($rate->getCarrier() == 'freterapido') {
                        if (strpos($rate->getMethod(), '_' . $this->quote->getShippingMethodOptionIncreazy())) {
                            $forceMethod = $rate->getCode();
                            break;
                        }
                    }
                }

                if ($forceMethod)
                    $this->forcerRate($forceMethod);
            }

            if (is_null($quote->getId()))
                $quote = $this->quoteFactory->create();
        }

        if (($body->token ?? '') !== '') {
            $customerId = $this->hashDecode($body->token);
            if ($customerId) {
                $customer = $this->customer->getById($customerId);
                $quote->assignCustomer($customer);

                try {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($customerId);
                    $customerSession = $objectManager->create('Magento\Customer\Model\Session');
                    $customerSession->setCustomerAsLoggedIn($customer);
                } catch (\Exception $e) {
                    // Race condition: o LoadCustomerQuoteObserver pode falhar se o quote
                    // foi convertido em order por outro request concorrente.
                    // O assignCustomer() acima ja associou o customer ao quote.
                }
            }
        }

        $quote->setStoreId($body->store);

        if ($this->isFreteRapidoEnabled()) {
            if (class_exists("\\Amasty\\Promo\\Model\\Storage")) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $promoStorage = $objectManager->get(\Amasty\Promo\Model\Storage::class);
                $promoStorage->restrictQuoteSaving(true);
            }

            $quote->setTotalsCollected(false)
                ->collectTotals()->save();

            return CompleteQuote::get($this->quote->load($quote->getId()), true);
        }

        $quote->collectTotals()->save();

        return CompleteQuote::get($quote);
    }

    private function forcerRate($shippingMethod)
    {
        $this->shippingRate
            ->setCode($shippingMethod)
            ->getPrice(1);

        $shippingAddress = $this->quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingMethod);
        $this->quote->getShippingAddress()->addShippingRate($this->shippingRate);
        $this->quote->save();
    }
}
