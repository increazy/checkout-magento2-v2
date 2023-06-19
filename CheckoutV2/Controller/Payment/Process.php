<?php
namespace Increazy\CheckoutV2\Controller\Payment;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class Process extends Controller
{
    /**
     * @var Order
     */
    private $order;

    private $orderSender;

    public function __construct(
        Context $context,
        Order $order,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->order = $order;
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->orderSender = $om->create(\Magento\Sales\Model\Order\Email\Sender\OrderSender::class);
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->payment_data) &&
            isset($body->order_id)
        ;
    }

    public function action($body)
    {
        $this->order->loadByIncrementId($body->order_id);
        $this->order->setEmailSent(0);
        $paymentData = json_decode(json_encode($body->payment_data), true);
        $this->order->getPayment()->setAdditionalInformation([
            'infos' => $paymentData
        ]);


        $this->orderSender->send($this->order);
        $this->order->setEmailSent(1);

        $this->order->getPayment()->save();
        $this->order->save();

        return array_merge($this->order->getData(), [
            'shipping'       => $this->order->getShippingAddress() ? $this->order->getShippingAddress()->getData() : $this->order->getBillingAddress()->getData(),
            'billing'        => $this->order->getBillingAddress()->getData(),
            'delivery_name'  => $this->order->getShippingDescription(),
            'delivery_price' => $this->order->getShippingAmount(),
            'discount'       => $this->order->getDiscountAmount(),
            'total'          => $this->order->getGrandTotal(),
            'tax'            => $this->order->getTaxAmount(),
            'payment_method' => [
                'additional_info' => $this->order->getPayment()->getAdditionalInformation(),
                'additional_data' => $this->order->getPayment()->getAdditionalData(),
                'method'          => $this->order->getPayment()->getMethod(),
            ],
        ]);
    }
}
