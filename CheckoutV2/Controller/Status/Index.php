<?php
namespace Increazy\CheckoutV2\Controller\Status;

class Index extends \Magento\Framework\App\Action\Action
{
    private $orderCollectionFactory;
    private $orderModel;
    private $invoiceService;
    private $invoiceSender;
    private $transaction;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\Order $orderModel,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->invoiceService = $invoiceService;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderModel = $orderModel;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getParam('body');

        if (!$data) {
            $data = file_get_contents("php://input");
        }

        $data = json_decode($data);

        if ($data->order) {
            $collection = $this->orderCollectionFactory->create()
                ->addAttributeToSelect('entity_id')
                ->addFieldToFilter('increment_id', $data->conversion->external_order);

            $orderId = $collection->getFirstItem();

            if ($orderId->getId()) {
                $order = $this->orderModel->load($orderId->getId());
                switch ($data->status) {
                    case 'validate':
                        if ($order->canUnhold()) {
                            $order->unhold();
                        }

                        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                        $order->setState($state)->setStatus($state);
                        break;
                    case 'error':
                    case 'canceled':
                        if ($order->canUnhold()) {
                            $order->unhold();
                        }

                        if ($order->canCancel()) {
                            $order->cancel();
                        }
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

                            $order->addStatusHistoryComment('Pagamento confirmado')
                                ->setIsCustomerNotified(true);

                            $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                            $order->setState($state)->setStatus($state);
                        }
                        break;
                }

                $order->save();
            }
        }
	exit();
    }
}
