<?php
namespace Increazy\CheckoutV2\Controller\Coupon;

use Increazy\CheckoutV2\Controller\Controller;
use Increazy\CheckoutV2\Helpers\CompleteQuote;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Coupon;
use Magento\Store\Model\StoreManagerInterface;

class Add extends Controller
{
    /**
     * @var Quote
     */
    private $quote;
    /**
     * @var Coupon
     */
    private $coupon;

    public function __construct(
        Context $context,
        Quote $quote,
        Coupon $coupon,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->quote = $quote;
        $this->coupon = $coupon;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->coupon) && isset($body->quote_id);
    }

    public function action($body)
    {
        $this->quote->load($body->quote_id);
        $this->quote->setCouponCode($body->coupon);

        $this->quote->collectTotals()->save();

        return CompleteQuote::get($this->quote);
    }
}
