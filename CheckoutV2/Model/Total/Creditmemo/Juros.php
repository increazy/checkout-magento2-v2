<?php

namespace Increazy\CheckoutV2\Model\Total\Creditmemo;

/**
 * Class FinanceCost
 *
 * @package MercadoPago\Core\Model\Creditmemo
 */
class Juros extends \Magento\Sales\Model\Order\Total\AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $amount = $order->getIncreazyJuros();
        $baseAmount = $order->getBaseIncreazyJuros();
        if ($amount) {
            $creditmemo->setIncreazyJuros($amount);
            $creditmemo->setBaseIncreazyJuros($baseAmount);
            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $amount);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseAmount);
        }

        return $this;
    }
}
