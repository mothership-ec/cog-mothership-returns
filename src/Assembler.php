<?php

namespace Message\Mothership\OrderReturn;

use LogicException;
use InvalidArgumentException;
use Message\Mothership\OrderReturn\Collection;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Entity\OrderReturnItem;
use Message\Mothership\Commerce\Product\Unit\Unit as ProductUnit;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Order\Entity\Note\Note as OrderNote;

/**
 * Assembler for creating returns.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Assembler
{
	const NOTE_RAISED_FROM_RETURN = 'return';

	/**
	 * The return instance being assembled.
	 *
	 * @var OrderReturn
	 */
	protected $_return;

	/**
	 * The return item instance being added to the return.
	 *
	 * @var OrderReturnItem
	 */
	protected $_returnItem;

	/**
	 * The exchange item instance being added to the return item.
	 *
	 * @var OrderItem
	 */
	protected $_exchangeItem;

	/**
	 * Set the return to use in the factory.
	 *
	 * @param  OrderReturn $return
	 * @return Factory
	 */
	public function setReturn(OrderReturn $return)
	{
		$this->_return = $return;

		return $this;
	}

	/**
	 * Get the return being built.
	 *
	 * @return Factory
	 */
	public function getReturn()
	{
		return $this->_return;
	}

	/**
	 * Set the return item from either an OrderItem or ProductUnit.
	 *
	 * @param  OrderItem|ProductUnit $item
	 * @return Factory
	 */
	public function setReturnItem($item)
	{
		$isOrderItem   = ($item instanceof OrderItem);
		$isProductUnit = ($item instanceof ProductUnit);

		if (! $isOrderItem and ! $isProductUnit) {
			throw new InvalidArgumentException("You can only set a return item from an OrderItem or ProductUnit");
		}

		if ($isOrderItem) {
			$this->setReturnItemFromOrderItem($item);
		} elseif ($isProductUnit) {
			$this->setReturnItemFromProductUnit($item);
		}

		return $this;
	}

	/**
	 * Set the return item from an OrderItem.
	 *
	 * @todo   Verify how the returnedValue should be calculated.
	 *
	 * @param  OrderItem $item
	 * @return Factory
	 */
	public function setReturnItemFromOrderItem(OrderItem $item)
	{
		$returnItem = new OrderReturnItem;

		$returnItem->order = $item->order;
		$returnItem->orderItem = $item;

		$returnItem->returnedValue = $item->gross;
		$returnItem->calculatedBalance = 0 - $item->gross;

		$this->_returnItem = $returnItem;
		$this->_return->item = $returnItem;

		return $this;
	}

	/**
	 * Set the return item from a ProductUnit.
	 *
	 * @todo   Get the correct currency id from somewhere.
	 *
	 * @param  ProductUnit $unit
	 * @return Factory
	 */
	public function setReturnItemFromProductUnit(ProductUnit $unit)
	{
		$returnItem = new OrderReturnItem;

		$currencyID = null;

		$returnItem->listPrice         = $unit->getPrice('retail', $currencyID);
		$returnItem->rrp               = $unit->getPrice('rrp', $currencyID);

		$returnItem->productTaxRate    = (float) $unit->product->taxRate;
		$returnItem->taxStrategy       = $unit->product->taxStrategy;
		$returnItem->productID         = $unit->product->id;
		$returnItem->productName       = $unit->product->name;
		$returnItem->unitID            = $unit->id;
		$returnItem->unitRevision      = $unit->revisionID;
		$returnItem->sku               = $unit->sku;
		$returnItem->barcode           = $unit->barcode;
		$returnItem->options           = implode($unit->options, ', ');
		$returnItem->brand             = $unit->product->brand;
		$returnItem->weight            = (int) $unit->weight;

		$returnItem->returnedValue     = null;
		$returnItem->calculatedBalance = null;

		$this->_returnItem = $returnItem;
		$this->_return->item = $returnItem;

		return $this;
	}

	/**
	 * Set the reason for the return onto the return item.
	 *
	 * @param  Collection\Item $reason
	 * @return Factory
	 */
	public function setReason(Collection\Item $reason)
	{
		if (! $this->_returnItem) {
			throw new LogicException("You can not set a reason without having previously set a return item");
		}

		$this->_returnItem->reason = $reason;

		return $this;
	}

	/**
	 * Set the note for the return. Attach the order if this is not a
	 * standalone return and apply the default values.
	 *
	 * @param  OrderNote $note
	 * @return Factory
	 */
	public function setNote(OrderNote $note)
	{
		if ($this->_returnItem->order) {
			$note->order = $this->_returnItem->order;
		}

		if (! $note->raisedFrom) {
			$note->raisedFrom = static::NOTE_RAISED_FROM_RETURN;
		}

		if (null === $note->customerNotified) {
			$note->customerNotified = 0;
		}

		$this->_returnItem->note = $note;

		return $this;
	}

	/**
	 * Set the exchange item from a ProductUnit.
	 *
	 * @param  ProductUnit $unit
	 * @return Factory
	 */
	public function setExchangeItem(ProductUnit $unit)
	{
		if (! $this->_returnItem) {
			throw new LogicException("You can not set the exchange item without having previously set the return item");
		}

		$item = new OrderItem;

		if ($this->_returnItem->order) {
			$this->_returnItem->order->append($item);
		}

		$item->populate($unit);

		$item->status = null;
		$item->stockLocation = null;

		$balance = 0 - ($item->gross - $this->_returnItem->calculatedBalance);

		$this->_returnItem->calculatedBalance = $balance;

		$this->_exchangeItem = $item;
		$this->_returnItem->exchangeItem = $item;

		return $this;
	}
}