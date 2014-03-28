<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;

use Message\Mothership\OrderReturn\Statuses;
use Message\Mothership\OrderReturn\Resolutions;

class OrderReturn implements EntityInterface
{
	public $id;
	public $balance;
	public $calculatedBalance;
	public $accepted;

	public $item;
	public $order;
	public $refund;
	public $exchangeItem;

	public $authorship;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}

	public function getDisplayID()
	{
		return 'R' . $this->id;
	}

	public function isReceived()
	{
		return $this->item->status->code >= Statuses::RETURN_RECEIVED or
			   $this->item->status->code == Order\Statuses::CANCELLED;
	}

	public function isAccepted()
	{
		return $this->accepted == true;
	}

	public function isRejected()
	{
		return $this->accepted == false and $this->accepted !== null;
	}

	public function isRefundResolution()
	{
		return $this->resolution->code == 'refund';
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
		return $this->balance !== 0.0;
	}

	/**
	 * If the balance is owed by the client to be paid to the customer.
	 *
	 * @return bool
	 */
	public function payeeIsCustomer()
	{
		if ($this->hasBalance()) return $this->balance < 0;
		return $this->calculatedBalance < 0;
	}

	/**
	 * If the balance is owed by the customer to be paid to the client.
	 *
	 * @return bool
	 */
	public function payeeIsClient()
	{
		if ($this->hasBalance()) return $this->balance > 0;
		return $this->calculatedBalance > 0;
	}

	public function isExchangeResolution()
	{
		return $this->resolution->code == 'exchange';
	}

	public function isExchanged()
	{
		return $this->exchangeItem->status->code >= Order\Statuses::AWAITING_DISPATCH;
	}

	public function isReturnedItemProcessed()
	{
		return $this->item->status->code < Statuses::AWAITING_RETURN or
			   $this->item->status->code > Statuses::RETURN_RECEIVED;
	}
}