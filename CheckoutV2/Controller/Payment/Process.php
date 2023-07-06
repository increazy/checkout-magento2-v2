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
    private $invoiceSender;

    private $transaction;

    public function __construct(
        Context $context,
        Order $order,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->invoiceService = $om->create(\Magento\Sales\Model\Service\InvoiceService::class);
        $this->transaction = $om->create(\Magento\Framework\DB\Transaction::class);
        $this->order = $order;
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

        $status = $body->payment_data->status ?? '';
        if ($status == 'success') {
            if ($this->order->canUnhold()) {
                $this->order->unhold();
            }

            if ($this->order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($this->order);
                $invoice->register();
                $invoice->save();

                $transactionSave = $this->transaction->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->invoiceSender->send($invoice);

                $this->order
                    ->addStatusHistoryComment('Pagamento confirmado')
                ->setIsCustomerNotified(true);

                $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $this->order->setState($state)->setStatus($state);
            }
        }

        if ($status == 'canceled') {
            if ($this->order->canUnhold()) {
                $this->order->unhold();
            }

            if ($this->order->canCancel()) {
                $this->order->cancel();
            }
            $state = \Magento\Sales\Model\Order::STATE_CANCELED;
            $this->order->setState($state)->setStatus($state);
        }
        
        $this->order->save();

        return $this->order->getData();
    }
}
