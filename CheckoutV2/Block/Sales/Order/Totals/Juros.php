<?php

namespace Increazy\CheckoutV2\Block\Sales\Order\Totals;

use Magento\Sales\Model\Order;

/**
 * Class FinanceCost
 *
 * @package MercadoPago\Core\Block\Sales\Order\Totals
 */
class Juros extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Framework\DataObject
     */
    protected $_source;

    /**
     * Get data (totals) source model
     *
     * @return \Magento\Framework\DataObject
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    /**
     * Add this total to parent
     */
    public function initTotals()
    {
        if ((float)$this->getSource()->getData('increazy_juros') == 0) {
            return $this;
        }
        $total = new \Magento\Framework\DataObject([
            'code' => 'juros_increazy',
            'field' => 'juros_increazy',
            'value' => $this->getSource()->getData('increazy_juros'),
            'label' => __('Juros'),
        ]);
        
        $this->getParentBlock()->addTotalBefore($total, 'shipping');

        return $this;
    }
}
