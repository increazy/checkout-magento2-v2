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

        $this->quote->load($body->quote_id);
        $this->address->load($body->address_id);

        // Remove o entity_id do customer_address para nao corromper a PK do quote_address
        // (customer_address_entity.entity_id != quote_address.address_id)
        $addressData = $this->address->getData();
        unset($addressData['entity_id']);

        // Usa instancias nativas separadas do quote para billing e shipping.
        // Passar a mesma instancia para ambos compartilha a referencia e corrompe os dados.
        $shippingAddress = $this->quote->getShippingAddress();
        $billingAddress = $this->quote->getBillingAddress();

        $shippingAddress->addData($addressData);
        $billingAddress->addData($addressData);

        // Forca os vinculos corretos para passar na validacao do QuoteAddressValidator
        $shippingAddress->setCustomerAddressId($body->address_id);
        $shippingAddress->setCustomerId($this->quote->getCustomerId());
        $billingAddress->setCustomerAddressId($body->address_id);
        $billingAddress->setCustomerId($this->quote->getCustomerId());

        $shippingAddress->setShippingMethod($body->shipping_method);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }

    private function actionFreteRapido($body)
    {
        $this->quote->load($body->quote_id);
        $this->quote->setStoreId($body->store);

        // Carrega o customer_address correto usando o address_id do body
        // (o codigo original carregava com o ID do quote_address, o que e invalido)
        $this->address->load($body->address_id);

        $addressData = $this->address->getData();
        unset($addressData['entity_id']);

        $shippingAddress = $this->quote->getShippingAddress();
        $billingAddress = $this->quote->getBillingAddress();

        $shippingAddress->addData($addressData);
        $billingAddress->addData($addressData);

        $shippingAddress->setCustomerAddressId($body->address_id);
        $shippingAddress->setCustomerId($this->quote->getCustomerId());
        $billingAddress->setCustomerAddressId($body->address_id);
        $billingAddress->setCustomerId($this->quote->getCustomerId());

        $this->quote->save();

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
