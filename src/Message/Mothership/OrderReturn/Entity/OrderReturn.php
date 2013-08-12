<?php

namespace Message\Mothership\Entity\OrderReturn;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;

class OrderReturn implements EntityInterface
{
	public $id;

	public $item;
	public $order;

	public $authorship;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}
}