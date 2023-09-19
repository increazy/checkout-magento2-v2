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


class Prepare extends Controller
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

    public function __construct(
        Context $context,
        QuoteManagement $quoteManagement,
        CustomerRepositoryInterface $customer,
        Quote $quote,
        StoreManagerInterface $store,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->quoteManagement = $quoteManagement;
        $this->quote = $quote;
        $this->customer = $customer;
        parent::__construct($context, $store, $scopeConfig);
    }

    public function validate($body)
    {
        return isset($body->token) && isset($body->payment_method) &&
            isset($body->quote_id) && isset($body->tax)
        ;
    }

    public function action($body)
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $customerId = $this->hashDecode($body->token);
        $customer = $this->customer->getById($customerId);
        $this->quote->load($body->quote_id);

        $this->quote->assignCustomer($customer);
        $this->quote->setPaymentMethod($body->payment_method);
        $this->quote->setInventoryProcessed(false);
        $this->quote->save();

        $this->quote->getPayment()->importData([
            'method' => $body->payment_method
        ]);
        $this->quote->collectTotals()->save();

        $order = $this->quoteManagement->submit($this->quote);
        $order->setState(Order::STATE_NEW);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);


        try {
            if ($body->payment_method == 'increazy-free') {
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
                    $oM = \Magento\Framework\App\ObjectManager::getInstance();
                    $oM->create('Magento\Sales\Model\Order\Email\Sender\OrderSender')->send($order, true);
                    $order->setEmailSent(1);
                }
            }
            
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        if ($body->tax < 0) {
            $order->setDiscountTaxCompensationAmount($body->tax);
            $order->setBaseDiscountTaxCompensationAmount($body->tax);

            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $body->tax);
            $order->setTotalDue($order->getTotalDue() + $body->tax);
            $order->setGrandTotal($order->getGrandTotal() + $body->tax);
            $order->setBaseTotalDue($order->getBaseTotalDue() + $body->tax);
        } else if ($body->tax > 0) {
            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $body->tax);
            $order->setTotalDue($order->getTotalDue() + $body->tax);
            $order->setGrandTotal($order->getGrandTotal() + $body->tax);
            $order->setBaseTotalDue($order->getBaseTotalDue() + $body->tax);
        }

        $order->save();
        return [
            'increment_id' => $order->getRealOrderId(),
        ];
    }
}
