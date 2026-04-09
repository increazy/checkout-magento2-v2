<?php
namespace Increazy\CheckoutV2\Helpers;

use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote;

abstract class CompleteQuote
{
    public static function get(Quote $quote, $freteRapido = false)
    {
		$shippingAddress = $quote->getShippingAddress();
		$shippingAddress->setCollectShippingRates(true)->collectShippingRates();

        if ($freteRapido) {
            $quote->setTotalsCollectedFlag(true)->collectTotals();
            $quote->collectTotals();
            $quote->save();

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($quote->getId());
        } else {
            $quote->collectTotals();
            $quote->save();
        }

        $data = $quote->getData();

        return array_merge($data, [
            'totals'   => self::getTotals($quote),
            'shipping' => self::getShipping($quote, $freteRapido),
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

    private static function getShipping(Quote $quote, $freteRapido = false)
    {
		$address = $quote->getShippingAddress();
		$shippingMethod = $address->getShippingMethod();
		$shippingDescription = $address->getShippingDescription();

        if ($freteRapido) {
            $tax = $quote->getShippingAddress()->getShippingAmount();

            if ($quote->getShippingMethodIncreazy() == 'freterapido') {
                $rates = $quote->getShippingAddress()->getShippingRatesCollection();
                foreach ($rates as $rate) {
                    if ($rate->getCarrier() == 'freterapido') {
                        if (strpos($rate->getMethod(), '_' . $quote->getShippingMethodOptionIncreazy())) {
                            $shippingMethod = $rate->getCode();
                            $shippingDescription = $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle();
                            break;
                        }
                    }
                }
            }
        } else {
            $tax = $quote->getShippingAddress()->getBaseTaxAmount();
        }

		return [
			'method'	  => $shippingMethod,
			'description' => $shippingDescription,
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
				// Resolve the stock_id linked to the current website
				$storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
				$websiteCode = $storeManager->getWebsite()->getCode();
				$stockResolver = $objectManager->get('Magento\InventorySalesApi\Api\StockResolverInterface');
				$stockId = $stockResolver->execute('website', $websiteCode)->getStockId();

				$stock['stock_id'] = $stockId;

				// Get salable qty for this stock
				$StockState = $objectManager->get('\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku');
				$salableData = $StockState->execute($entity->getSku());
				$salable = 0;
				foreach ($salableData as $stockData) {
					if (isset($stockData['stock_id']) && (int)$stockData['stock_id'] === (int)$stockId) {
						$salable = $stockData['qty'];
						break;
					}
				}

				$stock['salable'] = $salable;
				$stock['is_in_stock'] = ($salable > 0) ? '1' : '0';

				// Get sources linked to this stock
				$sourcesAssigned = $objectManager->get('Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface');
				$sources = $sourcesAssigned->execute($stockId);

				$validSourceCodes = [];
				foreach ($sources as $source) {
					if ($source->isEnabled()) {
						$validSourceCodes[] = $source->getSourceCode();
					}
				}

				// Get source items for this SKU
				$sourceItemsBySku = $objectManager->get('Magento\InventoryApi\Api\GetSourceItemsBySkuInterface');
				$sourceItems = $sourceItemsBySku->execute($entity->getSku());

				$sourceQtys = [];
				foreach ($sourceItems as $sourceItem) {
					$sourceCode = $sourceItem->getSourceCode();
					if (in_array($sourceCode, $validSourceCodes)) {
						$sourceQtys[] = [
							'source_code' => $sourceCode,
							'qty' => (float)$sourceItem->getQuantity(),
							'status' => (int)$sourceItem->getStatus(),
						];
					}
				}

				$stock['qty'] = $salable;
				$stock['sources'] = $sourceQtys;
			} catch (\Exception $e) {
			} catch (\Error $e) {}
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
