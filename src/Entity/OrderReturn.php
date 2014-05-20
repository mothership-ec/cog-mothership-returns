<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;
use Message\Mothership\Commerce\Order\Transaction\RecordInterface;

class OrderReturn implements EntityInterface, RecordInterface
{
	const RECORD_TYPE = 'return';

	public $id;
	public $authorship;

	public $item;
	public $payments = [];
	public $refunds = [];

	public function __construct()
	{
		$this->authorship = new Authorship;
	}

	public function getDisplayID()
	{
		return 'R' . $this->id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordType()
	{
		return self::RECORD_TYPE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordID()
	{
		return $this->id;
	}
}