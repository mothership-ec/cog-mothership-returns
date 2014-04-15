<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;

class OrderReturn implements EntityInterface
{
	public $id;
	public $authorship;

	public $item;

	public function __construct()
	{
		$this->authorship = new Authorship;
	}

	public function getDisplayID()
	{
		return 'R' . $this->id;
	}
}