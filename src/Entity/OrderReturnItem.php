<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order;

use Message\Mothership\OrderReturn\Statuses;

class OrderReturnItem
{
	public $id;
	public $returnID;
	public $orderID;
	public $orderItemID;
	public $exchangeItemID;
	public $noteID;

	// Related entities
	public $order;
	public $orderItem;
	public $exchangeItem;
	public $note;
	public $document;
	public $returnedStockLocation;
	public $returnedStock;

	// Return Item
	public $authorship;
	public $status;
	public $reason;
	public $accepted;
	public $balance;
	public $remainingBalance;
	public $calculatedBalance;
	public $returnedValue;

	// Order Item
	public $listPrice       = 0;
	public $actualPrice     = 0;
	public $net             = 0;
	public $discount        = 0;
	public $tax             = 0;
	public $gross           = 0;
	public $rrp             = 0;
	public $taxRate         = 0;
	public $productTaxRate  = 0;
	public $taxStrategy;
	public $taxes;

	// Order Item Product
	public $productID;
	public $productName;
	public $unit;
	public $unitID;
	public $unitRevision;
	public $sku;
	public $barcode;
	public $options;
	public $brand;

	public function __construct()
	{
		$this->authorship = new Authorship();
		$this->authorship->disableDelete();
	}

	/**
	 * Get the item description.
	 *
	 * The item description is made up of the brand name; the product name and
	 * the list of options. They are comma-separated, and if any of them are
	 * not set or blank they are excluded.
	 *
	 * @return string The item description
	 */
	public function getDescription()
	{
		return implode(', ', array_filter(array(
			$this->brand,
			$this->productName,
			$this->options,
		)));
	}

	public function isReceived()
	{
		return $this->status->code >= Statuses::RETURN_RECEIVED;
	}

	public function isAccepted()
	{
		return $this->accepted == true;
	}

	public function isRejected()
	{
		return $this->accepted === false;
	}

	public function isRefundResolution()
	{
		return null === $this->exchangeItemID && null === $this->exchangeItem;
	}

	public function isExchangeResolution()
	{
		return !$this->isRefundResolution();
	}

	public function hasBalance()
	{
		return $this->balance !== null;
	}

	public function hasCalculatedBalance()
	{
		return $this->calculatedBalance != 0;
	}

	public function hasRemainingBalance()
	{
		return null !== $this->remainingBalance and $this->remainingBalance != 0;
	}

	/**
	 * If the balance is owed by the retailer to be paid to the customer.
	 *
	 * @return bool
	 */
	public function payeeIsCustomer()
	{
		return $this->hasBalance() ? $this->balance < 0 : $this->calculatedBalance < 0;
	}

	/**
	 * If the balance is owed by the customer to be paid to the retailer.
	 *
	 * @return bool
	 */
	public function payeeIsRetailer()
	{
		return $this->hasBalance() ? $this->balance > 0 : $this->calculatedBalance > 0;
	}

	public function isExchanged()
	{
		return $this->isExchangeResolution() ?
			$this->exchangeItem->status->code >= Order\Statuses::AWAITING_DISPATCH : false;
	}

	public function isReturnedItemProcessed()
	{
		return $this->returnedStock;
	}
}