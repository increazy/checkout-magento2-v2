<?php
namespace Increazy\CheckoutV2\Helpers;

use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote;

abstract class CompleteQuote
{
    public static function get(Quote $quote)
    {
		$shippingAddress = $quote->getShippingAddress();
		$shippingAddress->setCollectShippingRates(true)->collectShippingRates();
		$quote->setTotalsCollectedFlag(true)->collectTotals();
		$quote->collectTotals();
		$quote->save();

		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$quote = $objectManager->get('Magento\Quote\Model\Quote')->load($quote->getId());
        $data = $quote->getData();

        return array_merge($data, [
            'totals'   => self::getTotals($quote),
            'shipping' => self::getShipping($quote),
            'items'    => self::getItems($quote),
        ]);
    }

    private static function getTotals(Quote $quote)
    {
		$subtotal = $quote->getSubtotal();
		$subtotalWithDiscount = $quote->getSubtotalWithDiscount();
		$discountAmount = $subtotal - $subtotalWithDiscount;

		$grandTotal = $quote->getGrandTotal();
		$shippingAmount = $quote->getShippingAddress()->getShippingAmount();

		return [
			'subtotal' => $subtotal,
			'discount' => $discountAmount,
			'shipping' => $shippingAmount,
			'total'	   => $grandTotal,
		];
	}

    private static function getShipping(Quote $quote) {
		$address = $quote->getShippingAddress();
		$tax = $quote->getShippingAddress()->getBaseTaxAmount();

		return [
			'method'	  => $address->getShippingMethod(),
			'description' => $address->getShippingDescription(),
			'tax' 		  => $tax,
		];
	}

    private static function getItems(Quote $quote) {
		$items = $quote->getAllVisibleItems();
		$result = [];

		$quoteRate = $quote->getData('base_to_quote_rate');

		foreach ($items as $item) {
			$result[] = (object)[
				'item_id'         => $item->getId(),
                'product_id'      => $item->getProduct()->getId(),
                'sku'			  => $item->getProduct()->getSku(),
                'name'		      => $item->getProduct()->getName(),
                'url'		      => $item->getProduct()->getUrlKey(),
                'image'	          => self::getProductImage($item->getProduct()),
                'thumbnail'		  => $item->getProduct()->getThumbnail(),
				'price'			  => $item->getPrice() * $quoteRate,
				'stock'			  => self::getStock($item->getProduct()),
				'discount_amount' => $item->getDiscountAmount(),
				'qty'			  => $item->getQty(),
				'total'			  => ($item->getRowTotal() - $item->getDiscountAmount()) * $quoteRate,
				'options'		  => self::getOptions($item),
			];
		}

		return $result;
	}

	private static function getStock($entity)
    {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $stockItem = $objectManager->get('Magento\CatalogInventory\Model\Stock\Item')->load($entity->getId(), 'product_id');

		$stock = $stockItem->getData();
		$stock['salable'] = $stock['qty'];

		if (class_exists('\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku')) {
			try {
				$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				$StockState = $objectManager->get('\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku');
				$qty = $StockState->execute($entity->getSku());

				if (count($qty) > 0) {
					$stock['salable'] = $qty[0]['qty'] ?? $stock['qty'];
				}
			} catch(\Error $e) {}
		}

        return $stock;
    }

    private static function getOptions($item) {
		return $item->getProduct()
            ->getTypeInstance(true)
        ->getOrderOptions($item->getProduct());
	}

    public static function getProductImage ($product)
    {
        ObjectManager::getInstance()
            ->get("Magento\Catalog\Model\Product\Gallery\ReadHandler")
        ->execute($product);

        $images = $product->getMediaGalleryImages();
        foreach ($images as $image) {
            return $image->getUrl();
        }
    }

}
