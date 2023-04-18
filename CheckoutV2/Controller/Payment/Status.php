<?php
namespace Increazy\CheckoutV2\Controller\Payment;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\DB\Transaction;

class Status extends Controller
{
    /**
     * @var InvoiceSender
     */
    private $invoiceService;
    /**
     * @var CollectionFactory
     */
    private $orderCollectionFactory;
    /**
     * @var Order
     */
    private $orderModel;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct(
        Context $context,
        InvoiceService $invoiceService,
        CollectionFactory $orderCollectionFactory,
        Order $orderModel,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->invoiceService = $invoiceService;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderModel = $orderModel;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->order) && isset($body->status) &&
            isset($body->gateway) && isset($body->method)
        ;
    }

    public function action($body)
    {
        $collection = $this->orderCollectionFactory->create()
            ->addAttributeToSelect('entity_id')
        ->addFieldToFilter('increment_id', $body->conversion->external_order);


        $order = $collection->getFirstItem();
        $order = $this->orderModel->load($order->getId());

        switch ($body->status) {
            case 'waiting':
//                 if ($order->canHold()) {
//                     $order->hold();
//                 }
                $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($state)->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
                break;

            case 'validate':
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($state)->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
                break;

            case 'error':
            case 'canceled':
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                if ($order->canCancel()) {
                    $order->cancel();
                }
                $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                $order->setState($state)->setStatus($state);
                break;

            case 'success':
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                if ($order->canInvoice()) {
                    $invoice = $this->invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();

                    $transactionSave = $this->transaction->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();

                    $this->invoiceSender->send($invoice);

                    $order
                        ->addStatusHistoryComment('Pagamento confirmado')
                    ->setIsCustomerNotified(true);

                    $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $order->setState($state)->setStatus($state);
                }
                break;
        }

        $order->save();

        return $order->getData();
    }
}
