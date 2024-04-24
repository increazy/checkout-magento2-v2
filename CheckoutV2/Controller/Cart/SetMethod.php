<?php
namespace Increazy\CheckoutV2\Controller\Cart;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

class SetMethod extends Controller
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
        return isset($body->quote_id) && isset($body->method);
    }

    public function action($body)
    {
        $this->quote->load($body->quote_id);

        $this->quote->getPayment()->importData(['method' => 'increazy-'.$body->method]);

        $this->quote->load($body->quote_id);
        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }
}
