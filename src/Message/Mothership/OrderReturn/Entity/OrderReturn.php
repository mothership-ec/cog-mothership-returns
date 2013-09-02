<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;

use Message\Mothership\OrderReturn\Statuses;
use Message\Mothership\OrderReturn\Resolutions;

class OrderReturn implements EntityInterface
{
	public $id;
	public $balance;

	public $item;
	public $order;
	public $refund;
	public $exchangeItem;

	public $authorship;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}

	public function isAccepted()
	{
		return $this->item->status->code >= Statuses::RETURN_ACCEPTED;
	}

	public function isRejected()
	{
		return $this->item->status->code == Statuses::RETURN_REJECTED;
	}

	public function isReceived()
	{
		return $this->item->status->code >= Statuses::RETURN_RECEIVED;
	}

	public function isRefundResolution()
	{
		return $this->resolution->code == 'refund';
	}

	public function isRefunded()
	{
		return $this->item->status->code >= Statuses::REFUND_PAID;
	}

	public function isExchangeResolution()
	{
		return $this->resolution->code == 'exchange';
	}

	public function isExchanged()
	{
		return $this->item->status->code >= Statuses::RETURN_ITEM_EXCHANGED;
	}
}