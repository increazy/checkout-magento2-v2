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
						'_edit' => 1,
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

		foreach ($items as $item) {
			$result[] = (object)[
				'item_id'         => $item->getId(),
                'product_id'      => $item->getProduct()->getId(),
                'sku'			  => $item->getProduct()->getSku(),
                'name'		      => $item->getProduct()->getName(),
                'url'		      => $item->getProduct()->getUrlPath(),
                'image'	          => self::getProductImage($item->getProduct()),
                'thumbnail'		  => $item->getProduct()->getThumbnail(),
				'price'			  => $item->getPrice(),
				'discount_amount' => $item->getDiscountAmount(),
				'qty'			  => $item->getQty(),
				'total'			  => ($item->getRowTotal() - $item->getDiscountAmount()),
				'options'		  => self::getOptions($item),
			];
		}

		return $result;
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
