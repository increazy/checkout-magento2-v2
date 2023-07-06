<?php
namespace Increazy\CheckoutV2\Controller\Payment;

use Increazy\CheckoutV2\Controller\Controller;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class Finish extends Controller
{
    /**
     * @var Quote
     */
    private $quote;
    /**
     * @var QuoteManagement
     */
    private $quoteManagement;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customer;

    private $invoiceSender;
    private $transaction;

    public function __construct(
        Context $context,
        QuoteManagement $quoteManagement,
        CustomerRepositoryInterface $customer,
        Quote $quote,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->invoiceService = $om->create(\Magento\Sales\Model\Service\InvoiceService::class);
        $this->transaction = $om->create(\Magento\Framework\DB\Transaction::class);
        
        $this->quoteManagement = $quoteManagement;
        $this->quote = $quote;
        $this->customer = $customer;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->token) && isset($body->payment_data) &&
            isset($body->quote_id) && isset($body->tax)
        ;
    }

    public function action($body)
    {
        $customerId = $this->hashDecode($body->token);
        $customer = $this->customer->getById($customerId);
        $this->quote->load($body->quote_id);

        $this->quote->assignCustomer($customer);
        $this->quote->setCustomerIsGuest(false);
        $this->quote->setPaymentMethod($body->payment_data->method);
        $this->quote->setInventoryProcessed(false);
        $this->quote->setCanSendNewEmailFlag(false);
        $this->quote->save();

        $paymentData = json_decode(json_encode($body->payment_data), true);
        $this->quote->getPayment()->importData($paymentData);
        $this->quote->collectTotals()->save();

        $order = $this->quoteManagement->submit($this->quote);
        $order->setState(Order::STATE_NEW);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        
        if ($body->tax < 0) {
            $order->setDiscountTaxCompensationAmount($body->tax);
            $order->setBaseDiscountTaxCompensationAmount($body->tax);

            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $body->tax);
            $order->setTotalDue($order->getTotalDue() + $body->tax);
            $order->setGrandTotal($order->getGrandTotal() + $body->tax);
            $order->setBaseTotalDue($order->getBaseTotalDue() + $body->tax);
        } else if ($body->tax > 0) {
            $order->setTaxAmount($order->getTaxAmount() + $body->tax);
            
            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $body->tax);
            $order->setTotalDue($order->getTotalDue() + $body->tax);
            $order->setGrandTotal($order->getGrandTotal() + $body->tax);
            $order->setBaseTotalDue($order->getBaseTotalDue() + $body->tax);
        }

        try {
            if ($body->payment_data->method == 'increazy-free') {
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                if ($order->canInvoice()) {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $invoiceService = $objectManager->get('Magento\Sales\Model\Service\InvoiceService');

                    $invoice = $invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();

                    $transaction = $objectManager->get('Magento\Framework\DB\Transaction');
                    $transactionSave = $transaction->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();

                    $invoiceSender = $objectManager->create('Magento\Sales\Model\Order\Email\Sender\InvoiceSender');

                    $invoiceSender->send($invoice);

                    $order
                        ->addStatusHistoryComment('Pagamento confirmado')
                    ->setIsCustomerNotified(true);

                    $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $order->setState($state)->setStatus($state);
                }
            }

            if ($body->payment_data->status == 'success') {
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
            }

            if ($body->payment_data->status == 'canceled') {
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                if ($order->canCancel()) {
                    $order->cancel();
                }
                $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                $order->setState($state)->setStatus($state);
            }

            $order->save();
            
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $oM = \Magento\Framework\App\ObjectManager::getInstance();
        $oM->create('Magento\Sales\Model\Order\Email\Sender\OrderSender')->send($order, true);
        $order->setEmailSent(1);

        $order->save();

        return $order->getData();

        // return [
        //     'increment_id-0' => $order->getRealOrderId(),
        // ];
    }
}
