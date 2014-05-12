<?php

namespace Message\Mothership\OrderReturn\Factory;

use LogicException;
use InvalidArgumentException;
use Message\Mothership\OrderReturn\Collection;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Entity\OrderReturnItem;
use Message\Mothership\Commerce\Product\Unit\Unit as ProductUnit;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Order\Entity\Note\Note as OrderNote;

/**
 * Factory for creating returns.
 *
 * @todo Should this be renamed to Assembler since it's not technically a
 *       factory and has no create() method?
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Factory
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
	 * @param  OrderItem $item
	 * @return Factory
	 */
	public function setReturnItemFromOrderItem(OrderItem $item)
	{
		$returnItem = new OrderReturnItem;

		$returnItem->order = $item->order;
		$returnItem->orderItem = $item;

		// @todo Verify how this value should be calculated
		$returnItem->returnedValue = $item->gross;

		$returnItem->calculatedBalance = 0 - $item->gross;

		$this->_returnItem = $returnItem;
		$this->_return->item = $returnItem;

		return $this;
	}

	/**
	 * Set the return item from a ProductUnit.
	 *
	 * @param  ProductUnit $unit
	 * @return Factory
	 */
	public function setReturnItemFromProductUnit(ProductUnit $unit)
	{
		$returnItem = new OrderReturnItem;

		// @todo Get the correct currency id from somewhere
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
			throw new LogicException("You must first call setReturnItem() before setExchangeItem()");
		}

		// @todo Add standalone logic for creating the order

		$item = new OrderItem;
		$item->populate($unit);

		$item->status = null;
		$item->stockLocation = null;

		// @todo Append exchange item to order

		// @todo Create exchange item?

		$balance = 0 - ($item->gross - $this->_returnItem->calculatedBalance);

		$this->_returnItem->calculatedBalance = $balance;

		$this->_exchangeItem = $exchangeItem;
		$this->_returnItem->exchangeItem = $exchangeItem;

		return $this;
	}
}