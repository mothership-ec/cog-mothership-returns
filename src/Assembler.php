<?php

namespace Message\Mothership\OrderReturn;

use LogicException;
use InvalidArgumentException;

use Message\Mothership\Commerce\Refund\Refund;
use Message\Mothership\Commerce\Payment\Payment;
use Message\Mothership\Commerce\OrderItemStatuses;
use Message\Mothership\Commerce\Product\Unit\Unit as ProductUnit;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Order\Entity\Note\Note as OrderNote;
use Message\Mothership\Commerce\Order\Status\Collection as StatusCollection;
use Message\Mothership\Commerce\Product\Stock\Location\Location as StockLocation;

use Message\Mothership\OrderReturn\ReturnStatuses;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Entity\OrderReturnItem;

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
	 * The list of payments made by the customer on this return.
	 *
	 * @var Payment
	 */
	protected $_payments;

	/**
	 * The list of refunds made by the seller on this return.
	 *
	 * @var Refund
	 */
	protected $_refunds;

	public function __construct(StatusCollection $statuses)
	{
		$this->_statuses = $statuses;
	}

	/**
	 * Set the return to use in the factory.
	 *
	 * @param  OrderReturn $return
	 * @return Assembler
	 */
	public function setReturn(OrderReturn $return)
	{
		$this->_return = $return;

		return $this;
	}

	/**
	 * Get the return being built.
	 *
	 * @return Assembler
	 */
	public function getReturn()
	{
		return $this->_return;
	}

	/**
	 * Set the return item from either an OrderItem or ProductUnit.
	 *
	 * @param  OrderItem|ProductUnit $item
	 * @return Assembler
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
	 * @return Assembler
	 */
	public function setReturnItemFromOrderItem(OrderItem $item)
	{
		$this->_return->item = $returnItem = new OrderReturnItem;

		$returnItem->order = $item->order;
		$returnItem->orderItem = $item;

		$returnItem->returnedValue = $item->gross;
		$returnItem->calculatedBalance = 0 - $item->gross;

		return $this;
	}

	/**
	 * Set the return item from a ProductUnit.
	 *
	 * @todo   Get the correct currency id from somewhere.
	 *
	 * @param  ProductUnit $unit
	 * @return Assembler
	 */
	public function setReturnItemFromProductUnit(ProductUnit $unit)
	{
		$this->_return->item = $returnItem = new OrderReturnItem;

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

		return $this;
	}

	/**
	 * Set the reason for the return onto the return item.
	 *
	 * @param  Collection\Item $reason
	 * @return Assembler
	 */
	public function setReason(Collection\Item $reason)
	{
		if (! $this->_return->item) {
			throw new LogicException("You can not set a reason without having previously set a return item");
		}

		$this->_return->item->reason = $reason;

		return $this;
	}

	/**
	 * Set the note for the return. Attach the order if this is not a
	 * standalone return and apply the default values.
	 *
	 * @param  OrderNote $note
	 * @return Assembler
	 */
	public function setNote(OrderNote $note)
	{
		if ($this->_return->item->order) {
			$note->order = $this->_return->item->order;
		}

		if (! $note->raisedFrom) {
			$note->raisedFrom = static::NOTE_RAISED_FROM_RETURN;
		}

		if (null === $note->customerNotified) {
			$note->customerNotified = 0;
		}

		$this->_return->item->note = $note;

		return $this;
	}

	public function setReturnedStockLocation(StockLocation $location)
	{
		$this->_return->item->returnedStockLocation = $location;

		return $this;
	}

	/**
	 * Set the exchange item from a ProductUnit.
	 *
	 * @param  ProductUnit $unit
	 * @return Assembler
	 */
	public function setExchangeItem(ProductUnit $unit, StockLocation $stockLocation = null)
	{
		if (! $this->_return->item) {
			throw new LogicException("You can not set the exchange item without having previously set the return item");
		}

		$this->_return->item->exchangeItem = $item = new OrderItem;

		if ($this->_return->item->order) {
			$this->_return->item->order->append($item);
		}

		$item->populate($unit);

		$item->stockLocation = $stockLocation;

		$balance = 0 - ($item->gross - $this->_return->item->calculatedBalance);

		$this->_return->item->calculatedBalance = $balance;

		return $this;
	}

	/**
	 * Add a payment to the return.
	 *
	 * @param  Payment $payment
	 * @return Assembler
	 */
	public function addPayment(Payment $payment)
	{
		$this->_payments[] = $payment;

		return $this;
	}

	/**
	 * Clear out and reset the payments to a given list.
	 *
	 * @param  array[Payment] $payments
	 * @return Assembler
	 */
	public function setPayments(array $payments)
	{
		$this->clearPayments();

		foreach ($payments as $payment) {
			$this->addPayment($payment);
		}

		return $this;
	}

	/**
	 * Clear out the list of payments.
	 *
	 * @return Assembler
	 */
	public function clearPayments()
	{
		$this->_payments = [];

		return $this;
	}

	/**
	 * Add a refund to the return.
	 *
	 * @param  Refund $refund
	 * @return Assembler
	 */
	public function addRefund(Refund $refund)
	{
		$this->_refunds[] = $refund;

		return $this;
	}

	/**
	 * Clear out and reset the refunds to a given list.
	 *
	 * @param  array[Refund] $refunds
	 * @return Assembler
	 */
	public function setRefunds(array $refunds)
	{
		$this->clearRefund();

		foreach ($refunds as $refund) {
			$this->addRefund($refund);
		}

		return $this;
	}

	/**
	 * Clear out the list of refunds.
	 *
	 * @return Assembler
	 */
	public function clearRefunds()
	{
		$this->_refunds = [];

		return $this;
	}

	/**
	 * Set the return's accepted status.
	 *
	 * @param  bool|null $accepted
	 * @return Assembler
	 */
	public function setAccepted($accepted = true)
	{
		$this->_return->item->accepted = $accepted;

		return $this;
	}

	/**
	 * Complete the return by accepting it and changing the return item status
	 * to completed.
	 *
	 * @return Assembler
	 */
	public function setCompleted()
	{
		$this->setAccepted(true);

		$status = $this->_statuses->get(ReturnStatuses::RETURN_COMPLETED);

		$this->_return->item->status = $status;
		$this->_return->item->remainingBalance = 0;

		if ($this->_return->item->exchangeItem) {
			$this->_return->item->exchangeItem = OrderItemStatuses::DISPATCHED;
		}

		return $this;
	}
}