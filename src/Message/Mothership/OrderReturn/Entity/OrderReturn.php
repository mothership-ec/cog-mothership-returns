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
		// Don't need to check with !== here as null is also a value negative
		// value in this case.
		return $this->balance != 0;
	}

	public function payeeIsCustomer()
	{
		if ($this->hasBalance()) return $this->balance > 0;
		return $this->calculatedBalance > 0;
	}

	public function payeeIsClient()
	{
		if ($this->hasBalance()) return $this->balance < 0;
		return $this->calculatedBalance < 0;
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