<?php

namespace Increazy\CheckoutV2\Model\Total\Invoice;

/**
 * Class FinanceCost
 *
 * @package MercadoPago\Core\Model\Invoice
 */
class Juros extends \Magento\Sales\Model\Order\Total\AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $amount = $order->getIncreazyJuros();
        $baseAmount = $order->getBaseIncreazyJuros();
        if ($amount) {
            $invoice->setIncreazyJuros($amount);
            $invoice->setBaseIncreazyJuros($baseAmount);
            $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);
        }

        return $this;
    }
}
