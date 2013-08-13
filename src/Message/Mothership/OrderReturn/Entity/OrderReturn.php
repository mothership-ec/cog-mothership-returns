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

	public $authorship;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}

	public function isAccepted()
	{
		return $this->item->status >= Statuses::RETURN_ACCEPTED;
	}

	public function isRejected()
	{
		return $this->item->status == Statuses::RETURN_REJECTED;
	}

	public function isRefund()
	{
		return $this->resolution->code == Resolutions::REFUND;
	}

	public function isExchange()
	{
		return $this->resolution->code == Resolutions::EXCHANGE;
	}
}