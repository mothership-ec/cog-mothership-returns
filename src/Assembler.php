<?php

namespace Message\Mothership\OrderReturn;

use LogicException;
use InvalidArgumentException;

use Message\Mothership\Commerce\Refund\Refund;
use Message\Mothership\Commerce\Payment\Payment;
use Message\Mothership\Commerce\Product\Unit\Unit as ProductUnit;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Order\Entity\Note\Note as OrderNote;
use Message\Mothership\Commerce\Order\Status\Collection as StatusCollection;
use Message\Mothership\Commerce\Product\Stock\Location\Location as StockLocation;

use Message\Mothership\Ecommerce\OrderItemStatuses;

use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Entity\OrderReturnItem;
use Message\Mothership\OrderReturn\Statuses as ReturnStatuses;

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
	 * The currency used for calculating the price of the return item.
	 *
	 * @var string
	 */
	protected $_currencyID;

	/**
	 * Construct the assembler.
	 *
	 * @param StatusCollection $statuses
	 */
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
	 * Set the currency to use when calculating the item price.
	 *
	 * @param  string $currencyID
	 * @return Assembler
	 */
	public function setCurrency($currencyID)
	{
		$this->_currencyID = $currencyID;

		if ($this->_return) {
			$this->_return->currencyID = $this->_currencyID;
		}

		return $this;
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

		$this->_return = new OrderReturn;

		if ($isOrderItem) {
			$this->setReturnItemFromOrderItem($item);
		} elseif ($isProductUnit) {
			$this->setReturnItemFromProductUnit($item);
		}

		$this->_return->currencyID = $this->_currencyID;

		return $this;
	}

	/**
	 * Set the return item from an OrderItem.
	 *
	 * @todo   Verify how the returnedValue should be calculated.
	 * @todo   Verify which value calculatedBalance should be taken from.
	 *
	 * @param  OrderItem $item
	 * @return Assembler
	 */
	public function setReturnItemFromOrderItem(OrderItem $item)
	{
		$this->_return->item = $returnItem = new OrderReturnItem;

		$this->setCurrency($item->order->currencyID);

		$returnItem->order             = $item->order;
		$returnItem->orderItem         = $item;
		$returnItem->listPrice         = $item->listPrice;
		$returnItem->actualPrice       = $item->actualPrice;
		$returnItem->returnedValue     = $item->actualPrice;
		$returnItem->calculatedBalance = 0 - $item->actualPrice;
		$returnItem->net               = $item->net;
		$returnItem->discount          = $item->discount;
		$returnItem->tax               = $item->tax;
		$returnItem->gross             = $item->gross;
		$returnItem->rrp               = $item->rrp;
		$returnItem->taxRate           = $item->taxRate;
		$returnItem->productTaxRate    = $item->productTaxRate;
		$returnItem->taxStrategy       = $item->taxStrategy;
		$returnItem->productID         = $item->productID;
		$returnItem->productName       = $item->productName;
		$returnItem->unit              = $item->getUnit();
		$returnItem->unitID            = $item->unitID;
		$returnItem->unitRevision      = $item->unitRevision;
		$returnItem->sku               = $item->sku;
		$returnItem->barcode           = $item->barcode;
		$returnItem->options           = $item->options;
		$returnItem->brand             = $item->brand;
		$returnItem->weight            = $item->weight;

		return $this;
	}

	/**
	 * Set the return item from a ProductUnit.
	 *
	 * @param  ProductUnit $unit
	 * @return Assembler
	 */
	public function setReturnItemFromProductUnit(ProductUnit $unit)
	{
		$this->_return->item = $returnItem = new OrderReturnItem;

		$retailPrice = $unit->getPrice('retail', $this->_currencyID);
		$rrpPrice    = $unit->getPrice('rrp', $this->_currencyID);

		$returnItem->listPrice         = $retailPrice;
		$returnItem->actualPrice       = $retailPrice;
		$returnItem->returnedValue     = $retailPrice;
		$returnItem->calculatedBalance = 0 - $retailPrice;
		$returnItem->rrp               = $rrpPrice;
		$returnItem->productTaxRate    = (float) $unit->product->taxRate;
		$returnItem->taxStrategy       = $unit->product->taxStrategy;
		$returnItem->productID         = $unit->product->id;
		$returnItem->productName       = $unit->product->name;
		$returnItem->unit              = $unit;
		$returnItem->unitID            = $unit->id;
		$returnItem->unitRevision      = $unit->revisionID;
		$returnItem->sku               = $unit->sku;
		$returnItem->barcode           = $unit->barcode;
		$returnItem->options           = implode($unit->options, ', ');
		$returnItem->brand             = $unit->product->brand;
		$returnItem->weight            = (int) $unit->weight;

		$this->_calculateTax($returnItem);

		return $this;
	}

	/**
	 * Set the balance for the return.
	 *
	 * @param  float $balance
	 * @return Assembler
	 */
	public function setBalance($balance)
	{
		$this->_return->item->balance = $balance;

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
		if (! $note->raisedFrom) {
			$note->raisedFrom = static::NOTE_RAISED_FROM_RETURN;
		}

		if (null === $note->customerNotified) {
			$note->customerNotified = 0;
		}

		$this->_return->item->note = $note;

		return $this;
	}

	/**
	 * Set the stock location for the returned item to be placed into.
	 *
	 * @param  StockLocation $location
	 * @return Assembler
	 */
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

		$item->listPrice = $unit->getPrice('retail', $this->_currencyID);
		$item->rrp       = $unit->getPrice('rrp', $this->_currencyID);

		$item->populate($unit);

		$item->stockLocation = $stockLocation;

		// Adjust the balance to reflect the exchange item
		$balance = $item->listPrice - $this->_return->item->actualPrice;
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
		$this->_return->payments[] = $payment;

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
		$this->_return->payments = [];

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
		$this->_return->refunds[] = $refund;

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
		$this->clearRefunds();

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
		$this->_return->refunds = [];

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
			$this->_return->item->exchangeItem->status = $this->_statuses->get(OrderItemStatuses::DISPATCHED);
		}

		return $this;
	}

	/**
	 * Calculates tax for return item
	 *
	 * @todo REFACTOR! This should be its own class (tax helper or something?)
	 *       and not copied from the Item EventListener in commerce!
	 */
	protected function _calculateTax($returnItem)
	{
		// Set the tax rate to whatever the product's tax rate is, if not already set
		if (!$returnItem->taxRate) {
			$returnItem->taxRate = $returnItem->productTaxRate;
		}

		// Set the gross to the list price minus the discount
		$returnItem->gross = round($returnItem->actualPrice - $returnItem->discount, 2);

		// Calculate tax where the strategy is exclusive
		if ('exclusive' === $returnItem->taxStrategy) {
			$returnItem->tax    = round($returnItem->gross * ($returnItem->taxRate / 100), 2);
			$returnItem->gross += $returnItem->tax;
		}
		// Calculate tax where the strategy is inclusive
		else {
			$returnItem->tax = $this->_calculateInclusiveTax($returnItem->gross, $returnItem->taxRate);
		}

		// Set the net value to gross - tax
		$returnItem->net = round($returnItem->gross - $returnItem->tax, 2);
	}

	/**
	 * Calculates inclusive tax from given amount and tax rate.
	 *
	 * @param  float $amount Amount
	 * @param  float $rate   Tax rate
	 * @return float         Calculated inclusive tax
	 *
	 * @todo This should also be refactored and not copied from commerce!
	 */
	protected function _calculateInclusiveTax($amount, $rate)
	{
		return round(($amount / (100 + $rate)) * $rate, 2);
	}
}