<?php
namespace Increazy\CheckoutV2\Controller\Payment;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
// use Magento\Framework\Registry;

class Cancel extends Controller
{
    /**
     * @var Order
     */
    private $order;
    // /**
    //  * @var Registry
    //  */
    // private $registry;


    public function __construct(
        Context $context,
        Order $order,
        // Registry $registry,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->order = $order;
        // $this->registry = $registry;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->order_id);
    }

    public function action($body)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $objectManager->get('Magento\Framework\Registry')->register('isSecureArea', true);
        $this->order->loadByIncrementId($body->order_id);

        if (stripos($this->order->getPayment()->getMethod(), 'increazy') !== false) {
            $infos = $this->order->getPayment()->getAdditionalInformation();
            if (!isset($infos['infos']['pay_method'])) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();


                if ($code = $this->order->getCouponCode()) {
                    $couponModel = $objectManager->get('Magento\SalesRule\Model\Coupon');
                    $coupon = $couponModel->loadByCode($code);
                    $coupon->setTimesUsed($coupon->getTimesUsed() - 1);
                    $coupon->save();

                    if ($customerId = $this->order->getCustomerId()) {
                        $rule = $objectManager->get('Magento\SalesRule\Model\Rule');


                        if ($customerCoupon = $rule->load($coupon->getRuleId())) {
                            $customerCoupon->setTimesUsed($customerCoupon->getTimesUsed() - 1);
                            $customerCoupon->save();
                        }

                        $usageCustomer = $objectManager->get('Magento\SalesRule\Model\ResourceModel\Coupon\Usage');
                        $usageCustomer->updateCustomerCouponTimesUsed($customerId, $coupon->getId(), false);

                        $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($this->order->getQuoteId());
                        $quote->setCouponCode($code);
                        $quote->collectTotals()->save();
                    }
                }


                // $this->registry->register('isSecureArea','true');
                
                //$this->order->delete();
                // $this->registry->unregister('isSecureArea');
            }
            
            $this->order->cancel()->save();
        }

        $objectManager->get('Magento\Framework\Registry')->unregister('isSecureArea');

        return [
            'success' => true,
        ];
    }
}
